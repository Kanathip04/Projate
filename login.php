<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
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
        input{
            width:100%;
            height:45px;
            margin:12px 0;
            padding:0 12px;
            border:1px solid #ccc;
            border-radius:10px;
            box-sizing:border-box;
        }
        button{ 
            width:100%;
            height:45px;
            border:none;
            border-radius:10px;
            background:#638411;
            color:#fff;
            font-size:16px;
            cursor:pointer;
        }
        button:hover{
            background:#4f6a0d;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>หน้า Login ใช้งานได้</h1>
        <p>ทดสอบสร้าง session login ก่อนเข้า Dashboard</p>

        <form action="check_login.php" method="post">
            <input type="password" name="password" placeholder="กรอกรหัสผ่าน" required>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>