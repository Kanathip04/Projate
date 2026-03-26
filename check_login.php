<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$admin_password = "000000"; // เปลี่ยนรหัสได้ตรงนี้
$password = $_POST['password'] ?? '';

if ($password === $admin_password) {
    $_SESSION['admin_logged_in'] = true;
    header("Location: admin_dashboard.php");
    exit;
} else {
    echo "<h2>รหัสผ่านไม่ถูกต้อง</h2>";
    echo "<a href='login.php'>กลับไปหน้า Login</a>";
    exit;
}