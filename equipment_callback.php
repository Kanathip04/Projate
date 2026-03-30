<?php
/**
 * equipment_callback.php
 * n8n POST มาที่นี่หลังจาก Gemini ตรวจสลิปอุปกรณ์เสร็จ
 */
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json; charset=utf-8');

define('CALLBACK_SECRET', 'wrbri_n8n_secret_2026');
define('PAYEE_FIRST_NAME', 'สุรัชฎา');
define('PAYEE_LAST_NAME',  'คุ้มชาติตา');

function isPayeeNameValid(string $name): bool {
    if ($name === '') return false;
    $norm = preg_replace('/^(น\.ส\.?\s*|นางสาว\s*|นาย\s*|นาง\s*|Mr\.?\s*|Ms\.?\s*|Mrs\.?\s*)/u', '', trim($name));
    $norm = preg_replace('/\s+/u', ' ', $norm);
    return mb_strpos($norm, PAYEE_FIRST_NAME) !== false
        && mb_strpos($norm, PAYEE_LAST_NAME)  !== false;
}

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (!$data) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }
if (($data['secret'] ?? '') !== CALLBACK_SECRET) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$bookingRef   = trim($data['booking_ref']     ?? '');
$verified     = (bool)($data['verified']      ?? false);
$amountMatched= (bool)($data['amount_matched']?? false);
$slipAmount   = (float)($data['slip_amount']  ?? 0);
$rejectReason = trim($data['reject_reason']   ?? '');
$is429        = (bool)($data['is429']         ?? false);
$retryNeeded  = (bool)($data['retry_needed']  ?? false);
$vStatus      = trim($data['verification_status'] ?? '');
$slipData     = $data['slip_data'] ?? [];
$slipPayeeName= trim($slipData['payee_name']         ?? '');
$slipDatetime = trim($slipData['transfer_datetime']  ?? '');
$slipRefNo    = trim($slipData['ref_no']             ?? '');
$slipConfidence=(float)($slipData['confidence']      ?? 0);
$rawAiResponse= trim($data['raw_ai_response'] ?? '');

// แปลง booking_ref → id (EQUIP-00005 → 5)
$bookingId = 0;
if (preg_match('/^EQUIP-(\d+)$/i', $bookingRef, $m)) $bookingId = (int)$m[1];

if (!$bookingId) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid booking_ref']); exit; }

$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB']); exit; }

$st = $conn->prepare("SELECT * FROM equipment_bookings WHERE id=? LIMIT 1");
$st->bind_param("i", $bookingId); $st->execute();
$bk = $st->get_result()->fetch_assoc(); $st->close();
if (!$bk) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }

// Helper: อัปเดต payment_slips
function updateSlip($conn, $bookingId, $fields) {
    if (empty($fields)) return;
    $sets=[]; $types=''; $vals=[];
    foreach ($fields as $col=>$val) {
        $sets[] = "`$col`=?";
        $types .= is_int($val)?'i':(is_float($val)?'d':'s');
        $vals[] = $val;
    }
    $vals[] = $bookingId; $vals[] = 'equipment'; $types .= 'ss';
    $sql = "UPDATE payment_slips SET ".implode(',',$sets)." WHERE booking_id=? AND booking_type='equipment' ORDER BY id DESC LIMIT 1";
    $s = $conn->prepare($sql);
    $s->bind_param($types, ...$vals);
    $s->execute(); $s->close();
}

// AI rate limit
if ($is429 || $retryNeeded || $rejectReason === 'AI_RATE_LIMIT') {
    $note = "\n[AUTO] AI ยืนยันไม่ได้ (rate limit) รอ admin ตรวจสลิปเอง [".date('Y-m-d H:i')."]";
    $s = $conn->prepare("UPDATE equipment_bookings SET payment_status='waiting_verify', booking_status=CONCAT(COALESCE(booking_status,''),?) WHERE id=?");
    // just keep waiting_verify, note in slip
    updateSlip($conn, $bookingId, ['verification_status'=>'manual_review','verification_reason'=>'AI rate limit']);
    echo json_encode(['ok'=>true,'action'=>'pending_manual']); $conn->close(); exit;
}

// manual_review
if ($vStatus === 'manual_review') {
    updateSlip($conn, $bookingId, ['verification_status'=>'manual_review','verification_reason'=>$rejectReason,'confidence_score'=>$slipConfidence,'raw_ai_response'=>substr($rawAiResponse,0,65535)]);
    echo json_encode(['ok'=>true,'action'=>'manual_review']); $conn->close(); exit;
}

// ตรวจ ref_no ซ้ำ
if ($slipRefNo) {
    $rs = $conn->prepare("SELECT id FROM payment_slips WHERE extracted_ref_no=? AND booking_id!=? LIMIT 1");
    $rs->bind_param("si", $slipRefNo, $bookingId); $rs->execute();
    $dupRef = $rs->get_result()->fetch_assoc(); $rs->close();
    if ($dupRef) {
        $conn->query("UPDATE equipment_bookings SET payment_status='failed' WHERE id=$bookingId");
        updateSlip($conn, $bookingId, ['verification_status'=>'duplicate','verification_reason'=>'เลขอ้างอิงธุรกรรมซ้ำ','extracted_ref_no'=>$slipRefNo]);
        echo json_encode(['ok'=>false,'action'=>'duplicate']); $conn->close(); exit;
    }
}

// บันทึกข้อมูล AI ลง payment_slips
$slipFields = array_filter([
    'extracted_amount'  => $slipAmount ?: null,
    'extracted_ref_no'  => $slipRefNo  ?: null,
    'payee_name'        => $slipPayeeName ?: null,
    'confidence_score'  => $slipConfidence ?: null,
    'raw_ai_response'   => substr($rawAiResponse,0,65535) ?: null,
], fn($v)=>$v!==null && $v!=='');
if ($slipDatetime) $slipFields['extracted_transfer_datetime'] = date('Y-m-d H:i:s', strtotime($slipDatetime));
if ($slipFields) updateSlip($conn, $bookingId, $slipFields);

// ตรวจชื่อผู้รับ
if ($verified) {
    if ($slipPayeeName === '') {
        updateSlip($conn, $bookingId, ['verification_status'=>'manual_review','verification_reason'=>'AI อ่านชื่อผู้รับไม่ได้']);
        echo json_encode(['ok'=>true,'action'=>'manual_review']); $conn->close(); exit;
    } elseif (!isPayeeNameValid($slipPayeeName)) {
        $reason = "ชื่อผู้รับ \"{$slipPayeeName}\" ไม่ตรง (ต้องเป็น ".PAYEE_FIRST_NAME.' '.PAYEE_LAST_NAME.")";
        $conn->query("UPDATE equipment_bookings SET payment_status='failed' WHERE id=$bookingId");
        updateSlip($conn, $bookingId, ['verification_status'=>'rejected','verification_reason'=>$reason]);
        echo json_encode(['ok'=>false,'action'=>'rejected','reject_reason'=>$reason]); $conn->close(); exit;
    }
}

// ── ตัดสินผล ──
if ($verified && $amountMatched) {
    $conn->query("UPDATE equipment_bookings SET payment_status='paid', booking_status='approved' WHERE id=$bookingId");
    updateSlip($conn, $bookingId, ['verification_status'=>'paid']);
    echo json_encode(['ok'=>true,'action'=>'approved','booking_id'=>$bookingId]);

} elseif ($vStatus === 'suspicious') {
    $conn->query("UPDATE equipment_bookings SET payment_status='failed' WHERE id=$bookingId");
    updateSlip($conn, $bookingId, ['verification_status'=>'suspicious','verification_reason'=>$rejectReason]);
    echo json_encode(['ok'=>false,'action'=>'suspicious']);

} else {
    $conn->query("UPDATE equipment_bookings SET payment_status='failed' WHERE id=$bookingId");
    $reason = $rejectReason ?: "ยอดไม่ตรง (สลิป: ฿$slipAmount, ต้องจ่าย: ฿{$bk['total_price']})";
    updateSlip($conn, $bookingId, ['verification_status'=>'rejected','verification_reason'=>$reason]);
    echo json_encode(['ok'=>false,'action'=>'rejected','reject_reason'=>$reason]);
}

$conn->close(); exit;
