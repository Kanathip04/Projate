<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config.php';

$newPlainPassword = '000000';
$newHash = password_hash($newPlainPassword, PASSWORD_DEFAULT);

echo "<h2>Reset Admin Password</h2>";
echo "New hash: " . htmlspecialchars($newHash) . "<br><br>";

$check = $conn->query("SELECT id FROM admin WHERE id = 1 LIMIT 1");

if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = 1");
    $stmt->bind_param("s", $newHash);

    if ($stmt->execute()) {
        echo "อัปเดตรหัสผ่านสำเร็จ<br>";
    } else {
        echo "อัปเดตไม่สำเร็จ: " . htmlspecialchars($stmt->error);
    }

    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO admin (id, password) VALUES (1, ?)");
    $stmt->bind_param("s", $newHash);

    if ($stmt->execute()) {
        echo "สร้าง admin id=1 สำเร็จ<br>";
    } else {
        echo "เพิ่มข้อมูลไม่สำเร็จ: " . htmlspecialchars($stmt->error);
    }

    $stmt->close();
}

$res = $conn->query("SELECT id, password FROM admin WHERE id = 1 LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;

echo "<br><strong>ตรวจสอบ:</strong><br>";
echo "ID: " . htmlspecialchars($row['id'] ?? '-') . "<br>";
echo "verify 000000: " . (isset($row['password']) && password_verify('000000', $row['password']) ? 'ถูก' : 'ผิด') . "<br>";

$conn->close();
?>