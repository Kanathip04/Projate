<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

function tableExists($conn, $t) {
    $t = $conn->real_escape_string($t);
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    return ($r && $r->num_rows > 0);
}
function getColumns($conn, $t) {
    $cols = [];
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    $r = $conn->query("SHOW COLUMNS FROM `$t`");
    if ($r) while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    return $cols;
}

if (!tableExists($conn, 'rooms')) die("ไม่พบตาราง rooms");

$rc             = getColumns($conn, 'rooms');
$hasRoomType    = in_array('room_type',   $rc, true);
$hasPrice       = in_array('price',       $rc, true);
$hasBedType     = in_array('bed_type',    $rc, true);
$hasMaxGuests   = in_array('max_guests',  $rc, true);
$hasImagePath   = in_array('image_path',  $rc, true);
$hasDescription = in_array('description', $rc, true);
$hasStatus      = in_array('status',      $rc, true);
$hasTotalRooms  = in_array('total_rooms', $rc, true);
$hasAmenities   = in_array('amenities',   $rc, true);

$approvedMap = [];
if (tableExists($conn, 'room_bookings')) {
    $bc = getColumns($conn, 'room_bookings');
    if (in_array('room_id', $bc, true) && in_array('booking_status', $bc, true)) {
        $hasUnits = in_array('room_units', $bc, true);
        $sql = $hasUnits
            ? "SELECT room_id, SUM(CASE WHEN room_units IS NOT NULL AND room_units!='' THEN JSON_LENGTH(room_units) ELSE 1 END) AS n FROM room_bookings WHERE booking_status='approved' GROUP BY room_id"
            : "SELECT room_id, COUNT(*) AS n FROM room_bookings WHERE booking_status='approved' GROUP BY room_id";
        $res = $conn->query($sql);
        if ($res) while ($row = $res->fetch_assoc()) $approvedMap[(int)$row['room_id']] = (int)$row['n'];
    }
}

$fields = ['id', 'room_name'];
if ($hasRoomType)    $fields[] = 'room_type';
if ($hasPrice)       $fields[] = 'price';
if ($hasBedType)     $fields[] = 'bed_type';
if ($hasMaxGuests)   $fields[] = 'max_guests';
if ($hasImagePath)   $fields[] = 'image_path';
if ($hasDescription) $fields[] = 'description';
if ($hasTotalRooms)  $fields[] = 'total_rooms';
if ($hasStatus)      $fields[] = 'status';
if ($hasAmenities)   $fields[] = 'amenities';

$sql = "SELECT " . implode(',', $fields) . " FROM rooms WHERE 1=1";
if ($hasStatus) $sql .= " AND status='show'";
$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->execute();
$result = $stmt->get_result();

$checkin_q  = trim($_GET['checkin']  ?? '');
$checkout_q = trim($_GET['checkout'] ?? '');
$guests_q   = max(1, (int)($_GET['guests'] ?? 1));
if (!$checkin_q)  $checkin_q  = date('Y-m-d');
if (!$checkout_q || $checkout_q <= $checkin_q)
    $checkout_q = date('Y-m-d', strtotime($checkin_q . ' +1 day'));
$nights_q = max(1, (int)((strtotime($checkout_q) - strtotime($checkin_q)) / 86400));

$rooms = [];
if ($result) while ($r = $result->fetch_assoc()) $rooms[] = $r;
$availCount = 0;
foreach ($rooms as $r) {
    $tot = $hasTotalRooms ? max(1, (int)$r['total_rooms']) : 5;
    if (max(0, $tot - ($approvedMap[(int)$r['id']] ?? 0)) > 0) $availCount++;
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,Helvetica,sans-serif;background:#edeae4;color:#1a1a2e;}
a{text-decoration:none;color:inherit;}
img{display:block;max-width:100%;}
button{font-family:inherit;cursor:pointer;}

/* ════ PAGE WRAPPER ════ */
.page-hero{
  background: #1a1a2e;
  padding-bottom: 80px;
}

/* ════ NAVBAR ════ */
.navbar{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:20px 40px;
  border-bottom:1px solid rgba(255,255,255,.07);
}
.nb-brand{
  font-size:16px;font-weight:900;color:#fff;letter-spacing:.04em;
}
.nb-brand span{color:#c9a96e;}
.nb-links{display:flex;gap:10px;}
.nb-links a{
  display:inline-block;
  padding:8px 18px;
  border-radius:99px;
  font-size:13px;font-weight:700;
  color:rgba(255,255,255,.65);
  border:1.5px solid rgba(255,255,255,.18);
  transition:.15s;
}
.nb-links a:hover{
  background:rgba(255,255,255,.1);
  color:#fff;
  border-color:rgba(255,255,255,.35);
}

/* ════ HERO TEXT ════ */
.hero-body{
  padding:48px 40px 0;
  max-width:700px;
}
.hero-eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  color:#c9a96e;font-size:12px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;
  margin-bottom:18px;
}
.hero-eyebrow::before{content:'';display:inline-block;width:28px;height:2px;background:#c9a96e;border-radius:2px;}
.hero-h1{
  font-size:58px;font-weight:900;color:#fff;line-height:1.05;
  margin-bottom:16px;letter-spacing:-.01em;
}
.hero-h1 em{color:#c9a96e;font-style:italic;}
.hero-desc{
  font-size:15px;color:rgba(255,255,255,.45);
  line-height:1.8;max-width:440px;
}

/* ════ CARDS SECTION ════ */
.cards-section{
  max-width:1160px;
  margin:-44px auto 0;
  padding:0 32px 80px;
  position:relative;
  z-index:10;
}

.cards-header{
  display:flex;align-items:center;gap:12px;
  margin-bottom:24px;
}
.cards-header-label{
  font-size:13px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:#7a7a8c;
}
.cards-header-line{flex:1;height:1px;background:#d8d4ce;}
.cards-header-count{
  background:#1a1a2e;color:#c9a96e;
  font-size:12px;font-weight:800;
  padding:4px 12px;border-radius:99px;
}

/* ════ GRID ════ */
.room-grid{
  display:flex;
  flex-wrap:wrap;
  gap:22px;
  justify-content:center;
}
/* ════ ROOM CARD ════ */
.rcard{
  flex:0 0 calc(50% - 11px);
  max-width:480px;
  min-width:300px;
  background:#fff;
  border-radius:22px;
  overflow:hidden;
  box-shadow:0 4px 24px rgba(26,26,46,.09);
  display:flex;flex-direction:column;
  transition:transform .22s,box-shadow .22s;
}
.rcard:hover{
  transform:translateY(-5px);
  box-shadow:0 18px 48px rgba(26,26,46,.16);
}
.rcard.rcard-full{opacity:.55;pointer-events:none;}

/* image */
.rcard-img-wrap{position:relative;flex-shrink:0;overflow:hidden;}
.rcard-img{width:100%;height:210px;object-fit:cover;display:block;transition:transform .4s;}
.rcard:hover .rcard-img{transform:scale(1.04);}
.rcard-img-ph{
  width:100%;height:210px;
  background:linear-gradient(135deg,#252545,#1a1a2e);
  display:flex;align-items:center;justify-content:center;
  font-size:56px;opacity:.18;
}
.rcard-avail-badge{
  position:absolute;top:14px;left:14px;
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 14px;border-radius:99px;
  font-size:12px;font-weight:800;color:#fff;
  backdrop-filter:blur(4px);
}
.rcard-avail-badge.avail{background:rgba(22,163,74,.85);}
.rcard-avail-badge.sold{background:rgba(220,38,38,.85);}
.rcard-dot{
  width:7px;height:7px;border-radius:50%;background:#fff;
  display:inline-block;animation:blink 1.5s infinite;
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
.rcard-price-badge{
  position:absolute;bottom:14px;right:14px;
  background:rgba(10,10,20,.85);color:#c9a96e;
  padding:6px 14px;border-radius:99px;
  font-size:14px;font-weight:900;letter-spacing:.02em;
  border:1px solid rgba(201,169,110,.3);
  backdrop-filter:blur(4px);
}

/* body */
.rcard-body{padding:20px 22px;flex:1;}
.rcard-type{
  font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;
  color:#c9a96e;margin-bottom:5px;
}
.rcard-name{
  font-size:19px;font-weight:800;color:#1a1a2e;
  margin-bottom:8px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.rcard-desc{
  font-size:13px;color:#888;line-height:1.7;
  margin-bottom:14px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.rcard-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;}
.tag{
  display:inline-flex;align-items:center;gap:4px;
  padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600;
}
.tag-gold{background:rgba(201,169,110,.12);color:#a07c3a;border:1px solid rgba(201,169,110,.22);}
.tag-gray{background:#f3f0ec;color:#6a6a7a;}
.tag-am{background:rgba(26,26,46,.05);color:#4a4a5a;border:1px solid rgba(26,26,46,.07);}

/* progress bar */
.prog-row{display:flex;justify-content:space-between;font-size:11px;font-weight:700;color:#aaa;margin-bottom:6px;}
.prog-bar{height:5px;background:#ede9e3;border-radius:99px;overflow:hidden;margin-bottom:18px;}
.prog-fill{height:100%;border-radius:99px;background:#16a34a;}
.prog-fill.warn{background:#f59e0b;}
.prog-fill.full{background:#dc2626;}

/* footer */
.rcard-foot{
  padding:16px 22px;
  border-top:1.5px solid #f0ece6;
  background:#fafaf7;
  display:flex;align-items:center;justify-content:space-between;gap:14px;
}
.rcard-price-main{font-size:24px;font-weight:900;color:#1a1a2e;line-height:1;}
.rcard-price-sub{font-size:11px;color:#aaa;font-weight:600;margin-top:3px;}
.rcard-btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:12px 24px;border-radius:14px;
  font-size:14px;font-weight:800;
  background:#1a1a2e;color:#fff;
  border:none;letter-spacing:.02em;
  transition:background .15s,transform .15s,box-shadow .15s;
}
.rcard-btn:hover{
  background:#2e2e50;
  transform:translateY(-1px);
  box-shadow:0 8px 24px rgba(26,26,46,.28);
  color:#fff;
}
.rcard-btn.rcard-btn-full{
  background:#c9c5bf;cursor:not-allowed;pointer-events:none;
}

/* empty */
.rcard-empty{
  grid-column:1/-1;
  text-align:center;padding:80px 20px;
  color:#aaa;
}
.rcard-empty-ico{font-size:56px;opacity:.18;margin-bottom:16px;}

/* responsive */
@media(max-width:860px){
  .navbar{padding:16px 20px;flex-direction:column;align-items:flex-start;gap:12px;}
  .nb-links{flex-wrap:wrap;}
  .hero-body{padding:32px 20px 0;}
  .hero-h1{font-size:38px;}
  .cards-section{padding:0 16px 60px;margin-top:-28px;}
}
@media(max-width:600px){
  .room-grid{grid-template-columns:1fr;}
  .hero-h1{font-size:32px;}
}
</style>
</head>
<body>

<div class="page-hero">

  <!-- NAVBAR -->
  <div class="navbar">
    <div class="nb-brand">วลัย<span>รุกขเวช</span></div>
    <nav class="nb-links">
      <a href="index.php">← กลับหน้าหลัก</a>
      <a href="booking_status.php">📋 ติดตามสถานะการจอง</a>
      <a href="booking_tent.php">⛺ จองเต็นท์</a>
      <a href="booking_boat.php">🚣 จองคิวพายเรือ</a>
    </nav>
  </div>

  <!-- HERO TEXT -->
  <div class="hero-body">
    <div class="hero-eyebrow">ที่พักภายในสถาบัน</div>
    <div class="hero-h1">จองห้องพัก <em>วลัยรุกขเวช</em></div>
    <div class="hero-desc">เลือกห้องพักที่ต้องการ กรอกข้อมูลผู้เข้าพัก<br>แล้วส่งคำขอจองได้เลย</div>
  </div>

</div>

<!-- CARDS SECTION -->
<div class="cards-section">

  <div class="cards-header">
    <span class="cards-header-label">รายการห้องพัก</span>
    <span class="cards-header-line"></span>
    <span class="cards-header-count">ว่าง <?= $availCount ?> ประเภท</span>
  </div>

  <div class="room-grid">
  <?php if (!empty($rooms)):
    $amIcons = [
      'แอร์'=>'❄️','TV'=>'📺','Wi-Fi'=>'📶','ตู้เย็น'=>'🧊',
      'ห้องน้ำในตัว'=>'🚿','เครื่องทำน้ำอุ่น'=>'🔥','ระเบียง'=>'🌅',
      'เตียงคู่'=>'🛏','เตียงเดี่ยว'=>'🛌'
    ];
    foreach ($rooms as $room):
      $roomId         = (int)$room['id'];
      $roomImg        = $room['image_path'] ?? '';
      $roomDesc       = $room['description'] ?? '';
      $roomPrice      = (float)($room['price'] ?? 0);
      $totalRooms     = $hasTotalRooms ? max(1,(int)$room['total_rooms']) : 5;
      $approvedCount  = $approvedMap[$roomId] ?? 0;
      $availRooms     = max(0, $totalRooms - $approvedCount);
      $isFull         = ($availRooms <= 0);
      $pct            = $totalRooms > 0 ? round($approvedCount / $totalRooms * 100) : 0;
      $fillCls        = $pct >= 100 ? 'full' : ($pct >= 60 ? 'warn' : '');
  ?>
  <div class="rcard<?= $isFull ? ' rcard-full' : '' ?>">

    <!-- image -->
    <div class="rcard-img-wrap">
      <?php if ($roomImg): ?>
        <img src="<?= htmlspecialchars($roomImg) ?>"
             alt="<?= htmlspecialchars($room['room_name']) ?>"
             class="rcard-img"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="rcard-img-ph" style="display:none">🏨</div>
      <?php else: ?>
        <div class="rcard-img-ph">🏨</div>
      <?php endif; ?>

      <div class="rcard-avail-badge <?= $isFull ? 'sold' : 'avail' ?>">
        <?php if (!$isFull): ?><span class="rcard-dot"></span><?php endif; ?>
        <?= $isFull ? 'ห้องเต็ม' : 'ว่าง ' . $availRooms . '/' . $totalRooms ?>
      </div>
      <div class="rcard-price-badge">฿<?= number_format($roomPrice) ?><span style="font-size:11px;font-weight:600;opacity:.7;">/คืน</span></div>
    </div>

    <!-- body -->
    <div class="rcard-body">
      <?php if (!empty($room['room_type'])): ?>
      <div class="rcard-type"><?= htmlspecialchars($room['room_type']) ?></div>
      <?php endif; ?>
      <div class="rcard-name"><?= htmlspecialchars($room['room_name']) ?></div>
      <?php if ($roomDesc): ?>
      <div class="rcard-desc"><?= htmlspecialchars($roomDesc) ?></div>
      <?php endif; ?>

      <div class="rcard-tags">
        <span class="tag tag-gold">฿<?= number_format($roomPrice) ?>/คืน</span>
        <span class="tag tag-gray">🏠 <?= $totalRooms ?> ห้อง</span>
        <?php if (!empty($room['max_guests']) && (int)$room['max_guests'] > 0): ?>
        <span class="tag tag-gray">👥 <?= (int)$room['max_guests'] ?> คน</span>
        <?php endif; ?>
        <?php if (!empty($room['bed_type'])): ?>
        <span class="tag tag-gray">🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($hasAmenities && !empty($room['amenities'])):
        $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
      <div class="rcard-tags">
        <?php foreach ($amItems as $am): ?>
        <span class="tag tag-am"><?= $amIcons[$am] ?? '' ?> <?= htmlspecialchars($am) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="prog-row">
        <span>จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</span>
        <span><?= $isFull ? 'เต็มแล้ว' : 'ว่าง ' . $availRooms . ' ห้อง' ?></span>
      </div>
      <div class="prog-bar">
        <div class="prog-fill <?= $fillCls ?>" style="width:<?= min(100,$pct) ?>%"></div>
      </div>
    </div>

    <!-- footer -->
    <div class="rcard-foot">
      <div>
        <div class="rcard-price-main">฿<?= number_format($roomPrice) ?></div>
        <div class="rcard-price-sub">ต่อห้อง / คืน</div>
      </div>
      <?php if ($isFull): ?>
      <span class="rcard-btn rcard-btn-full">ห้องเต็ม</span>
      <?php else: ?>
      <a class="rcard-btn"
         href="booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>">
        จองเลย →
      </a>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; else: ?>
  <div class="rcard-empty">
    <div class="rcard-empty-ico">🏨</div>
    <div style="font-size:17px;font-weight:800;color:#4a4a5a;margin-bottom:8px;">ไม่พบห้องพักในขณะนี้</div>
    <div style="font-size:13px;">กรุณาติดต่อเจ้าหน้าที่</div>
  </div>
  <?php endif; ?>
  </div>

</div>
</body>
</html>
