<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Mail\OrderConfirmedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private string $vnp_TmnCode;
    private string $vnp_HashSecret;
    private string $vnp_Url;
    private string $vnp_ReturnUrl;

    public function __construct()
    {
        $this->vnp_TmnCode = config('services.vnpay.tmn_code');
        $this->vnp_HashSecret = config('services.vnpay.hash_secret');
        $this->vnp_Url = config('services.vnpay.url');
        $this->vnp_ReturnUrl = config('services.vnpay.return_url');
    }


    public function createCod(Request $request, $orderId)
    {
        $user = $request->user();
        $order = Order::with('items.variant.product', 'user')->findOrFail($orderId);

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        if ($order->payment_method !== 'cod') {
            return response()->json(['message' => 'Đơn hàng không phải COD'], 400);
        }

        if ($order->payment_status !== 'pending') {
            return response()->json(['message' => 'Đơn hàng đã được xử lý'], 400);
        }

        $order->update([
            'order_status' => 'confirmed',
        ]);

        Mail::to($user->email)->send(new OrderConfirmedMail($order->fresh('items.variant.product', 'user')));

        return response()->json([
            'message' => 'Đặt hàng thành công',
            'data' => $order,
        ]);
    }


    public function createVnpay(Request $request, $orderId)
    {
        $user = $request->user();
        $order = Order::findOrFail($orderId);

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        if ($order->payment_method !== 'vnpay') {
            return response()->json(['message' => 'Đơn hàng không phải VNPay'], 400);
        }

        if ($order->payment_status !== 'pending') {
            return response()->json(['message' => 'Đơn hàng đã được xử lý'], 400);
        }
        $amount = (int) $order->total_amount + (int) $order->shipping_fee;

        $url = $this->buildPaymentUrl(
            orderId: $order->id,
            amount: $amount,
            ipAddr: $request->ip(),
        );

        return response()->json(['payment_url' => $url]);
    }


    public function return(Request $request)
    {
        $params = $request->query();

        if (!$this->verifySignature($params)) {
            Log::warning('VNPay return: invalid signature', $params);
            return redirect(env('FRONTEND_URL') . '/ordersuccess?error=invalid_signature');
        }

        $orderId = explode('_', $params['vnp_TxnRef'])[0];
        $order = Order::with('items.variant.product', 'user')->find($orderId);

        if (!$order) {
            return redirect(env('FRONTEND_URL') . '/ordersuccess?error=order_not_found');
        }

        if ($params['vnp_ResponseCode'] === '00') {
            if ($order->payment_status === 'pending') {
                $order->update([
                    'payment_status' => 'paid',
                    'vnpay_txn_ref'  => $params['vnp_TransactionNo'] ?? null,
                    'paid_at'        => now(),
                ]);

                try {
                    Mail::to($order->user->email)->send(new OrderConfirmedMail($order));
                } catch (\Exception $e) {
                    Log::error('VNPay return: gửi mail thất bại', [
                        'order_id' => $order->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            return redirect(env('FRONTEND_URL') . '/ordersuccess?order_id=' . $order->id);
        }

        $order->update(['payment_status' => 'failed']);

        return redirect(env('FRONTEND_URL') . '/ordersuccess?error=' . $params['vnp_ResponseCode']);
    }

    public function ipn(Request $request)
    {
        $params = $request->query();

        if (!$this->verifySignature($params)) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }

        $orderId = explode('_', $params['vnp_TxnRef'])[0];
        $order = Order::with('user')->find($orderId);

        if (!$order) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        $expectedAmount = (int) (($order->total_amount + $order->shipping_fee) * 100);
        $receivedAmount = (int) $params['vnp_Amount'];

        if ($receivedAmount !== $expectedAmount) {
            Log::warning('VNPay IPN amount mismatch', [
                'order_id'        => $orderId,
                'expected_amount' => $expectedAmount,
                'received_amount' => $receivedAmount,
            ]);
            return response()->json(['RspCode' => '04', 'Message' => 'Amount mismatch']);
        }
        if ($order->payment_status !== 'pending') {
            return response()->json(['RspCode' => '02', 'Message' => 'Already confirmed']);
        }

        try {
            if ($params['vnp_ResponseCode'] === '00') {
                $order->update([
                    'payment_status' => 'paid',
                    'vnpay_txn_ref'  => $params['vnp_TransactionNo'] ?? null,
                    'paid_at'        => now(),
                ]);

                Mail::to($order->user->email)->send(new OrderConfirmedMail($order->load('items.variant.product')));
            } else {
                $order->update(['payment_status' => 'failed']);
            }
        } catch (\Exception $e) {
            Log::error('VNPay IPN update failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['RspCode' => '99', 'Message' => 'Unknown error']);
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }

    private function buildPaymentUrl(int $orderId, int $amount, string $ipAddr): string
    {
        $startTime = date('YmdHis');
        $expire = date('YmdHis', strtotime('+15 minutes', strtotime($startTime)));

        $params = [
            'vnp_Version'   => '2.1.0',
            'vnp_TmnCode'   => $this->vnp_TmnCode,
            'vnp_Amount'    => $amount * 100,
            'vnp_Command'   => 'pay',
            'vnp_CreateDate'=> $startTime,
            'vnp_CurrCode'  => 'VND',
            'vnp_IpAddr'    => $ipAddr,
            'vnp_Locale'    => 'vn',
            'vnp_OrderInfo' => 'Thanh_toan_don_hang_' . $orderId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $this->vnp_ReturnUrl,
            'vnp_TxnRef'    => $orderId . '_' . $startTime,
            'vnp_ExpireDate'=> $expire,
        ];

        ksort($params);

        $hashParts  = [];
        $queryParts = [];

        foreach ($params as $key => $value) {
            $hashParts[]  = urlencode($key) . '=' . urlencode($value);
            $queryParts[] = urlencode($key) . '=' . urlencode($value);
        }

        $hashData   = implode('&', $hashParts);
        $query      = implode('&', $queryParts);
        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

        return $this->vnp_Url . '?' . $query . '&vnp_SecureHash=' . $secureHash;
    }

    private function verifySignature(array $params): bool
    {
        $vnp_SecureHash = $params['vnp_SecureHash'] ?? '';

        $data = collect($params)
            ->filter(fn($v, $k) => str_starts_with($k, 'vnp_') && $k !== 'vnp_SecureHash')
            ->sortKeys()
            ->mapWithKeys(fn($v, $k) => [urlencode($k) => urlencode($v)])
            ->map(fn($v, $k) => "$k=$v")
            ->implode('&');

        $secureHash = hash_hmac('sha512', $data, $this->vnp_HashSecret);

        return hash_equals($secureHash, $vnp_SecureHash);
    }
}