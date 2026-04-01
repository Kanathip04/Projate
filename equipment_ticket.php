<?php
/**
 * equipment_ticket.php — สลิปยืนยันการจองเช่าอุปกรณ์
 */
session_start();
require_once 'auth_guard.php';
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: booking_tent.php"); exit; }

$st = $conn->prepare("SELECT * FROM equipment_bookings WHERE id = ? LIMIT 1");
$st->bind_param("i", $id);
$st->execute();
$bk = $st->get_result()->fetch_assoc();
$st->close();

if (!$bk) { $conn->close(); header("Location: booking_tent.php"); exit; }
if ($bk['payment_status'] !== 'paid') {
    $conn->close(); header("Location: equipment_bill.php?id=$id"); exit;
}

// คำนวณ booking ref รูปแบบ EQUIP-YYYYMMDD-NNN (นับลำดับรายวัน)
$_ts      = strtotime($bk['created_at']);
$_dateStr = date('Y-m-d', $_ts);
$_seqRes  = $conn->query("SELECT COUNT(*) AS seq FROM equipment_bookings WHERE DATE(created_at) = '$_dateStr' AND id <= $id");
$_seq     = (int)($_seqRes ? $_seqRes->fetch_assoc()['seq'] : 1);
$conn->close();

$items  = json_decode($bk['items_json'] ?? '[]', true) ?: [];
$nights = 1;
if (!empty($bk['checkin_date']) && !empty($bk['checkout_date'])) {
    $d1 = new DateTime($bk['checkin_date']);
    $d2 = new DateTime($bk['checkout_date']);
    $nights = max(1, (int)$d1->diff($d2)->days);
}
$total      = (float)$bk['total_price'] * $nights;
$bookingRef = 'EQUIP-' . date('Y', $_ts) . date('m', $_ts) . date('d', $_ts) . '-' . str_pad($_seq, 3, '0', STR_PAD_LEFT);
$approvedAt = !empty($bk['approved_at'])
    ? date('d/m/Y H:i', strtotime($bk['approved_at']))
    : date('d/m/Y H:i');
$checkinFmt  = date('d/m/Y', strtotime($bk['checkin_date']));
$checkoutFmt = date('d/m/Y', strtotime($bk['checkout_date']));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สลิปจองเต็นท์ <?= $bookingRef ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#1a1a2e;--green:#15803d;--green-bg:#ecfdf3;--green-bd:#d1fadf;
  --gold:#c9a96e;--bg:#f0fdf4;--card:#fff;--border:#d1fae5;--muted:#6b7280;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:24px 16px;gap:14px;}

/* ── Ticket ── */
.ticket{width:min(480px,100%);background:#fff;border-radius:24px;
  box-shadow:0 20px 60px rgba(21,128,61,.15);overflow:hidden;
  animation:ticketIn .4s cubic-bezier(.34,1.56,.64,1) both;}
@keyframes ticketIn{from{opacity:0;transform:scale(.9) translateY(20px)}to{opacity:1;transform:none}}

/* head */
.ticket-head{background:linear-gradient(135deg,#052e16 0%,#14532d 50%,#166534 100%);
  padding:28px 28px 36px;text-align:center;position:relative;overflow:hidden;}
.ticket-head::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 30% 50%,rgba(21,128,61,.4) 0%,transparent 55%),
  radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.15) 0%,transparent 40%);}
.th-org{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.15em;text-transform:uppercase;
  position:relative;z-index:1;margin-bottom:12px;}
.th-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(21,128,61,.35);
  border:1px solid rgba(116,198,157,.4);border-radius:999px;padding:4px 14px;
  font-size:11px;font-weight:700;color:#86efac;position:relative;z-index:1;margin-bottom:16px;}
.ref-label{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.2em;text-transform:uppercase;
  position:relative;z-index:1;margin-bottom:6px;}
.ref-no{font-family:'Kanit',sans-serif;font-size:36px;font-weight:900;line-height:1;
  color:#fff;letter-spacing:2px;position:relative;z-index:1;}
.ref-no span{color:#6ee7b7;}

/* notch */
.notch{display:flex;align-items:center;}
.notch .circle{width:26px;height:26px;border-radius:50%;background:var(--bg);flex-shrink:0;}
.notch .line{flex:1;border-top:2px dashed var(--border);margin:0 6px;}

/* body */
.ticket-body{padding:22px 24px 26px;}
.tc-name{text-align:center;margin-bottom:18px;}
.tc-name .lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.12em;margin-bottom:4px;}
.tc-name .name{font-size:22px;font-weight:800;}
.tc-name .phone{font-size:13px;color:var(--muted);margin-top:2px;}

.rows{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;}
.row{display:flex;align-items:center;gap:12px;background:#f0fdf4;
  border-radius:10px;padding:10px 13px;border:1px solid var(--border);}
.r-ico{font-size:15px;width:24px;text-align:center;flex-shrink:0;}
.r-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
.r-val{font-size:13px;font-weight:700;margin-top:1px;}

/* items */
.items-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;
  padding:14px;margin-bottom:16px;}
.items-title{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;
  letter-spacing:.1em;margin-bottom:10px;}
.item-row{display:flex;justify-content:space-between;align-items:center;
  padding:6px 0;border-bottom:1px solid #e2e8f0;font-size:13px;}
.item-row:last-child{border-bottom:none;}
.item-name{color:var(--ink);}
.item-price{font-weight:700;color:#92400e;}
.total-row{display:flex;justify-content:space-between;align-items:center;
  padding-top:10px;margin-top:4px;border-top:2px solid #e2e8f0;}
.total-row .lbl{font-size:14px;font-weight:700;}
.total-row .val{font-family:'Kanit',sans-serif;font-size:22px;font-weight:900;color:#92400e;}

.paid-badge{display:flex;justify-content:center;margin-bottom:14px;}
.paid-inner{display:inline-flex;align-items:center;gap:7px;padding:8px 20px;
  border-radius:999px;background:var(--green-bg);border:1px solid var(--green-bd);
  color:var(--green);font-size:13px;font-weight:700;}

.note-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
  padding:11px 14px;font-size:12px;color:#92400e;line-height:1.7;margin-bottom:16px;}

.btns{display:flex;gap:8px;}
.btn{flex:1;padding:12px;border-radius:12px;font-family:'Sarabun',sans-serif;
  font-size:13px;font-weight:700;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:6px;transition:.2s;text-decoration:none;}
.btn-print{background:linear-gradient(135deg,var(--gold),#e8c98a);color:var(--ink);}
.btn-home{background:var(--ink);color:#fff;}
.btn-home:hover{opacity:.85;}
.btn-again{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd);}
.foot{text-align:center;font-size:10px;color:var(--muted);margin-top:4px;}

@media print{
  body{background:#fff;padding:0;display:block;}
  .ticket{box-shadow:none;border-radius:0;border:1px solid #ddd;margin:0 auto;animation:none;}
  .btns,.foot,.no-print{display:none!important;}
  .ticket-head{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
</style>
</head>
<body>

<div class="ticket">
  <!-- HEAD -->
  <div class="ticket-head">
    <div class="th-org">⛺ สถาบันวิจัยวลัยรุกขเวช &nbsp;·&nbsp; ระบบเช่าอุปกรณ์</div>
    <div class="th-badge">✓ ชำระเงินแล้ว</div>
    <div class="ref-label">หมายเลขการจอง</div>
    <div class="ref-no"><?= htmlspecialchars($bookingRef) ?></div>
  </div>

  <!-- NOTCH -->
  <div class="notch">
    <div class="circle"></div>
    <div class="line"></div>
    <div class="circle"></div>
  </div>

  <!-- BODY -->
  <div class="ticket-body">

    <!-- ชื่อผู้เช่า -->
    <div class="tc-name">
      <div class="lbl">ชื่อผู้เช่า</div>
      <div class="name"><?= htmlspecialchars($bk['full_name']) ?></div>
      <div class="phone">📞 <?= htmlspecialchars($bk['phone']) ?>
        <?php if (!empty($bk['email'])): ?>
        &nbsp;·&nbsp; ✉️ <?= htmlspecialchars($bk['email']) ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ข้อมูลการจอง -->
    <div class="rows">
      <div class="row">
        <span class="r-ico">📅</span>
        <div>
          <div class="r-lbl">วันเข้าพัก</div>
          <div class="r-val"><?= $checkinFmt ?></div>
        </div>
      </div>
      <div class="row">
        <span class="r-ico">📅</span>
        <div>
          <div class="r-lbl">วันออก</div>
          <div class="r-val"><?= $checkoutFmt ?> (<?= $nights ?> คืน)</div>
        </div>
      </div>
    </div>

    <!-- รายการอุปกรณ์ -->
    <div class="items-card">
      <div class="items-title">รายการอุปกรณ์ที่เช่า</div>
      <?php foreach ($items as $it):
        $sub = (float)$it['price'] * (int)$it['qty'] * $nights; ?>
      <div class="item-row">
        <span class="item-name"><?= htmlspecialchars($it['name']) ?> × <?= (int)$it['qty'] ?> <?= htmlspecialchars($it['unit']) ?></span>
        <span class="item-price">฿<?= number_format($sub) ?></span>
      </div>
      <?php endforeach; ?>
      <div class="total-row">
        <span class="lbl">ยอดรวมทั้งหมด</span>
        <span class="val">฿<?= number_format($total, 2) ?></span>
      </div>
    </div>

    <!-- สถานะ -->
    <div class="paid-badge">
      <div class="paid-inner">✅ ยืนยันการจองเรียบร้อยแล้ว</div>
    </div>

    <!-- หมายเหตุ -->
    <div class="note-box">
      ⚠ กรุณาแสดงสลิปนี้แก่เจ้าหน้าที่เมื่อมาถึงในวันเข้าพัก<br>
      หากมีข้อสงสัย กรุณาติดต่อเจ้าหน้าที่ล่วงหน้า
    </div>

    <!-- ปุ่ม -->
    <div class="btns no-print">
      <button onclick="window.print()" class="btn btn-print">🖨 พิมพ์สลิป</button>
      <a href="booking_tent.php" class="btn btn-again">⛺ จองใหม่</a>
      <a href="index.php" class="btn btn-home">🏠 หน้าหลัก</a>
    </div>
    <div class="foot no-print"><?= $bookingRef ?> &nbsp;·&nbsp; ยืนยัน <?= $approvedAt ?> น.</div>

  </div><!-- /ticket-body -->
</div><!-- /ticket -->

</body>
</html>
