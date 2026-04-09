<?php
// app/Observers/AttributeValueObserver.php
namespace App\Observers;

use App\Models\AttributeValue;
use Illuminate\Support\Facades\Cache;

class AttributeValueObserver {
    public function created(AttributeValue $av) { $this->clearCache($av); }
    public function updated(AttributeValue $av) { $this->clearCache($av); }
    public function deleted(AttributeValue $av) { $this->clearCache($av); }

    private function clearCache(AttributeValue $av) {
        // Xóa cả attributes vì attribute_value thuộc attribute
        Cache::tags(['attribute_values', 'attributes'])->flush();
    }
}