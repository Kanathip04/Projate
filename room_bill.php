<?php
/**
 * room_bill.php
 * หน้าบิลและชำระเงิน PromptPay สำหรับการจองห้องพัก
 * ธีม: navy/gold (ตรงกับ booking_form.php)
 */
session_start();
require_once 'auth_guard.php';
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");

/* === สร้าง payment_slips ถ้ายังไม่มี และเพิ่ม booking_type ถ้าขาด === */
$conn->query("CREATE TABLE IF NOT EXISTS `payment_slips` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT UNSIGNED,
    `booking_ref` VARCHAR(50),
    `slip_image_path` VARCHAR(500),
    `slip_hash` VARCHAR(64),
    `verification_status` VARCHAR(50) DEFAULT 'checking',
    `uploaded_ip` VARCHAR(50),
    `uploaded_ua` VARCHAR(500),
    `uploaded_at` DATETIME,
    `extracted_amount` DECIMAL(10,2) DEFAULT NULL,
    `extracted_ref_no` VARCHAR(100) DEFAULT NULL,
    `payer_name` VARCHAR(200) DEFAULT NULL,
    `payee_name` VARCHAR(200) DEFAULT NULL,
    `confidence_score` FLOAT DEFAULT NULL,
    `raw_ai_response` TEXT,
    `verification_reason` VARCHAR(500) DEFAULT NULL,
    `source_bank` VARCHAR(100) DEFAULT NULL,
    `destination_bank` VARCHAR(100) DEFAULT NULL,
    `extracted_transfer_datetime` DATETIME DEFAULT NULL,
    `booking_type` VARCHAR(30) DEFAULT 'boat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$chkBt = $conn->query("SHOW COLUMNS FROM payment_slips LIKE 'booking_type'");
if ($chkBt && $chkBt->num_rows === 0) {
    $conn->query("ALTER TABLE payment_slips ADD COLUMN booking_type VARCHAR(30) DEFAULT 'boat'");
}

/* === ตรวจ/เพิ่มคอลัมน์ที่จำเป็นใน room_bookings === */
foreach ([
    "payment_status ENUM('unpaid','waiting_verify','paid','failed','manual_review') DEFAULT 'unpaid'",
    "payment_slip VARCHAR(500) DEFAULT NULL",
    "paid_at DATETIME DEFAULT NULL",
    "approved_at DATETIME DEFAULT NULL",
    "total_price DECIMAL(10,2) DEFAULT NULL",
    "room_price DECIMAL(10,2) DEFAULT NULL",
    "booking_ref VARCHAR(50) DEFAULT NULL",
] as $colDef) {
    $colName = strtok($colDef, ' ');
    $chk = $conn->query("SHOW COLUMNS FROM room_bookings LIKE '$colName'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE room_bookings ADD COLUMN $colDef");
    }
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: booking_room.php"); exit; }

/* === retry: รีเซ็ตสลิปเพื่อส่งใหม่ === */
if (isset($_GET['retry'])) {
    $conn->query("UPDATE room_bookings SET payment_status='unpaid', payment_slip=NULL WHERE id=$id AND payment_status='failed'");
    header("Location: room_bill.php?id=$id"); exit;
}

/* === โหลดข้อมูลการจอง === */
$st = $conn->prepare("SELECT * FROM room_bookings WHERE id = ? LIMIT 1");
$st->bind_param("i", $id);
$st->execute();
$bk = $st->get_result()->fetch_assoc();
$st->close();
if (!$bk) { header("Location: booking_room.php"); exit; }

/* คำนวณเลขใบเสร็จ รูปแบบ ROOM-YYYYMMDD-NNN */
$_rbTs      = strtotime($bk['created_at']);
$_rbDateStr = date('Y-m-d', $_rbTs);
$_rbSeqRes  = $conn->query("SELECT COUNT(*) AS seq FROM room_bookings WHERE DATE(created_at) = '$_rbDateStr' AND id <= $id");
$_rbSeq     = (int)($_rbSeqRes ? $_rbSeqRes->fetch_assoc()['seq'] : 1);
$_roomBookingRef = 'ROOM-' . date('Y', $_rbTs) . date('m', $_rbTs) . date('d', $_rbTs) . '-' . str_pad($_rbSeq, 3, '0', STR_PAD_LEFT);

define('PROMPTPAY_ID', '0611360322');

/* === อัปโหลดสลิป === */
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slip'])) {
    $file = $_FILES['slip'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $uploadError = 'รองรับเฉพาะ jpg, png, webp เท่านั้น';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $uploadError = 'ไฟล์ขนาดใหญ่เกินไป (สูงสุด 5MB)';
    } else {
        $dir = 'uploads/slips/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'room_slip_' . $id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
            $slipPath = $dir . $fname;
            $slipHash = hash('sha256', file_get_contents($slipPath));

            /* ตรวจสลิปซ้ำ */
            $dupSt = $conn->prepare("SELECT id FROM payment_slips WHERE slip_hash = ? LIMIT 1");
            $dupSt->bind_param("s", $slipHash);
            $dupSt->execute();
            $dupRow = $dupSt->get_result()->fetch_assoc();
            $dupSt->close();

            if ($dupRow) {
                @unlink($slipPath);
                $uploadError = 'สลิปนี้เคยถูกใช้แล้ว กรุณาใช้สลิปใหม่';
            } else {
                /* คำนวณราคาและคืน */
                $bookingRef = $_roomBookingRef;

                $nights = 1;
                if (!empty($bk['checkin_date']) && !empty($bk['checkout_date'])) {
                    $d1t = new DateTime($bk['checkin_date']);
                    $d2t = new DateTime($bk['checkout_date']);
                    $nights = max(1, (int)$d1t->diff($d2t)->days);
                }

                $room_units = json_decode($bk['room_units'] ?? '[]', true) ?: [];
                $numRooms   = max(1, count($room_units));
                $roomPrice  = (float)($bk['room_price'] ?? 0);

                /* ถ้า room_price ยังเป็น 0 ดึงจาก rooms */
                if ($roomPrice <= 0) {
                    $rpSt = $conn->prepare("SELECT price FROM rooms WHERE id = ? LIMIT 1");
                    $rpSt->bind_param("i", $bk['room_id']);
                    $rpSt->execute();
                    $rpRow = $rpSt->get_result()->fetch_assoc();
                    $rpSt->close();
                    $roomPrice = (float)($rpRow['price'] ?? 0);
                }

                $totalPay = !empty($bk['total_price']) ? (float)$bk['total_price'] : ($roomPrice * $nights * $numRooms);

                /* บันทึก payment_slips */
                $insStmt = $conn->prepare(
                    "INSERT INTO payment_slips
                     (booking_id, booking_ref, slip_image_path, slip_hash, verification_status, uploaded_ip, uploaded_ua, uploaded_at, booking_type)
                     VALUES (?, ?, ?, ?, 'checking', ?, ?, NOW(), 'room')"
                );
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
                $insStmt->bind_param("isssss", $id, $bookingRef, $slipPath, $slipHash, $ip, $ua);
                $insStmt->execute();
                $insStmt->close();

                /* อัปเดต booking */
                $su = $conn->prepare("UPDATE room_bookings SET payment_slip = ?, payment_status = 'waiting_verify' WHERE id = ?");
                $su->bind_param("si", $slipPath, $id);
                $su->execute();
                $su->close();

                /* ส่ง n8n webhook */
                $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
                $slipB64 = base64_encode(file_get_contents($slipPath));
                $payload = json_encode([
                    'secret'           => 'wrbri_n8n_secret_2026',
                    'booking_ref'      => $bookingRef,
                    'booking_id'       => $id,
                    'booking_type'     => 'room',
                    'user_name'        => $bk['full_name'],
                    'expected_amount'  => $totalPay,
                    'amount_tolerance' => 1.00,
                    'promptpay_id'     => PROMPTPAY_ID,
                    'booking_created'  => $bk['created_at'],
                    'slip_hash'        => $slipHash,
                    'slip_url'         => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/' . $slipPath,
                    'callback_url'     => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/payment_callback.php',
                    'slip_base64'      => $slipB64,
                    'slip_mime_type'   => $mimeMap[$ext] ?? 'image/jpeg',
                ]);
                $ch = curl_init('https://kanayhip.app.n8n.cloud/webhook/boat-slip');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                curl_exec($ch);
                curl_close($ch);

                header("Location: room_bill.php?id=$id&uploaded=1"); exit;
            }
        } else {
            $uploadError = 'อัปโหลดไม่สำเร็จ กรุณาลองใหม่';
        }
    }
    /* reload booking หลังจาก POST */
    $st2 = $conn->prepare("SELECT * FROM room_bookings WHERE id = ? LIMIT 1");
    $st2->bind_param("i", $id);
    $st2->execute();
    $bk = $st2->get_result()->fetch_assoc();
    $st2->close();
}

/* === คำนวณคืน / ห้อง / ราคา === */
$uploaded = isset($_GET['uploaded']);

$nights = 1;
if (!empty($bk['checkin_date']) && !empty($bk['checkout_date'])) {
    $d1 = new DateTime($bk['checkin_date']);
    $d2 = new DateTime($bk['checkout_date']);
    $nights = max(1, (int)$d1->diff($d2)->days);
}

$room_units = json_decode($bk['room_units'] ?? '[]', true) ?: [];
$numRooms   = max(1, count($room_units));
$roomPrice  = (float)($bk['room_price'] ?? 0);

/* ถ้า room_price ยังเป็น 0 ดึงจาก rooms */
if ($roomPrice <= 0) {
    $rpSt = $conn->prepare("SELECT price FROM rooms WHERE id = ? LIMIT 1");
    $rpSt->bind_param("i", $bk['room_id']);
    $rpSt->execute();
    $rpRow = $rpSt->get_result()->fetch_assoc();
    $rpSt->close();
    $roomPrice = (float)($rpRow['price'] ?? 0);
}

$total = !empty($bk['total_price']) ? (float)$bk['total_price'] : ($roomPrice * $nights * $numRooms);

/* === สร้าง PromptPay QR payload === */
function promptpayPayload(string $target, float $amount): string {
    $target = preg_replace('/\D/', '', $target);
    if (strlen($target) === 10 && $target[0] === '0') $target = '0066' . substr($target, 1);
    $isPhone  = strlen($target) === 13;
    $subTag   = $isPhone ? '01' : '02';
    $subLen   = str_pad(strlen($target), 2, '0', STR_PAD_LEFT);
    $guid     = 'A000000677010111';
    $guidTLV  = '00' . str_pad(strlen($guid), 2, '0', STR_PAD_LEFT) . $guid;
    $phoneTLV = $subTag . $subLen . $target;
    $merchant = $guidTLV . $phoneTLV;
    $tag29    = '29' . str_pad(strlen($merchant), 2, '0', STR_PAD_LEFT) . $merchant;
    $amtStr   = number_format($amount, 2, '.', '');
    $amtTLV   = '54' . str_pad(strlen($amtStr), 2, '0', STR_PAD_LEFT) . $amtStr;
    $body = '000201' . '010212' . $tag29 . '5303764' . $amtTLV . '5802TH' . '6304';
    $crc  = 0xFFFF;
    for ($i = 0; $i < strlen($body); $i++) {
        $crc ^= ord($body[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return $body . strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

$qrPayload = promptpayPayload(PROMPTPAY_ID, $total);
$payStatus = $bk['payment_status'] ?? 'unpaid';

/* Auto redirect สำหรับ cash booking */
$pmMethod = trim($bk['payment_method'] ?? '');
$isCashRoom = ($pmMethod === 'เงินสด' || $pmMethod === 'cash' || $pmMethod === 'ชำระเงินสด');
if ($isCashRoom) {
    header("Location: booking_status.php");
    exit;
}

/* Auto redirect ไปหน้าใบเสร็จถ้าชำระแล้ว */
if ($payStatus === 'paid') {
    header("Location: room_ticket.php?id=$id");
    exit;
}

/* === Timer 5 นาที === */
define('PAY_TIMEOUT_SEC', 300);
$createdAt   = strtotime($bk['created_at']);
$deadline    = $createdAt + PAY_TIMEOUT_SEC;
$nowTs       = time();
$secondsLeft = max(0, $deadline - $nowTs);
$isExpired   = ($secondsLeft === 0 && !in_array($payStatus, ['paid', 'waiting_verify', 'manual_review']));
if ($isExpired && $payStatus === 'unpaid') {
    $conn->query("UPDATE room_bookings SET payment_status='failed' WHERE id=$id AND payment_status='unpaid'");
    $payStatus = 'failed';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>บิลจองห้องพัก #<?= $id ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#0d1b2e;--bg:#f1f4f8;--card:#fff;
  --gold:#c9a96e;--gold-dark:#a8864d;--gold-bg:rgba(201,169,110,.08);
  --navy:#1a3a5c;--navy-dark:#0a1628;--navy-bg:#eff6ff;--navy-bd:#bfdbfe;
  --border:#e2e8f0;--muted:#64748b;
  --blue:#1d6fad;--blue-bg:#eff6ff;--blue-bd:#bfdbfe;
  --yellow:#d97706;--yellow-bg:#fffbeb;--yellow-bd:#fde68a;
  --red:#dc2626;--red-bg:#fef2f2;--red-bd:#fca5a5;
  --green:#16a34a;--green-bg:#f0fdf4;--green-bd:#bbf7d0;
  --shadow:0 4px 24px rgba(13,27,46,.10);
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;}
.page{max-width:540px;margin:0 auto;padding:24px 16px 56px;}

/* timer */
.timer-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;
  background:var(--navy-bg);border:1.5px solid var(--navy-bd);border-radius:14px;
  padding:12px 16px;margin-bottom:16px;}
.timer-bar.urgent{border-color:var(--red-bd);background:var(--red-bg);}
.timer-label{font-size:.78rem;color:var(--muted);font-weight:600;margin-bottom:4px;}
.timer-track{height:5px;background:var(--border);border-radius:4px;width:180px;overflow:hidden;}
.timer-fill{height:100%;background:var(--navy);border-radius:4px;transition:width 1s linear;}
.timer-fill.urgent{background:var(--red);}
.timer-count{font-family:'Kanit',sans-serif;font-size:1.15rem;font-weight:900;color:var(--navy);white-space:nowrap;}
.timer-count.urgent{color:var(--red);}
.expired-card{background:var(--red-bg);border:1.5px solid var(--red-bd);border-radius:14px;
  padding:32px 20px;text-align:center;margin-bottom:16px;}
.exp-ico{font-size:2.5rem;margin-bottom:10px;}
.exp-title{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:900;color:var(--red);margin-bottom:6px;}
.exp-sub{font-size:.83rem;color:var(--muted);line-height:1.7;margin-bottom:14px;}
.rebook-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;
  background:var(--ink);color:#fff;border-radius:10px;font-weight:700;font-size:.85rem;}

/* nav */
.top-bar{display:flex;align-items:center;gap:10px;margin-bottom:24px;}
.back-btn{padding:8px 14px;background:var(--card);border:1px solid var(--border);border-radius:10px;
  color:var(--muted);font-size:.82rem;font-weight:600;transition:.15s;}
.back-btn:hover{border-color:var(--navy);color:var(--navy);}
.page-title{font-family:'Kanit',sans-serif;font-size:1.05rem;font-weight:800;}

/* status badge */
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;
  border-radius:999px;font-size:.75rem;font-weight:700;}
.s-unpaid{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-bd);}
.s-waiting{background:var(--navy-bg);color:var(--navy);border:1px solid var(--navy-bd);}
.s-paid{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd);}
.s-failed{background:var(--red-bg);color:var(--red);border:1px solid var(--red-bd);}
.dot{width:6px;height:6px;border-radius:50%;background:currentColor;animation:pulse 1.3s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.2}}

/* card */
.card{background:var(--card);border-radius:18px;box-shadow:var(--shadow);margin-bottom:16px;overflow:hidden;}

/* bill header */
.bill-head{background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 100%);padding:20px 22px;}
.bill-ref{font-size:.72rem;color:rgba(255,255,255,.45);letter-spacing:.06em;margin-bottom:4px;}
.bill-name{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:900;color:#fff;margin-bottom:2px;}
.bill-dates{font-size:.8rem;color:rgba(255,255,255,.65);}
.bill-meta{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;}
.bill-chip{padding:4px 12px;border-radius:999px;background:rgba(255,255,255,.12);
  font-size:.72rem;font-weight:600;color:rgba(255,255,255,.85);}

/* info rows */
.bill-body{padding:16px 20px;}
.info-row{display:flex;align-items:flex-start;gap:14px;
  padding:10px 0;border-bottom:1px solid var(--border);}
.info-row:last-child{border-bottom:none;}
.info-icon{font-size:16px;width:22px;text-align:center;flex-shrink:0;margin-top:1px;}
.info-label{font-size:.72rem;color:var(--muted);font-weight:600;margin-bottom:2px;text-transform:uppercase;letter-spacing:.05em;}
.info-value{font-size:.92rem;font-weight:700;}

/* unit pills */
.unit-pills{display:flex;flex-wrap:wrap;gap:5px;margin-top:4px;}
.unit-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;
  border-radius:999px;background:rgba(26,58,92,.1);border:1px solid rgba(26,58,92,.25);
  color:var(--navy);font-size:.72rem;font-weight:700;}

/* totals */
.bill-totals{padding:14px 20px;border-top:2px solid var(--border);}
.total-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.total-row .lbl{font-size:.85rem;color:var(--muted);}
.total-row .val{font-size:.9rem;font-weight:600;}
.total-final{display:flex;justify-content:space-between;align-items:center;
  padding-top:10px;border-top:1px dashed var(--border);margin-top:4px;}
.total-final .lbl{font-size:1rem;font-weight:700;}
.total-final .val{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:900;color:var(--gold-dark);}

/* payment status banner */
.pay-banner{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;
  border-top:1px solid var(--border);background:#fafbfd;}

/* QR */
.qr-section{padding:22px;text-align:center;}
.qr-canvas{width:190px!important;height:190px!important;border-radius:14px;
  border:2px solid var(--border);margin:0 auto 12px;}
.qr-label{font-size:.88rem;font-weight:700;margin-bottom:3px;}
.qr-hint{font-size:.72rem;color:var(--muted);margin-bottom:10px;}
.qr-amount{display:inline-block;padding:8px 24px;background:var(--navy-bg);
  border-radius:999px;font-family:'Kanit',sans-serif;font-size:1.3rem;font-weight:900;color:var(--navy);}

/* upload */
.upload-wrap{padding:16px 20px 20px;border-top:1px solid var(--border);}
.upload-label{font-size:.8rem;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px;}
.drop-zone{border:2px dashed var(--border);border-radius:14px;padding:22px 16px;
  text-align:center;cursor:pointer;transition:.2s;background:#fafbfc;position:relative;}
.drop-zone:hover,.drop-zone.drag{border-color:var(--navy);background:var(--navy-bg);}
.drop-zone input{display:none;}
.dz-icon{font-size:1.8rem;margin-bottom:6px;}
.dz-txt{font-size:.88rem;font-weight:700;margin-bottom:2px;}
.dz-hint{font-size:.72rem;color:var(--muted);}
#prevImg{max-width:100%;border-radius:10px;margin-top:12px;display:none;
  border:1px solid var(--border);max-height:200px;object-fit:contain;}
.err-box{background:var(--red-bg);border:1px solid var(--red-bd);border-radius:10px;
  padding:10px 14px;color:var(--red);font-size:.82rem;font-weight:600;
  margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.ok-box{background:var(--green-bg);border:1px solid var(--green-bd);border-radius:10px;
  padding:10px 14px;color:var(--green);font-size:.82rem;font-weight:600;
  margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.submit-btn{width:100%;margin-top:12px;padding:14px;border:none;border-radius:13px;
  background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;
  font-family:'Kanit',sans-serif;font-size:1rem;font-weight:800;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;}
.submit-btn:hover{filter:brightness(1.12);}
.submit-btn:disabled{opacity:.5;cursor:not-allowed;}

/* waiting state */
.wait-box{padding:28px 20px;text-align:center;}
.wait-ico{font-size:2.5rem;margin-bottom:12px;animation:bob .8s infinite alternate;}
@keyframes bob{from{transform:translateY(0)}to{transform:translateY(-5px)}}
.wait-title{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:6px;}
.wait-sub{font-size:.82rem;color:var(--muted);line-height:1.7;}
.wait-bar{height:4px;background:var(--border);border-radius:4px;margin:14px 0;overflow:hidden;}
.wait-inner{height:100%;background:linear-gradient(90deg,var(--navy),#7ec8f4);
  border-radius:4px;animation:load 1.8s infinite;}
@keyframes load{0%{width:0%;margin-left:0}50%{width:60%;margin-left:20%}100%{width:0%;margin-left:100%}}
.refresh-btn{margin-top:10px;padding:9px 22px;background:var(--card);
  border:1.5px solid var(--border);border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:.82rem;font-weight:700;
  color:var(--muted);cursor:pointer;transition:.15s;}
.refresh-btn:hover{border-color:var(--navy);color:var(--navy);}

/* paid */
.paid-box{padding:28px 20px;text-align:center;}
.paid-ico{font-size:3rem;margin-bottom:10px;}
.paid-title{font-family:'Kanit',sans-serif;font-size:1.15rem;font-weight:900;margin-bottom:6px;}
.paid-sub{font-size:.82rem;color:var(--muted);}

/* failed */
.fail-box{padding:28px 20px;text-align:center;}
.fail-ico{font-size:2.5rem;margin-bottom:10px;}
.fail-title{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:900;color:var(--red);margin-bottom:6px;}
.fail-sub{font-size:.82rem;color:var(--muted);line-height:1.7;margin-bottom:16px;}
.retry-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;
  background:var(--ink);color:#fff;border-radius:12px;font-family:'Kanit',sans-serif;
  font-size:.9rem;font-weight:800;transition:.2s;}
.retry-btn:hover{background:var(--red);}
</style>
</head>
<body>
<div class="page">

  <div class="top-bar">
    <a href="booking_room.php" class="back-btn">← กลับ</a>
    <span class="page-title">บิลจองห้องพัก</span>
  </div>

  <!-- BILL CARD -->
  <div class="card">
    <div class="bill-head">
      <div class="bill-ref">หมายเลขการจอง <?= htmlspecialchars($_roomBookingRef) ?></div>
      <div class="bill-name"><?= htmlspecialchars($bk['full_name']) ?></div>
      <div class="bill-dates">
        📅 <?= date('d/m/Y', strtotime($bk['checkin_date'])) ?> → <?= date('d/m/Y', strtotime($bk['checkout_date'])) ?>
        (<?= $nights ?> คืน)
      </div>
      <div class="bill-meta">
        <span class="bill-chip">📞 <?= htmlspecialchars($bk['phone']) ?></span>
        <?php if (!empty($bk['email'])): ?>
        <span class="bill-chip">✉️ <?= htmlspecialchars($bk['email']) ?></span>
        <?php endif; ?>
        <span class="bill-chip">👥 <?= (int)$bk['guests'] ?> คน</span>
      </div>
    </div>

    <!-- รายละเอียดห้อง -->
    <div class="bill-body">
      <div class="info-row">
        <div class="info-icon">🏨</div>
        <div>
          <div class="info-label">ประเภทห้องพัก</div>
          <div class="info-value"><?= htmlspecialchars($bk['room_type']) ?></div>
        </div>
      </div>

      <?php if (!empty($room_units)): ?>
      <div class="info-row">
        <div class="info-icon">🔑</div>
        <div>
          <div class="info-label">ห้องที่จอง (<?= $numRooms ?> ห้อง)</div>
          <div class="unit-pills">
            <?php foreach ($room_units as $u): ?>
              <span class="unit-pill">ห้อง <?= (int)$u ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="info-row">
        <div class="info-icon">📅</div>
        <div>
          <div class="info-label">วันเช็คอิน</div>
          <div class="info-value"><?= date('d/m/Y', strtotime($bk['checkin_date'])) ?></div>
        </div>
      </div>

      <div class="info-row">
        <div class="info-icon">📅</div>
        <div>
          <div class="info-label">วันเช็คเอาท์</div>
          <div class="info-value"><?= date('d/m/Y', strtotime($bk['checkout_date'])) ?></div>
        </div>
      </div>
    </div>

    <!-- TOTALS -->
    <div class="bill-totals">
      <div class="total-row">
        <span class="lbl">ราคาห้องต่อคืน</span>
        <span class="val">฿<?= number_format($roomPrice, 2) ?></span>
      </div>
      <div class="total-row">
        <span class="lbl"><?= $numRooms ?> ห้อง × <?= $nights ?> คืน</span>
        <span class="val">× <?= $numRooms * $nights ?></span>
      </div>
      <div class="total-final">
        <span class="lbl">ยอดรวมทั้งหมด</span>
        <span class="val">฿<?= number_format($total, 2) ?></span>
      </div>
    </div>

    <!-- PAYMENT STATUS -->
    <div class="pay-banner">
      <span style="font-size:.82rem;font-weight:600;color:var(--muted);">สถานะการชำระเงิน</span>
      <?php
        $sClass = ['unpaid'=>'s-unpaid','waiting_verify'=>'s-waiting','paid'=>'s-paid','failed'=>'s-failed','manual_review'=>'s-waiting'][$payStatus] ?? 's-unpaid';
        $sLabel = ['unpaid'=>'ยังไม่ชำระ','waiting_verify'=>'รอตรวจสอบ','paid'=>'ชำระแล้ว','failed'=>'ไม่ผ่าน','manual_review'=>'รอ Admin'][$payStatus] ?? 'ยังไม่ชำระ';
      ?>
      <span class="status-pill <?= $sClass ?>">
        <?php if (in_array($payStatus, ['waiting_verify','manual_review'])): ?><span class="dot"></span><?php endif; ?>
        <?= $sLabel ?>
      </span>
    </div>
  </div><!-- /BILL CARD -->

  <!-- PAYMENT SECTION -->
  <?php if ($payStatus === 'paid'): ?>
  <div class="card">
    <div class="paid-box">
      <div class="paid-ico">✅</div>
      <div class="paid-title">ชำระเงินเรียบร้อยแล้ว!</div>
      <div class="paid-sub">เจ้าหน้าที่ยืนยันการชำระของท่านแล้ว</div>
      <a href="room_ticket.php?id=<?= $id ?>"
         style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;padding:12px 28px;
                background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;border-radius:13px;
                font-family:'Kanit',sans-serif;font-size:.95rem;font-weight:800;text-decoration:none;">
        🧾 ดูใบเสร็จ
      </a>
    </div>
  </div>

  <?php elseif ($payStatus === 'failed'): ?>
  <div class="card">
    <div class="fail-box">
      <div class="fail-ico">❌</div>
      <div class="fail-title">สลิปไม่ผ่านการตรวจสอบ</div>
      <div class="fail-sub">
        ระบบ AI ตรวจสอบสลิปแล้วพบว่าไม่ถูกต้อง<br>
        กรุณาตรวจสอบยอดเงิน ชื่อผู้รับ และลองใหม่อีกครั้ง
      </div>
      <a href="room_bill.php?id=<?= $id ?>&retry=1" class="retry-btn">🔄 ส่งสลิปใหม่</a>
    </div>
  </div>

  <?php elseif ($payStatus === 'waiting_verify' || $payStatus === 'manual_review'): ?>
  <div class="card">
    <div class="wait-box">
      <div class="wait-ico">📤</div>
      <div class="wait-title">ได้รับสลิปแล้ว กำลังตรวจสอบ</div>
      <div class="wait-sub">ระบบ AI กำลังตรวจสอบความถูกต้องของสลิป<br>ใบเสร็จจะออกให้อัตโนมัติเมื่อผ่านการยืนยัน</div>
      <div class="wait-bar"><div class="wait-inner"></div></div>
      <button class="refresh-btn" onclick="location.reload()">🔄 รีเฟรชสถานะ</button>
    </div>
  </div>

  <?php elseif ($isExpired): ?>
  <!-- หมดเวลา -->
  <div class="expired-card">
    <div class="exp-ico">⏰</div>
    <div class="exp-title">หมดเวลาชำระเงินแล้ว</div>
    <div class="exp-sub">การจองนี้หมดอายุเนื่องจากไม่ได้ชำระเงินภายใน 5 นาที<br>กรุณาจองใหม่อีกครั้ง</div>
    <a href="booking_room.php" class="rebook-btn">🏨 จองใหม่อีกครั้ง</a>
  </div>

  <?php else: ?>
  <!-- Timer bar -->
  <div class="timer-bar" id="timerBar">
    <div>
      <div class="timer-label">⏱ เวลาชำระเงิน</div>
      <div class="timer-track">
        <div class="timer-fill" id="timerFill" style="width:100%"></div>
      </div>
    </div>
    <div class="timer-count" id="timerCount">5:00</div>
  </div>

  <!-- QR + UPLOAD -->
  <div class="card">
    <div class="qr-section">
      <canvas id="qrCanvas" class="qr-canvas"></canvas>
      <div class="qr-label">พร้อมเพย์: <?= PROMPTPAY_ID ?></div>
      <div class="qr-hint">สแกนด้วยแอปธนาคาร หรือ Mobile Banking</div>
      <div class="qr-amount">฿<?= number_format($total, 2) ?></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script>QRCode.toCanvas(document.getElementById('qrCanvas'),'<?= addslashes($qrPayload) ?>',{width:190,margin:1},function(){});</script>

    <div class="upload-wrap">
      <div class="upload-label">แนบสลิปการโอนเงิน</div>

      <?php if ($uploadError): ?>
        <div class="err-box">⚠️ <?= htmlspecialchars($uploadError) ?></div>
      <?php endif; ?>
      <?php if ($uploaded): ?>
        <div class="ok-box">✅ อัปโหลดสลิปสำเร็จแล้ว กรุณารอการยืนยัน</div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="slipForm">
        <div class="drop-zone" id="dz" onclick="document.getElementById('slipInp').click()">
          <input type="file" id="slipInp" name="slip" accept="image/*" required>
          <div class="dz-icon">📷</div>
          <div class="dz-txt">แตะเพื่อเลือกรูปสลิป</div>
          <div class="dz-hint">JPG, PNG, WEBP · สูงสุด 5MB</div>
          <img id="prevImg" src="" alt="">
        </div>
        <button type="submit" class="submit-btn" id="upBtn">
          <span>📤</span><span>ส่งสลิปยืนยันการชำระ</span>
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /page -->

<script>
/* ── Countdown timer ── */
(function(){
  const total = <?= PAY_TIMEOUT_SEC ?>;
  let left    = <?= $secondsLeft ?>;
  const countEl = document.getElementById('timerCount');
  const fillEl  = document.getElementById('timerFill');
  const barEl   = document.getElementById('timerBar');
  if (!countEl) return;
  function update() {
    if (left <= 0) { location.reload(); return; }
    const m = Math.floor(left / 60);
    const s = left % 60;
    countEl.textContent = m + ':' + String(s).padStart(2,'0');
    fillEl.style.width = (left / total * 100) + '%';
    if (left <= 60) {
      countEl.classList.add('urgent');
      fillEl.classList.add('urgent');
      barEl.classList.add('urgent');
    }
    left--;
  }
  update();
  setInterval(update, 1000);
})();

/* ── Drag & drop + preview ── */
const inp  = document.getElementById('slipInp');
const prev = document.getElementById('prevImg');
const dz   = document.getElementById('dz');
if (inp) {
  inp.addEventListener('change', function() {
    if (this.files[0]) {
      const r = new FileReader();
      r.onload = e => { prev.src = e.target.result; prev.style.display = 'block'; };
      r.readAsDataURL(this.files[0]);
    }
  });
}
if (dz) {
  dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
  dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('drag');
    inp.files = e.dataTransfer.files;
    inp.dispatchEvent(new Event('change'));
  });
  document.getElementById('slipForm').addEventListener('submit', () => {
    const b = document.getElementById('upBtn');
    b.innerHTML = '<span>⏳</span><span>กำลังส่ง...</span>';
    b.disabled = true;
  });
}

/* ── Auto-poll เมื่อสถานะ waiting_verify ── */
<?php if ($payStatus === 'waiting_verify' || $payStatus === 'manual_review'): ?>
(function() {
  const bookingId = <?= $id ?>;
  let tries = 0;
  const maxTries = 24; /* หยุดหลัง 2 นาที */
  const timer = setInterval(async () => {
    tries++;
    if (tries > maxTries) { clearInterval(timer); return; }
    try {
      const res  = await fetch('room_status_check.php?id=' + bookingId);
      const data = await res.json();
      if (data.status && data.status !== 'waiting_verify' && data.status !== 'manual_review') {
        clearInterval(timer);
        location.reload();
      }
    } catch(e) {}
  }, 5000);
})();
<?php endif; ?>
</script>
</body>
</html>
