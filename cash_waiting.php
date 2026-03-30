<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ref = trim($_GET['ref'] ?? '');
$booking = null;

if ($ref !== '') {
    $st = $conn->prepare("SELECT * FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
    $st->bind_param("s", $ref);
    $st->execute();
    $booking = $st->get_result()->fetch_assoc();
    $st->close();
}

if (!$booking) {
    echo '<p style="text-align:center;padding:60px 20px;font-family:Sarabun,sans-serif;">ไม่พบข้อมูลการจอง</p>';
    $conn->close();
    exit;
}

$queueInfo = null;
if (!empty($booking['queue_id'])) {
    $qs = $conn->prepare("SELECT time_start, time_end FROM boat_queues WHERE id = ? LIMIT 1");
    $qs->bind_param("i", $booking['queue_id']);
    $qs->execute();
    $queueInfo = $qs->get_result()->fetch_assoc();
    $qs->close();
}

$conn->close();

$isPaid = ($booking['payment_status'] === 'cash_paid');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รอชำระเงินสด | สถาบันวิจัยวลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#0d1b2a;--blue:#1565c0;--blue-lt:#e3f2fd;
  --green:#15803d;--green-bg:#ecfdf3;
  --bg:#eef4fb;--muted:#5f7281;--border:#dde6f0;
  --navy:#0d1b2a;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:20px;}
.hero{background:linear-gradient(160deg,#071423 0%,#0d2344 45%,#1565c0 100%);color:#fff;padding:40px 20px;width:100%;text-align:center;border-radius:0 0 24px 24px;margin:-20px -20px 0 -20px;width:calc(100% + 40px);margin-bottom:24px;}
.hero h1{font-family:'Kanit',sans-serif;font-size:1.6rem;font-weight:900;margin-bottom:6px;}
.hero p{font-size:.9rem;opacity:.8;}

.card{background:#fff;border-radius:18px;box-shadow:0 4px 24px rgba(13,27,42,.08);width:100%;max-width:480px;overflow:hidden;}

.ref-banner{background:linear-gradient(135deg,var(--navy),#1565c0);color:#fff;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;}
.ref-label{font-size:.72rem;font-weight:700;opacity:.7;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;}
.ref-no{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:900;letter-spacing:.02em;}
.status-badge{padding:6px 14px;border-radius:99px;font-size:.75rem;font-weight:800;}
.badge-waiting{background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);}
.badge-paid{background:#dcfce7;color:#166534;}

.info-body{padding:20px 24px;}
.info-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);}
.info-row:last-child{border-bottom:none;}
.info-ico{font-size:1.1rem;width:24px;text-align:center;flex-shrink:0;}
.info-lbl{font-size:.72rem;color:var(--muted);font-weight:600;margin-bottom:2px;}
.info-val{font-size:.9rem;font-weight:700;color:var(--ink);}

.amount-row{background:var(--blue-lt);border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;margin:16px 0;}
.amount-lbl{font-size:.8rem;font-weight:600;color:var(--blue);}
.amount-val{font-family:'Kanit',sans-serif;font-size:2rem;font-weight:900;color:var(--blue);}

.instruction-box{background:#fffbeb;border:1.5px solid #fcd34d;border-radius:12px;padding:16px 18px;margin:0 0 16px;}
.instruction-box .ins-title{font-size:.85rem;font-weight:800;color:#92400e;margin-bottom:8px;}
.instruction-box ol{padding-left:18px;color:#78350f;}
.instruction-box ol li{font-size:.82rem;line-height:1.8;}

.paid-box{background:var(--green-bg);border:1.5px solid #86efac;border-radius:12px;padding:20px;text-align:center;margin:0 0 16px;}
.paid-icon{font-size:2.5rem;margin-bottom:8px;}
.paid-title{font-size:1rem;font-weight:800;color:var(--green);}
.paid-sub{font-size:.8rem;color:#166534;margin-top:4px;}
.queue-big{font-family:'Kanit',sans-serif;font-size:3rem;font-weight:900;color:var(--green);margin:8px 0;}

.btn-row{padding:0 24px 24px;display:flex;flex-direction:column;gap:8px;}
.btn-print{background:var(--navy);color:#fff;border:none;border-radius:12px;padding:14px 20px;font-family:'Sarabun',sans-serif;font-size:.9rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;}
.btn-print:hover{background:#1565c0;}
.btn-back{background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:12px;padding:12px 20px;font-family:'Sarabun',sans-serif;font-size:.85rem;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;display:block;}

.org-footer{text-align:center;font-size:.72rem;color:var(--muted);margin-top:20px;line-height:1.8;}

@media print {
  body{background:#fff;}
  .btn-row,.hero{display:none;}
  .card{box-shadow:none;border:1px solid #ddd;}
}
</style>
</head>
<body>

<div class="hero">
  <div>🚣 ระบบจองคิวพายเรือ</div>
  <h1><?= $isPaid ? 'ชำระเงินสำเร็จแล้ว' : 'รอชำระเงินสด' ?></h1>
  <p>สถาบันวิจัยวลัยรุกขเวช</p>
</div>

<div class="card">

  <!-- Booking Ref Banner -->
  <div class="ref-banner">
    <div>
      <div class="ref-label">หมายเลขการจอง</div>
      <div class="ref-no"><?= h($booking['booking_ref']) ?></div>
    </div>
    <?php if ($isPaid): ?>
      <span class="status-badge badge-paid">✓ ชำระแล้ว</span>
    <?php else: ?>
      <span class="status-badge badge-waiting">⏳ รอชำระ</span>
    <?php endif; ?>
  </div>

  <!-- Booking Info -->
  <div class="info-body">
    <div class="info-row">
      <span class="info-ico">👤</span>
      <div>
        <div class="info-lbl">ชื่อผู้จอง</div>
        <div class="info-val"><?= h($booking['full_name']) ?></div>
      </div>
    </div>
    <div class="info-row">
      <span class="info-ico">📋</span>
      <div>
        <div class="info-lbl">คิวที่จอง</div>
        <div class="info-val"><?= h($booking['queue_name']) ?></div>
      </div>
    </div>
    <div class="info-row">
      <span class="info-ico">📅</span>
      <div>
        <div class="info-lbl">วันที่</div>
        <div class="info-val">
          <?= !empty($booking['boat_date']) ? date('d/m/Y', strtotime($booking['boat_date'])) : '-' ?>
          <?php if ($queueInfo): ?>
          · <?= substr($queueInfo['time_start'],0,5) ?>–<?= substr($queueInfo['time_end'],0,5) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="info-row">
      <span class="info-ico">🛶</span>
      <div>
        <div class="info-lbl">ประเภทเรือ</div>
        <div class="info-val"><?= h($booking['boat_type'] ?: '-') ?></div>
      </div>
    </div>
    <div class="info-row">
      <span class="info-ico">👥</span>
      <div>
        <div class="info-lbl">จำนวนผู้เข้าร่วม</div>
        <div class="info-val"><?= (int)$booking['guests'] ?> คน</div>
      </div>
    </div>
  </div>

  <!-- Amount -->
  <div style="padding:0 24px;">
    <div class="amount-row">
      <div class="amount-lbl">ยอดที่ต้องชำระ (เงินสด)</div>
      <div class="amount-val">฿<?= number_format((float)($booking['total_amount'] ?? 0), 0) ?></div>
    </div>
  </div>

  <!-- Paid queue number OR instruction -->
  <?php if ($isPaid): ?>
  <div style="padding:0 24px;">
    <div class="paid-box">
      <div class="paid-icon">✅</div>
      <div class="paid-title">เจ้าหน้าที่รับเงินสดแล้ว</div>
      <?php if (!empty($booking['daily_queue_no'])): ?>
      <div class="queue-big">Q<?= str_pad((int)$booking['daily_queue_no'],4,'0',STR_PAD_LEFT) ?></div>
      <div class="paid-sub">หมายเลขคิวของคุณ — กรุณาแสดงต่อเจ้าหน้าที่</div>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div style="padding:0 24px;">
    <div class="instruction-box">
      <div class="ins-title">📌 ขั้นตอนชำระเงิน</div>
      <ol>
        <li>นำหน้าจอนี้ (หรือพิมพ์) แสดงต่อเจ้าหน้าที่</li>
        <li>ชำระเงินสดจำนวน <strong>฿<?= number_format((float)($booking['total_amount'] ?? 0), 0) ?></strong> บาท</li>
        <li>เจ้าหน้าที่จะออกบัตรคิวและยืนยันการจองให้</li>
        <li>กรุณามาถึงก่อนเวลาจอง 15 นาที</li>
      </ol>
    </div>
  </div>
  <?php endif; ?>

  <!-- Buttons -->
  <div class="btn-row">
    <button class="btn-print" onclick="window.print()">🖨 พิมพ์ / บันทึก PDF</button>
    <a href="booking_boat.php" class="btn-back">← กลับไปหน้าจอง</a>
  </div>

</div>

<div class="org-footer">
  สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม<br>
  หมายเลขการจอง: <?= h($booking['booking_ref']) ?> · วันที่จอง: <?= date('d/m/Y H:i', strtotime($booking['created_at'])) ?>
</div>

</body>
</html>
