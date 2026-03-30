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
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}
:root{
    --ink:#1a1a2e;
    --gold:#c9a96e;
    --gold-dark:#a8864d;
    --bg:#f5f1eb;
    --card:#ffffff;
    --muted:#7a7a8c;
    --border:#e8e4de;
    --white:#ffffff;
    --danger:#d92d20;
    --danger-bg:#fff1f1;
    --success:#15803d;
    --success-bg:#ecfdf3;
    --card-shadow:0 14px 35px rgba(26,26,46,.10);
}
body{
    font-family:'Sarabun', 'Segoe UI', Tahoma, sans-serif;
    background:var(--bg);
    color:var(--ink);
}
a{
    text-decoration:none;
}
.page-wrap{
    min-height:100vh;
}
.hero{
    background:
        linear-gradient(135deg, rgba(26,26,46,.97) 0%, rgba(26,26,46,.88) 100%),
        url('uploads/room-banner.jpg') center/cover no-repeat;
    color:#fff;
    padding:70px 20px 120px;
    position:relative;
    overflow:hidden;
}
.hero::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(to bottom, rgba(255,255,255,0) 65%, var(--bg) 100%);
}
.hero-inner{
    width:min(1180px, 92%);
    margin:0 auto;
    position:relative;
    z-index:2;
}
.back-home-btn{
    display:inline-block;
    margin-bottom:20px;
    padding:10px 18px;
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    color:#fff;
    background:rgba(201,169,110,0.18);
    border:1px solid rgba(201,169,110,0.45);
    backdrop-filter:blur(8px);
    transition:.25s ease;
}
.back-home-btn:hover{
    background:rgba(201,169,110,0.32);
    color:var(--gold);
}
.hero-badge{
    display:inline-block;
    padding:10px 18px;
    border:1px solid rgba(201,169,110,.45);
    background:rgba(201,169,110,.14);
    backdrop-filter:blur(8px);
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    color:var(--gold);
    margin-bottom:18px;
}
.hero h1{
    font-size:48px;
    line-height:1.2;
    margin-bottom:14px;
    max-width:760px;
    color:#ffffff;
}
.hero h1 span{
    color:var(--gold);
}
.hero p{
    font-size:18px;
    line-height:1.8;
    color:rgba(255,255,255,.85);
    max-width:760px;
}
.section{
    width:min(1180px, 92%);
    margin:-40px auto 60px;
    position:relative;
    z-index:5;
}
.section-head{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:20px;
    margin-bottom:24px;
}
.section-head h3{
    font-size:34px;
    color:var(--ink);
}
.section-head p{
    color:var(--muted);
    max-width:760px;
    line-height:1.7;
}
.room-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(320px,1fr));
    gap:24px;
}
.room-card{
    background:var(--card);
    border-radius:20px;
    overflow:hidden;
    border:1px solid var(--border);
    box-shadow:var(--card-shadow);
    transition:.25s ease;
}
.room-card:hover{
    transform:translateY(-6px);
    box-shadow:0 20px 48px rgba(26,26,46,.14);
}
.room-image-wrap{
    position:relative;
}
.room-image{
    width:100%;
    height:240px;
    object-fit:cover;
    display:block;
    background:#ddd;
}
.room-price-tag{
    position:absolute;
    top:16px;
    right:16px;
    background:rgba(26,26,46,.88);
    color:var(--gold);
    padding:10px 14px;
    border-radius:999px;
    font-size:14px;
    font-weight:700;
    backdrop-filter:blur(8px);
    border:1px solid rgba(201,169,110,.3);
}
.room-stock-badge{
    position:absolute;
    top:16px;
    left:16px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    font-size:13px;
    font-weight:700;
    backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.25);
}
.room-stock-badge.available{
    background:rgba(21,128,61,.88);
    color:#fff;
}
.room-stock-badge.full{
    background:rgba(217,45,32,.88);
    color:#fff;
}
.room-body{
    padding:22px;
}
.room-title{
    font-size:24px;
    font-weight:800;
    margin-bottom:10px;
    color:var(--ink);
}
.room-desc{
    font-size:15px;
    line-height:1.7;
    color:var(--muted);
    margin-bottom:18px;
    min-height:76px;
}
.room-meta{
    display:grid;
    grid-template-columns:1fr;
    gap:10px;
    margin-bottom:20px;
}
.meta-item{
    background:var(--bg);
    border:1px solid var(--border);
    padding:12px 14px;
    border-radius:14px;
    font-size:14px;
    color:var(--muted);
}
.meta-item strong{
    color:var(--ink);
}
.booking-summary{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.summary-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    font-size:13px;
    font-weight:700;
    border:1px solid transparent;
}
.summary-pill.total{
    background:rgba(26,26,46,.07);
    color:var(--ink);
    border-color:rgba(26,26,46,.15);
}
.summary-pill.booked{
    background:rgba(201,169,110,.12);
    color:var(--gold-dark);
    border-color:rgba(201,169,110,.35);
}
.summary-pill.left{
    background:var(--success-bg);
    color:var(--success);
    border-color:#d1fadf;
}
.summary-pill.full{
    background:var(--danger-bg);
    color:var(--danger);
    border-color:#fecaca;
}
.room-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.price{
    font-size:28px;
    font-weight:800;
    color:var(--gold-dark);
}
.price span{
    font-size:14px;
    color:var(--muted);
    font-weight:500;
}
.book-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:160px;
    padding:13px 18px;
    border-radius:14px;
    background:var(--ink);
    color:#fff;
    font-weight:700;
    transition:.2s ease;
    border:none;
    cursor:pointer;
    font-family:'Sarabun', sans-serif;
}
.book-btn:hover{
    background:var(--gold);
    color:var(--ink);
}
.book-btn.disabled{
    background:#9ca3af;
    cursor:not-allowed;
    pointer-events:none;
}
.empty-box{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    padding:40px 25px;
    text-align:center;
    color:var(--muted);
    box-shadow:var(--card-shadow);
}
.footer-note{
    width:min(1180px, 92%);
    margin:0 auto 50px;
    background:rgba(26,26,46,.05);
    border:1px solid var(--border);
    border-radius:20px;
    padding:20px;
    color:var(--muted);
    line-height:1.8;
}
.footer-note strong{
    color:var(--ink);
}
@media (max-width: 768px){
    .hero{
        padding:50px 16px 100px;
    }
    .hero h1{
        font-size:34px;
    }
    .hero p{
        font-size:15px;
    }
    .section{
        margin-top:-30px;
    }
    .section-head{
        flex-direction:column;
        align-items:flex-start;
    }
    .section-head h3{
        font-size:28px;
    }
    .room-image{
        height:220px;
    }
    .room-title{
        font-size:21px;
    }
    .room-footer{
        flex-direction:column;
        align-items:flex-start;
    }
    .book-btn{
        width:100%;
    }
}
.amenity-list{display:flex;flex-wrap:wrap;gap:7px;margin-top:4px;margin-bottom:4px;}
.amenity-chip{
    display:inline-flex;align-items:center;gap:5px;
    padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;
    background:rgba(26,26,46,.06);color:var(--ink);
    border:1px solid var(--border);
}
.top-nav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px;}
.nav-btn{
    display:inline-flex;align-items:center;padding:10px 18px;
    border-radius:999px;font-size:14px;font-weight:600;color:#fff;
    background:rgba(201,169,110,0.18);border:1px solid rgba(201,169,110,0.45);
    backdrop-filter:blur(8px);transition:.25s ease;text-decoration:none;
}
.nav-btn:hover{background:rgba(201,169,110,0.35);color:var(--gold);}
</style>
</head>
<body>

<div class="page-wrap">

    <section class="hero">
        <div class="hero-inner">
            <div class="top-nav">
                <a href="index.php" class="nav-btn">← กลับหน้าหลัก</a>
                <a href="booking_status.php" class="nav-btn">ติดตามสถานะการจอง</a>
                <a href="booking_tent.php" class="nav-btn">จองเต็นท์</a>
                <a href="booking_boat.php" class="nav-btn">จองคิวพายเรือ</a>
            </div>
            <h1>ระบบจองห้องพักและที่พักภายในสถาบัน</h1>
            <p>
                เลือกห้องพักที่ต้องการจากรายการด้านล่างได้เลย
                เมื่อกดจอง ระบบจะพาไปยังหน้าแบบฟอร์มสำหรับกรอกข้อมูลการจองต่อทันที
                และจำนวนห้องที่แสดงจะอัปเดตตามรายการที่อนุมัติแล้วจากหลังบ้าน
            </p>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>ห้องพักแนะนำ</h3>
                <p>
                    แสดงจำนวนห้องทั้งหมด จำนวนที่ถูกอนุมัติการจองแล้ว และจำนวนห้องคงเหลือของแต่ละรายการ
                </p>
            </div>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="room-grid">
                <?php while($room = $result->fetch_assoc()): ?>
                    <?php
                        $roomId    = (int)$room['id'];
                        $roomImage = (!empty($room['image_path'])) ? $room['image_path'] : 'uploads/no-image.png';
                        $roomDesc  = (!empty($room['description'])) ? $room['description'] : 'ไม่มีรายละเอียดเพิ่มเติม';
                        $roomPrice = isset($room['price']) ? (float)$room['price'] : 0;

                        $totalRooms = $hasTotalRooms ? (int)$room['total_rooms'] : 5;
                        if ($totalRooms <= 0) {
                            $totalRooms = 5;
                        }

                        $approvedCount  = isset($approvedMap[$roomId]) ? (int)$approvedMap[$roomId] : 0;
                        $availableRooms = max(0, $totalRooms - $approvedCount);
                        $isFull         = ($availableRooms <= 0);
                    ?>
                    <div class="room-card">
                        <div class="room-image-wrap">
                            <img src="<?php echo htmlspecialchars($roomImage); ?>"
                                 alt="<?php echo htmlspecialchars($room['room_name']); ?>"
                                 class="room-image"
                                 onerror="this.src='uploads/no-image.png'">

                            <div class="room-price-tag">฿<?php echo number_format($roomPrice); ?> / คืน</div>

                            <div class="room-stock-badge <?php echo $isFull ? 'full' : 'available'; ?>">
                                <?php echo $isFull ? 'ห้องเต็ม' : 'ว่าง ' . $availableRooms . '/' . $totalRooms; ?>
                            </div>
                        </div>

                        <div class="room-body">
                            <div class="room-title"><?php echo htmlspecialchars($room['room_name']); ?></div>
                            <div class="room-desc"><?php echo htmlspecialchars($roomDesc); ?></div>

                            <div class="booking-summary">
                                <div class="summary-pill total">จำนวนทั้งหมด <?php echo $totalRooms; ?> ห้อง</div>
                                <div class="summary-pill booked">จองแล้ว <?php echo $approvedCount; ?>/<?php echo $totalRooms; ?></div>

                                <?php if ($isFull): ?>
                                    <div class="summary-pill full">คงเหลือ 0 ห้อง</div>
                                <?php else: ?>
                                    <div class="summary-pill left">คงเหลือ <?php echo $availableRooms; ?> ห้อง</div>
                                <?php endif; ?>
                            </div>

                            <div class="room-meta">
                                <?php if ($hasRoomType): ?>
                                    <div class="meta-item"><strong>ประเภทห้อง:</strong> <?php echo htmlspecialchars($room['room_type'] ?? '-'); ?></div>
                                <?php endif; ?>

                                <?php if ($hasRoomSize): ?>
                                    <div class="meta-item"><strong>ขนาดห้อง:</strong> <?php echo htmlspecialchars($room['room_size'] ?? '-'); ?></div>
                                <?php endif; ?>

                                <?php if ($hasBedType && !empty($room['bed_type'])): ?>
                                    <?php
                                        $bt = $room['bed_type'];
                                        $bedChips = [];
                                        if (strpos($bt, 'เตียงคู่') !== false)   $bedChips[] = '🛏️ เตียงคู่';
                                        if (strpos($bt, 'เตียงเดี่ยว') !== false) $bedChips[] = '🛌 เตียงเดี่ยว';
                                        if (empty($bedChips)) $bedChips[] = '🛏️ ' . $bt;
                                    ?>
                                    <div class="meta-item">
                                        <strong>ประเภทเตียง:</strong>
                                        <div class="amenity-list">
                                            <?php foreach ($bedChips as $bc): ?>
                                                <span class="amenity-chip"><?php echo htmlspecialchars($bc); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($hasMaxGuests): ?>
                                    <div class="meta-item"><strong>รองรับ:</strong> <?php echo (int)($room['max_guests'] ?? 0); ?> คน</div>
                                <?php endif; ?>

                                <?php if ($hasAmenities && !empty($room['amenities'])): ?>
                                    <?php
                                        $amIcons = [
                                            'เตียงคู่'       => '🛏️',
                                            'แอร์'           => '❄️',
                                            'TV'             => '📺',
                                            'Wi-Fi'          => '📶',
                                            'ตู้เย็น'        => '🧊',
                                            'ห้องน้ำในตัว'  => '🚿',
                                            'เครื่องทำน้ำอุ่น' => '🔥',
                                            'ระเบียง'        => '🌅',
                                        ];
                                        $amItems = array_filter(array_map('trim', explode('|', $room['amenities'])));
                                    ?>
                                    <div class="meta-item">
                                        <strong>สิ่งอำนวยความสะดวก:</strong>
                                        <div class="amenity-list">
                                            <?php foreach ($amItems as $am): ?>
                                                <span class="amenity-chip">
                                                    <?php echo isset($amIcons[$am]) ? $amIcons[$am] . ' ' : ''; echo htmlspecialchars($am); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="room-footer">
                                <div class="price">
                                    ฿<?php echo number_format($roomPrice); ?>
                                    <span>/ คืน</span>
                                </div>

                                <?php if ($isFull): ?>
                                    <span class="book-btn disabled">ห้องเต็ม</span>
                                <?php else: ?>
                                    <a class="book-btn" href="booking_form.php?room_id=<?php echo $roomId; ?>">
                                        จองห้องนี้
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-box">
                ไม่พบข้อมูลห้องพัก
            </div>
        <?php endif; ?>
    </section>

    <div class="footer-note">
        หมายเหตุ:
        จำนวน “จองแล้ว” จะนับเฉพาะรายการที่มีสถานะอนุมัติจากหลังบ้านเท่านั้น
        และถ้าคุณมีคอลัมน์ <strong>total_rooms</strong> ในตาราง <strong>rooms</strong>
        ระบบจะใช้ค่าจริงจากฐานข้อมูล แต่ถ้ายังไม่มี ระบบจะใช้ค่าเริ่มต้นเป็น 5 ห้องต่อรายการ
    </div>

</div>

</body>
</html>