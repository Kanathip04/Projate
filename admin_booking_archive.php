<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php"); exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message = ''; $message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'restore_booking') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("UPDATE room_bookings SET archived=0 WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            $message = "กู้คืนข้อมูลเรียบร้อยแล้ว";
        }
    }
    if ($action === 'delete_booking') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("DELETE FROM room_bookings WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            $message = "ลบข้อมูลถาวรเรียบร้อยแล้ว";
        }
    }
    header("Location: {$currentPage}?msg=".urlencode($message)."&type=".urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }
$search      = trim($_GET['search']      ?? '');
$filterDate  = trim($_GET['filter_date'] ?? '');
$filterMonth = trim($_GET['filter_month']?? '');

$where = "WHERE archived=1";
$params = []; $types = "";
if ($filterDate !== '') {
    $where .= " AND DATE(COALESCE(approved_at, created_at)) = ?";
    $params[] = $filterDate; $types .= "s";
} elseif ($filterMonth !== '') {
    $where .= " AND DATE_FORMAT(COALESCE(approved_at, created_at),'%Y-%m') = ?";
    $params[] = $filterMonth; $types .= "s";
}
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR room_type LIKE ?)";
    $like = "%{$search}%"; $params = array_merge($params,[$like,$like,$like,$like]); $types .= "ssss";
}
$stmt = $conn->prepare("SELECT * FROM room_bookings {$where} ORDER BY id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();
$total = $result->num_rows;

$pageTitle = "จัดเก็บห้องพัก"; $activeMenu = "booking_archive";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;}

.arc-banner{border-radius:18px;padding:22px 28px;margin-bottom:24px;
  background:linear-gradient(135deg,#1e3a5f 0%,#1d4ed8 100%);
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

.arc-card{background:var(--card);border-radius:18px;box-shadow:0 2px 16px rgba(26,26,46,.07);overflow:hidden;}
.arc-card-head{padding:16px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.arc-title{font-size:.9rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px;}
.arc-title::before{content:'';display:inline-block;width:3px;height:14px;background:#1d4ed8;border-radius:2px;}
.arc-cnt{background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}

.arc-search{padding:14px 22px;border-bottom:1px solid var(--border);
  display:flex;gap:9px;flex-wrap:wrap;align-items:center;background:#fdfcfa;}
.arc-sw{position:relative;flex:1;min-width:180px;}
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
.unit-pills{display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;}
.unit-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:.64rem;
  font-weight:700;background:rgba(21,128,61,.1);border:1px solid rgba(21,128,61,.28);color:#15803d;}
.pm-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:99px;font-size:.68rem;font-weight:700;margin-top:4px;}
.pm-cash{background:#f0fdf4;color:#15803d;border:1px solid #86efac;}
.pm-transfer{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.bill-ref{font-size:.78rem;font-weight:700;color:#15803d;white-space:nowrap;}
.date-block{font-size:.77rem;}
.date-block-sub{font-size:.72rem;color:var(--muted);margin-top:2px;}
</style>

<?php if ($message !== ''): ?>
<div class="arc-alert <?= $message_type==='error'?'arc-alert-err':'arc-alert-ok' ?>">
  <?= $message_type==='error'?'⚠':'✓' ?> <?= h($message) ?>
</div>
<?php endif; ?>

<div class="arc-banner">
  <div>
    <h1>🗂 จัดเก็บห้องพัก</h1>
    <p>รายการจองห้องพักที่ถูกจัดเก็บแล้ว — กู้คืนหรือลบถาวรได้</p>
  </div>
  <div class="arc-banner-links">
    <a href="admin_booking_approved.php" class="arc-link">✅ รายการอนุมัติ</a>
    <a href="admin_booking_list.php"     class="arc-link">← รายการจอง</a>
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
        <input type="text" name="search" placeholder="ค้นหาชื่อ, เบอร์โทร, อีเมล, ห้องพัก..."
               value="<?= h($search) ?>">
      </div>
      <div>
        <input type="date" name="filter_date" value="<?= h($filterDate) ?>"
               style="height:36px;padding:0 10px;border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.82rem;color:var(--ink);background:#fff;outline:none;"
               title="กรองตามวันที่">
      </div>
      <button type="submit" class="arc-btn arc-btn-primary">ค้นหา</button>
      <?php if ($search || $filterDate || $filterMonth): ?>
        <a href="<?= h($currentPage) ?>" class="arc-btn arc-btn-ghost">ล้าง</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="arc-table-wrap">
    <table class="arc-table">
      <thead>
        <tr>
          <th style="width:44px;">#</th>
          <th>เลขที่บิล</th>
          <th>ผู้จอง</th>
          <th>ติดต่อ</th>
          <th>ห้องพัก / ราคา</th>
          <th>วันเข้า–ออก</th>
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
            $_seqR = $conn->query("SELECT COUNT(*) AS seq FROM room_bookings WHERE DATE(created_at)=DATE('".$conn->real_escape_string($row['created_at'])."') AND id<=".(int)$row['id']);
            $_seq  = (int)($_seqR ? $_seqR->fetch_assoc()['seq'] : 1);
            $_billRef = 'ROOM-'.$_bkDate.'-'.str_pad($_seq,3,'0',STR_PAD_LEFT);
            // คืน
            $nights = 1;
            if (!empty($row['checkin_date']) && !empty($row['checkout_date'])) {
                $d1 = new DateTime($row['checkin_date']);
                $d2 = new DateTime($row['checkout_date']);
                $nights = max(1, (int)$d1->diff($d2)->days);
            }
            // units
            $units = !empty($row['room_units']) ? json_decode($row['room_units'], true) : [];
            // payment
            $pm = trim($row['payment_method'] ?? '');
          ?>
          <tr>
            <td style="color:var(--muted);font-size:.76rem;"><?= $no++ ?></td>
            <td><span class="bill-ref"><?= h($_billRef) ?></span></td>
            <td>
              <div class="arc-name"><?= h($row['full_name']) ?></div>
              <div class="arc-meta">👥 <?= (int)($row['guests']??1) ?> คน</div>
            </td>
            <td>
              <div><?= h($row['phone']??'—') ?></div>
              <div class="arc-meta"><?= h($row['email']??'—') ?></div>
            </td>
            <td>
              <div style="font-weight:700;font-size:.83rem;"><?= h($row['room_type']??'—') ?></div>
              <?php if (!empty($units)): ?>
                <div class="unit-pills">
                  <?php foreach ($units as $u): ?>
                    <span class="unit-pill">🔑 ห้องที่ <?= (int)$u ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($row['total_price'])): ?>
                <div class="arc-meta">฿ <?= number_format((float)$row['total_price'],2) ?></div>
              <?php endif; ?>
              <?php if ($pm !== ''): $isCash = ($pm==='เงินสด'); ?>
                <span class="pm-pill <?= $isCash?'pm-cash':'pm-transfer' ?>">
                  <?= $isCash?'💵':'🏦' ?> <?= h($pm) ?>
                </span>
              <?php endif; ?>
            </td>
            <td>
              <div class="date-block">📅 <?= h($row['checkin_date']??'—') ?></div>
              <div class="date-block-sub">→ <?= h($row['checkout_date']??'—') ?> (<?= $nights ?> คืน)</div>
            </td>
            <td style="font-size:.75rem;color:var(--muted);"><?= h(substr($row['created_at'],0,16)) ?></td>
            <td style="font-size:.75rem;color:var(--muted);">
              <?= !empty($row['approved_at']) ? h(substr($row['approved_at'],0,16)) : '—' ?>
            </td>
            <td><span class="badge-archived">🗂 จัดเก็บแล้ว</span></td>
            <td>
              <div class="arc-actions">
                <form method="POST" class="mif" onsubmit="return confirm('กู้คืนรายการนี้?')">
                  <input type="hidden" name="action" value="restore_booking">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="arc-btn arc-btn-restore" style="padding:5px 11px;font-size:.74rem;">↩ คืนกลับ</button>
                </form>
                <form method="POST" class="mif" onsubmit="return confirm('ลบถาวร — ไม่สามารถกู้คืนได้!')">
                  <input type="hidden" name="action" value="delete_booking">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="arc-btn arc-btn-del" style="padding:5px 11px;font-size:.74rem;">🗑 ลบ</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
        <tr><td colspan="10">
          <div class="arc-empty">
            <div class="arc-empty-ico">🗂</div>
            <div><?= $search ? 'ไม่พบรายการที่ตรงกับ "'.h($search).'"' : 'ยังไม่มีข้อมูลที่จัดเก็บ' ?></div>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php $stmt->close(); $conn->close(); include 'admin_layout_bottom.php'; ?>
