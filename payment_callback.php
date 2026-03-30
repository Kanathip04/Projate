<?php
/**
 * payment_callback.php
 * n8n POST มาที่นี่หลังจาก Gemini ตรวจสลิปเสร็จ
 */
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json; charset=utf-8');

define('CALLBACK_SECRET', 'wrbri_n8n_secret_2026');

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid JSON']);
    exit;
}

if (($data['secret'] ?? '') !== CALLBACK_SECRET) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}

// ── รับข้อมูลพื้นฐาน ──
$booking_ref  = trim($data['booking_ref']  ?? '');
$verified     = (bool)($data['verified']   ?? false);
$amountMatched= (bool)($data['amount_matched'] ?? false);
$slipAmount   = (float)($data['slip_amount'] ?? 0);
$txnId        = trim($data['provider_txn_id'] ?? '');
$rejectReason = trim($data['reject_reason'] ?? '');
$is429        = (bool)($data['is429']        ?? false);
$retryNeeded  = (bool)($data['retry_needed'] ?? false);
$vStatus      = trim($data['verification_status'] ?? '');

// ── ข้อมูลที่ AI อ่านจากสลิป ──
$slipData = $data['slip_data'] ?? [];
$slipRefNo       = trim($slipData['ref_no']             ?? '');
$slipDatetime    = trim($slipData['transfer_datetime']  ?? '');
$slipPayerName   = trim($slipData['payer_name']         ?? '');
$slipPayerAcct   = trim($slipData['payer_account']      ?? '');
$slipPayeeName   = trim($slipData['payee_name']         ?? '');
$slipPayeeAcct   = trim($slipData['payee_account']      ?? '');
$slipSrcBank     = trim($slipData['source_bank']        ?? '');
$slipDstBank     = trim($slipData['destination_bank']   ?? '');
$slipTransType   = trim($slipData['transfer_type']      ?? '');
$slipConfidence  = (float)($slipData['confidence']      ?? 0);
$rawAiResponse   = trim($data['raw_ai_response']        ?? '');

if (!$booking_ref) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'booking_ref required']);
    exit;
}

$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'DB Error']);
    exit;
}

// ── ดึง booking ──
$stmt = $conn->prepare("SELECT * FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Booking not found']);
    exit;
}

// ── บันทึก webhook payload ──
$conn->query(
    "UPDATE boat_bookings SET webhook_payload = '" . $conn->real_escape_string($rawBody) . "'" .
    " WHERE booking_ref = '" . $conn->real_escape_string($booking_ref) . "'"
);

// ── Helper: อัปเดต payment_slips ──
function updateSlipRecord($conn, $booking_id, $fields) {
    if (empty($fields)) return;
    $sets = [];
    $types = '';
    $vals  = [];
    foreach ($fields as $col => $val) {
        $sets[] = "`$col` = ?";
        $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
        $vals[] = $val;
    }
    $vals[] = $booking_id;
    $types .= 'i';
    $sql = "UPDATE payment_slips SET " . implode(', ', $sets) . " WHERE booking_id = ? ORDER BY id DESC LIMIT 1";
    $st  = $conn->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();
    $st->close();
}

// ── กรณี AI rate limit / retry ──
if ($is429 || $retryNeeded || $rejectReason === 'AI_RATE_LIMIT') {
    $note = "\n[AUTO] AI ยืนยันไม่ได้ (rate limit) รอ admin ตรวจสลิปเอง [" . date('Y-m-d H:i') . "]";
    $st = $conn->prepare("UPDATE boat_bookings SET payment_status='manual_review', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
    $st->bind_param("ss", $note, $booking_ref);
    $st->execute(); $st->close();

    updateSlipRecord($conn, $booking['id'], [
        'verification_status' => 'manual_review',
        'verification_reason' => 'AI rate limit',
    ]);

    echo json_encode(['ok'=>true,'action'=>'pending_manual']);
    $conn->close(); exit;
}

// ── กรณี manual_review (AI อ่านไม่ชัด) ──
if ($vStatus === 'manual_review') {
    $note = "\n[AUTO] AI อ่านสลิปไม่ชัด (confidence ต่ำ) รอ admin ตรวจ [" . date('Y-m-d H:i') . "]";
    $st = $conn->prepare("UPDATE boat_bookings SET payment_status='manual_review', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
    $st->bind_param("ss", $note, $booking_ref);
    $st->execute(); $st->close();

    updateSlipRecord($conn, $booking['id'], [
        'verification_status' => 'manual_review',
        'verification_reason' => $rejectReason,
        'confidence_score'    => $slipConfidence,
        'raw_ai_response'     => substr($rawAiResponse, 0, 65535),
    ]);

    echo json_encode(['ok'=>true,'action'=>'manual_review']);
    $conn->close(); exit;
}

// ── ตรวจ ref_no ซ้ำ ──
if ($slipRefNo) {
    $refStmt = $conn->prepare(
        "SELECT booking_ref FROM payment_slips WHERE extracted_ref_no = ? AND booking_id != ? LIMIT 1"
    );
    $refStmt->bind_param("si", $slipRefNo, $booking['id']);
    $refStmt->execute();
    $dupRef = $refStmt->get_result()->fetch_assoc();
    $refStmt->close();

    if ($dupRef) {
        $note = "\n[AUTO] เลขอ้างอิงซ้ำกับ " . $dupRef['booking_ref'] . " ref:" . $slipRefNo . " [" . date('Y-m-d H:i') . "]";
        $st = $conn->prepare("UPDATE boat_bookings SET payment_status='duplicate', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
        $st->bind_param("ss", $note, $booking_ref);
        $st->execute(); $st->close();

        updateSlipRecord($conn, $booking['id'], [
            'verification_status' => 'duplicate',
            'verification_reason' => 'เลขอ้างอิงธุรกรรมซ้ำกับ ' . $dupRef['booking_ref'],
            'extracted_ref_no'    => $slipRefNo,
        ]);

        echo json_encode(['ok'=>false,'action'=>'duplicate','reject_reason'=>'สลิปเลขอ้างอิงซ้ำ']);
        $conn->close(); exit;
    }
}

// ── บันทึกข้อมูลที่ AI อ่านได้ลง payment_slips ──
$slipDt = $slipDatetime ? date('Y-m-d H:i:s', strtotime($slipDatetime)) : null;
$slipFields = [
    'extracted_amount'            => $slipAmount,
    'extracted_ref_no'            => $slipRefNo    ?: null,
    'payer_name'                  => $slipPayerName ?: null,
    'payer_account'               => $slipPayerAcct ?: null,
    'payee_name'                  => $slipPayeeName ?: null,
    'payee_account'               => $slipPayeeAcct ?: null,
    'source_bank'                 => $slipSrcBank   ?: null,
    'destination_bank'            => $slipDstBank   ?: null,
    'transfer_type'               => $slipTransType ?: null,
    'confidence_score'            => $slipConfidence ?: null,
    'raw_ai_response'             => substr($rawAiResponse, 0, 65535) ?: null,
];
if ($slipDt) $slipFields['extracted_transfer_datetime'] = $slipDt;

// กรองค่า null ออกก่อนอัปเดต
$slipFields = array_filter($slipFields, fn($v) => $v !== null && $v !== '');
if ($slipFields) updateSlipRecord($conn, $booking['id'], $slipFields);

// ── ตรวจวันที่สลิปต้องไม่เก่ากว่าวันที่จองเกิน 1 วัน ──
if ($verified && $slipDatetime) {
    $slipTs    = strtotime($slipDatetime);
    $bookingTs = strtotime($booking['created_at']);

    if ($slipTs !== false && $slipTs < ($bookingTs - 86400)) {
        $slipDateFmt = date('d/m/Y H:i', $slipTs);
        $bookDateFmt = date('d/m/Y H:i', $bookingTs);
        $note = "\n[AUTO] วันที่สลิปไม่ตรง: สลิป {$slipDateFmt} แต่จอง {$bookDateFmt} [" . date('Y-m-d H:i') . "]";

        $st = $conn->prepare("UPDATE boat_bookings SET payment_status='failed', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
        $st->bind_param("ss", $note, $booking_ref);
        $st->execute(); $st->close();

        updateSlipRecord($conn, $booking['id'], [
            'verification_status' => 'rejected',
            'verification_reason' => "วันที่สลิป ({$slipDateFmt}) เก่ากว่าวันที่จอง ({$bookDateFmt})",
        ]);

        echo json_encode([
            'ok'            => false,
            'action'        => 'rejected',
            'reject_reason' => "สลิปวันที่ {$slipDateFmt} ไม่สามารถใช้ได้ กรุณาโอนเงินใหม่และแนบสลิปที่ถูกต้อง",
        ]);
        $conn->close(); exit;
    }
}

// ── ตัดสินผล ──
if ($verified && $amountMatched) {
    // ── ชำระสำเร็จ ──
    $today  = date('Y-m-d');
    $cntRes = $conn->query(
        "SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(approved_at)='$today' AND booking_status='approved'"
    );
    $queueNo = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;

    $upStmt = $conn->prepare(
        "UPDATE boat_bookings
         SET payment_status='paid', booking_status='approved',
             daily_queue_no=?, provider_txn_id=?, paid_at=NOW(), approved_at=NOW()
         WHERE booking_ref=?"
    );
    $upStmt->bind_param("iss", $queueNo, $txnId, $booking_ref);
    $upStmt->execute(); $upStmt->close();

    updateSlipRecord($conn, $booking['id'], ['verification_status'=>'paid']);

    $queueLabel = 'Q' . str_pad($queueNo, 4, '0', STR_PAD_LEFT);
    echo json_encode([
        'ok'          => true,
        'action'      => 'approved',
        'queue_label' => $queueLabel,
        'booking_ref' => $booking_ref,
        'ticket_url'  => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/queue_ticket.php?ref=' . urlencode($booking_ref),
    ]);

} elseif ($vStatus === 'suspicious') {
    // ── น่าสงสัย ──
    $note = "\n[AUTO] สลิปน่าสงสัย: $rejectReason [" . date('Y-m-d H:i') . "]";
    $st = $conn->prepare("UPDATE boat_bookings SET payment_status='suspicious', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
    $st->bind_param("ss", $note, $booking_ref);
    $st->execute(); $st->close();

    updateSlipRecord($conn, $booking['id'], [
        'verification_status' => 'suspicious',
        'verification_reason' => $rejectReason,
    ]);

    echo json_encode(['ok'=>false,'action'=>'suspicious','reject_reason'=>$rejectReason]);

} else {
    // ── สลิปไม่ผ่าน ──
    $failNote = "\n[AUTO] สลิปไม่ผ่าน: " .
        ($rejectReason ?: "ยอดไม่ตรง (฿$slipAmount / ต้องจ่าย ฿{$booking['total_amount']})") .
        " [" . date('Y-m-d H:i') . "]";
    $upStmt = $conn->prepare(
        "UPDATE boat_bookings SET payment_status='failed', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?"
    );
    $upStmt->bind_param("ss", $failNote, $booking_ref);
    $upStmt->execute(); $upStmt->close();

    updateSlipRecord($conn, $booking['id'], [
        'verification_status' => 'rejected',
        'verification_reason' => $rejectReason ?: 'ยอดไม่ตรง',
    ]);

    echo json_encode([
        'ok'            => false,
        'action'        => 'rejected',
        'booking_ref'   => $booking_ref,
        'reject_reason' => $rejectReason ?: "ยอดเงินไม่ตรง (สลิป: ฿$slipAmount, ต้องจ่าย: ฿{$booking['total_amount']})",
    ]);
}

$conn->close();
exit;
