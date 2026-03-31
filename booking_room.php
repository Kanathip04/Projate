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

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

function tableExists($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    return ($res && $res->num_rows > 0);
}

function getTableColumns($conn, $tableName) {
    $columns = [];
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}`");
    if ($res) { while ($row = $res->fetch_assoc()) $columns[] = $row['Field']; }
    return $columns;
}

if (!tableExists($conn, 'rooms')) die("ไม่พบตาราง rooms ในฐานข้อมูล");
$roomColumns = getTableColumns($conn, 'rooms');

$hasId = in_array('id', $roomColumns, true); $hasRoomName = in_array('room_name', $roomColumns, true);
$hasRoomType = in_array('room_type', $roomColumns, true); $hasPrice = in_array('price', $roomColumns, true);
$hasRoomSize = in_array('room_size', $roomColumns, true); $hasBedType = in_array('bed_type', $roomColumns, true);
$hasMaxGuests = in_array('max_guests', $roomColumns, true); $hasImagePath = in_array('image_path', $roomColumns, true);
$hasDescription = in_array('description', $roomColumns, true); $hasStatus = in_array('status', $roomColumns, true);
$hasTotalRooms = in_array('total_rooms', $roomColumns, true); $hasAmenities = in_array('amenities', $roomColumns, true);
if (!$hasId || !$hasRoomName) die("ตาราง rooms ต้องมีคอลัมน์ id และ room_name");

$approvedMap = [];
if (tableExists($conn, 'room_bookings')) {
    $bookingColumns = getTableColumns($conn, 'room_bookings');
    $hasBookingRoomId = in_array('room_id', $bookingColumns, true);
    $hasBookingStatus = in_array('booking_status', $bookingColumns, true);
    if ($hasBookingRoomId && $hasBookingStatus) {
        $hasRoomUnitsCol = in_array('room_units', $bookingColumns, true);
        $sqlApproved = $hasRoomUnitsCol
            ? "SELECT room_id, SUM(CASE WHEN room_units IS NOT NULL AND room_units != '' THEN JSON_LENGTH(room_units) ELSE 1 END) AS approved_total FROM room_bookings WHERE booking_status = 'approved' GROUP BY room_id"
            : "SELECT room_id, COUNT(*) AS approved_total FROM room_bookings WHERE booking_status = 'approved' GROUP BY room_id";
        $resApproved = $conn->query($sqlApproved);
        if ($resApproved) while ($row = $resApproved->fetch_assoc()) $approvedMap[(int)$row['room_id']] = (int)$row['approved_total'];
    }
}

$selectFields = ['id', 'room_name'];
if ($hasRoomType) $selectFields[] = 'room_type'; if ($hasPrice) $selectFields[] = 'price';
if ($hasRoomSize) $selectFields[] = 'room_size'; if ($hasBedType) $selectFields[] = 'bed_type';
if ($hasMaxGuests) $selectFields[] = 'max_guests'; if ($hasImagePath) $selectFields[] = 'image_path';
if ($hasDescription) $selectFields[] = 'description'; if ($hasTotalRooms) $selectFields[] = 'total_rooms';
if ($hasStatus) $selectFields[] = 'status'; if ($hasAmenities) $selectFields[] = 'amenities';

$sql = "SELECT " . implode(", ", $selectFields) . " FROM rooms WHERE 1=1";
if ($hasStatus) $sql .= " AND status = 'show'";
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->execute();
$result = $stmt->get_result();

$checkin_q  = trim($_GET['checkin']  ?? '');
$checkout_q = trim($_GET['checkout'] ?? '');
$guests_q   = (int)($_GET['guests']  ?? 1);
if ($checkin_q  === '') $checkin_q  = date('Y-m-d');
if ($checkout_q === '' || $checkout_q <= $checkin_q) $checkout_q = date('Y-m-d', strtotime($checkin_q.' +1 day'));
if ($guests_q < 1) $guests_q = 1;

$ci_ts    = strtotime($checkin_q);
$co_ts    = strtotime($checkout_q);
$nights_q = max(1, (int)(($co_ts - $ci_ts) / 86400));

function thDate2($s) {
    $m = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($s);
    return date('j',$ts).' '.$m[(int)date('m',$ts)].' '.(date('Y',$ts)+543);
}

$totalCount = $result ? $result->num_rows : 0;
$availCount = 0;
if ($result && $totalCount > 0) {
    $result->data_seek(0);
    while ($r = $result->fetch_assoc()) {
        $rid = (int)$r['id']; $tot = $hasTotalRooms ? max(1,(int)$r['total_rooms']) : 5;
        if (max(0,$tot-($approvedMap[$rid]??0)) > 0) $availCount++;
    }
    $result->data_seek(0);
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก | วลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ══════════════════════════════════════════
   ROOT VARIABLES
══════════════════════════════════════════ */
:root {
  --forest:   #162a1e;
  --forest2:  #1e3d2a;
  --forest3:  #2a5238;
  --gold:     #c9a96e;
  --gold2:    #e8c88a;
  --cream:    #f5f0e8;
  --cream2:   #faf7f2;
  --text:     #2c2c2c;
  --muted:    #7a7060;
  --white:    #ffffff;
  --red:      #c0392b;
  --shadow:   0 8px 40px rgba(22,42,30,.13);
  --radius:   16px;
  --tr:       .3s cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Sarabun', sans-serif;
  background: var(--cream2);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
}

/* ══════════════════════════════════════════
   HERO HEADER
══════════════════════════════════════════ */
.hero {
  background: linear-gradient(160deg, var(--forest) 0%, var(--forest2) 50%, var(--forest3) 100%);
  position: relative;
  overflow: hidden;
  padding: 0;
}

.hero::before {
  content: '';
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 70% 50%, rgba(201,169,110,.08) 0%, transparent 70%),
    url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a96e' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}

.hero-inner {
  position: relative; z-index: 1;
  padding: 28px 0 0;
}

/* Top nav */
.hero-nav {
  display: flex; align-items: center; gap: 8px;
  padding: 0 0 24px;
  flex-wrap: wrap;
}

.hero-nav a {
  color: rgba(245,240,232,.65);
  font-size: .8rem; font-weight: 500; letter-spacing: .03em;
  text-decoration: none;
  padding: 5px 14px;
  border: 1px solid rgba(201,169,110,.2);
  border-radius: 50px;
  transition: var(--tr);
  display: flex; align-items: center; gap: 5px;
}

.hero-nav a:hover {
  color: var(--gold);
  border-color: var(--gold);
  background: rgba(201,169,110,.08);
}

.hero-nav .back-link {
  color: var(--gold);
  border-color: rgba(201,169,110,.4);
  background: rgba(201,169,110,.1);
}

/* Hero content */
.hero-content {
  padding-bottom: 40px;
}

.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(201,169,110,.15);
  border: 1px solid rgba(201,169,110,.3);
  border-radius: 50px;
  padding: 5px 16px;
  font-size: .75rem; font-weight: 600; letter-spacing: .12em;
  color: var(--gold);
  text-transform: uppercase;
  margin-bottom: 18px;
}

.hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 5vw, 3.4rem);
  font-weight: 700;
  color: var(--cream);
  line-height: 1.15;
  margin-bottom: 12px;
  letter-spacing: -.01em;
}

.hero-title em {
  font-style: italic;
  color: var(--gold2);
}

.hero-sub {
  color: rgba(245,240,232,.6);
  font-size: .92rem; font-weight: 400; line-height: 1.7;
  max-width: 440px;
}

/* Night pill */
.night-pill {
  display: flex; align-items: center; gap: 14px;
  background: rgba(201,169,110,.12);
  border: 1px solid rgba(201,169,110,.25);
  border-radius: var(--radius);
  padding: 16px 22px;
  backdrop-filter: blur(10px);
  width: fit-content;
}

.night-num {
  font-family: 'Playfair Display', serif;
  font-size: 2.4rem; font-weight: 700;
  color: var(--gold2);
  line-height: 1;
}

.night-lbl {
  font-size: .75rem; font-weight: 600; letter-spacing: .1em;
  color: rgba(245,240,232,.6);
  text-transform: uppercase;
}

.night-sub {
  font-size: .72rem; color: var(--gold); margin-top: 2px;
}

/* ══════════════════════════════════════════
   SEARCH PANEL (floating)
══════════════════════════════════════════ */
.search-float {
  background: var(--white);
  border-radius: var(--radius);
  box-shadow: 0 20px 60px rgba(22,42,30,.18), 0 4px 16px rgba(22,42,30,.08);
  padding: 28px 28px 24px;
  margin-top: -36px;
  position: relative; z-index: 10;
  border: 1px solid rgba(201,169,110,.12);
}

.search-label {
  display: block;
  font-size: .7rem; font-weight: 700; letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 6px;
}

.search-input {
  width: 100%;
  border: 1.5px solid #e8e2d6;
  border-radius: 10px;
  padding: 11px 14px;
  font-family: 'Sarabun', sans-serif;
  font-size: .9rem;
  color: var(--text);
  background: var(--cream2);
  transition: var(--tr);
  outline: none;
}

.search-input:focus {
  border-color: var(--gold);
  background: var(--white);
  box-shadow: 0 0 0 3px rgba(201,169,110,.12);
}

.search-divider {
  width: 1px; background: #e8e2d6;
  align-self: stretch; margin: 0;
}

.search-btn {
  background: linear-gradient(135deg, var(--forest) 0%, var(--forest3) 100%);
  color: var(--gold2);
  border: none;
  border-radius: 10px;
  padding: 12px 28px;
  font-family: 'Sarabun', sans-serif;
  font-size: .92rem; font-weight: 700;
  cursor: pointer;
  transition: var(--tr);
  width: 100%;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  letter-spacing: .02em;
  box-shadow: 0 4px 20px rgba(22,42,30,.25);
}

.search-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(22,42,30,.35);
}

/* ══════════════════════════════════════════
   STATS BAR
══════════════════════════════════════════ */
.stats-bar {
  display: flex; gap: 0;
  background: var(--white);
  border-radius: var(--radius);
  overflow: hidden;
  border: 1px solid #ece7dd;
  box-shadow: 0 2px 12px rgba(22,42,30,.06);
}

.stat-item {
  flex: 1;
  padding: 18px 20px;
  display: flex; align-items: center; gap: 14px;
  position: relative;
}

.stat-item + .stat-item::before {
  content: '';
  position: absolute; left: 0; top: 20%; bottom: 20%;
  width: 1px;
  background: #ece7dd;
}

.stat-ico {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.stat-ico.green  { background: #e8f5ee; }
.stat-ico.gold   { background: #fdf5e8; }
.stat-ico.forest { background: #e8f0ec; }

.stat-val {
  font-family: 'Playfair Display', serif;
  font-size: 1.6rem; font-weight: 700;
  color: var(--forest); line-height: 1;
}

.stat-lbl {
  font-size: .72rem; color: var(--muted); margin-top: 2px;
  font-weight: 500;
}

/* ══════════════════════════════════════════
   SECTION HEADER
══════════════════════════════════════════ */
.section-head {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; flex-wrap: wrap;
}

.section-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem; font-weight: 700;
  color: var(--forest);
  display: flex; align-items: center; gap: 10px;
}

.section-title::before {
  content: '';
  width: 4px; height: 24px;
  background: linear-gradient(180deg, var(--gold) 0%, var(--forest3) 100%);
  border-radius: 4px;
  display: block;
  flex-shrink: 0;
}

.date-badge {
  background: var(--forest);
  color: var(--gold2);
  border-radius: 50px;
  padding: 6px 18px;
  font-size: .78rem; font-weight: 600;
  letter-spacing: .04em;
  white-space: nowrap;
}

/* ══════════════════════════════════════════
   ROOM CARDS
══════════════════════════════════════════ */
.room-card {
  background: var(--white);
  border-radius: var(--radius);
  overflow: hidden;
  border: 1px solid #ece7dd;
  box-shadow: 0 4px 20px rgba(22,42,30,.06);
  transition: var(--tr);
  display: flex; flex-direction: column;
  height: 100%;
}

.room-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 48px rgba(22,42,30,.14);
  border-color: rgba(201,169,110,.35);
}

.room-card.is-full {
  opacity: .72;
}

.room-card.is-full:hover {
  transform: none;
  box-shadow: 0 4px 20px rgba(22,42,30,.06);
  border-color: #ece7dd;
}

/* Image */
.card-img-wrap {
  position: relative;
  height: 200px;
  overflow: hidden;
  background: linear-gradient(135deg, var(--forest2), var(--forest3));
}

.card-img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform .6s cubic-bezier(.4,0,.2,1);
  display: block;
}

.room-card:hover .card-img { transform: scale(1.06); }

.card-img-ph {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 3rem;
  color: rgba(201,169,110,.5);
}

/* Badges on image */
.img-badge-avail {
  position: absolute; top: 12px; left: 12px;
  display: flex; align-items: center; gap: 6px;
  border-radius: 50px; padding: 5px 12px;
  font-size: .72rem; font-weight: 700;
  backdrop-filter: blur(8px);
  border: 1px solid transparent;
}

.img-badge-avail.ok {
  background: rgba(22, 101, 52, .85);
  color: #86efac;
  border-color: rgba(134,239,172,.2);
}

.img-badge-avail.full {
  background: rgba(127, 29, 29, .85);
  color: #fca5a5;
  border-color: rgba(252,165,165,.2);
}

.avail-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #4ade80;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%,100% { opacity:1; transform:scale(1); }
  50%      { opacity:.5; transform:scale(.75); }
}

.img-badge-price {
  position: absolute; bottom: 12px; right: 12px;
  background: var(--forest);
  color: var(--gold2);
  border-radius: 10px;
  padding: 6px 14px;
  font-family: 'Playfair Display', serif;
  font-size: .9rem; font-weight: 700;
  box-shadow: 0 4px 16px rgba(0,0,0,.3);
  border: 1px solid rgba(201,169,110,.2);
}

.img-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(22,42,30,.4) 0%, transparent 50%);
  pointer-events: none;
}

/* Card body */
.card-body-inner {
  padding: 20px 20px 16px;
  flex: 1;
}

.card-type {
  font-size: .68rem; font-weight: 700; letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 5px;
}

.card-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.15rem; font-weight: 700;
  color: var(--forest);
  margin-bottom: 8px; line-height: 1.3;
}

.card-desc {
  font-size: .8rem; color: var(--muted); line-height: 1.6;
  margin-bottom: 14px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Chips */
.chip-row { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 12px; }

.chip {
  display: inline-flex; align-items: center; gap: 4px;
  background: var(--cream);
  color: var(--muted);
  border-radius: 6px;
  padding: 4px 10px;
  font-size: .72rem; font-weight: 600;
  border: 1px solid #ece7dd;
}

.chip.gold-chip {
  background: #fdf5e8;
  color: #92600a;
  border-color: rgba(201,169,110,.3);
}

/* Amenity tags */
.am-tag {
  display: inline-flex; align-items: center; gap: 4px;
  background: #e8f0ec;
  color: var(--forest3);
  border-radius: 6px;
  padding: 3px 9px;
  font-size: .7rem; font-weight: 600;
}

/* Availability track */
.avail-row {
  display: flex; justify-content: space-between;
  font-size: .72rem; color: var(--muted); margin-bottom: 5px;
  font-weight: 500;
}

.avail-track {
  height: 4px; background: #ece7dd; border-radius: 99px;
  overflow: hidden;
}

.avail-fill {
  height: 100%; border-radius: 99px;
  background: linear-gradient(90deg, var(--forest3) 0%, var(--gold) 100%);
  transition: width .6s cubic-bezier(.4,0,.2,1);
}

.avail-fill.warn { background: linear-gradient(90deg, #d97706 0%, #ef4444 100%); }
.avail-fill.full { background: #ef4444; }

/* Card footer */
.card-foot {
  padding: 16px 20px;
  border-top: 1px solid #ece7dd;
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px;
  background: var(--cream2);
}

.price-big {
  font-family: 'Playfair Display', serif;
  font-size: 1.35rem; font-weight: 700;
  color: var(--forest);
  line-height: 1;
}

.price-sub {
  font-size: .67rem; color: var(--muted); margin-top: 2px; font-weight: 500;
}

.price-est {
  font-size: .72rem; color: var(--gold); margin-top: 3px; font-weight: 600;
}

/* Book button */
.book-btn {
  display: inline-flex; align-items: center; gap: 7px;
  background: linear-gradient(135deg, var(--forest) 0%, var(--forest3) 100%);
  color: var(--gold2);
  border-radius: 10px;
  padding: 10px 20px;
  font-family: 'Sarabun', sans-serif;
  font-size: .85rem; font-weight: 700;
  text-decoration: none;
  transition: var(--tr);
  white-space: nowrap;
  border: none; cursor: pointer;
  box-shadow: 0 4px 16px rgba(22,42,30,.25);
  letter-spacing: .02em;
}

.book-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(22,42,30,.35);
  color: var(--gold2);
}

.book-btn.disabled {
  background: #e8e2d6;
  color: #aaa;
  cursor: not-allowed;
  box-shadow: none;
}

.book-btn.disabled:hover { transform: none; box-shadow: none; }

/* ══════════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════════ */
.empty-state {
  text-align: center; padding: 70px 20px;
  background: var(--white);
  border-radius: var(--radius);
  border: 1px dashed #d4c9b4;
}

.empty-ico {
  font-size: 3.5rem; margin-bottom: 16px; display: block;
  filter: grayscale(.5) opacity(.7);
}

.empty-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.2rem; color: var(--forest); margin-bottom: 8px;
}

.empty-sub { font-size: .82rem; color: var(--muted); }

/* ══════════════════════════════════════════
   FADE-IN ANIMATION
══════════════════════════════════════════ */
@keyframes fadeUp {
  from { opacity:0; transform:translateY(24px); }
  to   { opacity:1; transform:translateY(0); }
}

.fade-up {
  opacity: 0;
  animation: fadeUp .6s cubic-bezier(.4,0,.2,1) forwards;
}

.delay-1 { animation-delay: .1s; }
.delay-2 { animation-delay: .2s; }
.delay-3 { animation-delay: .3s; }
.delay-4 { animation-delay: .4s; }

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */
@media (max-width: 767px) {
  .stats-bar { flex-direction: column; }
  .stat-item + .stat-item::before { top:0; bottom:auto; width:auto; height:1px; left:20%; right:20%; }
  .hero-title { font-size: 1.8rem; }
  .search-float { padding: 20px; }
}
</style>
</head>
<body>

<!-- ═══════════ HERO ═══════════ -->
<div class="hero">
  <div class="container-xl px-4">
    <div class="hero-inner">

      <nav class="hero-nav">
        <a href="index.php" class="back-link">← หน้าหลัก</a>
        <a href="booking_status.php">📋 สถานะการจอง</a>
        <a href="booking_tent.php">⛺ จองเต็นท์</a>
        <a href="booking_boat.php">🚣 จองพายเรือ</a>
      </nav>

      <div class="row align-items-end hero-content g-4">
        <div class="col">
          <div class="hero-eyebrow">🌿 ที่พักภายในสถาบัน</div>
          <h1 class="hero-title">จองห้องพัก<br><em>วลัยรุกขเวช</em></h1>
          <p class="hero-sub">ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น ท่ามกลางธรรมชาติ พร้อมสิ่งอำนวยความสะดวกครบครัน</p>
        </div>
        <div class="col-auto d-none d-md-block">
          <div class="night-pill">
            <div>
              <div class="night-num"><?= $nights_q ?></div>
            </div>
            <div>
              <div class="night-lbl">คืน</div>
              <div class="night-sub">🌙 <?= thDate2($checkin_q) ?> – <?= thDate2($checkout_q) ?></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════ MAIN ═══════════ -->
<div class="container-xl px-4 pb-5">

  <!-- Search Card (floating) -->
  <div class="search-float fade-up">
    <div class="d-flex align-items-center gap-2 mb-4">
      <div style="width:3px;height:18px;background:linear-gradient(var(--gold),var(--forest3));border-radius:4px;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);">ค้นหาห้องพัก</span>
    </div>
    <form method="GET" action="booking_room.php">
      <div class="row g-3 align-items-end">
        <div class="col-lg-3 col-sm-6">
          <label class="search-label">วันเช็คอิน</label>
          <input type="date" name="checkin" class="search-input" value="<?= htmlspecialchars($checkin_q) ?>" required>
        </div>
        <div class="col-lg-3 col-sm-6">
          <label class="search-label">วันเช็คเอาท์</label>
          <input type="date" name="checkout" class="search-input" value="<?= htmlspecialchars($checkout_q) ?>" required>
        </div>
        <div class="col-lg-2 col-sm-4">
          <label class="search-label">ผู้เข้าพัก</label>
          <input type="number" name="guests" class="search-input" min="1" max="20" value="<?= $guests_q ?>" required>
        </div>
        <div class="col-lg-4 col-sm-8">
          <button type="submit" class="search-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            ค้นหาห้องพัก
          </button>
        </div>
      </div>
    </form>
  </div>

  <div class="mt-4 mb-4 fade-up delay-1">
    <div class="stats-bar">
      <div class="stat-item">
        <div class="stat-ico forest">🏨</div>
        <div>
          <div class="stat-val"><?= $totalCount ?></div>
          <div class="stat-lbl">ประเภทห้องทั้งหมด</div>
        </div>
      </div>
      <div class="stat-item">
        <div class="stat-ico green">✅</div>
        <div>
          <div class="stat-val"><?= $availCount ?></div>
          <div class="stat-lbl">ประเภทที่ว่าง</div>
        </div>
      </div>
      <div class="stat-item">
        <div class="stat-ico gold">🌙</div>
        <div>
          <div class="stat-val"><?= $nights_q ?></div>
          <div class="stat-lbl">จำนวนคืน</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Section header -->
  <div class="section-head mb-3 fade-up delay-2">
    <div class="section-title">ห้องพักที่ว่าง &nbsp;·&nbsp; <?= $guests_q ?> ผู้เข้าพัก</div>
    <div class="date-badge">📅 <?= thDate2($checkin_q) ?> — <?= thDate2($checkout_q) ?></div>
  </div>

  <!-- Room Cards -->
  <?php
  $amIcons = ['แอร์'=>'❄️','TV'=>'📺','Wi-Fi'=>'📶','ตู้เย็น'=>'🧊','ห้องน้ำในตัว'=>'🚿','เครื่องทำน้ำอุ่น'=>'🔥','ระเบียง'=>'🌅','เตียงคู่'=>'🛏','เตียงเดี่ยว'=>'🛌'];
  ?>
  <?php if ($result && $result->num_rows > 0): ?>
  <div class="row g-3 fade-up delay-3">
    <?php
    $idx = 0;
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
      $idx++;
    ?>
    <div class="col-lg-4 col-md-6">
      <div class="room-card<?= $isFull ? ' is-full' : '' ?>">

        <!-- Image -->
        <div class="card-img-wrap">
          <?php if ($roomImg): ?>
            <img src="<?= htmlspecialchars($roomImg) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>" class="card-img"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div class="card-img-ph" style="display:none;">🏨</div>
          <?php else: ?>
            <div class="card-img-ph">🏨</div>
          <?php endif; ?>
          <div class="img-overlay"></div>
          <div class="img-badge-avail <?= $isFull ? 'full' : 'ok' ?>">
            <?php if (!$isFull): ?><span class="avail-dot"></span><?php endif; ?>
            <?= $isFull ? '🔴 ห้องเต็ม' : ('ว่าง '.$availableRooms.'/'.$totalRooms.' ห้อง') ?>
          </div>
          <div class="img-badge-price">฿<?= number_format($roomPrice) ?></div>
        </div>

        <!-- Body -->
        <div class="card-body-inner">
          <?php if (!empty($room['room_type'])): ?>
          <div class="card-type"><?= htmlspecialchars($room['room_type']) ?></div>
          <?php endif; ?>
          <div class="card-name"><?= htmlspecialchars($room['room_name']) ?></div>
          <?php if ($roomDesc): ?>
          <div class="card-desc"><?= htmlspecialchars($roomDesc) ?></div>
          <?php endif; ?>

          <div class="chip-row">
            <span class="chip gold-chip">฿<?= number_format($roomPrice) ?>/คืน</span>
            <span class="chip">🏠 <?= $totalRooms ?> ห้อง</span>
            <?php $cap = (int)($room['max_guests'] ?? 0); if ($cap > 0): ?>
            <span class="chip">👥 <?= $cap ?> คน</span>
            <?php endif; ?>
            <?php if (!empty($room['bed_type'])): ?>
            <span class="chip">🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
            <?php endif; ?>
            <?php if (!empty($room['room_size'])): ?>
            <span class="chip">📐 <?= htmlspecialchars($room['room_size']) ?></span>
            <?php endif; ?>
          </div>

          <?php if ($hasAmenities && !empty($room['amenities'])):
            $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
          <div class="chip-row mb-3">
            <?php foreach ($amItems as $am): ?>
            <span class="am-tag"><?= ($amIcons[$am] ?? '') ?> <?= htmlspecialchars($am) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="avail-row">
            <span>จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</span>
            <span style="color:<?= $isFull ? '#ef4444' : 'var(--forest3)' ?>;font-weight:700;">
              <?= $isFull ? 'เต็ม' : 'เหลือ '.$availableRooms.' ห้อง' ?>
            </span>
          </div>
          <div class="avail-track">
            <div class="avail-fill <?= $fillCls ?>" style="width:<?= min(100,$fillPct) ?>%"></div>
          </div>
        </div>

        <!-- Footer -->
        <div class="card-foot">
          <div>
            <div class="price-big">฿<?= number_format($roomPrice) ?></div>
            <div class="price-sub">ต่อห้อง / คืน</div>
            <?php if ($nights_q > 1 && !$isFull): ?>
            <div class="price-est">~฿<?= number_format($totalEst) ?> รวม <?= $nights_q ?> คืน</div>
            <?php endif; ?>
          </div>
          <?php if ($isFull): ?>
          <span class="book-btn disabled">ห้องเต็ม</span>
          <?php else: ?>
          <a class="book-btn" href="booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>">
            จองเลย
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <?php else: ?>
  <div class="empty-state fade-up delay-3">
    <span class="empty-ico">🏨</span>
    <div class="empty-title">ไม่พบห้องพักในช่วงเวลานี้</div>
    <div class="empty-sub">กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<script>
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