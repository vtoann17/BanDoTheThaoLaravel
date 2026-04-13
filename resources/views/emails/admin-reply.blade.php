<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8"/>
  <title>Phản hồi từ BanDoThao</title>
  <style>
    body { margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI',sans-serif; }
    .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; }
    .header { background:linear-gradient(135deg,#1a2b4a,#2563eb); padding:30px 40px; text-align:center; }
    .logo { color:#fff; font-size:20px; font-weight:700; }
    .body { padding:36px 40px; }
    .greeting { font-size:17px; font-weight:700; color:#0f172a; margin-bottom:8px; }
    p { color:#475569; font-size:14px; line-height:1.7; margin:0 0 14px; }
    .original-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; font-size:13px; color:#64748b; margin:20px 0; }
    .original-label { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; }
    .reply-box { background:#eff6ff; border-left:4px solid #2563eb; border-radius:0 8px 8px 0; padding:18px; margin:20px 0; }
    .reply-label { font-size:11px; font-weight:700; color:#1d4ed8; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
    .reply-text { font-size:14px; color:#1e3a8a; line-height:1.7; }
    .admin-sig { display:flex; align-items:center; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid #e2e8f0; }
    .admin-av { width:40px; height:40px; background:#1e3a8a; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; font-weight:700; flex-shrink:0; }
    .admin-name { font-weight:700; font-size:14px; color:#0f172a; }
    .admin-role { font-size:12px; color:#64748b; }
    .footer { background:#f8fafc; padding:20px 40px; text-align:center; border-top:1px solid #e2e8f0; }
    .footer p { font-size:12px; color:#94a3b8; margin:0; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="header"><div class="logo">BanDoThao</div></div>
  <div class="body">
    <p class="greeting">Xin chào {{ $contact->name }},</p>
    <p>Đội ngũ hỗ trợ của chúng tôi đã xem xét yêu cầu của bạn và gửi phản hồi bên dưới.</p>

    <div class="original-box">
      <div class="original-label">Yêu cầu của bạn (#CT-{{ str_pad($contact->id, 5, '0', STR_PAD_LEFT) }})</div>
      {{ $contact->message }}
    </div>

    <div class="reply-box">
      <div class="reply-label">Phản hồi từ chúng tôi</div>
      <div class="reply-text">{{ $replyText }}</div>
    </div>

    <div class="admin-sig">
      <div class="admin-av">{{ mb_strtoupper(mb_substr($adminName, 0, 2)) }}</div>
      <div>
        <div class="admin-name">{{ $adminName }}</div>
        <div class="admin-role">Đội ngũ hỗ trợ BanDoThao</div>
      </div>
    </div>

    <p style="margin-top:20px;">Nếu bạn vẫn cần hỗ trợ thêm, hãy trả lời email này hoặc gọi <strong>1800 123 456</strong>.</p>
  </div>
  <div class="footer">
    <p>© {{ date('Y') }} BanDoThao · 123 Đường ABC, Quận 1, TP. HCM</p>
  </div>
</div>
</body>
</html>