<?php
session_start();
require_once 'config.php';

$otp   = trim($_POST['otp'] ?? '');
$email = $_SESSION['otp_email'] ?? '';

if (empty($email)) {
    $_SESSION['otp_error'] = 'ไม่พบอีเมลสำหรับยืนยัน OTP';
    header('Location: login.php');
    exit;
}

if (empty($otp)) {
    $_SESSION['otp_error'] = 'กรุณากรอกรหัส OTP';
    header('Location: otp_verify.php');
    exit;
}

// ตรวจสอบ OTP
$stmt = $conn->prepare("
    SELECT * 
    FROM email_otps 
    WHERE email = ? 
      AND otp_code = ? 
      AND is_used = 0 
      AND expires_at >= NOW()
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result = $stmt->get_result();
$otpRow = $result->fetch_assoc();
$stmt->close();

if (!$otpRow) {
    $_SESSION['otp_error'] = 'รหัส OTP ไม่ถูกต้องหรือหมดอายุ';
    header('Location: otp_verify.php');
    exit;
}

// mark OTP used
$stmt = $conn->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
$stmt->bind_param("i", $otpRow['id']);
$stmt->execute();
$stmt->close();

// อัปเดตสถานะยืนยันอีเมล
$stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->close();

// ✅ ดึงข้อมูล user รวมถึง role ด้วย
$stmt = $conn->prepare("SELECT id, fullname, email, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['otp_error'] = 'ไม่พบบัญชีผู้ใช้';
    header('Location: login.php');
    exit;
}

// ✅ Set session ให้ครบ รวม role
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['fullname'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role']  = $user['role']; // 'admin' หรือ 'user'

// admin ได้ admin_logged_in ด้วย เพื่อให้ admin_layout_top.php เช็คได้
if ($user['role'] === 'admin') {
    $_SESSION['admin_logged_in'] = true;
}

// ล้าง session OTP
unset($_SESSION['otp_email'], $_SESSION['otp_message'], $_SESSION['otp_error']);

// ✅ Redirect ตาม role
if ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
} else {
    header('Location: index.php');
}
exit;