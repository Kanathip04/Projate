<?php
/**
 * queue_display.php — หน้าแสดงคิววันนี้สำหรับเจ้าหน้าที่
 */
date_default_timezone_set('Asia/Bangkok');
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");

$today = date('Y-m-d');

$result = $conn->query("
    SELECT daily_queue_no, booking_ref, full_name, boat_type, num_people,
           boat_date, approved_at, payment_status, booking_status
    FROM boat_bookings
    WHERE DATE(approved_at) = '$today'
      AND booking_status = 'approved'
      AND payment_status = 'paid'
    ORDER BY daily_queue_no ASC
");

$queues = [];
$dbError = '';
if ($result === false) {
    $dbError = $conn->error;
} else {
    while ($r = $result->fetch_assoc()) $queues[] = $r;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="30">
<title>คิววันนี้ — เจ้าหน้าที่</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#1a1a2e;--blue:#1d6fad;--blue-light:#e8f2fb;
  --gold:#c9a96e;--bg:#f0f7ff;--card:#fff;
  --border:#dce8f5;--muted:#7a7a8c;--green:#15803d;--green-bg:#ecfdf3;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;padding:24px;}
h1{font-family:'Kanit',sans-serif;font-size:1.6rem;color:var(--ink);margin-bottom:4px;}
.sub{color:var(--muted);font-size:.9rem;margin-bottom:24px;}
.refresh-note{font-size:.75rem;color:var(--muted);float:right;margin-top:6px;}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}

.card{background:var(--card);border:1.5px solid var(--border);border-radius:16px;
  padding:20px;display:flex;flex-direction:column;gap:10px;
  box-shadow:0 2px 8px rgba(0,0,0,.06);}

.queue-no{font-family:'Kanit',sans-serif;font-size:2.6rem;font-weight:900;
  color:var(--blue);line-height:1;}
.name{font-size:1.05rem;font-weight:700;color:var(--ink);}
.detail{font-size:.85rem;color:var(--muted);display:flex;flex-direction:column;gap:4px;}
.detail span{display:flex;align-items:center;gap:6px;}
.badge{display:inline-block;padding:3px 10px;border-radius:99px;font-size:.75rem;font-weight:700;
  background:var(--green-bg);color:var(--green);}

.empty{text-align:center;padding:60px 20px;color:var(--muted);font-size:1rem;}
.empty .big{font-size:3rem;margin-bottom:12px;}

header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;margin-bottom:24px;}
.back-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
  background:var(--blue);color:#fff;border-radius:99px;text-decoration:none;
  font-size:.85rem;font-weight:600;}
.count-box{background:var(--blue-light);border-radius:12px;padding:10px 18px;
  font-size:.9rem;color:var(--blue);font-weight:700;text-align:center;margin-bottom:16px;}
</style>
</head>
<body>

<header>
  <div>
    <h1>🚣 คิววันนี้</h1>
    <div class="sub"><?= date('d/m/Y', strtotime($today)) ?> <span class="refresh-note">🔄 refresh อัตโนมัติทุก 30 วิ</span></div>
  </div>
  <a href="index.php" class="back-btn">← กลับหน้าหลัก</a>
</header>

<?php if ($dbError): ?>
  <div class="empty">
    <div class="big">⚠️</div>
    DB Error: <?= htmlspecialchars($dbError) ?>
  </div>
<?php elseif (empty($queues)): ?>
  <div class="empty">
    <div class="big">📋</div>
    ยังไม่มีคิววันนี้
  </div>
<?php else: ?>
  <div class="count-box">ทั้งหมด <?= count($queues) ?> คิว</div>
  <div class="grid">
    <?php foreach ($queues as $q): ?>
    <div class="card">
      <div class="queue-no">Q<?= str_pad($q['daily_queue_no'], 4, '0', STR_PAD_LEFT) ?></div>
      <div class="name"><?= htmlspecialchars($q['full_name']) ?></div>
      <div class="detail">
        <span>🚤 <?= htmlspecialchars($q['boat_type'] ?? '-') ?></span>
        <span>👥 <?= (int)$q['num_people'] ?> คน</span>
        <span>📅 <?= date('d/m/Y', strtotime($q['boat_date'])) ?></span>
        <span>✅ ชำระ <?= date('H:i', strtotime($q['approved_at'])) ?> น.</span>
      </div>
      <div><span class="badge">ชำระแล้ว</span></div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</body>
</html>
