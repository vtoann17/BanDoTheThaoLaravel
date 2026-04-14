<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Coupons;
use App\Models\User;

class UserCoupon extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'coupon_id', 'claimed_at'];

    public function coupon()
    {
        return $this->belongsTo(Coupons::class, 'coupon_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}