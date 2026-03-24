<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    die("ยังไม่ได้ login");
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("DB ERROR: " . $conn->connect_error);
}

echo "<div style='background:#222;color:#0f0;padding:10px;margin:10px 0;'>CHANGE PASSWORD DEBUG VERSION</div>";

$stmt = $conn->prepare("SELECT username, password FROM admin WHERE username = ? LIMIT 1");
$username = "admin";
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($dbUser, $dbPass);
$stmt->fetch();
$stmt->close();

echo "พบ user: " . htmlspecialchars($dbUser ?: 'ไม่พบ') . "<br>";
echo "hash ใน DB: " . htmlspecialchars($dbPass ?: 'ไม่มี') . "<br>";
echo "ทดสอบ verify 000000: " . (password_verify("000000", $dbPass) ? "ถูก" : "ผิด") . "<hr>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    echo "current ที่กรอก: " . htmlspecialchars($current) . "<br>";

    if (!password_verify($current, $dbPass)) {
        echo "<div style='color:red;font-weight:bold;'>รหัสผ่านปัจจุบันไม่ถูกต้อง</div>";
    } elseif ($new !== $confirm) {
        echo "<div style='color:red;font-weight:bold;'>รหัสใหม่ไม่ตรงกัน</div>";
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE admin SET password = ? WHERE username = ?");
        $upd->bind_param("ss", $newHash, $username);
        if ($upd->execute()) {
            echo "<div style='color:green;font-weight:bold;'>เปลี่ยนรหัสผ่านสำเร็จ</div>";
        } else {
            echo "UPDATE ERROR: " . $upd->error;
        }
        $upd->close();
    }
}
?>

<form method="post">
    <input type="password" name="current_password" placeholder="รหัสปัจจุบัน"><br><br>
    <input type="password" name="new_password" placeholder="รหัสใหม่"><br><br>
    <input type="password" name="confirm_password" placeholder="ยืนยันรหัสใหม่"><br><br>
    <button type="submit">บันทึก</button>
</form>