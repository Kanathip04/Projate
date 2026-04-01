<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$currentPage = basename($_SERVER['PHP_SELF']);
$message = ''; $message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'restore' && $id > 0) {
        $st = $conn->prepare("UPDATE boat_bookings SET archived=0 WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "คืนรายการกลับสู่อนุมัติแล้วเรียบร้อย";
    }
    if ($action === 'delete' && $id > 0) {
        $st = $conn->prepare("DELETE FROM boat_bookings WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "ลบรายการเรียบร้อยแล้ว";
    }
    header("Location: {$currentPage}?msg=".urlencode($message)."&type=".urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }
$search    = trim($_GET['search']    ?? '');
$filterDate = trim($_GET['filter_date'] ?? '');

$where = "WHERE archived=1";
$params = []; $types = "";
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR booking_ref LIKE ?)";
    $like = "%{$search}%"; $params[] = $like; $params[] = $like; $params[] = $like; $types .= "sss";
}
if ($filterDate !== '') {
    $where .= " AND DATE(COALESCE(approved_at, created_at)) = ?";
    $params[] = $filterDate; $types .= "s";
}
$stmt = $conn->prepare("SELECT * FROM boat_bookings {$where} ORDER BY approved_at DESC, id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();
$total = $result->num_rows;

// Stats (ทั้งหมด ไม่ filter)
$sr = $conn->query("SELECT COUNT(*) t, SUM(total_amount) rev FROM boat_bookings WHERE archived=1")->fetch_assoc();
$stat_total   = (int)($sr['t']   ?? 0);
$stat_revenue = (float)($sr['rev'] ?? 0);

$pageTitle = "จัดเก็บข้อมูลคิว"; $activeMenu = "boat_archive";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;}

.arc-banner{border-radius:18px;padding:22px 28px;margin-bottom:24px;
  background:linear-gradient(135deg,#0d1b2a 0%,#1a3a5c 55%,#1565c0 100%);
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
  position:relative;overflow:hidden;}
.arc-banner::before{content:'';position:absolute;width:260px;height:260px;border-radius:50%;
  background:rgba(255,255,255,.06);top:-70px;right:-50px;pointer-events:none;}
.arc-banner h1{font-size:1.25rem;font-weight:800;color:#fff;margin:0 0 3px;}
.arc-banner p{font-size:.78rem;color:rgba(255,255,255,.7);margin:0;}
.arc-banner-links{display:flex;gap:9px;flex-wrap:wrap;position:relative;z-index:1;}
.arc-link{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:8px;
  font-size:.75rem;font-weight:700;text-decoration:none;color:#fff;
  border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);transition:.2s;}
.arc-link:hover{background:rgba(255,255,255,.2);}

.arc-alert{display:flex;align-items:center;gap:10px;padding:12px 18px;
  border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:20px;}
.arc-alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.arc-alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

.arc-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px;}
.arc-stat{background:var(--card);border-radius:14px;padding:20px 18px;
  box-shadow:0 2px 10px rgba(26,26,46,.06);border:1px solid var(--border);
  display:flex;align-items:center;gap:14px;position:relative;overflow:hidden;}
.arc-stat::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;}
.arc-stat:nth-child(1)::after{background:linear-gradient(90deg,var(--gold),#e8c97a);}
.arc-stat:nth-child(2)::after{background:linear-gradient(90deg,#3b82f6,#60a5fa);}
.arc-stat:nth-child(3)::after{background:linear-gradient(90deg,#22c55e,#4ade80);}
.arc-stat-ico{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;}
.arc-stat:nth-child(1) .arc-stat-ico{background:var(--gold-dim);}
.arc-stat:nth-child(2) .arc-stat-ico{background:#eff6ff;}
.arc-stat:nth-child(3) .arc-stat-ico{background:#f0fdf4;}
.arc-stat-body{}
.arc-stat-label{font-size:.67rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:4px;font-weight:700;}
.arc-stat-value{font-size:1.8rem;font-weight:800;line-height:1;color:var(--ink);}
.arc-stat:nth-child(2) .arc-stat-value{color:#1d4ed8;}
.arc-stat:nth-child(3) .arc-stat-value{color:var(--success);}

.arc-card{background:var(--card);border-radius:18px;box-shadow:0 2px 16px rgba(26,26,46,.07);overflow:hidden;}
.arc-card-head{padding:16px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.arc-title{font-size:.9rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px;}
.arc-title::before{content:'';display:inline-block;width:3px;height:14px;background:#1565c0;border-radius:2px;}
.arc-cnt{background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}

.arc-search{padding:14px 22px;border-bottom:1px solid var(--border);
  display:flex;gap:9px;flex-wrap:wrap;align-items:center;background:#fdfcfa;}
.arc-sw{position:relative;flex:1;min-width:180px;}
.arc-date-input{padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.84rem;color:var(--ink);background:#fff;outline:none;
  cursor:pointer;}
.arc-date-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.arc-btn-date{background:var(--ink);color:#fff;border:none;border-radius:8px;padding:8px 16px;
  font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;
  display:inline-flex;align-items:center;gap:5px;transition:.18s;white-space:nowrap;}
.arc-btn-date:hover{background:#2a2a4a;transform:translateY(-1px);}
.arc-filter-active{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;
  padding:5px 12px;font-size:.76rem;font-weight:700;color:#1d4ed8;display:inline-flex;align-items:center;gap:6px;}
.arc-sw::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:.72rem;pointer-events:none;}
.arc-sw input{width:100%;padding:8px 12px 8px 34px;border:1.5px solid var(--border);
  border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.84rem;color:var(--ink);background:#fff;outline:none;}
.arc-sw input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.arc-btn{display:inline-flex;align-items:center;gap:5px;padding:8px 15px;border:none;
  border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:700;
  cursor:pointer;text-decoration:none;transition:.18s;white-space:nowrap;}
.arc-btn:hover{transform:translateY(-1px);}
.arc-btn-primary{background:var(--ink);color:#fff;}
.arc-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.arc-btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.arc-btn-restore{background:#eff6ff;color:#1d4ed8;border:1.5px solid #bfdbfe;}
.arc-btn-restore:hover{background:#dbeafe;}
.arc-btn-del{background:#fef2f2;color:var(--danger);border:1.5px solid #fecaca;}
.arc-btn-del:hover{background:#fee2e2;}
.arc-btn-slip{background:#f0fdf4;color:#15803d;border:1.5px solid #86efac;}
.arc-btn-slip:hover{background:#dcfce7;}
.slip-lb{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;padding:20px;}
.slip-lb.open{display:flex;}
.slip-lb img{max-width:90vw;max-height:88vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,.5);}
.slip-lb-close{position:fixed;top:18px;right:22px;font-size:2rem;color:#fff;cursor:pointer;background:none;border:none;line-height:1;}

.arc-table-wrap{overflow-x:auto;}
.arc-table{width:100%;border-collapse:collapse;min-width:1100px;}
.arc-table thead th{padding:10px 14px;font-size:.65rem;letter-spacing:.1em;
  text-transform:uppercase;color:var(--muted);border-bottom:2px solid var(--border);
  text-align:left;font-weight:700;background:#fdfcfa;}
.arc-table tbody td{padding:13px 14px;font-size:.83rem;color:var(--ink);
  border-bottom:1px solid var(--border);vertical-align:middle;}
.arc-table tbody tr:last-child td{border-bottom:none;}
.arc-table tbody tr:hover{background:#fafaf8;}
.arc-name{font-weight:700;}
.arc-meta{font-size:.72rem;color:var(--muted);margin-top:2px;}
.arc-actions{display:flex;gap:6px;flex-wrap:wrap;}
.mif{display:inline;}
.arc-empty{padding:48px 24px;text-align:center;color:var(--muted);}
.arc-empty-ico{font-size:2.5rem;opacity:.25;margin-bottom:10px;}
.badge-archived{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;
  border-radius:20px;font-size:.69rem;font-weight:700;background:#eff6ff;color:#1d4ed8;}
.bill-ref{font-size:.78rem;font-weight:700;color:#15803d;white-space:nowrap;}
.q-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;
  background:linear-gradient(135deg,#0d1b2a,#1a3a5c);color:#fff;font-weight:800;font-size:.79rem;}
.q-badge-q{color:rgba(201,169,110,.9);font-size:.67rem;}
.ref-pill{display:inline-block;padding:2px 8px;border-radius:6px;
  background:#f1f5f9;border:1px solid #dde3ec;font-size:.7rem;font-weight:700;font-family:monospace;color:#334155;}
.pay-method{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:99px;font-size:.69rem;font-weight:700;}
.pay-cash{background:#fff7ed;color:#c2410c;border:1px solid #fdba74;}
.pay-slip{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.amount-cell{font-weight:800;color:var(--success);}
.date-block{font-size:.77rem;}
.date-block-sub{font-size:.72rem;color:var(--muted);margin-top:2px;}
</style>

<!-- Slip Lightbox -->
<div class="slip-lb" id="slipLb">
  <button class="slip-lb-close" onclick="closeSLB()">✕</button>
  <img id="slipLbImg" src="" alt="สลิป">
</div>

<?php if ($message !== ''): ?>
<div class="arc-alert <?= $message_type==='error'?'arc-alert-err':'arc-alert-ok' ?>">
  <?= $message_type==='error'?'⚠':'✓' ?> <?= h($message) ?>
</div>
<?php endif; ?>

<div class="arc-banner">
  <div>
    <h1>📦 จัดเก็บข้อมูลคิว</h1>
    <p>ประวัติการจองคิวพายเรือที่จัดเก็บแล้วทั้งหมด</p>
  </div>
  <div class="arc-banner-links">
    <a href="admin_boat_approved.php" class="arc-link">← อนุมัติการจอง</a>
    <a href="admin_boat_bookings.php" class="arc-link">🚣 รายการจอง</a>
  </div>
</div>

<!-- Stats -->
<div class="arc-stats">
  <div class="arc-stat">
    <div class="arc-stat-ico">🎫</div>
    <div class="arc-stat-body">
      <div class="arc-stat-label">รายการทั้งหมด</div>
      <div class="arc-stat-value"><?= $stat_total ?></div>
    </div>
  </div>
  <div class="arc-stat">
    <div class="arc-stat-ico">📦</div>
    <div class="arc-stat-body">
      <div class="arc-stat-label">รายการค้นหา</div>
      <div class="arc-stat-value"><?= $total ?></div>
    </div>
  </div>
  <div class="arc-stat">
    <div class="arc-stat-ico">💰</div>
    <div class="arc-stat-body">
      <div class="arc-stat-label">รายได้รวม</div>
      <div class="arc-stat-value">฿<?= number_format($stat_revenue, 0) ?></div>
    </div>
  </div>
</div>

<div class="arc-card">
  <div class="arc-card-head">
    <div class="arc-title">รายการที่จัดเก็บแล้ว</div>
    <span class="arc-cnt"><?= $total ?> รายการ</span>
  </div>

  <form method="GET">
    <div class="arc-search">
      <div class="arc-sw">
        <input type="text" name="search" placeholder="ค้นหาชื่อ, เบอร์โทร, หมายเลขจอง..."
               value="<?= h($search) ?>">
      </div>
      <input type="date" name="filter_date" class="arc-date-input"
             value="<?= h($filterDate) ?>" title="กรองตามวันที่อนุมัติ">
      <button type="submit" class="arc-btn-date">ค้นหา</button>
      <?php if ($search || $filterDate): ?>
        <a href="<?= h($currentPage) ?>" class="arc-btn arc-btn-ghost">ล้าง</a>
      <?php endif; ?>
    </div>
    <?php if ($filterDate): ?>
    <div style="padding:8px 22px;background:#fdfcfa;border-bottom:1px solid var(--border);">
      <span class="arc-filter-active">
        📅 แสดงวันที่: <?= h(date('d/m/Y', strtotime($filterDate))) ?>
        &nbsp;·&nbsp; <?= $total ?> รายการ
        <a href="<?= h($currentPage).($search?'?search='.urlencode($search):'') ?>" style="color:#1d4ed8;text-decoration:none;font-size:.9rem;">✕</a>
      </span>
    </div>
    <?php endif; ?>
  </form>

  <div class="arc-table-wrap">
    <table class="arc-table">
      <thead>
        <tr>
          <th style="width:44px;">#</th>
          <th>เลขที่บิล</th>
          <th>ผู้จอง</th>
          <th>หมายเลขจอง</th>
          <th>เรือ / วันที่จอง</th>
          <th>จำนวน</th>
          <th>ยอด</th>
          <th>บัตรคิว</th>
          <th>ประเภทชำระ</th>
          <th>วันที่จอง</th>
          <th>วันที่อนุมัติ</th>
          <th>สถานะ</th>
          <th style="width:150px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): $no = 1; ?>
          <?php while ($row = $result->fetch_assoc()):
            // เลขที่บิล
            $_bkDate = date('Ymd', strtotime($row['created_at']));
            $_seqR = $conn->query("SELECT COUNT(*) AS seq FROM boat_bookings WHERE DATE(created_at)=DATE('".$conn->real_escape_string($row['created_at'])."') AND id<=".(int)$row['id']);
            $_seq  = (int)($_seqR ? $_seqR->fetch_assoc()['seq'] : 1);
            $_billRef = 'BOAT-'.$_bkDate.'-'.str_pad($_seq,3,'0',STR_PAD_LEFT);
            $pp = $row['payment_provider'] ?? '';
            $ps = $row['payment_status']   ?? '';
          ?>
          <tr>
            <td style="color:var(--muted);font-size:.76rem;"><?= $no++ ?></td>
            <td><span class="bill-ref"><?= h($_billRef) ?></span></td>
            <td>
              <div class="arc-name"><?= h($row['full_name']) ?></div>
              <div class="arc-meta"><?= h($row['phone']??'—') ?></div>
              <?php if (!empty($row['email'])): ?>
                <div class="arc-meta"><?= h($row['email']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="ref-pill"><?= h($row['booking_ref']??'—') ?></span></td>
            <td>
              <div style="font-weight:700;"><?= h($row['boat_type']??'—') ?></div>
              <div class="arc-meta">📅 <?= h($row['boat_date']??'—') ?></div>
              <?php if (!empty($row['queue_name'])): ?>
                <div class="arc-meta"><?= h($row['queue_name']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-weight:600;"><?= (int)($row['guests']??1) ?> คน</td>
            <td><span class="amount-cell">฿<?= number_format((float)($row['total_amount']??0),0) ?></span></td>
            <td>
              <?php if (!empty($row['daily_queue_no'])): ?>
                <span class="q-badge">
                  <span class="q-badge-q">Q</span><?= str_pad((int)$row['daily_queue_no'],4,'0',STR_PAD_LEFT) ?>
                </span>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.73rem;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($pp==='cash' || $ps==='cash_paid'): ?>
                <span class="pay-method pay-cash">💵 เงินสด</span>
              <?php else: ?>
                <span class="pay-method pay-slip">📄 โอนเงิน</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.75rem;color:var(--muted);"><?= h(substr($row['created_at'],0,16)) ?></td>
            <td style="font-size:.75rem;color:var(--muted);">
              <?= !empty($row['approved_at']) ? h(substr($row['approved_at'],0,16)) : '—' ?>
            </td>
            <td><span class="badge-archived">📦 จัดเก็บแล้ว</span></td>
            <td>
              <div class="arc-actions">
                <?php if (!empty($row['payment_slip'])): ?>
                  <button onclick="openSLB('<?= h($row['payment_slip']) ?>')" class="arc-btn arc-btn-slip" style="padding:5px 11px;font-size:.74rem;">🖼 ดูสลิป</button>
                <?php endif; ?>
                <form method="POST" class="mif" onsubmit="return confirm('คืนรายการนี้กลับไปหน้าอนุมัติแล้ว?')">
                  <input type="hidden" name="action" value="restore">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="arc-btn arc-btn-restore" style="padding:5px 11px;font-size:.74rem;">↩ คืนกลับ</button>
                </form>
                <form method="POST" class="mif" onsubmit="return confirm('ยืนยันการลบถาวร?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="arc-btn arc-btn-del" style="padding:5px 11px;font-size:.74rem;">🗑 ลบ</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
        <tr><td colspan="13">
          <div class="arc-empty">
            <div class="arc-empty-ico">📦</div>
            <div><?= $search ? 'ไม่พบรายการที่ตรงกับ "'.h($search).'"' : 'ยังไม่มีรายการที่จัดเก็บ' ?></div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function openSLB(src){document.getElementById('slipLbImg').src=src;document.getElementById('slipLb').classList.add('open');}
function closeSLB(){document.getElementById('slipLb').classList.remove('open');document.getElementById('slipLbImg').src='';}
document.getElementById('slipLb').addEventListener('click',function(e){if(e.target===this)closeSLB();});
</script>
<?php $stmt->close(); $conn->close(); include 'admin_layout_bottom.php'; ?>
