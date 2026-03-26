<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['otp_error'] = 'กรุณากรอกอีเมลให้ถูกต้อง';
    header('Location: login.php');
    exit;
}

// ตรวจสอบว่ามีอีเมลนี้ในระบบหรือไม่
$stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['otp_error'] = 'ไม่พบบัญชีอีเมลนี้ในระบบ';
    header('Location: login.php');
    exit;
}

// สร้างตาราง otp ถ้ายังไม่มี
$conn->query("
CREATE TABLE IF NOT EXISTS email_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ลบ OTP เก่าของอีเมลนี้
$stmt = $conn->prepare("DELETE FROM email_otps WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->close();

// สร้าง OTP 6 หลัก
$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', time() + 300); // 5 นาที

$stmt = $conn->prepare("INSERT INTO email_otps (email, otp_code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $otp, $expires_at);
$stmt->execute();
$stmt->close();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'yourprojectotp@gmail.com'; // เปลี่ยนเป็นอีเมลจริงของคุณ
    $mail->Password   = 'xxxx xxxx xxxx xxxx';      // เปลี่ยนเป็น App Password 16 หลัก
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom('yourprojectotp@gmail.com', 'OTP Verification');
    $mail->addAddress($email, $user['fullname']);

    $mail->isHTML(true);
    $mail->Subject = 'รหัส OTP สำหรับยืนยันการเข้าสู่ระบบ';
    $mail->Body = "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:14px'>
            <h2 style='margin:0 0 12px;color:#111827'>ยืนยันอีเมลของคุณ</h2>
            <p style='color:#374151;font-size:15px'>รหัส OTP ของคุณคือ</p>
            <div style='font-size:32px;font-weight:700;letter-spacing:6px;color:#2563eb;margin:18px 0'>
                {$otp}
            </div>
            <p style='color:#6b7280;font-size:14px'>รหัสนี้มีอายุ 5 นาที</p>
        </div>
    ";

    $mail->send();

    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_message'] = 'ระบบส่ง OTP ไปยังอีเมลของคุณแล้ว';

    header('Location: otp_verify.php');
    exit;

} catch (Exception $e) {
    $_SESSION['otp_error'] = 'ส่งอีเมลไม่สำเร็จ: ' . $mail->ErrorInfo;
    header('Location: login.php');
    exit;
}