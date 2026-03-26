<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
</head>
<body>
    <h1>เข้าสู่ระบบสำเร็จ</h1>
    <p>Session admin_logged_in = <?php var_dump($_SESSION['admin_logged_in']); ?></p>
    <p><a href="logout.php">ออกจากระบบ</a></p>
</body>
</html>