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

/* === ดึงอุปกรณ์ให้เช่า === */
$conn->query("CREATE TABLE IF NOT EXISTS `tent_equipment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `price` DECIMAL(10,2) DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT 'ชิ้น',
    `note` TEXT,
    `sort_order` INT DEFAULT 0,
    `is_available` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$equipResult = $conn->query("SELECT * FROM tent_equipment WHERE is_available=1 ORDER BY sort_order, id");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เช่าอุปกรณ์เต็นท์ | สถาบันวิจัยวลัยรุกขเวช</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
    --bg:#f5f1eb;--card:#ffffff;--muted:#7a7a8c;
    --border:#e8e4de;
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
.hero-inner{width:min(860px,92%);margin:0 auto;position:relative;z-index:2;}
.top-nav{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px;}
.nav-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:999px;font-size:14px;font-weight:600;color:#fff;background:rgba(201,169,110,0.18);border:1px solid rgba(201,169,110,0.45);backdrop-filter:blur(8px);transition:.25s ease;}
.nav-btn:hover{background:rgba(201,169,110,0.32);color:var(--gold);}
.hero h1{font-size:46px;line-height:1.2;margin-bottom:14px;color:#fff;}
.hero h1 span{color:var(--gold);}
.hero p{font-size:17px;line-height:1.8;color:rgba(255,255,255,.85);}
.section{width:min(860px,92%);margin:-40px auto 60px;position:relative;z-index:5;}
.equip-section{background:var(--card);border-radius:20px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;}
.equip-head{padding:24px 28px;border-bottom:1px solid var(--border);}
.equip-head h3{font-size:22px;font-weight:800;color:var(--ink);margin:0 0 4px;}
.equip-head p{font-size:14px;color:var(--muted);margin:0;}
.equip-table{width:100%;border-collapse:collapse;}
.equip-table thead th{padding:12px 22px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);text-align:left;font-weight:700;background:#fdfcfa;}
.equip-table tbody td{padding:16px 22px;font-size:15px;color:var(--ink);border-bottom:1px solid var(--border);}
.equip-table tbody tr:last-child td{border-bottom:none;}
.equip-table tbody tr:hover{background:#fafaf8;}
.equip-name{font-weight:700;}
.equip-price{font-weight:800;color:var(--gold-dark);font-size:16px;}
.equip-unit{color:var(--muted);font-size:14px;}
.equip-note{font-size:13px;color:var(--muted);}
.equip-num{color:#c4c4cc;font-size:13px;}
.equip-empty{padding:48px;text-align:center;color:var(--muted);font-size:15px;}
@media(max-width:600px){
    .hero{padding:50px 16px 100px;}
    .hero h1{font-size:32px;}
    .section{margin-top:-30px;}
    .equip-table thead th,.equip-table tbody td{padding:12px 14px;}
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
            <a href="booking_boat.php" class="nav-btn">จองคิวพายเรือ</a>
        </div>
        <h1>เช่า<span>อุปกรณ์เต็นท์</span></h1>
        <p>รายการอุปกรณ์ที่สามารถเช่าได้ กรุณาแจ้งเจ้าหน้าที่เมื่อต้องการเช่าอุปกรณ์เพิ่มเติม</p>
    </div>
</section>

<div class="section">
    <div class="equip-section">
        <div class="equip-head">
            <h3>🏕️ อุปกรณ์ให้เช่า</h3>
            <p>ราคาต่อคืน / ต่อชิ้น ตามหน่วยที่ระบุ</p>
        </div>
        <table class="equip-table">
            <thead>
                <tr>
                    <th style="width:44px;">#</th>
                    <th>รายการ</th>
                    <th>ราคา</th>
                    <th>หน่วย</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($equipResult && $equipResult->num_rows > 0): ?>
                    <?php $i = 1; while ($eq = $equipResult->fetch_assoc()): ?>
                    <tr>
                        <td class="equip-num"><?= $i++ ?></td>
                        <td class="equip-name"><?= htmlspecialchars($eq['name']) ?></td>
                        <td class="equip-price">฿<?= number_format((float)$eq['price']) ?></td>
                        <td class="equip-unit"><?= htmlspecialchars($eq['unit']) ?></td>
                        <td class="equip-note"><?= htmlspecialchars($eq['note'] ?: '—') ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="equip-empty">ยังไม่มีรายการอุปกรณ์</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $conn->close(); ?>
</body>
</html>
