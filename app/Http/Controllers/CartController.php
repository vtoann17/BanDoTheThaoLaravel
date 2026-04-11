<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Variant;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = auth()->id();

        $cart = Cart::with(['variant.product', 'variant.values.attribute'])
            ->where('user_id', $userId)
            ->get()
            ->map(function ($item) {
                $variant = $item->variant;
                $product = $variant?->product;
                $baseUrl = config('app.url');

                $image = $variant?->img
                    ? $baseUrl . '/storage/' . $variant->img
                    : ($product?->image ? $baseUrl . '/storage/' . $product->image : null);

                $attrs = $variant?->values
                    ?->map(fn($av) => [
                        'name' => $av->attribute?->name,
                        'value' => $av->value,
                    ]) ?? collect();

                return [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'stock' => $variant?->stock ?? 0,
                    'name' => $product?->name ?? 'Sản phẩm',
                    'image' => $image,
                    'attrs' => $attrs,
                    'sale' => $variant?->sale ?? $product?->sale ?? 0,
                ];
            });

        return response()->json($cart);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'variant_id' => 'required|exists:variants,id',
            'quantity' => 'required|integer|min:1'
        ]);
        $variant = Variant::findOrFail($data['variant_id']);
        if ($variant->stock < $data['quantity']) {
            return response()->json([
                'message' => 'Không đủ hàng'
            ], 400);
        }
        $data['user_id'] = auth()->id();
        $data['price'] = $variant->price;
        $item = Cart::where('user_id', $data['user_id'])
            ->where('variant_id', $data['variant_id'])
            ->first();
        if ($item) {
            $newQty = $item->quantity + $data['quantity'];
            if ($variant->stock < $newQty) {
                return response()->json([
                    'message' => 'Vượt quá số lượng tồn kho'
                ], 400);
            }
            $item->update([
                'quantity' => $newQty
            ]);
        } else {
            $item = Cart::create($data);
        }
        return response()->json([
            'message' => 'Thêm vào giỏ hàng thành công',
            'data' => $item
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
        if ($cart->user_id != auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $cart->load('variant');

        return response()->json($cart);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cart $cart)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        if ($cart->user_id != auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $variant = Variant::find($cart->variant_id);
        if (!$variant) {
            return response()->json(['message' => 'Variant không tồn tại'], 400);
        }
        if ($variant->stock < $data['quantity']) {
            return response()->json(['message' => 'Không đủ hàng'], 400);
        }

        $cart->quantity = $data['quantity'];
        $cart->save();

        return response()->json(['message' => 'Cập nhật thành công']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $cart = Cart::find($id);
        if (!$cart) {
            return response()->json([
                'message' => 'Item không tồn tại'
            ], 404);
        }
        if ($cart->user_id != auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        Cart::destroy($id);

        return response()->json([
            'message' => 'Đã xóa khỏi giỏ hàng'
        ]);
    }
}
