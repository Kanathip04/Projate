<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php'; // ต้องมี $conn = new mysqli(...)

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

$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt, true)) {
    echo json_encode(['ok' => false, 'message' => 'อนุญาตเฉพาะไฟล์ jpg, jpeg, png, webp']);
    exit;
}

if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'message' => 'ไฟล์ต้องไม่เกิน 5MB']);
    exit;
}

$stmt = $conn->prepare("SELECT id, booking_ref, payment_status FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['ok' => false, 'message' => 'ไม่พบรายการจอง']);
    exit;
}

$newFileName = 'slip_' . $booking_ref . '_' . time() . '.' . $ext;
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
    $update->close();
    echo json_encode(['ok' => false, 'message' => 'บันทึกข้อมูลสลิปไม่สำเร็จ: ' . $conn->error]);
    exit;
}
$update->close();

echo json_encode([
    'ok' => true,
    'message' => 'อัปโหลดสลิปสำเร็จ',
    'file' => $dbPath
]);