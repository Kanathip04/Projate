<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $mail->Username   = 'kanathip4123@gmail.com';
    $mail->Password   = 'uzotcwasteiaaroz';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->SMTPDebug  = 2;
    $mail->Debugoutput = 'html';

    $mail->CharSet = 'UTF-8';
    $mail->setFrom('67010974003@msu.ac.th', 'Mail Test');
    $mail->addAddress('67010974003@msu.ac.th');

    $mail->isHTML(true);
    $mail->Subject = 'PHPMailer Test';
    $mail->Body    = 'ส่งเมลทดสอบสำเร็จ';

    $mail->send();
    echo 'ส่งเมลสำเร็จ';
} catch (Exception $e) {
    echo 'ส่งเมลไม่สำเร็จ: ' . $mail->ErrorInfo;
}