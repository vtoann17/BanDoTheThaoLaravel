<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupons extends Model
{
    use HasFactory;
    protected $table = 'coupons';
    public $timestamps = false;
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_order_value'
    ];
}
