<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\OrderDetail;
use App\Models\Address;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'total_amount',
        'address_id', 
        'coupon_code', 
        'shipping_fee',
        'discount',
        'payment_method',
        'payment_status', 
        'order_status',     
        'vnpay_txn_ref',
        'vnpay_transaction_no',
        'momo_trans_id', 
        'cancel_reason',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderDetail::class);
    }
    public function address()
{
    return $this->belongsTo(Address::class);
}
}
