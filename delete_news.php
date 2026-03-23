<?php
include 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_news.php");
    exit;
}

$id = (int)$_GET['id'];

/* ดึงชื่อรูปมาก่อน เพื่อลบไฟล์รูปออกจากโฟลเดอร์ด้วย */
$stmt = $conn->prepare("SELECT image FROM news WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: manage_news.php");
    exit;
}

$row = $result->fetch_assoc();
$imageName = $row["image"] ?? "";
$stmt->close();

/* ลบข่าวจากฐานข้อมูล */
$stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    /* ถ้ามีรูป ให้ลบไฟล์รูปด้วย */
    if (!empty($imageName)) {
        $filePath = "uploads/" . $imageName;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}

$stmt->close();
header("Location: manage_news.php");
exit;
?>