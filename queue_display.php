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
      $qData = json_encode([
        'no'       => str_pad($q['daily_queue_no'], 4, '0', STR_PAD_LEFT),
        'name'     => $q['full_name'],
        'boat'     => $boatLabel,
        'date'     => date('d/m/Y', strtotime($q['boat_date'])),
        'time'     => $timeLabel ?? '-',
        'paid'     => $paidTime,
        'ref'      => $ref,
        'status'   => $q['booking_status'],
      ]);
    ?>
    <div class="card" onclick='openQueue(<?= htmlspecialchars($qData, ENT_QUOTES) ?>)'>
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

<!-- ── QUEUE DETAIL POPUP ── -->
<style>
.card{cursor:pointer;}
.card:active{transform:scale(.98);}

#qdOverlay{
  display:none;position:fixed;inset:0;z-index:999;
  background:rgba(0,0,0,.5);backdrop-filter:blur(6px);
  align-items:flex-end;justify-content:center;padding:0;
}
#qdOverlay.open{display:flex;}

#qdSheet{
  width:min(520px,100%);background:#fff;
  border-radius:24px 24px 0 0;
  box-shadow:0 -8px 40px rgba(0,0,0,.18);
  animation:sheetUp .32s cubic-bezier(.25,.8,.25,1) both;
  overflow:hidden;
}
@keyframes sheetUp{from{transform:translateY(100%)}to{transform:translateY(0)}}

.qd-head{
  background:linear-gradient(135deg,#0d47a1 0%,#1976d2 60%,#42a5f5 100%);
  padding:20px 22px 22px;color:#fff;position:relative;
  display:flex;align-items:center;gap:16px;
}
.qd-no-wrap{
  background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.3);
  border-radius:14px;padding:10px 16px;text-align:center;flex-shrink:0;min-width:80px;
}
.qd-no-label{font-size:.6rem;font-weight:700;letter-spacing:.1em;opacity:.7;margin-bottom:2px;}
.qd-no{font-family:'Kanit',sans-serif;font-size:2rem;font-weight:900;line-height:1;}
.qd-name{font-family:'Kanit',sans-serif;font-size:1.15rem;font-weight:800;line-height:1.3;}
.qd-ref{font-size:.72rem;opacity:.65;margin-top:3px;}
.qd-close{
  position:absolute;top:14px;right:14px;
  width:34px;height:34px;border-radius:50%;
  background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.3);
  color:#fff;font-size:1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:.2s;
}
.qd-close:hover{background:rgba(255,255,255,.28);}

.qd-body{padding:20px 22px 28px;}
.qd-row{
  display:flex;align-items:center;gap:14px;
  padding:13px 16px;border-radius:12px;
  background:#f8fbff;border:1px solid #e4edf8;
  margin-bottom:10px;
}
.qd-icon{font-size:1.2rem;width:28px;text-align:center;flex-shrink:0;}
.qd-lbl{font-size:.68rem;font-weight:700;color:#5f7281;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;}
.qd-val{font-size:.9rem;font-weight:700;color:#0d1b2a;}

.qd-badge{
  display:flex;align-items:center;justify-content:center;gap:8px;
  background:#ecfdf3;color:#15803d;border:1px solid #a7f3d0;
  border-radius:10px;padding:11px;margin-top:4px;
  font-size:.85rem;font-weight:700;
}

@media(min-width:521px){
  #qdOverlay{align-items:center;}
  #qdSheet{border-radius:20px;margin:20px;}
}
</style>

<div id="qdOverlay" onclick="if(event.target===this)closeQueue()">
  <div id="qdSheet">
    <div class="qd-head">
      <div class="qd-no-wrap">
        <div class="qd-no-label">QUEUE</div>
        <div class="qd-no" id="qdNo">0001</div>
      </div>
      <div>
        <div class="qd-name" id="qdName">—</div>
        <div class="qd-ref" id="qdRef">—</div>
      </div>
      <button class="qd-close" onclick="closeQueue()">✕</button>
    </div>
    <div class="qd-body">
      <div class="qd-row">
        <span class="qd-icon">🚤</span>
        <div><div class="qd-lbl">ประเภทเรือ</div><div class="qd-val" id="qdBoat">—</div></div>
      </div>
      <div class="qd-row">
        <span class="qd-icon">📅</span>
        <div><div class="qd-lbl">วันที่จอง</div><div class="qd-val" id="qdDate">—</div></div>
      </div>
      <div class="qd-row" id="qdTimeRow">
        <span class="qd-icon">🕐</span>
        <div><div class="qd-lbl">เวลา</div><div class="qd-val" id="qdTime">—</div></div>
      </div>
      <div class="qd-row">
        <span class="qd-icon">💳</span>
        <div><div class="qd-lbl">เวลาชำระเงิน</div><div class="qd-val" id="qdPaid">—</div></div>
      </div>
      <div class="qd-badge">✅ ชำระเงินแล้ว · พร้อมใช้บริการ</div>
    </div>
  </div>
</div>

<script>
function openQueue(q) {
  document.getElementById('qdNo').textContent   = q.no;
  document.getElementById('qdName').textContent = q.name;
  document.getElementById('qdRef').textContent  = 'หมายเลขอ้างอิง: ' + q.ref;
  document.getElementById('qdBoat').textContent = q.boat;
  document.getElementById('qdDate').textContent = q.date;
  document.getElementById('qdTime').textContent = q.time;
  document.getElementById('qdPaid').textContent = 'ชำระ ' + q.paid + ' น.';
  document.getElementById('qdTimeRow').style.display = q.time === '-' ? 'none' : 'flex';
  document.getElementById('qdOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeQueue() {
  document.getElementById('qdOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeQueue(); });
</script>
</body>
</html>
