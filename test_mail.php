<?php
require __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = '67010974003@msu.ac.th';
    $mail->Password   = 'uzotcwasteiaaroz';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('67010974003@msu.ac.th', 'Test');
    $mail->addAddress('adminrukkhawet@gmail.com');
    $mail->Subject = 'Test Mail';
    $mail->Body    = 'ทดสอบส่งอีเมล';
    $mail->send();
    echo 'ส่งสำเร็จ ✅';
} catch (Exception $e) {
    echo 'Error: ' . $mail->ErrorInfo;
}