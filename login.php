<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$admin_password = "000000"; // เปลี่ยนเป็นรหัสที่คุณต้องการ

$password = $_POST['password'] ?? '';

if ($password === $admin_password) {
    $_SESSION['admin_logged_in'] = true;
    header("Location: admin_dashboard.php");
    exit;
} else {
    $_SESSION['login_message'] = "รหัสผ่านไม่ถูกต้อง";
    header("Location: login.php");
    exit;
}