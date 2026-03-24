<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$message = "";
$activeMenu = "password";

/* =========================
   เปลี่ยนรหัสผ่าน
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $message = "<div class='alert alert-error'>❌ กรุณากรอกข้อมูลให้ครบ</div>";
    } elseif ($new !== $confirm) {
        $message = "<div class='alert alert-error'>❌ รหัสใหม่ไม่ตรงกัน</div>";
    } elseif (strlen($new) < 6) {
        $message = "<div class='alert alert-error'>❌ รหัสต้องอย่างน้อย 6 ตัว</div>";
    } else {

        $stmt = $conn->prepare("SELECT password FROM admin WHERE username = ? LIMIT 1");
        $username = "admin";
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($db_pass);
        $stmt->fetch();
        $stmt->close();

        if (!$db_pass || !password_verify($current, $db_pass)) {
            $message = "<div class='alert alert-error'>❌ รหัสผ่านปัจจุบันไม่ถูกต้อง</div>";
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);

            $update = $conn->prepare("UPDATE admin SET password = ? WHERE username = ?");
            $update->bind_param("ss", $new_hash, $username);

            if ($update->execute()) {
                $message = "<div class='alert alert-success'>✅ เปลี่ยนรหัสผ่านสำเร็จ</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ บันทึกไม่สำเร็จ</div>";
            }

            $update->close();
        }
    }
}

include "admin_layout_top.php";
?>

<style>
.security-card{
    background:#fff;
    max-width:600px;
    padding:30px;
    border-radius:14px;
    box-shadow:0 4px 12px rgba(0,0,0,.08);
}
.form-group{
    margin-bottom:18px;
}
.form-group label{
    font-weight:bold;
}
.input-wrap{
    position:relative;
}
.input-wrap input{
    width:100%;
    padding:12px;
    border-radius:8px;
    border:1px solid #ddd;
}
.btn-submit{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:var(--brand);
    color:#fff;
    font-weight:bold;
}
.alert{
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
}
.alert-success{
    background:#e6f7ed;
    color:#0a7c3e;
}
.alert-error{
    background:#fde8e8;
    color:#b91c1c;
}
</style>

<div class="security-card">
    <h2>🔐 เปลี่ยนรหัสผ่าน</h2>

    <?php echo $message; ?>

    <form method="POST">
        <div class="form-group">
            <label>รหัสผ่านปัจจุบัน</label>
            <input type="password" name="current_password" required>
        </div>

        <div class="form-group">
            <label>รหัสผ่านใหม่</label>
            <input type="password" name="new_password" required>
        </div>

        <div class="form-group">
            <label>ยืนยันรหัสผ่านใหม่</label>
            <input type="password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn-submit">
            บันทึกรหัสผ่านใหม่
        </button>
    </form>
</div>

<?php
include "admin_layout_bottom.php";
$conn->close();
?>