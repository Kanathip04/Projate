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
html,body{font-family:'Sarabun',Arial,sans-serif;background:#f0ede8;color:#1a1a2e;}
a{text-decoration:none;color:inherit;}
img{display:block;max-width:100%;}

/* ── HERO HEADER ── */
.hero{
  background: linear-gradient(160deg, #1a1a2e 0%, #1a1a2e 55%, rgba(26,26,46,0) 100%),
              url('') center/cover;
  background-color:#1a1a2e;
  min-height:300px;
  padding:32px 40px 60px;
  position:relative;
}
.hero-nav{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:40px;}
.hero-nav a{
  display:inline-block;padding:8px 20px;border-radius:99px;font-size:13px;font-weight:700;
  color:rgba(255,255,255,.75);border:1.5px solid rgba(255,255,255,.22);
  background:rgba(255,255,255,.06);transition:.15s;
}
.hero-nav a:hover{background:rgba(255,255,255,.14);color:#fff;border-color:rgba(255,255,255,.4);}
.hero-title{font-size:52px;font-weight:900;color:#fff;line-height:1.1;margin-bottom:12px;}
.hero-title em{font-style:italic;color:#c9a96e;}
.hero-sub{font-size:15px;color:rgba(255,255,255,.5);line-height:1.7;max-width:480px;}

/* ── CONTENT ── */
.content{max-width:1100px;margin:-40px auto 0;padding:0 32px 80px;position:relative;z-index:2;}

/* ── SECTION TITLE ── */
.sec-label{
  display:flex;align-items:center;gap:10px;
  font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
  color:#a07c3a;margin-bottom:24px;
}
.sec-label::before{content:'🏨';}
.sec-label::after{content:'';flex:1;height:1px;background:#ddd9d3;}

/* ── ROOM GRID ── */
.room-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
  gap:24px;
  justify-content:center;
}

/* ── ROOM CARD ── */
.rc{
  background:#fff;border-radius:20px;overflow:hidden;
  box-shadow:0 2px 16px rgba(26,26,46,.08);
  display:flex;flex-direction:column;
  transition:box-shadow .2s,transform .2s;
}
.rc:hover{box-shadow:0 16px 40px rgba(26,26,46,.14);transform:translateY(-4px);}
.rc.full{opacity:.6;}

/* image */
.rc-img-wrap{position:relative;flex-shrink:0;}
.rc-img{width:100%;height:200px;object-fit:cover;display:block;}
.rc-img-ph{
  width:100%;height:200px;
  background:linear-gradient(135deg,#2a2a4a,#1a1a2e);
  display:flex;align-items:center;justify-content:center;
  font-size:52px;opacity:.3;
}
.rc-avail{
  position:absolute;top:14px;left:14px;
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 13px;border-radius:99px;font-size:12px;font-weight:800;color:#fff;
}
.rc-avail.ok{background:rgba(22,163,74,.9);}
.rc-avail.full{background:rgba(220,38,38,.9);}
.rc-dot{width:7px;height:7px;border-radius:50%;background:#fff;display:inline-block;animation:blink 1.4s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.rc-price-tag{
  position:absolute;bottom:14px;right:14px;
  background:rgba(26,26,46,.92);color:#c9a96e;
  padding:6px 14px;border-radius:99px;font-size:14px;font-weight:900;
  border:1px solid rgba(201,169,110,.35);letter-spacing:.02em;
}

/* body */
.rc-body{padding:20px;flex:1;}
.rc-type{font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#c9a96e;margin-bottom:5px;}
.rc-name{font-size:18px;font-weight:800;color:#1a1a2e;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rc-desc{font-size:13px;color:#7a7a8c;line-height:1.65;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}

.rc-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;}
.chip{display:inline-flex;align-items:center;gap:4px;padding:4px 11px;border-radius:99px;font-size:12px;font-weight:600;}
.chip-gray{background:#f0ede8;color:#6b6b7b;}
.chip-gold{background:rgba(201,169,110,.14);color:#a07c3a;border:1px solid rgba(201,169,110,.25);}
.chip-am{background:rgba(26,26,46,.05);color:#4a4a5a;border:1px solid rgba(26,26,46,.08);}

/* progress */
.rc-prog-label{display:flex;justify-content:space-between;font-size:11px;font-weight:700;color:#9a9aaa;margin-bottom:5px;}
.rc-bar{height:5px;background:#ede9e3;border-radius:99px;overflow:hidden;margin-bottom:16px;}
.rc-bar-fill{height:100%;border-radius:99px;background:#16a34a;transition:width .4s;}
.rc-bar-fill.warn{background:#f59e0b;}
.rc-bar-fill.danger{background:#dc2626;}

/* footer */
.rc-foot{
  padding:16px 20px;
  border-top:1.5px solid #f0ede8;
  background:linear-gradient(to right,#fdfcfa,#fff);
  display:flex;align-items:center;justify-content:space-between;gap:12px;
}
.rc-price-wrap{}
.rc-price{font-size:22px;font-weight:900;color:#1a1a2e;line-height:1;}
.rc-price-night{font-size:11px;color:#9a9aaa;font-weight:600;margin-top:2px;}
.rc-book-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:11px 22px;border-radius:12px;font-size:14px;font-weight:800;
  background:linear-gradient(135deg,#1a1a2e,#2d2d50);
  color:#fff;border:none;cursor:pointer;letter-spacing:.02em;
  transition:box-shadow .2s,transform .2s;
}
.rc-book-btn:hover{box-shadow:0 8px 20px rgba(26,26,46,.3);transform:translateY(-1px);color:#fff;}
.rc-book-btn.off{background:linear-gradient(135deg,#b8b4ae,#ccc9c4);cursor:not-allowed;pointer-events:none;}

/* empty */
.rc-empty{grid-column:1/-1;text-align:center;padding:72px 20px;color:#9a9aaa;}
.rc-empty-ico{font-size:52px;opacity:.2;margin-bottom:16px;}
.rc-empty-title{font-size:16px;font-weight:800;color:#4a4a5a;margin-bottom:6px;}

/* responsive */
@media(max-width:768px){
  .hero{padding:24px 20px 48px;}
  .hero-title{font-size:36px;}
  .content{padding:0 16px 60px;margin-top:-28px;}
  .room-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- ══ HERO ══ -->
<div class="hero">
  <nav class="hero-nav">
    <a href="index.php">← กลับหน้าหลัก</a>
    <a href="booking_status.php">📋 ติดตามสถานะการจอง</a>
    <a href="booking_tent.php">⛺ จองเต็นท์</a>
    <a href="booking_boat.php">🚣 จองคิวพายเรือ</a>
  </nav>
  <div class="hero-title">จองห้องพัก<br><em>วลัยรุกขเวช</em></div>
  <div class="hero-sub">เลือกห้องพักที่ต้องการ กรอกข้อมูล แล้วส่งคำขอจองได้เลย</div>
</div>

<!-- ══ CONTENT ══ -->
<div class="content">

  <div class="sec-label">ห้องพักที่เปิดให้จอง</div>

  <div class="room-grid">
  <?php if (!empty($rooms)):
    $amIcons = ['แอร์'=>'❄️','TV'=>'📺','Wi-Fi'=>'📶','ตู้เย็น'=>'🧊','ห้องน้ำในตัว'=>'🚿','เครื่องทำน้ำอุ่น'=>'🔥','ระเบียง'=>'🌅','เตียงคู่'=>'🛏','เตียงเดี่ยว'=>'🛌'];
    foreach ($rooms as $room):
      $roomId         = (int)$room['id'];
      $roomImg        = $room['image_path'] ?? '';
      $roomDesc       = $room['description'] ?? '';
      $roomPrice      = (float)($room['price'] ?? 0);
      $totalRooms     = $hasTotalRooms ? max(1, (int)$room['total_rooms']) : 5;
      $approvedCount  = $approvedMap[$roomId] ?? 0;
      $availableRooms = max(0, $totalRooms - $approvedCount);
      $isFull         = ($availableRooms <= 0);
      $fillPct        = $totalRooms > 0 ? round($approvedCount / $totalRooms * 100) : 0;
      $fillCls        = $fillPct >= 100 ? 'danger' : ($fillPct >= 60 ? 'warn' : '');
  ?>
  <div class="rc<?= $isFull ? ' full' : '' ?>">

    <div class="rc-img-wrap">
      <?php if ($roomImg): ?>
        <img src="<?= htmlspecialchars($roomImg) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>"
          class="rc-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="rc-img-ph" style="display:none;">🏨</div>
      <?php else: ?>
        <div class="rc-img-ph">🏨</div>
      <?php endif; ?>

      <div class="rc-avail <?= $isFull ? 'full' : 'ok' ?>">
        <?php if (!$isFull): ?><span class="rc-dot"></span><?php endif; ?>
        <?= $isFull ? 'ห้องเต็ม' : 'ว่าง ' . $availableRooms . '/' . $totalRooms ?>
      </div>
      <div class="rc-price-tag">฿<?= number_format($roomPrice) ?>/คืน</div>
    </div>

    <div class="rc-body">
      <?php if (!empty($room['room_type'])): ?>
      <div class="rc-type"><?= htmlspecialchars($room['room_type']) ?></div>
      <?php endif; ?>
      <div class="rc-name"><?= htmlspecialchars($room['room_name']) ?></div>
      <?php if ($roomDesc): ?>
      <div class="rc-desc"><?= htmlspecialchars($roomDesc) ?></div>
      <?php endif; ?>

      <div class="rc-chips">
        <span class="chip chip-gold">฿<?= number_format($roomPrice) ?>/คืน</span>
        <span class="chip chip-gray">🏠 <?= $totalRooms ?> ห้อง</span>
        <?php if (!empty($room['max_guests']) && (int)$room['max_guests'] > 0): ?>
        <span class="chip chip-gray">👥 <?= (int)$room['max_guests'] ?> คน</span>
        <?php endif; ?>
        <?php if (!empty($room['bed_type'])): ?>
        <span class="chip chip-gray">🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($hasAmenities && !empty($room['amenities'])):
        $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
      <div class="rc-chips">
        <?php foreach ($amItems as $am): ?>
        <span class="chip chip-am"><?= $amIcons[$am] ?? '' ?> <?= htmlspecialchars($am) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="rc-prog-label">
        <span>จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</span>
        <span><?= $isFull ? 'เต็มแล้ว' : 'เหลือ ' . $availableRooms . ' ห้อง' ?></span>
      </div>
      <div class="rc-bar">
        <div class="rc-bar-fill <?= $fillCls ?>" style="width:<?= min(100,$fillPct) ?>%"></div>
      </div>
    </div>

    <div class="rc-foot">
      <div class="rc-price-wrap">
        <div class="rc-price">฿<?= number_format($roomPrice) ?></div>
        <div class="rc-price-night">ต่อห้อง / คืน</div>
      </div>
      <?php if ($isFull): ?>
      <span class="rc-book-btn off">ห้องเต็ม</span>
      <?php else: ?>
      <a class="rc-book-btn"
         href="booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>">จองเลย →</a>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; else: ?>
  <div class="rc-empty">
    <div class="rc-empty-ico">🏨</div>
    <div class="rc-empty-title">ไม่พบห้องพักในขณะนี้</div>
    <div style="font-size:13px;">กรุณาติดต่อเจ้าหน้าที่</div>
  </div>
  <?php endif; ?>
  </div>

</div>
</body>
</html>
