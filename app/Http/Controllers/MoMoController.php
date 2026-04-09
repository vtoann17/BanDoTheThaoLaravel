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

        $user  = $request->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($order->payment_status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Đơn hàng đã được thanh toán hoặc không hợp lệ',
            ], 400);
        }

        $partnerCode = config('services.momo.partner_code');
        $accessKey   = config('services.momo.access_key');
        $secretKey   = config('services.momo.secret_key');
        $endpoint    = config('services.momo.endpoint');
        $redirectUrl = config('services.momo.return_url') . '?order_id=' . $order->id;
        $ipnUrl      = config('services.momo.notify_url');

        $amount      = (int) ($order->total_amount + $order->shipping_fee);
        $orderId     = 'ORDER_' . $order->id . '_' . time();
        $orderInfo   = 'Thanh toán đơn hàng #' . $order->id;
        $requestId   = (string) time();
        $requestType = 'payWithMethod';
        $extraData   = base64_encode(json_encode(['order_id' => $order->id]));

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
            'storeId'     => 'MoMoStore',
            'accessKey'   => $accessKey,
            'requestId'   => $requestId,
            'amount'      => $amount,
            'orderId'     => $orderId,
            'orderInfo'   => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl'      => $ipnUrl,
            'requestType' => $requestType,
            'extraData'   => $extraData,
            'lang'        => 'vi',
            'signature'   => $signature,
        ]);

        $result = $response->json();

        if (isset($result['payUrl'])) {
            return response()->json([
                'status'  => 'success',
                'payUrl'  => $result['payUrl'],
                'orderId' => $orderId,
                'amount'  => $amount,
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => $result['message'] ?? 'Tạo thanh toán thất bại',
        ], 400);
    }

    public function return(Request $request)
    {
        Log::info('MoMo Return received', $request->all());

        $resultCode = $request->query('resultCode');
        $extraData  = $request->query('extraData');
        $orderId    = $request->query('order_id');
        if ($extraData) {
            $decoded = json_decode(base64_decode($extraData), true);
            $orderId = $decoded['order_id'] ?? $orderId;
        }

        if (!$orderId) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng'], 400);
        }

        if ($resultCode == '0') {
            DB::transaction(function () use ($orderId) {
                $order = Order::where('id', $orderId)
                    ->where('payment_status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'momo',
                        'paid_at'        => Carbon::now(),
                    ]);

                    Mail::to($order->user->email)
                        ->send(new OrderConfirmedMail($order));

                    Log::info('MoMo Return: Cập nhật thành công đơn #' . $orderId);
                }
            });

            return response()->json([
                'status'   => 'success',
                'message'  => 'Thanh toán thành công',
                'order_id' => $orderId,
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Thanh toán thất bại hoặc đã bị hủy',
        ], 400);
    }

    public function notify(Request $request)
    {
        Log::info('MoMo IPN received', $request->all());

        $data      = $request->all();
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
                'expected'  => $signature,
                'received'  => $data['signature'] ?? '',
            ]);
            return response()->json(['status' => 'invalid signature'], 400);
        }

        if ($data['resultCode'] == 0) {
            $extra   = json_decode(base64_decode($data['extraData'] ?? ''), true);
            $orderId = $extra['order_id'] ?? null;

            if (!$orderId) {
                Log::warning('MoMo IPN: Không tìm thấy order_id', $data);
                return response()->json(['status' => 'ok']);
            }

            DB::transaction(function () use ($orderId) {
                $order = Order::where('id', $orderId)
                    ->where('payment_status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'momo',
                        'paid_at'        => Carbon::now(),
                    ]);

                    Mail::to($order->user->email)
                        ->send(new OrderConfirmedMail($order));

                    Log::info('MoMo IPN: Cập nhật thành công đơn hàng #' . $orderId);
                } else {
                    Log::warning('MoMo IPN: Không tìm thấy order hoặc đã paid, id=' . $orderId);
                }
            });
        } else {
            Log::info('MoMo IPN: Thanh toán thất bại', [
                'resultCode' => $data['resultCode'],
                'message'    => $data['message'] ?? '',
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}