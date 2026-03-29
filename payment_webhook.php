<?php
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'message' => 'DB connection failed']);
    exit;
}

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

$booking_ref    = trim($data['booking_ref'] ?? '');
$transaction_id = trim($data['transaction_id'] ?? '');
$amount         = (float)($data['amount'] ?? 0);
$status         = strtolower(trim($data['status'] ?? ''));
$provider       = trim($data['provider'] ?? 'custom');

if ($booking_ref === '' || $transaction_id === '' || $status === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, total_amount, provider_txn_id
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
    $fail = $conn->prepare("
        UPDATE boat_bookings
        SET payment_status = 'failed',
            payment_provider = ?,
            provider_txn_id = ?,
            webhook_payload = ?
        WHERE booking_ref = ?
    ");
    $fail->bind_param("ssss", $provider, $transaction_id, $raw, $booking_ref);
    $fail->execute();
    $fail->close();

    echo json_encode(['ok' => false, 'message' => 'Amount mismatch']);
    exit;
}

if (in_array($status, ['successful', 'success', 'paid'], true)) {
    $upd = $conn->prepare("
        UPDATE boat_bookings
        SET payment_status = 'paid',
            booking_status = 'approved',
            payment_provider = ?,
            provider_txn_id = ?,
            paid_at = NOW(),
            approved_at = NOW(),
            webhook_payload = ?
        WHERE booking_ref = ?
    ");
    $upd->bind_param("ssss", $provider, $transaction_id, $raw, $booking_ref);
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
    $upd->bind_param("ssss", $provider, $transaction_id, $raw, $booking_ref);
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
$upd->bind_param("ssss", $provider, $transaction_id, $raw, $booking_ref);
$upd->execute();
$upd->close();

echo json_encode(['ok' => true, 'message' => 'Payment marked as failed']);