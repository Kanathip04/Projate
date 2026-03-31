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
<html lang=”th”>
<head>
<meta charset=”UTF-8”>
<meta name=”viewport” content=”width=device-width, initial-scale=1.0”>
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>
<link rel=”preconnect” href=”https://fonts.googleapis.com”>
<link rel=”preconnect” href=”https://fonts.gstatic.com” crossorigin>
<link href=”https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:ital,wght@0,700;0,800;0,900;1,800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap” rel=”stylesheet”>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);--gold-border:rgba(201,169,110,.3);--gold-dark:#a07c3a;
  --ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;
  --success:#16a34a;--danger:#dc2626;--blue:#1d4ed8;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;}

/* ── TOP BAR ── */
.top-bar{
  background:linear-gradient(135deg,var(--ink) 0%,#2a2a4a 100%);
  padding:0;position:relative;overflow:hidden;
}
.top-bar::before{content:'';position:absolute;width:500px;height:500px;border-radius:50%;
  background:rgba(201,169,110,.05);top:-220px;right:-80px;pointer-events:none;}
.top-bar-inner{width:min(1200px,94%);margin:0 auto;padding:24px 0;}

/* nav breadcrumb */
.top-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;}
.nav-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 15px;border-radius:99px;
  font-size:.78rem;font-weight:700;color:rgba(255,255,255,.75);
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);transition:.2s;}
.nav-btn:hover{background:rgba(201,169,110,.18);color:var(--gold);border-color:var(--gold-border);}
.nav-btn.active{background:rgba(201,169,110,.18);color:var(--gold);border-color:var(--gold-border);}

/* hero row */
.hero-row{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;}
.hero-left{}
.hero-eyebrow{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:99px;
  background:rgba(201,169,110,.14);border:1px solid var(--gold-border);
  color:var(--gold);font-size:.72rem;font-weight:700;letter-spacing:.06em;margin-bottom:12px;}
.hero-title{font-family:'Kanit',sans-serif;font-size:2rem;font-weight:900;color:#fff;line-height:1.2;margin-bottom:6px;}
.hero-title em{font-style:italic;color:var(--gold);}
.hero-sub{font-size:.85rem;color:rgba(255,255,255,.55);line-height:1.7;}

/* stay summary pill */
.stay-pill{display:inline-flex;align-items:center;gap:8px;
  background:rgba(201,169,110,.14);border:1px solid var(--gold-border);
  border-radius:14px;padding:12px 18px;color:#fff;white-space:nowrap;}
.stay-pill-night{font-family:'Kanit',sans-serif;font-size:1.6rem;font-weight:900;color:var(--gold);}
.stay-pill-label{font-size:.72rem;color:rgba(255,255,255,.55);font-weight:600;text-transform:uppercase;letter-spacing:.06em;}

/* ── SEARCH CARD ── */
.search-card{
  background:var(--card);border-radius:20px;
  box-shadow:0 4px 24px rgba(26,26,46,.1);
  overflow:hidden;margin-bottom:28px;
}
.search-card-head{
  background:linear-gradient(135deg,var(--ink) 0%,#2a2a4a 100%);
  padding:16px 24px;
  display:flex;align-items:center;gap:10px;
}
.search-card-head-title{
  font-size:.7rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
  color:rgba(255,255,255,.55);
}
.search-card-body{padding:20px 24px;}
.search-fields{display:grid;grid-template-columns:1fr 1fr 130px 150px;gap:12px;align-items:end;}
.sf{display:flex;flex-direction:column;gap:6px;}
.sf label{font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);}
.sf-input-wrap{position:relative;}
.sf-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:.85rem;opacity:.55;pointer-events:none;}
.sf input{
  width:100%;padding:10px 12px 10px 34px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);
  background:#fafaf8;border:1.5px solid var(--border);
  border-radius:10px;outline:none;transition:.2s;
}
.sf input:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.search-btn{
  width:100%;padding:11px 16px;border:none;border-radius:10px;
  background:var(--ink);
  color:#fff;font-family:'Sarabun',sans-serif;font-size:.88rem;font-weight:700;
  cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:6px;
}
.search-btn:hover{background:#2a2a4a;transform:translateY(-1px);box-shadow:0 4px 14px rgba(26,26,46,.2);}

/* ── PAGE BODY ── */
.page-body{width:min(1200px,94%);margin:0 auto;padding:32px 0 60px;}

/* stats row */
.bk-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:28px;}
.bk-stat{background:var(--card);border-radius:14px;padding:16px 20px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
  display:flex;align-items:center;gap:14px;border-left:3px solid var(--gold);}
.bk-stat-icon{font-size:1.5rem;flex-shrink:0;}
.bk-stat-val{font-size:1.4rem;font-weight:900;color:var(--ink);line-height:1;}
.bk-stat-lbl{font-size:.68rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-top:2px;}

/* list header */
.rm-list-head{
  background:var(--card);border-radius:20px 20px 0 0;
  padding:16px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
}
.rm-list-title{font-size:.9rem;font-weight:800;color:var(--ink);
  display:flex;align-items:center;gap:8px;}
.rm-list-title::before{content:'';width:3px;height:14px;background:var(--gold);border-radius:2px;display:inline-block;}
.rm-cnt-badge{background:var(--gold-dim);color:var(--gold-dark);font-size:.7rem;font-weight:700;
  padding:3px 10px;border-radius:20px;}
.date-badge{font-size:.75rem;color:var(--muted);font-weight:600;}

/* ── ROOM GRID ── */
.room-grid{
  background:var(--card);border-radius:0 0 20px 20px;padding:16px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);
  display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;
}

/* room card */
.room-card{
  border-radius:14px;border:1.5px solid var(--border);overflow:hidden;
  transition:box-shadow .2s,transform .2s;display:flex;flex-direction:column;
}
.room-card:hover{box-shadow:0 8px 24px rgba(26,26,46,.1);transform:translateY(-3px);}
.room-card.is-full{opacity:.72;}

/* image */
.rc-img-wrap{position:relative;overflow:hidden;}
.rc-img{width:100%;height:160px;object-fit:cover;display:block;transition:transform .35s;}
.room-card:hover .rc-img{transform:scale(1.04);}
.rc-img-ph{width:100%;height:160px;background:linear-gradient(135deg,#f1ede8,#e8e4de);
  display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--border);}
.rc-price-badge{position:absolute;bottom:10px;right:10px;
  background:rgba(26,26,46,.82);backdrop-filter:blur(6px);
  color:var(--gold);padding:6px 12px;border-radius:99px;
  font-family:'Kanit',sans-serif;font-size:.8rem;font-weight:800;border:1px solid var(--gold-border);}
.rc-avail{position:absolute;top:10px;left:10px;display:inline-flex;align-items:center;gap:5px;
  padding:5px 11px;border-radius:99px;font-size:.7rem;font-weight:800;
  backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.2);}
.rc-avail.ok{background:rgba(22,163,74,.85);color:#fff;}
.rc-avail.full{background:rgba(220,38,38,.85);color:#fff;}
.rc-avail .blink{width:6px;height:6px;border-radius:50%;background:currentColor;animation:blink 1.4s ease infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

/* body */
.rc-body{padding:14px 16px;flex:1;display:flex;flex-direction:column;}
.rc-type{font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--gold-dark);margin-bottom:4px;}
.rc-name{font-weight:800;color:var(--ink);font-size:.95rem;margin-bottom:6px;line-height:1.35;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rc-desc{font-size:.8rem;color:var(--muted);line-height:1.65;margin-bottom:10px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}

/* meta chips */
.rc-chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px;}
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;
  font-size:.7rem;font-weight:600;background:#f1ede8;color:var(--muted);}
.chip-price{background:rgba(201,169,110,.15);color:var(--gold-dark);}

/* amenities */
.rc-amenities{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;}
.am-chip{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:7px;
  font-size:.68rem;font-weight:600;background:var(--gold-dim);color:var(--gold-dark);border:1px solid rgba(201,169,110,.2);}

/* stock bar */
.rc-stock{margin-bottom:12px;}
.stock-nums{display:flex;justify-content:space-between;font-size:.7rem;font-weight:700;color:var(--muted);margin-bottom:4px;}
.stock-bar{height:4px;background:var(--border);border-radius:99px;overflow:hidden;}
.stock-fill{height:100%;border-radius:99px;background:var(--success);transition:width .4s;}
.stock-fill.warn{background:#f59e0b;}
.stock-fill.full{background:var(--danger);}

/* footer */
.rc-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;
  margin-top:auto;padding:10px 16px;border-top:1px solid var(--border);background:#fdfcfa;}
.rc-price-main{}
.rc-price-big{font-family:'Kanit',sans-serif;font-size:1.15rem;font-weight:900;color:var(--gold-dark);}
.rc-price-sub{font-size:.65rem;color:var(--muted);font-weight:600;}
.rc-total-est{font-size:.68rem;color:var(--success);font-weight:700;margin-top:1px;}
.book-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:8px 16px;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:700;
  background:var(--ink);color:#fff;border:none;cursor:pointer;transition:.2s;white-space:nowrap;
}
.book-btn:hover{background:#2a2a4a;transform:translateY(-1px);box-shadow:0 4px 12px rgba(26,26,46,.2);}
.book-btn.disabled{background:#c4bcb4;cursor:not-allowed;pointer-events:none;}

/* empty */
.room-empty{padding:48px;text-align:center;color:var(--muted);grid-column:1/-1;}
.room-empty-ico{font-size:2.5rem;opacity:.3;margin-bottom:10px;}

@media(max-width:900px){.search-fields{grid-template-columns:1fr 1fr;}.search-btn{grid-column:1/-1;}.bk-stats{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){
  .hero-title{font-size:1.5rem;}
  .search-fields{grid-template-columns:1fr;}
  .search-card-body{padding:16px;}
  .room-grid{grid-template-columns:1fr;}
  .bk-stats{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- ════ TOP BAR ════ -->
<div class=”top-bar”>
  <div class=”top-bar-inner”>

    <nav class=”top-nav”>
      <a href=”index.php” class=”nav-btn”>← หน้าหลัก</a>
      <a href=”booking_status.php” class=”nav-btn”>📋 สถานะการจอง</a>
      <a href=”booking_tent.php” class=”nav-btn”>⛺ จองเต็นท์</a>
      <a href=”booking_boat.php” class=”nav-btn”>🚣 จองพายเรือ</a>
    </nav>

    <div class=”hero-row”>
      <div class=”hero-left”>
        <div class=”hero-eyebrow”>🏨 ที่พักภายในสถาบัน</div>
        <div class=”hero-title”>จองห้องพัก <em>วลัยรุกขเวช</em></div>
        <div class=”hero-sub”>ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น พร้อมสิ่งอำนวยความสะดวกครบครัน</div>
      </div>
      <div class=”stay-pill”>
        <div>
          <div class=”stay-pill-night”>🌙 <?= $nights_q ?></div>
          <div class=”stay-pill-label”>คืน</div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ════ MAIN ════ -->
<div class=”page-body”>

  <!-- Search Card -->
  <div class=”search-card”>
    <div class=”search-card-head”>
      <div class=”search-card-head-title”>🔍 ค้นหาห้องพัก</div>
    </div>
    <div class=”search-card-body”>
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
            <button type=”submit” class=”search-btn”>🔍 ค้นหา</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Stats -->
  <?php
    $totalCount = $result ? $result->num_rows : 0;
    $availCount = 0;
    $fullCount  = 0;
    if ($result) {
      $result->data_seek(0);
      while ($r = $result->fetch_assoc()) {
        $rid = (int)$r['id'];
        $tot = $hasTotalRooms ? max(1,(int)$r['total_rooms']) : 5;
        $app = $approvedMap[$rid] ?? 0;
        if (max(0,$tot-$app) > 0) $availCount++; else $fullCount++;
      }
      $result->data_seek(0);
    }
  ?>
  <div class=”bk-stats”>
    <div class=”bk-stat” style=”border-left-color:#1d6fad;”>
      <div class=”bk-stat-icon”>🏨</div>
      <div>
        <div class=”bk-stat-val”><?= $totalCount ?></div>
        <div class=”bk-stat-lbl”>ประเภทห้องทั้งหมด</div>
      </div>
    </div>
    <div class=”bk-stat” style=”border-left-color:var(--success);”>
      <div class=”bk-stat-icon”>✅</div>
      <div>
        <div class=”bk-stat-val”><?= $availCount ?></div>
        <div class=”bk-stat-lbl”>ประเภทที่ว่าง</div>
      </div>
    </div>
    <div class=”bk-stat” style=”border-left-color:var(--gold);”>
      <div class=”bk-stat-icon”>🌙</div>
      <div>
        <div class=”bk-stat-val”><?= $nights_q ?></div>
        <div class=”bk-stat-lbl”>จำนวนคืน</div>
      </div>
    </div>
  </div>

  <!-- List header -->
  <div class=”rm-list-head”>
    <div class=”rm-list-title”>ห้องพักที่ว่าง · <?= $guests_q ?> ผู้เข้าพัก</div>
    <span class=”date-badge”><?= thDate2($checkin_q) ?> — <?= thDate2($checkout_q) ?></span>
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
          <?php if (!$isFull): ?><span class=”blink”></span><?php endif; ?>
          <?= $isFull ? 'ห้องเต็ม' : ('ว่าง '.$availableRooms.'/'.$totalRooms) ?>
        </div>
        <div class=”rc-price-badge”>฿<?= number_format($roomPrice) ?>/คืน</div>
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
          <span class=”chip chip-price”>฿<?= number_format($roomPrice) ?>/คืน</span>
          <span class=”chip”>🏠 <?= $totalRooms ?> ห้อง</span>
          <?php $cap = (int)($room['max_guests'] ?? 0); if ($cap > 0): ?>
          <span class=”chip”>👥 <?= $cap ?> คน</span>
          <?php endif; ?>
          <?php if (!empty($room['bed_type'])): ?>
          <span class=”chip”>🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
          <?php endif; ?>
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
            <span><?= $isFull ? 'เต็ม' : ('คงเหลือ '.$availableRooms.' ห้อง') ?></span>
          </div>
          <div class=”stock-bar”>
            <div class=”stock-fill <?= $fillCls ?>” style=”width:<?= min(100,$fillPct) ?>%”></div>
          </div>
        </div>
      </div><!-- rc-body -->

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

    </div><!-- room-card -->
    <?php endwhile; ?>
  </div><!-- room-grid -->

  <?php else: ?>
  <div class=”room-grid”>
    <div class=”room-empty”>
      <div class=”room-empty-ico”>🏨</div>
      <div style=”font-size:1rem;font-weight:800;color:var(--ink);margin-bottom:6px;”>ไม่พบห้องพักในช่วงเวลานี้</div>
      <div style=”font-size:.82rem;”>กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
    </div>
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