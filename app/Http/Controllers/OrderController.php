<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Cart;
use App\Models\Address;
use App\Models\Coupons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Order::with(['user', 'items.variant.product']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $query->where('id', $request->search);
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $sortBy = in_array($request->sort_by, ['id', 'total_amount', 'created_at'])
            ? $request->sort_by
            : 'created_at';

        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        $perPage = in_array((int)$request->per_page, [2, 5, 10, 20, 50])
            ? (int)$request->per_page
            : 10;

        $result = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'data'         => $result->items(),
            'total'        => $result->total(),
            'per_page'     => $result->perPage(),
            'current_page' => $result->currentPage(),
            'last_page'    => $result->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'address_id'     => 'required|exists:addresses,id',
            'payment_method' => 'required|in:vnpay,cod,momo',
            'shipping_fee'   => 'nullable|integer|min:0',
            'coupon_code'    => 'nullable|string',
            'discount'       => 'nullable|integer|min:0',
        ]);

        $address = Address::where('id', $data['address_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        $coupon = null;
        if (!empty($data['coupon_code'])) {
            $coupon = Coupons::where('code', $data['coupon_code'])->first();

            if (!$coupon || !$coupon->isValid()) {
                return response()->json(['message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn'], 400);
            }
        }

        $order = DB::transaction(function () use ($user, $data, $address, $coupon) {
            $cartItems = Cart::with([
                'variant' => fn($q) => $q->lockForUpdate()
            ])->where('user_id', $user->id)->get();

            if ($cartItems->isEmpty()) {
                throw new \Exception('CART_EMPTY');
            }

            foreach ($cartItems as $item) {
                if ($item->variant->stock < $item->quantity) {
                    throw new \Exception('OUT_OF_STOCK:' . $item->variant->sku);
                }
            }

            $subtotal = $cartItems->sum(
                fn($item) => $item->quantity * $item->variant->price
            );

            $shipping = $data['shipping_fee'] ?? 0;
            $discount = $data['discount'] ?? 0;
            $total    = $subtotal + $shipping - $discount;

            $order = Order::create([
                'user_id'        => $user->id,
                'address_id'     => $address->id,
                'total_amount'   => $total,
                'shipping_fee'   => $shipping,
                'coupon_code'    => $data['coupon_code'] ?? null,
                'discount'       => $discount,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'order_status'   => 'pending',
            ]);

            foreach ($cartItems as $item) {
                OrderDetail::create([
                    'order_id'   => $order->id,
                    'variant_id' => $item->variant_id,
                    'quantity'   => $item->quantity,
                    'price'      => $item->variant->price,
                ]);

                $item->variant->decrement('stock', $item->quantity);
            }

            if ($coupon) {
                Coupons::where('id', $coupon->id)->lockForUpdate()->first();
                $coupon->increment('used_count');
            }

            Cart::where('user_id', $user->id)->delete();

            return $order;
        });

        return response()->json([
            'message' => 'Đặt hàng thành công',
            'data'    => $order->load('items.variant.product'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::with(['user', 'items.variant.product'])->findOrFail($id);

        if ($user->role !== 'admin' && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        $order = Order::findOrFail($id);

        $data = $request->validate([
            'order_status'   => 'nullable|in:pending,confirmed,shipping,completed,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed',
        ]);

        $order->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data'    => $order,
        ]);
    }
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::with('items.variant')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!in_array($order->order_status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Không thể hủy đơn ở trạng thái này',
            ], 400);
        }

        DB::transaction(function () use ($order, $request) {
            foreach ($order->items as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                }
            }

            if ($order->coupon_code) {
                Coupons::where('code', $order->coupon_code)
                    ->where('used_count', '>', 0)
                    ->decrement('used_count');
            }

            $order->update([
                'order_status'  => 'cancelled',
                'cancel_reason' => $request->input('cancel_reason'),
            ]);
        });

        return response()->json([
            'message' => 'Hủy đơn thành công',
            'data'    => $order->fresh(),
        ]);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Xoá thành công']);
    }
}