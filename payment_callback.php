<?php
/**
 * payment_callback.php
 * n8n จะ POST มาที่นี่หลังจากตรวจสอบสลิปเสร็จ
 *
 * Expected JSON body from n8n:
 * {
 *   "booking_ref": "BK20260329000001",
 *   "verified": true,
 *   "amount_matched": true,
 *   "slip_amount": 20.00,
 *   "slip_date": "2026-03-29",
 *   "provider_txn_id": "ABC123456",
 *   "secret": "wrbri_n8n_secret_2026",
 *   "reject_reason": "" // เมื่อ verified=false
 * }
 */
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json; charset=utf-8');

// ─── Security: shared secret ───
define('CALLBACK_SECRET', 'wrbri_n8n_secret_2026'); // ต้องตรงกับที่ตั้งใน n8n

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// ตรวจสอบ secret
if (($data['secret'] ?? '') !== CALLBACK_SECRET) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$booking_ref   = trim($data['booking_ref']   ?? '');
$verified      = (bool)($data['verified']    ?? false);
$amountMatched = (bool)($data['amount_matched'] ?? false);
$slipAmount    = (float)($data['slip_amount'] ?? 0);
$txnId         = trim($data['provider_txn_id'] ?? '');
$rejectReason  = trim($data['reject_reason']  ?? '');

if (!$booking_ref) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'booking_ref required']);
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB Error']);
    exit;
}

// ดึง booking
$stmt = $conn->prepare("SELECT * FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Booking not found']);
    exit;
}

// บันทึก webhook payload
$conn->query(
    "UPDATE boat_bookings SET webhook_payload = '" . $conn->real_escape_string($rawBody ?? '') . "'" .
    " WHERE booking_ref = '" . $conn->real_escape_string($booking_ref) . "'"
);

if ($verified && $amountMatched) {
    // ─── ชำระสำเร็จ: คำนวณเลขคิว + อนุมัติ ───
    $today = date('Y-m-d');
    $cntRes = $conn->query(
        "SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(approved_at) = '$today' AND booking_status = 'approved'"
    );
    $queueNo = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;

    $upStmt = $conn->prepare(
        "UPDATE boat_bookings
         SET payment_status  = 'paid',
             booking_status  = 'approved',
             daily_queue_no  = ?,
             provider_txn_id = ?,
             paid_at         = NOW(),
             approved_at     = NOW()
         WHERE booking_ref = ?"
    );
    $upStmt->bind_param("iss", $queueNo, $txnId, $booking_ref);
    $upStmt->execute();
    $upStmt->close();

    $queueLabel = 'Q' . str_pad($queueNo, 4, '0', STR_PAD_LEFT);
    echo json_encode([
        'ok'          => true,
        'action'      => 'approved',
        'queue_label' => $queueLabel,
        'booking_ref' => $booking_ref,
        'ticket_url'  => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/queue_ticket.php?ref=' . urlencode($booking_ref),
    ]);

} else {
    // ─── สลิปไม่ผ่าน: ให้ลูกค้าส่งใหม่ ───
    $upStmt = $conn->prepare(
        "UPDATE boat_bookings SET payment_status = 'failed', note = CONCAT(COALESCE(note,''), ?) WHERE booking_ref = ?"
    );
    $failNote = "\n[AUTO] สลิปไม่ผ่าน: " . ($rejectReason ?: "ยอดไม่ตรง (สลิป ฿$slipAmount / ต้องจ่าย ฿{$booking['total_amount']})") . " [" . date('Y-m-d H:i') . "]";
    $upStmt->bind_param("ss", $failNote, $booking_ref);
    $upStmt->execute();
    $upStmt->close();

    echo json_encode([
        'ok'           => false,
        'action'       => 'rejected',
        'booking_ref'  => $booking_ref,
        'reject_reason'=> $rejectReason ?: "ยอดเงินไม่ตรง (สลิป: ฿$slipAmount, ต้องจ่าย: ฿{$booking['total_amount']})",
    ]);
}

$conn->close();
exit;
