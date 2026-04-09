<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Brands;
use App\Models\Coupons;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Variant;
use App\Models\User;

use App\Observers\ProductObserver;
use App\Observers\CategoryObserver;
use App\Observers\SubcategoryObserver;
use App\Observers\BrandObserver;
use App\Observers\CouponObserver;
use App\Observers\AttributeObserver;
use App\Observers\AttributeValueObserver;
use App\Observers\VariantObserver;
use App\Observers\UserObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot() {
    User::observe(UserObserver::class);
    Product::observe(ProductObserver::class);
    Category::observe(CategoryObserver::class);
    Subcategory::observe(SubcategoryObserver::class);
    Brands::observe(BrandObserver::class);
    Coupons::observe(CouponObserver::class);
    Attribute::observe(AttributeObserver::class);
    AttributeValue::observe(AttributeValueObserver::class);
    Variant::observe(VariantObserver::class);
}
}
