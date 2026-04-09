<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    public function created(User $user): void { $this->clearCache($user); }
    public function updated(User $user): void { $this->clearCache($user); }
    public function deleted(User $user): void { $this->clearCache($user); }

    private function clearCache(User $user): void
    {
        Cache::tags(['users'])->flush();
    }
}