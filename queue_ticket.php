<?php
/**
 * queue_ticket.php — แสดงบัตรคิวหลังจ่ายแล้ว
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
$conn->close();

if (!$booking) { header("Location: booking_boat.php"); exit; }
$pm = trim($booking['payment_method'] ?? '');
$isCashBooking = ($pm === 'เงินสด' || $pm === 'cash' || $pm === 'cash_paid');
if (!in_array($booking['payment_status'], ['paid','cash_paid']) && !$isCashBooking) {
    header("Location: payment_slip.php?ref=" . urlencode($booking_ref));
    exit;
}

$queueLabel = 'Q' . str_pad($booking['daily_queue_no'], 4, '0', STR_PAD_LEFT);
$bookingRef = $booking['booking_ref'];
$dateDisplay = date('d/m/Y', strtotime($booking['boat_date']));
$approvedAt  = $booking['approved_at'] ? date('d/m/Y H:i', strtotime($booking['approved_at'])) : date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>บัตรคิว <?= $queueLabel ?> — จองคิวพายเรือ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--ink:#1a1a2e;--blue:#1d6fad;--gold:#c9a96e;--bg:#f0f7ff;--card:#fff;
  --border:#dce8f5;--muted:#7a7a8c;--success:#15803d;--success-bg:#ecfdf3;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;gap:16px;}

/* ── Ticket ── */
.ticket{width:min(460px,100%);background:#fff;border-radius:24px;
  box-shadow:0 20px 60px rgba(29,111,173,.15);overflow:hidden;
  animation:ticketIn .4s cubic-bezier(.34,1.56,.64,1) both;}
@keyframes ticketIn{from{opacity:0;transform:scale(.9) translateY(20px)}to{opacity:1;transform:none}}

.ticket-head{background:linear-gradient(135deg,#0a1628 0%,#0d2344 50%,#1a3a5c 100%);
  padding:28px 28px 36px;text-align:center;position:relative;overflow:hidden;}
.ticket-head::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 30% 50%,rgba(29,111,173,.4) 0%,transparent 55%),
  radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.15) 0%,transparent 40%);}
.th-org{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.15em;text-transform:uppercase;
  position:relative;z-index:1;margin-bottom:16px;}
.th-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(21,128,61,.3);
  border:1px solid rgba(116,198,157,.4);border-radius:999px;padding:4px 12px;
  font-size:11px;font-weight:700;color:#86efac;position:relative;z-index:1;margin-bottom:14px;}
.qno-label{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.2em;text-transform:uppercase;
  position:relative;z-index:1;margin-bottom:4px;}
.qno{font-family:'Kanit',sans-serif;font-size:80px;font-weight:900;line-height:1;
  color:#fff;letter-spacing:4px;text-shadow:0 4px 24px rgba(29,111,173,.5);
  position:relative;z-index:1;}
.qno span{color:#7ec8f4;}

.notch{display:flex;align-items:center;}
.notch .circle{width:26px;height:26px;border-radius:50%;background:var(--bg);flex-shrink:0;}
.notch .line{flex:1;border-top:2px dashed var(--border);margin:0 6px;}

.ticket-body{padding:22px 26px 26px;}
.tc-name{text-align:center;margin-bottom:18px;}
.tc-name .lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.12em;margin-bottom:4px;}
.tc-name .name{font-size:22px;font-weight:800;}
.rows{display:flex;flex-direction:column;gap:8px;margin-bottom:18px;}
.row{display:flex;align-items:center;gap:12px;background:var(--bg);
  border-radius:10px;padding:10px 13px;border:1px solid var(--border);}
.r-ico{font-size:15px;width:24px;text-align:center;flex-shrink:0;}
.r-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
.r-val{font-size:13px;font-weight:700;margin-top:1px;}
.paid-badge{display:flex;justify-content:center;margin-bottom:14px;}
.paid-inner{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;
  border-radius:999px;background:var(--success-bg);border:1px solid #d1fadf;
  color:var(--success);font-size:13px;font-weight:700;}
.note-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
  padding:10px 13px;font-size:12px;color:#92400e;line-height:1.65;margin-bottom:14px;}
.btns{display:flex;gap:8px;}
.btn{flex:1;padding:12px;border-radius:12px;font-family:'Sarabun',sans-serif;
  font-size:13px;font-weight:700;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:6px;transition:.2s;text-decoration:none;}
.btn-print{background:linear-gradient(135deg,var(--gold),#e8c98a);color:var(--ink);}
.btn-home{background:var(--ink);color:#fff;}
.btn-home:hover{background:var(--blue);}
.btn-again{background:var(--success-bg);color:var(--success);border:1px solid #d1fadf;}
.ref{text-align:center;font-size:10px;color:var(--muted);margin-top:8px;}

@media print{
  body{background:#fff;padding:0;display:block;}
  .ticket{box-shadow:none;border-radius:0;border:1px solid #ddd;margin:0 auto;animation:none;}
  .btns,.ref,.no-print{display:none!important;}
  .ticket-head{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
</style>
</head>
<body>

<div class="ticket">
  <div class="ticket-head">
    <div class="th-org">🚣 สถาบันวิจัยวลัยรุกขเวช &nbsp;·&nbsp; ระบบจองคิวพายเรือ</div>
    <div class="th-badge">✓ ชำระเงินแล้ว</div>
    <div class="qno-label">หมายเลขคิวของคุณ</div>
    <div class="qno"><span>Q</span><?= str_pad($booking['daily_queue_no'], 4, '0', STR_PAD_LEFT) ?></div>
  </div>

  <div class="notch">
    <div class="circle"></div>
    <div class="line"></div>
    <div class="circle"></div>
  </div>

  <div class="ticket-body">
    <div class="tc-name">
      <div class="lbl">ชื่อผู้จอง</div>
      <div class="name"><?= htmlspecialchars($booking['full_name']) ?></div>
    </div>

    <div class="rows">
      <div class="row">
        <span class="r-ico">🚣</span>
        <div>
          <div class="r-lbl">คิว / ประเภทเรือ</div>
          <div class="r-val"><?= htmlspecialchars($booking['queue_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($booking['boat_type']) ?></div>
        </div>
      </div>
      <div class="row">
        <span class="r-ico">📅</span>
        <div>
          <div class="r-lbl">วันที่</div>
          <div class="r-val"><?= $dateDisplay ?></div>
        </div>
      </div>
      <div class="row">
        <span class="r-ico">👥</span>
        <div>
          <div class="r-lbl">จำนวนผู้เข้าร่วม</div>
          <div class="r-val"><?= (int)$booking['guests'] ?> คน</div>
        </div>
      </div>
      <div class="row">
        <span class="r-ico">💳</span>
        <div>
          <div class="r-lbl">ยอดที่ชำระ</div>
          <div class="r-val">฿<?= number_format((float)$booking['total_amount'], 2) ?></div>
        </div>
      </div>
    </div>

    <div class="paid-badge">
      <div class="paid-inner">✅ ยืนยันการจองเรียบร้อยแล้ว</div>
    </div>

    <div class="note-box">
      ⚠ กรุณาแสดงบัตรคิวนี้ต่อเจ้าหน้าที่ในวันที่มาใช้บริการ
    </div>

    <div class="btns no-print">
      <a href="booking_status.php" class="btn btn-home">← ย้อนกลับสถานะการจอง</a>
    </div>
    <div class="ref no-print"><?= htmlspecialchars($bookingRef) ?> &nbsp;·&nbsp; ยืนยัน <?= $approvedAt ?> น.</div>
  </div>
</div>

</body>
</html>
