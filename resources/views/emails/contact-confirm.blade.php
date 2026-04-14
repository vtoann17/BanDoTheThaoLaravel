<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Xác nhận liên hệ</title>
  <style>
    body { margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI',sans-serif; }
    .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; }
    .header { background:linear-gradient(135deg,#1a2b4a,#2563eb); padding:36px 40px; text-align:center; }
    .header img { width:48px; height:48px; margin-bottom:12px; }
    .logo-text { color:#fff; font-size:22px; font-weight:700; letter-spacing:-0.5px; }
    .body { padding:36px 40px; }
    .greeting { font-size:18px; font-weight:700; color:#0f172a; margin-bottom:8px; }
    p { color:#475569; font-size:14px; line-height:1.7; margin:0 0 16px; }
    .ticket-box { background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #2563eb; border-radius:8px; padding:20px; margin:24px 0; }
    .ticket-row { display:flex; justify-content:space-between; margin-bottom:10px; font-size:13px; }
    .ticket-key { color:#64748b; font-weight:500; }
    .ticket-val { color:#0f172a; font-weight:600; }
    .msg-preview { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; font-size:13px; color:#374151; line-height:1.7; margin-top:8px; }
    .footer { background:#f8fafc; padding:24px 40px; text-align:center; border-top:1px solid #e2e8f0; }
    .footer p { font-size:12px; color:#94a3b8; margin:0; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="logo-text">BanDoThao</div>
  </div>
  <div class="body">
    <p class="greeting">Xin chào {{ $contact->name }},</p>
    <p>Chúng tôi đã nhận được tin nhắn của bạn và sẽ phản hồi trong vòng <strong>24 giờ làm việc</strong>.</p>

    <div class="ticket-box">
      <div class="ticket-row">
        <span class="ticket-key">Mã liên hệ</span>
        <span class="ticket-val">#CT-{{ str_pad($contact->id, 5, '0', STR_PAD_LEFT) }}</span>
      </div>
      <div class="ticket-row">
        <span class="ticket-key">Chủ đề</span>
        <span class="ticket-val">{{ $contact->topicLabel() }}</span>
      </div>
      <div class="ticket-row">
        <span class="ticket-key">Ngày gửi</span>
        <span class="ticket-val">{{ $contact->created_at->format('d/m/Y H:i') }}</span>
      </div>
      <div style="margin-top:12px;">
        <span class="ticket-key" style="display:block;margin-bottom:6px;">Nội dung của bạn:</span>
        <div class="msg-preview">{{ $contact->message }}</div>
      </div>
    </div>

    <p>Nếu bạn cần hỗ trợ khẩn cấp, hãy gọi <strong>1800 123 456</strong> (miễn phí, 8:00–22:00).</p>
    <p>Trân trọng,<br><strong>Đội ngũ hỗ trợ BanDoThao</strong></p>
  </div>
  <div class="footer">
    <p>© {{ date('Y') }} BanDoThao. Đây là email tự động, vui lòng không trả lời.</p>
    <p style="margin-top:6px;">123 Đường ABC, Quận 1, TP. Hồ Chí Minh</p>
  </div>
</div>
</body>
</html>