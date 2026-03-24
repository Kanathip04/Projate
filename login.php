<?php
session_start();
include 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = trim($_POST['password'] ?? '');

    if ($password === "") {
        $error = "กรุณากรอกรหัสผ่าน";
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
            $error = "รหัสผ่านไม่ถูกต้อง";
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
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#f4f6f9;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}
.login-box{
    width:100%;
    max-width:420px;
    background:#fff;
    padding:30px;
    border-radius:16px;
    box-shadow:0 8px 24px rgba(0,0,0,.08);
}
h2{
    margin-top:0;
    text-align:center;
}
label{
    display:block;
    margin-bottom:8px;
    font-weight:600;
}
input{
    width:100%;
    padding:12px;
    border:1px solid #ddd;
    border-radius:10px;
    margin-bottom:16px;
}
button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    background:#6f9f13;
    color:#fff;
    font-weight:bold;
    cursor:pointer;
}
.error{
    background:#fde8e8;
    color:#b91c1c;
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
}
</style>
</head>
<body>
    <div class="login-box">
        <h2>🔐 Admin Login</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>