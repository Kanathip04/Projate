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
<link href=”https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:ital,wght@0,700;0,800;0,900;1,800&display=swap” rel=”stylesheet”>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sarabun', sans-serif; background: #f5f1eb; color: #1a1a2e; min-height: 100vh; }
a { text-decoration: none; }
img { display: block; }

/* ── WRAPPER ── */
.wrap { max-width: 1200px; width: 94%; margin: 0 auto; }

/* ── TOP BAR ── */
.top-bar { background: #1a1a2e; padding: 22px 0 28px; }
.top-nav { display: -webkit-box; display: -ms-flexbox; display: flex; -ms-flex-wrap: wrap; flex-wrap: wrap; gap: 8px; margin-bottom: 22px; }
.nav-btn {
  display: -webkit-inline-box; display: -ms-inline-flexbox; display: inline-flex;
  -webkit-box-align: center; -ms-flex-align: center; align-items: center;
  gap: 6px; padding: 7px 14px; border-radius: 99px; font-size: .78rem; font-weight: 700;
  color: rgba(255,255,255,.75); background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.15); transition: .2s;
}
.nav-btn:hover { background: rgba(201,169,110,.2); color: #c9a96e; border-color: rgba(201,169,110,.4); }

.hero-flex { display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-pack: justify; -ms-flex-pack: justify; justify-content: space-between; -webkit-box-align: flex-end; -ms-flex-align: flex-end; align-items: flex-end; -ms-flex-wrap: wrap; flex-wrap: wrap; gap: 16px; }
.hero-badge { display: inline-block; padding: 4px 12px; border-radius: 99px; background: rgba(201,169,110,.15); border: 1px solid rgba(201,169,110,.35); color: #c9a96e; font-size: .72rem; font-weight: 700; margin-bottom: 10px; }
.hero-title { font-family: 'Kanit', sans-serif; font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1.2; margin-bottom: 5px; }
.hero-title em { font-style: italic; color: #c9a96e; }
.hero-sub { font-size: .83rem; color: rgba(255,255,255,.5); line-height: 1.65; }
.night-pill { display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; gap: 10px; background: rgba(201,169,110,.12); border: 1px solid rgba(201,169,110,.3); border-radius: 12px; padding: 12px 20px; color: #fff; white-space: nowrap; }
.night-num { font-family: 'Kanit', sans-serif; font-size: 2rem; font-weight: 900; color: #c9a96e; line-height: 1; }
.night-lbl { font-size: .7rem; color: rgba(255,255,255,.5); font-weight: 600; letter-spacing: .06em; }

/* ── MAIN ── */
.page-body { max-width: 1200px; width: 94%; margin: 0 auto; padding: 28px 0 60px; }

/* ── SEARCH CARD ── */
.search-card { background: #fff; border-radius: 18px; box-shadow: 0 4px 20px rgba(26,26,46,.1); overflow: hidden; margin-bottom: 24px; }
.sc-head { background: #1a1a2e; padding: 14px 22px; }
.sc-head-title { font-size: .68rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.5); }
.sc-body { padding: 18px 22px; }
.sf-row { display: -webkit-box; display: -ms-flexbox; display: flex; gap: 12px; -ms-flex-wrap: wrap; flex-wrap: wrap; -webkit-box-align: end; -ms-flex-align: end; align-items: flex-end; }
.sf { display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-orient: vertical; -webkit-box-direction: normal; -ms-flex-direction: column; flex-direction: column; gap: 5px; -webkit-box-flex: 1; -ms-flex: 1 1 160px; flex: 1 1 160px; }
.sf-sm { -webkit-box-flex: 0; -ms-flex: 0 0 120px; flex: 0 0 120px; }
.sf-btn { -webkit-box-flex: 0; -ms-flex: 0 0 140px; flex: 0 0 140px; }
.sf label { font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #7a7a8c; }
.sf-iw { position: relative; }
.sf-ico { position: absolute; left: 10px; top: 50%; -webkit-transform: translateY(-50%); transform: translateY(-50%); font-size: .85rem; opacity: .5; pointer-events: none; }
.sf input { width: 100%; padding: 10px 10px 10px 32px; font-family: 'Sarabun', sans-serif; font-size: .88rem; color: #1a1a2e; background: #fafaf8; border: 1.5px solid #e8e4de; border-radius: 10px; outline: none; }
.sf input:focus { border-color: #c9a96e; background: #fff; }
.search-btn { width: 100%; padding: 11px; border: none; border-radius: 10px; background: #1a1a2e; color: #fff; font-family: 'Sarabun', sans-serif; font-size: .88rem; font-weight: 700; cursor: pointer; }
.search-btn:hover { background: #2a2a4a; }

/* ── STATS ── */
.stats-row { display: -webkit-box; display: -ms-flexbox; display: flex; gap: 12px; margin-bottom: 24px; }
.stat-box { -webkit-box-flex: 1; -ms-flex: 1 1 0; flex: 1 1 0; background: #fff; border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 10px rgba(26,26,46,.06); display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; gap: 12px; border-left: 3px solid #c9a96e; }
.stat-ico { font-size: 1.5rem; flex-shrink: 0; }
.stat-val { font-size: 1.4rem; font-weight: 900; color: #1a1a2e; line-height: 1; }
.stat-lbl { font-size: .67rem; color: #7a7a8c; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; margin-top: 2px; }

/* ── LIST HEADER ── */
.list-head { background: #fff; border-radius: 18px 18px 0 0; padding: 15px 20px; border-bottom: 1px solid #e8e4de; display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; -webkit-box-pack: justify; -ms-flex-pack: justify; justify-content: space-between; box-shadow: 0 2px 10px rgba(26,26,46,.05); }
.list-head-title { font-size: .88rem; font-weight: 800; color: #1a1a2e; display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; gap: 8px; }
.list-head-title::before { content: ''; width: 3px; height: 14px; background: #c9a96e; border-radius: 2px; display: inline-block; }
.date-badge { font-size: .75rem; color: #7a7a8c; font-weight: 600; }

/* ── ROOM GRID ── */
.room-wrap { background: #fff; border-radius: 0 0 18px 18px; padding: 14px; box-shadow: 0 4px 20px rgba(26,26,46,.07); display: -webkit-box; display: -ms-flexbox; display: flex; -ms-flex-wrap: wrap; flex-wrap: wrap; gap: 14px; }
.room-card { width: calc(33.333% - 10px); border-radius: 14px; border: 1.5px solid #e8e4de; overflow: hidden; display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-orient: vertical; -webkit-box-direction: normal; -ms-flex-direction: column; flex-direction: column; background: #fff; transition: box-shadow .2s, -webkit-transform .2s; transition: box-shadow .2s, transform .2s; }
.room-card:hover { box-shadow: 0 8px 24px rgba(26,26,46,.1); -webkit-transform: translateY(-3px); transform: translateY(-3px); }
.room-card.is-full { opacity: .72; }

/* image */
.rc-img-wrap { position: relative; overflow: hidden; }
.rc-img { width: 100%; height: 160px; object-fit: cover; transition: -webkit-transform .35s; transition: transform .35s; }
.room-card:hover .rc-img { -webkit-transform: scale(1.04); transform: scale(1.04); }
.rc-img-ph { width: 100%; height: 160px; background: linear-gradient(135deg, #f1ede8, #e8e4de); display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; -webkit-box-pack: center; -ms-flex-pack: center; justify-content: center; font-size: 2.5rem; color: #ccc; }
.rc-badge-price { position: absolute; bottom: 10px; right: 10px; background: rgba(26,26,46,.85); color: #c9a96e; padding: 5px 11px; border-radius: 99px; font-family: 'Kanit', sans-serif; font-size: .78rem; font-weight: 800; border: 1px solid rgba(201,169,110,.35); }
.rc-badge-avail { position: absolute; top: 10px; left: 10px; display: -webkit-inline-box; display: -ms-inline-flexbox; display: inline-flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 99px; font-size: .7rem; font-weight: 800; border: 1px solid rgba(255,255,255,.2); }
.rc-badge-avail.ok { background: rgba(22,163,74,.85); color: #fff; }
.rc-badge-avail.full { background: rgba(220,38,38,.85); color: #fff; }
.blink-dot { width: 6px; height: 6px; border-radius: 50%; background: #fff; display: inline-block; -webkit-animation: bl 1.4s ease infinite; animation: bl 1.4s ease infinite; }
@-webkit-keyframes bl { 0%,100%{opacity:1}50%{opacity:.3} }
@keyframes bl { 0%,100%{opacity:1}50%{opacity:.3} }

/* card body */
.rc-body { padding: 13px 15px; -webkit-box-flex: 1; -ms-flex: 1; flex: 1; display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-orient: vertical; -webkit-box-direction: normal; -ms-flex-direction: column; flex-direction: column; }
.rc-type { font-size: .66rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #a07c3a; margin-bottom: 3px; }
.rc-name { font-weight: 800; color: #1a1a2e; font-size: .93rem; margin-bottom: 5px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rc-desc { font-size: .79rem; color: #7a7a8c; line-height: 1.6; margin-bottom: 9px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.rc-chips { display: -webkit-box; display: -ms-flexbox; display: flex; -ms-flex-wrap: wrap; flex-wrap: wrap; gap: 5px; margin-bottom: 9px; }
.chip { display: -webkit-inline-box; display: -ms-inline-flexbox; display: inline-flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 99px; font-size: .69rem; font-weight: 600; background: #f1ede8; color: #7a7a8c; }
.chip-gold { background: rgba(201,169,110,.15); color: #a07c3a; }
.rc-amenities { display: -webkit-box; display: -ms-flexbox; display: flex; -ms-flex-wrap: wrap; flex-wrap: wrap; gap: 4px; margin-bottom: 9px; }
.am-chip { display: -webkit-inline-box; display: -ms-inline-flexbox; display: inline-flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 7px; font-size: .67rem; font-weight: 600; background: rgba(201,169,110,.1); color: #a07c3a; border: 1px solid rgba(201,169,110,.2); }
.rc-stock { margin-bottom: 10px; }
.stock-row { display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-pack: justify; -ms-flex-pack: justify; justify-content: space-between; font-size: .7rem; font-weight: 700; color: #7a7a8c; margin-bottom: 4px; }
.stock-track { height: 4px; background: #e8e4de; border-radius: 99px; overflow: hidden; }
.stock-fill { height: 100%; border-radius: 99px; background: #16a34a; }
.stock-fill.warn { background: #f59e0b; }
.stock-fill.full { background: #dc2626; }

/* card footer */
.rc-foot { display: -webkit-box; display: -ms-flexbox; display: flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; -webkit-box-pack: justify; -ms-flex-pack: justify; justify-content: space-between; gap: 8px; padding: 10px 15px; border-top: 1px solid #e8e4de; background: #fdfcfa; margin-top: auto; }
.rc-price-big { font-family: 'Kanit', sans-serif; font-size: 1.1rem; font-weight: 900; color: #a07c3a; }
.rc-price-sub { font-size: .63rem; color: #7a7a8c; font-weight: 600; }
.rc-price-est { font-size: .67rem; color: #16a34a; font-weight: 700; margin-top: 1px; }
.book-btn { display: -webkit-inline-box; display: -ms-inline-flexbox; display: inline-flex; -webkit-box-align: center; -ms-flex-align: center; align-items: center; gap: 4px; padding: 8px 15px; border-radius: 8px; font-family: 'Sarabun', sans-serif; font-size: .8rem; font-weight: 700; background: #1a1a2e; color: #fff; border: none; cursor: pointer; white-space: nowrap; transition: background .2s; }
.book-btn:hover { background: #2a2a4a; }
.book-btn.disabled { background: #b8b0a8; cursor: not-allowed; pointer-events: none; }

/* empty */
.room-empty { width: 100%; padding: 48px; text-align: center; color: #7a7a8c; }
.room-empty-ico { font-size: 2.5rem; opacity: .3; margin-bottom: 10px; }

@media (max-width: 900px) {
  .room-card { width: calc(50% - 7px); }
  .sf-btn { -webkit-box-flex: 1; -ms-flex: 1 1 100%; flex: 1 1 100%; }
}
@media (max-width: 600px) {
  .hero-title { font-size: 1.4rem; }
  .room-card { width: 100%; }
  .stats-row { -ms-flex-wrap: wrap; flex-wrap: wrap; }
  .stat-box { -webkit-box-flex: 0; -ms-flex: 0 0 calc(50% - 6px); flex: 0 0 calc(50% - 6px); }
  .sf { -webkit-box-flex: 1; -ms-flex: 1 1 100%; flex: 1 1 100%; }
}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class=”top-bar”>
  <div class=”wrap”>
    <nav class=”top-nav”>
      <a href=”index.php” class=”nav-btn”>← หน้าหลัก</a>
      <a href=”booking_status.php” class=”nav-btn”>📋 สถานะการจอง</a>
      <a href=”booking_tent.php” class=”nav-btn”>⛺ จองเต็นท์</a>
      <a href=”booking_boat.php” class=”nav-btn”>🚣 จองพายเรือ</a>
    </nav>
    <div class=”hero-flex”>
      <div>
        <div class=”hero-badge”>🏨 ที่พักภายในสถาบัน</div>
        <div class=”hero-title”>จองห้องพัก <em>วลัยรุกขเวช</em></div>
        <div class=”hero-sub”>ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น พร้อมสิ่งอำนวยความสะดวกครบครัน</div>
      </div>
      <div class=”night-pill”>
        <div class=”night-num”>🌙 <?= $nights_q ?></div>
        <div class=”night-lbl”>คืน</div>
      </div>
    </div>
  </div>
</div>

<!-- MAIN -->
<div class=”page-body”>

  <!-- Search -->
  <div class=”search-card”>
    <div class=”sc-head”>
      <div class=”sc-head-title”>🔍 ค้นหาห้องพัก</div>
    </div>
    <div class=”sc-body”>
      <form method=”GET” action=”booking_room.php”>
        <div class=”sf-row”>
          <div class=”sf”>
            <label>วันเช็คอิน</label>
            <div class=”sf-iw”>
              <span class=”sf-ico”>📅</span>
              <input type=”date” name=”checkin” value=”<?= htmlspecialchars($checkin_q) ?>” required>
            </div>
          </div>
          <div class=”sf”>
            <label>วันเช็คเอาท์</label>
            <div class=”sf-iw”>
              <span class=”sf-ico”>📅</span>
              <input type=”date” name=”checkout” value=”<?= htmlspecialchars($checkout_q) ?>” required>
            </div>
          </div>
          <div class=”sf sf-sm”>
            <label>ผู้เข้าพัก</label>
            <div class=”sf-iw”>
              <span class=”sf-ico”>👥</span>
              <input type=”number” name=”guests” min=”1” max=”20” value=”<?= $guests_q ?>” required>
            </div>
          </div>
          <div class=”sf sf-btn”>
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
  <div class=”stats-row”>
    <div class=”stat-box” style=”border-left-color:#1d6fad;”>
      <div class=”stat-ico”>🏨</div>
      <div>
        <div class=”stat-val”><?= $totalCount ?></div>
        <div class=”stat-lbl”>ประเภทห้องทั้งหมด</div>
      </div>
    </div>
    <div class=”stat-box” style=”border-left-color:#16a34a;”>
      <div class=”stat-ico”>✅</div>
      <div>
        <div class=”stat-val”><?= $availCount ?></div>
        <div class=”stat-lbl”>ประเภทที่ว่าง</div>
      </div>
    </div>
    <div class=”stat-box” style=”border-left-color:#c9a96e;”>
      <div class=”stat-ico”>🌙</div>
      <div>
        <div class=”stat-val”><?= $nights_q ?></div>
        <div class=”stat-lbl”>จำนวนคืน</div>
      </div>
    </div>
  </div>

  <!-- List header -->
  <div class=”list-head”>
    <div class=”list-head-title”>ห้องพักที่ว่าง &middot; <?= $guests_q ?> ผู้เข้าพัก</div>
    <span class=”date-badge”><?= thDate2($checkin_q) ?> — <?= thDate2($checkout_q) ?></span>
  </div>

  <?php if ($result && $result->num_rows > 0): ?>
  <div class=”room-wrap”>
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
      $fillCls        = $fillPct >= 100 ? 'full' : ($fillPct >= 60 ? 'warn' : '');
      $totalEst       = $roomPrice * $nights_q;
    ?>
    <div class=”room-card<?= $isFull ? ' is-full' : '' ?>”>

      <div class=”rc-img-wrap”>
        <?php if ($roomImg): ?>
          <img src=”<?= htmlspecialchars($roomImg) ?>” alt=”<?= htmlspecialchars($room['room_name']) ?>” class=”rc-img” onerror=”this.style.display='none';this.parentNode.querySelector('.rc-img-ph').style.display='flex'”>
          <div class=”rc-img-ph” style=”display:none;”>🏨</div>
        <?php else: ?>
          <div class=”rc-img-ph”>🏨</div>
        <?php endif; ?>
        <div class=”rc-badge-avail <?= $isFull ? 'full' : 'ok' ?>”>
          <?php if (!$isFull): ?><span class=”blink-dot”></span><?php endif; ?>
          <?= $isFull ? 'ห้องเต็ม' : ('ว่าง '.$availableRooms.'/'.$totalRooms) ?>
        </div>
        <div class=”rc-badge-price”>฿<?= number_format($roomPrice) ?>/คืน</div>
      </div>

      <div class=”rc-body”>
        <?php if (!empty($room['room_type'])): ?>
        <div class=”rc-type”><?= htmlspecialchars($room['room_type']) ?></div>
        <?php endif; ?>
        <div class=”rc-name”><?= htmlspecialchars($room['room_name']) ?></div>
        <?php if ($roomDesc): ?>
        <div class=”rc-desc”><?= htmlspecialchars($roomDesc) ?></div>
        <?php endif; ?>

        <div class=”rc-chips”>
          <span class=”chip chip-gold”>฿<?= number_format($roomPrice) ?>/คืน</span>
          <span class=”chip”>🏠 <?= $totalRooms ?> ห้อง</span>
          <?php $cap = (int)($room['max_guests'] ?? 0); if ($cap > 0): ?>
          <span class=”chip”>👥 <?= $cap ?> คน</span>
          <?php endif; ?>
          <?php if (!empty($room['bed_type'])): ?>
          <span class=”chip”>🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($hasAmenities && !empty($room['amenities'])):
          $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
        <div class=”rc-amenities”>
          <?php foreach ($amItems as $am): ?>
          <span class=”am-chip”><?= ($amIcons[$am] ?? '') ?> <?= htmlspecialchars($am) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class=”rc-stock”>
          <div class=”stock-row”>
            <span>จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</span>
            <span><?= $isFull ? 'เต็ม' : ('เหลือ '.$availableRooms) ?></span>
          </div>
          <div class=”stock-track”>
            <div class=”stock-fill <?= $fillCls ?>” style=”width:<?= min(100,$fillPct) ?>%”></div>
          </div>
        </div>
      </div>

      <div class=”rc-foot”>
        <div>
          <div class=”rc-price-big”>฿<?= number_format($roomPrice) ?></div>
          <div class=”rc-price-sub”>ต่อห้อง / คืน</div>
          <?php if ($nights_q > 1 && !$isFull): ?>
          <div class=”rc-price-est”>ประมาณ ฿<?= number_format($totalEst) ?> (<?= $nights_q ?> คืน)</div>
          <?php endif; ?>
        </div>
        <?php if ($isFull): ?>
        <span class=”book-btn disabled”>ห้องเต็ม</span>
        <?php else: ?>
        <a class=”book-btn” href=”booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>”>จองเลย →</a>
        <?php endif; ?>
      </div>

    </div>
    <?php endwhile; ?>
  </div>

  <?php else: ?>
  <div class=”room-wrap”>
    <div class=”room-empty”>
      <div class=”room-empty-ico”>🏨</div>
      <div style=”font-size:1rem;font-weight:800;color:#1a1a2e;margin-bottom:6px;”>ไม่พบห้องพักในช่วงเวลานี้</div>
      <div style=”font-size:.82rem;”>กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
    </div>
  </div>
  <?php endif; ?>

</div>

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