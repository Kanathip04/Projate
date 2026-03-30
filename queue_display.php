<?php
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
if ($result === false) { $dbError = $conn->error; }
else { while ($r = $result->fetch_assoc()) $queues[] = $r; }
$conn->close();

$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y') + 543;
$thDate = "$d {$thMonths[$m]} $y";
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta http-equiv="refresh" content="30">
<title>คิววันนี้</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
:root{
  --blue:#1565c0;--blue2:#1e88e5;
  --ink:#0d1b2a;--muted:#6b7c93;
  --bg:#f0f5fc;--card:#fff;
  --green:#1b7c3b;--green-bg:#e6f9ed;
}
html,body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;}

/* ── HEADER ── */
.header{
  background:linear-gradient(160deg,#0d47a1 0%,#1976d2 60%,#42a5f5 100%);
  padding:20px 16px 28px;
  position:relative;overflow:hidden;
}
.header::before{
  content:'';position:absolute;top:-40px;right:-40px;
  width:180px;height:180px;border-radius:50%;
  background:rgba(255,255,255,.07);
}
.header::after{
  content:'';position:absolute;bottom:-30px;left:30%;
  width:120px;height:120px;border-radius:50%;
  background:rgba(255,255,255,.05);
}
.header-inner{position:relative;z-index:1;}
.header-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.back-btn{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.3);
  color:#fff;border-radius:99px;padding:7px 14px;text-decoration:none;
  font-size:.82rem;font-weight:600;
}
.auto-chip{
  background:rgba(255,255,255,.12);border-radius:99px;
  padding:5px 11px;font-size:.72rem;color:rgba(255,255,255,.9);
  display:flex;align-items:center;gap:5px;border:1px solid rgba(255,255,255,.2);
}
.header-title{font-family:'Kanit',sans-serif;font-size:1.7rem;font-weight:900;color:#fff;line-height:1.1;}
.header-date{font-size:.85rem;color:rgba(255,255,255,.8);margin-top:4px;}

/* ── FLOATING COUNT ── */
.count-float{
  background:#fff;border-radius:16px;
  margin:0 16px;
  margin-top:-18px;
  box-shadow:0 4px 20px rgba(21,101,192,.18);
  padding:13px 18px;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;z-index:2;
}
.count-num{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:900;color:var(--blue);}
.count-label{font-size:.78rem;color:var(--muted);margin-top:1px;}
.count-right{text-align:right;}
.update-time{font-size:.72rem;color:var(--muted);}
.update-label{font-size:.82rem;font-weight:600;color:var(--ink);}

/* ── LIST ── */
.list{padding:12px 16px 24px;}

/* ── CARD ── */
.card{
  background:var(--card);
  border-radius:16px;
  box-shadow:0 2px 10px rgba(13,27,42,.07);
  margin-bottom:12px;
  overflow:hidden;
  border:1px solid #e4edf8;
  display:flex;
}
.card-left{
  background:linear-gradient(180deg,#1565c0 0%,#1976d2 100%);
  width:80px;flex-shrink:0;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:14px 8px;
}
.q-label{font-size:.6rem;color:rgba(255,255,255,.7);font-weight:700;letter-spacing:.05em;margin-bottom:2px;}
.q-num{font-family:'Kanit',sans-serif;font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.q-ref{font-size:.6rem;color:rgba(255,255,255,.55);margin-top:5px;text-align:center;word-break:break-all;}

.card-right{flex:1;padding:13px 14px;min-width:0;}
.c-name{font-size:.95rem;font-weight:700;color:var(--ink);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;}
.c-rows{display:flex;flex-direction:column;gap:5px;}
.c-row{display:flex;align-items:center;gap:7px;font-size:.8rem;color:var(--muted);}
.c-icon{width:18px;text-align:center;flex-shrink:0;font-size:.85rem;}
.c-val{flex:1;line-height:1.3;}
.c-badge{
  display:inline-flex;align-items:center;gap:4px;
  background:var(--green-bg);color:var(--green);
  border-radius:99px;padding:3px 9px;font-size:.7rem;font-weight:700;
  margin-top:6px;
}

/* ── EMPTY ── */
.empty{text-align:center;padding:60px 20px;}
.empty-icon{font-size:3.5rem;margin-bottom:14px;opacity:.4;}
.empty-title{font-size:1rem;font-weight:700;color:var(--ink);}
.empty-sub{font-size:.82rem;color:var(--muted);margin-top:5px;}

/* ── ERROR ── */
.err{margin:16px;background:#fff3cd;border:1.5px solid #ffc107;border-radius:12px;padding:12px 16px;font-size:.83rem;color:#856404;}

/* ── DESKTOP: 2 column ── */
@media(min-width:640px){
  .header{padding:28px 24px 36px;}
  .count-float{margin:0 24px;margin-top:-22px;}
  .list{padding:16px 24px 32px;display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .card{margin-bottom:0;}
}
@media(min-width:1024px){
  .list{grid-template-columns:repeat(3,1fr);}
}
</style>
</head>
<body>

<div class="header">
  <div class="header-inner">
    <div class="header-top">
      <a href="index.php" class="back-btn">← กลับ</a>
      <div class="auto-chip">🔄 รีเฟรชทุก 30 วิ</div>
    </div>
    <div class="header-title">🚣 คิววันนี้</div>
    <div class="header-date"><?= $thDate ?></div>
  </div>
</div>

<?php if ($dbError): ?>
  <div class="err">⚠️ <?= htmlspecialchars($dbError) ?></div>

<?php elseif (empty($queues)): ?>
  <div class="count-float">
    <div><div class="count-num">0</div><div class="count-label">คิว</div></div>
    <div class="count-right">
      <div class="update-label">ยังไม่มีคิว</div>
      <div class="update-time">อัปเดต <?= date('H:i') ?> น.</div>
    </div>
  </div>
  <div class="empty">
    <div class="empty-icon">📋</div>
    <div class="empty-title">ยังไม่มีคิววันนี้</div>
    <div class="empty-sub">คิวจะแสดงเมื่อลูกค้าชำระเงินสำเร็จ</div>
  </div>

<?php else: ?>
  <div class="count-float">
    <div><div class="count-num"><?= count($queues) ?></div><div class="count-label">คิวทั้งหมด</div></div>
    <div class="count-right">
      <div class="update-label">วันนี้</div>
      <div class="update-time">อัปเดต <?= date('H:i') ?> น.</div>
    </div>
  </div>
  <div class="list">
    <?php foreach ($queues as $q):
      $units = json_decode($q['boat_units'] ?? '[]', true) ?: [];
      $boatLabel = !empty($units)
        ? implode(' · ', array_map(fn($u) => "เรือ {$u} คน", $units))
        : ($q['boat_type'] ?? '-');
      $timeLabel = !empty($q['time_start'])
        ? substr($q['time_start'],0,5).'–'.substr($q['time_end'],0,5).' น.'
        : null;
      $paidTime = !empty($q['approved_at']) ? date('H:i', strtotime($q['approved_at'])) : '-';
      $ref = $q['booking_ref'] ?? '';
    ?>
    <div class="card">
      <div class="card-left">
        <div class="q-label">QUEUE</div>
        <div class="q-num"><?= str_pad($q['daily_queue_no'], 4, '0', STR_PAD_LEFT) ?></div>
        <div class="q-ref"><?= htmlspecialchars(substr($ref, -6)) ?></div>
      </div>
      <div class="card-right">
        <div class="c-name"><?= htmlspecialchars($q['full_name']) ?></div>
        <div class="c-rows">
          <div class="c-row"><span class="c-icon">🚤</span><span class="c-val"><?= htmlspecialchars($boatLabel) ?></span></div>
          <div class="c-row"><span class="c-icon">📅</span><span class="c-val"><?= date('d/m/Y', strtotime($q['boat_date'])) ?></span></div>
          <?php if ($timeLabel): ?>
          <div class="c-row"><span class="c-icon">🕐</span><span class="c-val"><?= $timeLabel ?></span></div>
          <?php endif; ?>
          <div class="c-row"><span class="c-icon">💳</span><span class="c-val">ชำระ <?= $paidTime ?> น.</span></div>
        </div>
        <div class="c-badge">✅ ชำระแล้ว</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</body>
</html>
