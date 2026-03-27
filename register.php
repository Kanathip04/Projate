<?php
session_start();
require_once 'config.php';

// ถ้า login อยู่แล้ว → ไปหน้าหลัก
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (empty($fullname) || empty($email) || empty($password) || empty($confirm)) {
        $error_message = 'กรุณากรอกข้อมูลให้ครบ';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (strlen($password) < 6) {
        $error_message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($password !== $confirm) {
        $error_message = 'รหัสผ่านไม่ตรงกัน';
    } else {
        // เช็คอีเมลซ้ำ
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $error_message = 'อีเมลนี้ถูกใช้งานแล้ว';
        } else {
            // บันทึก user (is_verified = 0 รอยืนยัน)
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("INSERT INTO users (fullname, email, phone, password, is_verified, role, created_at) VALUES (?, ?, ?, ?, 0, 'user', NOW())");
            $stmt->bind_param("ssss", $fullname, $email, $phone, $hashed);

            if ($stmt->execute()) {
                $stmt->close();
                // เก็บ session สำหรับส่ง OTP
                $_SESSION['otp_email']       = $email;
                $_SESSION['otp_register']    = true; // บอกว่าเป็น OTP จากการสมัคร
                header("Location: send_otp_register.php");
                exit;
            } else {
                $error_message = 'เกิดข้อผิดพลาด: ' . $conn->error;
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>สมัครสมาชิก</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --ink:#1a1a2e; --card:#fff; --accent:#c9a96e; --muted:#7a7a8c; --border:#e0ddd6; --danger:#c0392b; --radius:2px; }
    body { min-height:100vh; display:flex; align-items:center; justify-content:center; background-color:#f5f1eb; background-image:radial-gradient(ellipse at 20% 50%,rgba(201,169,110,.12) 0%,transparent 60%),radial-gradient(ellipse at 80% 20%,rgba(26,26,46,.06) 0%,transparent 55%); font-family:'Sarabun',sans-serif; padding:20px 0; }
    body::before { content:''; position:fixed; inset:0; pointer-events:none; background-image:repeating-linear-gradient(90deg,rgba(201,169,110,.04) 0px,rgba(201,169,110,.04) 1px,transparent 1px,transparent 80px); }
    .wrapper { display:flex; width:860px; max-width:96vw; min-height:560px; box-shadow:0 40px 80px rgba(26,26,46,.14); animation:rise .7s cubic-bezier(.23,1,.32,1) both; }
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
    .panel-right { flex:1.05; background:var(--card); padding:48px 48px; display:flex; flex-direction:column; justify-content:center; }
    .login-header { margin-bottom:24px; }
    .login-eyebrow { font-size:.7rem; letter-spacing:.2em; text-transform:uppercase; color:var(--accent); margin-bottom:8px; }
    .login-title { font-size:1.75rem; font-weight:600; color:var(--ink); }
    .alert-error { background:#fdf0ef; border:1px solid var(--danger); border-radius:var(--radius); padding:10px 14px; font-size:.82rem; color:var(--danger); margin-bottom:18px; }
    .form-group { margin-bottom:16px; }
    label { display:block; font-size:.72rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin-bottom:7px; }
    .optional { font-size:.65rem; color:var(--muted); text-transform:none; letter-spacing:0; margin-left:4px; }
    .input-wrap { position:relative; }
    .input-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:.9rem; pointer-events:none; }
    .toggle-pwd { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:.85rem; cursor:pointer; border:none; background:none; padding:2px; }
    input[type="text"], input[type="email"], input[type="tel"], input[type="password"] { width:100%; padding:13px 40px 13px 40px; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Sarabun',sans-serif; font-size:.95rem; color:var(--ink); background:#fafaf8; outline:none; transition:border-color .2s,box-shadow .2s; }
    input:focus { border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(201,169,110,.13); }
    .error-msg { font-size:.74rem; color:var(--danger); margin-top:5px; display:none; }
    input.invalid { border-color:var(--danger); }
    .strength-bar { height:3px; border-radius:2px; margin-top:7px; background:var(--border); overflow:hidden; }
    .strength-fill { height:100%; width:0%; transition:width .3s,background .3s; border-radius:2px; }
    .btn-register { width:100%; padding:14px; background:var(--ink); color:#fff; border:none; border-radius:var(--radius); font-family:'Sarabun',sans-serif; font-size:.88rem; letter-spacing:.15em; text-transform:uppercase; font-weight:600; cursor:pointer; margin-top:8px; transition:background .25s,transform .15s; }
    .btn-register:hover { background:#2a2a4a; transform:translateY(-1px); }
    .btn-register:disabled { opacity:.7; cursor:not-allowed; transform:none; }
    .login-row { text-align:center; margin-top:20px; font-size:.82rem; color:var(--muted); }
    .login-row a { color:var(--ink); font-weight:600; text-decoration:none; border-bottom:1px solid var(--ink); }
    .login-row a:hover { color:var(--accent); border-color:var(--accent); }
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
      <div class="quote-text">เริ่มต้นทุกสิ่งด้วยความตั้งใจ<br/>แล้วความสำเร็จจะตามมาเอง</div>
      <div class="quote-author">— คติประจำองค์กร</div>
    </div>
  </div>

  <div class="panel-right">
    <div class="login-header">
      <div class="login-eyebrow">เริ่มต้นใช้งาน</div>
      <div class="login-title">สมัครสมาชิก</div>
    </div>

    <?php if (!empty($error_message)): ?>
      <div class="alert-error">⚠ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateRegister()">

      <div class="form-group">
        <label>ชื่อ-นามสกุล</label>
        <div class="input-wrap">
          <span class="input-icon">👤</span>
          <input type="text" name="fullname" placeholder="ชื่อ นามสกุล"
                 value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"/>
        </div>
        <div class="error-msg" id="fullname-err">กรุณากรอกชื่อ-นามสกุล</div>
      </div>

      <div class="form-group">
        <label>อีเมล</label>
        <div class="input-wrap">
          <span class="input-icon">✉</span>
          <input type="email" name="email" placeholder="example@company.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
        </div>
        <div class="error-msg" id="email-err">กรุณากรอกอีเมลให้ถูกต้อง</div>
      </div>

      <div class="form-group">
        <label>เบอร์โทรศัพท์ <span class="optional">(ไม่บังคับ)</span></label>
        <div class="input-wrap">
          <span class="input-icon">📱</span>
          <input type="tel" name="phone" placeholder="08x-xxx-xxxx"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"/>
        </div>
      </div>

      <div class="form-group">
        <label>รหัสผ่าน</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" id="password" name="password"
                 placeholder="อย่างน้อย 6 ตัวอักษร"
                 oninput="checkStrength(this.value)"/>
          <button type="button" class="toggle-pwd" onclick="togglePwd('password',this)">👁</button>
        </div>
        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
        <div class="error-msg" id="pass-err">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</div>
      </div>

      <div class="form-group">
        <label>ยืนยันรหัสผ่าน</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" id="confirm" name="confirm" placeholder="••••••••"/>
          <button type="button" class="toggle-pwd" onclick="togglePwd('confirm',this)">👁</button>
        </div>
        <div class="error-msg" id="confirm-err">รหัสผ่านไม่ตรงกัน</div>
      </div>

      <button type="submit" class="btn-register" id="regBtn">สมัครสมาชิก</button>
    </form>

    <div class="login-row">มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></div>
  </div>
</div>
<script>
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
function checkStrength(val) {
  const fill = document.getElementById('strength-fill');
  let s = 0;
  if (val.length >= 6)  s++;
  if (val.length >= 10) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const colors = ['#e74c3c','#e67e22','#f1c40f','#2ecc71','#27ae60'];
  fill.style.width = (s * 20) + '%';
  fill.style.background = colors[s-1] || '#e0ddd6';
}
function validateRegister() {
  ['fullname','email','password','confirm'].forEach(f => {
    document.getElementById(f)?.classList.remove('invalid');
    const e = document.getElementById(f+'-err');
    if (e) e.style.display='none';
  });
  let valid = true;
  const fullname = document.querySelector('[name="fullname"]').value.trim();
  const email    = document.querySelector('[name="email"]').value.trim();
  const pass     = document.getElementById('password').value;
  const confirm  = document.getElementById('confirm').value;

  if (!fullname) {
    document.querySelector('[name="fullname"]').classList.add('invalid');
    document.getElementById('fullname-err').style.display='block'; valid=false;
  }
  if (!email || !/\S+@\S+\.\S+/.test(email)) {
    document.querySelector('[name="email"]').classList.add('invalid');
    document.getElementById('email-err').style.display='block'; valid=false;
  }
  if (!pass || pass.length < 6) {
    document.getElementById('password').classList.add('invalid');
    document.getElementById('pass-err').style.display='block'; valid=false;
  }
  if (pass !== confirm) {
    document.getElementById('confirm').classList.add('invalid');
    document.getElementById('confirm-err').style.display='block'; valid=false;
  }
  if (valid) {
    const b = document.getElementById('regBtn');
    b.textContent='กำลังสมัคร...'; b.disabled=true;
  }
  return valid;
}
</script>
</body>
</html>