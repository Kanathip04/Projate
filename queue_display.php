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
    SELECT daily_queue_no, booking_ref, full_name, boat_type, boat_units,
           boat_date, time_start, time_end, approved_at, payment_status, booking_status
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

$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y') + 543;
$thDate = "$d {$thMonths[$m]} $y";
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="30">
<title>คิววันนี้</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --blue:#1565c0;--blue2:#1976d2;--blue-lt:#e3f2fd;--blue-md:#bbdefb;
  --ink:#0d1b2a;--muted:#5f7281;--bg:#eef4fb;--card:#fff;
  --green:#1b7c3b;--green-bg:#e6f9ed;--green-bdr:#a7e8bb;
  --gold:#b8860b;--radius:18px;
}
html{font-size:16px;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;}

/* ── TOPBAR ── */
.topbar{
  background:linear-gradient(135deg,var(--blue) 0%,var(--blue2) 100%);
  color:#fff;padding:14px 20px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:99;
  box-shadow:0 2px 12px rgba(21,101,192,.35);
}
.topbar-left{display:flex;flex-direction:column;gap:1px;}
.topbar-title{font-family:'Kanit',sans-serif;font-size:1.25rem;font-weight:800;line-height:1.1;}
.topbar-sub{font-size:.75rem;opacity:.85;}
.topbar-right{display:flex;align-items:center;gap:10px;}
.back-btn{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.4);
  color:#fff;border-radius:99px;padding:6px 14px;text-decoration:none;
  font-size:.8rem;font-weight:600;backdrop-filter:blur(4px);
}
.refresh-chip{
  background:rgba(255,255,255,.15);border-radius:99px;
  padding:4px 10px;font-size:.7rem;opacity:.9;display:flex;align-items:center;gap:4px;
}

/* ── SUMMARY BAR ── */
.summary{
  display:flex;align-items:center;justify-content:space-between;
  background:#fff;border-bottom:1.5px solid var(--blue-md);
  padding:10px 20px;
}
.summary-count{
  font-family:'Kanit',sans-serif;font-size:1rem;color:var(--blue);font-weight:800;
}
.summary-time{font-size:.75rem;color:var(--muted);}

/* ── GRID ── */
.grid-wrap{padding:16px;}
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
  gap:14px;
}

/* ── CARD ── */
.card{
  background:var(--card);border-radius:var(--radius);
  box-shadow:0 2px 12px rgba(21,101,192,.09);
  border:1.5px solid #daeaf8;
  overflow:hidden;
  transition:box-shadow .2s;
}
.card:hover{box-shadow:0 6px 24px rgba(21,101,192,.18);}

.card-top{
  background:linear-gradient(135deg,var(--blue) 0%,#2196f3 100%);
  padding:16px 18px 14px;
  display:flex;align-items:flex-end;justify-content:space-between;
}
.queue-no{
  font-family:'Kanit',sans-serif;font-size:2.8rem;font-weight:900;
  color:#fff;line-height:1;text-shadow:0 2px 8px rgba(0,0,0,.2);
}
.card-top-right{text-align:right;}
.badge-paid{
  background:rgba(255,255,255,.22);border:1.5px solid rgba(255,255,255,.5);
  color:#fff;border-radius:99px;padding:3px 10px;font-size:.7rem;font-weight:700;
}
.paid-time{color:rgba(255,255,255,.8);font-size:.72rem;margin-top:4px;}

.card-body{padding:14px 18px 16px;}
.name{
  font-size:1.1rem;font-weight:700;color:var(--ink);
  margin-bottom:10px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.info-list{display:flex;flex-direction:column;gap:6px;}
.info-row{
  display:flex;align-items:center;gap:8px;
  font-size:.83rem;color:var(--muted);
}
.info-icon{width:20px;text-align:center;flex-shrink:0;}
.info-text{flex:1;}

/* ── EMPTY ── */
.empty{
  text-align:center;padding:80px 20px;color:var(--muted);
}
.empty-icon{font-size:4rem;margin-bottom:16px;opacity:.5;}
.empty-text{font-size:1rem;font-weight:600;}
.empty-sub{font-size:.82rem;margin-top:6px;opacity:.7;}

/* ── DB ERROR ── */
.error-box{
  margin:20px;background:#fff3cd;border:1.5px solid #ffc107;
  border-radius:12px;padding:14px 18px;color:#856404;font-size:.85rem;
}

/* ── MOBILE ── */
@media(max-width:480px){
  .topbar{padding:12px 14px;}
  .topbar-title{font-size:1.1rem;}
  .grid{grid-template-columns:1fr;}
  .queue-no{font-size:2.4rem;}
  .grid-wrap{padding:12px;}
  .back-btn span{display:none;}
}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-left">
    <div class="topbar-title">🚣 คิววันนี้</div>
    <div class="topbar-sub"><?= $thDate ?></div>
  </div>
  <div class="topbar-right">
    <div class="refresh-chip">🔄 <span>auto 30วิ</span></div>
    <a href="index.php" class="back-btn">← <span>กลับ</span></a>
  </div>
</div>

<?php if ($dbError): ?>
  <div class="error-box">⚠️ DB Error: <?= htmlspecialchars($dbError) ?></div>

<?php elseif (empty($queues)): ?>
  <div class="empty">
    <div class="empty-icon">📋</div>
    <div class="empty-text">ยังไม่มีคิววันนี้</div>
    <div class="empty-sub">คิวจะแสดงเมื่อมีการชำระเงินสำเร็จ</div>
  </div>

<?php else: ?>
  <div class="summary">
    <div class="summary-count">ทั้งหมด <?= count($queues) ?> คิว</div>
    <div class="summary-time">อัปเดต <?= date('H:i') ?> น.</div>
  </div>
  <div class="grid-wrap">
    <div class="grid">
      <?php foreach ($queues as $q):
        $units = json_decode($q['boat_units'] ?? '[]', true) ?: [];
        $boatLabel = !empty($units)
          ? implode(' · ', array_map(fn($u) => "เรือ $u คน", $units))
          : ($q['boat_type'] ?? '-');
        $timeLabel = !empty($q['time_start'])
          ? substr($q['time_start'],0,5).'–'.substr($q['time_end'],0,5).' น.'
          : null;
        $paidTime = !empty($q['approved_at']) ? date('H:i', strtotime($q['approved_at'])) : '-';
      ?>
      <div class="card">
        <div class="card-top">
          <div class="queue-no">Q<?= str_pad($q['daily_queue_no'], 4, '0', STR_PAD_LEFT) ?></div>
          <div class="card-top-right">
            <div class="badge-paid">✅ ชำระแล้ว</div>
            <div class="paid-time"><?= $paidTime ?> น.</div>
          </div>
        </div>
        <div class="card-body">
          <div class="name"><?= htmlspecialchars($q['full_name']) ?></div>
          <div class="info-list">
            <div class="info-row">
              <span class="info-icon">🚤</span>
              <span class="info-text"><?= htmlspecialchars($boatLabel) ?></span>
            </div>
            <div class="info-row">
              <span class="info-icon">📅</span>
              <span class="info-text"><?= date('d/m/Y', strtotime($q['boat_date'])) ?></span>
            </div>
            <?php if ($timeLabel): ?>
            <div class="info-row">
              <span class="info-icon">🕐</span>
              <span class="info-text"><?= $timeLabel ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

</body>
</html>
