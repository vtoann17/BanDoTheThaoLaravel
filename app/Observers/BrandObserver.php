<?php
// app/Observers/BrandObserver.php
namespace App\Observers;

use App\Models\Brands;
use Illuminate\Support\Facades\Cache;

class BrandObserver {
    public function created(Brands $brand) { $this->clearCache($brand); }
    public function updated(Brands $brand) { $this->clearCache($brand); }
    public function deleted(Brands $brand) { $this->clearCache($brand); }

    private function clearCache(Brands $brand) {
        Cache::tags(['brands'])->flush();
    }
}