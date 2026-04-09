<?php
// app/Observers/VariantObserver.php
namespace App\Observers;

use App\Models\Variant;
use Illuminate\Support\Facades\Cache;

class VariantObserver {
    public function created(Variant $variant) { $this->clearCache($variant); }
    public function updated(Variant $variant) { $this->clearCache($variant); }
    public function deleted(Variant $variant) { $this->clearCache($variant); }

    private function clearCache(Variant $variant) {
        // Variant đổi → product detail cũng phải xóa
        Cache::tags(['variants', 'products'])->flush();
    }
}