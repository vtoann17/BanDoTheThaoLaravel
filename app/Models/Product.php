<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Brands;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'subcategory_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'price',
        'image',
        'status'
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brands::class);
        
    }
    public function subcategory()
{
    return $this->belongsTo(Subcategory::class);
}
}
