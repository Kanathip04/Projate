<?php
session_start();
include 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = trim($_POST['password'] ?? '');

    if ($password === '') {
        $error = 'กรุณากรอกรหัสผ่าน';
    } else {
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $stmt->bind_result($db_pass);
        $stmt->fetch();
        $stmt->close();

        if ($db_pass && password_verify($password, $db_pass)) {
            $_SESSION['admin_logged_in'] = true;
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $error = 'รหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<style>
body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#f4f6f9;
    font-family:'Segoe UI', Tahoma, sans-serif;
}
.login-card{
    width:100%;
    max-width:420px;
    background:#fff;
    padding:30px;
    border-radius:18px;
    box-shadow:0 8px 24px rgba(0,0,0,.08);
}
.login-card h2{
    margin:0 0 20px;
    text-align:center;
}
.form-group{
    margin-bottom:16px;
}
.form-group label{
    display:block;
    margin-bottom:8px;
    font-weight:600;
}
.form-group input{
    width:100%;
    padding:12px 14px;
    border:1px solid #dcdcdc;
    border-radius:10px;
    font-size:15px;
}
.btn-login{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    background:#6f9f13;
    color:#fff;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
}
.error{
    margin-bottom:15px;
    padding:10px 12px;
    border-radius:10px;
    background:#fde8e8;
    color:#b91c1c;
    font-weight:600;
}
</style>
</head>
<body>
    <div class="login-card">
        <h2>🔐 Admin Login</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>