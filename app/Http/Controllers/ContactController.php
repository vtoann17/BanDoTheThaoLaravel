<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    // ──────────────────────────────────────────────
    //  KHÁCH HÀNG: Gửi form liên hệ
    // ──────────────────────────────────────────────

    /**
     * POST /api/contacts
     * Khách hàng gửi form liên hệ
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = Contact::create([
            ...$request->validated(),
            'type'   => 'form',
            'status' => 'pending',
        ]);

        // Gửi email xác nhận cho khách hàng
        try {
            Mail::send('emails.contact-confirm', ['contact' => $contact], function ($mail) use ($contact) {
                $mail->to($contact->email, $contact->name)
                     ->subject('Chúng tôi đã nhận được tin nhắn của bạn - BanDoThao');
            });

            // Gửi email thông báo cho admin
            Mail::send('emails.contact-notify-admin', ['contact' => $contact], function ($mail) {
                $mail->to(config('mail.admin_email', 'admin@bandothao.vn'))
                     ->subject('[Liên hệ mới] ' . request('topic') . ' - ' . request('name'));
            });
        } catch (\Exception $e) {
            // Không để lỗi email ảnh hưởng response
            \Log::warning('Gửi email thất bại: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Gửi liên hệ thành công! Chúng tôi sẽ phản hồi trong vòng 24h.',
            'data'    => [
                'id'         => $contact->id,
                'created_at' => $contact->created_at->format('d/m/Y H:i'),
            ],
        ], 201);
    }

    // ──────────────────────────────────────────────
    //  ADMIN: Quản lý danh sách liên hệ
    // ──────────────────────────────────────────────

    /**
     * GET /api/admin/contacts
     * Lấy danh sách có filter, search, phân trang
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contact::with(['chatMessages' => fn($q) => $q->latest()->limit(1)])
            ->latest();

        // Filter theo trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter theo chủ đề
        if ($request->filled('topic')) {
            $query->where('topic', $request->topic);
        }

        // Filter theo loại (form / chat)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Tìm kiếm
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contacts = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $contacts->map(fn($c) => $this->formatContact($c)),
            'meta'    => [
                'total'        => $contacts->total(),
                'per_page'     => $contacts->perPage(),
                'current_page' => $contacts->currentPage(),
                'last_page'    => $contacts->lastPage(),
            ],
            'stats'   => $this->getStats(),
        ]);
    }

    /**
     * GET /api/admin/contacts/{id}
     * Chi tiết 1 liên hệ + toàn bộ tin nhắn
     */
    public function show(Contact $contact): JsonResponse
    {
        // Đánh dấu đã đọc
        if (!$contact->is_read) {
            $contact->markAsRead();
        }

        $contact->load('chatMessages');

        return response()->json([
            'success' => true,
            'data'    => $this->formatContactDetail($contact),
        ]);
    }

    /**
     * PATCH /api/admin/contacts/{id}/status
     * Cập nhật trạng thái
     */
    public function updateStatus(Request $request, Contact $contact): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,in_progress,resolved'],
        ]);

        $data = ['status' => $request->status];

        if ($request->status === 'resolved') {
            $data['resolved_at'] = now();
        }

        $contact->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái thành công.',
            'data'    => ['status' => $contact->status, 'status_label' => $contact->statusLabel()],
        ]);
    }

    /**
     * PATCH /api/admin/contacts/{id}/assign
     * Giao việc cho nhân viên
     */
    public function assign(Request $request, Contact $contact): JsonResponse
    {
        $request->validate([
            'assigned_to' => ['required', 'string', 'max:100'],
        ]);

        $contact->update([
            'assigned_to' => $request->assigned_to,
            'status'      => 'in_progress',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Đã giao cho {$request->assigned_to}.",
        ]);
    }

    /**
     * DELETE /api/admin/contacts/{id}
     * Xóa liên hệ (và toàn bộ tin nhắn liên quan)
     */
    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete(); // chatMessages sẽ bị xóa cascade

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa liên hệ.',
        ]);
    }

    /**
     * GET /api/admin/contacts/stats
     * Thống kê tổng quan
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->getStats(),
        ]);
    }

    // ──────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────

    private function getStats(): array
    {
        return [
            'total'       => Contact::count(),
            'pending'     => Contact::where('status', 'pending')->count(),
            'in_progress' => Contact::where('status', 'in_progress')->count(),
            'resolved'    => Contact::where('status', 'resolved')->count(),
            'unread'      => Contact::where('is_read', false)->count(),
            'chat_active' => Contact::where('type', 'chat')
                                    ->where('status', 'in_progress')
                                    ->count(),
            'today'       => Contact::whereDate('created_at', today())->count(),
        ];
    }

    private function formatContact(Contact $c): array
    {
        return [
            'id'          => $c->id,
            'name'        => $c->name,
            'email'       => $c->email,
            'phone'       => $c->phone,
            'topic'       => $c->topic,
            'topic_label' => $c->topicLabel(),
            'status'      => $c->status,
            'status_label'=> $c->statusLabel(),
            'type'        => $c->type,
            'is_read'     => $c->is_read,
            'message'     => $c->message,
            'assigned_to' => $c->assigned_to,
            'created_at'  => $c->created_at->format('d/m/Y H:i'),
            'time_ago'    => $c->created_at->diffForHumans(),
            'initials'    => $this->initials($c->name),
        ];
    }

    private function formatContactDetail(Contact $c): array
    {
        return [
            ...$this->formatContact($c),
            'resolved_at'  => $c->resolved_at?->format('d/m/Y H:i'),
            'chat_messages'=> $c->chatMessages->map(fn($m) => [
                'id'          => $m->id,
                'sender'      => $m->sender,
                'sender_name' => $m->sender_name,
                'message'     => $m->message,
                'is_read'     => $m->is_read,
                'created_at'  => $m->created_at->format('H:i d/m/Y'),
                'time_ago'    => $m->created_at->diffForHumans(),
            ]),
        ];
    }

    private function initials(string $name): string
    {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
        }
        return mb_strtoupper(mb_substr($name, 0, 2));
    }
}