<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

if (!isset($conn) || !$conn) {
    die("❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
}

$message = "";
$pageTitle  = "Security Setting";
$activeMenu = "password";

/* =========================
   1) สร้างตาราง admin ถ้ายังไม่มี
========================= */
$createTableSQL = "CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createTableSQL)) {
    die("❌ สร้างตาราง admin ไม่สำเร็จ: " . $conn->error);
}

/* =========================
   2) ถ้ายังไม่มี user admin id=1 ให้สร้าง
   รหัสเริ่มต้น = 000000
========================= */
$checkAdmin = $conn->query("SELECT id FROM admin WHERE id = 1 LIMIT 1");
if (!$checkAdmin) {
    die("❌ ตรวจสอบข้อมูล admin ไม่สำเร็จ: " . $conn->error);
}

if ($checkAdmin->num_rows === 0) {
    $defaultPassword = password_hash('000000', PASSWORD_DEFAULT);
    $stmtInsert = $conn->prepare("INSERT INTO admin (id, username, password) VALUES (1, 'admin', ?)");
    $stmtInsert->bind_param("s", $defaultPassword);

    if (!$stmtInsert->execute()) {
        die("❌ เพิ่มข้อมูล admin เริ่มต้นไม่สำเร็จ: " . $stmtInsert->error);
    }

    $stmtInsert->close();
}

/* =========================
   3) เมื่อกด submit เปลี่ยนรหัสผ่าน
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $message = "<div class='alert alert-error'>❌ กรุณากรอกข้อมูลให้ครบทุกช่อง</div>";
    } elseif ($new !== $confirm) {
        $message = "<div class='alert alert-error'>❌ รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน</div>";
    } elseif (strlen($new) < 6) {
        $message = "<div class='alert alert-error'>❌ รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร</div>";
    } else {
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $stmt->bind_result($db_pass);
        $stmt->fetch();
        $stmt->close();

        if (!$db_pass) {
            $message = "<div class='alert alert-error'>❌ ไม่พบข้อมูลผู้ดูแลระบบ</div>";
        } elseif (!password_verify($current, $db_pass)) {
            $message = "<div class='alert alert-error'>❌ รหัสผ่านปัจจุบันไม่ถูกต้อง</div>";
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);

            $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = 1");
            $update->bind_param("s", $new_hash);

            if ($update->execute()) {
                $message = "<div class='alert alert-success'>✅ เปลี่ยนรหัสผ่านสำเร็จ</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ บันทึกไม่สำเร็จ: " . htmlspecialchars($update->error) . "</div>";
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

.security-card h1{
    margin:0 0 20px 0;
    font-size:22px;
}

.form-group{
    margin-bottom:18px;
}

.form-group label{
    display:block;
    margin-bottom:6px;
    font-weight:bold;
}

.input-wrap{
    position:relative;
    display:flex;
    align-items:center;
}

.input-wrap input{
    width:100%;
    padding:12px 44px 12px 12px;
    border-radius:8px;
    border:1px solid #ddd;
    font-size:14px;
}

.input-wrap input:focus{
    border-color:var(--brand);
    outline:none;
}

.toggle-eye{
    position:absolute;
    right:12px;
    background:none;
    border:none;
    cursor:pointer;
    padding:0;
    color:#999;
    font-size:18px;
    line-height:1;
    user-select:none;
}

.toggle-eye:hover{
    color:#555;
}

.btn-submit{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:var(--brand);
    color:#fff;
    font-weight:bold;
    cursor:pointer;
    transition:.2s;
}

.btn-submit:hover{
    background:var(--brand2);
}

.alert{
    padding:10px 12px;
    border-radius:8px;
    margin-bottom:15px;
    font-weight:bold;
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
    <h1>🔐 เปลี่ยนรหัสผ่าน</h1>

    <?php echo $message; ?>

    <form method="POST">
        <div class="form-group">
            <label for="current_password">รหัสผ่านปัจจุบัน</label>
            <div class="input-wrap">
                <input type="password" id="current_password" name="current_password" required>
                <button type="button" class="toggle-eye" onclick="togglePass('current_password', this)">👁️</button>
            </div>
        </div>

        <div class="form-group">
            <label for="new_password">รหัสผ่านใหม่</label>
            <div class="input-wrap">
                <input type="password" id="new_password" name="new_password" required>
                <button type="button" class="toggle-eye" onclick="togglePass('new_password', this)">👁️</button>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
            <div class="input-wrap">
                <input type="password" id="confirm_password" name="confirm_password" required>
                <button type="button" class="toggle-eye" onclick="togglePass('confirm_password', this)">👁️</button>
            </div>
        </div>

        <button type="submit" name="submit" class="btn-submit">
            บันทึกรหัสผ่านใหม่
        </button>
    </form>
</div>

<script>
function togglePass(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}
</script>

<?php
include "admin_layout_bottom.php";
$conn->close();
?>