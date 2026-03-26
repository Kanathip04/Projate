<?php
session_start();

if (empty($_SESSION['otp_email'])) {
    header('Location: login.php');
    exit;
}

$error_message = $_SESSION['otp_error'] ?? '';
$message = $_SESSION['otp_message'] ?? '';

unset($_SESSION['otp_error'], $_SESSION['otp_message']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ยืนยัน OTP</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:'Sarabun',sans-serif;
      background:#f5f1eb;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:20px;
    }
    .card{
      width:100%;
      max-width:460px;
      background:#fff;
      border-radius:12px;
      padding:32px;
      box-shadow:0 24px 60px rgba(0,0,0,.08);
    }
    h1{
      font-size:1.6rem;
      color:#1a1a2e;
      margin-bottom:10px;
    }
    p{
      color:#666;
      margin-bottom:20px;
      line-height:1.7;
    }
    label{
      display:block;
      font-size:.85rem;
      margin-bottom:8px;
      color:#666;
    }
    input{
      width:100%;
      padding:14px;
      border:1px solid #ddd;
      border-radius:8px;
      font-size:1rem;
      margin-bottom:16px;
      outline:none;
    }
    input:focus{
      border-color:#c9a96e;
      box-shadow:0 0 0 3px rgba(201,169,110,.12);
    }
    .btn{
      width:100%;
      padding:14px;
      border:none;
      background:#1a1a2e;
      color:#fff;
      border-radius:8px;
      cursor:pointer;
      font-size:1rem;
      font-weight:600;
    }
    .btn:hover{background:#2a2a4a}
    .alert-error{
      background:#fdf0ef;
      color:#c0392b;
      border:1px solid #c0392b;
      padding:10px 14px;
      border-radius:8px;
      margin-bottom:16px;
      font-size:.9rem;
    }
    .alert-success{
      background:#edf7ed;
      color:#2e7d32;
      border:1px solid #2e7d32;
      padding:10px 14px;
      border-radius:8px;
      margin-bottom:16px;
      font-size:.9rem;
    }
    .link-row{
      margin-top:14px;
      text-align:center;
    }
    .link-row a{
      color:#1a1a2e;
      text-decoration:none;
      font-weight:600;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>ยืนยัน OTP</h1>
    <p>ระบบได้ส่ง OTP ไปที่อีเมล <strong><?= htmlspecialchars($_SESSION['otp_email']) ?></strong> กรุณากรอกรหัสเพื่อยืนยันอีเมลก่อนเข้าสู่ระบบ</p>

    <?php if (!empty($error_message)): ?>
      <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
      <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form action="otp_check.php" method="POST">
      <label for="otp">รหัส OTP</label>
      <input type="text" id="otp" name="otp" maxlength="6" placeholder="กรอกรหัส 6 หลัก" required>

      <button type="submit" class="btn">ยืนยัน OTP</button>
    </form>

    <div class="link-row">
      <a href="login.php">← กลับไปหน้าเข้าสู่ระบบ</a>
    </div>
  </div>
</body>
</html>