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
function statusLabel($s) {
    $m = ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','cancelled'=>'ยกเลิก'];
    return $m[$s] ?? $s;
}
function statusBadge($s) {
    $m = ['pending'=>'bk-badge-pending','approved'=>'bk-badge-approved','cancelled'=>'bk-badge-cancelled'];
    return $m[$s] ?? '';
}

$message = ''; $message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'archive_all') {
        $conn->query("UPDATE room_bookings SET archived=1 WHERE booking_status='approved' AND archived=0");
        header("Location: admin_booking_archive.php?msg=".urlencode("จัดเก็บข้อมูลเรียบร้อยแล้ว")."&type=success"); exit;
    }
    if ($action === 'set_pending') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("UPDATE room_bookings SET booking_status='pending' WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            header("Location: admin_booking_list.php?msg=".urlencode("ย้ายกลับรออนุมัติแล้ว")."&type=success"); exit;
        }
    }
    if ($action === 'set_cancelled') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("UPDATE room_bookings SET booking_status='cancelled' WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            header("Location: admin_booking_list.php?msg=".urlencode("ยกเลิกการอนุมัติแล้ว")."&type=success"); exit;
        }
    }
    if ($action === 'delete_booking') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("DELETE FROM room_bookings WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            $message = "ลบข้อมูลเรียบร้อยแล้ว"; $message_type = "success";
        }
    }
    header("Location: {$currentPage}?msg=".urlencode($message)."&type=".urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }
$search = trim($_GET['search'] ?? '');

$rs = $conn->query("SELECT COUNT(*) t FROM room_bookings WHERE archived=0 AND booking_status='approved'");
$stat_approved = (int)$rs->fetch_assoc()['t'];

$where = "WHERE archived=0 AND booking_status='approved'";
$params = []; $types = "";
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR room_type LIKE ?)";
    $like = "%{$search}%"; $params = [$like,$like,$like,$like]; $types = "ssss";
}
$stmt = $conn->prepare("SELECT * FROM room_bookings {$where} ORDER BY id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();
$total_rows = $result->num_rows;

$pageTitle = "รายการอนุมัติแล้ว"; $activeMenu = "booking_approved";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,0.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;}
.bk-wrap{padding:0 0 48px;animation:bkUp .4s ease both;}
@keyframes bkUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.bk-banner{border-radius:18px;padding:26px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;position:relative;overflow:hidden;background:linear-gradient(135deg,#065f46 0%,#059669 100%);}
.bk-banner::before{content:'';position:absolute;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,0.1) 0%,transparent 70%);top:-90px;right:-60px;pointer-events:none;}
.bk-banner h1{font-family:'Playfair Display',serif;font-style:italic;font-size:1.5rem;color:#fff;margin:0 0 5px;}
.bk-banner p{font-size:0.8rem;color:rgba(255,255,255,0.75);margin:0;}
.bk-banner-links{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1;}
.bk-banner-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:0.76rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.12);color:#fff;transition:all .2s;}
.bk-banner-link:hover{background:rgba(255,255,255,0.22);transform:translateY(-1px);}
.bk-alert{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:12px;font-size:0.85rem;font-weight:600;margin-bottom:22px;}
.bk-alert-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.bk-alert-error{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}
.bk-stats{display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:22px;}
.bk-stat{background:var(--card);border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(26,26,46,.06);border-top:3px solid var(--success);}
.bk-stat-label{font-size:0.68rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:700;}
.bk-stat-value{font-size:1.9rem;font-weight:800;color:var(--success);line-height:1;}
.bk-stat-sub{font-size:0.71rem;color:var(--muted);margin-top:4px;}
.bk-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.06);overflow:hidden;}
.bk-card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.bk-card-title{font-size:0.9rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
.bk-card-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--gold);border-radius:2px;}
.bk-card-sub{font-size:0.74rem;color:var(--muted);}
.bk-count{background:var(--gold-dim);color:#a07c3a;font-size:0.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.bk-search{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:#fdfcfa;}
.bk-search-wrap{position:relative;flex:1;min-width:180px;}
.bk-search-wrap::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:0.72rem;pointer-events:none;}
.bk-search-input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:0.84rem;color:var(--ink);background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;}
.bk-search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.bk-btn{display:inline-flex;align-items:center;gap:5px;padding:9px 16px;border:none;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.bk-btn:hover{transform:translateY(-1px);}
.bk-btn-primary{background:var(--ink);color:#fff;}.bk-btn-primary:hover{background:#2a2a4a;}
.bk-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}.bk-btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.bk-btn-warning{background:#fffbeb;color:var(--warning);border:1.5px solid #fde68a;}.bk-btn-warning:hover{background:#fef3c7;}
.bk-btn-danger{background:#fef2f2;color:var(--danger);border:1.5px solid #fca5a5;}.bk-btn-danger:hover{background:#fee2e2;}
.bk-table-wrap{overflow-x:auto;}
.bk-table{width:100%;border-collapse:collapse;min-width:1100px;}
.bk-table thead th{padding:11px 14px;font-size:0.67rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);border-bottom:2px solid var(--border);text-align:left;font-weight:700;background:#fdfcfa;}
.bk-table tbody td{padding:13px 14px;font-size:0.83rem;color:var(--ink);border-bottom:1px solid var(--border);vertical-align:middle;}
.bk-table tbody tr:last-child td{border-bottom:none;}
.bk-table tbody tr{transition:background .15s;}.bk-table tbody tr:hover{background:#fdfcfa;}
.bk-name{font-weight:700;}
.bk-meta{font-size:0.73rem;color:var(--muted);margin-top:2px;}
.bk-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.bk-inline{display:inline;margin:0;}
.bk-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:0.69rem;font-weight:700;white-space:nowrap;}
.bk-badge::before{content:'';width:5px;height:5px;border-radius:50%;}
.bk-badge-pending{background:#fffbeb;color:#92400e;}.bk-badge-pending::before{background:#f59e0b;}
.bk-badge-approved{background:#f0fdf4;color:#166534;}.bk-badge-approved::before{background:var(--success);}
.bk-badge-cancelled{background:#fef2f2;color:#991b1b;}.bk-badge-cancelled::before{background:var(--danger);}
.bk-empty{padding:48px 24px;text-align:center;}
.bk-empty-icon{font-size:2.2rem;margin-bottom:10px;opacity:.35;}
.bk-empty-text{font-size:0.83rem;color:var(--muted);line-height:1.7;}
.unit-pills{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px;}
.unit-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:0.65rem;font-weight:700;background:rgba(21,128,61,.1);border:1px solid rgba(21,128,61,.28);color:#15803d;}
.bill-ref{font-size:.78rem;font-weight:700;color:#15803d;white-space:nowrap;}
.price-meta{font-size:.75rem;color:var(--muted);margin-top:3px;}
.pm-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:.7rem;font-weight:700;margin-top:5px;}
.pm-cash{background:#f0fdf4;color:#15803d;border:1px solid #86efac;}
.pm-transfer{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
</style>

<div class="bk-wrap">

  <div class="bk-banner">
    <div>
      <h1>รายการอนุมัติแล้ว</h1>
      <p>รายการที่ผ่านการอนุมัติ — สามารถย้ายกลับหรือยกเลิกได้</p>
    </div>
    <div class="bk-banner-links">
      <a href="admin_booking_list.php"    class="bk-banner-link">← รายการรออนุมัติ</a>
      <a href="admin_booking_archive.php" class="bk-banner-link">🗂 รายการย้อนหลัง</a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันจัดเก็บข้อมูลที่อนุมัติแล้วทั้งหมด?')">
        <input type="hidden" name="action" value="archive_all">
        <button type="submit" class="bk-banner-link" style="cursor:pointer;">📦 จัดเก็บข้อมูล</button>
      </form>
    </div>
  </div>

  <?php if ($message !== ''): ?>
    <div class="bk-alert <?= $message_type==='error'?'bk-alert-error':'bk-alert-success' ?>">
      <?= $message_type==='error'?'⚠':'✓' ?> <?= h($message) ?>
    </div>
  <?php endif; ?>

  <div class="bk-stats">
    <div class="bk-stat">
      <div class="bk-stat-label">รายการอนุมัติแล้วทั้งหมด</div>
      <div class="bk-stat-value"><?= $stat_approved ?></div>
      <div class="bk-stat-sub">รายการสถานะอนุมัติที่ยังใช้งาน</div>
    </div>
  </div>

  <div class="bk-card">
    <div class="bk-card-header">
      <div>
        <div class="bk-card-title">รายการอนุมัติแล้วทั้งหมด</div>
        <div class="bk-card-sub">จัดการรายการที่อนุมัติได้จากที่นี่</div>
      </div>
      <span class="bk-count"><?= $total_rows ?> รายการ</span>
    </div>

    <form method="GET">
      <div class="bk-search">
        <div class="bk-search-wrap">
          <input type="text" name="search" class="bk-search-input"
                 placeholder="ค้นหาชื่อ, เบอร์โทร, อีเมล, ห้องพัก..."
                 value="<?= h($search) ?>">
        </div>
        <button type="submit" class="bk-btn bk-btn-primary">ค้นหา</button>
        <?php if ($search): ?>
          <a href="<?= h($currentPage) ?>" class="bk-btn bk-btn-ghost">ล้าง</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="bk-table-wrap">
      <table class="bk-table">
        <thead>
          <tr>
            <th style="width:44px;">#</th>
            <th>เลขที่บิล</th>
            <th>ผู้จอง</th>
            <th>ติดต่อ</th>
            <th>ห้องพัก / ราคา</th>
            <th>วันเข้า–ออก</th>
            <th>สถานะ</th>
            <th>วันที่จอง</th>
            <th style="width:160px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php $rowNum = 0; while ($row = $result->fetch_assoc()): $rowNum++;
              // เลขที่บิล
              $_bkDate = date('Ymd', strtotime($row['created_at']));
              $_seqR = $conn->query("SELECT COUNT(*) AS seq FROM room_bookings WHERE DATE(created_at)=DATE('".$conn->real_escape_string($row['created_at'])."') AND id<=".(int)$row['id']);
              $_seq  = (int)($_seqR ? $_seqR->fetch_assoc()['seq'] : 1);
              $_billRef = 'ROOM-'.$_bkDate.'-'.str_pad($_seq,3,'0',STR_PAD_LEFT);
              // คำนวณคืน
              $nights = 1;
              if (!empty($row['checkin_date']) && !empty($row['checkout_date'])) {
                  $d1 = new DateTime($row['checkin_date']);
                  $d2 = new DateTime($row['checkout_date']);
                  $nights = max(1, (int)$d1->diff($d2)->days);
              }
              // room units
              $units = !empty($row['room_units']) ? json_decode($row['room_units'], true) : [];
              // payment method
              $pm = trim($row['payment_method'] ?? '');
            ?>
              <tr>
                <td style="color:var(--muted);font-size:0.76rem;"><?= $rowNum ?></td>
                <td><span class="bill-ref"><?= h($_billRef) ?></span></td>
                <td>
                  <div class="bk-name"><?= h($row['full_name']) ?></div>
                  <div class="bk-meta">👥 <?= (int)$row['guests'] ?> คน</div>
                </td>
                <td>
                  <div><?= h($row['phone']) ?></div>
                  <div class="bk-meta"><?= h($row['email']) ?></div>
                </td>
                <td>
                  <div style="font-weight:700;font-size:.84rem;"><?= h($row['room_type']) ?> · สถาบันวิจัยลุ่มน้ำโขง</div>
                  <?php if (!empty($units)): ?>
                    <div class="unit-pills">
                      <?php foreach ($units as $u): ?>
                        <span class="unit-pill">🔑 ห้องที่ <?= (int)$u ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($row['total_price'])): ?>
                    <div class="price-meta">฿ <?= number_format((float)$row['total_price'], 2) ?></div>
                  <?php endif; ?>
                  <?php if ($pm !== ''):
                    $isCash = ($pm === 'เงินสด');
                  ?>
                    <span class="pm-pill <?= $isCash?'pm-cash':'pm-transfer' ?>">
                      <?= $isCash?'💵':'🏦' ?> <?= h($pm) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-size:0.79rem;">📅 <?= h($row['checkin_date']) ?></div>
                  <div style="font-size:0.79rem;color:var(--muted);">→ <?= h($row['checkout_date']) ?> (<?= $nights ?> คืน)</div>
                </td>
                <td>
                  <span class="bk-badge <?= statusBadge($row['booking_status']) ?>">
                    <?= statusLabel($row['booking_status']) ?>
                  </span>
                </td>
                <td style="font-size:0.76rem;color:var(--muted);"><?= h(substr($row['created_at'],0,16)) ?></td>
                <td>
                  <div class="bk-actions">
                    <form method="POST" class="bk-inline" onsubmit="return confirm('ย้ายกลับรออนุมัติ?')">
                      <input type="hidden" name="action" value="set_pending">
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <button class="bk-btn bk-btn-warning" style="padding:6px 11px;font-size:0.74rem;">↩ ย้ายกลับ</button>
                    </form>
                    <form method="POST" class="bk-inline" onsubmit="return confirm('ยกเลิกการอนุมัติ?')">
                      <input type="hidden" name="action" value="set_cancelled">
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <button class="bk-btn bk-btn-ghost" style="padding:6px 11px;font-size:0.74rem;">✕ ยกเลิก</button>
                    </form>
                    <form method="POST" class="bk-inline" onsubmit="return confirm('ยืนยันการลบ?')">
                      <input type="hidden" name="action" value="delete_booking">
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <button class="bk-btn bk-btn-danger" style="padding:6px 11px;font-size:0.74rem;">🗑 ลบ</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9">
              <div class="bk-empty">
                <div class="bk-empty-icon">✅</div>
                <div class="bk-empty-text">
                  <?= $search ? 'ไม่พบรายการที่ตรงกับ "'.h($search).'"' : 'ยังไม่มีรายการที่อนุมัติแล้ว' ?>
                </div>
              </div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php $stmt->close(); $conn->close(); include 'admin_layout_bottom.php'; ?>