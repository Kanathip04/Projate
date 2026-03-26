<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Bangkok');

/* =========================
   DB Connection
========================= */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";

$fullname = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($fullname === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "กรุณากรอกข้อมูลให้ครบ";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password) < 6) {
        $error = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
    } else {
        $stmtCheck = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $stmtCheck->bind_param("s", $email);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            $userRow = $resultCheck->fetch_assoc();

            if ((int)$userRow['is_verified'] === 1) {
                $error = "อีเมลนี้ถูกใช้งานแล้ว";
            } else {
                $userId = (int)$userRow['id'];

                $otp = str_pad((string)rand(0, 999999), 6, "0", STR_PAD_LEFT);
                $expiresAt = date("Y-m-d H:i:s", strtotime("+5 minutes"));

                $conn->query("DELETE FROM user_otps WHERE user_id = {$userId} AND is_used = 0");

                $stmtOtp = $conn->prepare("INSERT INTO user_otps (user_id, otp_code, expires_at, is_used) VALUES (?, ?, ?, 0)");
                $stmtOtp->bind_param("iss", $userId, $otp, $expiresAt);
                $stmtOtp->execute();
                $stmtOtp->close();

                $_SESSION['pending_user_id'] = $userId;
                $_SESSION['pending_user_email'] = $email;
                $_SESSION['debug_otp'] = $otp;

                header("Location: otp_verify.php");
                exit;
            }
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmtInsert = $conn->prepare("INSERT INTO users (fullname, email, phone, password, is_verified) VALUES (?, ?, ?, ?, 0)");
            $stmtInsert->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);

            if ($stmtInsert->execute()) {
                $userId = $stmtInsert->insert_id;

                $otp = str_pad((string)rand(0, 999999), 6, "0", STR_PAD_LEFT);
                $expiresAt = date("Y-m-d H:i:s", strtotime("+5 minutes"));

                $stmtOtp = $conn->prepare("INSERT INTO user_otps (user_id, otp_code, expires_at, is_used) VALUES (?, ?, ?, 0)");
                $stmtOtp->bind_param("iss", $userId, $otp, $expiresAt);
                $stmtOtp->execute();
                $stmtOtp->close();

                $_SESSION['pending_user_id'] = $userId;
                $_SESSION['pending_user_email'] = $email;
                $_SESSION['debug_otp'] = $otp;

                header("Location: otp_verify.php");
                exit;
            } else {
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก";
            }

            $stmtInsert->close();
        }

        $stmtCheck->close();
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

    :root {
      --ink: #1a1a2e;
      --card: #ffffff;
      --accent: #c9a96e;
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
      width: 900px;
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
      border: 1.5px solid rgba(201,169,110,0.5);
      color: var(--accent);
      font-size: 0.72rem;
      font-weight: 600;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      margin-top: 2px;
    }
    .step-num.active {
      background: var(--accent);
      border-color: var(--accent);
      color: var(--ink);
    }
    .step-text { font-size: 0.82rem; color: rgba(255,255,255,0.6); line-height: 1.5; }
    .step-text strong { display: block; color: rgba(255,255,255,0.9); font-size: 0.85rem; margin-bottom: 2px; }

    .panel-bottom { position: relative; z-index: 1; }
    .login-link {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.4);
    }
    .login-link a {
      color: var(--accent);
      text-decoration: none;
      border-bottom: 1px solid rgba(201,169,110,0.4);
      transition: border-color 0.2s;
    }
    .login-link a:hover { border-color: var(--accent); }

    /* ── RIGHT PANEL ── */
    .panel-right {
      flex: 1;
      background: var(--card);
      padding: 48px 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .reg-header { margin-bottom: 30px; }
    .reg-eyebrow {
      font-size: 0.7rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .reg-title {
      font-size: 1.65rem;
      font-weight: 600;
      color: var(--ink);
      letter-spacing: -0.02em;
    }

    .alert-error {
      background: #fff5f5;
      border: 1.5px solid #fcc;
      color: var(--danger);
      border-radius: var(--radius);
      padding: 11px 14px;
      font-size: 0.84rem;
      margin-bottom: 22px;
      display: flex;
      align-items: center;
      gap: 8px;
      animation: fadein 0.3s both;
    }

    .form-row-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .form-group {
      margin-bottom: 18px;
      animation: fadein 0.5s both;
    }
    .form-group:nth-child(1) { animation-delay: 0.10s; }
    .form-group:nth-child(2) { animation-delay: 0.16s; }
    .form-group:nth-child(3) { animation-delay: 0.22s; }
    .form-group:nth-child(4) { animation-delay: 0.28s; }

    @keyframes fadein {
      from { opacity: 0; transform: translateX(10px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    label {
      display: block;
      font-size: 0.7rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 7px;
    }
    .required-dot {
      display: inline-block;
      width: 5px; height: 5px;
      border-radius: 50%;
      background: var(--accent);
      margin-left: 4px;
      vertical-align: middle;
      margin-top: -2px;
    }

    .input-wrap { position: relative; }
    .input-icon {
      position: absolute;
      left: 13px; top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: 0.85rem;
      pointer-events: none;
      transition: color 0.2s;
    }
    .input-eye {
      position: absolute;
      right: 13px; top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: 0.85rem;
      cursor: pointer;
      transition: color 0.2s;
      user-select: none;
    }
    .input-eye:hover { color: var(--accent); }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="tel"] {
      width: 100%;
      padding: 12px 13px 12px 38px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      font-family: 'Sarabun', sans-serif;
      font-size: 0.93rem;
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
    .input-wrap:focus-within .input-icon { color: var(--accent); }

    /* Password strength */
    .strength-bar {
      display: flex;
      gap: 4px;
      margin-top: 7px;
    }
    .strength-bar span {
      flex: 1;
      height: 3px;
      background: var(--border);
      border-radius: 2px;
      transition: background 0.3s;
    }
    .strength-label {
      font-size: 0.68rem;
      color: var(--muted);
      margin-top: 4px;
      letter-spacing: 0.05em;
    }

    .divider-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 6px 0 20px;
    }
    .divider-line { flex: 1; height: 1px; background: var(--border); }
    .divider-text { font-size: 0.7rem; color: var(--muted); letter-spacing: 0.1em; }

    .btn-submit {
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
      animation: fadein 0.5s 0.36s both;
    }
    .btn-submit::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, transparent 30%, rgba(201,169,110,0.18) 50%, transparent 70%);
      transform: translateX(-100%);
      transition: transform 0.45s;
    }
    .btn-submit:hover { background: #2a2a4a; transform: translateY(-1px); }
    .btn-submit:hover::after { transform: translateX(100%); }
    .btn-submit:active { transform: translateY(0); }

    .terms-text {
      text-align: center;
      margin-top: 14px;
      font-size: 0.72rem;
      color: var(--muted);
      line-height: 1.7;
      animation: fadein 0.5s 0.42s both;
    }
    .terms-text a { color: var(--ink); text-decoration: none; border-bottom: 1px solid var(--border); }
    .terms-text a:hover { color: var(--accent); border-color: var(--accent); }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 16px;
      font-size: 0.76rem;
      color: var(--muted);
      text-decoration: none;
      letter-spacing: 0.05em;
      transition: color 0.2s;
      animation: fadein 0.5s 0.46s both;
    }
    .back-link:hover { color: var(--ink); }

    @media (max-width: 700px) {
      .panel-left { display: none; }
      .panel-right { padding: 38px 24px; }
      .wrapper { max-width: 100%; }
      .form-row-2 { grid-template-columns: 1fr; }
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
        <div class="step-num active">1</div>
        <div class="step-text">
          <strong>กรอกข้อมูล</strong>
          กรอกชื่อ อีเมล และรหัสผ่าน
        </div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-text">
          <strong>ยืนยัน OTP</strong>
          รับรหัส 6 หลักทางอีเมล
        </div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-text">
          <strong>เข้าใช้งาน</strong>
          บัญชีพร้อมใช้งานทันที
        </div>
      </div>
    </div>

    <div class="panel-bottom">
      <div class="login-link">มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="panel-right">
    <div class="reg-header">
      <div class="reg-eyebrow">สร้างบัญชีใหม่</div>
      <div class="reg-title">สมัครสมาชิก</div>
    </div>

    <?php if ($error !== ""): ?>
      <div class="alert-error">
        <span>⚠</span>
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return beforeSubmit(this)">

      <div class="form-row-2">
        <div class="form-group">
          <label>ชื่อ-นามสกุล <span class="required-dot"></span></label>
          <div class="input-wrap">
            <input type="text" name="fullname" placeholder="กรอกชื่อ-นามสกุล"
              value="<?php echo htmlspecialchars($fullname); ?>" required/>
            <span class="input-icon">👤</span>
          </div>
        </div>

        <div class="form-group">
          <label>เบอร์โทรศัพท์</label>
          <div class="input-wrap">
            <input type="tel" name="phone" placeholder="0XX-XXX-XXXX"
              value="<?php echo htmlspecialchars($phone); ?>"/>
            <span class="input-icon">📱</span>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>อีเมล <span class="required-dot"></span></label>
        <div class="input-wrap">
          <input type="email" name="email" placeholder="example@email.com"
            value="<?php echo htmlspecialchars($email); ?>" required/>
          <span class="input-icon">✉</span>
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label>รหัสผ่าน <span class="required-dot"></span></label>
          <div class="input-wrap">
            <input type="password" name="password" id="password" placeholder="••••••••"
              required oninput="checkStrength(this.value)"/>
            <span class="input-icon">🔒</span>
            <span class="input-eye" onclick="togglePwd('password', this)">👁</span>
          </div>
          <div class="strength-bar">
            <span id="s1"></span><span id="s2"></span><span id="s3"></span><span id="s4"></span>
          </div>
          <div class="strength-label" id="strength-label">ความปลอดภัยรหัสผ่าน</div>
        </div>

        <div class="form-group">
          <label>ยืนยันรหัสผ่าน <span class="required-dot"></span></label>
          <div class="input-wrap">
            <input type="password" name="confirm_password" id="confirm_password"
              placeholder="••••••••" required/>
            <span class="input-icon">🔒</span>
            <span class="input-eye" onclick="togglePwd('confirm_password', this)">👁</span>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">สมัครสมาชิก</button>
    </form>

    <div class="terms-text">
      การสมัครสมาชิกแสดงว่าคุณยอมรับ
      <a href="#">เงื่อนไขการใช้งาน</a> และ <a href="#">นโยบายความเป็นส่วนตัว</a>
    </div>

    <a href="index.php" class="back-link">← กลับหน้าแรก</a>
  </div>

</div>

<script>
  function togglePwd(id, icon) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }

  function checkStrength(val) {
    const bars = [document.getElementById('s1'), document.getElementById('s2'),
                  document.getElementById('s3'), document.getElementById('s4')];
    const label = document.getElementById('strength-label');
    const colors = ['#c0392b','#e67e22','#f1c40f','#27ae60'];
    const labels = ['อ่อนมาก','อ่อน','ปานกลาง','แข็งแรง'];

    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    bars.forEach((b, i) => {
      b.style.background = i < score ? colors[score - 1] : 'var(--border)';
    });
    label.textContent = val.length > 0 ? labels[score - 1] || 'อ่อนมาก' : 'ความปลอดภัยรหัสผ่าน';
    label.style.color = val.length > 0 ? colors[score - 1] : 'var(--muted)';
  }

  function beforeSubmit(form) {
    const btn = document.getElementById('submitBtn');
    btn.textContent = 'กำลังดำเนินการ...';
    btn.disabled = true;
    return true;
  }
</script>

</body>
</html>