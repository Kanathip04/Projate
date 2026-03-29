<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'message' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$booking_ref = trim($_POST['booking_ref'] ?? '');

if ($booking_ref === '') {
    echo json_encode(['ok' => false, 'message' => 'ไม่พบเลขอ้างอิงการจอง']);
    exit;
}

if (!isset($_FILES['payment_slip']) || $_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'message' => 'กรุณาอัปโหลดรูปสลิป']);
    exit;
}

$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
$uploadDir  = __DIR__ . '/uploads/payment_slips/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$originalName = $_FILES['payment_slip']['name'];
$tmpName      = $_FILES['payment_slip']['tmp_name'];
$fileSize     = $_FILES['payment_slip']['size'];
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt, true)) {
    echo json_encode(['ok' => false, 'message' => 'อนุญาตเฉพาะไฟล์ jpg, jpeg, png, webp']);
    exit;
}

if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'message' => 'ไฟล์ต้องไม่เกิน 5MB']);
    exit;
}

$stmt = $conn->prepare("SELECT id, payment_status FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$res = $stmt->get_result();
$booking = $res->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['ok' => false, 'message' => 'ไม่พบรายการจอง']);
    exit;
}

$newFileName = 'slip_' . preg_replace('/[^A-Za-z0-9_-]/', '', $booking_ref) . '_' . time() . '.' . $ext;
$targetPath  = $uploadDir . $newFileName;
$dbPath      = 'uploads/payment_slips/' . $newFileName;

if (!move_uploaded_file($tmpName, $targetPath)) {
    echo json_encode(['ok' => false, 'message' => 'อัปโหลดไฟล์ไม่สำเร็จ']);
    exit;
}

$update = $conn->prepare("
    UPDATE boat_bookings
    SET payment_slip = ?,
        payment_status = CASE
            WHEN payment_status = 'paid' THEN 'paid'
            ELSE 'waiting_verify'
        END
    WHERE booking_ref = ?
");
$update->bind_param("ss", $dbPath, $booking_ref);

if (!$update->execute()) {
    echo json_encode(['ok' => false, 'message' => 'บันทึกสลิปไม่สำเร็จ: ' . $update->error]);
    $update->close();
    exit;
}
$update->close();

echo json_encode([
    'ok' => true,
    'message' => 'อัปโหลดสลิปสำเร็จ',
    'file' => $dbPath
]);