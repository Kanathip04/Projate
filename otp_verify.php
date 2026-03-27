<?php
session_start();

// ต้องมี otp_email ใน session ก่อน
if (empty($_SESSION['otp_email'])) {
    header('Location: login.php'); exit;
}

$error_message = $_SESSION['otp_error'] ?? '';
unset($_SESSION['otp_error']);

$email = $_SESSION['otp_email'];
// แสดงแค่บางส่วนของอีเมล เช่น k***@gmail.com
$parts = explode('@', $email);
$masked = substr($parts[0], 0, 2) . str_repeat('*', max(1, strlen($parts[0]) - 2)) . '@' . $parts[1];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ยืนยันอีเมล</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root { --ink:#1a1a2e; --accent:#c9a96e; --muted:#7a7a8c; --border:#e0ddd6; --danger:#c0392b; --success:#2e7d32; }
    body { min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f5f1eb; font-family:'Sarabun',sans-serif; padding:20px; }
    body::before { content:''; position:fixed; inset:0; pointer-events:none; background-image:repeating-linear-gradient(90deg,rgba(201,169,110,.04) 0px,rgba(201,169,110,.04) 1px,transparent 1px,transparent 80px); }
    .card { width:100%; max-width:440px; background:#fff; border-radius:20px; box-shadow:0 24px 60px rgba(26,26,46,.12); overflow:hidden; animation:rise .6s cubic-bezier(.23,1,.32,1) both; }
    @keyframes rise { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
    .card-top { background:var(--ink); padding:32px; text-align:center; position:relative; overflow:hidden; }
    .card-top::before { content:''; position:absolute; width:200px; height:200px; border-radius:50%; background:radial-gradient(circle,rgba(201,169,110,.18) 0%,transparent 70%); top:-60px; right:-60px; }
    .card-top::after  { content:''; position:absolute; width:150px; height:150px; border-radius:50%; background:radial-gradient(circle,rgba(201,169,110,.1) 0%,transparent 70%); bottom:-40px; left:-40px; }
    .brand { font-family:'Playfair Display',serif; font-style:italic; font-size:1.4rem; color:#fff; position:relative; z-index:1; margin-bottom:20px; }
    .otp-icon { font-size:2.8rem; position:relative; z-index:1; margin-bottom:12px; }
    .card-top h2 { font-size:1.2rem; color:#fff; position:relative; z-index:1; margin-bottom:8px; }
    .card-top p  { font-size:.82rem; color:rgba(255,255,255,.6); position:relative; z-index:1; line-height:1.6; }
    .card-top strong { color:rgba(255,255,255,.9); }
    .card-body { padding:32px; }
    .alert-error { background:#fdf0ef; border:1px solid var(--danger); border-radius:8px; padding:10px 14px; font-size:.82rem; color:var(--danger); margin-bottom:20px; }
    .label { display:block; font-size:.68rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); margin-bottom:8px; }
    /* OTP boxes */
    .otp-boxes { display:flex; gap:10px; justify-content:center; margin-bottom:24px; }
    .otp-box { width:52px; height:60px; border:2px solid var(--border); border-radius:10px; text-align:center; font-size:1.4rem; font-weight:700; color:var(--ink); background:#fafaf8; outline:none; transition:border-color .2s, box-shadow .2s; }
    .otp-box:focus { border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(201,169,110,.13); }
    .otp-box.filled { border-color:var(--ink); background:#fff; }
    /* Hidden real input */
    #otp-hidden { display:none; }
    .btn-verify { width:100%; padding:14px; background:var(--ink); color:#fff; border:none; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:.88rem; letter-spacing:.12em; text-transform:uppercase; font-weight:600; cursor:pointer; transition:background .25s, transform .15s; }
    .btn-verify:hover { background:#2a2a4a; transform:translateY(-1px); }
    .btn-verify:disabled { opacity:.6; cursor:not-allowed; transform:none; }
    .resend-row { text-align:center; margin-top:18px; font-size:.82rem; color:var(--muted); }
    .resend-row a { color:var(--ink); font-weight:600; text-decoration:none; border-bottom:1px solid var(--ink); }
    .resend-row a:hover { color:var(--accent); border-color:var(--accent); }
    .timer { color:var(--accent); font-weight:700; }
    .back-link { display:block; text-align:center; margin-top:14px; font-size:.8rem; color:var(--muted); text-decoration:none; }
    .back-link:hover { color:var(--ink); }
  </style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <div class="brand">WRBRI</div>
    <div class="otp-icon"></div>
    <h2>ยืนยันอีเมลของคุณ</h2>
    <p>ระบบส่งรหัส OTP ไปที่<br><strong><?= htmlspecialchars($masked) ?></strong><br>กรอกรหัส 6 หลักเพื่อยืนยัน</p>
  </div>

  <div class="card-body">
    <?php if (!empty($error_message)): ?>
      <div class="alert-error">⚠ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <label class="label">รหัส OTP</label>

    <form action="otp_check.php" method="POST" id="otpForm">
      <input type="hidden" name="otp" id="otp-hidden">

      <div class="otp-boxes">
        <?php for ($i=0; $i<6; $i++): ?>
          <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn-verify" id="verifyBtn" disabled>ยืนยันอีเมล</button>
    </form>

    <div class="resend-row">
      ไม่ได้รับรหัส? รออีก <span class="timer" id="timer">5:00</span> แล้วกด
      <a href="send_otp_register.php" id="resendLink" style="pointer-events:none;opacity:.4;">ส่งใหม่</a>
    </div>

    <a href="register.php" class="back-link">← กลับหน้าสมัครสมาชิก</a>
  </div>
</div>

<script>
// ── OTP box logic ──
const boxes  = document.querySelectorAll('.otp-box');
const hidden = document.getElementById('otp-hidden');
const btn    = document.getElementById('verifyBtn');

boxes.forEach((box, i) => {
  box.addEventListener('input', e => {
    const val = e.target.value.replace(/\D/g,'');
    e.target.value = val;
    if (val && i < boxes.length-1) boxes[i+1].focus();
    updateHidden();
  });
  box.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !box.value && i > 0) boxes[i-1].focus();
  });
  box.addEventListener('paste', e => {
    e.preventDefault();
    const pasted = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    pasted.split('').forEach((ch, j) => { if (boxes[i+j]) boxes[i+j].value = ch; });
    updateHidden();
    const next = Math.min(i + pasted.length, boxes.length-1);
    boxes[next].focus();
  });
});

function updateHidden() {
  const val = [...boxes].map(b => b.value).join('');
  hidden.value = val;
  btn.disabled = val.length < 6;
  boxes.forEach(b => b.classList.toggle('filled', b.value !== ''));
}

// ── Countdown timer ──
let seconds = 300;
const timerEl  = document.getElementById('timer');
const resendEl = document.getElementById('resendLink');

const countdown = setInterval(() => {
  seconds--;
  const m = Math.floor(seconds/60);
  const s = seconds % 60;
  timerEl.textContent = m + ':' + String(s).padStart(2,'0');
  if (seconds <= 0) {
    clearInterval(countdown);
    timerEl.textContent = 'หมดเวลา';
    resendEl.style.pointerEvents = 'auto';
    resendEl.style.opacity = '1';
  }
}, 1000);

document.getElementById('otpForm').addEventListener('submit', () => {
  btn.textContent = 'กำลังยืนยัน...';
  btn.disabled = true;
});
</script>
</body>
</html>