<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "config.php";

if (!isset($conn) || !$conn) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้");
}

$createTableSQL = "CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createTableSQL)) {
    die("สร้างตารางไม่สำเร็จ: " . $conn->error);
}

$newHash = password_hash("000000", PASSWORD_DEFAULT);

// ถ้ามี admin อยู่แล้ว ให้อัปเดตรหัส
$check = $conn->prepare("SELECT id FROM `admin` WHERE username = ? LIMIT 1");
$username = "admin";
$check->bind_param("s", $username);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $update = $conn->prepare("UPDATE `admin` SET password = ? WHERE username = ?");
    $update->bind_param("ss", $newHash, $username);

    if ($update->execute()) {
        echo "รีเซ็ตรหัสผ่าน admin เป็น 000000 สำเร็จ";
    } else {
        echo "อัปเดตรหัสไม่สำเร็จ: " . $update->error;
    }

    $update->close();
} else {
    $insert = $conn->prepare("INSERT INTO `admin` (username, password) VALUES (?, ?)");
    $insert->bind_param("ss", $username, $newHash);

    if ($insert->execute()) {
        echo "สร้าง admin และตั้งรหัสเป็น 000000 สำเร็จ";
    } else {
        echo "เพิ่ม admin ไม่สำเร็จ: " . $insert->error;
    }

    $insert->close();
}

$check->close();
$conn->close();
?>