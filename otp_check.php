<?php
session_start();
require_once 'config.php';

$otp   = trim($_POST['otp'] ?? '');
$email = $_SESSION['otp_email'] ?? '';

if (empty($email)) {
    header('Location: login.php'); exit;
}
if (empty($otp)) {
    $_SESSION['otp_error'] = 'กรุณากรอกรหัส OTP';
    header('Location: otp_verify.php'); exit;
}

$stmt = $conn->prepare("
    SELECT * FROM email_otps
    WHERE email = ? AND otp_code = ? AND is_used = 0 AND expires_at >= NOW()
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$otpRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$otpRow) {
    $_SESSION['otp_error'] = 'รหัส OTP ไม่ถูกต้องหรือหมดอายุ';
    header('Location: otp_verify.php'); exit;
}

// Mark used
$stmt = $conn->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
$stmt->bind_param("i", $otpRow['id']); $stmt->execute(); $stmt->close();

// ยืนยันอีเมล
$stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
$stmt->bind_param("s", $email); $stmt->execute(); $stmt->close();

// ล้าง session OTP
unset($_SESSION['otp_email'], $_SESSION['otp_register'], $_SESSION['otp_error']);

// ✅ ไป login พร้อมข้อความแจ้งเตือน
$_SESSION['login_success'] = 'ยืนยันอีเมลเรียบร้อยแล้ว 🎉 กรุณาเข้าสู่ระบบด้วยอีเมลและรหัสผ่านของคุณ';
header('Location: login.php');
exit;