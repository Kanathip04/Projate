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

$isPaid    = ($booking['payment_status'] === 'cash_paid');
$amount    = (float)($booking['total_amount'] ?? 0);
$boatDate  = !empty($booking['boat_date']) ? date('d/m/Y', strtotime($booking['boat_date'])) : '-';
$timeRange = $queueInfo ? substr($queueInfo['time_start'],0,5).'–'.substr($queueInfo['time_end'],0,5) : '00:00–23:59';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รอชำระเงินสด | สถาบันวิจัยวลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
    --bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;
    --border:#e8e4de;--success:#15803d;--success-bg:#ecfdf3;
}
body{font-family:'Sarabun','Segoe UI',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.page{max-width:560px;width:100%;}

.top-bar{
    background:linear-gradient(135deg,#071423 0%,#0d2344 50%,#1565c0 100%);
    border-radius:22px 22px 0 0;padding:32px 32px 28px;text-align:center;position:relative;overflow:hidden;
}
.top-bar::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse at 20% 50%,rgba(21,111,173,.35) 0%,transparent 60%),
                radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.12) 0%,transparent 50%);
    pointer-events:none;
}
.checkmark{width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:32px;position:relative;z-index:1;animation:pop .4s cubic-bezier(.175,.885,.32,1.275) both;}
@keyframes pop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
.top-bar h1{font-size:24px;font-weight:800;color:#fff;margin-bottom:6px;position:relative;z-index:1;}
.top-bar p{font-size:14px;color:rgba(255,255,255,.72);position:relative;z-index:1;}
.booking-ref{display:inline-block;margin-top:14px;padding:6px 18px;border-radius:999px;background:rgba(201,169,110,.18);border:1px solid rgba(201,169,110,.4);color:var(--gold);font-size:13px;font-weight:700;position:relative;z-index:1;}

.card-body{background:var(--card);border:1px solid var(--border);border-top:none;border-radius:0 0 22px 22px;padding:28px 28px 32px;box-shadow:0 12px 30px rgba(26,26,46,.08);}

.info-grid{display:flex;flex-direction:column;gap:12px;margin-bottom:24px;}
.info-row{display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:var(--bg);border:1px solid var(--border);border-radius:14px;}
.info-icon{width:36px;height:36px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:17px;background:rgba(26,26,46,.06);}
.info-label{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:3px;}
.info-value{font-size:15px;font-weight:700;color:var(--ink);}
.info-value.amount{font-size:22px;color:#1565c0;}

.note-box{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 16px;font-size:13px;color:#92400e;line-height:1.7;margin-bottom:22px;}
.note-box ol{padding-left:18px;margin-top:6px;}
.note-box .note-title{font-weight:800;margin-bottom:4px;}

.paid-box{background:var(--success-bg);border:1px solid #86efac;border-radius:14px;padding:20px;text-align:center;margin-bottom:22px;}
.paid-icon{font-size:2.5rem;margin-bottom:8px;}
.paid-title{font-size:1rem;font-weight:800;color:var(--success);}
.queue-big{font-family:'Kanit',sans-serif;font-size:3rem;font-weight:900;color:var(--success);margin:8px 0;}

.btn-group{display:flex;gap:10px;}
.btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:14px 18px;border-radius:14px;font-family:'Sarabun',sans-serif;font-size:15px;font-weight:700;text-decoration:none;transition:all .2s;}
.btn-primary{background:linear-gradient(135deg,#071423 0%,#1565c0 100%);color:#fff;}
.btn-primary:hover{opacity:.88;}
.btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.btn-ghost:hover{border-color:var(--gold);color:var(--gold-dark);}

.org-footer{text-align:center;font-size:.72rem;color:var(--muted);margin-top:16px;line-height:1.8;}

@media(max-width:480px){
    .top-bar{padding:26px 20px 22px;}
    .card-body{padding:20px 18px 26px;}
    .btn-group{flex-direction:column;}
}
@media print{body{background:#fff;}.btn-group,.org-footer{display:none;}.card-body{box-shadow:none;}}
</style>
</head>
<body>
<div class="page">

    <div class="top-bar">
        <div class="checkmark"><?= $isPaid ? '✓' : '🚣' ?></div>
        <h1><?= $isPaid ? 'ชำระเงินสำเร็จแล้ว' : 'ส่งคำขอจองสำเร็จ' ?></h1>
        <p>ระบบบันทึกข้อมูลการจองเรียบร้อยแล้ว</p>
        <div class="booking-ref">หมายเลขการจอง <?= h($booking['booking_ref']) ?></div>
    </div>

    <div class="card-body">
        <div class="info-grid">
            <div class="info-row">
                <div class="info-icon">👤</div>
                <div>
                    <div class="info-label">ชื่อผู้จอง</div>
                    <div class="info-value"><?= h($booking['full_name']) ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon">📋</div>
                <div>
                    <div class="info-label">คิวที่จอง</div>
                    <div class="info-value"><?= h($booking['queue_name']) ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon">📅</div>
                <div>
                    <div class="info-label">วันที่</div>
                    <div class="info-value"><?= $boatDate ?> · <?= $timeRange ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon">🛶</div>
                <div>
                    <div class="info-label">ประเภทเรือ</div>
                    <div class="info-value"><?= h($booking['boat_type'] ?: '-') ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon">👥</div>
                <div>
                    <div class="info-label">จำนวนผู้เข้าร่วม</div>
                    <div class="info-value"><?= (int)$booking['guests'] ?> คน</div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon">💵</div>
                <div>
                    <div class="info-label">ยอดที่ต้องชำระ (เงินสด)</div>
                    <div class="info-value amount">฿<?= number_format($amount, 0) ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon">💳</div>
                <div>
                    <div class="info-label">วิธีชำระ</div>
                    <div class="info-value">เงินสด (ชำระกับเจ้าหน้าที่)</div>
                </div>
            </div>
        </div>

        <?php if ($isPaid): ?>
        <div class="paid-box">
            <div class="paid-icon">✅</div>
            <div class="paid-title">เจ้าหน้าที่รับเงินสดแล้ว</div>
            <?php if (!empty($booking['daily_queue_no'])): ?>
            <div class="queue-big">Q<?= str_pad((int)$booking['daily_queue_no'],4,'0',STR_PAD_LEFT) ?></div>
            <div style="font-size:.8rem;color:#166534;">หมายเลขคิวของคุณ — กรุณาแสดงต่อเจ้าหน้าที่</div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="note-box">
            <div class="note-title">📌 ขั้นตอนชำระเงิน</div>
            <ol>
                <li>นำหน้าจอนี้ (หรือพิมพ์) แสดงต่อเจ้าหน้าที่</li>
                <li>ชำระเงินสดจำนวน <strong>฿<?= number_format($amount, 0) ?></strong> บาท</li>
                <li>เจ้าหน้าที่จะออกบัตรคิวและยืนยันการจองให้</li>
                <li>กรุณามาถึงก่อนเวลาจอง 15 นาที</li>
            </ol>
        </div>
        <?php endif; ?>

        <div class="btn-group">
            <a href="booking_boat.php" class="btn btn-primary">🚣 จองเพิ่ม</a>
            <a href="booking_status.php" class="btn btn-ghost">📋 ติดตามสถานะ</a>
        </div>
    </div>

    <div class="org-footer">
        สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม<br>
        หมายเลขการจอง: <?= h($booking['booking_ref']) ?> · วันที่จอง: <?= date('d/m/Y H:i', strtotime($booking['created_at'])) ?>
    </div>

</div>
</body>
</html>
