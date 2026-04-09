<?php
// app/Observers/CouponObserver.php
namespace App\Observers;

use App\Models\Coupons;
use Illuminate\Support\Facades\Cache;

class CouponObserver {
    public function created(Coupons $coupon) { $this->clearCache($coupon); }
    public function updated(Coupons $coupon) { $this->clearCache($coupon); }
    public function deleted(Coupons $coupon) { $this->clearCache($coupon); }

    private function clearCache(Coupons $coupon) {
        Cache::tags(['coupons'])->flush();
    }
}