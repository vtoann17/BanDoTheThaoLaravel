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
    // ──────────────────────────────────────────────
    //  KHÁCH HÀNG: Bắt đầu phiên chat
    // ──────────────────────────────────────────────

    /**
     * POST /api/chat/start
     * Tạo contact mới khi khách bắt đầu chat
     */
    public function start(Request $request): JsonResponse
    {
       $request->validate([
    'name'  => ['required', 'string', 'max:100'],
    'email' => ['required', 'email', 'max:150'], // 👈 bắt buộc luôn
], [
            'name.required' => 'Vui lòng nhập tên của bạn.',
        ]);

        $contact = Contact::create([
            'name'    => $request->name,
            'email' => $request->email,
            'message' => 'Khách hàng bắt đầu chat trực tuyến.',
            'topic'   => 'other',
            'type'    => 'chat',
            'status'  => 'pending',
        ]);

        // Tin nhắn chào mừng tự động
        $welcome = ChatMessage::create([
            'contact_id'  => $contact->id,
            'sender'      => 'admin',
            'sender_name' => 'BanDoThao Support',
            'message'     => "Xin chào {$request->name}! Tôi có thể giúp gì cho bạn hôm nay?",
            'is_read'     => true,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Phiên chat đã được tạo.',
            'contact_id' => $contact->id,
            'welcome'    => [
                'id'          => $welcome->id,
                'sender'      => $welcome->sender,
                'sender_name' => $welcome->sender_name,
                'message'     => $welcome->message,
                'created_at'  => $welcome->created_at->format('H:i'),
            ],
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  GỬI TIN NHẮN (cả khách & admin)
    // ──────────────────────────────────────────────

    /**
     * POST /api/chat/{contact}/messages
     * Gửi tin nhắn mới trong phiên chat
     */
    public function sendMessage(StoreChatMessageRequest $request, Contact $contact): JsonResponse
    {
        // Chỉ chat-type contact mới nhắn được
        if ($contact->type !== 'chat') {
            return response()->json(['success' => false, 'message' => 'Liên hệ này không phải dạng chat.'], 422);
        }

        $chatMessage = ChatMessage::create([
            'contact_id'  => $contact->id,
            'sender'      => $request->sender,
            'sender_name' => $request->sender_name,
            'message'     => $request->message,
            'is_read'     => false,
        ]);

        // Cập nhật trạng thái contact
        if ($contact->status === 'pending' && $request->sender === 'admin') {
            $contact->update(['status' => 'in_progress']);
        }

        // Broadcast real-time (nếu dùng Laravel Reverb / Pusher)
        broadcast(new NewChatMessage($chatMessage))->toOthers();

        $formatted = [
            'id'          => $chatMessage->id,
            'contact_id'  => $chatMessage->contact_id,
            'sender'      => $chatMessage->sender,
            'sender_name' => $chatMessage->sender_name,
            'message'     => $chatMessage->message,
            'is_read'     => $chatMessage->is_read,
            'created_at'  => $chatMessage->created_at->format('H:i'),
            'time_ago'    => $chatMessage->created_at->diffForHumans(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $formatted,
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  LẤY TIN NHẮN
    // ──────────────────────────────────────────────

    /**
     * GET /api/chat/{contact}/messages
     * Lấy toàn bộ lịch sử tin nhắn của một phiên chat
     * ?after_id=X → chỉ lấy tin nhắn mới hơn ID X (polling)
     */
    public function getMessages(Request $request, Contact $contact): JsonResponse
    {
        $query = $contact->chatMessages()->orderBy('id');

        if ($request->filled('after_id')) {
            $query->where('id', '>', $request->after_id);
        }

        $messages = $query->get();

        // Đánh dấu đã đọc (với admin đọc tin của khách)
        if ($request->get('reader') === 'admin') {
            $contact->chatMessages()
                    ->where('sender', 'customer')
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'data'    => $messages->map(fn($m) => [
                'id'          => $m->id,
                'sender'      => $m->sender,
                'sender_name' => $m->sender_name,
                'message'     => $m->message,
                'is_read'     => $m->is_read,
                'created_at'  => $m->created_at->format('H:i'),
                'time_ago'    => $m->created_at->diffForHumans(),
            ]),
        ]);
    }

    // ──────────────────────────────────────────────
    //  ADMIN: Trả lời từ trang quản lý
    // ──────────────────────────────────────────────

    /**
     * POST /api/admin/contacts/{contact}/reply
     * Admin gửi phản hồi (cả chat lẫn form)
     */
    public function adminReply(Request $request, Contact $contact): JsonResponse
{
    $request->validate([
        'message'       => ['required', 'string', 'max:2000'],
        'mark_resolved' => ['nullable', 'boolean'],
        'send_email'    => ['nullable', 'boolean'],
        'admin_name'    => ['nullable', 'string', 'max:100'],
    ]);

    $adminName = $request->admin_name ?? 'Admin BanDoThao';

    // ─── Lưu tin nhắn ─────────────────────────────
    $chatMessage = ChatMessage::create([
        'contact_id'  => $contact->id,
        'sender'      => 'admin',
        'sender_name' => $adminName,
        'message'     => $request->message,
        'is_read'     => true,
    ]);

    // ─── Cập nhật trạng thái ─────────────────────
    $newStatus  = $request->boolean('mark_resolved') ? 'resolved' : 'in_progress';
    $updateData = ['status' => $newStatus];

    if ($newStatus === 'resolved') {
        $updateData['resolved_at'] = now();
    }

    $contact->update($updateData);

    // ─── Gửi email phản hồi ─────────────────────
    $emailSent = false;

    if ($request->boolean('send_email', true)) {
    
        // 🔍 Check email hợp lệ + không phải email fake
        if (
            filter_var($contact->email, FILTER_VALIDATE_EMAIL) &&
            !str_contains($contact->email, 'guest@')
        ) {
            try {
                Mail::send('emails.admin-reply', [
                    'contact'   => $contact,
                    'replyText' => $request->message,
                    'adminName' => $adminName,
                ], function ($mail) use ($contact) {
                    $mail->to($contact->email, $contact->name)
                         ->subject('Phản hồi từ BanDoThao: ' . $contact->topicLabel());
                });

                $emailSent = true;

            } catch (\Exception $e) {
    dd($e->getMessage());
}
        } else {
            \Log::warning('⚠️ Email không hợp lệ: ' . $contact->email);
        }
    }

    // ─── Response ───────────────────────────────
    return response()->json([
        'success' => true,
        'message' => 'Đã gửi phản hồi thành công.',
        'data'    => [
            'message_id'   => $chatMessage->id,
            'new_status'   => $contact->status,
            'status_label' => $contact->statusLabel(),
            'email_sent'   => $emailSent, // 👈 chuẩn hơn
        ],
    ]);
}

    /**
     * GET /api/admin/chat/active
     * Danh sách chat đang hoạt động (cho admin dashboard)
     */
    public function activeChats(): JsonResponse
    {
        $chats = Contact::where('type', 'chat')
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['chatMessages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $chats->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'status'       => $c->status,
                'last_message' => $c->chatMessages->first()?->message,
                'last_time'    => $c->updated_at->diffForHumans(),
                'unread_count' => $c->chatMessages()->where('sender', 'customer')->where('is_read', false)->count(),
            ]),
        ]);
    }
}