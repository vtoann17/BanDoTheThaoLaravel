<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $chatMessage)
    {
    }

    /**
     * Kênh broadcast:
     * - Khách hàng lắng nghe: chat.{contact_id}
     * - Admin lắng nghe tất cả: admin.chat
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("chat.{$this->chatMessage->contact_id}"),
            new Channel('admin.chat'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.message';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->chatMessage->id,
            'contact_id'  => $this->chatMessage->contact_id,
            'sender'      => $this->chatMessage->sender,
            'sender_name' => $this->chatMessage->sender_name,
            'message'     => $this->chatMessage->message,
            'created_at'  => $this->chatMessage->created_at->format('H:i'),
        ];
    }
}