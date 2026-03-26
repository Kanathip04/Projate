<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['otp_verified']) || empty($_SESSION['otp_email'])) {
    header('Location: login.php');
    exit;
}

$error_message = '';
$email = $_SESSION['otp_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');

    if (empty($password)) {
        $error_message = 'กรุณากรอกรหัสผ่าน';
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, email, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $match = false;
            if (password_verify($password, $user['password'])) $match = true;
            elseif (md5($password) === $user['password']) $match = true;
            elseif ($password === $user['password']) $match = true;

            if ($match) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['fullname'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['admin_logged_in'] = true;

                unset($_SESSION['otp_verified'], $_SESSION['otp_email'], $_SESSION['otp_message'], $_SESSION['otp_error']);

                header("Location: admin_dashboard.php");
                exit;
            } else {
                $error_message = 'รหัสผ่านไม่ถูกต้อง';
            }
        } else {
            $error_message = 'ไม่พบบัญชีผู้ใช้';
        }
    }
}

$message = $_SESSION['otp_message'] ?? '';
unset($_SESSION['otp_message']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>กรอกรหัสผ่าน</title>
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
  </style>
</head>
<body>
  <div class="card">
    <h1>กรอกรหัสผ่าน</h1>
    <p>ยืนยันอีเมลแล้วสำหรับ <strong><?= htmlspecialchars($email) ?></strong> กรุณากรอกรหัสผ่านเพื่อเข้าสู่ระบบ</p>

    <?php if (!empty($message)): ?>
      <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
      <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="password">รหัสผ่าน</label>
      <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
      <button type="submit" class="btn">เข้าสู่ระบบ</button>
    </form>
  </div>
</body>
</html>