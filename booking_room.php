<?php
session_start();
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
   เช็คคอลัมน์ที่อาจมี/ไม่มี
========================= */
$hasId          = in_array('id', $roomColumns, true);
$hasRoomName    = in_array('room_name', $roomColumns, true);
$hasRoomType    = in_array('room_type', $roomColumns, true);
$hasPrice       = in_array('price', $roomColumns, true);
$hasRoomSize    = in_array('room_size', $roomColumns, true);
$hasBedType     = in_array('bed_type', $roomColumns, true);
$hasCapacity    = in_array('capacity', $roomColumns, true);
$hasImage       = in_array('image', $roomColumns, true);
$hasDescription = in_array('description', $roomColumns, true);
$hasStatus      = in_array('status', $roomColumns, true);

if (!$hasId || !$hasRoomName) {
    die("ตาราง rooms ต้องมีคอลัมน์ id และ room_name อย่างน้อย");
}

/* =========================
   ไม่ใช้ฟอร์มค้นหาแล้ว
========================= */
$checkin  = '';
$checkout = '';
$guests   = '';
$type     = '';

/* =========================
   สร้าง SELECT แบบยืดหยุ่น
========================= */
$selectFields = ['id', 'room_name'];

if ($hasRoomType)    $selectFields[] = 'room_type';
if ($hasPrice)       $selectFields[] = 'price';
if ($hasRoomSize)    $selectFields[] = 'room_size';
if ($hasBedType)     $selectFields[] = 'bed_type';
if ($hasCapacity)    $selectFields[] = 'capacity';
if ($hasImage)       $selectFields[] = 'image';
if ($hasDescription) $selectFields[] = 'description';

/* =========================
   สร้าง SQL หลัก
========================= */
$sql = "SELECT " . implode(", ", $selectFields) . " FROM rooms WHERE 1=1";

if ($hasStatus) {
    $sql .= " AND status = 1";
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
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}
:root{
    --brand:#6f8428;
    --brand-dark:#58691f;
    --brand-light:#f4f8ea;
    --text:#1f2937;
    --muted:#6b7280;
    --line:#e5e7eb;
    --white:#ffffff;
    --bg:#f8fafc;
    --card-shadow:0 12px 35px rgba(0,0,0,.08);
}
body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:var(--bg);
    color:var(--text);
}
a{
    text-decoration:none;
}
.page-wrap{
    min-height:100vh;
}
.hero{
    background:
        linear-gradient(135deg, rgba(62,79,14,.92), rgba(111,132,40,.88)),
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
    background:linear-gradient(to bottom, rgba(255,255,255,0) 65%, rgba(248,250,252,1) 100%);
}
.hero-inner{
    width:min(1180px, 92%);
    margin:0 auto;
    position:relative;
    z-index:2;
}
.hero-badge{
    display:inline-block;
    padding:10px 18px;
    border:1px solid rgba(255,255,255,.35);
    background:rgba(255,255,255,.12);
    backdrop-filter:blur(8px);
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    margin-bottom:18px;
}
.hero h1{
    font-size:48px;
    line-height:1.2;
    margin-bottom:14px;
    max-width:760px;
}
.hero p{
    font-size:18px;
    line-height:1.8;
    color:rgba(255,255,255,.92);
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
    color:#111827;
}
.section-head p{
    color:var(--muted);
    max-width:700px;
    line-height:1.7;
}
.room-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(320px,1fr));
    gap:24px;
}
.room-card{
    background:#fff;
    border-radius:24px;
    overflow:hidden;
    border:1px solid #edf0f5;
    box-shadow:var(--card-shadow);
    transition:.25s ease;
}
.room-card:hover{
    transform:translateY(-6px);
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
    background:rgba(17,24,39,.82);
    color:#fff;
    padding:10px 14px;
    border-radius:999px;
    font-size:14px;
    font-weight:700;
    backdrop-filter:blur(8px);
}
.room-body{
    padding:22px;
}
.room-title{
    font-size:24px;
    font-weight:800;
    margin-bottom:10px;
    color:#111827;
}
.room-desc{
    font-size:15px;
    line-height:1.7;
    color:#4b5563;
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
    background:#f8fafc;
    border:1px solid #e9eef5;
    padding:12px 14px;
    border-radius:14px;
    font-size:14px;
    color:#374151;
}
.meta-item strong{
    color:#111827;
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
    color:var(--brand-dark);
}
.price span{
    font-size:14px;
    color:#6b7280;
    font-weight:500;
}
.book-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:150px;
    padding:13px 18px;
    border-radius:14px;
    background:var(--brand);
    color:#fff;
    font-weight:700;
    transition:.2s ease;
}
.book-btn:hover{
    background:var(--brand-dark);
}
.empty-box{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:20px;
    padding:40px 25px;
    text-align:center;
    color:#6b7280;
    box-shadow:var(--card-shadow);
}
.footer-note{
    width:min(1180px, 92%);
    margin:0 auto 50px;
    background:var(--brand-light);
    border:1px solid #e2ecd0;
    border-radius:20px;
    padding:20px;
    color:#3f4b1d;
    line-height:1.8;
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
</style>
</head>
<body>

<div class="page-wrap">

    <section class="hero">
        <div class="hero-inner">
            <div class="hero-badge">Room Reservation</div>
            <h1>ระบบจองห้องพักและที่พักภายในสถาบัน</h1>
            <p>
                เลือกห้องพักที่ต้องการจากรายการด้านล่างได้เลย
                เมื่อกดจอง ระบบจะพาไปยังหน้าแบบฟอร์มสำหรับกรอกข้อมูลการจองต่อทันที
            </p>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>ห้องพักแนะนำ</h3>
                <p>ข้อมูลห้องพักทั้งหมดถูกดึงจากฐานข้อมูลโดยตรง และเมื่อกดจองจะส่งข้อมูลไปหน้าแบบฟอร์มจอง</p>
            </div>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="room-grid">
                <?php while($room = $result->fetch_assoc()): ?>
                    <?php
                        $roomImage = (!empty($room['image'])) ? $room['image'] : 'uploads/no-image.png';
                        $roomDesc  = (!empty($room['description'])) ? $room['description'] : 'ไม่มีรายละเอียดเพิ่มเติม';
                        $roomPrice = isset($room['price']) ? (float)$room['price'] : 0;
                    ?>
                    <div class="room-card">
                        <div class="room-image-wrap">
                            <img src="<?php echo htmlspecialchars($roomImage); ?>"
                                 alt="<?php echo htmlspecialchars($room['room_name']); ?>"
                                 class="room-image"
                                 onerror="this.src='uploads/no-image.png'">
                            <div class="room-price-tag">฿<?php echo number_format($roomPrice); ?> / คืน</div>
                        </div>

                        <div class="room-body">
                            <div class="room-title"><?php echo htmlspecialchars($room['room_name']); ?></div>
                            <div class="room-desc"><?php echo htmlspecialchars($roomDesc); ?></div>

                            <div class="room-meta">
                                <?php if ($hasRoomType): ?>
                                    <div class="meta-item"><strong>ประเภทห้อง:</strong> <?php echo htmlspecialchars($room['room_type'] ?? '-'); ?></div>
                                <?php endif; ?>

                                <?php if ($hasRoomSize): ?>
                                    <div class="meta-item"><strong>ขนาดห้อง:</strong> <?php echo htmlspecialchars($room['room_size'] ?? '-'); ?></div>
                                <?php endif; ?>

                                <?php if ($hasBedType): ?>
                                    <div class="meta-item"><strong>ประเภทเตียง:</strong> <?php echo htmlspecialchars($room['bed_type'] ?? '-'); ?></div>
                                <?php endif; ?>

                                <?php if ($hasCapacity): ?>
                                    <div class="meta-item"><strong>รองรับ:</strong> <?php echo htmlspecialchars($room['capacity'] ?? '-'); ?> คน</div>
                                <?php endif; ?>
                            </div>

                            <div class="room-footer">
                                <div class="price">
                                    ฿<?php echo number_format($roomPrice); ?>
                                    <span>/ คืน</span>
                                </div>

                                <a class="book-btn"
                                   href="/Projate/booking_form.php?room_id=<?php echo (int)$room['id']; ?>">
                                   จองห้องนี้
                                </a>
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
        หมายเหตุ: หน้านี้จะพยายามดึงข้อมูลจากตาราง rooms เท่าที่มีจริงในฐานข้อมูล และจะไม่พังแม้บางคอลัมน์ยังไม่ได้สร้าง
    </div>

</div>

</body>
</html>