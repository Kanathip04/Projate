<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

/*
ตัวอย่าง payload ที่คาดหวัง:
{
  "event": "payment.success",
  "booking_ref": "BK202603290001",
  "transaction_id": "TXN123456789",
  "amount": 200.00,
  "status": "successful",
  "provider": "your_provider_name"
}
*/

$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['ok' => false, 'message' => 'Empty payload']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

/*
ส่วนนี้ต้องเพิ่มจริงเมื่อใช้ provider จริง:
1) ตรวจ signature
2) ตรวจ source IP / secret
3) เช็ก event type
*/

$booking_ref    = trim($data['booking_ref'] ?? '');
$transaction_id = trim($data['transaction_id'] ?? '');
$amount         = (float)($data['amount'] ?? 0);
$status         = strtolower(trim($data['status'] ?? ''));
$provider       = trim($data['provider'] ?? 'custom');
$event          = trim($data['event'] ?? '');

if ($booking_ref === '' || $transaction_id === '' || $status === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, booking_ref, total_amount, payment_status, booking_status, provider_txn_id
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
    echo json_encode(['ok' => false, 'message' => 'Booking not found']);
    exit;
}

if (!empty($booking['provider_txn_id']) && $booking['provider_txn_id'] === $transaction_id) {
    echo json_encode(['ok' => true, 'message' => 'Already processed']);
    exit;
}

if ((float)$booking['total_amount'] > 0 && abs((float)$booking['total_amount'] - $amount) > 0.01) {
    $payloadText = $raw;
    $fail = $conn->prepare("
        UPDATE boat_bookings
        SET payment_status = 'failed',
            payment_provider = ?,
            provider_txn_id = ?,
            webhook_payload = ?
        WHERE booking_ref = ?
    ");
    $fail->bind_param("ssss", $provider, $transaction_id, $payloadText, $booking_ref);
    $fail->execute();
    $fail->close();

    echo json_encode(['ok' => false, 'message' => 'Amount mismatch']);
    exit;
}

$payloadText = $raw;

if ($status === 'successful' || $status === 'success' || $status === 'paid') {
    $upd = $conn->prepare("
        UPDATE boat_bookings
        SET payment_status = 'paid',
            booking_status = 'confirmed',
            payment_provider = ?,
            provider_txn_id = ?,
            paid_at = NOW(),
            approved_at = NOW(),
            webhook_payload = ?
        WHERE booking_ref = ?
    ");
    $upd->bind_param("ssss", $provider, $transaction_id, $payloadText, $booking_ref);
    $upd->execute();
    $upd->close();

    echo json_encode(['ok' => true, 'message' => 'Payment confirmed']);
    exit;
}

if ($status === 'pending') {
    $upd = $conn->prepare("
        UPDATE boat_bookings
        SET payment_status = 'pending',
            payment_provider = ?,
            provider_txn_id = ?,
            webhook_payload = ?
        WHERE booking_ref = ?
    ");
    $upd->bind_param("ssss", $provider, $transaction_id, $payloadText, $booking_ref);
    $upd->execute();
    $upd->close();

    echo json_encode(['ok' => true, 'message' => 'Payment pending']);
    exit;
}

$upd = $conn->prepare("
    UPDATE boat_bookings
    SET payment_status = 'failed',
        payment_provider = ?,
        provider_txn_id = ?,
        webhook_payload = ?
    WHERE booking_ref = ?
");
$upd->bind_param("ssss", $provider, $transaction_id, $payloadText, $booking_ref);
$upd->execute();
$upd->close();

echo json_encode(['ok' => true, 'message' => 'Payment marked as failed']);