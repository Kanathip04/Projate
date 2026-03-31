<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

date_default_timezone_set('Asia/Bangkok');

/* =========================
   เชื่อมต่อฐานข้อมูล
========================= */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =========================
   ฟังก์ชันเช็คว่าตารางมีอยู่ไหม
========================= */
function tableExists($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    return ($res && $res->num_rows > 0);
}

/* =========================
   ฟังก์ชันดึงคอลัมน์ของตาราง
========================= */
function getTableColumns($conn, $tableName) {
    $columns = [];
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    return $columns;
}

/* =========================
   ตรวจสอบว่ามีตาราง rooms หรือไม่
========================= */
if (!tableExists($conn, 'rooms')) {
    die("ไม่พบตาราง rooms ในฐานข้อมูล");
}

$roomColumns = getTableColumns($conn, 'rooms');

/* =========================
   เช็คคอลัมน์ของ rooms
========================= */
$hasId          = in_array('id', $roomColumns, true);
$hasRoomName    = in_array('room_name', $roomColumns, true);
$hasRoomType    = in_array('room_type', $roomColumns, true);
$hasPrice       = in_array('price', $roomColumns, true);
$hasRoomSize    = in_array('room_size', $roomColumns, true);
$hasBedType     = in_array('bed_type', $roomColumns, true);
$hasMaxGuests   = in_array('max_guests', $roomColumns, true);
$hasImagePath   = in_array('image_path', $roomColumns, true);
$hasDescription = in_array('description', $roomColumns, true);
$hasStatus      = in_array('status', $roomColumns, true);
$hasTotalRooms  = in_array('total_rooms', $roomColumns, true);
$hasAmenities   = in_array('amenities', $roomColumns, true);

if (!$hasId || !$hasRoomName) {
    die("ตาราง rooms ต้องมีคอลัมน์ id และ room_name อย่างน้อย");
}

/* =========================
   ดึงจำนวนการจองที่อนุมัติแล้วจาก room_bookings
========================= */
$approvedMap = [];

if (tableExists($conn, 'room_bookings')) {
    $bookingColumns = getTableColumns($conn, 'room_bookings');

    $hasBookingRoomId = in_array('room_id', $bookingColumns, true);
    $hasBookingStatus = in_array('booking_status', $bookingColumns, true);

    if ($hasBookingRoomId && $hasBookingStatus) {
        $hasRoomUnitsCol = in_array('room_units', $bookingColumns, true);
        if ($hasRoomUnitsCol) {
            /* นับจากจำนวนหน่วยจริงใน room_units JSON */
            $sqlApproved = "
                SELECT room_id,
                       SUM(CASE
                           WHEN room_units IS NOT NULL AND room_units != ''
                           THEN JSON_LENGTH(room_units)
                           ELSE 1
                       END) AS approved_total
                FROM room_bookings
                WHERE booking_status = 'approved'
                GROUP BY room_id
            ";
        } else {
            $sqlApproved = "
                SELECT room_id, COUNT(*) AS approved_total
                FROM room_bookings
                WHERE booking_status = 'approved'
                GROUP BY room_id
            ";
        }

        $resApproved = $conn->query($sqlApproved);
        if ($resApproved) {
            while ($row = $resApproved->fetch_assoc()) {
                $approvedMap[(int)$row['room_id']] = (int)$row['approved_total'];
            }
        }
    }
}

/* =========================
   สร้าง SELECT แบบยืดหยุ่น
========================= */
$selectFields = ['id', 'room_name'];

if ($hasRoomType)    $selectFields[] = 'room_type';
if ($hasPrice)       $selectFields[] = 'price';
if ($hasRoomSize)    $selectFields[] = 'room_size';
if ($hasBedType)     $selectFields[] = 'bed_type';
if ($hasMaxGuests)   $selectFields[] = 'max_guests';
if ($hasImagePath)   $selectFields[] = 'image_path';
if ($hasDescription) $selectFields[] = 'description';
if ($hasTotalRooms)  $selectFields[] = 'total_rooms';
if ($hasStatus)      $selectFields[] = 'status';
if ($hasAmenities)   $selectFields[] = 'amenities';

/* =========================
   สร้าง SQL หลัก
========================= */
$sql = "SELECT " . implode(", ", $selectFields) . " FROM rooms WHERE 1=1";

if ($hasStatus) {
    $sql .= " AND status = 'show'";
}

$sql .= " ORDER BY id DESC";

/* =========================
   Prepare / Execute
========================= */
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
$checkin_q  = trim($_GET['checkin']  ?? '');
$checkout_q = trim($_GET['checkout'] ?? '');
$guests_q   = (int)($_GET['guests']  ?? 1);
if ($checkin_q  === '') $checkin_q  = date('Y-m-d');
if ($checkout_q === '' || $checkout_q <= $checkin_q) $checkout_q = date('Y-m-d', strtotime($checkin_q.' +1 day'));
if ($guests_q < 1) $guests_q = 1;
$ci_ts = strtotime($checkin_q);
$co_ts = strtotime($checkout_q);
$nights_q = max(1, (int)(($co_ts - $ci_ts) / 86400));
function thDate2($s){ $m=['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']; $ts=strtotime($s); return date('j',$ts).' '.$m[(int)date('m',$ts)].' '.(date('Y',$ts)+543); }
ob_end_clean();
?>
<!DOCTYPE html>
<html lang=”th”>
<head>
<meta charset=”UTF-8”>
<meta name=”viewport” content=”width=device-width, initial-scale=1.0”>
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>
<link href=”https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap” rel=”stylesheet”>
<link href=”https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css” rel=”stylesheet”>
<link href=”booking_room.css?v=<?= time() ?>” rel=”stylesheet”>
</head>
<body>

<!-- TOP BAR -->
<div class=”bk-topbar”>
  <div class=”container-xl px-4”>
    <nav class=”bk-nav”>
      <a href=”index.php”>← หน้าหลัก</a>
      <a href=”booking_status.php”>📋 สถานะการจอง</a>
      <a href=”booking_tent.php”>⛺ จองเต็นท์</a>
      <a href=”booking_boat.php”>🚣 จองพายเรือ</a>
    </nav>
    <div class=”row align-items-end”>
      <div class=”col”>
        <div class=”bk-hero-badge”>🏨 ที่พักภายในสถาบัน</div>
        <div class=”bk-hero-title”>จองห้องพัก <em>วลัยรุกขเวช</em></div>
        <div class=”bk-hero-sub”>ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น พร้อมสิ่งอำนวยความสะดวกครบครัน</div>
      </div>
      <div class=”col-auto”>
        <div class=”bk-night-pill”>
          <div class=”bk-night-num”>🌙 <?= $nights_q ?></div>
          <div class=”bk-night-lbl”>คืน</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MAIN -->
<div class=”container-xl px-4 py-4”>

  <!-- Search Card -->
  <div class=”bk-sc mb-4”>
    <div class=”bk-sc-head”>
      <p class=”bk-sc-title”>🔍 ค้นหาห้องพัก</p>
    </div>
    <div class=”bk-sc-body”>
      <form method=”GET” action=”booking_room.php”>
        <div class=”row g-3 align-items-end”>
          <div class=”col-md-4 col-sm-6”>
            <label class=”bk-label”>วันเช็คอิน</label>
            <input type=”date” name=”checkin” class=”bk-input” value=”<?= htmlspecialchars($checkin_q) ?>” required>
          </div>
          <div class=”col-md-4 col-sm-6”>
            <label class=”bk-label”>วันเช็คเอาท์</label>
            <input type=”date” name=”checkout” class=”bk-input” value=”<?= htmlspecialchars($checkout_q) ?>” required>
          </div>
          <div class=”col-md-2 col-sm-6”>
            <label class=”bk-label”>ผู้เข้าพัก</label>
            <input type=”number” name=”guests” class=”bk-input” min=”1” max=”20” value=”<?= $guests_q ?>” required>
          </div>
          <div class=”col-md-2 col-sm-6”>
            <button type=”submit” class=”bk-search-btn”>🔍 ค้นหา</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Stats -->
  <?php
    $totalCount = $result ? $result->num_rows : 0;
    $availCount = 0;
    if ($result && $totalCount > 0) {
      $result->data_seek(0);
      while ($r = $result->fetch_assoc()) {
        $rid = (int)$r['id'];
        $tot = $hasTotalRooms ? max(1,(int)$r['total_rooms']) : 5;
        $app = $approvedMap[$rid] ?? 0;
        if (max(0,$tot-$app) > 0) $availCount++;
      }
      $result->data_seek(0);
    }
  ?>
  <div class=”row g-3 mb-4”>
    <div class=”col-md-4”>
      <div class=”bk-stat” style=”border-left-color:#1d6fad;”>
        <div class=”bk-stat-ico”>🏨</div>
        <div class=”bk-stat-val”><?= $totalCount ?></div>
        <div class=”bk-stat-lbl”>ประเภทห้องทั้งหมด</div>
      </div>
    </div>
    <div class=”col-md-4”>
      <div class=”bk-stat” style=”border-left-color:#16a34a;”>
        <div class=”bk-stat-ico”>✅</div>
        <div class=”bk-stat-val”><?= $availCount ?></div>
        <div class=”bk-stat-lbl”>ประเภทที่ว่าง</div>
      </div>
    </div>
    <div class=”col-md-4”>
      <div class=”bk-stat” style=”border-left-color:#c9a96e;”>
        <div class=”bk-stat-ico”>🌙</div>
        <div class=”bk-stat-val”><?= $nights_q ?></div>
        <div class=”bk-stat-lbl”>จำนวนคืน</div>
      </div>
    </div>
  </div>

  <!-- List Header -->
  <div class=”bk-list-head d-flex align-items-center justify-content-between mb-0”>
    <div class=”bk-list-title”>ห้องพักที่ว่าง &middot; <?= $guests_q ?> ผู้เข้าพัก</div>
    <span class=”bk-date-badge”><?= thDate2($checkin_q) ?> — <?= thDate2($checkout_q) ?></span>
  </div>

  <!-- Room Cards -->
  <div class=”bk-room-wrap”>
    <?php if ($result && $result->num_rows > 0): ?>
    <div class=”row g-3”>
      <?php
      $amIcons = ['แอร์'=>'❄️','TV'=>'📺','Wi-Fi'=>'📶','ตู้เย็น'=>'🧊','ห้องน้ำในตัว'=>'🚿','เครื่องทำน้ำอุ่น'=>'🔥','ระเบียง'=>'🌅','เตียงคู่'=>'🛏','เตียงเดี่ยว'=>'🛌'];
      while ($room = $result->fetch_assoc()):
        $roomId         = (int)$room['id'];
        $roomImg        = !empty($room['image_path']) ? $room['image_path'] : '';
        $roomDesc       = !empty($room['description']) ? $room['description'] : '';
        $roomPrice      = (float)($room['price'] ?? 0);
        $totalRooms     = $hasTotalRooms ? max(1,(int)$room['total_rooms']) : 5;
        $approvedCount  = $approvedMap[$roomId] ?? 0;
        $availableRooms = max(0, $totalRooms - $approvedCount);
        $isFull         = ($availableRooms <= 0);
        $fillPct        = $totalRooms > 0 ? round($approvedCount / $totalRooms * 100) : 0;
        $fillCls        = $fillPct >= 100 ? 'bk-full-bar' : ($fillPct >= 60 ? 'warn' : '');
        $totalEst       = $roomPrice * $nights_q;
      ?>
      <div class=”col-md-4 col-sm-6”>
        <div class=”bk-card<?= $isFull ? ' is-full' : '' ?>”>

          <div class=”bk-img-wrap”>
            <?php if ($roomImg): ?>
              <img src=”<?= htmlspecialchars($roomImg) ?>” alt=”<?= htmlspecialchars($room['room_name']) ?>” class=”bk-img” onerror=”this.style.display='none';this.nextElementSibling.style.display='flex'”>
              <div class=”bk-img-ph” style=”display:none;”>🏨</div>
            <?php else: ?>
              <div class=”bk-img-ph”>🏨</div>
            <?php endif; ?>
            <div class=”bk-badge-avail <?= $isFull ? 'full' : 'ok' ?>”>
              <?php if (!$isFull): ?><span class=”bk-dot”></span><?php endif; ?>
              <?= $isFull ? 'ห้องเต็ม' : ('ว่าง '.$availableRooms.'/'.$totalRooms) ?>
            </div>
            <div class=”bk-badge-price”>฿<?= number_format($roomPrice) ?>/คืน</div>
          </div>

          <div class=”bk-card-body”>
            <?php if (!empty($room['room_type'])): ?>
            <div class=”bk-type”><?= htmlspecialchars($room['room_type']) ?></div>
            <?php endif; ?>
            <div class=”bk-name”><?= htmlspecialchars($room['room_name']) ?></div>
            <?php if ($roomDesc): ?>
            <div class=”bk-desc”><?= htmlspecialchars($roomDesc) ?></div>
            <?php endif; ?>

            <div class=”mb-2”>
              <span class=”bk-chip bk-chip-gold”>฿<?= number_format($roomPrice) ?>/คืน</span>
              <span class=”bk-chip”>🏠 <?= $totalRooms ?> ห้อง</span>
              <?php $cap = (int)($room['max_guests'] ?? 0); if ($cap > 0): ?>
              <span class=”bk-chip”>👥 <?= $cap ?> คน</span>
              <?php endif; ?>
              <?php if (!empty($room['bed_type'])): ?>
              <span class=”bk-chip”>🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
              <?php endif; ?>
            </div>

            <?php if ($hasAmenities && !empty($room['amenities'])):
              $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
            <div class=”mb-2”>
              <?php foreach ($amItems as $am): ?>
              <span class=”bk-am”><?= ($amIcons[$am] ?? '') ?> <?= htmlspecialchars($am) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class=”bk-stock-row”>
              <span>จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</span>
              <span><?= $isFull ? 'เต็ม' : ('เหลือ '.$availableRooms) ?></span>
            </div>
            <div class=”bk-track mb-2”>
              <div class=”bk-fill <?= $fillCls ?>” style=”width:<?= min(100,$fillPct) ?>%”></div>
            </div>
          </div>

          <div class=”bk-foot”>
            <div>
              <div class=”bk-price-big”>฿<?= number_format($roomPrice) ?></div>
              <div class=”bk-price-sub”>ต่อห้อง / คืน</div>
              <?php if ($nights_q > 1 && !$isFull): ?>
              <div class=”bk-price-est”>~฿<?= number_format($totalEst) ?> (<?= $nights_q ?> คืน)</div>
              <?php endif; ?>
            </div>
            <?php if ($isFull): ?>
            <span class=”bk-book-btn disabled”>ห้องเต็ม</span>
            <?php else: ?>
            <a class=”bk-book-btn” href=”booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>”>จองเลย →</a>
            <?php endif; ?>
          </div>

        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <?php else: ?>
    <div class=”bk-empty”>
      <div class=”bk-empty-ico”>🏨</div>
      <div style=”font-size:1rem;font-weight:800;margin-bottom:6px;”>ไม่พบห้องพักในช่วงเวลานี้</div>
      <div style=”font-size:.82rem;”>กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
const ci = document.querySelector('input[name=checkin]');
const co = document.querySelector('input[name=checkout]');
if (ci && co) {
  ci.addEventListener('change', function() {
    if (co.value <= ci.value) {
      var d = new Date(ci.value); d.setDate(d.getDate()+1);
      co.value = d.toISOString().slice(0,10);
    }
  });
}
</script>

<script>
// auto-fix checkout > checkin
const ci = document.querySelector('input[name=checkin]');
const co = document.querySelector('input[name=checkout]');
if (ci && co) {
  ci.addEventListener('change', () => {
    if (co.value <= ci.value) {
      const d = new Date(ci.value); d.setDate(d.getDate()+1);
      co.value = d.toISOString().slice(0,10);
    }
  });
}
</script>
</body>
</html>