<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\Variant;

class OrderDetail extends Model
{
    use HasFactory;
    protected $fillable = [
    'order_id',
    'variant_id',
    'quantity',
    'price'
];

public function order()
{
    return $this->belongsTo(Order::class);
}
public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}
