<?php
namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver {
    public function created(Product $product) { $this->clearCache($product); }
    public function updated(Product $product) { $this->clearCache($product); }
    public function deleted(Product $product) { $this->clearCache($product); }

    private function clearCache(Product $product) {
        Cache::tags(['products'])->flush();
    }
}