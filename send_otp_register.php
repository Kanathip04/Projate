<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ต้องมาจากการสมัครเท่านั้น
if (empty($_SESSION['otp_email']) || empty($_SESSION['otp_register'])) {
    header('Location: register.php'); exit;
}

require_once 'config.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = $_SESSION['otp_email'];

// ดึงชื่อ user จาก session (ยังไม่ได้บันทึก DB)
if (!empty($_SESSION['pending_register']['fullname'])) {
    $user = ['fullname' => $_SESSION['pending_register']['fullname']];
} else {
    // กรณี OTP login ปกติ (ไม่ใช่สมัครใหม่)
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        header('Location: register.php'); exit;
    }
}

// สร้าง OTP table ถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS email_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ลบ OTP เก่า
$stmt = $conn->prepare("DELETE FROM email_otps WHERE email = ?");
$stmt->bind_param("s", $email); $stmt->execute(); $stmt->close();

// สร้าง OTP ใหม่
$otp        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', time() + 300);

$stmt = $conn->prepare("INSERT INTO email_otps (email, otp_code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $otp, $expires_at);
$stmt->execute(); $stmt->close();

// ส่งอีเมล
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'kanathip4123@gmail.com';
    $mail->Password   = 'puevhxejwcyxqpdx';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('kanathip4123@gmail.com', 'Lumière System');
    $mail->addAddress($email, $user['fullname']);
    $mail->isHTML(true);
    $mail->Subject = 'ยืนยันอีเมลสำหรับการสมัครสมาชิก';
    $mail->Body    = "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:auto;padding:32px;border:1px solid #e5e7eb;border-radius:14px'>
            <div style='font-size:1.4rem;font-weight:700;color:#1a1a2e;margin-bottom:8px;font-style:italic;'>Lumière</div>
            <hr style='border:none;border-top:1px solid #e5e7eb;margin:16px 0'>
            <h2 style='margin:0 0 12px;color:#111827;font-size:1.2rem;'>ยืนยันอีเมลของคุณ</h2>
            <p style='color:#374151;font-size:15px;margin-bottom:20px;'>สวัสดีคุณ <strong>{$user['fullname']}</strong> กรุณาใช้รหัส OTP ด้านล่างเพื่อยืนยันอีเมลของคุณ</p>
            <div style='font-size:36px;font-weight:800;letter-spacing:10px;color:#1a1a2e;background:#f5f1eb;padding:20px;text-align:center;border-radius:10px;margin:20px 0'>{$otp}</div>
            <p style='color:#6b7280;font-size:13px;'>รหัสนี้มีอายุ <strong>5 นาที</strong> และใช้ได้ครั้งเดียวเท่านั้น</p>
            <p style='color:#9ca3af;font-size:12px;margin-top:16px;'>หากคุณไม่ได้สมัครสมาชิก กรุณาเพิกเฉยต่ออีเมลนี้</p>
        </div>
    ";
    $mail->send();
    header('Location: otp_verify.php');
    exit;
} catch (Exception $e) {
    die('ส่งอีเมลไม่สำเร็จ: ' . $mail->ErrorInfo);
}