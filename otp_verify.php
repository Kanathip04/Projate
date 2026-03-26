<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['pending_user_email'])) {
    header("Location: register.php");
    exit;
}

$userId = (int)$_SESSION['pending_user_id'];
$userEmail = $_SESSION['pending_user_email'];

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $otp = trim($_POST['otp'] ?? '');

    if ($otp === "") {
        $error = "กรุณากรอกรหัส OTP";
    } else {
        $stmt = $conn->prepare("
            SELECT id, otp_code, expires_at, is_used
            FROM user_otps
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "ไม่พบรหัส OTP กรุณาสมัครใหม่";
        } else {
            $otpRow = $result->fetch_assoc();

            if ((int)$otpRow['is_used'] === 1) {
                $error = "OTP นี้ถูกใช้งานแล้ว กรุณาขอรหัสใหม่";
            } elseif (strtotime($otpRow['expires_at']) < time()) {
                $error = "OTP หมดอายุแล้ว กรุณาขอรหัสใหม่";
            } elseif ($otp !== $otpRow['otp_code']) {
                $error = "รหัส OTP ไม่ถูกต้อง";
            } else {
                $otpId = (int)$otpRow['id'];

                $stmtUse = $conn->prepare("UPDATE user_otps SET is_used = 1 WHERE id = ?");
                $stmtUse->bind_param("i", $otpId);
                $stmtUse->execute();
                $stmtUse->close();

                $stmtVerify = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                $stmtVerify->bind_param("i", $userId);
                $stmtVerify->execute();
                $stmtVerify->close();

                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_user_email']);
                unset($_SESSION['debug_otp']);

                $success = "ยืนยัน OTP สำเร็จ กำลังไปหน้าเข้าสู่ระบบ...";
                header("refresh:2;url=login.php");
            }
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ยืนยัน OTP</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --ink: #1a1a2e;
      --card: #ffffff;
      --accent: #c9a96e;
      --muted: #7a7a8c;
      --border: #e0ddd6;
      --danger: #c0392b;
      --success: #27ae60;
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
      padding: 28px 16px;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background-image: repeating-linear-gradient(
        90deg,
        rgba(201,169,110,0.04) 0px,
        rgba(201,169,110,0.04) 1px,
        transparent 1px,
        transparent 80px
      );
      pointer-events: none;
    }

    .wrapper {
      display: flex;
      width: 820px;
      max-width: 96vw;
      box-shadow: 0 40px 80px rgba(26,26,46,0.14), 0 2px 8px rgba(26,26,46,0.06);
      animation: rise 0.7s cubic-bezier(.23,1,.32,1) both;
    }

    @keyframes rise {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── LEFT PANEL ── */
    .panel-left {
      flex: 0 0 300px;
      background: var(--ink);
      padding: 52px 40px;
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

    .brand { position: relative; z-index: 1; }
    .brand-line { width: 36px; height: 3px; background: var(--accent); margin-bottom: 18px; }
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

    .steps { position: relative; z-index: 1; }
    .steps-title {
      font-size: 0.68rem;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 20px;
    }
    .step {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      margin-bottom: 20px;
    }
    .step-num {
      width: 26px; height: 26px;
      border-radius: 50%;
      border: 1.5px solid rgba(201,169,110,0.3);
      color: rgba(255,255,255,0.3);
      font-size: 0.72rem;
      font-weight: 600;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      margin-top: 2px;
    }
    .step-num.done {
      background: rgba(201,169,110,0.2);
      border-color: rgba(201,169,110,0.5);
      color: var(--accent);
    }
    .step-num.active {
      background: var(--accent);
      border-color: var(--accent);
      color: var(--ink);
    }
    .step-text { font-size: 0.82rem; color: rgba(255,255,255,0.35); line-height: 1.5; }
    .step-text.active-text { color: rgba(255,255,255,0.75); }
    .step-text strong { display: block; font-size: 0.85rem; margin-bottom: 2px; }
    .step-connector {
      width: 1px; height: 16px;
      background: rgba(201,169,110,0.2);
      margin-left: 12px;
      margin-bottom: 4px;
    }

    .panel-bottom { position: relative; z-index: 1; }
    .back-reg {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.4);
    }
    .back-reg a {
      color: var(--accent);
      text-decoration: none;
      border-bottom: 1px solid rgba(201,169,110,0.4);
      transition: border-color 0.2s;
    }
    .back-reg a:hover { border-color: var(--accent); }

    /* ── RIGHT PANEL ── */
    .panel-right {
      flex: 1;
      background: var(--card);
      padding: 52px 52px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .otp-header { margin-bottom: 10px; }
    .otp-eyebrow {
      font-size: 0.7rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .otp-title {
      font-size: 1.65rem;
      font-weight: 600;
      color: var(--ink);
      letter-spacing: -0.02em;
    }
    .otp-desc {
      margin-top: 10px;
      font-size: 0.86rem;
      color: var(--muted);
      line-height: 1.7;
    }
    .otp-email {
      color: var(--ink);
      font-weight: 600;
    }

    /* Alerts */
    .alert {
      border-radius: var(--radius);
      padding: 11px 14px;
      font-size: 0.84rem;
      margin: 20px 0;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      animation: fadein 0.3s both;
      line-height: 1.6;
    }
    .alert-error { background: #fff5f5; border: 1.5px solid #fcc; color: var(--danger); }
    .alert-success { background: #f0faf3; border: 1.5px solid #b7dfc2; color: var(--success); }
    .alert-info { background: #fdf9f3; border: 1.5px solid rgba(201,169,110,0.4); color: #8a6a30; }

    @keyframes fadein {
      from { opacity: 0; transform: translateX(10px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    /* OTP Boxes */
    .otp-wrap {
      margin: 28px 0 8px;
      animation: fadein 0.5s 0.1s both;
    }
    .otp-label {
      font-size: 0.7rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 12px;
    }
    .otp-boxes {
      display: flex;
      gap: 10px;
    }
    .otp-box {
      flex: 1;
      height: 58px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      background: #fafaf8;
      font-family: 'Sarabun', sans-serif;
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--ink);
      text-align: center;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s, transform 0.15s;
      caret-color: var(--accent);
    }
    .otp-box:focus {
      border-color: var(--accent);
      background: #fff;
      box-shadow: 0 0 0 3px rgba(201,169,110,0.13);
      transform: translateY(-2px);
    }
    .otp-box.filled {
      border-color: rgba(201,169,110,0.6);
      background: #fdf9f3;
    }
    .otp-box.error-box {
      border-color: var(--danger);
      background: #fff8f8;
      animation: shake 0.4s both;
    }

    @keyframes shake {
      0%,100% { transform: translateX(0); }
      20%      { transform: translateX(-5px); }
      40%      { transform: translateX(5px); }
      60%      { transform: translateX(-4px); }
      80%      { transform: translateX(4px); }
    }

    /* Timer */
    .timer-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 12px;
      animation: fadein 0.5s 0.2s both;
    }
    .timer-text {
      font-size: 0.78rem;
      color: var(--muted);
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .timer-count {
      font-weight: 600;
      color: var(--ink);
      font-variant-numeric: tabular-nums;
      min-width: 36px;
    }
    .timer-count.urgent { color: var(--danger); }
    .resend-btn {
      font-size: 0.78rem;
      color: var(--accent);
      background: none;
      border: none;
      cursor: pointer;
      font-family: 'Sarabun', sans-serif;
      border-bottom: 1px solid transparent;
      padding: 0;
      transition: border-color 0.2s;
    }
    .resend-btn:not(:disabled):hover { border-color: var(--accent); }
    .resend-btn:disabled { color: var(--muted); cursor: default; }

    .btn-verify {
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
      margin-top: 24px;
      animation: fadein 0.5s 0.3s both;
    }
    .btn-verify::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, transparent 30%, rgba(201,169,110,0.18) 50%, transparent 70%);
      transform: translateX(-100%);
      transition: transform 0.45s;
    }
    .btn-verify:hover:not(:disabled) { background: #2a2a4a; transform: translateY(-1px); }
    .btn-verify:hover:not(:disabled)::after { transform: translateX(100%); }
    .btn-verify:disabled { opacity: 0.5; cursor: default; }

    .note-text {
      text-align: center;
      margin-top: 18px;
      font-size: 0.74rem;
      color: var(--muted);
      line-height: 1.7;
      animation: fadein 0.5s 0.36s both;
    }

    @media (max-width: 640px) {
      .panel-left { display: none; }
      .panel-right { padding: 40px 24px; }
      .wrapper { max-width: 100%; }
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

    <div class="steps">
      <div class="steps-title">ขั้นตอนการสมัคร</div>

      <div class="step">
        <div class="step-num done">✓</div>
        <div class="step-text">
          <strong>กรอกข้อมูล</strong>
          กรอกชื่อ อีเมล และรหัสผ่าน
        </div>
      </div>
      <div class="step-connector"></div>

      <div class="step">
        <div class="step-num active">2</div>
        <div class="step-text active-text">
          <strong>ยืนยัน OTP</strong>
          รับรหัส 6 หลักทางอีเมล
        </div>
      </div>
      <div class="step-connector"></div>

      <div class="step">
        <div class="step-num">3</div>
        <div class="step-text">
          <strong>เข้าใช้งาน</strong>
          บัญชีพร้อมใช้งานทันที
        </div>
      </div>
    </div>

    <div class="panel-bottom">
      <div class="back-reg">กรอกข้อมูลผิด? <a href="register.php">กลับไปสมัครใหม่</a></div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="panel-right">
    <div class="otp-header">
      <div class="otp-eyebrow">ขั้นตอนที่ 2 / 3</div>
      <div class="otp-title">ยืนยันตัวตน</div>
      <div class="otp-desc">
        ระบบได้ส่งรหัส OTP 6 หลักไปยัง<br/>
        <span class="otp-email"><?php echo htmlspecialchars($userEmail); ?></span>
      </div>
    </div>

    <?php if ($error !== ""): ?>
      <div class="alert alert-error"><span>⚠</span><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
      <div class="alert alert-success"><span>✓</span><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['debug_otp'])): ?>
      <div class="alert alert-info">
        <span>🔧</span>
        <span>
          OTP สำหรับทดสอบ: <strong><?php echo htmlspecialchars($_SESSION['debug_otp']); ?></strong>
          &nbsp;—&nbsp; ลบกล่องนี้ออกเมื่อเชื่อมอีเมลจริง
        </span>
      </div>
    <?php endif; ?>

    <form method="POST" id="otpForm" onsubmit="return beforeSubmit()">
      <!-- Hidden field รับค่า OTP รวม -->
      <input type="hidden" name="otp" id="otpHidden"/>

      <div class="otp-wrap">
        <div class="otp-label">รหัส OTP</div>
        <div class="otp-boxes" id="otpBoxes">
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-idx="0"/>
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-idx="1"/>
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-idx="2"/>
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-idx="3"/>
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-idx="4"/>
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-idx="5"/>
        </div>
      </div>

      <div class="timer-row">
        <div class="timer-text">
          ⏱ หมดอายุใน <span class="timer-count" id="timerCount">05:00</span>
        </div>
        <button type="button" class="resend-btn" id="resendBtn" disabled onclick="resendOtp()">
          ส่งรหัสใหม่
        </button>
      </div>

      <button type="submit" class="btn-verify" id="submitBtn" disabled>ยืนยันรหัส OTP</button>
    </form>

    <div class="note-text">
      ไม่พบอีเมล? ตรวจสอบในโฟลเดอร์ Spam<br/>
      รหัส OTP มีอายุ 5 นาทีเท่านั้น
    </div>
  </div>

</div>

<script>
  const boxes = document.querySelectorAll('.otp-box');
  const hidden = document.getElementById('otpHidden');
  const submitBtn = document.getElementById('submitBtn');
  const resendBtn = document.getElementById('resendBtn');
  const timerEl  = document.getElementById('timerCount');

  // ── OTP Boxes logic ──
  boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
      const val = e.target.value.replace(/\D/g, '');
      box.value = val ? val[0] : '';
      box.classList.toggle('filled', !!box.value);
      if (box.value && i < 5) boxes[i + 1].focus();
      syncHidden();
    });

    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && i > 0) {
        boxes[i - 1].focus();
        boxes[i - 1].value = '';
        boxes[i - 1].classList.remove('filled');
        syncHidden();
      }
    });

    box.addEventListener('paste', e => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      if (!text) return;
      boxes.forEach((b, j) => {
        b.value = text[j] || '';
        b.classList.toggle('filled', !!b.value);
      });
      boxes[Math.min(text.length, 5)].focus();
      syncHidden();
    });
  });

  function syncHidden() {
    const val = Array.from(boxes).map(b => b.value).join('');
    hidden.value = val;
    submitBtn.disabled = val.length < 6;
  }

  // Auto-focus first box
  boxes[0].focus();

  // ── Countdown Timer (5 min) ──
  let seconds = 300;
  const interval = setInterval(() => {
    seconds--;
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    timerEl.textContent = `${m}:${s}`;
    timerEl.classList.toggle('urgent', seconds <= 60);
    if (seconds <= 0) {
      clearInterval(interval);
      timerEl.textContent = '00:00';
      resendBtn.disabled = false;
    }
  }, 1000);

  function resendOtp() {
    resendBtn.disabled = true;
    resendBtn.textContent = 'กำลังส่ง...';
    // TODO: เชื่อม endpoint resend OTP
    setTimeout(() => {
      resendBtn.textContent = 'ส่งแล้ว ✓';
    }, 1000);
  }

  function beforeSubmit() {
    // Shake if wrong (PHP already set error)
    <?php if ($error !== ""): ?>
      boxes.forEach(b => b.classList.add('error-box'));
      setTimeout(() => boxes.forEach(b => b.classList.remove('error-box')), 600);
      return true;
    <?php endif; ?>
    submitBtn.textContent = 'กำลังตรวจสอบ...';
    submitBtn.disabled = true;
    return true;
  }
</script>

</body>
</html>