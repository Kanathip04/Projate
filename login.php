<?php
session_start();

// ✅ ถ้า login อยู่แล้ว → redirect ตาม role ที่มีใน session
if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

// ล้างเฉพาะ OTP ที่ค้างอยู่
unset($_SESSION['otp_verified'], $_SESSION['otp_email']);

$message       = $_SESSION['otp_message'] ?? '';
$error_message = $_SESSION['otp_error']   ?? '';

unset($_SESSION['otp_message'], $_SESSION['otp_error']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>เข้าสู่ระบบ</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --ink:#1a1a2e; --card:#fff; --accent:#c9a96e; --muted:#7a7a8c; --border:#e0ddd6; --danger:#c0392b; --success:#2e7d32; --radius:2px; }
    body { min-height:100vh; display:flex; align-items:center; justify-content:center; background-color:#f5f1eb; background-image:radial-gradient(ellipse at 20% 50%,rgba(201,169,110,.12) 0%,transparent 60%),radial-gradient(ellipse at 80% 20%,rgba(26,26,46,.06) 0%,transparent 55%); font-family:'Sarabun',sans-serif; }
    body::before { content:''; position:fixed; inset:0; pointer-events:none; background-image:repeating-linear-gradient(90deg,rgba(201,169,110,.04) 0px,rgba(201,169,110,.04) 1px,transparent 1px,transparent 80px); }
    .wrapper { display:flex; width:860px; max-width:96vw; min-height:520px; box-shadow:0 40px 80px rgba(26,26,46,.14); animation:rise .7s cubic-bezier(.23,1,.32,1) both; }
    @keyframes rise { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
    .panel-left { flex:1; background:var(--ink); padding:52px 44px; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; }
    .panel-left::before { content:''; position:absolute; width:320px; height:320px; border-radius:50%; background:radial-gradient(circle,rgba(201,169,110,.18) 0%,transparent 70%); top:-80px; right:-100px; }
    .panel-left::after  { content:''; position:absolute; width:200px; height:200px; border-radius:50%; background:radial-gradient(circle,rgba(201,169,110,.1) 0%,transparent 70%); bottom:40px; left:-60px; }
    .brand { position:relative; z-index:1; }
    .brand-line { width:36px; height:3px; background:var(--accent); margin-bottom:18px; }
    .brand-name { font-family:'Playfair Display',serif; font-style:italic; font-size:2rem; color:#fff; }
    .brand-sub  { margin-top:10px; font-size:.78rem; color:rgba(255,255,255,.38); letter-spacing:.18em; text-transform:uppercase; }
    .panel-quote { position:relative; z-index:1; }
    .quote-mark { font-size:4rem; color:var(--accent); line-height:.6; margin-bottom:14px; font-family:Georgia,serif; opacity:.7; }
    .quote-text { font-size:.92rem; color:rgba(255,255,255,.68); line-height:1.75; }
    .quote-author { margin-top:14px; font-size:.72rem; color:var(--accent); letter-spacing:.12em; text-transform:uppercase; }
    .panel-right { flex:1.05; background:var(--card); padding:52px 48px; display:flex; flex-direction:column; justify-content:center; }
    .login-header { margin-bottom:28px; }
    .login-eyebrow { font-size:.7rem; letter-spacing:.2em; text-transform:uppercase; color:var(--accent); margin-bottom:8px; }
    .login-title { font-size:1.75rem; font-weight:600; color:var(--ink); }
    .alert-error   { background:#fdf0ef; border:1px solid var(--danger); border-radius:var(--radius); padding:10px 14px; font-size:.82rem; color:var(--danger); margin-bottom:18px; }
    .alert-success { background:#edf7ed; border:1px solid var(--success); border-radius:var(--radius); padding:10px 14px; font-size:.82rem; color:var(--success); margin-bottom:18px; }
    .form-group { margin-bottom:20px; }
    label { display:block; font-size:.72rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin-bottom:8px; }
    .input-wrap { position:relative; }
    .input-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:.9rem; pointer-events:none; }
    input[type="email"] { width:100%; padding:13px 14px 13px 40px; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Sarabun',sans-serif; font-size:.95rem; color:var(--ink); background:#fafaf8; outline:none; transition:border-color .2s,box-shadow .2s; }
    input:focus { border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(201,169,110,.13); }
    .error-msg { font-size:.74rem; color:var(--danger); margin-top:5px; display:none; }
    input.invalid { border-color:var(--danger); }
    .btn-login { width:100%; padding:14px; background:var(--ink); color:#fff; border:none; border-radius:var(--radius); font-family:'Sarabun',sans-serif; font-size:.88rem; letter-spacing:.15em; text-transform:uppercase; font-weight:600; cursor:pointer; transition:background .25s,transform .15s; }
    .btn-login:hover { background:#2a2a4a; transform:translateY(-1px); }
    .btn-login:disabled { opacity:.7; cursor:not-allowed; transform:none; }
    .register-row { text-align:center; margin-top:24px; font-size:.82rem; color:var(--muted); }
    .register-row a { color:var(--ink); font-weight:600; text-decoration:none; border-bottom:1px solid var(--ink); }
    .register-row a:hover { color:var(--accent); border-color:var(--accent); }
    .step-text { font-size:.9rem; color:var(--muted); margin-bottom:18px; line-height:1.7; }
    @media (max-width:640px) { .panel-left{display:none} .panel-right{padding:40px 28px} .wrapper{max-width:100%;min-height:100vh} }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="panel-left">
    <div class="brand">
      <div class="brand-line"></div>
      <div class="brand-name">Lumière</div>
      <div class="brand-sub">Management Platform</div>
    </div>
    <div class="panel-quote">
      <div class="quote-mark">"</div>
      <div class="quote-text">ความสำเร็จไม่ใช่จุดหมายปลายทาง<br/>แต่คือการเดินทางที่ไม่หยุดนิ่ง</div>
      <div class="quote-author">— คติประจำองค์กร</div>
    </div>
  </div>

  <div class="panel-right">
    <div class="login-header">
      <div class="login-eyebrow">ยืนยันตัวตน</div>
      <div class="login-title">เข้าสู่ระบบด้วยอีเมล</div>
    </div>

    <div class="step-text">
      กรุณากรอกอีเมลของคุณก่อน ระบบจะส่ง OTP ไปยังอีเมลนั้นเพื่อยืนยันว่าเป็นอีเมลจริง
    </div>

    <?php if (!empty($error_message)): ?>
      <div class="alert-error">⚠ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
      <div class="alert-success">✓ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="send_otp.php" onsubmit="return validateForm()">
      <div class="form-group">
        <label for="email">อีเมล</label>
        <div class="input-wrap">
          <input type="email" id="email" name="email" placeholder="example@company.com" autocomplete="email"/>
          <span class="input-icon">✉</span>
        </div>
        <div class="error-msg" id="email-err">กรุณากรอกอีเมลให้ถูกต้อง</div>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">ส่ง OTP ไปยังอีเมล</button>
    </form>

    <div class="register-row">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></div>
  </div>
</div>

<script>
  function validateForm() {
    const email = document.getElementById('email');
    const err   = document.getElementById('email-err');
    const btn   = document.getElementById('loginBtn');
    let valid   = true;

    email.classList.remove('invalid');
    err.style.display = 'none';

    if (!email.value || !/\S+@\S+\.\S+/.test(email.value)) {
      email.classList.add('invalid');
      err.style.display = 'block';
      valid = false;
    }

    if (valid) {
      btn.textContent = 'กำลังส่ง OTP...';
      btn.disabled = true;
    }

    return valid;
  }
</script>
</body>
</html>