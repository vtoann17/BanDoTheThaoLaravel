<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderCancellationController extends Controller
{
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items.variant')->findOrFail($id);

        if ($user->role !== 'admin' && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        if (!in_array($order->order_status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Không thể hủy đơn ở trạng thái ' . $order->order_status
            ], 400);
        }

        $data = $request->validate([
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($order, $data) {
            if ($order->payment_status === 'paid' && $order->payment_method !== 'cod') {
                $order->update(['payment_status' => 'refund_pending']);

                $refundSuccess = match ($order->payment_method) {
                    'momo'  => $this->refundMomo($order),
                    'vnpay' => $this->refundVnpay($order),
                    default => false,
                };

                if (!$refundSuccess) {
                    throw new \Exception('Hoàn tiền thất bại, không thể hủy đơn');
                }
            }

            foreach ($order->items as $item) {
                $item->variant->increment('stock', $item->quantity);
            }

            $order->update([
                'order_status'   => 'cancelled',
                'cancel_reason'  => $data['cancel_reason'] ?? null,
                'payment_status' => $order->payment_status === 'refund_pending'
                    ? 'refunded'
                    : $order->payment_status,
            ]);
        });

        return response()->json([
            'message' => 'Hủy đơn hàng thành công',
            'data'    => $order->fresh()->load('items.variant.product')
        ]);
    }

    private function refundMomo(Order $order): bool
    {
        $partnerCode = config('services.momo.partner_code');
        $accessKey   = config('services.momo.access_key');
        $secretKey   = config('services.momo.secret_key');
        $endpoint    = 'https://test-payment.momo.vn/v2/gateway/api/refund';

        // total_amount đã gồm shipping_fee và đã trừ discount — không cộng thêm
        $amount    = (int) $order->total_amount;
        $orderId   = 'REFUND_' . $order->id . '_' . time();
        $requestId = (string) time();
        $transId   = $order->momo_trans_id;

        $rawHash = "accessKey={$accessKey}"
            . "&amount={$amount}"
            . "&description=Hoàn tiền đơn hàng #{$order->id}"
            . "&orderId={$orderId}"
            . "&partnerCode={$partnerCode}"
            . "&requestId={$requestId}"
            . "&transId={$transId}";

        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $response = Http::timeout(10)->post($endpoint, [
            'partnerCode' => $partnerCode,
            'orderId'     => $orderId,
            'requestId'   => $requestId,
            'amount'      => $amount,
            'transId'     => $transId,
            'lang'        => 'vi',
            'description' => 'Hoàn tiền đơn hàng #' . $order->id,
            'signature'   => $signature,
        ]);

        Log::info('MoMo Refund response', $response->json() ?? []);

        return $response->successful() && $response->json('resultCode') === 0;
    }

    private function refundVnpay(Order $order): bool
    {
        $tmnCode    = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');
        $endpoint   = 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction';

        $requestId     = date('YmdHis') . '_' . $order->id;
        $createDate    = date('YmdHis');
        $transDate     = Carbon::parse($order->paid_at)->format('YmdHis');
        // total_amount đã gồm shipping_fee và đã trừ discount — nhân 100 cho VNPay
        $amount        = (int) ($order->total_amount * 100);
        $ipAddr        = request()->ip();
        $orderInfo     = 'Hoan_tien_don_hang_' . $order->id;
        $transType     = '02';

        $txnRef        = $order->vnpay_txn_ref;
        $transactionNo = $order->vnpay_transaction_no;

        $rawHash = implode('|', [
            $requestId,
            '2.1.0',
            'refund',
            $tmnCode,
            $transType,
            $txnRef,
            (string) $amount,
            $transactionNo,
            $transDate,
            'system',
            $createDate,
            $ipAddr,
            $orderInfo,
        ]);

        Log::info('VNPay Refund rawHash', [
            'requestId'     => $requestId,
            'txnRef'        => $txnRef,
            'transactionNo' => $transactionNo,
            'amount'        => $amount,
            'transDate'     => $transDate,
            'createDate'    => $createDate,
            'ipAddr'        => $ipAddr,
            'rawHash'       => $rawHash,
        ]);

        $signature = hash_hmac('sha512', $rawHash, $hashSecret);

        $response = Http::timeout(10)->post($endpoint, [
            'vnp_RequestId'       => $requestId,
            'vnp_Version'         => '2.1.0',
            'vnp_Command'         => 'refund',
            'vnp_TmnCode'         => $tmnCode,
            'vnp_TransactionType' => $transType,
            'vnp_TxnRef'          => $txnRef,
            'vnp_Amount'          => $amount,
            'vnp_OrderInfo'       => $orderInfo,
            'vnp_TransactionNo'   => $transactionNo,
            'vnp_TransactionDate' => $transDate,
            'vnp_CreateBy'        => 'system',
            'vnp_CreateDate'      => $createDate,
            'vnp_IpAddr'          => $ipAddr,
            'vnp_SecureHash'      => $signature,
        ]);

        Log::info('VNPay Refund response', $response->json() ?? []);

        return $response->successful() && $response->json('vnp_ResponseCode') === '00';
    }
}