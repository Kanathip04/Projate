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

$rc              = getColumns($conn, 'rooms');
$hasRoomType    = in_array('room_type',   $rc, true);
$hasPrice       = in_array('price',       $rc, true);
$hasBedType     = in_array('bed_type',    $rc, true);
$hasMaxGuests   = in_array('max_guests',  $rc, true);
$hasImagePath   = in_array('image_path',  $rc, true);
$hasDescription = in_array('description', $rc, true);
$hasStatus      = in_array('status',      $rc, true);
$hasTotalRooms  = in_array('total_rooms', $rc, true);
$hasAmenities   = in_array('amenities',   $rc, true);

/* approved bookings map */
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

/* build query */
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

/* date params */
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

/* stats */
$totalCount = $result ? $result->num_rows : 0;
$availCount = 0;
$rooms = [];
if ($result && $totalCount > 0) {
    while ($r = $result->fetch_assoc()) {
        $rooms[] = $r;
        $tot = $hasTotalRooms ? max(1, (int)$r['total_rooms']) : 5;
        if (max(0, $tot - ($approvedMap[(int)$r['id']] ?? 0)) > 0) $availCount++;
    }
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
/* ── RESET ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { font-family: Arial, Helvetica, sans-serif; background: #f5f1eb; color: #1a1a2e; }
a { text-decoration: none; color: inherit; }
img { display: block; max-width: 100%; }
input, button, select, textarea { font-family: inherit; font-size: 14px; }

/* ── TOPBAR ── */
.bk-topbar { background: #1a1a2e; padding: 22px 0 28px; }
.bk-wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
.bk-nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.bk-nav a {
  padding: 6px 14px; border-radius: 99px; font-size: 13px; font-weight: 700;
  color: rgba(255,255,255,.75); background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.15);
}
.bk-nav a:hover { background: rgba(201,169,110,.2); color: #c9a96e; }
.bk-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; }
.bk-badge {
  display: inline-block; padding: 4px 12px; border-radius: 99px;
  background: rgba(201,169,110,.15); border: 1px solid rgba(201,169,110,.35);
  color: #c9a96e; font-size: 12px; font-weight: 700; margin-bottom: 10px;
}
.bk-title { font-size: 28px; font-weight: 900; color: #fff; line-height: 1.2; margin-bottom: 6px; }
.bk-title em { font-style: italic; color: #c9a96e; }
.bk-sub { font-size: 14px; color: rgba(255,255,255,.55); line-height: 1.65; }
.bk-night-pill {
  background: rgba(201,169,110,.12); border: 1px solid rgba(201,169,110,.3);
  border-radius: 12px; padding: 14px 20px; text-align: center; flex-shrink: 0;
}
.bk-night-num { font-size: 26px; font-weight: 900; color: #c9a96e; line-height: 1; }
.bk-night-lbl { font-size: 11px; color: rgba(255,255,255,.5); font-weight: 600; margin-top: 2px; }

/* ── SEARCH CARD ── */
.bk-sc {
  background: #fff; border-radius: 20px;
  box-shadow: 0 4px 24px rgba(26,26,46,.08); overflow: hidden; margin-bottom: 24px;
}
.bk-sc-head { background: #1a1a2e; padding: 14px 22px; }
.bk-sc-head-title {
  font-size: 11px; font-weight: 800; letter-spacing: .12em;
  text-transform: uppercase; color: rgba(255,255,255,.5); margin: 0;
}
.bk-sc-body { padding: 20px 22px; }
.bk-form-row { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
.bk-form-col { flex: 1; min-width: 160px; }
.bk-form-col-sm { width: 130px; flex-shrink: 0; }
.bk-label {
  display: block; font-size: 11px; font-weight: 800; letter-spacing: .08em;
  text-transform: uppercase; color: #7a7a8c; margin-bottom: 6px;
}
.bk-input {
  width: 100%; padding: 10px 13px; font-size: 14px; color: #1a1a2e;
  background: #fafaf8; border: 1.5px solid #e8e4de; border-radius: 10px; outline: none;
}
.bk-input:focus { border-color: #c9a96e; background: #fff; box-shadow: 0 0 0 3px rgba(201,169,110,.12); }
.bk-search-btn {
  width: 100%; padding: 11px 18px; border: none; border-radius: 10px;
  background: #1a1a2e; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;
  white-space: nowrap;
}
.bk-search-btn:hover { background: #2a2a4a; }

/* ── STATS ── */
.bk-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 26px; }
.bk-stat {
  background: #fff; border-radius: 14px; padding: 16px 20px;
  box-shadow: 0 2px 12px rgba(26,26,46,.06);
  display: flex; align-items: center; gap: 14px; border-left: 3px solid #c9a96e;
}
.bk-stat-icon { font-size: 26px; flex-shrink: 0; }
.bk-stat-val { font-size: 24px; font-weight: 900; color: #1a1a2e; line-height: 1; }
.bk-stat-lbl {
  font-size: 11px; color: #7a7a8c; font-weight: 600;
  text-transform: uppercase; letter-spacing: .08em; margin-top: 2px;
}

/* ── LIST HEADER ── */
.bk-list-head {
  background: #fff; border-radius: 20px 20px 0 0;
  padding: 16px 22px; border-bottom: 1px solid #e8e4de;
  display: flex; align-items: center; justify-content: space-between;
  box-shadow: 0 2px 12px rgba(26,26,46,.06);
}
.bk-list-title {
  font-size: 15px; font-weight: 800; color: #1a1a2e;
  display: flex; align-items: center; gap: 8px;
}
.bk-list-title::before {
  content: ''; width: 3px; height: 15px; background: #c9a96e;
  border-radius: 2px; display: inline-block; flex-shrink: 0;
}
.bk-cnt-badge {
  background: rgba(201,169,110,.12); color: #a07c3a;
  font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 20px;
}
.bk-date-lbl { font-size: 13px; color: #7a7a8c; font-weight: 600; }

/* ── ROOM CARDS GRID ── */
.bk-cards {
  background: #fff; border-radius: 0 0 20px 20px; padding: 16px;
  box-shadow: 0 4px 24px rgba(26,26,46,.08);
  display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;
}

/* ── ROOM CARD ── */
.bk-card {
  border-radius: 14px; border: 1.5px solid #e8e4de; overflow: hidden;
  background: #fff; display: flex; flex-direction: column;
  transition: box-shadow .2s, transform .2s;
}
.bk-card:hover { box-shadow: 0 8px 24px rgba(26,26,46,.1); transform: translateY(-2px); }
.bk-card.is-full { opacity: .72; }

/* image */
.bk-img-wrap { position: relative; flex-shrink: 0; }
.bk-img { width: 100%; height: 160px; object-fit: cover; display: block; }
.bk-img-ph {
  width: 100%; height: 160px;
  background: linear-gradient(135deg, #f1ede8, #e8e4de);
  display: flex; align-items: center; justify-content: center;
  font-size: 40px; color: #ccc;
}
.bk-badge-avail {
  position: absolute; top: 10px; left: 10px;
  padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 800;
  border: 1px solid rgba(255,255,255,.2); color: #fff;
}
.bk-badge-avail.ok  { background: rgba(22,163,74,.85); }
.bk-badge-avail.full { background: rgba(220,38,38,.85); }
.bk-badge-price {
  position: absolute; bottom: 10px; right: 10px;
  background: rgba(26,26,46,.85); color: #c9a96e;
  padding: 5px 11px; border-radius: 99px; font-size: 13px; font-weight: 800;
  border: 1px solid rgba(201,169,110,.35);
}

/* card body */
.bk-card-body { padding: 14px 16px; flex: 1; }
.bk-card-type { font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #a07c3a; margin-bottom: 3px; }
.bk-card-name { font-weight: 800; color: #1a1a2e; font-size: 15px; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bk-card-desc { font-size: 13px; color: #7a7a8c; line-height: 1.6; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bk-card-meta { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
.bk-chip {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 3px 9px; border-radius: 99px; font-size: 11px; font-weight: 600;
  background: #f1ede8; color: #7a7a8c;
}
.bk-chip-gold { background: rgba(201,169,110,.15); color: #a07c3a; }
.bk-am-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px; }
.bk-am {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 7px; font-size: 11px; font-weight: 600;
  background: rgba(201,169,110,.1); color: #a07c3a; border: 1px solid rgba(201,169,110,.2);
}
.bk-stock-row { display: flex; justify-content: space-between; font-size: 11px; font-weight: 700; color: #7a7a8c; margin-bottom: 4px; }
.bk-track { height: 4px; background: #e8e4de; border-radius: 99px; overflow: hidden; }
.bk-fill { height: 100%; border-radius: 99px; background: #16a34a; }
.bk-fill.warn { background: #f59e0b; }
.bk-fill.danger { background: #dc2626; }

/* card footer */
.bk-card-foot {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 11px 16px; border-top: 1px solid #e8e4de; background: #fdfcfa; flex-shrink: 0;
}
.bk-price-big { font-size: 18px; font-weight: 900; color: #a07c3a; }
.bk-price-sub { font-size: 10px; color: #7a7a8c; font-weight: 600; }
.bk-price-est { font-size: 11px; color: #16a34a; font-weight: 700; margin-top: 1px; }
.bk-book-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 9px 16px; border-radius: 9px; font-size: 13px; font-weight: 700;
  background: #1a1a2e; color: #fff; border: none; cursor: pointer; white-space: nowrap;
}
.bk-book-btn:hover { background: #2a2a4a; color: #fff; }
.bk-book-btn.disabled { background: #b8b0a8; cursor: not-allowed; pointer-events: none; }

/* empty */
.bk-empty { grid-column: 1 / -1; padding: 56px; text-align: center; color: #7a7a8c; }
.bk-empty-ico { font-size: 40px; opacity: .3; margin-bottom: 12px; }

/* blinking dot */
.bk-dot {
  width: 6px; height: 6px; border-radius: 50%; background: #fff;
  display: inline-block; animation: bkblink 1.4s ease infinite; vertical-align: middle; margin-right: 2px;
}
@keyframes bkblink { 0%,100%{opacity:1} 50%{opacity:.3} }

/* responsive */
@media (max-width: 900px) {
  .bk-stats { grid-template-columns: 1fr 1fr; }
  .bk-hero { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 600px) {
  .bk-stats { grid-template-columns: 1fr; }
  .bk-form-row { flex-direction: column; }
  .bk-form-col, .bk-form-col-sm { width: 100%; }
}
</style>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<div class="bk-topbar">
  <div class="bk-wrap">
    <nav class="bk-nav">
      <a href="index.php">← หน้าหลัก</a>
      <a href="booking_status.php">📋 สถานะการจอง</a>
      <a href="booking_tent.php">⛺ จองเต็นท์</a>
      <a href="booking_boat.php">🚣 จองพายเรือ</a>
    </nav>
    <div class="bk-hero">
      <div>
        <div class="bk-badge">🏨 ที่พักภายในสถาบัน</div>
        <div class="bk-title">จองห้องพัก <em>วลัยรุกขเวช</em></div>
        <div class="bk-sub">ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น พร้อมสิ่งอำนวยความสะดวกครบครัน</div>
      </div>
      <div class="bk-night-pill">
        <div class="bk-night-num">🌙 <?= $nights_q ?></div>
        <div class="bk-night-lbl">คืน</div>
      </div>
    </div>
  </div>
</div>
  <!-- Room Cards -->
  <div class="bk-cards">
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
  <div class="bk-card<?= $isFull ? ' is-full' : '' ?>">

    <!-- image -->
    <div class="bk-img-wrap">
      <?php if ($roomImg): ?>
        <img src="<?= htmlspecialchars($roomImg) ?>"
             alt="<?= htmlspecialchars($room['room_name']) ?>"
             class="bk-img"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="bk-img-ph" style="display:none;">🏨</div>
      <?php else: ?>
        <div class="bk-img-ph">🏨</div>
      <?php endif; ?>

      <div class="bk-badge-avail <?= $isFull ? 'full' : 'ok' ?>">
        <?php if (!$isFull): ?><span class="bk-dot"></span><?php endif; ?>
        <?= $isFull ? 'ห้องเต็ม' : 'ว่าง ' . $availableRooms . '/' . $totalRooms ?>
      </div>
      <div class="bk-badge-price">฿<?= number_format($roomPrice) ?>/คืน</div>
    </div>

    <!-- body -->
    <div class="bk-card-body">
      <?php if (!empty($room['room_type'])): ?>
      <div class="bk-card-type"><?= htmlspecialchars($room['room_type']) ?></div>
      <?php endif; ?>
      <div class="bk-card-name"><?= htmlspecialchars($room['room_name']) ?></div>
      <?php if ($roomDesc): ?>
      <div class="bk-card-desc"><?= htmlspecialchars($roomDesc) ?></div>
      <?php endif; ?>

      <div class="bk-card-meta">
        <span class="bk-chip bk-chip-gold">฿<?= number_format($roomPrice) ?>/คืน</span>
        <span class="bk-chip">🏠 <?= $totalRooms ?> ห้อง</span>
        <?php if (!empty($room['max_guests']) && (int)$room['max_guests'] > 0): ?>
        <span class="bk-chip">👥 <?= (int)$room['max_guests'] ?> คน</span>
        <?php endif; ?>
        <?php if (!empty($room['bed_type'])): ?>
        <span class="bk-chip">🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($hasAmenities && !empty($room['amenities'])):
        $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
      <div class="bk-am-chips">
        <?php foreach ($amItems as $am): ?>
        <span class="bk-am"><?= $amIcons[$am] ?? '' ?> <?= htmlspecialchars($am) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="bk-stock-row">
        <span>จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</span>
        <span><?= $isFull ? 'เต็ม' : 'เหลือ ' . $availableRooms ?></span>
      </div>
      <div class="bk-track">
        <div class="bk-fill <?= $fillCls ?>" style="width:<?= min(100, $fillPct) ?>%"></div>
      </div>
    </div>

    <!-- footer -->
    <div class="bk-card-foot">
      <div>
        <div class="bk-price-big">฿<?= number_format($roomPrice) ?></div>
        <div class="bk-price-sub">ต่อห้อง / คืน</div>
        <?php if ($nights_q > 1 && !$isFull): ?>
        <div class="bk-price-est">~฿<?= number_format($totalEst) ?> (<?= $nights_q ?> คืน)</div>
        <?php endif; ?>
      </div>
      <?php if ($isFull): ?>
      <span class="bk-book-btn disabled">ห้องเต็ม</span>
      <?php else: ?>
      <a class="bk-book-btn"
         href="booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>">จองเลย →</a>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>

  <?php else: ?>
  <div class="bk-empty">
    <div class="bk-empty-ico">🏨</div>
    <div style="font-size:16px;font-weight:800;margin-bottom:6px;">ไม่พบห้องพักในช่วงเวลานี้</div>
    <div style="font-size:13px;">กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
  </div>
  <?php endif; ?>
  </div><!-- /bk-cards -->

</div><!-- /bk-wrap -->

<script>
(function () {
  var ci = document.querySelector('input[name=checkin]');
  var co = document.querySelector('input[name=checkout]');
  if (ci && co) {
    ci.addEventListener('change', function () {
      if (co.value <= ci.value) {
        var d = new Date(ci.value);
        d.setDate(d.getDate() + 1);
        co.value = d.toISOString().slice(0, 10);
      }
    });
  }
})();
</script>
</body>
</html>
