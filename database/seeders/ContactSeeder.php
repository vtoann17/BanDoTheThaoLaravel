<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        // ── Form liên hệ mẫu ──────────────────────────────────
        $contacts = [
            [
                'name'    => 'Nguyễn Văn An',
                'email'   => 'an.nguyen@gmail.com',
                'phone'   => '0912 345 678',
                'topic'   => 'order',
                'message' => 'Tôi đã đặt hàng cách đây 3 ngày nhưng chưa nhận được hàng. Mã đơn hàng #ORD-2024-001.',
                'type'    => 'form',
                'status'  => 'pending',
                'is_read' => false,
            ],
            [
                'name'    => 'Trần Thị Bích',
                'email'   => 'bich.tran@email.com',
                'phone'   => '0908 765 432',
                'topic'   => 'payment',
                'message' => 'Tôi muốn yêu cầu hoàn tiền cho đơn hàng bị lỗi. Sản phẩm không đúng như mô tả trên website.',
                'type'    => 'form',
                'status'  => 'in_progress',
                'is_read' => true,
            ],
            [
                'name'    => 'Lê Minh Đức',
                'email'   => 'duc.le@company.vn',
                'phone'   => null,
                'topic'   => 'technical',
                'message' => 'Không thể đăng nhập vào tài khoản. Hệ thống báo lỗi mật khẩu không đúng dù tôi chắc chắn nhập đúng.',
                'type'    => 'form',
                'status'  => 'resolved',
                'is_read' => true,
                'resolved_at' => now()->subHours(2),
            ],
            [
                'name'    => 'Phạm Hồng Linh',
                'email'   => 'linh.pham@gmail.com',
                'phone'   => '0978 111 222',
                'topic'   => 'product',
                'message' => 'Sản phẩm bản đồ địa hình tỉnh Đắk Lắk có độ phân giải bao nhiêu? Có bản cập nhật 2024 không?',
                'type'    => 'chat',
                'status'  => 'pending',
                'is_read' => false,
            ],
        ];

        foreach ($contacts as $data) {
            $contact = Contact::create($data);

            // Thêm tin nhắn mẫu cho chat
            if ($contact->type === 'chat') {
                ChatMessage::create([
                    'contact_id'  => $contact->id,
                    'sender'      => 'customer',
                    'sender_name' => $contact->name,
                    'message'     => $contact->message,
                    'is_read'     => true,
                ]);

                ChatMessage::create([
                    'contact_id'  => $contact->id,
                    'sender'      => 'admin',
                    'sender_name' => 'BanDoThao Support',
                    'message'     => "Xin chào {$contact->name}! Tôi có thể giúp gì cho bạn?",
                    'is_read'     => false,
                ]);
            }

            // Thêm thread cho liên hệ đã xử lý
            if ($contact->status === 'resolved') {
                ChatMessage::create([
                    'contact_id'  => $contact->id,
                    'sender'      => 'admin',
                    'sender_name' => 'Admin BanDoThao',
                    'message'     => 'Chúng tôi đã reset mật khẩu tạm thời. Vui lòng kiểm tra email.',
                    'is_read'     => true,
                ]);
                ChatMessage::create([
                    'contact_id'  => $contact->id,
                    'sender'      => 'customer',
                    'sender_name' => $contact->name,
                    'message'     => 'Đã đổi mật khẩu thành công. Cảm ơn bạn!',
                    'is_read'     => true,
                ]);
            }
        }

        $this->command->info('✅ Đã tạo ' . count($contacts) . ' liên hệ mẫu.');
    }
}