<?php
// app/Observers/AttributeObserver.php
namespace App\Observers;

use App\Models\Attribute;
use Illuminate\Support\Facades\Cache;

class AttributeObserver {
    public function created(Attribute $attribute) { $this->clearCache($attribute); }
    public function updated(Attribute $attribute) { $this->clearCache($attribute); }
    public function deleted(Attribute $attribute) { $this->clearCache($attribute); }

    private function clearCache(Attribute $attribute) {
        Cache::tags(['attributes'])->flush();
    }
}