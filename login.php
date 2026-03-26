<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once 'config.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error_message = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        // ✅ ใช้ชื่อคอลัมน์จริงจาก DB: fullname, email, password
        $stmt = $conn->prepare("SELECT id, fullname, email, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $match = false;
            if (password_verify($password, $user['password']))  $match = true;
            elseif (md5($password) === $user['password'])       $match = true;
            elseif ($password === $user['password'])            $match = true;

            if ($match) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['fullname'];
                $_SESSION['user_email'] = $user['email'];

                if (!empty($_POST['remember'])) {
                    setcookie('remember_token', bin2hex(random_bytes(32)), time() + 60*60*24*30, '/', '', false, true);
                }

                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = 'รหัสผ่านไม่ถูกต้อง';
            }
        } else {
            $error_message = 'ไม่พบบัญชีผู้ใช้นี้ในระบบ';
        }
    }
}
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
    :root { --ink:#1a1a2e; --card:#fff; --accent:#c9a96e; --muted:#7a7a8c; --border:#e0ddd6; --danger:#c0392b; --radius:2px; }
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
    .alert-success { background:#edf7ed; border:1px solid #2e7d32; border-radius:var(--radius); padding:10px 14px; font-size:.82rem; color:#2e7d32; margin-bottom:18px; }
    .form-group { margin-bottom:20px; }
    label { display:block; font-size:.72rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin-bottom:8px; }
    .input-wrap { position:relative; }
    .input-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:.9rem; pointer-events:none; }
    input[type="email"], input[type="password"] { width:100%; padding:13px 14px 13px 40px; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Sarabun',sans-serif; font-size:.95rem; color:var(--ink); background:#fafaf8; outline:none; transition:border-color .2s,box-shadow .2s; }
    input:focus { border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(201,169,110,.13); }
    .error-msg { font-size:.74rem; color:var(--danger); margin-top:5px; display:none; }
    input.invalid { border-color:var(--danger); }
    .form-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    .checkbox-label { display:flex; align-items:center; gap:8px; font-size:.82rem; color:var(--muted); cursor:pointer; }
    .checkbox-label input[type="checkbox"] { accent-color:var(--accent); width:14px; height:14px; }
    .forgot-link { font-size:.82rem; color:var(--accent); text-decoration:none; border-bottom:1px solid transparent; }
    .forgot-link:hover { border-color:var(--accent); }
    .btn-login { width:100%; padding:14px; background:var(--ink); color:#fff; border:none; border-radius:var(--radius); font-family:'Sarabun',sans-serif; font-size:.88rem; letter-spacing:.15em; text-transform:uppercase; font-weight:600; cursor:pointer; transition:background .25s,transform .15s; }
    .btn-login:hover { background:#2a2a4a; transform:translateY(-1px); }
    .btn-login:disabled { opacity:.7; cursor:not-allowed; transform:none; }
    .divider { display:flex; align-items:center; gap:14px; margin:20px 0; }
    .divider-line { flex:1; height:1px; background:var(--border); }
    .divider-text { font-size:.72rem; color:var(--muted); }
    .btn-social { width:100%; padding:12px; background:transparent; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Sarabun',sans-serif; font-size:.85rem; color:var(--ink); cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; transition:border-color .2s,background .2s; }
    .btn-social:hover { border-color:var(--accent); background:#faf7f2; }
    .register-row { text-align:center; margin-top:24px; font-size:.82rem; color:var(--muted); }
    .register-row a { color:var(--ink); font-weight:600; text-decoration:none; border-bottom:1px solid var(--ink); }
    .register-row a:hover { color:var(--accent); border-color:var(--accent); }
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
      <div class="login-eyebrow">ยินดีต้อนรับ</div>
      <div class="login-title">เข้าสู่ระบบ</div>
    </div>

    <?php if (!empty($error_message)): ?>
      <div class="alert-error">⚠ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['registered'])): ?>
      <div class="alert-success">✓ สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</div>
    <?php endif; ?>

    <form method="POST" action="login.php" onsubmit="return validateForm()">
      <div class="form-group">
        <label for="email">อีเมล</label>
        <div class="input-wrap">
          <input type="email" id="email" name="email" placeholder="example@company.com"
                 autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
          <span class="input-icon">✉</span>
        </div>
        <div class="error-msg" id="email-err">กรุณากรอกอีเมลให้ถูกต้อง</div>
      </div>

      <div class="form-group">
        <label for="password">รหัสผ่าน</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password"/>
          <span class="input-icon" style="left:auto;right:14px;cursor:pointer;pointer-events:all;" onclick="togglePwd('password',this)">👁</span>
          <span class="input-icon">🔒</span>
        </div>
        <div class="error-msg" id="pass-err">กรุณากรอกรหัสผ่าน</div>
      </div>

      <div class="form-row">
        <label class="checkbox-label"><input type="checkbox" name="remember"/> จดจำฉันไว้</label>
        <a href="#" class="forgot-link">ลืมรหัสผ่าน?</a>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">เข้าสู่ระบบ</button>
    </form>

    <div class="divider">
      <div class="divider-line"></div><span class="divider-text">หรือ</span><div class="divider-line"></div>
    </div>

    <button class="btn-social">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#4285F4" d="M44.5 20H24v8.5h11.8C34.7 33.1 29.8 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l6-6C34.6 6.5 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20c11 0 20-8 20-20 0-1.3-.1-2.7-.5-4z"/><path fill="#34A853" d="M6.3 14.7l7 5.1C15 17.4 19.2 15 24 15c3 0 5.8 1.1 7.9 3l6-6C34.6 6.5 29.6 4 24 4c-7.7 0-14.4 4.4-17.7 10.7z"/><path fill="#FBBC05" d="M24 44c5.6 0 10.6-1.9 14.5-5l-6.7-5.5C29.8 35.1 27 36 24 36c-5.7 0-10.6-3-11.7-7.5l-7 5.4C8.2 40.1 15.5 44 24 44z"/><path fill="#EA4335" d="M44.5 20H24v8.5h11.8c-1 2.7-2.7 4.9-5 6.5l6.7 5.5C41.7 37.5 44 31.3 44 24c0-1.3-.1-2.7-.5-4z"/></svg>
      เข้าสู่ระบบด้วย Google
    </button>

    <div class="register-row">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></div>
  </div>
</div>
<script>
  function togglePwd(id, icon) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.textContent = inp.type === 'password' ? '👁' : '🙈';
  }
  function validateForm() {
    const email = document.getElementById('email');
    const pass  = document.getElementById('password');
    let valid = true;
    email.classList.remove('invalid'); pass.classList.remove('invalid');
    document.getElementById('email-err').style.display = 'none';
    document.getElementById('pass-err').style.display  = 'none';
    if (!email.value || !/\S+@\S+\.\S+/.test(email.value)) {
      email.classList.add('invalid'); document.getElementById('email-err').style.display = 'block'; valid = false;
    }
    if (!pass.value) {
      pass.classList.add('invalid'); document.getElementById('pass-err').style.display = 'block'; valid = false;
    }
    if (valid) { const b = document.getElementById('loginBtn'); b.textContent = 'กำลังเข้าสู่ระบบ...'; b.disabled = true; }
    return valid;
  }
</script>
</body>
</html>