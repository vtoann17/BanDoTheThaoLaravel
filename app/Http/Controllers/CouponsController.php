<?php

namespace App\Http\Controllers;

use App\Models\Coupons;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponsController extends Controller
{
    public function index()
    {
        return response()->json(Coupons::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:50|unique:coupons,code',
            'discount_type'   => 'required|in:percent,fixed',
            'discount_value'  => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount'    => 'nullable|numeric|min:0',
            'usage_limit'     => 'nullable|integer|min:1',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'is_active'       => 'nullable|boolean',
        ]);

        $coupon = Coupons::create($data);

        return response()->json([
            'message' => 'Thêm thành công',
            'data'    => $coupon,
        ], 201);
    }

    public function show($id)
    {
        return response()->json(Coupons::findOrFail($id));
    }

    public function apply(Request $request)
    {
        $request->validate([
            'code'        => 'required|string',
            'order_total' => 'required|numeric|min:0',
        ]);

        $coupon = Coupons::where('code', $request->code)->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn'], 400);
        }

        if ($request->order_total < $coupon->min_order_value) {
            return response()->json([
                'message' => 'Đơn hàng chưa đạt giá trị tối thiểu ' . number_format($coupon->min_order_value) . 'đ',
            ], 400);
        }

        $discount = $coupon->calcDiscount($request->order_total);

        return response()->json([
            'message'       => 'Áp dụng thành công',
            'code'          => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount'      => $discount,
            'final_total'   => $request->order_total - $discount,
        ]);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupons::findOrFail($id);

        $data = $request->validate([
            'code'            => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($id)],
            'discount_type'   => 'required|in:percent,fixed',
            'discount_value'  => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount'    => 'nullable|numeric|min:0',
            'usage_limit'     => 'nullable|integer|min:1',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'is_active'       => 'nullable|boolean',
        ]);

        $coupon->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data'    => $coupon,
        ]);
    }

    public function destroy($id)
    {
        Coupons::findOrFail($id)->delete();

        return response()->json(['message' => 'Xóa thành công']);
    }
}