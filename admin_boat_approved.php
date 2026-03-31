<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0");
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$currentPage = basename($_SERVER['PHP_SELF']);
$message = ''; $message_type = 'success';

// ---------------------------------------------------------------------------
// POST handlers
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'approve' && $id > 0) {
        // คำนวณเลขคิววันนี้
        $today  = date('Y-m-d');
        $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(approved_at) = '$today' AND booking_status = 'approved'");
        $qno    = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;

        $st = $conn->prepare("UPDATE boat_bookings
            SET payment_status='cash_paid', booking_status='approved',
                daily_queue_no=?, paid_at=IFNULL(paid_at,NOW()), approved_at=NOW()
            WHERE id=?");
        $st->bind_param("ii", $qno, $id); $st->execute(); $st->close();
        $message = "อนุมัติรายการเรียบร้อยแล้ว (คิว Q" . str_pad($qno,4,'0',STR_PAD_LEFT) . ")";
    }
    if ($action === 'reject' && $id > 0) {
        $st = $conn->prepare("UPDATE boat_bookings SET payment_status='failed', booking_status='rejected' WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "ปฏิเสธรายการเรียบร้อยแล้ว"; $message_type = 'danger';
    }
    if ($action === 'delete' && $id > 0) {
        $st = $conn->prepare("DELETE FROM boat_bookings WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "ลบรายการเรียบร้อยแล้ว";
    }
    if ($action === 'archive_approved') {
        $archDate = trim($_POST['archive_date'] ?? date('Y-m-d'));
        $bkRes = $conn->query("SELECT * FROM boat_bookings WHERE DATE(approved_at)='$archDate' AND booking_status='approved' AND payment_status='cash_paid' ORDER BY daily_queue_no ASC");
        $bkRows = []; $totalRev = 0;
        while ($bk = $bkRes->fetch_assoc()) {
            $bkRows[] = $bk;
            $totalRev += (float)($bk['total_amount'] ?? 0);
        }
        $json = json_encode($bkRows, JSON_UNESCAPED_UNICODE);
        $cnt  = count($bkRows);
        $archIns = $conn->prepare("INSERT INTO boat_queue_daily_archive (archive_date,total_queues,total_revenue,bookings_json) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE total_queues=VALUES(total_queues),total_revenue=VALUES(total_revenue),bookings_json=VALUES(bookings_json),archived_at=NOW()");
        $archIns->bind_param("siis", $archDate, $cnt, $totalRev, $json);
        $archIns->execute(); $archIns->close();
        $conn->query("UPDATE boat_bookings SET archived=1 WHERE DATE(approved_at)='$archDate' AND booking_status='approved' AND payment_status='cash_paid'");
        header("Location: admin_boat_archive_view.php"); exit;
    }
    header("Location: {$currentPage}?msg=" . urlencode($message) . "&type=" . urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }
$search = trim($_GET['search'] ?? '');

// ---------------------------------------------------------------------------
// Stats — เฉพาะเงินสด
// ---------------------------------------------------------------------------
$rs = $conn->query("SELECT
    COUNT(*) AS t,
    SUM(payment_status='cash_pending') AS p,
    SUM(payment_status='cash_paid') AS a,
    SUM(payment_status='failed') AS r
    FROM boat_bookings WHERE payment_provider='cash'");
$sr = $rs->fetch_assoc();
$stat_total    = (int)$sr['t'];
$stat_pending  = (int)$sr['p'];
$stat_approved = (int)$sr['a'];
$stat_rejected = (int)$sr['r'];

// ---------------------------------------------------------------------------
// List — รอดำเนินการเงินสด
// ---------------------------------------------------------------------------
$where = "WHERE payment_provider='cash' AND payment_status IN ('cash_pending','failed')";
$params = []; $types = "";
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR booking_ref LIKE ?)";
    $like = "%{$search}%"; $params = [$like,$like,$like]; $types = "sss";
}
$stmt = $conn->prepare("SELECT * FROM boat_bookings {$where} ORDER BY id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();

// รายการอนุมัติแล้ว (ทุก payment method ที่ booking_status='approved')
$whereApproved = "WHERE booking_status='approved' AND (archived IS NULL OR archived=0)";
$paramsA = []; $typesA = "";
if ($search !== '') {
    $whereApproved .= " AND (full_name LIKE ? OR phone LIKE ? OR booking_ref LIKE ?)";
    $paramsA = [$like,$like,$like]; $typesA = "sss";
}
$stmtA = $conn->prepare("SELECT * FROM boat_bookings {$whereApproved} ORDER BY approved_at DESC");
if (!empty($paramsA)) $stmtA->bind_param($typesA, ...$paramsA);
$stmtA->execute(); $resultA = $stmtA->get_result();

$pageTitle = "อนุมัติการจองเรือ"; $activeMenu = "boat_approve";
include 'admin_layout_top.php';
?>
<style>
:root{
  --gold:#c9a96e;--gold-light:#fdf3e3;
  --ink:#1a1a2e;--ink2:#2d3250;
  --bg:#f4f0ea;--card:#fff;
  --muted:#8a8a9a;--border:#e8e3dc;
  --danger:#e53e3e;--danger-light:#fff5f5;--danger-mid:#feb2b2;
  --success:#22863a;--success-light:#f0fff4;--success-mid:#9ae6b4;
  --warning:#c97b10;--warning-light:#fffaf0;--warning-mid:#fbd38d;
  --shadow:0 4px 24px rgba(26,26,46,.09);
  --shadow-sm:0 2px 10px rgba(26,26,46,.06);
}
*{box-sizing:border-box;}
.tk-wrap{padding:0 0 56px;animation:tkUp .45s cubic-bezier(.22,.68,0,1.2) both;}
@keyframes tkUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}

/* ── Banner ── */
.tk-banner{
  border-radius:20px;padding:32px 36px;margin-bottom:28px;
  display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;
  background:linear-gradient(135deg,#0f2d1f 0%,#163b28 40%,#1a2840 100%);
  position:relative;overflow:hidden;
}
.tk-banner::before{
  content:'';position:absolute;width:380px;height:380px;border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,.15) 0%,transparent 70%);
  top:-140px;right:-80px;pointer-events:none;
}
.tk-banner::after{
  content:'';position:absolute;width:200px;height:200px;border-radius:50%;
  background:rgba(255,255,255,.04);bottom:-80px;left:60px;pointer-events:none;
}
.tk-banner-body{position:relative;z-index:1;}
.tk-banner-eyebrow{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(201,169,110,.85);font-weight:700;margin-bottom:8px;}
.tk-banner h1{font-size:1.6rem;font-weight:800;color:#fff;margin:0 0 6px;line-height:1.2;}
.tk-banner p{font-size:.82rem;color:rgba(255,255,255,.6);margin:0;}
.tk-banner-links{display:flex;gap:10px;position:relative;z-index:1;flex-wrap:wrap;}
.tk-banner-link{
  display:inline-flex;align-items:center;gap:6px;padding:9px 16px;
  border-radius:10px;font-size:.76rem;font-weight:700;text-decoration:none;
  border:1.5px solid rgba(255,255,255,.18);
  background:rgba(255,255,255,.08);
  color:#fff;transition:all .2s;backdrop-filter:blur(6px);
}
.tk-banner-link:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.35);transform:translateY(-1px);}

/* ── Alert ── */
.tk-alert{display:flex;align-items:center;gap:12px;padding:14px 20px;border-radius:14px;font-size:.86rem;font-weight:600;margin-bottom:24px;border-left:4px solid;}
.tk-alert-success{background:var(--success-light);border-color:var(--success);color:var(--success);}
.tk-alert-danger{background:var(--danger-light);border-color:var(--danger);color:var(--danger);}
.tk-alert-icon{font-size:1.1rem;flex-shrink:0;}

/* ── Stats ── */
.tk-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.tk-stat{
  background:var(--card);border-radius:16px;padding:22px 20px 18px;
  box-shadow:var(--shadow-sm);border:1px solid var(--border);
  display:flex;align-items:flex-start;gap:16px;position:relative;overflow:hidden;
}
.tk-stat::after{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0;
}
.tk-stat:nth-child(1)::after{background:linear-gradient(90deg,var(--gold),#e8c97a);}
.tk-stat:nth-child(2)::after{background:linear-gradient(90deg,#f59e0b,#fbbf24);}
.tk-stat:nth-child(3)::after{background:linear-gradient(90deg,#22c55e,#4ade80);}
.tk-stat:nth-child(4)::after{background:linear-gradient(90deg,#ef4444,#f87171);}
.tk-stat-icon{
  width:44px;height:44px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;
}
.tk-stat:nth-child(1) .tk-stat-icon{background:var(--gold-light);}
.tk-stat:nth-child(2) .tk-stat-icon{background:#fef3c7;}
.tk-stat:nth-child(3) .tk-stat-icon{background:#dcfce7;}
.tk-stat:nth-child(4) .tk-stat-icon{background:#fee2e2;}
.tk-stat-body{flex:1;min-width:0;}
.tk-stat-label{font-size:.67rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;font-weight:700;}
.tk-stat-value{font-size:2rem;font-weight:800;line-height:1;color:var(--ink);}
.tk-stat:nth-child(2) .tk-stat-value{color:#d97706;}
.tk-stat:nth-child(3) .tk-stat-value{color:#16a34a;}
.tk-stat:nth-child(4) .tk-stat-value{color:#dc2626;}
.tk-stat-sub{font-size:.7rem;color:var(--muted);margin-top:5px;}

/* ── Card ── */
.tk-card{background:var(--card);border-radius:18px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:24px;}
.tk-card-header{
  padding:18px 24px;border-bottom:1px solid var(--border);
  display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
  background:linear-gradient(180deg,#faf9f6 0%,#fff 100%);
  border-radius:18px 18px 0 0;
}
.tk-card-title{font-size:.92rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:10px;}
.tk-card-title::before{content:'';display:inline-block;width:4px;height:16px;background:var(--warning);border-radius:3px;}
.tk-card-title.approved-title::before{background:var(--success);}
.tk-count{
  background:var(--warning-light);color:var(--warning);border:1px solid var(--warning-mid);
  font-size:.69rem;font-weight:700;padding:3px 11px;border-radius:20px;
}
.tk-count-success{
  background:var(--success-light);color:var(--success);border:1px solid var(--success-mid);
  font-size:.69rem;font-weight:700;padding:3px 11px;border-radius:20px;
}

/* ── Search ── */
.tk-search{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;gap:10px;background:#fdfcfa;}
.tk-search-wrap{position:relative;flex:1;}
.tk-search-wrap::before{content:'🔍';position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.72rem;pointer-events:none;}
.tk-search-input{
  width:100%;padding:10px 14px 10px 36px;
  border:1.5px solid var(--border);border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:.85rem;outline:none;
  transition:border-color .2s,box-shadow .2s;background:#fff;
}
.tk-search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.15);}

/* ── Buttons ── */
.tk-btn{
  display:inline-flex;align-items:center;gap:5px;padding:8px 15px;
  border:none;border-radius:10px;font-family:'Sarabun',sans-serif;
  font-size:.8rem;font-weight:700;cursor:pointer;text-decoration:none;
  transition:all .2s;white-space:nowrap;
}
.tk-btn-primary{background:var(--ink);color:#fff;box-shadow:0 2px 8px rgba(26,26,46,.25);}
.tk-btn-primary:hover{background:var(--ink2);transform:translateY(-1px);}
.tk-btn-success{background:#16a34a;color:#fff;box-shadow:0 2px 8px rgba(22,163,74,.3);}
.tk-btn-success:hover{background:#15803d;transform:translateY(-1px);}
.tk-btn-danger{background:#dc2626;color:#fff;box-shadow:0 2px 8px rgba(220,38,38,.25);}
.tk-btn-danger:hover{background:#b91c1c;transform:translateY(-1px);}
.tk-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.tk-btn-ghost:hover{background:#f5f1eb;color:var(--ink);}
.tk-btn-archive{
  background:var(--success-light);color:var(--success);
  border:1.5px solid var(--success-mid);box-shadow:none;
}
.tk-btn-archive:hover{background:#dcfce7;transform:translateY(-1px);}

/* ── Table ── */
.tk-table{width:100%;border-collapse:collapse;}
.tk-table thead{position:sticky;top:0;z-index:2;}
.tk-table th{
  padding:11px 16px;font-size:.7rem;font-weight:700;
  color:var(--muted);text-transform:uppercase;letter-spacing:.07em;
  border-bottom:2px solid var(--border);background:#faf9f6;text-align:left;
}
.tk-table td{
  padding:15px 16px;border-bottom:1px solid #f0ede8;
  font-size:.84rem;color:var(--ink);vertical-align:middle;
}
.tk-table tbody tr:last-child td{border-bottom:none;}
.tk-table tbody tr:hover td{background:#fdfaf6;}
.tk-table tbody tr:nth-child(even) td{background:#faf8f5;}
.tk-table tbody tr:nth-child(even):hover td{background:#f5f1eb;}
.tk-empty{text-align:center;padding:60px 20px;color:var(--muted);}
.tk-empty-icon{font-size:2.5rem;margin-bottom:10px;}
.tk-empty-text{font-size:.9rem;font-weight:600;}
.tk-empty-sub{font-size:.78rem;margin-top:4px;opacity:.7;}

/* ── Data cells ── */
.bk-name{font-weight:700;font-size:.88rem;color:var(--ink);}
.bk-sub{font-size:.74rem;color:var(--muted);margin-top:3px;line-height:1.4;}
.ref-pill{
  display:inline-block;padding:3px 10px;border-radius:6px;
  background:#f1f5f9;border:1px solid #dde3ec;
  font-size:.72rem;font-weight:700;font-family:monospace;color:#334155;letter-spacing:.02em;
}
.amount-cell{font-weight:800;font-size:.92rem;color:#16a34a;}
.num-badge{
  display:inline-flex;align-items:center;justify-content:center;
  width:26px;height:26px;border-radius:8px;
  background:#f0ede8;color:var(--muted);font-size:.74rem;font-weight:800;
}

/* ── Status pills ── */
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;font-size:.73rem;font-weight:700;}
.status-pill::before{content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.s-pending{background:#fffbeb;color:#b45309;border:1px solid #fde68a;}
.s-pending::before{background:#f59e0b;}
.s-failed{background:#fef2f2;color:#b91c1c;border:1px solid #fca5a5;}
.s-failed::before{background:#ef4444;}
.s-approved{background:#f0fdf4;color:#166534;border:1px solid #86efac;}
.s-approved::before{background:#22c55e;}

/* ── Actions ── */
.actions{display:flex;flex-direction:column;gap:7px;min-width:90px;}

/* ── Queue badge ── */
.queue-badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 12px;border-radius:10px;
  background:linear-gradient(135deg,#0f2d1f,#1a2840);
  color:#fff;font-weight:800;font-size:.82rem;letter-spacing:.03em;
  text-decoration:none;box-shadow:0 2px 8px rgba(15,45,31,.3);
  transition:all .2s;
}
.queue-badge:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(15,45,31,.35);}
.queue-badge-q{color:rgba(201,169,110,.9);font-size:.7rem;margin-right:1px;}
.tk-btn-ticket{
  display:inline-flex;align-items:center;gap:5px;padding:5px 11px;
  border-radius:8px;font-size:.74rem;font-weight:700;text-decoration:none;
  background:var(--success-light);color:var(--success);border:1px solid var(--success-mid);
  transition:all .2s;margin-top:6px;
}
.tk-btn-ticket:hover{background:#dcfce7;transform:translateY(-1px);}

@media(max-width:900px){.tk-stats{grid-template-columns:repeat(2,1fr);}}
@media(max-width:600px){.tk-stats{grid-template-columns:1fr 1fr;}.tk-table{display:block;overflow-x:auto;}.tk-banner{padding:22px 20px;}.tk-banner h1{font-size:1.25rem;}}
</style>

<div class="tk-wrap">

  <!-- Banner -->
  <div class="tk-banner">
    <div class="tk-banner-body">
      <div class="tk-banner-eyebrow">การจองเรือ · เงินสด</div>
      <h1>💵 อนุมัติการจองเรือ</h1>
      <p>รายการรอชำระเงินสด — ลูกค้านำใบจองมายื่นที่สำนักงาน</p>
    </div>
    <div class="tk-banner-links">
      <a href="admin_boat_bookings.php" class="tk-banner-link">🚣 รายการทั้งหมด</a>
      <a href="admin_boat_queues.php" class="tk-banner-link">🛶 จัดการคิว</a>
      <a href="admin_boat_archive_view.php" class="tk-banner-link">📦 จัดเก็บข้อมูล</a>
    </div>
  </div>

  <!-- Alert -->
  <?php if ($message): ?>
  <div class="tk-alert tk-alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
    <span class="tk-alert-icon"><?= $message_type === 'success' ? '✅' : '❌' ?></span>
    <?= h($message) ?>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="tk-stats">
    <div class="tk-stat">
      <div class="tk-stat-icon">💳</div>
      <div class="tk-stat-body">
        <div class="tk-stat-label">รายการทั้งหมด</div>
        <div class="tk-stat-value"><?= $stat_total ?></div>
        <div class="tk-stat-sub">เงินสดในระบบ</div>
      </div>
    </div>
    <div class="tk-stat">
      <div class="tk-stat-icon">⏳</div>
      <div class="tk-stat-body">
        <div class="tk-stat-label">รออนุมัติ</div>
        <div class="tk-stat-value"><?= $stat_pending ?></div>
        <div class="tk-stat-sub">รอแอดมินตรวจสอบ</div>
      </div>
    </div>
    <div class="tk-stat">
      <div class="tk-stat-icon">✅</div>
      <div class="tk-stat-body">
        <div class="tk-stat-label">อนุมัติแล้ว</div>
        <div class="tk-stat-value"><?= $stat_approved ?></div>
        <div class="tk-stat-sub">ผ่านการอนุมัติ</div>
      </div>
    </div>
    <div class="tk-stat">
      <div class="tk-stat-icon">🚫</div>
      <div class="tk-stat-body">
        <div class="tk-stat-label">ปฏิเสธ</div>
        <div class="tk-stat-value"><?= $stat_rejected ?></div>
        <div class="tk-stat-sub">ไม่ผ่านการอนุมัติ</div>
      </div>
    </div>
  </div>

  <!-- Pending card -->
  <div class="tk-card">
    <div class="tk-card-header">
      <div class="tk-card-title">
        รายการรออนุมัติ
        <span class="tk-count"><?= $result->num_rows ?> รายการ</span>
      </div>
    </div>
    <div class="tk-search">
      <form method="GET" style="display:flex;gap:10px;width:100%;">
        <div class="tk-search-wrap">
          <input type="text" name="search" class="tk-search-input"
                 placeholder="ค้นหาชื่อ, เบอร์, booking ref..."
                 value="<?= h($search) ?>">
        </div>
        <button type="submit" class="tk-btn tk-btn-primary">ค้นหา</button>
        <?php if ($search): ?>
          <a href="<?= $currentPage ?>" class="tk-btn tk-btn-ghost">ล้าง</a>
        <?php endif; ?>
      </form>
    </div>
    <div style="overflow-x:auto;">
      <table class="tk-table">
        <thead>
          <tr>
            <th>#</th><th>ผู้จอง</th><th>หมายเลขจอง</th>
            <th>เรือ / วันที่</th><th>จำนวน</th><th>ยอด</th>
            <th>สถานะ</th><th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
          <tr><td colspan="8" class="tk-empty">
            <div class="tk-empty-icon">📋</div>
            <div class="tk-empty-text">ไม่มีรายการรออนุมัติ</div>
            <div class="tk-empty-sub">รายการใหม่จะแสดงที่นี่เมื่อมีลูกค้านำใบจองมายื่น</div>
          </td></tr>
        <?php else: $rowNum = 1; while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><span class="num-badge"><?= $rowNum++ ?></span></td>
            <td>
              <div class="bk-name"><?= h($row['full_name']) ?></div>
              <div class="bk-sub"><?= h($row['phone']) ?></div>
              <?php if ($row['email']): ?>
              <div class="bk-sub"><?= h($row['email']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="ref-pill"><?= h($row['booking_ref'] ?? '-') ?></span>
              <?php if ($row['queue_name']): ?>
              <div class="bk-sub" style="margin-top:5px;"><?= h($row['queue_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:700;"><?= h($row['boat_type'] ?? '-') ?></div>
              <div class="bk-sub">📅 <?= h($row['boat_date'] ?? '-') ?></div>
              <div class="bk-sub" style="font-size:.69rem;">จอง <?= h(date('d/m H:i', strtotime($row['created_at']))) ?></div>
            </td>
            <td style="font-weight:600;"><?= (int)$row['guests'] ?> คน</td>
            <td><span class="amount-cell">฿<?= number_format((float)$row['total_amount'], 0) ?></span></td>
            <td>
              <?php $ps = $row['payment_status'];
              if ($ps === 'cash_pending'): ?>
                <span class="status-pill s-pending">รอชำระสด</span>
              <?php elseif ($ps === 'failed'): ?>
                <span class="status-pill s-failed">ปฏิเสธ</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="actions">
                <form method="POST" onsubmit="return confirm('อนุมัติรายการนี้?')">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="tk-btn tk-btn-success" style="width:100%;">✓ อนุมัติ</button>
                </form>
                <?php if ($row['payment_status'] === 'cash_pending'): ?>
                <form method="POST" onsubmit="return confirm('ยืนยันลบรายการ รอชำระสด นี้?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="tk-btn tk-btn-danger" style="width:100%;">🗑 ลบ</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Approved card -->
  <div class="tk-card">
    <div class="tk-card-header">
      <div class="tk-card-title approved-title">
        รายการอนุมัติแล้ว
        <span class="tk-count-success"><?= $resultA->num_rows ?> รายการ</span>
      </div>
      <form method="POST" onsubmit="return confirm('ยืนยันจัดเก็บข้อมูลที่อนุมัติแล้ววันนี้?')">
        <input type="hidden" name="action" value="archive_approved">
        <input type="hidden" name="archive_date" value="<?= date('Y-m-d') ?>">
        <button class="tk-btn tk-btn-archive">📦 จัดเก็บข้อมูลวันนี้</button>
      </form>
    </div>
    <div style="overflow-x:auto;">
      <table class="tk-table">
        <thead>
          <tr>
            <th>#</th><th>ผู้จอง</th><th>หมายเลขจอง</th>
            <th>เรือ / วันที่</th><th>จำนวน</th><th>ยอด</th>
            <th>บัตรคิว</th><th>สถานะ</th><th>วันที่อนุมัติ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($resultA->num_rows === 0): ?>
          <tr><td colspan="9" class="tk-empty">
            <div class="tk-empty-icon">✅</div>
            <div class="tk-empty-text">ยังไม่มีรายการอนุมัติ</div>
            <div class="tk-empty-sub">รายการที่อนุมัติแล้วจะแสดงที่นี่</div>
          </td></tr>
        <?php else: $rowNumA = 1; while ($rowA = $resultA->fetch_assoc()): ?>
          <tr>
            <td><span class="num-badge"><?= $rowNumA++ ?></span></td>
            <td>
              <div class="bk-name"><?= h($rowA['full_name']) ?></div>
              <div class="bk-sub"><?= h($rowA['phone']) ?></div>
              <?php if ($rowA['email']): ?>
              <div class="bk-sub"><?= h($rowA['email']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="ref-pill"><?= h($rowA['booking_ref'] ?? '-') ?></span>
              <?php if ($rowA['queue_name']): ?>
              <div class="bk-sub" style="margin-top:5px;"><?= h($rowA['queue_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:700;"><?= h($rowA['boat_type'] ?? '-') ?></div>
              <div class="bk-sub">📅 <?= h($rowA['boat_date'] ?? '-') ?></div>
            </td>
            <td style="font-weight:600;"><?= (int)$rowA['guests'] ?> คน</td>
            <td><span class="amount-cell">฿<?= number_format((float)$rowA['total_amount'], 0) ?></span></td>
            <td>
              <?php if (!empty($rowA['daily_queue_no'])): ?>
              <div style="display:flex;flex-direction:column;align-items:flex-start;gap:0;">
                <a href="queue_ticket.php?ref=<?= urlencode($rowA['booking_ref'] ?? '') ?>"
                   target="_blank" class="queue-badge">
                  <span class="queue-badge-q">Q</span><?= str_pad((int)$rowA['daily_queue_no'], 4, '0', STR_PAD_LEFT) ?>
                </a>
                <a href="queue_ticket.php?ref=<?= urlencode($rowA['booking_ref'] ?? '') ?>"
                   target="_blank" class="tk-btn-ticket">🎫 ดูบัตรคิว</a>
              </div>
              <?php else: ?>
              <span style="font-size:.74rem;color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
            <td><span class="status-pill s-approved">อนุมัติแล้ว</span></td>
            <td style="font-size:.76rem;color:var(--muted);"><?= h(substr($rowA['approved_at'] ?? $rowA['created_at'], 0, 16)) ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php $stmtA->close(); include 'admin_layout_bottom.php'; ?>
