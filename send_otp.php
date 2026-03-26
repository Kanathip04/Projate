<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ❌ ลบบรรทัดนี้ออก
// header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("อีเมลไม่ถูกต้อง");
}

// ====== สร้าง OTP ======
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', time() + 300);

// ====== บันทึก DB ======
$conn->query("CREATE TABLE IF NOT EXISTS email_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255),
    otp_code VARCHAR(10),
    expires_at DATETIME,
    is_used TINYINT(1) DEFAULT 0
)");

$conn->query("DELETE FROM email_otps WHERE email='$email'");
$conn->query("INSERT INTO email_otps (email, otp_code, expires_at) VALUES ('$email','$otp','$expires_at')");

// ====== ส่งเมล ======
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'yourprojectotp@gmail.com';
    $mail->Password = 'xxxx xxxx xxxx xxxx';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('yourprojectotp@gmail.com', 'OTP System');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'OTP ของคุณ';
    $mail->Body = "OTP ของคุณคือ: <b>$otp</b>";

    $mail->send();

    // ====== สำคัญ: redirect ไปหน้า OTP ======
    header("Location: otp_page.php?email=" . urlencode($email));
    exit;

} catch (Exception $e) {
    echo "ส่งเมลไม่สำเร็จ: " . $mail->ErrorInfo;
}