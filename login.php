<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>เข้าสู่ระบบ</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --ink: #1a1a2e;
      --card: #ffffff;
      --accent: #c9a96e;
      --accent2: #e8d5b0;
      --muted: #7a7a8c;
      --border: #e0ddd6;
      --danger: #c0392b;
      --radius: 2px;
    }

    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #f5f1eb;
      background-image:
        radial-gradient(ellipse at 20% 50%, rgba(201,169,110,0.12) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(26,26,46,0.06) 0%, transparent 55%);
      font-family: 'Sarabun', sans-serif;
      overflow: hidden;
    }

    /* Decorative lines */
    body::before, body::after {
      content: '';
      position: fixed;
      pointer-events: none;
    }
    body::before {
      top: 0; left: 0; right: 0; bottom: 0;
      background-image:
        repeating-linear-gradient(
          90deg,
          rgba(201,169,110,0.04) 0px,
          rgba(201,169,110,0.04) 1px,
          transparent 1px,
          transparent 80px
        );
    }

    .wrapper {
      display: flex;
      width: 860px;
      max-width: 96vw;
      min-height: 520px;
      box-shadow: 0 40px 80px rgba(26,26,46,0.14), 0 2px 8px rgba(26,26,46,0.06);
      animation: rise 0.7s cubic-bezier(.23,1,.32,1) both;
    }

    @keyframes rise {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── LEFT PANEL ── */
    .panel-left {
      flex: 1;
      background: var(--ink);
      padding: 52px 44px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }

    .panel-left::before {
      content: '';
      position: absolute;
      width: 320px; height: 320px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(201,169,110,0.18) 0%, transparent 70%);
      top: -80px; right: -100px;
      pointer-events: none;
    }
    .panel-left::after {
      content: '';
      position: absolute;
      width: 200px; height: 200px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(201,169,110,0.1) 0%, transparent 70%);
      bottom: 40px; left: -60px;
      pointer-events: none;
    }

    .brand {
      position: relative;
      z-index: 1;
    }
    .brand-line {
      width: 36px; height: 3px;
      background: var(--accent);
      margin-bottom: 18px;
    }
    .brand-name {
      font-family: 'Playfair Display', serif;
      font-style: italic;
      font-size: 2rem;
      color: #fff;
      letter-spacing: 0.02em;
      line-height: 1.2;
    }
    .brand-sub {
      margin-top: 10px;
      font-size: 0.78rem;
      color: rgba(255,255,255,0.38);
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }

    .panel-quote {
      position: relative;
      z-index: 1;
    }
    .quote-mark {
      font-size: 4rem;
      color: var(--accent);
      line-height: 0.6;
      margin-bottom: 14px;
      font-family: Georgia, serif;
      opacity: 0.7;
    }
    .quote-text {
      font-size: 0.92rem;
      color: rgba(255,255,255,0.68);
      line-height: 1.75;
    }
    .quote-author {
      margin-top: 14px;
      font-size: 0.72rem;
      color: var(--accent);
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    /* ── RIGHT PANEL ── */
    .panel-right {
      flex: 1.05;
      background: var(--card);
      padding: 52px 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .login-header {
      margin-bottom: 36px;
    }
    .login-eyebrow {
      font-size: 0.7rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .login-title {
      font-size: 1.75rem;
      font-weight: 600;
      color: var(--ink);
      letter-spacing: -0.02em;
    }

    .form-group {
      margin-bottom: 22px;
      animation: fadein 0.5s both;
    }
    .form-group:nth-child(1) { animation-delay: 0.15s; }
    .form-group:nth-child(2) { animation-delay: 0.25s; }

    @keyframes fadein {
      from { opacity: 0; transform: translateX(12px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    label {
      display: block;
      font-size: 0.72rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 8px;
    }

    .input-wrap {
      position: relative;
    }
    .input-icon {
      position: absolute;
      left: 14px; top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: 0.9rem;
      pointer-events: none;
      transition: color 0.2s;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 13px 14px 13px 40px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      font-family: 'Sarabun', sans-serif;
      font-size: 0.95rem;
      color: var(--ink);
      background: #fafaf8;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    input:focus {
      border-color: var(--accent);
      background: #fff;
      box-shadow: 0 0 0 3px rgba(201,169,110,0.13);
    }
    input:focus ~ .input-icon,
    .input-wrap:focus-within .input-icon {
      color: var(--accent);
    }

    .form-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      animation: fadein 0.5s 0.3s both;
    }
    .checkbox-label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.82rem;
      color: var(--muted);
      cursor: pointer;
      user-select: none;
    }
    .checkbox-label input[type="checkbox"] {
      accent-color: var(--accent);
      width: 14px; height: 14px;
      cursor: pointer;
    }
    .forgot-link {
      font-size: 0.82rem;
      color: var(--accent);
      text-decoration: none;
      border-bottom: 1px solid transparent;
      transition: border-color 0.2s;
    }
    .forgot-link:hover { border-color: var(--accent); }

    .btn-login {
      width: 100%;
      padding: 14px;
      background: var(--ink);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      font-family: 'Sarabun', sans-serif;
      font-size: 0.88rem;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      font-weight: 600;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: background 0.25s, transform 0.15s;
      animation: fadein 0.5s 0.35s both;
    }
    .btn-login::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, transparent 30%, rgba(201,169,110,0.18) 50%, transparent 70%);
      transform: translateX(-100%);
      transition: transform 0.45s;
    }
    .btn-login:hover { background: #2a2a4a; transform: translateY(-1px); }
    .btn-login:hover::after { transform: translateX(100%); }
    .btn-login:active { transform: translateY(0); }

    .divider {
      display: flex;
      align-items: center;
      gap: 14px;
      margin: 24px 0;
      animation: fadein 0.5s 0.4s both;
    }
    .divider-line { flex: 1; height: 1px; background: var(--border); }
    .divider-text { font-size: 0.72rem; color: var(--muted); letter-spacing: 0.1em; }

    .btn-social {
      width: 100%;
      padding: 12px;
      background: transparent;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      font-family: 'Sarabun', sans-serif;
      font-size: 0.85rem;
      color: var(--ink);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: border-color 0.2s, background 0.2s;
      animation: fadein 0.5s 0.45s both;
    }
    .btn-social:hover { border-color: var(--accent); background: #faf7f2; }

    .register-row {
      text-align: center;
      margin-top: 28px;
      font-size: 0.82rem;
      color: var(--muted);
      animation: fadein 0.5s 0.5s both;
    }
    .register-row a {
      color: var(--ink);
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px solid var(--ink);
      transition: color 0.2s, border-color 0.2s;
    }
    .register-row a:hover { color: var(--accent); border-color: var(--accent); }

    /* Error state */
    .error-msg {
      font-size: 0.74rem;
      color: var(--danger);
      margin-top: 5px;
      display: none;
    }
    input.invalid { border-color: var(--danger); }
    input.invalid:focus { box-shadow: 0 0 0 3px rgba(192,57,43,0.1); }

    @media (max-width: 640px) {
      .panel-left { display: none; }
      .panel-right { padding: 40px 28px; }
      .wrapper { max-width: 100%; min-height: 100vh; border-radius: 0; }
    }
  </style>
</head>
<body>

<div class="wrapper">

  <!-- LEFT -->
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

  <!-- RIGHT -->
  <div class="panel-right">
    <div class="login-header">
      <div class="login-eyebrow">ยินดีต้อนรับ</div>
      <div class="login-title">เข้าสู่ระบบ</div>
    </div>

    <div class="form-group">
      <label for="email">อีเมล</label>
      <div class="input-wrap">
        <input type="email" id="email" placeholder="example@company.com" autocomplete="email"/>
        <span class="input-icon">✉</span>
      </div>
      <div class="error-msg" id="email-err">กรุณากรอกอีเมลให้ถูกต้อง</div>
    </div>

    <div class="form-group">
      <label for="password">รหัสผ่าน</label>
      <div class="input-wrap">
        <input type="password" id="password" placeholder="••••••••" autocomplete="current-password"/>
        <span class="input-icon" id="toggle-icon" style="left:auto;right:14px;cursor:pointer;pointer-events:all;" onclick="togglePassword()">👁</span>
        <span class="input-icon">🔒</span>
      </div>
      <div class="error-msg" id="pass-err">กรุณากรอกรหัสผ่าน</div>
    </div>

    <div class="form-row">
      <label class="checkbox-label">
        <input type="checkbox" id="remember"/>
        จดจำฉันไว้
      </label>
      <a href="#" class="forgot-link">ลืมรหัสผ่าน?</a>
    </div>

    <button class="btn-login" onclick="handleLogin()">เข้าสู่ระบบ</button>

    <div class="divider">
      <div class="divider-line"></div>
      <span class="divider-text">หรือ</span>
      <div class="divider-line"></div>
    </div>

    <button class="btn-social">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#4285F4" d="M44.5 20H24v8.5h11.8C34.7 33.1 29.8 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l6-6C34.6 6.5 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20c11 0 20-8 20-20 0-1.3-.1-2.7-.5-4z"/><path fill="#34A853" d="M6.3 14.7l7 5.1C15 17.4 19.2 15 24 15c3 0 5.8 1.1 7.9 3l6-6C34.6 6.5 29.6 4 24 4c-7.7 0-14.4 4.4-17.7 10.7z"/><path fill="#FBBC05" d="M24 44c5.6 0 10.6-1.9 14.5-5l-6.7-5.5C29.8 35.1 27 36 24 36c-5.7 0-10.6-3-11.7-7.5l-7 5.4C8.2 40.1 15.5 44 24 44z"/><path fill="#EA4335" d="M44.5 20H24v8.5h11.8c-1 2.7-2.7 4.9-5 6.5l6.7 5.5C41.7 37.5 44 31.3 44 24c0-1.3-.1-2.7-.5-4z"/></svg>
      เข้าสู่ระบบด้วย Google
    </button>

    <div class="register-row">
      ยังไม่มีบัญชี? <a href="#">สมัครสมาชิก</a>
    </div>
  </div>

</div>

<script>
  function togglePassword() {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }

  function handleLogin() {
    const email = document.getElementById('email');
    const pass  = document.getElementById('password');
    const eErr  = document.getElementById('email-err');
    const pErr  = document.getElementById('pass-err');
    let valid = true;

    // Reset
    email.classList.remove('invalid');
    pass.classList.remove('invalid');
    eErr.style.display = 'none';
    pErr.style.display = 'none';

    if (!email.value || !/\S+@\S+\.\S+/.test(email.value)) {
      email.classList.add('invalid');
      eErr.style.display = 'block';
      valid = false;
    }
    if (!pass.value) {
      pass.classList.add('invalid');
      pErr.style.display = 'block';
      valid = false;
    }

    if (valid) {
      const btn = document.querySelector('.btn-login');
      btn.textContent = 'กำลังเข้าสู่ระบบ...';
      btn.disabled = true;
      setTimeout(() => {
        btn.textContent = '✓ เข้าสู่ระบบสำเร็จ';
        btn.style.background = '#2e7d32';
      }, 1200);
    }
  }
</script>

</body>
</html>