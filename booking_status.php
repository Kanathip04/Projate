<?php
session_start();
require_once 'auth_guard.php';
include 'config.php';
date_default_timezone_set('Asia/Bangkok');

$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
if ($user_email === '') die('ไม่พบ session email');

/* ══ ดึงข้อมูลทั้ง 3 ตาราง ══ */
$allBookings = [];

/* ── ห้องพัก ── */
$st = $conn->prepare("SELECT id, full_name, room_type AS title, checkin_date AS date_from, checkout_date AS date_to, guests, booking_status, created_at, NULL AS booking_ref, NULL AS payment_status, NULL AS paid_at FROM room_bookings WHERE email=? ORDER BY id DESC");
$st->bind_param("s",$user_email); $st->execute(); $res = $st->get_result();
while ($r = $res->fetch_assoc()) { $r['type']='room'; $allBookings[] = $r; }
$st->close();

/* ── เต็นท์ ── */
$st2 = $conn->prepare("SELECT id, full_name, tent_type AS title, checkin_date AS date_from, checkout_date AS date_to, guests, booking_status, created_at, NULL AS booking_ref, NULL AS payment_status, NULL AS paid_at FROM tent_bookings WHERE email=? ORDER BY id DESC");
$st2->bind_param("s",$user_email); $st2->execute(); $res2 = $st2->get_result();
while ($r = $res2->fetch_assoc()) { $r['type']='tent'; $allBookings[] = $r; }
$st2->close();

/* ── เรือ ── */
$st3 = $conn->prepare("SELECT id, full_name, queue_name AS title, boat_date AS date_from, boat_date AS date_to, NULL AS guests, booking_status, created_at, booking_ref, payment_status, paid_at FROM boat_bookings WHERE email=? ORDER BY id DESC");
$st3->bind_param("s",$user_email); $st3->execute(); $res3 = $st3->get_result();
while ($r = $res3->fetch_assoc()) { $r['type']='boat'; $allBookings[] = $r; }
$st3->close();

/* เรียงตาม created_at ล่าสุดก่อน */
usort($allBookings, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

/* ── helpers ── */
function bsText($s) {
    return ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ',
            'cancelled'=>'ยกเลิก','completed'=>'เสร็จสิ้น',
            'paid'=>'ชำระแล้ว','waiting_verify'=>'รอตรวจสลิป',
            'failed'=>'สลิปไม่ผ่าน','manual_review'=>'รอตรวจสอบ','suspicious'=>'น่าสงสัย',
            'unpaid'=>'ยังไม่ชำระ'][$s] ?? $s;
}
function bsCls($s) {
    return ['pending'=>'st-pending','approved'=>'st-approved','rejected'=>'st-rejected',
            'cancelled'=>'st-cancel','completed'=>'st-done',
            'paid'=>'st-paid','waiting_verify'=>'st-waiting','failed'=>'st-reject',
            'manual_review'=>'st-waiting','suspicious'=>'st-reject','unpaid'=>'st-pending'][$s] ?? 'st-pending';
}
function typeInfo($t) {
    return ['room'=>['icon'=>'🏨','label'=>'ห้องพัก','color'=>'#7c3aed','bg'=>'#ede9fe'],
            'tent'=>['icon'=>'⛺','label'=>'เต็นท์','color'=>'#d97706','bg'=>'#fef3c7'],
            'boat'=>['icon'=>'🚣','label'=>'พายเรือ','color'=>'#0369a1','bg'=>'#e0f2fe']][$t];
}
function thDate($s) {
    if (!$s) return '-';
    $m=['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return date('j',$ts=strtotime($s)).' '.$m[(int)date('m',$ts)].' '.(date('Y',$ts)+543);
}

/* นับสถิติ */
$counts = ['room'=>0,'tent'=>0,'boat'=>0];
$pending = 0;
foreach ($allBookings as $b) {
    $counts[$b['type']]++;
    if (in_array($b['booking_status'],['pending','waiting_verify','manual_review'])) $pending++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สถานะการจองทั้งหมด</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --ink:#0d1b2a;--gold:#c9a96e;--muted:#5f7281;
  --bg:#f0f4f8;--card:#fff;--border:#e2e8f0;
  --navy:#0d1b2a;--navy2:#1a3a5c;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);}
.container{width:min(960px,94%);margin:0 auto;}

/* HERO */
.hero{
  background:linear-gradient(135deg,#0d1b2a 0%,#1a2744 55%,#1e3a5c 100%);
  padding:36px 20px 90px;position:relative;overflow:hidden;
}
.hero::after{content:'';position:absolute;right:-100px;top:-100px;width:420px;height:420px;
  border-radius:50%;background:rgba(201,169,110,.07);pointer-events:none;}
.hero-nav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;}
.hero-nav a{
  display:inline-flex;align-items:center;gap:6px;
  padding:8px 18px;border-radius:99px;text-decoration:none;color:#fff;
  font-weight:700;font-size:.85rem;
  border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);
  transition:background .2s;
}
.hero-nav a:hover{background:rgba(255,255,255,.16);}
.hero h1{font-family:'Kanit',sans-serif;font-size:2.4rem;font-weight:900;color:#fff;margin-bottom:8px;}
.hero h1 span{color:var(--gold);}
.hero-sub{font-size:.9rem;color:rgba(255,255,255,.75);max-width:600px;}

/* STATS */
.stats-row{
  display:grid;grid-template-columns:repeat(4,1fr);gap:12px;
  margin:0 auto;width:min(960px,94%);
  margin-top:-36px;position:relative;z-index:2;
  margin-bottom:24px;
}
.stat-card{
  background:var(--card);border-radius:14px;
  box-shadow:0 4px 16px rgba(13,27,42,.1);border:1px solid var(--border);
  padding:14px 16px;text-align:center;
}
.stat-num{font-family:'Kanit',sans-serif;font-size:1.8rem;font-weight:900;}
.stat-label{font-size:.72rem;color:var(--muted);margin-top:2px;font-weight:600;}
.stat-icon{font-size:1.1rem;margin-bottom:4px;}

/* FILTER TABS */
.filter-bar{
  display:flex;gap:8px;flex-wrap:wrap;
  width:min(960px,94%);margin:0 auto 16px;
}
.f-tab{
  padding:7px 16px;border-radius:99px;border:1.5px solid var(--border);
  background:var(--card);font-size:.82rem;font-weight:700;cursor:pointer;color:var(--muted);
  transition:all .2s;
}
.f-tab:hover,.f-tab.active{border-color:var(--navy);background:var(--navy);color:#fff;}

/* CONTENT */
.content{width:min(960px,94%);margin:0 auto;padding-bottom:60px;}
.list{display:grid;gap:12px;}

/* BOOKING CARD */
.bk-card{
  background:var(--card);border-radius:16px;overflow:hidden;
  box-shadow:0 2px 10px rgba(13,27,42,.07);border:1px solid var(--border);
  transition:box-shadow .2s,transform .2s;
}
.bk-card:hover{box-shadow:0 6px 20px rgba(13,27,42,.12);transform:translateY(-1px);}
.bk-main{
  display:flex;align-items:stretch;gap:0;cursor:pointer;
}
.bk-accent{width:5px;flex-shrink:0;}
.bk-center{flex:1;padding:16px 18px;min-width:0;}
.bk-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px;}
.bk-type-chip{
  display:inline-flex;align-items:center;gap:5px;
  border-radius:99px;padding:3px 10px;font-size:.72rem;font-weight:700;
}
.bk-title{font-weight:800;font-size:.95rem;color:var(--ink);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px;}
.bk-ref{font-size:.72rem;color:var(--muted);margin-top:2px;font-family:monospace;}
.bk-metas{display:flex;gap:14px;flex-wrap:wrap;font-size:.8rem;color:var(--muted);}
.bk-meta{display:flex;align-items:center;gap:4px;}
.bk-right{padding:16px 16px 16px 0;display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;}
.bk-status{padding:4px 12px;border-radius:99px;font-size:.72rem;font-weight:700;white-space:nowrap;}

/* status colors */
.st-pending{background:#fef3c7;color:#92400e;}
.st-approved{background:#dcfce7;color:#166534;}
.st-rejected,.st-reject{background:#fee2e2;color:#991b1b;}
.st-cancel{background:#f3f4f6;color:#374151;}
.st-done{background:#dbeafe;color:#1d4ed8;}
.st-paid{background:#d1fae5;color:#065f46;}
.st-waiting{background:#fef9c3;color:#713f12;}

.detail-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 14px;border-radius:99px;font-size:.78rem;font-weight:700;
  background:var(--navy);color:#fff;border:none;cursor:pointer;
  text-decoration:none;transition:background .2s;white-space:nowrap;
}
.detail-btn:hover{background:#1e3a5c;}

/* DETAIL PANEL */
.bk-detail{
  display:none;border-top:1px solid var(--border);
  padding:16px 20px 20px;background:#f8fafc;
}
.bk-detail.open{display:block;}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;}
.d-item{background:var(--card);border-radius:10px;border:1px solid var(--border);padding:10px 12px;}
.d-label{font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;}
.d-val{font-size:.85rem;font-weight:700;color:var(--ink);}
.bk-pay-section{margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.pay-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 16px;border-radius:99px;font-size:.8rem;font-weight:700;
  background:#0369a1;color:#fff;border:none;cursor:pointer;text-decoration:none;
}
.pay-btn:hover{background:#075985;}

/* EMPTY */
.empty{
  background:var(--card);border-radius:16px;padding:60px 24px;text-align:center;
  box-shadow:0 2px 10px rgba(13,27,42,.07);border:1px solid var(--border);
}
.empty-icon{font-size:3rem;margin-bottom:14px;opacity:.4;}
.empty h3{font-size:1.2rem;font-weight:800;color:var(--ink);margin-bottom:8px;}
.empty p{color:var(--muted);font-size:.88rem;}

/* SECTION LABEL */
.section-label{
  font-family:'Kanit',sans-serif;font-size:.78rem;font-weight:800;
  color:var(--muted);text-transform:uppercase;letter-spacing:.08em;
  padding:8px 0 6px;margin-top:4px;
}

@media(max-width:600px){
  .stats-row{grid-template-columns:repeat(2,1fr);}
  .hero h1{font-size:1.8rem;}
  .bk-title{max-width:180px;}
  .detail-grid{grid-template-columns:repeat(2,1fr);}
  .bk-metas{gap:8px;}
}
</style>
</head>
<body>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <nav class="hero-nav">
      <a href="index.php">← หน้าหลัก</a>
      <a href="booking_boat.php">🚣 จองพายเรือ</a>
      <a href="booking_room.php">🏨 จองห้องพัก</a>
      <a href="booking_tent.php">⛺ จองเต็นท์</a>
    </nav>
    <h1>สถานะ<span>การจอง</span></h1>
    <p class="hero-sub">รายการจองทั้งหมดของคุณ · ห้องพัก · เต็นท์ · พายเรือ</p>
  </div>
</section>

<!-- STATS -->
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-icon">📋</div>
    <div class="stat-num" style="color:#0d1b2a;"><?= count($allBookings) ?></div>
    <div class="stat-label">รายการทั้งหมด</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🏨</div>
    <div class="stat-num" style="color:#7c3aed;"><?= $counts['room'] ?></div>
    <div class="stat-label">ห้องพัก</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⛺</div>
    <div class="stat-num" style="color:#d97706;"><?= $counts['tent'] ?></div>
    <div class="stat-label">เต็นท์</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🚣</div>
    <div class="stat-num" style="color:#0369a1;"><?= $counts['boat'] ?></div>
    <div class="stat-label">พายเรือ</div>
  </div>
</div>

<!-- FILTER -->
<div class="filter-bar">
  <button class="f-tab active" data-filter="all">ทั้งหมด (<?= count($allBookings) ?>)</button>
  <button class="f-tab" data-filter="room">🏨 ห้องพัก (<?= $counts['room'] ?>)</button>
  <button class="f-tab" data-filter="tent">⛺ เต็นท์ (<?= $counts['tent'] ?>)</button>
  <button class="f-tab" data-filter="boat">🚣 พายเรือ (<?= $counts['boat'] ?>)</button>
</div>

<!-- LIST -->
<div class="content">
  <?php if (empty($allBookings)): ?>
  <div class="empty">
    <div class="empty-icon">📭</div>
    <h3>ยังไม่มีรายการจอง</h3>
    <p>เมื่อคุณจองบริการแล้ว รายการทั้งหมดจะแสดงที่นี่</p>
  </div>
  <?php else: ?>
  <div class="list">
    <?php foreach ($allBookings as $i => $b):
      $ti   = typeInfo($b['type']);
      $bkSt = $b['booking_status'] ?? 'pending';
      $payS = $b['payment_status'] ?? null;
      $needPay = ($b['type']==='boat' && in_array($payS,['unpaid','failed',null,'']));
      $refStr = $b['booking_ref'] ? 'Ref: '.$b['booking_ref'] : '#'.$b['id'];
    ?>
    <div class="bk-card" data-btype="<?= $b['type'] ?>">
      <div class="bk-main" onclick="toggleDetail(<?= $i ?>)">
        <div class="bk-accent" style="background:<?= $ti['color'] ?>;"></div>
        <div class="bk-center">
          <div class="bk-top">
            <div>
              <span class="bk-type-chip" style="background:<?= $ti['bg'] ?>;color:<?= $ti['color'] ?>;">
                <?= $ti['icon'] ?> <?= $ti['label'] ?>
              </span>
            </div>
            <div class="bk-status <?= bsCls($bkSt) ?>"><?= bsText($bkSt) ?></div>
          </div>
          <div class="bk-title"><?= htmlspecialchars($b['title'] ?? '-') ?></div>
          <div class="bk-ref"><?= $refStr ?></div>
          <div class="bk-metas" style="margin-top:6px;">
            <?php if ($b['date_from']): ?>
            <span class="bk-meta">📅 <?= thDate($b['date_from']) ?></span>
            <?php endif; ?>
            <?php if ($b['guests']): ?>
            <span class="bk-meta">👥 <?= (int)$b['guests'] ?> คน</span>
            <?php endif; ?>
            <?php if ($b['type']==='boat' && $payS): ?>
            <span class="bk-status <?= bsCls($payS) ?>" style="font-size:.7rem;padding:2px 8px;"><?= bsText($payS) ?></span>
            <?php endif; ?>
            <span class="bk-meta" style="margin-left:auto;">🕐 <?= date('d/m/Y H:i',strtotime($b['created_at'])) ?></span>
          </div>
        </div>
        <div class="bk-right">
          <button class="detail-btn" onclick="event.stopPropagation();toggleDetail(<?= $i ?>)">
            รายละเอียด ▾
          </button>
          <?php if ($needPay): ?>
          <a href="payment_slip.php?ref=<?= urlencode($b['booking_ref']) ?>" class="pay-btn" onclick="event.stopPropagation()">
            💳 ชำระเงิน
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- DETAIL PANEL -->
      <div class="bk-detail" id="detail-<?= $i ?>">
        <div class="detail-grid">
          <div class="d-item">
            <div class="d-label">ชื่อผู้จอง</div>
            <div class="d-val"><?= htmlspecialchars($b['full_name'] ?? '-') ?></div>
          </div>
          <?php if ($b['date_from']): ?>
          <div class="d-item">
            <div class="d-label"><?= $b['type']==='boat' ? 'วันที่' : 'เช็คอิน' ?></div>
            <div class="d-val"><?= thDate($b['date_from']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($b['date_to'] && $b['date_to'] !== $b['date_from']): ?>
          <div class="d-item">
            <div class="d-label">เช็คเอาท์</div>
            <div class="d-val"><?= thDate($b['date_to']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($b['guests']): ?>
          <div class="d-item">
            <div class="d-label">จำนวนคน</div>
            <div class="d-val"><?= (int)$b['guests'] ?> คน</div>
          </div>
          <?php endif; ?>
          <div class="d-item">
            <div class="d-label">สถานะการจอง</div>
            <div class="d-val"><?= bsText($bkSt) ?></div>
          </div>
          <?php if ($b['type']==='boat' && $payS): ?>
          <div class="d-item">
            <div class="d-label">สถานะการชำระ</div>
            <div class="d-val"><?= bsText($payS) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($b['paid_at']): ?>
          <div class="d-item">
            <div class="d-label">ชำระเมื่อ</div>
            <div class="d-val"><?= date('d/m/Y H:i',strtotime($b['paid_at'])) ?></div>
          </div>
          <?php endif; ?>
          <div class="d-item">
            <div class="d-label">วันที่จอง</div>
            <div class="d-val"><?= date('d/m/Y H:i',strtotime($b['created_at'])) ?></div>
          </div>
        </div>
        <?php if ($b['type']==='boat' && $needPay): ?>
        <div class="bk-pay-section">
          <span style="font-size:.82rem;color:var(--muted);">ยังไม่ได้ชำระเงิน —</span>
          <a href="payment_slip.php?ref=<?= urlencode($b['booking_ref']) ?>" class="pay-btn">💳 ไปชำระเงิน</a>
        </div>
        <?php endif; ?>
        <?php if ($b['type']==='boat' && !empty($b['booking_ref'])): ?>
        <div style="margin-top:10px;">
          <a href="queue_ticket.php?ref=<?= urlencode($b['booking_ref']) ?>" class="detail-btn" style="background:#15803d;">🎫 ดูบัตรคิว</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleDetail(i) {
    const el = document.getElementById('detail-' + i);
    el.classList.toggle('open');
    const btn = el.previousElementSibling.querySelector('.detail-btn');
    btn.textContent = el.classList.contains('open') ? 'ซ่อน ▴' : 'รายละเอียด ▾';
}

/* Filter tabs */
document.querySelectorAll('.f-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.f-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const f = this.dataset.filter;
        document.querySelectorAll('.bk-card').forEach(card => {
            card.style.display = (f === 'all' || card.dataset.btype === f) ? '' : 'none';
        });
    });
});
</script>
</body>
</html>
