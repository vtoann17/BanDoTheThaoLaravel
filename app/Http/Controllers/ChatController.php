<?php

namespace App\Http\Controllers;

use App\Events\NewChatMessage;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ChatController extends Controller
{

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
        ], [
            'name.required' => 'Vui lòng nhập tên của bạn.',
        ]);

        $contact = Contact::create([
            'name' => $request->name,
            'email' => $request->email,
            'message' => 'Khách hàng bắt đầu chat trực tuyến.',
            'topic' => 'other',
            'type' => 'chat',
            'status' => 'pending',
        ]);

        $welcome = ChatMessage::create([
            'contact_id' => $contact->id,
            'sender' => 'admin',
            'sender_name' => 'BanDoThao Support',
            'message' => "Xin chào {$request->name}! Tôi có thể giúp gì cho bạn hôm nay?",
            'is_read' => true,
        ]);

        // ✅ Broadcast để admin nhận được contact mới realtime
        broadcast(new NewChatMessage($welcome));

        return response()->json([
            'success' => true,
            'message' => 'Phiên chat đã được tạo.',
            'contact_id' => $contact->id,
            'welcome' => [
                'id' => $welcome->id,
                'sender' => $welcome->sender,
                'sender_name' => $welcome->sender_name,
                'message' => $welcome->message,
                'created_at' => $welcome->created_at->format('H:i'),
            ],
        ], 201);
    }

    public function sendMessage(StoreChatMessageRequest $request, Contact $contact): JsonResponse
    {
        if ($contact->type !== 'chat') {
            return response()->json(['success' => false, 'message' => 'Liên hệ này không phải dạng chat.'], 422);
        }

        $chatMessage = ChatMessage::create([
            'contact_id' => $contact->id,
            'sender' => $request->sender,
            'sender_name' => $request->sender_name,
            'message' => $request->message,
            'is_read' => false,
        ]);

        if ($contact->status === 'pending' && $request->sender === 'admin') {
            $contact->update(['status' => 'in_progress']);
        }

        // toOthers() ở đây là đúng — customer gửi, admin nhận (không loop lại customer)
        broadcast(new NewChatMessage($chatMessage))->toOthers();

        $formatted = [
            'id' => $chatMessage->id,
            'contact_id' => $chatMessage->contact_id,
            'sender' => $chatMessage->sender,
            'sender_name' => $chatMessage->sender_name,
            'message' => $chatMessage->message,
            'is_read' => $chatMessage->is_read,
            'created_at' => $chatMessage->created_at->format('H:i'),
            'time_ago' => $chatMessage->created_at->diffForHumans(),
        ];

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ], 201);
    }

    public function getMessages(Request $request, Contact $contact): JsonResponse
    {
        $query = $contact->chatMessages()->orderBy('id');

        if ($request->filled('after_id')) {
            $query->where('id', '>', $request->after_id);
        }

        $messages = $query->get();

        if ($request->get('reader') === 'admin') {
            $contact->chatMessages()
                ->where('sender', 'customer')
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => $messages->map(fn($m) => [
                'id' => $m->id,
                'sender' => $m->sender,
                'sender_name' => $m->sender_name,
                'message' => $m->message,
                'is_read' => $m->is_read,
                'created_at' => $m->created_at->format('H:i'),
                'time_ago' => $m->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function adminReply(Request $request, Contact $contact): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'mark_resolved' => ['nullable', 'boolean'],
            'send_email' => ['nullable', 'boolean'],
            'admin_name' => ['nullable', 'string', 'max:100'],
        ]);

        $adminName = $request->admin_name ?? 'Admin BanDoThao';

        $chatMessage = ChatMessage::create([
            'contact_id' => $contact->id,
            'sender' => 'admin',
            'sender_name' => $adminName,
            'message' => $request->message,
            'is_read' => true,
        ]);

        // BỎ ->toOthers() — admin gửi qua API (không có socket session),
        // toOthers() sẽ không loại được ai và có thể suppress event.
        // Customer cần nhận event này, admin tự push local ở store.
        broadcast(new NewChatMessage($chatMessage));

        $newStatus = $request->boolean('mark_resolved') ? 'resolved' : 'in_progress';
        $updateData = ['status' => $newStatus];

        if ($newStatus === 'resolved') {
            $updateData['resolved_at'] = now();
        }

        $contact->update($updateData);

        $emailSent = false;

        if ($request->boolean('send_email', true)) {
            if (
                filter_var($contact->email, FILTER_VALIDATE_EMAIL) &&
                !str_contains($contact->email, 'guest@')
            ) {
                try {
                    Mail::send('emails.admin-reply', [
                        'contact' => $contact,
                        'replyText' => $request->message,
                        'adminName' => $adminName,
                    ], function ($mail) use ($contact) {
                        $mail->to($contact->email, $contact->name)
                            ->subject('Phản hồi từ BanDoThao: ' . $contact->topicLabel());
                    });

                    $emailSent = true;
                } catch (\Exception $e) {
                    \Log::error('Mail error: ' . $e->getMessage());
                }
            } else {
                \Log::warning('⚠️ Email không hợp lệ: ' . $contact->email);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi phản hồi thành công.',
            'data' => [
                'message_id' => $chatMessage->id,
                'new_status' => $contact->status,
                'status_label' => $contact->statusLabel(),
                'email_sent' => $emailSent,
            ],
        ]);
    }

    public function activeChats(): JsonResponse
    {
        $chats = Contact::where('type', 'chat')
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['chatMessages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $chats->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'last_message' => $c->chatMessages->first()?->message,
                'last_time' => $c->updated_at->diffForHumans(),
                'unread_count' => $c->chatMessages()->where('sender', 'customer')->where('is_read', false)->count(),
            ]),
        ]);
    }
}