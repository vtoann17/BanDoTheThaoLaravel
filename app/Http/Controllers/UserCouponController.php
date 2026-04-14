<?php

namespace App\Http\Controllers;

use App\Models\Coupons;
use App\Models\UserCoupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserCouponController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $userCoupons = UserCoupon::with('coupon')
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn($uc) => $uc->coupon);

        return response()->json($userCoupons);
    }

    public function claim(Request $request)
    {
        $userId = $request->user()->id;

        $request->validate([
            'coupon_id' => 'required|integer|exists:coupons,id',
        ]);

        $coupon = Coupons::findOrFail($request->coupon_id);

        if (!$coupon->isValid()) {
            return response()->json(['message' => 'Mã giảm giá không còn hiệu lực'], 400);
        }

        $already = UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $coupon->id)
            ->exists();

        if ($already) {
            return response()->json(['message' => 'Bạn đã nhận mã này rồi'], 409);
        }

        UserCoupon::create([
            'user_id'    => $userId,
            'coupon_id'  => $coupon->id,
            'claimed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Nhận mã thành công',
            'data'    => $coupon,
        ], 201);
    }
}