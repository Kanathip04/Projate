<?php
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

/* === สร้างตาราง boat_queues ถ้ายังไม่มี === */
$conn->query("CREATE TABLE IF NOT EXISTS `boat_queues` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_name` VARCHAR(200) NOT NULL,
    `queue_date` DATE NOT NULL,
    `time_start` TIME NOT NULL,
    `time_end` TIME NOT NULL,
    `total_boats` INT DEFAULT 5,
    `price_per_boat` DECIMAL(10,2) DEFAULT 0,
    `description` TEXT,
    `image_path` VARCHAR(500) DEFAULT '',
    `status` ENUM('show','hide') DEFAULT 'show',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* === สร้างตาราง boat_bookings ถ้ายังไม่มี === */
$conn->query("CREATE TABLE IF NOT EXISTS `boat_bookings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id` INT UNSIGNED DEFAULT NULL,
    `full_name` VARCHAR(200) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `email` VARCHAR(200) DEFAULT '',
    `queue_name` VARCHAR(200) DEFAULT '',
    `guests` INT DEFAULT 1,
    `boat_date` DATE DEFAULT NULL,
    `time_start` TIME DEFAULT NULL,
    `time_end` TIME DEFAULT NULL,
    `boat_units` TEXT DEFAULT NULL,
    `note` TEXT,
    `booking_status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    `archived` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* === นับเรือที่จองแล้ว (approved) ต่อคิว === */
$approvedMap = [];
$resAp = $conn->query(
    "SELECT queue_id,
            SUM(CASE WHEN boat_units IS NOT NULL AND boat_units != ''
                THEN JSON_LENGTH(boat_units) ELSE 1 END) AS total
     FROM boat_bookings WHERE booking_status='approved' GROUP BY queue_id"
);
if ($resAp) while ($r = $resAp->fetch_assoc()) $approvedMap[(int)$r['queue_id']] = (int)$r['total'];

/* === ดึงคิว (แยกตามวัน) === */
$today  = date('Y-m-d');
$queues = $conn->query("SELECT * FROM boat_queues WHERE status='show' AND queue_date >= '$today' ORDER BY queue_date ASC, time_start ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองคิวพายเรือ | สถาบันวิจัยวลัยรุกขเวช</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
    --bg:#f0f7ff;--card:#ffffff;--muted:#7a7a8c;
    --border:#dce8f5;--white:#ffffff;
    --danger:#d92d20;--danger-bg:#fff1f1;
    --success:#15803d;--success-bg:#ecfdf3;
    --blue:#1d6fad;--blue-light:#e8f4ff;
    --card-shadow:0 14px 35px rgba(29,111,173,.10);
}
body{font-family:'Sarabun','Segoe UI',Tahoma,sans-serif;background:var(--bg);color:var(--ink);}
a{text-decoration:none;}
.hero{
    background:linear-gradient(145deg,#0a1628 0%,#0d2344 40%,#1a3a5c 70%,#0d2344 100%);
    color:#fff;padding:70px 20px 120px;position:relative;overflow:hidden;
}
.hero::before{content:"";position:absolute;inset:0;
    background-image:radial-gradient(circle at 15% 85%,rgba(29,111,173,.35) 0%,transparent 45%),
    radial-gradient(circle at 85% 20%,rgba(201,169,110,.12) 0%,transparent 40%);pointer-events:none;}
.hero::after{content:"";position:absolute;inset:0;background:linear-gradient(to bottom,rgba(255,255,255,0) 65%,var(--bg) 100%);pointer-events:none;}
.hero-inner{width:min(1180px,92%);margin:0 auto;position:relative;z-index:2;}
.top-nav{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px;}
.nav-btn{display:inline-flex;align-items:center;padding:10px 18px;border-radius:999px;font-size:14px;font-weight:600;color:#fff;background:rgba(29,111,173,0.25);border:1px solid rgba(29,111,173,0.5);backdrop-filter:blur(8px);transition:.25s ease;}
.nav-btn:hover{background:rgba(29,111,173,0.45);color:#7ec8f4;}
.hero-badge{display:inline-block;padding:10px 18px;border:1px solid rgba(29,111,173,.5);background:rgba(29,111,173,.2);backdrop-filter:blur(8px);border-radius:999px;font-size:14px;font-weight:600;color:#7ec8f4;margin-bottom:18px;}
.hero h1{font-size:46px;line-height:1.2;margin-bottom:14px;max-width:760px;color:#fff;}
.hero h1 span{color:#7ec8f4;}
.hero p{font-size:17px;line-height:1.8;color:rgba(255,255,255,.85);max-width:760px;}
.section{width:min(1180px,92%);margin:-40px auto 60px;position:relative;z-index:5;}
.section-head{margin-bottom:24px;}
.section-head h3{font-size:32px;color:var(--ink);margin-bottom:6px;}
.section-head p{color:var(--muted);line-height:1.7;}
.date-group{margin-bottom:36px;}
.date-label{display:inline-flex;align-items:center;gap:10px;font-size:15px;font-weight:700;color:var(--blue);background:var(--blue-light);border:1px solid #b8d8f5;padding:8px 18px;border-radius:999px;margin-bottom:16px;}
.queue-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;}
.queue-card{background:var(--card);border-radius:20px;overflow:hidden;border:1px solid var(--border);box-shadow:var(--card-shadow);transition:.25s ease;}
.queue-card:hover{transform:translateY(-5px);box-shadow:0 20px 48px rgba(29,111,173,.14);}
.queue-img-wrap{position:relative;}
.queue-img{width:100%;height:200px;object-fit:cover;display:block;background:#c9dff5;}
.queue-price-tag{position:absolute;top:14px;right:14px;background:rgba(10,22,40,.88);color:#7ec8f4;padding:8px 13px;border-radius:999px;font-size:13px;font-weight:700;backdrop-filter:blur(8px);border:1px solid rgba(29,111,173,.4);}
.queue-avail-badge{position:absolute;top:14px;left:14px;display:inline-flex;align-items:center;gap:7px;padding:8px 13px;border-radius:999px;font-size:13px;font-weight:700;backdrop-filter:blur(8px);}
.queue-avail-badge.available{background:rgba(21,128,61,.88);color:#fff;}
.queue-avail-badge.full{background:rgba(217,45,32,.88);color:#fff;}
.queue-body{padding:20px;}
.queue-title{font-size:20px;font-weight:800;margin-bottom:8px;color:var(--ink);}
.queue-time{display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:700;color:var(--blue);background:var(--blue-light);padding:6px 14px;border-radius:999px;margin-bottom:12px;border:1px solid #b8d8f5;}
.queue-desc{font-size:14px;line-height:1.7;color:var(--muted);margin-bottom:14px;min-height:60px;}
.booking-summary{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.summary-pill{display:inline-flex;align-items:center;gap:7px;padding:7px 13px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent;}
.summary-pill.total{background:rgba(29,111,173,.08);color:var(--blue);border-color:rgba(29,111,173,.2);}
.summary-pill.booked{background:rgba(201,169,110,.12);color:var(--gold-dark);border-color:rgba(201,169,110,.35);}
.summary-pill.left{background:var(--success-bg);color:var(--success);border-color:#d1fadf;}
.summary-pill.full{background:var(--danger-bg);color:var(--danger);border-color:#fecaca;}
.queue-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
.price{font-size:22px;font-weight:800;color:var(--blue);}
.price span{font-size:13px;color:var(--muted);font-weight:500;}
.book-btn{display:inline-flex;align-items:center;justify-content:center;min-width:130px;padding:11px 16px;border-radius:12px;background:var(--ink);color:#fff;font-weight:700;font-size:14px;transition:.2s ease;font-family:'Sarabun',sans-serif;}
.book-btn:hover{background:var(--blue);color:#fff;}
.book-btn.disabled{background:#9ca3af;cursor:not-allowed;pointer-events:none;}
.empty-box{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:60px 25px;text-align:center;color:var(--muted);box-shadow:var(--card-shadow);}
.empty-box h3{font-size:22px;color:var(--ink);margin-bottom:8px;}
@media(max-width:768px){
    .hero{padding:50px 16px 100px;}
    .hero h1{font-size:32px;}
    .section{margin-top:-30px;}
    .queue-footer{flex-direction:column;align-items:flex-start;}
    .book-btn{width:100%;}
}
</style>
</head>
<body>

<section class="hero">
    <div class="hero-inner">
        <div class="top-nav">
            <a href="index.php" class="nav-btn">← กลับหน้าหลัก</a>
            <a href="booking_boat_status.php" class="nav-btn">ติดตามสถานะการจอง</a>
            <a href="booking_room.php" class="nav-btn">จองห้องพัก</a>
            <a href="booking_tent.php" class="nav-btn">จองเต็นท์</a>
        </div>
        <div class="hero-badge">🚣 ระบบจองคิวพายเรือ</div>
        <h1>จองคิว<span>พายเรือ</span></h1>
        <p>เลือกรอบเวลาที่ต้องการ ระบบแสดงจำนวนเรือว่างแบบ real-time พร้อมเลือกหมายเลขเรือได้เลย</p>
    </div>
</section>

<div class="section">
    <div class="section-head">
        <h3>🚣 คิวพายเรือที่เปิดจอง</h3>
        <p>แสดงเฉพาะรอบที่ยังมีเรือว่างและวันที่ยังไม่ผ่านมา</p>
    </div>

    <?php
    $grouped = [];
    if ($queues && $queues->num_rows > 0) {
        while ($q = $queues->fetch_assoc()) $grouped[$q['queue_date']][] = $q;
    }
    ?>

    <?php if (!empty($grouped)): ?>
        <?php foreach ($grouped as $date => $dayQueues): ?>
            <div class="date-group">
                <div class="date-label">
                    📅 <?= date('l, d F Y', strtotime($date)) ?>
                    <?php if ($date === $today): ?><span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:6px;font-size:11px;">วันนี้</span><?php endif; ?>
                </div>
                <div class="queue-grid">
                    <?php foreach ($dayQueues as $q): ?>
                        <?php
                            $qid    = (int)$q['id'];
                            $img    = !empty($q['image_path']) ? $q['image_path'] : 'uploads/no-image.png';
                            $desc   = !empty($q['description']) ? $q['description'] : 'ไม่มีรายละเอียดเพิ่มเติม';
                            $price  = (float)$q['price_per_boat'];
                            $total  = max(1, (int)$q['total_boats']);
                            $booked = $approvedMap[$qid] ?? 0;
                            $avail  = max(0, $total - $booked);
                            $isFull = ($avail <= 0);
                        ?>
                        <div class="queue-card">
                            <div class="queue-img-wrap">
                                <img src="<?= htmlspecialchars($img) ?>"
                                     alt="<?= htmlspecialchars($q['queue_name']) ?>"
                                     class="queue-img"
                                     onerror="this.src='uploads/no-image.png'">
                                <?php if ($price > 0): ?>
                                    <div class="queue-price-tag">฿<?= number_format($price) ?> / ลำ</div>
                                <?php else: ?>
                                    <div class="queue-price-tag">ฟรี</div>
                                <?php endif; ?>
                                <div class="queue-avail-badge <?= $isFull ? 'full' : 'available' ?>">
                                    <?= $isFull ? 'เต็มแล้ว' : 'ว่าง ' . $avail . '/' . $total ?>
                                </div>
                            </div>
                            <div class="queue-body">
                                <div class="queue-title"><?= htmlspecialchars($q['queue_name']) ?></div>
                                <div class="queue-time">
                                    🕐 <?= substr($q['time_start'],0,5) ?> – <?= substr($q['time_end'],0,5) ?> น.
                                </div>
                                <div class="queue-desc"><?= htmlspecialchars($desc) ?></div>
                                <div class="booking-summary">
                                    <div class="summary-pill total">เรือทั้งหมด <?= $total ?> ลำ</div>
                                    <div class="summary-pill booked">จองแล้ว <?= $booked ?>/<?= $total ?></div>
                                    <?php if ($isFull): ?>
                                        <div class="summary-pill full">คงเหลือ 0 ลำ</div>
                                    <?php else: ?>
                                        <div class="summary-pill left">คงเหลือ <?= $avail ?> ลำ</div>
                                    <?php endif; ?>
                                </div>
                                <div class="queue-footer">
                                    <div class="price">
                                        <?= $price > 0 ? '฿'.number_format($price) : 'ฟรี' ?>
                                        <?php if ($price > 0): ?><span> / ลำ</span><?php endif; ?>
                                    </div>
                                    <?php if ($isFull): ?>
                                        <span class="book-btn disabled">เต็มแล้ว</span>
                                    <?php else: ?>
                                        <a href="booking_boat_form.php?queue_id=<?= $qid ?>" class="book-btn">🚣 จองคิวนี้</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-box">
            <h3>🚣 ยังไม่มีคิวพายเรือที่เปิดจอง</h3>
            <p>กรุณาติดต่อเจ้าหน้าที่หรือรอเพิ่มคิวใหม่</p>
        </div>
    <?php endif; ?>
</div>

<?php $conn->close(); ?>
</body>
</html>
