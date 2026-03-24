<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("DB ERROR: " . $conn->connect_error);
}

echo "<h2>กำลังรีเซ็ตรหัส admin...</h2>";

$sql1 = "CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($sql1)) {
    die("CREATE TABLE ERROR: " . $conn->error);
}

$newHash = password_hash("000000", PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id, username FROM admin WHERE username = ? LIMIT 1");
$username = "admin";
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $upd = $conn->prepare("UPDATE admin SET password = ? WHERE username = ?");
    $upd->bind_param("ss", $newHash, $username);

    if ($upd->execute()) {
        echo "รีเซ็ตรหัสผ่าน admin เป็น 000000 สำเร็จ";
    } else {
        echo "UPDATE ERROR: " . $upd->error;
    }
    $upd->close();
} else {
    $ins = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
    $ins->bind_param("ss", $username, $newHash);

    if ($ins->execute()) {
        echo "สร้าง admin ใหม่ และตั้งรหัสเป็น 000000 สำเร็จ";
    } else {
        echo "INSERT ERROR: " . $ins->error;
    }
    $ins->close();
}

$stmt->close();

echo "<hr>";
$res = $conn->query("SELECT id, username FROM admin");
if ($res) {
    echo "<h3>ข้อมูลในตาราง admin ตอนนี้</h3>";
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | USERNAME: " . htmlspecialchars($row['username']) . "<br>";
    }
}

$conn->close();
?>