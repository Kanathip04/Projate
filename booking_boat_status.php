<?php
session_start();
require_once 'auth_guard.php';
include 'config.php';
date_default_timezone_set('Asia/Bangkok');

$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
if ($user_email === '') {
    die('ไม่พบ session email ของผู้ใช้');
}

$stmt = $conn->prepare(
    "SELECT * FROM boat_bookings WHERE email = ? ORDER BY id DESC"
);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

function boatStatusText($s) {
    return ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิกแล้ว'][$s] ?? 'ไม่ทราบสถานะ';
}
function boatStatusClass($s) {
    return ['pending'=>'pending','approved'=>'approved','rejected'=>'rejected','cancelled'=>'cancelled'][$s] ?? 'unknown';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ติดตามสถานะการจองคิวพายเรือ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--ink:#1a1a2e;--blue:#1d6fad;--blue-light:#e8f4ff;--bg:#f0f7ff;--card:#fff;--muted:#7a7a8c;--border:#dce8f5;}
body{background:var(--bg);color:var(--ink);font-family:'Sarabun','Segoe UI',Tahoma,sans-serif;font-size:16px;line-height:1.6;}
.container{width:min(1180px,92%);margin:0 auto;}
.hero{background:linear-gradient(135deg,#0a1628 0%,#0d2344 50%,#1a3a5c 100%);color:#fff;padding:44px 20px 96px;position:relative;overflow:hidden;}
.hero::after{content:'';position:absolute;right:-120px;top:-120px;width:480px;height:480px;border-radius:50%;background:rgba(29,111,173,0.09);pointer-events:none;}
.top-menu{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px;}
.top-menu a{display:inline-flex;align-items:center;padding:10px 20px;border-radius:999px;text-decoration:none;color:#fff;font-weight:700;font-size:15px;border:1px solid rgba(29,111,173,.45);background:rgba(29,111,173,.15);transition:background .25s,transform .2s;}
.top-menu a:hover{background:rgba(29,111,173,.3);transform:translateY(-2px);}
.hero h1{font-size:44px;font-weight:800;margin-bottom:12px;}
.hero h1 span{color:#7ec8f4;}
.hero p{font-size:17px;max-width:760px;line-height:1.75;color:rgba(255,255,255,.82);}
.content{margin-top:-42px;padding-bottom:60px;}
.list{display:grid;gap:24px;}
.card{background:var(--card);border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(29,111,173,.10);border:1px solid var(--border);transition:box-shadow .25s,transform .2s;}
.card:hover{box-shadow:0 16px 44px rgba(29,111,173,.14);transform:translateY(-2px);}
.card::before{content:'';display:block;height:4px;background:linear-gradient(90deg,#1d6fad,#7ec8f4);}
.card-body{padding:26px 28px 28px;}
.card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:22px;}
.queue-name{font-size:26px;font-weight:800;color:var(--ink);}
.badge{padding:7px 16px;border-radius:999px;font-size:14px;font-weight:700;white-space:nowrap;}
.pending{background:#fef3c7;color:#92400e;}
.approved{background:#dcfce7;color:#166534;}
.rejected{background:#fee2e2;color:#991b1b;}
.cancelled{background:#f3f4f6;color:#374151;}
.unknown{background:#e5e7eb;color:var(--ink);}
.grid{display:grid;grid-template-columns:repeat(2,minmax(200px,1fr));gap:14px;margin-bottom:18px;}
.item{background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:14px 16px;}
.item .label{display:block;font-size:12px;color:var(--muted);margin-bottom:5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.item .value{font-size:16px;color:var(--ink);font-weight:700;}
.boats-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
.boat-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 13px;border-radius:999px;background:rgba(29,111,173,.1);border:1px solid rgba(29,111,173,.3);color:var(--blue);font-size:13px;font-weight:700;}
.note-box{background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:16px 18px;}
.note-box .label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px;font-weight:700;text-transform:uppercase;}
.note-box .value{line-height:1.75;color:var(--ink);font-size:15px;}
.empty{background:var(--card);border-radius:20px;padding:60px 24px;text-align:center;box-shadow:0 8px 32px rgba(29,111,173,.10);border:1px solid var(--border);}
.empty h3{font-size:24px;font-weight:800;margin-bottom:10px;}
.empty p{color:var(--muted);margin-bottom:24px;}
.empty a{display:inline-block;padding:12px 28px;border-radius:999px;text-decoration:none;background:var(--ink);color:#fff;font-weight:700;font-size:15px;}
.empty a:hover{background:#2a2a4a;}
@media(max-width:900px){.grid{grid-template-columns:1fr;}.hero h1{font-size:32px;}}
</style>
</head>
<body>

<section class="hero">
    <div class="container">
        <div class="top-menu">
            <a href="booking_boat.php">🚣 กลับไปหน้าจองคิวพายเรือ</a>
            <a href="index.php">← หน้าหลัก</a>
        </div>
        <h1>ติดตามสถานะ<span>การจองเรือ</span></h1>
        <p>แสดงรายการจองคิวพายเรือทั้งหมดของคุณ พร้อมสถานะล่าสุด</p>
    </div>
</section>

<div class="content">
<div class="container">
    <?php if ($result && $result->num_rows > 0): ?>
        <div class="list">
        <?php while ($row = $result->fetch_assoc()):
            $units = json_decode($row['boat_units'] ?? '[]', true) ?: [];
        ?>
            <div class="card">
                <div class="card-body">
                    <div class="card-top">
                        <div class="queue-name">🚣 <?= htmlspecialchars($row['queue_name'] ?: 'คิวพายเรือ') ?></div>
                        <span class="badge <?= boatStatusClass($row['booking_status']) ?>">
                            <?= boatStatusText($row['booking_status']) ?>
                        </span>
                    </div>
                    <div class="grid">
                        <div class="item">
                            <span class="label">หมายเลขการจอง</span>
                            <span class="value">#<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div class="item">
                            <span class="label">วันที่จอง</span>
                            <span class="value"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></span>
                        </div>
                        <div class="item">
                            <span class="label">วันที่พายเรือ</span>
                            <span class="value"><?= $row['boat_date'] ? date('d/m/Y', strtotime($row['boat_date'])) : '-' ?></span>
                        </div>
                        <div class="item">
                            <span class="label">เวลา</span>
                            <span class="value">
                                <?= $row['time_start'] ? substr($row['time_start'],0,5).' – '.substr($row['time_end'],0,5).' น.' : '-' ?>
                            </span>
                        </div>
                        <div class="item">
                            <span class="label">จำนวนผู้เข้าร่วม</span>
                            <span class="value"><?= (int)$row['guests'] ?> คน</span>
                        </div>
                        <div class="item">
                            <span class="label">เรือที่จอง</span>
                            <?php if (!empty($units)): ?>
                                <div class="boats-wrap">
                                    <?php foreach ($units as $u): ?>
                                        <span class="boat-pill">🚣 ลำที่ <?= (int)$u ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="value">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($row['note'])): ?>
                    <div class="note-box">
                        <span class="label">หมายเหตุ</span>
                        <div class="value"><?= htmlspecialchars($row['note']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <h3>🚣 ยังไม่มีการจองคิวพายเรือ</h3>
            <p>คุณยังไม่ได้จองคิวพายเรือ กดปุ่มด้านล่างเพื่อเริ่มจอง</p>
            <a href="booking_boat.php">🚣 จองคิวพายเรือ</a>
        </div>
    <?php endif; ?>
</div>
</div>

</body>
</html>
<?php $stmt->close(); ?>
