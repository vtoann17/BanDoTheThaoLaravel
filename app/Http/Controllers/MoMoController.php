<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Mail\OrderConfirmedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MoMoController extends Controller
{
    public function pay(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $user = $request->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($order->payment_status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn hàng đã được thanh toán hoặc không hợp lệ',
            ], 400);
        }

        $partnerCode = config('services.momo.partner_code');
        $accessKey = config('services.momo.access_key');
        $secretKey = config('services.momo.secret_key');
        $endpoint = config('services.momo.endpoint');
        $redirectUrl = config('services.momo.return_url') . '?order_id=' . $order->id;
        $ipnUrl = config('services.momo.notify_url');
        $amount = (int) $order->total_amount;

        $orderId = 'ORDER_' . $order->id . '_' . time();
        $orderInfo = 'Thanh toán đơn hàng #' . $order->id;
        $requestId = (string) time();
        $requestType = 'payWithMethod';
        $extraData = base64_encode(json_encode(['order_id' => $order->id]));

        $rawHash = "accessKey={$accessKey}"
            . "&amount={$amount}"
            . "&extraData={$extraData}"
            . "&ipnUrl={$ipnUrl}"
            . "&orderId={$orderId}"
            . "&orderInfo={$orderInfo}"
            . "&partnerCode={$partnerCode}"
            . "&redirectUrl={$redirectUrl}"
            . "&requestId={$requestId}"
            . "&requestType={$requestType}";

        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $response = Http::timeout(10)->post($endpoint, [
            'partnerCode' => $partnerCode,
            'partnerName' => 'Test',
            'storeId' => 'MoMoStore',
            'accessKey' => $accessKey,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'requestType' => $requestType,
            'extraData' => $extraData,
            'lang' => 'vi',
            'signature' => $signature,
        ]);

        $result = $response->json();

        if (isset($result['payUrl'])) {
            return response()->json([
                'status' => 'success',
                'payUrl' => $result['payUrl'],
                'orderId' => $orderId,
                'amount' => $amount,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'] ?? 'Tạo thanh toán thất bại',
        ], 400);
    }

    public function return(Request $request)
    {
        Log::info('MoMo Return received', $request->all());

        $resultCode = $request->query('resultCode');
        $extraData = $request->query('extraData');
        $orderId = $request->query('order_id');

        if ($extraData) {
            $decoded = json_decode(base64_decode($extraData), true);
            $orderId = $decoded['order_id'] ?? $orderId;
        }

        if (!$orderId) {
            return redirect(env('FRONTEND_URL') . '/orderfailed?error=order_not_found');
        }

        if ($resultCode == '0') {
            DB::transaction(function () use ($orderId, $request) {
                $order = Order::where('id', $orderId)
                    ->where('payment_status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'order_status' => 'confirmed',
                        'payment_method' => 'momo',
                        'paid_at' => Carbon::now(),
                        'momo_trans_id' => $request->query('transId'),
                    ]);

                    try {
                        Mail::to($order->user->email)
                            ->send(new OrderConfirmedMail($order->fresh('items.variant.product', 'user')));
                    } catch (\Exception $e) {
                        Log::error('MoMo Return: gửi mail thất bại', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    Log::info('MoMo Return: Cập nhật thành công đơn #' . $orderId);
                }
            });

            return redirect(env('FRONTEND_URL') . '/ordersuccess?order_id=' . $orderId);
        }

        $order = Order::find($orderId);
        if ($order && $order->payment_status === 'pending') {
            $order->update([
                'payment_status' => 'failed',
                'order_status' => 'cancelled',
            ]);
        }

        return redirect(env('FRONTEND_URL') . '/orderfailed?order_id=' . $orderId);
    }

    public function notify(Request $request)
    {
        Log::info('MoMo IPN received', $request->all());

        $data = $request->all();
        $secretKey = config('services.momo.secret_key');
        $accessKey = config('services.momo.access_key');

        $rawHash = "accessKey={$accessKey}"
            . "&amount={$data['amount']}"
            . "&extraData={$data['extraData']}"
            . "&message={$data['message']}"
            . "&orderId={$data['orderId']}"
            . "&orderInfo={$data['orderInfo']}"
            . "&orderType={$data['orderType']}"
            . "&partnerCode={$data['partnerCode']}"
            . "&payType={$data['payType']}"
            . "&requestId={$data['requestId']}"
            . "&responseTime={$data['responseTime']}"
            . "&resultCode={$data['resultCode']}"
            . "&transId={$data['transId']}";

        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        if ($signature !== ($data['signature'] ?? '')) {
            Log::warning('MoMo IPN: Sai chữ ký', [
                'expected' => $signature,
                'received' => $data['signature'] ?? '',
            ]);
            return response()->json(['status' => 'invalid signature'], 400);
        }

        $extra = json_decode(base64_decode($data['extraData'] ?? ''), true);
        $orderId = $extra['order_id'] ?? null;

        if (!$orderId) {
            Log::warning('MoMo IPN: Không tìm thấy order_id', $data);
            return response()->json(['status' => 'ok']);
        }

        if ($data['resultCode'] == 0) {
            DB::transaction(function () use ($orderId, $data) {
                $order = Order::where('id', $orderId)
                    ->where('payment_status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'order_status' => 'confirmed',
                        'payment_method' => 'momo',
                        'paid_at' => Carbon::now(),
                        'momo_trans_id' => $data['transId'],
                    ]);

                    try {
                        Mail::to($order->user->email)
                            ->send(new OrderConfirmedMail($order->load('items.variant.product', 'user')));
                    } catch (\Exception $e) {
                        Log::error('MoMo IPN: gửi mail thất bại', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    Log::info('MoMo IPN: Cập nhật thành công đơn hàng #' . $orderId);
                } else {
                    Log::warning('MoMo IPN: Không tìm thấy order hoặc đã paid, id=' . $orderId);
                }
            });
        } else {
            $order = Order::where('id', $orderId)
                ->where('payment_status', 'pending')
                ->first();

            if ($order) {
                $order->update([
                    'payment_status' => 'failed',
                    'order_status' => 'cancelled',
                ]);
            }

            Log::info('MoMo IPN: Thanh toán thất bại', [
                'resultCode' => $data['resultCode'],
                'message' => $data['message'] ?? '',
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}