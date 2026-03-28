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
    "SELECT * FROM tent_bookings WHERE email = ? ORDER BY id DESC"
);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

function tentStatusText($s) {
    return ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิกแล้ว'][$s] ?? 'ไม่ทราบสถานะ';
}
function tentStatusClass($s) {
    return ['pending'=>'pending','approved'=>'approved','rejected'=>'rejected','cancelled'=>'cancelled'][$s] ?? 'unknown';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ติดตามสถานะการจองเต็นท์</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--ink:#1a1a2e;--gold:#c9a96e;--gold-light:#e8d5b0;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;}
body{background:var(--bg);color:var(--ink);font-family:'Sarabun','Segoe UI',Tahoma,sans-serif;font-size:16px;line-height:1.6;}
.container{width:min(1180px,92%);margin:0 auto;}
.hero{background:linear-gradient(135deg,#0f0f1e 0%,#1a1a2e 55%,#252545 100%);color:#fff;padding:44px 20px 96px;position:relative;overflow:hidden;}
.hero::after{content:'';position:absolute;right:-120px;top:-120px;width:480px;height:480px;border-radius:50%;background:rgba(201,169,110,0.07);pointer-events:none;}
.top-menu{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px;}
.top-menu a{display:inline-flex;align-items:center;padding:10px 20px;border-radius:999px;text-decoration:none;color:#fff;font-weight:700;font-size:15px;border:1px solid rgba(201,169,110,.45);background:rgba(201,169,110,.12);transition:background .25s,transform .2s;}
.top-menu a:hover{background:rgba(201,169,110,.25);transform:translateY(-2px);}
.hero h1{font-size:44px;font-weight:800;margin-bottom:12px;}
.hero h1 span{color:var(--gold);}
.hero p{font-size:17px;max-width:760px;line-height:1.75;color:rgba(255,255,255,.82);}
.content{margin-top:-42px;padding-bottom:60px;}
.list{display:grid;gap:24px;}
.card{background:var(--card);border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(26,26,46,.10);border:1px solid var(--border);transition:box-shadow .25s,transform .2s;}
.card:hover{box-shadow:0 16px 44px rgba(26,26,46,.14);transform:translateY(-2px);}
.card::before{content:'';display:block;height:4px;background:linear-gradient(90deg,var(--gold),var(--gold-light));}
.card-body{padding:26px 28px 28px;}
.card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:22px;}
.tent-name{font-size:26px;font-weight:800;color:var(--ink);}
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
.note-box{background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:16px 18px;}
.note-box .label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px;font-weight:700;text-transform:uppercase;}
.note-box .value{line-height:1.75;color:var(--ink);font-size:15px;}
.empty{background:var(--card);border-radius:20px;padding:60px 24px;text-align:center;box-shadow:0 8px 32px rgba(26,26,46,.10);border:1px solid var(--border);}
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
            <a href="booking_tent.php">⛺ กลับไปหน้าจองเต็นท์</a>
            <a href="index.php">หน้าหลัก</a>
        </div>
        <h1>สถานะ<span>การจองเต็นท์</span></h1>
        <p>ตรวจสอบรายการจองเต็นท์ของคุณ พร้อมดูสถานะการอนุมัติและรายละเอียดต่าง ๆ</p>
    </div>
</section>

<section class="content">
    <div class="container">
        <div class="list">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $st = $row['booking_status'] ?? 'pending'; ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="card-top">
                                <div class="tent-name">⛺ <?= htmlspecialchars($row['tent_type'] ?? 'เต็นท์') ?></div>
                                <div class="badge <?= tentStatusClass($st) ?>"><?= tentStatusText($st) ?></div>
                            </div>
                            <div class="grid">
                                <div class="item">
                                    <span class="label">เลขที่การจอง</span>
                                    <span class="value">#<?= (int)$row['id'] ?></span>
                                </div>
                                <div class="item">
                                    <span class="label">ชื่อผู้จอง</span>
                                    <span class="value"><?= htmlspecialchars($row['full_name']) ?></span>
                                </div>
                                <div class="item">
                                    <span class="label">เบอร์โทร</span>
                                    <span class="value"><?= htmlspecialchars($row['phone']) ?></span>
                                </div>
                                <div class="item">
                                    <span class="label">จำนวนผู้เข้าพัก</span>
                                    <span class="value"><?= (int)$row['guests'] ?> คน</span>
                                </div>
                                <div class="item">
                                    <span class="label">วันเข้าพัก</span>
                                    <span class="value"><?= !empty($row['checkin_date']) ? date('d/m/Y', strtotime($row['checkin_date'])) : '-' ?></span>
                                </div>
                                <div class="item">
                                    <span class="label">วันออก</span>
                                    <span class="value"><?= !empty($row['checkout_date']) ? date('d/m/Y', strtotime($row['checkout_date'])) : '-' ?></span>
                                </div>
                                <div class="item">
                                    <span class="label">วันที่ทำรายการ</span>
                                    <span class="value"><?= !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-' ?></span>
                                </div>
                                <div class="item">
                                    <span class="label">สถานะ</span>
                                    <span class="value"><?= tentStatusText($st) ?></span>
                                </div>
                            </div>
                            <?php if (!empty($row['note'])): ?>
                                <div class="note-box">
                                    <span class="label">หมายเหตุ</span>
                                    <div class="value"><?= nl2br(htmlspecialchars($row['note'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">
                    <h3>⛺ ยังไม่มีรายการจอง</h3>
                    <p>เมื่อคุณจองเต็นท์แล้ว รายการจะแสดงในหน้านี้</p>
                    <a href="booking_tent.php">ไปหน้าจองเต็นท์</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>
