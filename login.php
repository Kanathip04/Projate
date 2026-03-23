<?php
session_start();
// สร้างไฟล์ config.php ไว้เก็บรหัสผ่าน
if (!file_exists('config.php')) {
    file_put_contents('config.php', '<?php $admin_password = "1234"; ?>');
}
include 'config.php'; 

$error_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error_message = "รหัสผ่านไม่ถูกต้อง!";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบจัดการหลังบ้าน</title>
    <style>
        body { 
            font-family: 'Tahoma', sans-serif; 
            background: #0f1712; /* สีพื้นหลังธีมป่าลึก */
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .login-card { 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.5); 
            text-align: center; 
            width: 350px; 
        }
        .login-card h2 { 
            color: #1a1a1a; 
            margin-bottom: 25px; 
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        input[type="password"] { 
            width: 100%; 
            padding: 12px; 
            margin: 10px 0; 
            border: 1px solid #ddd; 
            border-radius: 10px; 
            box-sizing: border-box; 
            font-size: 16px;
            outline: none;
        }
        input[type="password"]:focus {
            border-color: #638411;
        }
        .btn-login { 
            background: #638411; 
            color: white; 
            border: none; 
            padding: 14px; 
            width: 100%; 
            border-radius: 10px; 
            cursor: pointer; 
            font-weight: bold; 
            font-size: 16px;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-login:hover { background: #4e690d; }
        .error { color: #e74c3c; font-size: 14px; margin-bottom: 15px; font-weight: bold; }
        .back-link { 
            display: block; 
            margin-top: 25px; 
            color: #666; 
            text-decoration: none; 
            font-size: 14px; 
        }
        .back-link:hover { color: #1a1a1a; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🔒 สำหรับเจ้าหน้าที่</h2>
        
        <?php if(!empty($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="password" name="password" placeholder="ใส่วันที่รหัสผ่าน..." required autocomplete="off">
            <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
        </form>

        <a href="index.php" class="back-link">← กลับหน้าหลัก</a>
    </div>
</body>
</html>