<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$thMonths=['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function thDate($dateStr){
    global $thMonths;
    $d=(int)date('d',strtotime($dateStr));
    $m=(int)date('m',strtotime($dateStr));
    $y=(int)date('Y',strtotime($dateStr))+543;
    return "$d {$thMonths[$m]} $y";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_archive') {
    $delDate = trim($_POST['archive_date'] ?? '');
    if ($delDate) {
        // คืน booking กลับ (archived=0) แล้วลบ archive record
        $conn->query("UPDATE boat_bookings SET archived=0 WHERE DATE(approved_at)='$delDate' AND booking_status='approved'");
        $st = $conn->prepare("DELETE FROM boat_queue_daily_archive WHERE archive_date=?");
        $st->bind_param("s", $delDate); $st->execute(); $st->close();
    }
    header("Location: admin_boat_archive_view.php"); exit;
}

$allArchives = $conn->query("SELECT * FROM boat_queue_daily_archive ORDER BY archive_date DESC");

// Stats
$stRow = $conn->query("SELECT COUNT(*) t, SUM(total_queues) tq, SUM(total_revenue) tr FROM boat_queue_daily_archive")->fetch_assoc();
$stat_days    = (int)($stRow['t']  ?? 0);
$stat_queues  = (int)($stRow['tq'] ?? 0);
$stat_revenue = (float)($stRow['tr'] ?? 0);

$pageTitle="จัดเก็บข้อมูลคิว"; $activeMenu="boat_archive";
include 'admin_layout_top.php';
?>
<style>
:root{
  --navy:#0f2d1f;--blue:#1565c0;--ink:#1a1a2e;--muted:#7a7a8c;
  --border:#e8e3dc;--green:#16a34a;--green-light:#f0fdf4;--green-mid:#86efac;
  --gold:#c9a96e;--gold-light:#fdf3e3;
  --card:#fff;--shadow:0 4px 20px rgba(26,26,46,.08);
}
*{box-sizing:border-box;}
.av-wrap{padding-bottom:56px;animation:avUp .4s ease both;}
@keyframes avUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

/* Banner */
.av-banner{
  border-radius:20px;padding:30px 36px;margin-bottom:24px;
  display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;
  background:linear-gradient(135deg,#0d1b2a 0%,#1a3a5c 55%,#1565c0 100%);
  position:relative;overflow:hidden;
}
.av-banner::before{
  content:'';position:absolute;width:340px;height:340px;border-radius:50%;
  background:radial-gradient(circle,rgba(21,101,192,.3) 0%,transparent 70%);
  top:-120px;right:-60px;pointer-events:none;
}
.av-banner-body{position:relative;z-index:1;}
.av-banner-eyebrow{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.55);font-weight:700;margin-bottom:8px;}
.av-banner h1{font-size:1.55rem;font-weight:800;color:#fff;margin:0 0 5px;}
.av-banner p{font-size:.82rem;color:rgba(255,255,255,.6);margin:0;}
.av-back{
  display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
  border-radius:10px;font-size:.78rem;font-weight:700;text-decoration:none;
  border:1.5px solid rgba(255,255,255,.22);background:rgba(255,255,255,.1);
  color:#fff;transition:all .2s;position:relative;z-index:1;
}
.av-back:hover{background:rgba(255,255,255,.2);}

/* Stats */
.av-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.av-stat{
  background:var(--card);border-radius:16px;padding:22px 20px 18px;
  box-shadow:0 2px 10px rgba(26,26,46,.06);border:1px solid var(--border);
  display:flex;align-items:flex-start;gap:14px;position:relative;overflow:hidden;
}
.av-stat::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0;}
.av-stat:nth-child(1)::after{background:linear-gradient(90deg,var(--gold),#e8c97a);}
.av-stat:nth-child(2)::after{background:linear-gradient(90deg,#3b82f6,#60a5fa);}
.av-stat:nth-child(3)::after{background:linear-gradient(90deg,#22c55e,#4ade80);}
.av-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.av-stat:nth-child(1) .av-stat-icon{background:var(--gold-light);}
.av-stat:nth-child(2) .av-stat-icon{background:#eff6ff;}
.av-stat:nth-child(3) .av-stat-icon{background:var(--green-light);}
.av-stat-body{flex:1;}
.av-stat-label{font-size:.67rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:5px;font-weight:700;}
.av-stat-value{font-size:2rem;font-weight:800;line-height:1;color:var(--ink);}
.av-stat:nth-child(2) .av-stat-value{color:#1d4ed8;}
.av-stat:nth-child(3) .av-stat-value{color:var(--green);}

/* Archive cards */
.av-date-card{
  background:var(--card);border-radius:18px;
  box-shadow:var(--shadow);border:1px solid var(--border);
  margin-bottom:20px;overflow:hidden;
}
.av-date-header{
  padding:18px 24px;border-bottom:1px solid var(--border);
  background:linear-gradient(180deg,#faf9f6 0%,#fff 100%);
  display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
  cursor:pointer;
}
.av-date-header:hover{background:#f5f1eb;}
.av-date-left{display:flex;align-items:center;gap:14px;}
.av-date-badge{
  background:linear-gradient(135deg,#0d1b2a,#1565c0);
  color:#fff;border-radius:12px;padding:10px 16px;text-align:center;min-width:64px;
}
.av-date-badge-day{font-size:1.4rem;font-weight:900;line-height:1;}
.av-date-badge-month{font-size:.65rem;font-weight:700;opacity:.8;margin-top:2px;}
.av-date-title{font-size:.95rem;font-weight:800;color:var(--ink);}
.av-date-sub{font-size:.74rem;color:var(--muted);margin-top:3px;}
.av-date-pills{display:flex;gap:8px;flex-wrap:wrap;}
.av-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:999px;font-size:.74rem;font-weight:700;}
.av-pill-q{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.av-pill-rev{background:var(--green-light);color:var(--green);border:1px solid var(--green-mid);}
.av-date-actions{display:flex;align-items:center;gap:8px;}
.av-toggle{font-size:.75rem;color:var(--muted);font-weight:600;padding:5px 10px;border-radius:7px;background:#f0ede8;border:none;cursor:pointer;transition:all .15s;}
.av-toggle:hover{background:#e8e3dc;color:var(--ink);}

/* Booking detail table */
.av-detail{border-top:1px solid var(--border);}
.av-detail table{width:100%;border-collapse:collapse;}
.av-detail th{
  padding:10px 16px;font-size:.69rem;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:.07em;border-bottom:1.5px solid var(--border);
  background:#faf9f6;text-align:left;
}
.av-detail td{
  padding:13px 16px;border-bottom:1px solid #f0ede8;
  font-size:.83rem;color:var(--ink);vertical-align:middle;
}
.av-detail tr:last-child td{border-bottom:none;}
.av-detail tr:hover td{background:#fdfaf6;}
.av-detail tr:nth-child(even) td{background:#faf8f5;}
.av-detail tr:nth-child(even):hover td{background:#f5f1eb;}

.q-badge{
  display:inline-flex;align-items:center;gap:4px;padding:5px 11px;
  border-radius:9px;background:linear-gradient(135deg,#0d1b2a,#1a3a5c);
  color:#fff;font-weight:800;font-size:.8rem;
}
.q-badge-q{color:rgba(201,169,110,.9);font-size:.68rem;}
.bk-name{font-weight:700;font-size:.86rem;}
.bk-sub{font-size:.73rem;color:var(--muted);margin-top:2px;}
.ref-pill{
  display:inline-block;padding:2px 9px;border-radius:6px;
  background:#f1f5f9;border:1px solid #dde3ec;
  font-size:.7rem;font-weight:700;font-family:monospace;color:#334155;
}
.pay-method{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;font-size:.72rem;font-weight:700;}
.pay-cash{background:#fff7ed;color:#c2410c;border:1px solid #fdba74;}
.pay-slip{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.amount-cell{font-weight:800;color:var(--green);}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
.empty-icon{font-size:2.5rem;margin-bottom:10px;}
.empty-text{font-size:.95rem;font-weight:600;}
.empty-sub{font-size:.78rem;margin-top:4px;opacity:.7;}
.num-badge{
  display:inline-flex;align-items:center;justify-content:center;
  width:26px;height:26px;border-radius:8px;
  background:#f0ede8;color:var(--muted);font-size:.74rem;font-weight:800;
}
.btn-del{
  display:inline-flex;align-items:center;gap:5px;padding:5px 11px;
  border-radius:8px;font-size:.74rem;font-weight:700;cursor:pointer;
  background:#fef2f2;color:#dc2626;border:1.5px solid #fca5a5;
  font-family:'Sarabun',sans-serif;transition:all .2s;
}
.btn-del:hover{background:#fee2e2;}

@media(max-width:768px){.av-stats{grid-template-columns:1fr 1fr;}.av-banner{padding:22px 18px;}}
@media(max-width:500px){.av-stats{grid-template-columns:1fr;}}
</style>

<div class="av-wrap">

  <!-- Banner -->
  <div class="av-banner">
    <div class="av-banner-body">
      <div class="av-banner-eyebrow">ประวัติข้อมูล · คิวพายเรือ</div>
      <h1>📦 จัดเก็บข้อมูลคิว</h1>
      <p>ประวัติข้อมูลการจองคิวพายเรือที่จัดเก็บแล้วทั้งหมด</p>
    </div>
    <a href="admin_boat_approved.php" class="av-back">← กลับ</a>
  </div>

  <!-- Stats -->
  <div class="av-stats">
    <div class="av-stat">
      <div class="av-stat-icon">📅</div>
      <div class="av-stat-body">
        <div class="av-stat-label">วันที่จัดเก็บ</div>
        <div class="av-stat-value"><?= $stat_days ?></div>
      </div>
    </div>
    <div class="av-stat">
      <div class="av-stat-icon">🎫</div>
      <div class="av-stat-body">
        <div class="av-stat-label">จำนวนคิวรวม</div>
        <div class="av-stat-value"><?= $stat_queues ?></div>
      </div>
    </div>
    <div class="av-stat">
      <div class="av-stat-icon">💰</div>
      <div class="av-stat-body">
        <div class="av-stat-label">รายได้รวม</div>
        <div class="av-stat-value">฿<?= number_format($stat_revenue, 0) ?></div>
      </div>
    </div>
  </div>

  <!-- Archive list -->
  <?php if (!$allArchives || $allArchives->num_rows === 0): ?>
  <div class="av-date-card">
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <div class="empty-text">ยังไม่มีข้อมูลที่จัดเก็บ</div>
      <div class="empty-sub">กดปุ่ม "จัดเก็บข้อมูล" จากหน้าอนุมัติการจองเพื่อเริ่มจัดเก็บ</div>
    </div>
  </div>
  <?php else:
    $arNo = 1;
    while ($ar = $allArchives->fetch_assoc()):
      $bookings = json_decode($ar['bookings_json'] ?? '[]', true) ?: [];
      $d = (int)date('d', strtotime($ar['archive_date']));
      $m = (int)date('m', strtotime($ar['archive_date']));
      $y = (int)date('Y', strtotime($ar['archive_date'])) + 543;
      $detailId = 'detail-' . $arNo;
  ?>
  <div class="av-date-card">
    <div class="av-date-header" onclick="toggleDetail('<?= $detailId ?>', this)">
      <div class="av-date-left">
        <div class="av-date-badge">
          <div class="av-date-badge-day"><?= $d ?></div>
          <div class="av-date-badge-month"><?= $thMonths[$m] ?> <?= $y ?></div>
        </div>
        <div>
          <div class="av-date-title"><?= $d ?> <?= $thMonths[$m] ?> <?= $y ?></div>
          <div class="av-date-sub">จัดเก็บเมื่อ <?= date('d/m/Y H:i', strtotime($ar['archived_at'])) ?></div>
        </div>
      </div>
      <div class="av-date-pills">
        <span class="av-pill av-pill-q">🎫 <?= (int)$ar['total_queues'] ?> คิว</span>
        <span class="av-pill av-pill-rev">฿<?= number_format((float)$ar['total_revenue'], 0) ?></span>
      </div>
      <div class="av-date-actions">
        <button class="av-toggle" id="toggle-<?= $detailId ?>">▼ ดูรายละเอียด</button>
        <form method="POST" onsubmit="return confirm('ยืนยันลบข้อมูลวันที่ <?= $d ?> <?= $thMonths[$m] ?> <?= $y ?> ?')">
          <input type="hidden" name="action" value="delete_archive">
          <input type="hidden" name="archive_date" value="<?= h($ar['archive_date']) ?>">
          <button type="submit" class="btn-del">🗑 ลบ</button>
        </form>
      </div>
    </div>

    <div class="av-detail" id="<?= $detailId ?>" style="display:none;">
      <?php if (empty($bookings)): ?>
        <div style="padding:30px;text-align:center;color:var(--muted);font-size:.85rem;">ไม่มีข้อมูลรายละเอียด</div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>คิว</th>
            <th>ผู้จอง</th>
            <th>หมายเลขจอง</th>
            <th>เรือ / วันที่</th>
            <th>จำนวน</th>
            <th>ยอด</th>
            <th>ประเภทชำระ</th>
            <th>เวลาอนุมัติ</th>
          </tr>
        </thead>
        <tbody>
        <?php $bNo = 1; foreach ($bookings as $bk): ?>
          <tr>
            <td><span class="num-badge"><?= $bNo++ ?></span></td>
            <td>
              <?php if (!empty($bk['daily_queue_no'])): ?>
              <span class="q-badge">
                <span class="q-badge-q">Q</span><?= str_pad((int)$bk['daily_queue_no'], 4, '0', STR_PAD_LEFT) ?>
              </span>
              <?php else: ?>
              <span style="color:var(--muted);font-size:.75rem;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="bk-name"><?= h($bk['full_name'] ?? '-') ?></div>
              <div class="bk-sub"><?= h($bk['phone'] ?? '') ?></div>
              <?php if (!empty($bk['email'])): ?>
              <div class="bk-sub"><?= h($bk['email']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="ref-pill"><?= h($bk['booking_ref'] ?? '-') ?></span>
              <?php if (!empty($bk['queue_name'])): ?>
              <div class="bk-sub" style="margin-top:4px;"><?= h($bk['queue_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:700;"><?= h($bk['boat_type'] ?? '-') ?></div>
              <div class="bk-sub">📅 <?= h($bk['boat_date'] ?? '-') ?></div>
            </td>
            <td style="font-weight:600;"><?= (int)($bk['guests'] ?? 0) ?> คน</td>
            <td><span class="amount-cell">฿<?= number_format((float)($bk['total_amount'] ?? 0), 0) ?></span></td>
            <td>
              <?php $pp = $bk['payment_provider'] ?? ''; $ps = $bk['payment_status'] ?? ''; ?>
              <?php if ($pp === 'cash' || $ps === 'cash_paid'): ?>
                <span class="pay-method pay-cash">💵 เงินสด</span>
              <?php else: ?>
                <span class="pay-method pay-slip">📄 สลิป</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.74rem;color:var(--muted);">
              <?= !empty($bk['approved_at']) ? date('d/m H:i', strtotime($bk['approved_at'])) : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php $arNo++; endwhile; endif; ?>

</div>

<script>
function toggleDetail(id, header) {
  const el = document.getElementById(id);
  const btn = document.getElementById('toggle-' + id);
  if (el.style.display === 'none') {
    el.style.display = 'block';
    btn.textContent = '▲ ซ่อน';
  } else {
    el.style.display = 'none';
    btn.textContent = '▼ ดูรายละเอียด';
  }
}
</script>

<?php include 'admin_layout_bottom.php'; ?>
<?php $conn->close(); ?>
