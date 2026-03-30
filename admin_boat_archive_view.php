<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$date = trim($_GET['date'] ?? date('Y-m-d', strtotime('-1 day')));

$stmt = $conn->prepare("SELECT * FROM boat_queue_daily_archive WHERE archive_date=? LIMIT 1");
$stmt->bind_param("s",$date); $stmt->execute();
$arch = $stmt->get_result()->fetch_assoc(); $stmt->close();

$thMonths=['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$d=(int)date('d',strtotime($date));$m=(int)date('m',strtotime($date));$y=(int)date('Y',strtotime($date))+543;
$thDate="$d {$thMonths[$m]} $y";

$bookings = $arch ? json_decode($arch['bookings_json'], true) : [];

$pageTitle="Archive: $thDate"; $activeMenu="boat_queue";
include 'admin_layout_top.php';
?>
<style>
:root{--navy:#0d1b2a;--blue:#1565c0;--ink:#0d1b2a;--muted:#5f7281;--border:#e0eaf5;--green:#15803d;--green-bg:#ecfdf3;}
.arch-wrap{padding-bottom:48px;}
.arch-banner{border-radius:14px;padding:22px 28px;margin-bottom:20px;
  background:linear-gradient(135deg,var(--navy) 0%,#1a3a5c 60%,#1565c0 100%);
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.arch-banner h1{font-family:'Kanit',sans-serif;font-size:1.35rem;font-weight:800;color:#fff;margin:0 0 4px;}
.arch-banner p{font-size:.8rem;color:rgba(255,255,255,.7);margin:0;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;}
.stat-box{background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 2px 8px rgba(13,27,42,.07);border:1px solid var(--border);}
.stat-val{font-family:'Kanit',sans-serif;font-size:1.8rem;font-weight:900;color:var(--blue);}
.stat-label{font-size:.75rem;color:var(--muted);margin-top:2px;}
.list-card{background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(13,27,42,.07);overflow:hidden;}
.list-header{padding:14px 20px;border-bottom:1px solid var(--border);}
.list-title{font-family:'Kanit',sans-serif;font-size:.95rem;font-weight:800;color:var(--ink);}
table.at{width:100%;border-collapse:collapse;}
table.at th{padding:9px 14px;font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;border-bottom:1.5px solid var(--border);background:#f8fbff;text-align:left;}
table.at td{padding:10px 14px;border-bottom:1px solid #f0f5fc;font-size:.84rem;color:var(--ink);}
table.at tr:last-child td{border-bottom:none;}
.q-no{font-family:'Kanit',sans-serif;font-weight:900;color:var(--blue);}
.badge-paid{background:var(--green-bg);color:var(--green);border-radius:99px;padding:2px 9px;font-size:.7rem;font-weight:700;}
.btn{display:inline-flex;align-items:center;gap:5px;border-radius:99px;padding:7px 16px;font-size:.82rem;font-weight:700;text-decoration:none;border:none;cursor:pointer;font-family:'Sarabun',sans-serif;}
.btn-ghost{background:transparent;border:1.5px solid rgba(255,255,255,.3);color:#fff;}
.btn-ghost:hover{border-color:#fff;}
.empty-state{text-align:center;padding:48px;color:var(--muted);}
@media(max-width:600px){table.at th:nth-child(5),table.at td:nth-child(5){display:none;}}
</style>
<div class="main">
<div class="arch-wrap">
  <div class="arch-banner">
    <div>
      <h1>🗄️ Archive: <?= $thDate ?></h1>
      <p>ข้อมูลคิวพายเรือที่จัดเก็บ</p>
    </div>
    <a href="admin_boat_queues.php" class="btn btn-ghost">← กลับ</a>
  </div>

  <?php if ($arch): ?>
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-val"><?= (int)$arch['total_queues'] ?></div>
      <div class="stat-label">คิวทั้งหมด</div>
    </div>
    <div class="stat-box">
      <div class="stat-val" style="color:var(--green);">฿<?= number_format((float)$arch['total_revenue']) ?></div>
      <div class="stat-label">รายได้รวม</div>
    </div>
    <div class="stat-box">
      <div class="stat-val" style="font-size:1rem;color:var(--muted);margin-top:6px;"><?= date('d/m/Y H:i', strtotime($arch['archived_at'])) ?></div>
      <div class="stat-label">เวลาจัดเก็บ</div>
    </div>
  </div>
  <div class="list-card">
    <div class="list-header"><div class="list-title">รายการคิว</div></div>
    <?php if (empty($bookings)): ?>
      <div class="empty-state">ไม่มีรายการในวันนี้</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="at">
      <thead>
        <tr><th>คิว</th><th>ชื่อ</th><th>เรือ</th><th>วันที่จอง</th><th>ชำระ</th><th>ยอด</th></tr>
      </thead>
      <tbody>
      <?php foreach ($bookings as $b):
        $units = json_decode($b['boat_units'] ?? '[]', true) ?: [];
        $boatLabel = !empty($units) ? implode(', ', array_map(fn($u)=>"เรือ {$u} คน",$units)) : ($b['boat_type'] ?? '-');
      ?>
        <tr>
          <td><span class="q-no">Q<?= str_pad($b['daily_queue_no'],4,'0',STR_PAD_LEFT) ?></span></td>
          <td><?= h($b['full_name'] ?? '') ?></td>
          <td><?= h($boatLabel) ?></td>
          <td><?= !empty($b['boat_date']) ? date('d/m/Y', strtotime($b['boat_date'])) : '-' ?></td>
          <td><?= !empty($b['approved_at']) ? date('H:i', strtotime($b['approved_at'])) : '-' ?> น.</td>
          <td>
            <?php if (!empty($b['total_amount'])): ?>
              <span style="font-weight:700;color:var(--green);">฿<?= number_format((float)$b['total_amount']) ?></span>
            <?php else: ?>-<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
    <div class="empty-state" style="background:#fff;border-radius:14px;padding:60px;">ไม่พบข้อมูล archive สำหรับวันที่ <?= $thDate ?></div>
  <?php endif; ?>
</div>
</div>
<?php include 'admin_layout_bottom.php'; ?>
<?php $conn->close(); ?>
