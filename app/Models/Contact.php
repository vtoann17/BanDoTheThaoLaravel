<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'topic',
        'message',
        'type',
        'status',
        'is_read',
        'assigned_to',
        'resolved_at',
    ];

    protected $casts = [
        'is_read'     => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // ─── Helpers ──────────────────────────────────────────────────
    public function topicLabel(): string
    {
        return match ($this->topic) {
            'order'     => 'Đơn hàng',
            'product'   => 'Sản phẩm',
            'payment'   => 'Thanh toán',
            'technical' => 'Kỹ thuật',
            default     => 'Khác',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'     => 'Chờ xử lý',
            'in_progress' => 'Đang xử lý',
            'resolved'    => 'Đã giải quyết',
            default       => $this->status,
        };
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    public function resolve(): void
    {
        $this->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}