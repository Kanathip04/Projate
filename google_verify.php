<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['login_message'] = 'วิธีเข้าใช้งานไม่ถูกต้อง';
    header('Location: login.php');
    exit;
}

$credential = $_POST['credential'] ?? '';

if (empty($credential)) {
    $_SESSION['login_message'] = 'ไม่พบข้อมูลจาก Google';
    header('Location: login.php');
    exit;
}

/*
 |------------------------------------------------------------
 | ตรงนี้เป็นเวอร์ชันทดสอบก่อน
 | ยังไม่ได้ verify token กับ Google จริง
 | แค่รับ credential มาแล้วถือว่าล็อกอินผ่านชั่วคราว
 |------------------------------------------------------------
*/

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_name'] = 'Google User';
$_SESSION['admin_email'] = 'google-login-test@local';

header('Location: admin_dashboard.php');
exit;