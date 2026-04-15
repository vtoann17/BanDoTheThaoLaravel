<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(ChatMessage $message)
    {
        // Format lại dữ liệu giống như API trả về để Frontend dễ xử lý
        $this->message = [
            'id'          => $message->id,
            'contact_id'  => $message->contact_id,
            'sender'      => $message->sender,
            'sender_name' => $message->sender_name,
            'message'     => $message->message,
            'created_at'  => $message->created_at->format('H:i'),
            'time_ago'    => $message->created_at->diffForHumans(),
        ];
    }

    public function broadcastOn(): array
    {
        // Kênh công khai dựa trên contact_id
        return [
            new Channel('chat.' . $this->message['contact_id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}