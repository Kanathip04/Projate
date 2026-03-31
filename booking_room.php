<?php
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:ital,wght@0,700;0,800;0,900;1,800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --ink:#0d1b2a;--gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);--gold-border:rgba(201,169,110,.4);--gold-dark:#a8864d;
  --bg:#f0f4f8;--card:#fff;--border:#e2e8f0;--muted:#64748b;
  --success:#15803d;--danger:#dc2626;--navy:#0d1b2a;--navy2:#1e3a5c;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;}

/* ── HERO ── */
.hero{
  background:linear-gradient(135deg,#0d1b2a 0%,#0f2740 50%,#1a3a5c 100%);
  padding:0 0 0;position:relative;overflow:hidden;
}
.hero::before{content:'';position:absolute;width:600px;height:600px;border-radius:50%;background:rgba(201,169,110,.06);top:-200px;right:-100px;pointer-events:none;}
.hero::after{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:rgba(201,169,110,.04);bottom:-150px;left:-80px;pointer-events:none;}
.hero-inner{width:min(1200px,94%);margin:0 auto;padding:36px 0 0;position:relative;z-index:2;}

/* top nav */
.top-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:40px;}
.nav-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:99px;font-size:.8rem;font-weight:700;color:rgba(255,255,255,.8);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);transition:.2s;}
.nav-btn:hover{background:rgba(201,169,110,.2);color:var(--gold);border-color:var(--gold-border);}
.nav-btn.active{background:rgba(201,169,110,.18);color:var(--gold);border-color:var(--gold-border);}

/* hero text */
.hero-text{margin-bottom:44px;}
.hero-eyebrow{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:99px;background:rgba(201,169,110,.14);border:1px solid var(--gold-border);color:var(--gold);font-size:.78rem;font-weight:700;letter-spacing:.06em;margin-bottom:18px;}
.hero h1{font-family:'Kanit',sans-serif;font-size:2.8rem;font-weight:900;color:#fff;line-height:1.15;margin-bottom:14px;}
.hero h1 em{font-style:italic;color:var(--gold);}
.hero-sub{font-size:.95rem;color:rgba(255,255,255,.65);max-width:560px;line-height:1.8;}

/* search bar */
.search-bar{
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.12);
  border-radius:20px 20px 0 0;
  padding:28px 32px 0;
  backdrop-filter:blur(12px);
}
.search-bar-title{font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.45);margin-bottom:16px;}
.search-fields{display:grid;grid-template-columns:1fr 1fr 120px 160px;gap:12px;align-items:end;padding-bottom:28px;}
.sf{display:flex;flex-direction:column;gap:6px;}
.sf label{font-size:.7rem;font-weight:700;color:rgba(255,255,255,.55);letter-spacing:.06em;text-transform:uppercase;}
.sf-input-wrap{position:relative;}
.sf-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:.9rem;opacity:.6;pointer-events:none;}
.sf input,.sf select{
  width:100%;padding:11px 12px 11px 36px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;color:#fff;
  background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.15);
  border-radius:12px;outline:none;transition:.2s;
}
.sf input:focus,.sf select:focus{border-color:var(--gold);background:rgba(255,255,255,.14);}
.sf input::placeholder{color:rgba(255,255,255,.35);}
.sf select option{background:#1a2e4a;color:#fff;}
.search-btn{
  width:100%;padding:12px;border:none;border-radius:12px;
  background:linear-gradient(135deg,var(--gold) 0%,#b8924a 100%);
  color:var(--ink);font-family:'Kanit',sans-serif;font-size:.88rem;font-weight:800;
  cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:6px;
}
.search-btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(201,169,110,.4);}

/* ── MAIN CONTENT ── */
.page-body{width:min(1200px,94%);margin:0 auto;padding:36px 0 60px;}

/* summary bar */
.summary-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:28px;}
.summary-title{font-family:'Kanit',sans-serif;font-size:1.2rem;font-weight:800;color:var(--ink);}
.summary-meta{font-size:.8rem;color:var(--muted);}
.stay-tag{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:99px;background:var(--navy);color:var(--gold);font-size:.78rem;font-weight:700;border:1px solid rgba(201,169,110,.25);}

/* ── ROOM GRID ── */
.room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:24px;}

/* room card */
.room-card{
  background:var(--card);border-radius:22px;overflow:hidden;
  border:1px solid var(--border);box-shadow:0 4px 20px rgba(13,27,42,.08);
  transition:all .25s;display:flex;flex-direction:column;
}
.room-card:hover{transform:translateY(-6px);box-shadow:0 16px 40px rgba(13,27,42,.14);}
.room-card.is-full{opacity:.75;}

/* image */
.rc-img-wrap{position:relative;overflow:hidden;}
.rc-img{width:100%;height:220px;object-fit:cover;display:block;transition:transform .4s;}
.room-card:hover .rc-img{transform:scale(1.04);}
.rc-img-ph{width:100%;height:220px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);display:flex;align-items:center;justify-content:center;font-size:3rem;}
.rc-price{position:absolute;bottom:14px;right:14px;background:rgba(13,27,42,.85);backdrop-filter:blur(8px);color:var(--gold);padding:8px 14px;border-radius:99px;font-family:'Kanit',sans-serif;font-size:.85rem;font-weight:800;border:1px solid var(--gold-border);}
.rc-avail{position:absolute;top:14px;left:14px;display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:99px;font-size:.73rem;font-weight:800;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.25);}
.rc-avail.ok{background:rgba(21,128,61,.85);color:#fff;}
.rc-avail.full{background:rgba(220,38,38,.85);color:#fff;}
.rc-avail .dot{width:6px;height:6px;border-radius:50%;background:currentColor;animation:blink 1.4s ease infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* body */
.rc-body{padding:22px 22px 18px;flex:1;display:flex;flex-direction:column;}
.rc-type{font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--gold-dark);margin-bottom:6px;}
.rc-name{font-family:'Kanit',sans-serif;font-size:1.15rem;font-weight:800;color:var(--ink);margin-bottom:8px;line-height:1.3;}
.rc-desc{font-size:.82rem;color:var(--muted);line-height:1.7;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}

/* meta chips */
.rc-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;}
.chip{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:8px;font-size:.72rem;font-weight:700;background:#f1f5f9;color:var(--muted);border:1px solid var(--border);}

/* stock bar */
.rc-stock{margin-bottom:16px;}
.stock-nums{display:flex;justify-content:space-between;font-size:.73rem;font-weight:700;color:var(--muted);margin-bottom:5px;}
.stock-bar{height:5px;background:#e2e8f0;border-radius:99px;overflow:hidden;}
.stock-fill{height:100%;border-radius:99px;background:var(--success);transition:width .4s;}
.stock-fill.warn{background:#f59e0b;}
.stock-fill.full{background:var(--danger);}

/* amenities */
.rc-amenities{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:16px;}
.am-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:7px;font-size:.7rem;font-weight:600;background:var(--gold-dim);color:var(--gold-dark);border:1px solid rgba(201,169,110,.25);}

/* footer */
.rc-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:auto;padding-top:14px;border-top:1px solid var(--border);}
.rc-price-main{display:flex;flex-direction:column;}
.rc-price-big{font-family:'Kanit',sans-serif;font-size:1.3rem;font-weight:900;color:var(--gold-dark);}
.rc-price-sub{font-size:.68rem;color:var(--muted);font-weight:600;}
.rc-total-est{font-size:.7rem;color:var(--success);font-weight:700;margin-top:1px;}
.book-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:11px 20px;border-radius:12px;font-family:'Kanit',sans-serif;font-size:.82rem;font-weight:800;
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);color:#fff;
  border:none;cursor:pointer;transition:all .2s;white-space:nowrap;
}
.book-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(13,27,42,.25);background:var(--gold);color:var(--ink);}
.book-btn.disabled{background:#94a3b8;cursor:not-allowed;pointer-events:none;}

/* empty */
.empty-box{background:var(--card);border:1px solid var(--border);border-radius:22px;padding:60px 24px;text-align:center;color:var(--muted);}
.empty-box .ei{font-size:3rem;margin-bottom:14px;opacity:.35;}

@media(max-width:900px){.search-fields{grid-template-columns:1fr 1fr;}.search-btn{grid-column:1/-1;}}
@media(max-width:600px){
  .hero h1{font-size:2rem;}
  .search-fields{grid-template-columns:1fr;}
  .search-bar{padding:20px 18px 0;}
  .room-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- ════ HERO ════ -->
<section class=”hero”>
  <div class=”hero-inner”>

    <nav class=”top-nav”>
      <a href=”index.php” class=”nav-btn”>← หน้าหลัก</a>
      <a href=”booking_status.php” class=”nav-btn”>📋 สถานะการจอง</a>
      <a href=”booking_tent.php” class=”nav-btn”>⛺ จองเต็นท์</a>
      <a href=”booking_boat.php” class=”nav-btn”>🚣 จองพายเรือ</a>
    </nav>

    <div class=”hero-text”>
      <div class=”hero-eyebrow”>🏨 ที่พักภายในสถาบัน</div>
      <h1>จองห้องพัก<br><em>วลัยรุกขเวช</em></h1>
      <p class=”hero-sub”>ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น พร้อมสิ่งอำนวยความสะดวกครบครัน</p>
    </div>

    <!-- search bar -->
    <div class=”search-bar”>
      <div class=”search-bar-title”>ค้นหาห้องพัก</div>
      <form method=”GET” action=”booking_room.php”>
        <div class=”search-fields”>
          <div class=”sf”>
            <label>วันเช็คอิน</label>
            <div class=”sf-input-wrap”>
              <span class=”sf-icon”>📅</span>
              <input type=”date” name=”checkin” value=”<?= htmlspecialchars($checkin_q) ?>” required>
            </div>
          </div>
          <div class=”sf”>
            <label>วันเช็คเอาท์</label>
            <div class=”sf-input-wrap”>
              <span class=”sf-icon”>📅</span>
              <input type=”date” name=”checkout” value=”<?= htmlspecialchars($checkout_q) ?>” required>
            </div>
          </div>
          <div class=”sf”>
            <label>จำนวนผู้เข้าพัก</label>
            <div class=”sf-input-wrap”>
              <span class=”sf-icon”>👥</span>
              <input type=”number” name=”guests” min=”1” max=”20” value=”<?= $guests_q ?>” placeholder=”คน” required>
            </div>
          </div>
          <div class=”sf”>
            <label>&nbsp;</label>
            <button type=”submit” class=”search-btn”>🔍 ค้นหาห้องพัก</button>
          </div>
        </div>
      </form>
    </div>

  </div>
</section>

<!-- ════ MAIN ════ -->
<div class=”page-body”>

  <div class=”summary-bar”>
    <div>
      <div class=”summary-title”>ห้องพักที่ว่าง</div>
      <div class=”summary-meta”><?= thDate2($checkin_q) ?> — <?= thDate2($checkout_q) ?> · <?= $guests_q ?> ผู้เข้าพัก</div>
    </div>
    <div class=”stay-tag”>🌙 <?= $nights_q ?> คืน</div>
  </div>

  <?php if ($result && $result->num_rows > 0): ?>
  <div class=”room-grid”>
    <?php
    $amIcons = ['แอร์'=>'❄️','TV'=>'📺','Wi-Fi'=>'📶','ตู้เย็น'=>'🧊','ห้องน้ำในตัว'=>'🚿','เครื่องทำน้ำอุ่น'=>'🔥','ระเบียง'=>'🌅','เตียงคู่'=>'🛏','เตียงเดี่ยว'=>'🛌'];
    while($room = $result->fetch_assoc()):
      $roomId    = (int)$room['id'];
      $roomImg   = !empty($room['image_path']) ? $room['image_path'] : '';
      $roomDesc  = !empty($room['description']) ? $room['description'] : '';
      $roomPrice = (float)($room['price'] ?? 0);
      $totalRooms = $hasTotalRooms ? max(1,(int)$room['total_rooms']) : 5;
      $approvedCount  = $approvedMap[$roomId] ?? 0;
      $availableRooms = max(0, $totalRooms - $approvedCount);
      $isFull = ($availableRooms <= 0);
      $fillPct = $totalRooms > 0 ? round($approvedCount/$totalRooms*100) : 0;
      $fillCls = $fillPct >= 100 ? 'full' : ($fillPct >= 60 ? 'warn' : '');
      $totalEst = $roomPrice * $nights_q;
    ?>
    <div class=”room-card<?= $isFull ? ' is-full' : '' ?>”>

      <!-- Image -->
      <div class=”rc-img-wrap”>
        <?php if ($roomImg): ?>
          <img src=”<?= htmlspecialchars($roomImg) ?>” alt=”<?= htmlspecialchars($room['room_name']) ?>” class=”rc-img” onerror=”this.parentNode.innerHTML='<div class=rc-img-ph>🏨</div>'”>
        <?php else: ?>
          <div class=”rc-img-ph”>🏨</div>
        <?php endif; ?>
        <div class=”rc-avail <?= $isFull ? 'full' : 'ok' ?>”>
          <?php if (!$isFull): ?><span class=”dot”></span><?php endif; ?>
          <?= $isFull ? 'ห้องเต็ม' : “ว่าง {$availableRooms}/{$totalRooms}” ?>
        </div>
        <div class=”rc-price”>฿<?= number_format($roomPrice) ?> / คืน</div>
      </div>

      <!-- Body -->
      <div class=”rc-body”>
        <?php if (!empty($room['room_type'])): ?>
        <div class=”rc-type”><?= htmlspecialchars($room['room_type']) ?></div>
        <?php endif; ?>
        <div class=”rc-name”><?= htmlspecialchars($room['room_name']) ?></div>
        <?php if ($roomDesc): ?>
        <div class=”rc-desc”><?= htmlspecialchars($roomDesc) ?></div>
        <?php endif; ?>

        <!-- Meta chips -->
        <div class=”rc-chips”>
          <?php if (!empty($room['room_size'])): ?>
          <span class=”chip”>📐 <?= htmlspecialchars($room['room_size']) ?></span>
          <?php endif; ?>
          <?php if (!empty($room['bed_type'])): ?>
          <span class=”chip”>🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
          <?php endif; ?>
          <?php $cap = (int)($room['max_guests'] ?? 0); if ($cap > 0): ?>
          <span class=”chip”>👥 <?= $cap ?> คน</span>
          <?php endif; ?>
          <span class=”chip”>🏠 <?= $totalRooms ?> ห้อง</span>
        </div>

        <!-- Amenities -->
        <?php if ($hasAmenities && !empty($room['amenities'])):
          $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
        <div class=”rc-amenities”>
          <?php foreach ($amItems as $am): ?>
          <span class=”am-chip”><?= ($amIcons[$am] ?? '') ?> <?= htmlspecialchars($am) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Stock bar -->
        <div class=”rc-stock”>
          <div class=”stock-nums”>
            <span>จองแล้ว <?= $approvedCount ?> / <?= $totalRooms ?> ห้อง</span>
            <span><?= $isFull ? 'เต็ม' : “คงเหลือ {$availableRooms} ห้อง” ?></span>
          </div>
          <div class=”stock-bar”>
            <div class=”stock-fill <?= $fillCls ?>” style=”width:<?= min(100,$fillPct) ?>%”></div>
          </div>
        </div>

        <!-- Footer -->
        <div class=”rc-footer”>
          <div class=”rc-price-main”>
            <div class=”rc-price-big”>฿<?= number_format($roomPrice) ?></div>
            <div class=”rc-price-sub”>ต่อห้อง / คืน</div>
            <?php if ($nights_q > 1 && !$isFull): ?>
            <div class=”rc-total-est”>ประมาณ ฿<?= number_format($totalEst) ?> (<?= $nights_q ?> คืน)</div>
            <?php endif; ?>
          </div>
          <?php if ($isFull): ?>
          <span class=”book-btn disabled”>ห้องเต็ม</span>
          <?php else: ?>
          <a class=”book-btn” href=”booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>”>
            จองเลย →
          </a>
          <?php endif; ?>
        </div>
      </div><!-- rc-body -->

    </div><!-- room-card -->
    <?php endwhile; ?>
  </div><!-- room-grid -->

  <?php else: ?>
  <div class=”empty-box”>
    <div class=”ei”>🏨</div>
    <div style=”font-size:1.1rem;font-weight:800;color:var(--ink);margin-bottom:6px;”>ไม่พบห้องพักในช่วงเวลานี้</div>
    <div style=”font-size:.85rem;”>กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
  </div>
  <?php endif; ?>

</div><!-- page-body -->

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