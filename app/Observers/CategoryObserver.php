<?php
// app/Observers/CategoryObserver.php
namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver {
    public function created(Category $category) { $this->clearCache($category); }
    public function updated(Category $category) { $this->clearCache($category); }
    public function deleted(Category $category) { $this->clearCache($category); }

    private function clearCache(Category $category) {
        // Xóa cả subcategories vì subcategory thuộc category
        Cache::tags(['categories', 'subcategories'])->flush();
    }
}