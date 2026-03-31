<?php
/**
 * room_ticket.php
 * ใบเสร็จรับเงินการจองห้องพัก — สถาบันวิจัยวลัยรุกขเวช
 * ธีม: navy/gold (formal receipt style)
 */
session_start();
require_once 'auth_guard.php';
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: booking_room.php"); exit; }

/* โหลดข้อมูลการจองพร้อมข้อมูลห้อง */
$st = $conn->prepare(
    "SELECT rb.*, r.room_name, r.price AS r_price, r.image_path, r.amenities, r.room_type AS r_type
     FROM room_bookings rb
     LEFT JOIN rooms r ON rb.room_id = r.id
     WHERE rb.id = ? LIMIT 1"
);
$st->bind_param("i", $id);
$st->execute();
$bk = $st->get_result()->fetch_assoc();
$st->close();
$conn->close();

if (!$bk) { header("Location: booking_room.php"); exit; }
if (($bk['payment_status'] ?? '') !== 'paid') {
    header("Location: room_bill.php?id=$id"); exit;
}

/* คำนวณข้อมูลการจอง */
$room_units = json_decode($bk['room_units'] ?? '[]', true) ?: [];
$numRooms   = max(1, count($room_units));

$nights = 1;
if (!empty($bk['checkin_date']) && !empty($bk['checkout_date'])) {
    $d1 = new DateTime($bk['checkin_date']);
    $d2 = new DateTime($bk['checkout_date']);
    $nights = max(1, (int)$d1->diff($d2)->days);
}

$roomPrice   = (float)($bk['room_price'] ?? $bk['r_price'] ?? 0);
$subtotal    = $roomPrice * $nights * $numRooms;
$total       = (float)($bk['total_price'] ?? $subtotal);
$bookingRef  = 'ROOM-' . str_pad($id, 5, '0', STR_PAD_LEFT);
$receiptDate = !empty($bk['paid_at'])
    ? date('d/m/Y H:i', strtotime($bk['paid_at']))
    : date('d/m/Y H:i');
$checkinFmt  = date('d/m/Y', strtotime($bk['checkin_date']));
$checkoutFmt = date('d/m/Y', strtotime($bk['checkout_date']));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบเสร็จรับเงิน <?= $bookingRef ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#0d1b2e;--bg:#eef2f7;--card:#fff;
  --gold:#c9a96e;--gold-dark:#a8864d;--gold-bg:rgba(201,169,110,.1);
  --navy:#1a3a5c;--navy-dark:#0a1628;
  --border:#dde4ee;--muted:#64748b;
  --green:#15803d;--green-bg:#ecfdf3;--green-bd:#d1fadf;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:24px 16px;gap:14px;}

/* ── Ticket / Receipt wrapper ── */
.receipt{width:min(500px,100%);background:#fff;border-radius:24px;
  box-shadow:0 20px 60px rgba(13,27,46,.18);overflow:hidden;
  animation:receiptIn .4s cubic-bezier(.34,1.56,.64,1) both;}
@keyframes receiptIn{from{opacity:0;transform:scale(.9) translateY(20px)}to{opacity:1;transform:none}}

/* ── Header ── */
.receipt-head{background:linear-gradient(135deg,#050f1e 0%,#0a1628 40%,#1a3a5c 100%);
  padding:30px 28px 38px;text-align:center;position:relative;overflow:hidden;}
.receipt-head::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 25% 55%,rgba(26,58,92,.5) 0%,transparent 55%),
             radial-gradient(ellipse at 80% 15%,rgba(201,169,110,.2) 0%,transparent 45%);}
.rh-org{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.18em;text-transform:uppercase;
  position:relative;z-index:1;margin-bottom:8px;}
.rh-institute{font-family:'Kanit',sans-serif;font-size:16px;font-weight:800;
  color:rgba(255,255,255,.9);position:relative;z-index:1;margin-bottom:14px;}
.rh-title{font-size:13px;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.2em;
  position:relative;z-index:1;margin-bottom:8px;}
.rh-ref{font-family:'Kanit',sans-serif;font-size:34px;font-weight:900;line-height:1.1;
  color:#fff;letter-spacing:2px;position:relative;z-index:1;margin-bottom:14px;}
.rh-ref span{color:var(--gold);}
.paid-badge{display:inline-flex;align-items:center;gap:7px;
  background:rgba(201,169,110,.2);border:1px solid rgba(201,169,110,.45);
  border-radius:999px;padding:6px 18px;
  font-size:12px;font-weight:700;color:var(--gold);
  position:relative;z-index:1;}

/* ── Notch divider ── */
.notch{display:flex;align-items:center;}
.notch .circle{width:26px;height:26px;border-radius:50%;background:var(--bg);flex-shrink:0;}
.notch .line{flex:1;border-top:2px dashed var(--border);margin:0 6px;}

/* ── Body ── */
.receipt-body{padding:24px 26px 28px;}

/* ── Section header ── */
.sec-title{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;
  letter-spacing:.12em;margin-bottom:10px;display:flex;align-items:center;gap:8px;}
.sec-title::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── Guest info block ── */
.guest-block{background:#f8fafd;border:1px solid var(--border);border-radius:14px;
  padding:14px 16px;margin-bottom:20px;}
.guest-name{font-family:'Kanit',sans-serif;font-size:19px;font-weight:800;color:var(--ink);margin-bottom:4px;}
.guest-meta{font-size:13px;color:var(--muted);display:flex;flex-wrap:wrap;gap:12px;}
.guest-meta span{display:flex;align-items:center;gap:4px;}

/* ── Info rows ── */
.info-rows{display:flex;flex-direction:column;gap:0;margin-bottom:20px;
  border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.info-row{display:flex;align-items:center;gap:12px;padding:11px 16px;
  border-bottom:1px solid var(--border);background:#fff;}
.info-row:last-child{border-bottom:none;}
.info-row:nth-child(even){background:#fafbfd;}
.r-ico{font-size:15px;width:22px;text-align:center;flex-shrink:0;}
.r-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;min-width:90px;}
.r-val{font-size:13px;font-weight:700;flex:1;text-align:right;}

/* ── Unit pills ── */
.unit-pills{display:flex;flex-wrap:wrap;gap:5px;justify-content:flex-end;}
.unit-pill{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;
  border-radius:999px;background:rgba(26,58,92,.08);border:1px solid rgba(26,58,92,.2);
  color:var(--navy);font-size:11px;font-weight:700;}

/* ── Receipt table ── */
.receipt-table{width:100%;border-collapse:collapse;margin-bottom:6px;
  border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.receipt-table th{background:linear-gradient(135deg,#0a1628,#1a3a5c);
  color:rgba(255,255,255,.85);font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.08em;padding:10px 12px;text-align:left;}
.receipt-table th:last-child{text-align:right;}
.receipt-table td{padding:12px 12px;font-size:13px;border-bottom:1px solid var(--border);
  vertical-align:middle;}
.receipt-table tr:last-child td{border-bottom:none;}
.receipt-table tr:nth-child(even) td{background:#fafbfd;}
.td-right{text-align:right;}
.td-total-row td{background:var(--gold-bg)!important;border-top:2px solid var(--border);}
.td-total-row td:last-child{font-family:'Kanit',sans-serif;font-size:18px;font-weight:900;
  color:var(--gold-dark);}
.td-total-row td:first-child{font-weight:700;font-size:14px;}

/* ── Paid badge ── */
.paid-confirm{display:flex;justify-content:center;margin:18px 0;}
.paid-inner{display:inline-flex;align-items:center;gap:8px;padding:9px 24px;
  border-radius:999px;background:var(--green-bg);border:1px solid var(--green-bd);
  color:var(--green);font-size:13px;font-weight:700;}

/* ── Note box ── */
.note-box{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;
  padding:12px 16px;font-size:12px;color:#92400e;line-height:1.7;margin-bottom:20px;}

/* ── Buttons ── */
.btns{display:flex;gap:8px;}
.btn{flex:1;padding:12px 10px;border-radius:12px;font-family:'Sarabun',sans-serif;
  font-size:13px;font-weight:700;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:.2s;text-decoration:none;}
.btn-print{background:linear-gradient(135deg,var(--gold),#e8c98a);color:var(--ink);}
.btn-print:hover{filter:brightness(1.08);}
.btn-rebook{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bd);}
.btn-rebook:hover{background:#dcfce7;}
.btn-home{background:var(--navy-dark);color:#fff;}
.btn-home:hover{opacity:.85;}

/* ── Receipt date footer ── */
.receipt-foot{text-align:center;font-size:10px;color:var(--muted);margin-top:6px;}

/* ── Print CSS ── */
@media print{
  body{background:#fff;padding:0;display:block;}
  .receipt{box-shadow:none;border-radius:0;border:1px solid #ccc;margin:0 auto;animation:none;}
  .btns,.no-print{display:none!important;}
  .receipt-head{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .receipt-table th{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
</style>
</head>
<body>

<div class="receipt">
  <!-- HEAD -->
  <div class="receipt-head">
    <div class="rh-org">สถาบันวิจัยวลัยรุกขเวช · WRBRI</div>
    <div class="rh-institute">สถาบันวิจัยวลัยรุกขเวช</div>
    <div class="rh-title">ใบเสร็จรับเงิน</div>
    <div class="rh-ref"><span>ROOM-</span><?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></div>
    <div class="paid-badge">✓ ชำระเงินแล้ว</div>
  </div>

  <!-- NOTCH -->
  <div class="notch">
    <div class="circle"></div>
    <div class="line"></div>
    <div class="circle"></div>
  </div>

  <!-- BODY -->
  <div class="receipt-body">

    <!-- ผู้เข้าพัก -->
    <div class="sec-title">ผู้เข้าพัก</div>
    <div class="guest-block">
      <div class="guest-name"><?= htmlspecialchars($bk['full_name']) ?></div>
      <div class="guest-meta">
        <span>📞 <?= htmlspecialchars($bk['phone']) ?></span>
        <?php if (!empty($bk['email'])): ?>
        <span>✉️ <?= htmlspecialchars($bk['email']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- ข้อมูลการจอง -->
    <div class="sec-title">ข้อมูลการจอง</div>
    <div class="info-rows">
      <div class="info-row">
        <span class="r-ico">🏨</span>
        <span class="r-lbl">ห้องพัก</span>
        <span class="r-val"><?= htmlspecialchars($bk['room_type']) ?></span>
      </div>
      <?php if (!empty($room_units)): ?>
      <div class="info-row">
        <span class="r-ico">🔑</span>
        <span class="r-lbl">ห้องที่</span>
        <div class="r-val">
          <div class="unit-pills">
            <?php foreach ($room_units as $u): ?>
              <span class="unit-pill">ห้อง <?= (int)$u ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="info-row">
        <span class="r-ico">📅</span>
        <span class="r-lbl">วันเช็คอิน</span>
        <span class="r-val"><?= $checkinFmt ?></span>
      </div>
      <div class="info-row">
        <span class="r-ico">📅</span>
        <span class="r-lbl">วันเช็คเอาท์</span>
        <span class="r-val"><?= $checkoutFmt ?></span>
      </div>
      <div class="info-row">
        <span class="r-ico">🌙</span>
        <span class="r-lbl">จำนวนคืน</span>
        <span class="r-val"><?= $nights ?> คืน</span>
      </div>
      <div class="info-row">
        <span class="r-ico">👥</span>
        <span class="r-lbl">ผู้เข้าพัก</span>
        <span class="r-val"><?= (int)$bk['guests'] ?> คน</span>
      </div>
    </div>

    <!-- ตารางรายการ -->
    <div class="sec-title">รายการชำระเงิน</div>
    <table class="receipt-table">
      <thead>
        <tr>
          <th>รายการ</th>
          <th style="text-align:center;">จำนวน</th>
          <th style="text-align:right;">ราคา/คืน</th>
          <th style="text-align:right;">รวม</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= htmlspecialchars($bk['room_type']) ?></td>
          <td style="text-align:center;"><?= $numRooms ?> ห้อง × <?= $nights ?> คืน</td>
          <td class="td-right">฿<?= number_format($roomPrice, 2) ?></td>
          <td class="td-right">฿<?= number_format($subtotal, 2) ?></td>
        </tr>
        <tr class="td-total-row">
          <td colspan="3">ยอดรวมทั้งหมด</td>
          <td class="td-right">฿<?= number_format($total, 2) ?></td>
        </tr>
      </tbody>
    </table>

    <!-- สถานะ -->
    <div class="paid-confirm">
      <div class="paid-inner">✅ ชำระเงินเรียบร้อยแล้ว</div>
    </div>

    <!-- หมายเหตุ -->
    <div class="note-box">
      ⚠ กรุณาแสดงใบเสร็จนี้แก่เจ้าหน้าที่เมื่อเดินทางมาถึง
      <?php if (!empty($bk['note'])): ?>
      <br>📝 <?= htmlspecialchars($bk['note']) ?>
      <?php endif; ?>
    </div>

    <!-- ปุ่ม -->
    <div class="btns no-print">
      <button onclick="window.print()" class="btn btn-print">🖨 พิมพ์ใบเสร็จ</button>
      <a href="booking_room.php" class="btn btn-rebook">🏨 จองใหม่</a>
      <a href="index.php" class="btn btn-home">🏠 หน้าหลัก</a>
    </div>

    <div class="receipt-foot no-print">
      <?= $bookingRef ?> &nbsp;·&nbsp; ชำระเงิน <?= $receiptDate ?> น.
    </div>

  </div><!-- /receipt-body -->
</div><!-- /receipt -->

</body>
</html>
