<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once 'config.php';

$email = $_SESSION['otp_email'] ?? '';
$otp = trim($_POST['otp'] ?? '');

if (empty($email)) {
    $_SESSION['otp_error'] = 'กรุณาเริ่มเข้าสู่ระบบใหม่อีกครั้ง';
    header('Location: login.php');
    exit;
}

if (empty($otp)) {
    $_SESSION['otp_error'] = 'กรุณากรอกรหัส OTP';
    header('Location: otp_verify.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT id, otp_code, expires_at, is_used
    FROM email_otps
    WHERE email = ? AND otp_code = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['otp_error'] = 'OTP ไม่ถูกต้อง';
    header('Location: otp_verify.php');
    exit;
}

if ((int)$row['is_used'] === 1) {
    $_SESSION['otp_error'] = 'OTP นี้ถูกใช้งานแล้ว';
    header('Location: otp_verify.php');
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    $_SESSION['otp_error'] = 'OTP หมดอายุแล้ว กรุณาขอใหม่';
    header('Location: otp_verify.php');
    exit;
}

$stmt = $conn->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
$stmt->bind_param("i", $row['id']);
$stmt->execute();
$stmt->close();

$_SESSION['otp_verified'] = true;
$_SESSION['otp_message'] = 'ยืนยันอีเมลสำเร็จ กรุณาใส่รหัสผ่านเพื่อเข้าสู่ระบบ';

header('Location: password_login.php');
exit;