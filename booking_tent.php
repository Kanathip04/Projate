<?php
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

/* === สร้างตาราง tents ถ้ายังไม่มี === */
$conn->query("CREATE TABLE IF NOT EXISTS `tents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tent_name` VARCHAR(200) NOT NULL,
    `tent_type` VARCHAR(100) DEFAULT '',
    `capacity` INT DEFAULT 4,
    `price_per_night` DECIMAL(10,2) DEFAULT 0,
    `total_tents` INT DEFAULT 5,
    `description` TEXT,
    `image_path` VARCHAR(500) DEFAULT '',
    `status` ENUM('show','hide') DEFAULT 'show',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* === สร้างตาราง tent_bookings ถ้ายังไม่มี === */
$conn->query("CREATE TABLE IF NOT EXISTS `tent_bookings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tent_id` INT UNSIGNED DEFAULT NULL,
    `full_name` VARCHAR(200) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `email` VARCHAR(200) DEFAULT '',
    `tent_type` VARCHAR(200) DEFAULT '',
    `guests` INT DEFAULT 1,
    `checkin_date` DATE DEFAULT NULL,
    `checkout_date` DATE DEFAULT NULL,
    `note` TEXT,
    `booking_status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    `archived` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* === นับการจองที่อนุมัติแล้วต่อเต็นท์ === */
$approvedMap = [];
$resAp = $conn->query("SELECT tent_id, COUNT(*) AS cnt FROM tent_bookings WHERE booking_status='approved' GROUP BY tent_id");
if ($resAp) {
    while ($r = $resAp->fetch_assoc()) {
        $approvedMap[(int)$r['tent_id']] = (int)$r['cnt'];
    }
}

/* === ดึงรายการเต็นท์ === */
$result = $conn->query("SELECT * FROM tents WHERE status='show' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองเต็นท์ | สถาบันวิจัยวลัยรุกขเวช</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
    --bg:#f5f1eb;--card:#ffffff;--muted:#7a7a8c;
    --border:#e8e4de;--white:#ffffff;
    --danger:#d92d20;--danger-bg:#fff1f1;
    --success:#15803d;--success-bg:#ecfdf3;
    --card-shadow:0 14px 35px rgba(26,26,46,.10);
}
body{font-family:'Sarabun','Segoe UI',Tahoma,sans-serif;background:var(--bg);color:var(--ink);}
a{text-decoration:none;}
.hero{
    background:
        radial-gradient(ellipse at 20% 50%, rgba(21,128,61,.25) 0%, transparent 55%),
        radial-gradient(ellipse at 80% 20%, rgba(201,169,110,.12) 0%, transparent 50%),
        linear-gradient(145deg,#0d1f0d 0%,#1a2e1a 35%,#1a1a2e 70%,#0f0f1e 100%);
    color:#fff;padding:70px 20px 120px;position:relative;overflow:hidden;
}
.hero::before{content:"";position:absolute;inset:0;
    background-image:radial-gradient(circle at 15% 85%,rgba(21,128,61,.18) 0%,transparent 40%),
    radial-gradient(circle at 85% 15%,rgba(201,169,110,.08) 0%,transparent 40%);
    pointer-events:none;}
.hero::after{content:"";position:absolute;inset:0;background:linear-gradient(to bottom,rgba(255,255,255,0) 65%,var(--bg) 100%);pointer-events:none;}
.hero-inner{width:min(1180px,92%);margin:0 auto;position:relative;z-index:2;}
.top-nav{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px;}
.nav-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:999px;font-size:14px;font-weight:600;color:#fff;background:rgba(201,169,110,0.18);border:1px solid rgba(201,169,110,0.45);backdrop-filter:blur(8px);transition:.25s ease;}
.nav-btn:hover{background:rgba(201,169,110,0.32);color:var(--gold);}
.hero-badge{display:inline-block;padding:10px 18px;border:1px solid rgba(201,169,110,.45);background:rgba(201,169,110,.14);backdrop-filter:blur(8px);border-radius:999px;font-size:14px;font-weight:600;color:var(--gold);margin-bottom:18px;}
.hero h1{font-size:46px;line-height:1.2;margin-bottom:14px;max-width:760px;color:#fff;}
.hero h1 span{color:var(--gold);}
.hero p{font-size:17px;line-height:1.8;color:rgba(255,255,255,.85);max-width:760px;}
.section{width:min(1180px,92%);margin:-40px auto 60px;position:relative;z-index:5;}
.section-head{margin-bottom:24px;}
.section-head h3{font-size:32px;color:var(--ink);margin-bottom:6px;}
.section-head p{color:var(--muted);line-height:1.7;}
.tent-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;}
.tent-card{background:var(--card);border-radius:20px;overflow:hidden;border:1px solid var(--border);box-shadow:var(--card-shadow);transition:.25s ease;}
.tent-card:hover{transform:translateY(-6px);box-shadow:0 20px 48px rgba(26,26,46,.14);}
.tent-image-wrap{position:relative;}
.tent-image{width:100%;height:240px;object-fit:cover;display:block;background:#ddd;}
.tent-price-tag{position:absolute;top:16px;right:16px;background:rgba(26,26,46,.88);color:var(--gold);padding:10px 14px;border-radius:999px;font-size:14px;font-weight:700;backdrop-filter:blur(8px);border:1px solid rgba(201,169,110,.3);}
.tent-stock-badge{position:absolute;top:16px;left:16px;display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;font-size:13px;font-weight:700;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.25);}
.tent-stock-badge.available{background:rgba(21,128,61,.88);color:#fff;}
.tent-stock-badge.full{background:rgba(217,45,32,.88);color:#fff;}
.tent-body{padding:22px;}
.tent-title{font-size:22px;font-weight:800;margin-bottom:10px;color:var(--ink);}
.tent-desc{font-size:15px;line-height:1.7;color:var(--muted);margin-bottom:18px;min-height:72px;}
.booking-summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.summary-pill{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;font-size:13px;font-weight:700;border:1px solid transparent;}
.summary-pill.total{background:rgba(26,26,46,.07);color:var(--ink);border-color:rgba(26,26,46,.15);}
.summary-pill.booked{background:rgba(201,169,110,.12);color:var(--gold-dark);border-color:rgba(201,169,110,.35);}
.summary-pill.left{background:var(--success-bg);color:var(--success);border-color:#d1fadf;}
.summary-pill.full{background:var(--danger-bg);color:var(--danger);border-color:#fecaca;}
.tent-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;}
.meta-item{background:var(--bg);border:1px solid var(--border);padding:12px 14px;border-radius:14px;font-size:14px;color:var(--muted);}
.meta-item strong{color:var(--ink);}
.tent-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.price{font-size:26px;font-weight:800;color:var(--gold-dark);}
.price span{font-size:14px;color:var(--muted);font-weight:500;}
.book-btn{display:inline-flex;align-items:center;justify-content:center;min-width:150px;padding:13px 18px;border-radius:14px;background:var(--ink);color:#fff;font-weight:700;font-size:15px;transition:.2s ease;font-family:'Sarabun',sans-serif;}
.book-btn:hover{background:var(--gold);color:var(--ink);}
.book-btn.disabled{background:#9ca3af;cursor:not-allowed;pointer-events:none;}
.empty-box{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:60px 25px;text-align:center;color:var(--muted);box-shadow:var(--card-shadow);}
.empty-box h3{font-size:22px;color:var(--ink);margin-bottom:8px;}
@media(max-width:768px){
    .hero{padding:50px 16px 100px;}
    .hero h1{font-size:34px;}
    .section{margin-top:-30px;}
    .tent-image{height:210px;}
    .tent-meta{grid-template-columns:1fr;}
    .tent-footer{flex-direction:column;align-items:flex-start;}
    .book-btn{width:100%;}
}
</style>
</head>
<body>

<section class="hero">
    <div class="hero-inner">
        <div class="top-nav">
            <a href="index.php" class="nav-btn">← กลับหน้าหลัก</a>
            <a href="booking_tent_status.php" class="nav-btn">ติดตามสถานะการจอง</a>
            <a href="booking_room.php" class="nav-btn">จองห้องพัก</a>
        </div>
        <div class="hero-badge">⛺ ระบบจองเต็นท์</div>
        <h1>จองเต็นท์<span>กลางแจ้ง</span></h1>
        <p>เลือกเต็นท์ที่ต้องการจากรายการด้านล่าง เหมาะสำหรับการพักค้างคืนกลางธรรมชาติ จำนวนเต็นท์จะอัปเดตตามรายการที่อนุมัติแล้ว</p>
    </div>
</section>

<div class="section">
    <div class="section-head">
        <h3>⛺ รายการเต็นท์</h3>
        <p>แสดงจำนวนเต็นท์ทั้งหมด จำนวนที่จองแล้ว และจำนวนคงเหลือ</p>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="tent-grid">
            <?php while ($tent = $result->fetch_assoc()): ?>
                <?php
                    $tid        = (int)$tent['id'];
                    $img        = !empty($tent['image_path']) ? $tent['image_path'] : 'uploads/no-image.png';
                    $desc       = !empty($tent['description']) ? $tent['description'] : 'ไม่มีรายละเอียดเพิ่มเติม';
                    $price      = (float)$tent['price_per_night'];
                    $total      = max(1, (int)$tent['total_tents']);
                    $booked     = isset($approvedMap[$tid]) ? (int)$approvedMap[$tid] : 0;
                    $avail      = max(0, $total - $booked);
                    $isFull     = ($avail <= 0);
                ?>
                <div class="tent-card">
                    <div class="tent-image-wrap">
                        <img src="<?= htmlspecialchars($img) ?>"
                             alt="<?= htmlspecialchars($tent['tent_name']) ?>"
                             class="tent-image"
                             onerror="this.src='uploads/no-image.png'">
                        <div class="tent-price-tag">฿<?= number_format($price) ?> / คืน</div>
                        <div class="tent-stock-badge <?= $isFull ? 'full' : 'available' ?>">
                            <?= $isFull ? 'เต็มแล้ว' : 'ว่าง ' . $avail . '/' . $total ?>
                        </div>
                    </div>
                    <div class="tent-body">
                        <div class="tent-title"><?= htmlspecialchars($tent['tent_name']) ?></div>
                        <div class="tent-desc"><?= htmlspecialchars($desc) ?></div>

                        <div class="booking-summary">
                            <div class="summary-pill total">ทั้งหมด <?= $total ?> หลัง</div>
                            <div class="summary-pill booked">จองแล้ว <?= $booked ?>/<?= $total ?></div>
                            <?php if ($isFull): ?>
                                <div class="summary-pill full">คงเหลือ 0 หลัง</div>
                            <?php else: ?>
                                <div class="summary-pill left">คงเหลือ <?= $avail ?> หลัง</div>
                            <?php endif; ?>
                        </div>

                        <div class="tent-meta">
                            <?php if (!empty($tent['tent_type'])): ?>
                                <div class="meta-item"><strong>ประเภท:</strong> <?= htmlspecialchars($tent['tent_type']) ?></div>
                            <?php endif; ?>
                            <div class="meta-item"><strong>รองรับ:</strong> <?= (int)$tent['capacity'] ?> คน</div>
                        </div>

                        <div class="tent-footer">
                            <div class="price">฿<?= number_format($price) ?><span> / คืน</span></div>
                            <?php if ($isFull): ?>
                                <span class="book-btn disabled">เต็มแล้ว</span>
                            <?php else: ?>
                                <a href="booking_tent_form.php?tent_id=<?= $tid ?>" class="book-btn">จองเต็นท์นี้</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-box">
            <h3>⛺ ยังไม่มีรายการเต็นท์</h3>
            <p>กรุณาติดต่อเจ้าหน้าที่หรือรอเพิ่มข้อมูลเต็นท์</p>
        </div>
    <?php endif; ?>
</div>

<?php $conn->close(); ?>
</body>
</html>
