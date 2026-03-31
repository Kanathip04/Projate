<?php
/**
 * payment_callback.php
 * n8n POST มาที่นี่หลังจาก Gemini ตรวจสลิปเสร็จ
 */
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json; charset=utf-8');

define('CALLBACK_SECRET',    'wrbri_n8n_secret_2026');
define('SLIP_MAX_AGE_SEC',   420);   // ยอมรับสลิปย้อนหลังได้ไม่เกิน 7 นาที (5 นาที + buffer 2 นาที)
define('PAYEE_FIRST_NAME',   'สุรัชฎา');
define('PAYEE_LAST_NAME',    'คุ้มชาติตา');

/**
 * ตรวจชื่อผู้รับเงินในสลิป
 * รองรับ: "สุรัชฎา คุ้มชาติตา", "น.ส. สุรัชฎา คุ้มชาติตา", "น.ส สุรัชฎา คุ้มชาติตา", ฯลฯ
 */
/**
 * Normalize Thai combining characters ให้อยู่ในลำดับ NFC
 * แก้ปัญหา: AI อาจส่ง tone mark + sara u ในลำดับสลับกัน
 * เช่น "คุ้" = ค+ุ+้ (ถูก) หรือ ค+้+ุ (ผิดลำดับ) → ตามองเห็นเหมือนกันแต่ byte ต่างกัน
 */
function thaiNormalize(string $s): string {
    // สลับ tone mark (U+0E48–0E4B) ที่อยู่ก่อน sara u/uu (U+0E38–0E39) → ให้ sara ขึ้นก่อน
    $s = preg_replace('/([\x{0E48}\x{0E49}\x{0E4A}\x{0E4B}])([\x{0E38}\x{0E39}])/u', '$2$1', $s);
    // ใช้ PHP Normalizer เพิ่มเติม ถ้า intl extension ติดตั้งอยู่
    if (class_exists('Normalizer')) {
        $s = \Normalizer::normalize($s, \Normalizer::NFC) ?: $s;
    }
    return $s;
}

function isPayeeNameValid(string $name): bool {
    if ($name === '') return false;
    $name = thaiNormalize($name);
    // ตัด prefix คำนำหน้า (น.ส., นางสาว, นาย, นาง, ฯลฯ)
    $norm  = preg_replace('/^(น\.ส\.?\s*|นางสาว\s*|นาย\s*|นาง\s*|Mr\.?\s*|Ms\.?\s*|Mrs\.?\s*)/u', '', trim($name));
    $norm  = preg_replace('/\s+/u', ' ', $norm);
    $first = thaiNormalize(PAYEE_FIRST_NAME);
    $last  = thaiNormalize(PAYEE_LAST_NAME);
    return mb_strpos($norm, $first) !== false
        && mb_strpos($norm, $last)  !== false;
}

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

// ── ตรวจว่าเป็น equipment booking หรือ boat booking ──
$isEquipment = (strpos($booking_ref, 'EQUIP-') === 0);
$equipId = 0;

if ($isEquipment) {
    // ── Equipment booking ──
    $equipId = (int)preg_replace('/\D/', '', $booking_ref);
    $stmt = $conn->prepare("SELECT * FROM equipment_bookings WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $equipId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'Equipment booking not found']);
        exit;
    }
    // ใช้ id จริงเป็น booking_id สำหรับ payment_slips
    $booking['booking_ref'] = $booking_ref;
} else {
    // ── Boat booking ──
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

    // ── บันทึก webhook payload (boat only) ──
    $conn->query(
        "UPDATE boat_bookings SET webhook_payload = '" . $conn->real_escape_string($rawBody) . "'" .
        " WHERE booking_ref = '" . $conn->real_escape_string($booking_ref) . "'"
    );
}

// ── Helper: อัปเดต equipment_bookings status ──
function updateEquipStatus($conn, $id, $status) {
    $conn->query("UPDATE equipment_bookings SET payment_status='$status', booking_status='approved' WHERE id=$id");
}

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

// ── Helper: อัปเดต status ตามประเภท booking ──
function updateBookingStatus($conn, $isEquipment, $equipId, $booking_ref, $status, $note = null) {
    if ($isEquipment) {
        $bookingStatus = ($status === 'paid') ? 'approved' : 'pending';
        if ($note !== null) {
            $st = $conn->prepare("UPDATE equipment_bookings SET payment_status=?, booking_status=?, note=CONCAT(COALESCE(note,''),?) WHERE id=?");
            $st->bind_param("sssi", $status, $bookingStatus, $note, $equipId);
        } else {
            $st = $conn->prepare("UPDATE equipment_bookings SET payment_status=?, booking_status=? WHERE id=?");
            $st->bind_param("ssi", $status, $bookingStatus, $equipId);
        }
    } else {
        if ($note !== null) {
            $st = $conn->prepare("UPDATE boat_bookings SET payment_status=?, note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
            $st->bind_param("sss", $status, $note, $booking_ref);
        } else {
            $st = $conn->prepare("UPDATE boat_bookings SET payment_status=? WHERE booking_ref=?");
            $st->bind_param("ss", $status, $booking_ref);
        }
    }
    $st->execute(); $st->close();
}

// ── กรณี AI rate limit / retry ──
if ($is429 || $retryNeeded || $rejectReason === 'AI_RATE_LIMIT') {
    $note = "\n[AUTO] AI ยืนยันไม่ได้ (rate limit) รอ admin ตรวจสลิปเอง [" . date('Y-m-d H:i') . "]";
    updateBookingStatus($conn, $isEquipment, $equipId ?? 0, $booking_ref, 'manual_review', $note);

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
    updateBookingStatus($conn, $isEquipment, $equipId ?? 0, $booking_ref, 'manual_review', $note);

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
        updateBookingStatus($conn, $isEquipment, $equipId ?? 0, $booking_ref, 'duplicate', $note);

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

// ══════════════════════════════════════════════
//  ตรวจที่ 1 — เวลาโอนต้องไม่เกิน 5 นาที (+ 2 นาที buffer)
// ══════════════════════════════════════════════
if ($verified && $slipDatetime) {
    $slipTs = strtotime($slipDatetime);
    $nowTs  = time();

    if ($slipTs !== false) {
        $diffSec = $nowTs - $slipTs;

        // สลิปในอนาคต (clock skew) หรือเก่ากว่า SLIP_MAX_AGE_SEC
        if ($diffSec < -120 || $diffSec > SLIP_MAX_AGE_SEC) {
            $slipFmt = date('d/m/Y H:i:s', $slipTs);
            $nowFmt  = date('d/m/Y H:i:s', $nowTs);
            $reason  = $diffSec > 0
                ? "สลิปเวลา {$slipFmt} โอนนานเกิน 5 นาทีแล้ว (ห่างจากปัจจุบัน " . round($diffSec/60, 1) . " นาที)"
                : "เวลาในสลิป ({$slipFmt}) อยู่ในอนาคต";

            $note = "\n[AUTO] ตรวจเวลาไม่ผ่าน: {$reason} [" . date('Y-m-d H:i') . "]";
            updateBookingStatus($conn, $isEquipment, $equipId ?? 0, $booking_ref, 'failed', $note);

            updateSlipRecord($conn, $booking['id'], [
                'verification_status' => 'rejected',
                'verification_reason' => $reason,
            ]);

            echo json_encode(['ok'=>false,'action'=>'rejected','reject_reason'=>$reason]);
            $conn->close(); exit;
        }
    }
}

// ══════════════════════════════════════════════
//  ตรวจที่ 2 — ชื่อผู้รับต้องมี "สุรัชฎา" + "คุ้มชาติตา"
// ══════════════════════════════════════════════
if ($verified) {
    if ($slipPayeeName === '') {
        // AI อ่านชื่อผู้รับไม่ได้ → ส่ง manual_review แทนการผ่านอัตโนมัติ
        $note = "\n[AUTO] AI อ่านชื่อผู้รับไม่ได้ รอ admin ตรวจสอบชื่อด้วยตนเอง [" . date('Y-m-d H:i') . "]";
        $st = $conn->prepare("UPDATE boat_bookings SET payment_status='manual_review', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
        $st->bind_param("ss", $note, $booking_ref);
        $st->execute(); $st->close();

        updateSlipRecord($conn, $booking['id'], [
            'verification_status' => 'manual_review',
            'verification_reason' => 'AI อ่านชื่อผู้รับไม่ได้ ต้องตรวจสอบด้วยมือ',
        ]);

        echo json_encode(['ok'=>true,'action'=>'manual_review','reject_reason'=>'ระบบอ่านชื่อผู้รับไม่ได้ รอ admin ตรวจสอบ']);
        $conn->close(); exit;

    } elseif (!isPayeeNameValid($slipPayeeName)) {
        // อ่านชื่อได้ แต่ไม่ตรงกับชื่อที่กำหนด
        $reason = "ชื่อผู้รับ \"{$slipPayeeName}\" ไม่ตรง (ต้องเป็น " . PAYEE_FIRST_NAME . ' ' . PAYEE_LAST_NAME . ")";
        $note   = "\n[AUTO] ชื่อผู้รับไม่ตรง: {$slipPayeeName} [" . date('Y-m-d H:i') . "]";
        updateBookingStatus($conn, $isEquipment, $equipId ?? 0, $booking_ref, 'failed', $note);

        updateSlipRecord($conn, $booking['id'], [
            'verification_status' => 'rejected',
            'verification_reason' => $reason,
        ]);

        echo json_encode(['ok'=>false,'action'=>'rejected','reject_reason'=>$reason]);
        $conn->close(); exit;
    }
}

// ── ตัดสินผล ──
if ($verified && $amountMatched) {
    // ── ชำระสำเร็จ ──
    if ($isEquipment) {
        // Equipment booking: อัปเดต equipment_bookings
        $upStmt = $conn->prepare(
            "UPDATE equipment_bookings
             SET payment_status='paid', booking_status='approved',
                 provider_txn_id=?, paid_at=NOW(), approved_at=NOW()
             WHERE id=?"
        );
        $upStmt->bind_param("si", $txnId, $equipId);
        $upStmt->execute(); $upStmt->close();

        updateSlipRecord($conn, $booking['id'], ['verification_status'=>'paid']);

        echo json_encode([
            'ok'          => true,
            'action'      => 'approved',
            'booking_ref' => $booking_ref,
            'ticket_url'  => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/queue_ticket.php?ref=' . urlencode($booking_ref),
        ]);
    } else {
        // Boat booking: คำนวณคิวและอัปเดต boat_bookings
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
    }

} elseif ($vStatus === 'suspicious') {
    // ── น่าสงสัย ──
    $note = "\n[AUTO] สลิปน่าสงสัย: $rejectReason [" . date('Y-m-d H:i') . "]";
    updateBookingStatus($conn, $isEquipment, $equipId ?? 0, $booking_ref, 'suspicious', $note);

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
    updateBookingStatus($conn, $isEquipment, $equipId ?? 0, $booking_ref, 'failed', $failNote);

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
