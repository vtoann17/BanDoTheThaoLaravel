<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Khi tạo user
     */
    public function created(User $user): void
    {
        $this->clearCache($user);
    }

    /**
     * Khi cập nhật user
     */
    public function updated(User $user): void
    {
        $this->clearCache($user);
    }

    /**
     * Khi xóa user
     */
    public function deleted(User $user): void
    {
        $this->clearCache($user);
    }

    /**
     * Xóa cache liên quan đến user
     */
    private function clearCache(User $user): void
    {
        // Xóa cache của user cụ thể
        Cache::forget('user_' . $user->id);

        // Xóa cache danh sách user (nếu có dùng)
        Cache::forget('users');
    }
}