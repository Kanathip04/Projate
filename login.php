<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ล้าง session ชั่วคราวเพื่อหยุด loop
unset($_SESSION['admin_logged_in']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Debug</title>
    <style>
        body{
            font-family:Arial,sans-serif;
            background:#f5f5f5;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0;
        }
        .box{
            background:#fff;
            width:100%;
            max-width:420px;
            padding:30px;
            border-radius:16px;
            box-shadow:0 10px 25px rgba(0,0,0,.12);
            text-align:center;
        }
        h1{margin:0 0 10px;}
        p{color:#555; line-height:1.6;}
        a{
            display:inline-block;
            margin-top:15px;
            padding:12px 18px;
            background:#638411;
            color:#fff;
            text-decoration:none;
            border-radius:10px;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>หน้า Login ใช้งานได้</h1>
        <p>ถ้าคุณเห็นหน้านี้ แปลว่าไฟล์ login.php เองไม่พังแล้ว</p>
        <a href="admin_dashboard.php">ไปหน้า Dashboard</a>
    </div>
</body>
</html>