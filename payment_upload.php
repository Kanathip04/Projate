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

/*
 * หมายเหตุ:
 * โค้ดนี้ใช้คอลัมน์ total_amount ในตาราง boat_bookings
 * ถ้าของคุณชื่อไม่ตรง ให้เปลี่ยน total_amount ตรง SQL ด้านล่าง
 */
$stmt = $conn->prepare("
    SELECT id, payment_status, total_amount, user_name, amount_tolerance,
           promptpay_id, created_at AS booking_created, expired_at, payment_channel
    FROM boat_bookings
    WHERE booking_ref = ?
    LIMIT 1
");
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$res = $stmt->get_result();
$booking = $res->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['ok' => false, 'message' => 'ไม่พบรายการจอง']);
    exit;
}

$booking_id       = (int)$booking['id'];
$total_amount     = (float)$booking['total_amount'];
$user_name        = $booking['user_name'] ?? '';
$amount_tolerance = (float)($booking['amount_tolerance'] ?? 1);
$promptpay_id     = $booking['promptpay_id'] ?? '';
$booking_created  = $booking['booking_created'] ?? '';
$expired_at       = $booking['expired_at'] ?? '';
$payment_channel  = $booking['payment_channel'] ?? 'promptpay';

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

/* =========================
   สร้าง URL สำหรับเรียก n8n
========================= */
$webhookUrl = 'https://kanayhip.app.n8n.cloud/webhook/boat-slip';

/* =========================
   สร้าง URL รูปสลิป + callback
========================= */
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

$scheme   = $isHttps ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? '141.98.18.11';
$baseUrl  = $scheme . '://' . $host . '/Projate';

$slip_url     = $baseUrl . '/' . $dbPath;
$callback_url = $baseUrl . '/payment_callback.php';

/* =========================
   ส่งข้อมูลไปหา n8n
========================= */
$slip_base64   = base64_encode(file_get_contents($targetPath));
$slip_mime_type = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
$slip_hash     = md5_file($targetPath);

$payload = [
    'secret'           => 'wrbri_n8n_secret_2026',
    'booking_ref'      => $booking_ref,
    'booking_id'       => $booking_id,
    'user_name'        => $user_name,
    'expected_amount'  => $total_amount,
    'amount_tolerance' => $amount_tolerance,
    'promptpay_id'     => $promptpay_id,
    'booking_created'  => $booking_created,
    'expired_at'       => $expired_at,
    'payment_channel'  => $payment_channel,
    'slip_hash'        => $slip_hash,
    'slip_url'         => $slip_url,
    'slip_base64'      => $slip_base64,
    'slip_mime_type'   => $slip_mime_type,
    'callback_url'     => $callback_url,
];

$n8nSent      = false;
$n8nResponse  = null;
$n8nHttpCode  = null;
$n8nCurlError = null;
file_put_contents(__DIR__ . '/check_secret_log.txt', json_encode($payload, JSON_UNESCAPED_UNICODE));
if (function_exists('curl_init')) {
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $n8nResponse  = curl_exec($ch);
    $n8nHttpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $n8nCurlError = curl_error($ch);
    curl_close($ch);

    $n8nSent = ($n8nHttpCode >= 200 && $n8nHttpCode < 300 && empty($n8nCurlError));
}

/* =========================
   เขียน log debug
========================= */
$logText  = "========== " . date('Y-m-d H:i:s') . " ==========\n";
$logText .= "booking_ref: " . $booking_ref . "\n";
$logText .= "booking_id: " . $booking_id . "\n";
$logText .= "total_amount: " . $total_amount . "\n";
$logText .= "slip_url: " . $slip_url . "\n";
$logText .= "callback_url: " . $callback_url . "\n";
$logText .= "n8n_http_code: " . ($n8nHttpCode ?? 'null') . "\n";
$logText .= "n8n_response: " . ($n8nResponse ?? 'null') . "\n";
$logText .= "n8n_error: " . ($n8nCurlError ?? 'null') . "\n\n";

file_put_contents(__DIR__ . '/n8n_debug.log', $logText, FILE_APPEND);

/* =========================
   ตอบกลับหน้าเว็บ
========================= */
echo json_encode([
    'ok'            => true,
    'message'       => 'อัปโหลดสลิปสำเร็จ',
    'file'          => $dbPath,
    'slip_url'      => $slip_url,
    'n8n_sent'      => $n8nSent,
    'n8n_http_code' => $n8nHttpCode,
    'n8n_error'     => $n8nCurlError
], JSON_UNESCAPED_UNICODE);