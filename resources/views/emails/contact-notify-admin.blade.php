<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8"/>
  <title>Liên hệ mới</title>
  <style>
    body { margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI',sans-serif; }
    .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; }
    .header { background:#0f172a; padding:24px 40px; display:flex; align-items:center; gap:12px; }
    .badge { background:#ef4444; color:#fff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; }
    .header-title { color:#fff; font-size:16px; font-weight:700; margin-left:8px; }
    .body { padding:32px 40px; }
    h2 { font-size:18px; font-weight:700; color:#0f172a; margin:0 0 20px; }
    .info-table { width:100%; border-collapse:collapse; font-size:13px; }
    .info-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
    .info-table td:first-child { color:#64748b; font-weight:600; width:120px; background:#f8fafc; }
    .info-table td:last-child { color:#1e293b; }
    .msg-box { background:#f8fafc; border-left:4px solid #2563eb; border-radius:0 8px 8px 0; padding:16px; margin:20px 0; font-size:13px; color:#374151; line-height:1.7; }
    .cta { display:inline-block; background:#1e3a8a; color:#fff; text-decoration:none; padding:12px 28px; border-radius:8px; font-size:14px; font-weight:600; margin-top:16px; }
    .footer { background:#f8fafc; padding:20px 40px; text-align:center; border-top:1px solid #e2e8f0; }
    .footer p { font-size:12px; color:#94a3b8; margin:0; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <span class="badge">MỚI</span>
    <span class="header-title">Liên hệ mới từ khách hàng</span>
  </div>
  <div class="body">
    <h2>Thông tin liên hệ</h2>
    <table class="info-table">
      <tr><td>Mã liên hệ</td><td><strong>#CT-{{ str_pad($contact->id, 5, '0', STR_PAD_LEFT) }}</strong></td></tr>
      <tr><td>Họ tên</td><td>{{ $contact->name }}</td></tr>
      <tr><td>Email</td><td><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></td></tr>
      <tr><td>Điện thoại</td><td>{{ $contact->phone ?? '—' }}</td></tr>
      <tr><td>Chủ đề</td><td>{{ $contact->topicLabel() }}</td></tr>
      <tr><td>Thời gian</td><td>{{ $contact->created_at->format('H:i - d/m/Y') }}</td></tr>
    </table>

    <div class="msg-box">{{ $contact->message }}</div>

    <a href="{{ config('app.url') }}/admin/contacts/{{ $contact->id }}" class="cta">
      Xem & Phản hồi ngay →
    </a>
  </div>
  <div class="footer">
    <p>Email này được gửi tự động từ hệ thống BanDoThao Admin.</p>
  </div>
</div>
</body>
</html>