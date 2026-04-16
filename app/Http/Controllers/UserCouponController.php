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

        $query = UserCoupon::with('coupon')
            ->where('user_id', $userId);

        if ($request->filled('status')) {
            $now = now();

            match ($request->status) {
                'expiring' => $query->whereHas('coupon', function ($q) use ($now) {
                        $q->where('is_active', true)
                          ->whereBetween('end_date', [$now, $now->copy()->addDays(3)]);
                    }),

                'used' => $query->whereNotNull('used_at'),

                'active' => $query->whereHas('coupon', function ($q) use ($now) {
                        $q->where('is_active', true)
                          ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>', $now));
                    }),

                'expired' => $query->whereHas('coupon', function ($q) use ($now) {
                        $q->where('is_active', false)
                          ->orWhere('end_date', '<=', $now);
                    }),

                default => null,
            };
        }

        $sortBy = in_array($request->sort_by, ['claimed_at', 'coupon_id']) ? $request->sort_by : 'claimed_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->per_page, [4, 10, 20, 50]) ? (int) $request->per_page : 10;

        $result = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'data' => collect($result->items())->map(fn($uc) => $uc->coupon),
            'total' => $result->total(),
            'per_page' => $result->perPage(),
            'current_page' => $result->currentPage(),
            'last_page' => $result->lastPage(),
        ]);
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
            'user_id' => $userId,
            'coupon_id' => $coupon->id,
            'claimed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Nhận mã thành công',
            'data' => $coupon,
        ], 201);
    }
}