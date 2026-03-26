<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Bangkok');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('กรุณาเข้าหน้านี้ผ่านฟอร์ม login เท่านั้น');
}

require_once 'config.php';

require __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['otp_error'] = 'กรุณากรอกอีเมลให้ถูกต้อง';
    header('Location: login.php');
    exit;
}

if (!isset($conn) || !$conn) {
    die('ไม่พบตัวแปร $conn จาก config.php');
}

$stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    die('Prepare users failed: ' . $conn->error);
}

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

$sql = "
CREATE TABLE IF NOT EXISTS email_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($sql)) {
    die('สร้างตาราง email_otps ไม่สำเร็จ: ' . $conn->error);
}

$stmt = $conn->prepare("DELETE FROM email_otps WHERE email = ?");
if (!$stmt) {
    die('Prepare delete OTP failed: ' . $conn->error);
}
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->close();

$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', time() + 300);

$stmt = $conn->prepare("INSERT INTO email_otps (email, otp_code, expires_at) VALUES (?, ?, ?)");
if (!$stmt) {
    die('Prepare insert OTP failed: ' . $conn->error);
}
$stmt->bind_param("sss", $email, $otp, $expires_at);
$stmt->execute();
$stmt->close();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = '67010974003@msu.ac.th';
    $mail->Password   = 'ใส่AppPasswordใหม่ของคุณ';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom('67010974003@msu.ac.th', 'OTP Verification');
    $mail->addAddress($email, $user['fullname'] ?? '');

    $mail->isHTML(true);
    $mail->Subject = 'รหัส OTP สำหรับยืนยันการเข้าสู่ระบบ';
    $mail->Body = "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:14px'>
            <h2 style='margin:0 0 12px;color:#111827'>ยืนยันอีเมลของคุณ</h2>
            <p style='color:#374151;font-size:15px'>รหัส OTP ของคุณคือ</p>
            <div style='font-size:32px;font-weight:700;letter-spacing:6px;color:#2563eb;margin:18px 0'>{$otp}</div>
            <p style='color:#6b7280;font-size:14px'>รหัสนี้มีอายุ 5 นาที</p>
        </div>
    ";

    $mail->send();

    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_message'] = 'ส่ง OTP ไปยังอีเมลเรียบร้อยแล้ว';
    header('Location: otp_verify.php');
    exit;

} catch (Exception $e) {
    die('ส่งอีเมลไม่สำเร็จ: ' . $mail->ErrorInfo);
}