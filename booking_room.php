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

function thDate($s) {
    static $m = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($s);
    return date('j', $ts) . ' ' . $m[(int)date('m', $ts)] . ' ' . (date('Y', $ts) + 543);
}

$rooms = [];
if ($result) {
    while ($r = $result->fetch_assoc()) $rooms[] = $r;
}
$totalCount = count($rooms);
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
html,body{font-family:Arial,Helvetica,sans-serif;background:#f0ece4;color:#1a1a2e;}
a{text-decoration:none;color:inherit;}
img{display:block;max-width:100%;}
input,button,select{font-family:inherit;}

/* ── HEADER ── */
.hd{background:#1a1a2e;padding:0;}
.hd-top{display:flex;align-items:center;justify-content:space-between;padding:14px 32px;border-bottom:1px solid rgba(255,255,255,.06);}
.hd-logo{font-size:15px;font-weight:800;color:#fff;letter-spacing:.03em;}
.hd-logo span{color:#c9a96e;}
.hd-nav{display:flex;gap:6px;}
.hd-nav a{padding:6px 14px;border-radius:99px;font-size:12px;font-weight:700;color:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.12);transition:.15s;}
.hd-nav a:hover{background:rgba(201,169,110,.15);color:#c9a96e;border-color:rgba(201,169,110,.3);}

/* ── HERO ── */
.hero{padding:36px 32px 32px;}
.hero-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#c9a96e;margin-bottom:12px;}
.hero-eyebrow::before{content:'';width:20px;height:1px;background:#c9a96e;}
.hero-title{font-size:32px;font-weight:900;color:#fff;line-height:1.15;margin-bottom:8px;}
.hero-title em{font-style:italic;color:#c9a96e;}
.hero-sub{font-size:13px;color:rgba(255,255,255,.45);line-height:1.7;max-width:520px;margin-bottom:28px;}

/* ── SEARCH BAR ── */
.searchbar{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px 20px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}
.sb-group{display:flex;flex-direction:column;gap:5px;flex:1;min-width:140px;}
.sb-group label{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);}
.sb-group input{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:9px 12px;font-size:13px;color:#fff;outline:none;width:100%;}
.sb-group input:focus{border-color:#c9a96e;background:rgba(201,169,110,.08);}
.sb-btn{padding:10px 22px;border:none;border-radius:10px;background:#c9a96e;color:#1a1a2e;font-size:13px;font-weight:800;cursor:pointer;white-space:nowrap;flex-shrink:0;}
.sb-btn:hover{background:#d4b87a;}

/* ── MAIN ── */
.main{max-width:1240px;margin:0 auto;padding:32px 24px 60px;}

/* ── SECTION HEADER ── */
.sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.sec-title{font-size:17px;font-weight:900;color:#1a1a2e;display:flex;align-items:center;gap:10px;}
.sec-title::before{content:'';width:4px;height:18px;background:#c9a96e;border-radius:2px;flex-shrink:0;}
.sec-meta{display:flex;align-items:center;gap:10px;}
.sec-pill{padding:4px 12px;border-radius:99px;font-size:12px;font-weight:700;}
.pill-avail{background:rgba(22,163,74,.1);color:#16a34a;border:1px solid rgba(22,163,74,.2);}
.pill-date{background:#fff;color:#7a7a8c;border:1px solid #e8e4de;}

/* ── GRID ── */
.room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;}

/* ── CARD ── */
.rc{background:#fff;border-radius:18px;border:1.5px solid #e8e4de;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s;}
.rc:hover{box-shadow:0 12px 32px rgba(26,26,46,.12);transform:translateY(-3px);}
.rc.full{opacity:.65;}

/* image zone */
.rc-img-wrap{position:relative;flex-shrink:0;}
.rc-img{width:100%;height:190px;object-fit:cover;display:block;}
.rc-img-ph{width:100%;height:190px;background:linear-gradient(135deg,#f1ede8,#e3ddd5);display:flex;align-items:center;justify-content:center;font-size:44px;color:#d0c8be;}
.rc-avail{position:absolute;top:12px;left:12px;display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:99px;font-size:11px;font-weight:800;color:#fff;border:1px solid rgba(255,255,255,.25);}
.rc-avail.ok{background:rgba(22,163,74,.88);}
.rc-avail.full{background:rgba(220,38,38,.88);}
.rc-dot{width:6px;height:6px;border-radius:50%;background:#fff;display:inline-block;animation:pulse 1.4s ease infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.rc-price-tag{position:absolute;bottom:12px;right:12px;background:rgba(26,26,46,.88);color:#c9a96e;padding:5px 12px;border-radius:99px;font-size:13px;font-weight:800;border:1px solid rgba(201,169,110,.3);}

/* body */
.rc-body{padding:16px 18px;flex:1;}
.rc-type{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#a07c3a;margin-bottom:4px;}
.rc-name{font-size:16px;font-weight:800;color:#1a1a2e;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rc-desc{font-size:12px;color:#7a7a8c;line-height:1.65;margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.rc-chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;}
.rc-chip{display:inline-flex;align-items:center;gap:3px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:#f5f1eb;color:#7a7a8c;}
.rc-chip.gold{background:rgba(201,169,110,.15);color:#a07c3a;}
.rc-amenities{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px;}
.rc-am{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:7px;font-size:11px;font-weight:600;background:rgba(201,169,110,.08);color:#a07c3a;border:1px solid rgba(201,169,110,.18);}

/* progress */
.rc-prog-row{display:flex;justify-content:space-between;font-size:11px;font-weight:700;color:#9a9aaa;margin-bottom:5px;}
.rc-bar{height:4px;background:#ede9e2;border-radius:99px;overflow:hidden;}
.rc-bar-fill{height:100%;border-radius:99px;background:#16a34a;}
.rc-bar-fill.warn{background:#f59e0b;}
.rc-bar-fill.danger{background:#dc2626;}

/* footer */
.rc-foot{padding:12px 18px;border-top:1.5px solid #f0ece4;background:#fdfcfa;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-shrink:0;}
.rc-price{font-size:20px;font-weight:900;color:#a07c3a;}
.rc-price-lbl{font-size:10px;color:#9a9aaa;font-weight:600;}
.rc-price-est{font-size:11px;color:#16a34a;font-weight:700;margin-top:1px;}
.rc-btn{display:inline-flex;align-items:center;gap:5px;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:800;background:#1a1a2e;color:#fff;border:none;cursor:pointer;white-space:nowrap;}
.rc-btn:hover{background:#2d2d50;color:#fff;}
.rc-btn.off{background:#c5bfb5;cursor:not-allowed;pointer-events:none;}

/* empty */
.rc-empty{grid-column:1/-1;text-align:center;padding:60px 20px;color:#9a9aaa;}
.rc-empty-ico{font-size:44px;opacity:.25;margin-bottom:14px;}

/* responsive */
@media(max-width:860px){.hd-top{padding:12px 18px;}.hero{padding:28px 18px 24px;}.main{padding:24px 16px 48px;}.searchbar{flex-direction:column;}.sb-group{min-width:100%;}.sb-btn{width:100%;text-align:center;}}
@media(max-width:600px){.hero-title{font-size:24px;}.hd-nav{display:none;}.room-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- ══ HEADER ══ -->
<div class="hd">
  <div class="hd-top">
    <div class="hd-logo">วลัย<span>รุกขเวช</span> · ห้องพัก</div>
    <nav class="hd-nav">
      <a href="index.php">← หน้าหลัก</a>
      <a href="booking_status.php">📋 สถานะการจอง</a>
      <a href="booking_tent.php">⛺ เต็นท์</a>
      <a href="booking_boat.php">🚣 พายเรือ</a>
    </nav>
  </div>

  <!-- HERO + SEARCH -->
  <div class="hero">
    <div class="hero-eyebrow">ที่พักภายในสถาบัน</div>
    <div class="hero-title">จองห้องพัก <em>วลัยรุกขเวช</em></div>
    <div class="hero-sub">ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น พร้อมสิ่งอำนวยความสะดวกครบครัน</div>

    <form method="GET" action="booking_room.php">
      <div class="searchbar">
        <div class="sb-group">
          <label>เช็คอิน</label>
          <input type="date" name="checkin" value="<?= htmlspecialchars($checkin_q) ?>" required>
        </div>
        <div class="sb-group">
          <label>เช็คเอาท์</label>
          <input type="date" name="checkout" value="<?= htmlspecialchars($checkout_q) ?>" required>
        </div>
        <div class="sb-group" style="max-width:110px;">
          <label>ผู้เข้าพัก</label>
          <input type="number" name="guests" min="1" max="20" value="<?= $guests_q ?>" required>
        </div>
        <button type="submit" class="sb-btn">🔍 ค้นหา</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

  <div class="sec-hd">
    <div class="sec-title">ห้องพักที่ว่าง</div>
    <div class="sec-meta">
      <span class="sec-pill pill-avail">✓ ว่าง <?= $availCount ?> ประเภท</span>
      <span class="sec-pill pill-date"><?= thDate($checkin_q) ?> — <?= thDate($checkout_q) ?> · <?= $nights_q ?> คืน</span>
    </div>
  </div>

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
      $totalEst       = $roomPrice * $nights_q;
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
        <span class="rc-chip gold">฿<?= number_format($roomPrice) ?>/คืน</span>
        <span class="rc-chip">🏠 <?= $totalRooms ?> ห้อง</span>
        <?php if (!empty($room['max_guests']) && (int)$room['max_guests'] > 0): ?>
        <span class="rc-chip">👥 <?= (int)$room['max_guests'] ?> คน</span>
        <?php endif; ?>
        <?php if (!empty($room['bed_type'])): ?>
        <span class="rc-chip">🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($hasAmenities && !empty($room['amenities'])):
        $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
      <div class="rc-amenities">
        <?php foreach ($amItems as $am): ?>
        <span class="rc-am"><?= $amIcons[$am] ?? '' ?> <?= htmlspecialchars($am) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="rc-prog-row">
        <span>จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</span>
        <span><?= $isFull ? 'เต็มแล้ว' : 'เหลือ ' . $availableRooms ?></span>
      </div>
      <div class="rc-bar">
        <div class="rc-bar-fill <?= $fillCls ?>" style="width:<?= min(100,$fillPct) ?>%"></div>
      </div>
    </div>

    <div class="rc-foot">
      <div>
        <div class="rc-price">฿<?= number_format($roomPrice) ?></div>
        <div class="rc-price-lbl">ต่อห้อง / คืน</div>
        <?php if ($nights_q > 1 && !$isFull): ?>
        <div class="rc-price-est">รวม <?= $nights_q ?> คืน ~฿<?= number_format($totalEst) ?></div>
        <?php endif; ?>
      </div>
      <?php if ($isFull): ?>
      <span class="rc-btn off">ห้องเต็ม</span>
      <?php else: ?>
      <a class="rc-btn" href="booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>">จองเลย →</a>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; else: ?>
  <div class="rc-empty">
    <div class="rc-empty-ico">🏨</div>
    <div style="font-size:15px;font-weight:800;margin-bottom:6px;color:#4a4a5a;">ไม่พบห้องพักในช่วงเวลานี้</div>
    <div style="font-size:13px;">กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
  </div>
  <?php endif; ?>
  </div>

</div>

<script>
(function(){
  var ci=document.querySelector('input[name=checkin]');
  var co=document.querySelector('input[name=checkout]');
  if(ci&&co){
    ci.addEventListener('change',function(){
      if(co.value<=ci.value){
        var d=new Date(ci.value);d.setDate(d.getDate()+1);
        co.value=d.toISOString().slice(0,10);
      }
    });
  }
})();
</script>
</body>
</html>
