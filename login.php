<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ถ้าล็อกอินแล้วค่อยไปหน้า dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Test</title>
    <style>
        body{
            font-family:Arial,sans-serif;
            background:#f5f5f5;
            display:flex;
            justify-content:center;
            align-items:center;
            min-height:100vh;
            margin:0;
        }
        .box{
            background:#fff;
            padding:30px;
            border-radius:16px;
            box-shadow:0 10px 25px rgba(0,0,0,.12);
            width:100%;
            max-width:400px;
        }
        h2{
            margin:0 0 15px;
        }
        input{
            width:100%;
            height:45px;
            margin-bottom:12px;
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
        <h2>Login Test</h2>
        <form action="check_login.php" method="post">
            <input type="password" name="password" placeholder="กรอกรหัสผ่าน">
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>