<?php
// app/Observers/SubcategoryObserver.php
namespace App\Observers;

use App\Models\Subcategory;
use Illuminate\Support\Facades\Cache;

class SubcategoryObserver {
    public function created(Subcategory $subcategory) { $this->clearCache($subcategory); }
    public function updated(Subcategory $subcategory) { $this->clearCache($subcategory); }
    public function deleted(Subcategory $subcategory) { $this->clearCache($subcategory); }

    private function clearCache(Subcategory $subcategory) {
        Cache::tags(['subcategories'])->flush();
    }
}