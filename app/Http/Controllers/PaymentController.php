<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\VNPayService;
use Illuminate\Http\Request;
use App\Mail\OrderConfirmedMail;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{

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

        Mail::to($user->email)->send(new OrderConfirmedMail($order));

        return response()->json([
            'message' => 'Đặt hàng thành công',
            'data' => $order
        ]);
    }
    public function __construct(protected VNPayService $vnpay)
    {
    }
    public function createVnpay(Request $request, $orderId)
    {
        $user = $request->user();
        $order = Order::findOrFail($orderId);
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }
        if ($order->payment_status !== 'pending') {
            return response()->json(['message' => 'Đơn hàng đã được xử lý'], 400);
        }

        $url = $this->vnpay->createPaymentUrl(
            orderId: $order->id,
            amount: (int) $order->total_amount,
            ipAddr: $request->ip(),
        );

        return response()->json(['payment_url' => $url]);
    }

    public function return(Request $request)
    {
        $params = $request->all();
        if (!$this->vnpay->verifyReturn($params)) {
            return redirect('/payment/failed?error=invalid_signature');
        }
        $orderId = explode('_', $params['vnp_TxnRef'])[0];
        $order = Order::findOrFail($orderId);
        if ($order->payment_status === 'paid') {
            return redirect('/payment/success?order_id=' . $order->id);
        }

        if ($params['vnp_ResponseCode'] === '00') {
            $order->update([
                'payment_status' => 'paid',
                'order_status' => 'confirmed',
                'vnpay_txn_ref' => $params['vnp_TransactionNo'],
                'paid_at' => now(),
            ]);

            return redirect('/payment/success?order_id=' . $order->id);
        }

        $order->update(['payment_status' => 'failed']);

        return redirect('/payment/failed?error=' . $params['vnp_ResponseCode']);
    }

    public function ipn(Request $request)
    {
        $params = $request->all();
        if (!$this->vnpay->verifyReturn($params)) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }
        $orderId = explode('_', $params['vnp_TxnRef'])[0];
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['RspCode' => '02', 'Message' => 'Already confirmed']);
        }
        if ($params['vnp_Amount'] != $order->total_amount * 100) {
            return response()->json(['RspCode' => '04', 'Message' => 'Amount mismatch']);
        }

        if ($params['vnp_ResponseCode'] === '00') {
            $order->update([
                'payment_status' => 'paid',
                'order_status' => 'confirmed',
                'vnpay_txn_ref' => $params['vnp_TransactionNo'],
                'paid_at' => now(),
            ]);
        }

        return response()->json(['RspCode' => '00', 'Message' => 'OK']);
    }
}