<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;
    protected $fillable = [
    'user_id',
    'province_id',
    'district_id',
    'ward_code',
    'province_name',
    'district_name',
    'ward_name',
    'address_detail',
    'receiver_name',
    'phone',
    'is_default'
];
}
