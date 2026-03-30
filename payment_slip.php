<?php
/**
 * payment_slip.php
 * หน้าอัปโหลดสลิปของลูกค้า + แสดง QR PromptPay
 */
session_start();
require_once 'auth_guard.php';
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");

$booking_ref = trim($_GET['ref'] ?? '');
if (!$booking_ref) { header("Location: booking_boat.php"); exit; }

$stmt = $conn->prepare("SELECT * FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) { header("Location: booking_boat.php"); exit; }

if (in_array($booking['payment_status'], ['paid'])) {
    header("Location: queue_ticket.php?ref=" . urlencode($booking_ref));
    exit;
}

// ── ตรวจสอบ timer 3 นาที ──
define('PAY_TIMEOUT_SEC', 180); // 3 นาที
$createdAt    = strtotime($booking['created_at']);
$deadline     = $createdAt + PAY_TIMEOUT_SEC;
$nowTs        = time();
$secondsLeft  = max(0, $deadline - $nowTs);
$isExpired    = ($secondsLeft === 0 && !in_array($booking['payment_status'], ['paid','waiting_verify']));

if ($isExpired) {
    // ยกเลิกการจองที่หมดเวลา
    $conn->query("UPDATE boat_bookings SET payment_status='expired', booking_status='cancelled' WHERE id=" . (int)$booking['id']);
}

if ((float)$booking['total_amount'] <= 0) {
    $today = date('Y-m-d');
    $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(created_at) = '$today' AND booking_status = 'approved'");
    $qno = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;
    $conn->query("UPDATE boat_bookings SET booking_status='approved', payment_status='paid', daily_queue_no=$qno, approved_at=NOW() WHERE id=" . (int)$booking['id']);
    header("Location: queue_ticket.php?ref=" . urlencode($booking_ref));
    exit;
}

$uploadError = '';
$uploadSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slip'])) {
    $file = $_FILES['slip'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed)) {
        $uploadError = 'รองรับเฉพาะ jpg, png, webp เท่านั้น';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $uploadError = 'ไฟล์ขนาดใหญ่เกินไป (สูงสุด 5MB)';
    } else {
        $dir = 'uploads/slips/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'slip_' . $booking['id'] . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
            $slipPath   = $dir . $fname;
            $slipHash   = hash('sha256', file_get_contents($slipPath));
            $uploadedIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $uploadedUa = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $uploadedAt = date('Y-m-d H:i:s');

            // ── ตรวจสลิปซ้ำจาก hash ──
            $dupStmt = $conn->prepare("SELECT booking_ref FROM payment_slips WHERE slip_hash = ? LIMIT 1");
            $dupStmt->bind_param("s", $slipHash);
            $dupStmt->execute();
            $dupSlip = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();

            if ($dupSlip) {
                // สลิปซ้ำ — อัปเดตสถานะทันที ไม่ส่ง n8n
                $dupNote = "\n[AUTO] สลิปซ้ำกับ " . $dupSlip['booking_ref'] . " [" . date('Y-m-d H:i') . "]";
                $conn->prepare("UPDATE boat_bookings SET payment_status='duplicate', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?")
                     ->bind_param("ss", $dupNote, $booking_ref);
                $st = $conn->prepare("UPDATE boat_bookings SET payment_status='duplicate', note=CONCAT(COALESCE(note,''),?) WHERE booking_ref=?");
                $st->bind_param("ss", $dupNote, $booking_ref);
                $st->execute(); $st->close();
                $uploadError = 'สลิปนี้เคยถูกใช้แล้ว (ใน ' . $dupSlip['booking_ref'] . ')';
            } else {
                // ── บันทึกลง payment_slips ──
                $insStmt = $conn->prepare(
                    "INSERT INTO payment_slips
                     (booking_id, booking_ref, slip_image_path, slip_hash, verification_status, uploaded_ip, uploaded_ua, uploaded_at)
                     VALUES (?,?,?,?,'checking',?,?,?)"
                );
                $insStmt->bind_param("issssss",
                    $booking['id'], $booking_ref, $slipPath, $slipHash,
                    $uploadedIp, $uploadedUa, $uploadedAt
                );
                $insStmt->execute();
                $insStmt->close();

                // ── อัปเดต booking ──
                $updStmt = $conn->prepare("UPDATE boat_bookings SET payment_slip=?, payment_status='waiting_verify' WHERE id=?");
                $updStmt->bind_param("si", $slipPath, $booking['id']);
                $updStmt->execute();
                $updStmt->close();

                // ── ส่ง webhook ไป n8n ──
                $mimeMap    = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
                $mimeType   = $mimeMap[$ext] ?? 'image/jpeg';
                $slipBase64 = base64_encode(file_get_contents($slipPath));
                $expiredAt  = date('Y-m-d H:i:s', strtotime($booking['created_at']) + PAY_TIMEOUT_SEC);

                $webhookUrl = 'https://kanayhip.app.n8n.cloud/webhook/boat-slip';
                $payload = json_encode([
                    'secret'           => 'wrbri_n8n_secret_2026',
                    'booking_ref'      => $booking_ref,
                    'booking_id'       => $booking['id'],
                    'user_name'        => $booking['full_name'],
                    'expected_amount'  => (float)$booking['total_amount'],
                    'amount_tolerance' => 1.00,
                    'promptpay_id'     => PROMPTPAY_ID,
                    'booking_created'  => $booking['created_at'],
                    'expired_at'       => $expiredAt,
                    'payment_channel'  => 'promptpay',
                    'slip_hash'        => $slipHash,
                    'slip_url'         => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/' . $slipPath,
                    'callback_url'     => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/payment_callback.php',
                    'slip_base64'      => $slipBase64,
                    'slip_mime_type'   => $mimeType,
                ]);
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                curl_exec($ch);
                curl_close($ch);

                header("Location: payment_slip.php?ref=" . urlencode($booking_ref));
                exit;
            }
        } else {
            $uploadError = 'อัปโหลดไม่สำเร็จ กรุณาลองใหม่';
        }
    }
    $stmt2 = $conn->prepare("SELECT * FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
    $stmt2->bind_param("s", $booking_ref);
    $stmt2->execute();
    $booking = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
}

define('PROMPTPAY_ID', '0622301236');

function promptpayPayload(string $target, float $amount): string
{
    $target = preg_replace('/\D/', '', $target);
    if (strlen($target) === 10 && $target[0] === '0') {
        $target = '0066' . substr($target, 1);
    }
    $isPhone   = strlen($target) === 13;
    $subTag    = $isPhone ? '01' : '02';
    $subLen    = str_pad(strlen($target), 2, '0', STR_PAD_LEFT);
    $guid      = 'A000000677010111';
    $guidTLV   = '00' . str_pad(strlen($guid), 2, '0', STR_PAD_LEFT) . $guid;
    $phoneTLV  = $subTag . $subLen . $target;
    $merchant  = $guidTLV . $phoneTLV;
    $tag29     = '29' . str_pad(strlen($merchant), 2, '0', STR_PAD_LEFT) . $merchant;
    $amountStr = number_format($amount, 2, '.', '');
    $amtTLV    = '54' . str_pad(strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;
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

$qrUrl = 'uploads/QRcode.jpg';
$qrPayload = promptpayPayload(PROMPTPAY_ID, (float)$booking['total_amount']);
$statusMap = [
    'paid'           => ['label' => 'ชำระแล้ว',          'class' => 'paid'],
    'waiting_verify' => ['label' => 'รอตรวจสอบสลิป',     'class' => 'waiting'],
    'failed'         => ['label' => 'สลิปไม่ผ่าน',        'class' => 'failed'],
];
$st = $statusMap[$booking['payment_status']] ?? ['label' => 'ยังไม่ชำระ', 'class' => 'unpaid'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ชำระเงิน — จองคิวพายเรือ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#0f172a;--blue:#1d6fad;--blue-light:#e0f0fb;
  --gold:#c9a96e;--bg:#f1f5f9;--white:#fff;
  --border:#e2e8f0;--muted:#64748b;
  --green:#16a34a;--green-bg:#f0fdf4;--green-border:#bbf7d0;
  --yellow:#d97706;--yellow-bg:#fffbeb;--yellow-border:#fde68a;
  --red:#dc2626;--red-bg:#fef2f2;--red-border:#fca5a5;
  --shadow:0 4px 24px rgba(15,23,42,.08);
  --shadow-lg:0 12px 48px rgba(15,23,42,.12);
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}

/* ── Layout ── */
.page{max-width:480px;margin:0 auto;padding:20px 16px 48px;}

/* ── Header bar ── */
.top-bar{display:flex;align-items:center;gap:10px;margin-bottom:24px;}
.back-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;
  background:var(--white);border:1px solid var(--border);border-radius:10px;
  color:var(--muted);font-size:.82rem;font-weight:600;text-decoration:none;
  transition:.15s;}
.back-btn:hover{border-color:var(--blue);color:var(--blue);}
.page-title{font-family:'Kanit',sans-serif;font-size:1.05rem;font-weight:800;color:var(--ink);}

/* ── Step indicator ── */
.steps{display:flex;align-items:center;margin-bottom:20px;}
.step-item{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;}
.step-item:not(:last-child)::after{
  content:'';position:absolute;top:13px;left:calc(50% + 14px);
  width:calc(100% - 28px);height:2px;background:var(--border);}
.step-item.done:not(:last-child)::after{background:var(--green);}
.step-item.active:not(:last-child)::after{background:var(--border);}
.sc{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-size:.72rem;font-weight:800;margin-bottom:5px;z-index:1;}
.step-item.done .sc{background:var(--green);color:#fff;}
.step-item.active .sc{background:var(--blue);color:#fff;box-shadow:0 0 0 4px var(--blue-light);}
.step-item.wait .sc{background:var(--border);color:var(--muted);}
.sl{font-size:.65rem;font-weight:700;color:var(--muted);}
.step-item.active .sl{color:var(--blue);}
.step-item.done .sl{color:var(--green);}

/* ── Card ── */
.card{background:var(--white);border-radius:18px;box-shadow:var(--shadow);margin-bottom:14px;overflow:hidden;}

/* ── Booking info ── */
.bk-head{background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 100%);
  padding:18px 20px;display:flex;justify-content:space-between;align-items:center;}
.bk-ref{font-size:.75rem;color:rgba(255,255,255,.5);letter-spacing:.05em;}
.bk-name{font-family:'Kanit',sans-serif;font-size:1rem;font-weight:800;color:#fff;margin-top:2px;}
.bk-amount{text-align:right;}
.bk-amount .lbl{font-size:.65rem;color:rgba(255,255,255,.5);margin-bottom:2px;}
.bk-amount .val{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:900;color:#7ec8f4;}

.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
.info-cell{padding:12px 20px;border-bottom:1px solid var(--border);border-right:1px solid var(--border);}
.info-cell:nth-child(2n){border-right:none;}
.info-cell:nth-last-child(-n+2){border-bottom:none;}
.info-cell .lbl{font-size:.67rem;color:var(--muted);font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;margin-bottom:3px;}
.info-cell .val{font-size:.88rem;font-weight:700;}

/* Status */
.status-row{padding:12px 20px;display:flex;align-items:center;justify-content:space-between;
  border-top:1px solid var(--border);}
.status-row .lbl{font-size:.78rem;color:var(--muted);font-weight:600;}
.badge{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;
  border-radius:999px;font-size:.75rem;font-weight:700;}
.badge.paid{background:var(--green-bg);color:var(--green);border:1px solid var(--green-border);}
.badge.waiting{background:var(--blue-light);color:var(--blue);border:1px solid #bfdbfe;}
.badge.unpaid{background:var(--yellow-bg);color:var(--yellow);border:1px solid var(--yellow-border);}
.badge.failed{background:var(--red-bg);color:var(--red);border:1px solid var(--red-border);}
.dot{width:6px;height:6px;border-radius:50%;background:currentColor;animation:pulse 1.4s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}

/* ── QR section ── */
.qr-wrap{padding:20px;text-align:center;}
.qr-img{width:180px;height:180px;border-radius:14px;
  border:2px solid var(--border);object-fit:cover;margin:0 auto 12px;}
.qr-ppid{font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:4px;}
.qr-hint{font-size:.72rem;color:var(--muted);}
.qr-amt{display:inline-block;margin-top:10px;padding:8px 24px;
  background:var(--blue-light);border-radius:999px;
  font-family:'Kanit',sans-serif;font-size:1.3rem;font-weight:900;color:var(--blue);}

/* ── Upload ── */
.upload-wrap{padding:16px 20px 20px;}
.upload-title{font-size:.82rem;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;}
.drop-zone{border:2px dashed var(--border);border-radius:14px;padding:24px 16px;
  text-align:center;cursor:pointer;transition:.2s;background:#fafbfc;position:relative;}
.drop-zone:hover,.drop-zone.drag{border-color:var(--blue);background:var(--blue-light);}
.drop-zone input{display:none;}
.dz-icon{font-size:2rem;margin-bottom:8px;}
.dz-label{font-size:.88rem;font-weight:700;color:var(--ink);margin-bottom:3px;}
.dz-hint{font-size:.72rem;color:var(--muted);}
#previewImg{max-width:100%;border-radius:10px;margin-top:14px;display:none;
  border:1px solid var(--border);max-height:220px;object-fit:contain;}

.err-box{background:var(--red-bg);border:1px solid var(--red-border);border-radius:10px;
  padding:10px 14px;color:var(--red);font-size:.82rem;font-weight:600;
  margin-bottom:12px;display:flex;align-items:center;gap:8px;}

.submit-btn{width:100%;margin-top:14px;padding:14px;border:none;border-radius:13px;
  background:linear-gradient(135deg,#0a1628,#1d6fad);
  color:#fff;font-family:'Kanit',sans-serif;font-size:1rem;font-weight:800;
  cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.submit-btn:hover{filter:brightness(1.1);}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;}

/* ── Waiting state ── */
.wait-box{padding:28px 20px;text-align:center;}
.wait-anim{font-size:2.8rem;margin-bottom:14px;animation:bounce .8s infinite alternate;}
@keyframes bounce{from{transform:translateY(0)}to{transform:translateY(-6px)}}
.wait-title{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:6px;}
.wait-sub{font-size:.82rem;color:var(--muted);line-height:1.7;}
.wait-bar{height:4px;background:var(--border);border-radius:4px;margin:16px 0;overflow:hidden;}
.wait-bar-inner{height:100%;background:linear-gradient(90deg,var(--blue),#7ec8f4);
  border-radius:4px;animation:loading 1.8s infinite;}
@keyframes loading{0%{width:0%;margin-left:0}50%{width:60%;margin-left:20%}100%{width:0%;margin-left:100%}}
.refresh-btn{margin-top:14px;padding:9px 22px;background:var(--white);
  border:1.5px solid var(--border);border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:.82rem;font-weight:700;
  color:var(--muted);cursor:pointer;transition:.15s;}
.refresh-btn:hover{border-color:var(--blue);color:var(--blue);}

/* ── Paid state ── */
.paid-box{padding:28px 20px;text-align:center;}
.paid-icon{font-size:3rem;margin-bottom:12px;}
.paid-title{font-family:'Kanit',sans-serif;font-size:1.15rem;font-weight:900;margin-bottom:6px;}
.paid-sub{font-size:.82rem;color:var(--muted);margin-bottom:20px;}
.ticket-btn{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;
  background:linear-gradient(135deg,var(--green),#22c55e);
  color:#fff;border-radius:13px;font-family:'Kanit',sans-serif;
  font-size:1rem;font-weight:800;text-decoration:none;transition:.2s;}
.ticket-btn:hover{filter:brightness(1.1);}

/* ── Timer ── */
.timer-bar{display:flex;align-items:center;justify-content:space-between;
  background:var(--white);border-radius:12px;padding:10px 16px;
  margin-bottom:14px;border:1px solid var(--border);}
.timer-bar.urgent{border-color:#fca5a5;background:#fff5f5;}
.timer-label{font-size:.78rem;color:var(--muted);font-weight:600;}
.timer-count{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:900;color:var(--blue);}
.timer-count.urgent{color:var(--red);}
.timer-track{height:4px;background:var(--border);border-radius:4px;margin-top:6px;overflow:hidden;}
.timer-fill{height:100%;background:var(--blue);border-radius:4px;transition:width 1s linear;}
.timer-fill.urgent{background:var(--red);}

/* ── Expired ── */
.expired-box{padding:32px 20px;text-align:center;}
.exp-icon{font-size:3rem;margin-bottom:12px;}
.exp-title{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:900;margin-bottom:6px;color:var(--red);}
.exp-sub{font-size:.82rem;color:var(--muted);margin-bottom:20px;line-height:1.7;}
.rebook-btn{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;
  background:linear-gradient(135deg,var(--ink),#1a3a5c);
  color:#fff;border-radius:13px;font-family:'Kanit',sans-serif;
  font-size:1rem;font-weight:800;text-decoration:none;}
</style>
</head>
<body>
<div class="page">

  <!-- Top bar -->
  <div class="top-bar">
    <a href="booking_boat.php" class="back-btn">← กลับ</a>
    <span class="page-title">ชำระเงิน</span>
  </div>

  <!-- Steps -->
  <div class="steps">
    <div class="step-item done">
      <div class="sc">✓</div><div class="sl">กรอกข้อมูล</div>
    </div>
    <div class="step-item <?= in_array($booking['payment_status'], ['waiting_verify','paid']) ? 'done' : 'active' ?>">
      <div class="sc">2</div><div class="sl">ชำระเงิน</div>
    </div>
    <div class="step-item <?= $booking['payment_status'] === 'paid' ? 'done' : 'wait' ?>">
      <div class="sc">3</div><div class="sl">รับบัตรคิว</div>
    </div>
  </div>

  <!-- Booking card -->
  <div class="card">
    <div class="bk-head">
      <div>
        <div class="bk-ref"><?= htmlspecialchars($booking['booking_ref']) ?></div>
        <div class="bk-name"><?= htmlspecialchars($booking['full_name']) ?></div>
      </div>
      <div class="bk-amount">
        <div class="lbl">ยอดชำระ</div>
        <div class="val">฿<?= number_format((float)$booking['total_amount'], 2) ?></div>
      </div>
    </div>
    <div class="info-grid">
      <div class="info-cell">
        <div class="lbl">คิว</div>
        <div class="val"><?= htmlspecialchars($booking['queue_name']) ?></div>
      </div>
      <div class="info-cell">
        <div class="lbl">ประเภทเรือ</div>
        <div class="val"><?= htmlspecialchars($booking['boat_type']) ?></div>
      </div>
      <div class="info-cell">
        <div class="lbl">วันที่</div>
        <div class="val"><?= date('d/m/Y', strtotime($booking['boat_date'])) ?></div>
      </div>
      <div class="info-cell">
        <div class="lbl">จำนวนคน</div>
        <div class="val"><?= (int)$booking['guests'] ?> คน</div>
      </div>
    </div>
    <div class="status-row">
      <span class="lbl">สถานะการชำระ</span>
      <span class="badge <?= $st['class'] ?>">
        <?php if (in_array($booking['payment_status'], ['waiting_verify'])): ?>
          <span class="dot"></span>
        <?php endif; ?>
        <?= $st['label'] ?>
      </span>
    </div>
  </div>

  <?php if ($booking['payment_status'] === 'paid'): ?>
  <!-- ── จ่ายแล้ว ── -->
  <div class="card">
    <div class="paid-box">
      <div class="paid-icon">🎉</div>
      <div class="paid-title">ชำระเงินเรียบร้อยแล้ว!</div>
      <div class="paid-sub">ระบบยืนยันการชำระของคุณแล้ว</div>
      <a href="queue_ticket.php?ref=<?= urlencode($booking_ref) ?>" class="ticket-btn">
        🎫 รับบัตรคิว
      </a>
    </div>
  </div>

  <?php elseif ($booking['payment_status'] === 'waiting_verify'): ?>
  <!-- ── รอตรวจสอบ ── -->
  <div class="card">
    <div class="wait-box">
      <div class="wait-anim">📤</div>
      <div class="wait-title">ได้รับสลิปแล้ว!</div>
      <div class="wait-sub">ระบบกำลังตรวจสอบการชำระเงินอัตโนมัติ<br>ใช้เวลาประมาณ 1-3 นาที</div>
      <div class="wait-bar"><div class="wait-bar-inner"></div></div>
      <button class="refresh-btn" onclick="location.reload()">🔄 ตรวจสอบสถานะ</button>
    </div>
  </div>
  <script>setTimeout(() => location.reload(), 5000);</script>

  <?php elseif ($isExpired || $booking['payment_status'] === 'expired'): ?>
  <!-- ── หมดเวลา ── -->
  <div class="card">
    <div class="expired-box">
      <div class="exp-icon">⏰</div>
      <div class="exp-title">หมดเวลาชำระเงินแล้ว</div>
      <div class="exp-sub">การจองนี้หมดอายุแล้ว เนื่องจากไม่ได้ชำระเงินภายใน 3 นาที<br>กรุณาจองใหม่อีกครั้ง</div>
      <a href="booking_boat.php" class="rebook-btn">🚣 จองใหม่อีกครั้ง</a>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Timer ── -->
  <div class="timer-bar" id="timerBar">
    <div>
      <div class="timer-label">⏱ เวลาชำระเงิน</div>
      <div class="timer-track" style="width:200px;">
        <div class="timer-fill" id="timerFill" style="width:100%"></div>
      </div>
    </div>
    <div class="timer-count" id="timerCount">3:00</div>
  </div>

  <!-- ── QR + Upload ── -->
  <div class="card">
    <div class="qr-wrap">
      <canvas id="qrCanvas" class="qr-img"></canvas>
      <div class="qr-ppid">พร้อมเพย์: <?= PROMPTPAY_ID ?></div>
      <div class="qr-hint">สแกนด้วยแอปธนาคาร หรือ Mobile Banking</div>
      <div class="qr-amt">฿<?= number_format((float)$booking['total_amount'], 2) ?></div>
      <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
      <script>
        QRCode.toCanvas(document.getElementById('qrCanvas'), '<?= addslashes($qrPayload) ?>', {width:180,margin:1}, function(err){});
      </script>
    </div>

    <div class="upload-wrap">
      <div class="upload-title">แนบสลิปการโอนเงิน</div>

      <?php if ($uploadError): ?>
        <div class="err-box">⚠ <?= htmlspecialchars($uploadError) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="slipForm">
        <div class="drop-zone" id="uploadZone" onclick="document.getElementById('slipInput').click()">
          <input type="file" id="slipInput" name="slip" accept="image/*" required>
          <div class="dz-icon">📷</div>
          <div class="dz-label">แตะเพื่อเลือกรูปสลิป</div>
          <div class="dz-hint">JPG, PNG, WEBP · สูงสุด 5MB</div>
          <img id="previewImg" src="" alt="preview">
        </div>
        <button type="submit" class="submit-btn" id="uploadBtn">
          <span>📤</span><span>ส่งสลิปยืนยันการชำระ</span>
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
// ── Countdown timer ──
(function(){
  const total = <?= PAY_TIMEOUT_SEC ?>;
  let left  = <?= $secondsLeft ?>;
  const countEl = document.getElementById('timerCount');
  const fillEl  = document.getElementById('timerFill');
  const barEl   = document.getElementById('timerBar');
  if (!countEl) return;

  function update() {
    if (left <= 0) { location.reload(); return; }
    const m = Math.floor(left / 60);
    const s = left % 60;
    countEl.textContent = m + ':' + String(s).padStart(2,'0');
    const pct = (left / total) * 100;
    fillEl.style.width = pct + '%';
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

const input   = document.getElementById('slipInput');
const preview = document.getElementById('previewImg');
const zone    = document.getElementById('uploadZone');

if (input) {
  input.addEventListener('change', function() {
    if (this.files[0]) {
      const reader = new FileReader();
      reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(this.files[0]);
    }
  });
}
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag');
    input.files = e.dataTransfer.files;
    input.dispatchEvent(new Event('change'));
  });
}
const form = document.getElementById('slipForm');
if (form) {
  form.addEventListener('submit', () => {
    const btn = document.getElementById('uploadBtn');
    btn.innerHTML = '<span>⏳</span><span>กำลังส่งสลิป...</span>';
    btn.disabled = true;
  });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
