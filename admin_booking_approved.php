<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
ini_set('display_errors', 1); error_reporting(E_ALL);

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php"); exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function statusMap() {
    return [
        'pending'   => ['label'=>'รออนุมัติ',  'badge'=>'bk-badge-pending'],
        'approved'  => ['label'=>'อนุมัติแล้ว','badge'=>'bk-badge-approved'],
        'cancelled' => ['label'=>'ยกเลิก',     'badge'=>'bk-badge-cancelled'],
    ];
}
function statusLabel($s) { $m=statusMap(); return $m[$s]['label'] ?? $s; }
function statusBadge($s) { $m=statusMap(); return $m[$s]['badge'] ?? ''; }

$message = ''; $message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_pending') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("UPDATE room_bookings SET booking_status='pending' WHERE id=?");
            $st->bind_param("i",$id); $st->execute(); $st->close();
            header("Location: admin_booking_list.php?msg=".urlencode("ย้ายกลับรออนุมัติแล้ว")."&type=success"); exit;
        }
    }
    if ($action === 'set_cancelled') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("UPDATE room_bookings SET booking_status='cancelled' WHERE id=?");
            $st->bind_param("i",$id); $st->execute(); $st->close();
            header("Location: admin_booking_list.php?msg=".urlencode("ยกเลิกการอนุมัติแล้ว")."&type=success"); exit;
        }
    }
    if ($action === 'delete_booking') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("DELETE FROM room_bookings WHERE id=?");
            $st->bind_param("i",$id); $st->execute(); $st->close();
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
/* ── Shared Lumière Booking Style ── */
:root {
  --gold:      #c9a96e;
  --gold-dim:  rgba(201,169,110,0.12);
  --ink:       #1a1a2e;
  --bg:        #f5f1eb;
  --card:      #ffffff;
  --muted:     #7a7a8c;
  --border:    #e8e4de;
  --danger:    #dc2626;
  --success:   #16a34a;
  --warning:   #d97706;
  --info:      #1d4ed8;
  --radius:    14px;
}

.bk-wrap { padding: 0 0 48px; animation: fadeUp 0.4s ease both; }
@keyframes fadeUp {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0); }
}

/* ── Page banner ── */
.bk-banner {
  border-radius: 18px;
  padding: 28px 32px;
  margin-bottom: 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  position: relative;
  overflow: hidden;
}
.bk-banner::before {
  content: '';
  position: absolute;
  width: 320px; height: 320px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
  top: -120px; right: -80px; pointer-events: none;
}
.bk-banner-text h1 {
  font-family: 'Playfair Display', serif;
  font-style: italic;
  font-size: 1.6rem;
  color: #fff;
  margin: 0 0 6px;
}
.bk-banner-text p {
  font-size: 0.82rem;
  color: rgba(255,255,255,0.75);
  margin: 0;
  line-height: 1.6;
}
.bk-banner-links {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  position: relative; z-index: 1;
}
.bk-banner-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  text-decoration: none;
  border: 1.5px solid rgba(255,255,255,0.25);
  background: rgba(255,255,255,0.12);
  color: #fff;
  letter-spacing: 0.04em;
  transition: all 0.2s;
}
.bk-banner-link:hover { background: rgba(255,255,255,0.22); transform: translateY(-1px); }

/* ── Alert ── */
.bk-alert {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 18px;
  border-radius: var(--radius);
  font-size: 0.85rem; font-weight: 600;
  margin-bottom: 24px;
  animation: slideDown 0.3s ease;
}
@keyframes slideDown {
  from { opacity:0; transform:translateY(-8px); }
  to   { opacity:1; transform:translateY(0); }
}
.bk-alert-success { background: #f0fdf4; border: 1.5px solid #86efac; color: var(--success); }
.bk-alert-error   { background: #fef2f2; border: 1.5px solid #fca5a5; color: var(--danger); }

/* ── Stat grid ── */
.bk-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
.bk-stat {
  background: var(--card);
  border-radius: var(--radius);
  padding: 20px;
  box-shadow: 0 2px 12px rgba(26,26,46,0.06);
  border-top: 3px solid var(--border);
  position: relative; overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}
.bk-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,26,46,0.1); }
.bk-stat::after {
  content: ''; position: absolute; bottom: -20px; right: -20px;
  width: 70px; height: 70px; border-radius: 50%;
  background: radial-gradient(circle, var(--gold-dim) 0%, transparent 70%);
}
.bk-stat-label { font-size: 0.68rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; font-weight: 700; }
.bk-stat-value { font-size: 1.9rem; font-weight: 800; color: var(--ink); line-height: 1; }
.bk-stat-sub   { font-size: 0.72rem; color: var(--muted); margin-top: 5px; }

/* ── Card ── */
.bk-card {
  background: var(--card);
  border-radius: 18px;
  box-shadow: 0 2px 12px rgba(26,26,46,0.06);
  overflow: hidden;
}
.bk-card-header {
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: 12px;
}
.bk-card-title {
  font-size: 0.92rem; font-weight: 700; color: var(--ink);
  display: flex; align-items: center; gap: 8px;
}
.bk-card-title::before {
  content: ''; display: inline-block;
  width: 3px; height: 14px; background: var(--gold); border-radius: 2px;
}
.bk-card-sub { font-size: 0.75rem; color: var(--muted); }

/* ── Search bar ── */
.bk-search {
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
  display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
  background: #fdfcfa;
}
.bk-search-input {
  flex: 1; min-width: 200px;
  padding: 10px 14px 10px 36px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: 'Sarabun', sans-serif;
  font-size: 0.85rem; color: var(--ink); background: #fff; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  position: relative;
}
.bk-search-input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,169,110,0.12); }
.bk-search-wrap { position: relative; flex: 1; }
.bk-search-wrap::before {
  content: '🔍'; position: absolute; left: 11px; top: 50%;
  transform: translateY(-50%); font-size: 0.75rem; pointer-events: none; z-index: 1;
}

/* ── Buttons ── */
.bk-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 16px; border: none; border-radius: 8px;
  font-family: 'Sarabun', sans-serif; font-size: 0.8rem; font-weight: 700;
  cursor: pointer; text-decoration: none;
  transition: all 0.2s ease; letter-spacing: 0.04em; white-space: nowrap;
}
.bk-btn:hover { transform: translateY(-1px); }
.bk-btn-primary   { background: var(--ink); color: #fff; }
.bk-btn-primary:hover { background: #2a2a4a; }
.bk-btn-gold      { background: var(--gold); color: var(--ink); }
.bk-btn-gold:hover { filter: brightness(1.06); }
.bk-btn-ghost     { background: transparent; color: var(--muted); border: 1.5px solid var(--border); }
.bk-btn-ghost:hover { border-color: var(--gold); color: var(--gold); }
.bk-btn-success   { background: #dcfce7; color: var(--success); border: 1.5px solid #86efac; }
.bk-btn-success:hover { background: #bbf7d0; }
.bk-btn-danger    { background: #fef2f2; color: var(--danger); border: 1.5px solid #fca5a5; }
.bk-btn-danger:hover { background: #fee2e2; }
.bk-btn-warning   { background: #fffbeb; color: var(--warning); border: 1.5px solid #fde68a; }
.bk-btn-warning:hover { background: #fef3c7; }
.bk-btn-info      { background: #eff6ff; color: var(--info); border: 1.5px solid #bfdbfe; }
.bk-btn-info:hover { background: #dbeafe; }
.bk-btn-approve   { background: var(--success); color: #fff; }
.bk-btn-approve:hover { background: #15803d; box-shadow: 0 4px 12px rgba(22,163,74,0.3); }

/* ── Table ── */
.bk-table-wrap { overflow-x: auto; }
.bk-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.bk-table thead th {
  padding: 12px 16px; font-size: 0.68rem; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--muted);
  border-bottom: 2px solid var(--border); text-align: left;
  font-weight: 700; background: #fdfcfa;
}
.bk-table tbody td {
  padding: 14px 16px; font-size: 0.84rem; color: var(--ink);
  border-bottom: 1px solid var(--border); vertical-align: middle;
}
.bk-table tbody tr:last-child td { border-bottom: none; }
.bk-table tbody tr { transition: background 0.15s; }
.bk-table tbody tr:hover { background: #fdfcfa; }

/* ── Cell styles ── */
.bk-name { font-weight: 700; color: var(--ink); }
.bk-meta { font-size: 0.74rem; color: var(--muted); margin-top: 2px; }
.bk-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.bk-inline { display: inline; margin: 0; }

/* ── Status badges ── */
.bk-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 11px; border-radius: 20px;
  font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em;
  white-space: nowrap;
}
.bk-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
.bk-badge-pending  { background: #fffbeb; color: #92400e; }
.bk-badge-pending::before  { background: #f59e0b; }
.bk-badge-approved { background: #f0fdf4; color: #166534; }
.bk-badge-approved::before { background: var(--success); }
.bk-badge-cancelled { background: #fef2f2; color: #991b1b; }
.bk-badge-cancelled::before { background: var(--danger); }

/* ── Empty state ── */
.bk-empty { padding: 52px 24px; text-align: center; }
.bk-empty-icon { font-size: 2.4rem; margin-bottom: 12px; opacity: 0.35; }
.bk-empty-text { font-size: 0.85rem; color: var(--muted); line-height: 1.7; }

/* ── Count badge ── */
.bk-count {
  background: var(--gold-dim); color: #a07c3a;
  font-size: 0.7rem; font-weight: 700;
  padding: 3px 10px; border-radius: 20px;
}

@media (max-width: 900px) { .bk-stats { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 580px) { .bk-stats { grid-template-columns: 1fr; } }
.bk-banner-approved { background: linear-gradient(135deg, #065f46 0%, #059669 100%); }
.bk-stat { border-top-color: var(--success); }
</style>

<div class="bk-wrap">

  <div class="bk-banner bk-banner-approved">
    <div class="bk-banner-text">
      <h1>รายการอนุมัติแล้ว</h1>
      <p>รายการที่ผ่านการอนุมัติเรียบร้อยแล้ว — สามารถย้ายกลับหรือยกเลิกได้</p>
    </div>
    <div class="bk-banner-links">
      <a href="admin_booking_list.php"    class="bk-banner-link">← รายการรออนุมัติ</a>
      <a href="admin_booking_archive.php" class="bk-banner-link">🗂 รายการย้อนหลัง</a>
    </div>
  </div>

  <?php if ( !== ''): ?>
    <div class="bk-alert <?= ==='error'?'bk-alert-error':'bk-alert-success' ?>">
      <?= ==='error'?'⚠':'✓' ?> <?= h() ?>
    </div>
  <?php endif; ?>

  <div class="bk-stats" style="grid-template-columns:1fr;">
    <div class="bk-stat">
      <div class="bk-stat-label">รายการอนุมัติแล้วทั้งหมด</div>
      <div class="bk-stat-value" style="color:var(--success)"><?=  ?></div>
      <div class="bk-stat-sub">รายการสถานะอนุมัติที่ยังใช้งาน</div>
    </div>
  </div>

  <div class="bk-card">
    <div class="bk-card-header">
      <div>
        <div class="bk-card-title">รายการอนุมัติแล้วทั้งหมด</div>
        <div class="bk-card-sub">จัดการรายการที่อนุมัติได้จากที่นี่</div>
      </div>
      <span class="bk-count"><?=  ?> รายการ</span>
    </div>

    <form method="GET" style="display:contents;">
      <div class="bk-search">
        <div class="bk-search-wrap">
          <input type="text" name="search" class="bk-search-input"
                 placeholder="ค้นหาชื่อ, เบอร์โทร, อีเมล, ห้องพัก..."
                 value="<?= h() ?>">
        </div>
        <button type="submit" class="bk-btn bk-btn-primary">ค้นหา</button>
        <?php if (): ?><a href="<?= h() ?>" class="bk-btn bk-btn-ghost">ล้าง</a><?php endif; ?>
      </div>
    </form>

    <div class="bk-table-wrap">
      <table class="bk-table">
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>ผู้จอง</th>
            <th>ติดต่อ</th>
            <th>ห้องพัก</th>
            <th>วันเข้า–ออก</th>
            <th>ผู้เข้าพัก</th>
            <th>สถานะ</th>
            <th>วันที่จอง</th>
            <th style="width:260px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ( && ->num_rows > 0): ?>
            <?php while ( = ->fetch_assoc()): ?>
              <tr>
                <td style="color:var(--muted);font-size:0.78rem;"><?= (int)['id'] ?></td>
                <td>
                  <div class="bk-name"><?= h(['full_name']) ?></div>
                  <div class="bk-meta">ห้อง id: <?= (int)['room_id'] ?></div>
                </td>
                <td>
                  <div><?= h(['phone']) ?></div>
                  <div class="bk-meta"><?= h(['email']) ?></div>
                </td>
                <td><?= h(['room_type']) ?></td>
                <td>
                  <div style="font-size:0.8rem;">เข้า <?= h(['checkin_date']) ?></div>
                  <div style="font-size:0.8rem;color:var(--muted);">ออก <?= h(['checkout_date']) ?></div>
                </td>
                <td><?= (int)['guests'] ?> คน</td>
                <td>
                  <span class="bk-badge <?= statusBadge(['booking_status']) ?>">
                    <?= statusLabel(['booking_status']) ?>
                  </span>
                </td>
                <td style="font-size:0.78rem;color:var(--muted);"><?= h(substr(['created_at'],0,16)) ?></td>
                <td>
                  <div class="bk-actions">
                    <form method="POST" class="bk-inline" onsubmit="return confirm('ย้ายกลับไปรออนุมัติ?')">
                      <input type="hidden" name="action" value="set_pending">
                      <input type="hidden" name="id" value="<?= (int)['id'] ?>">
                      <button class="bk-btn bk-btn-warning" style="font-size:0.75rem;padding:7px 12px;">↩ ย้ายกลับ</button>
                    </form>
                    <form method="POST" class="bk-inline" onsubmit="return confirm('ยกเลิกการอนุมัติ?')">
                      <input type="hidden" name="action" value="set_cancelled">
                      <input type="hidden" name="id" value="<?= (int)['id'] ?>">
                      <button class="bk-btn bk-btn-ghost" style="font-size:0.75rem;padding:7px 12px;">✕ ยกเลิก</button>
                    </form>
                    <form method="POST" class="bk-inline" onsubmit="return confirm('ยืนยันการลบ?')">
                      <input type="hidden" name="action" value="delete_booking">
                      <input type="hidden" name="id" value="<?= (int)['id'] ?>">
                      <button class="bk-btn bk-btn-danger" style="font-size:0.75rem;padding:7px 12px;">🗑 ลบ</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9">
              <div class="bk-empty">
                <div class="bk-empty-icon">✅</div>
                <div class="bk-empty-text">ยังไม่มีรายการที่อนุมัติแล้ว<br><?=  ? 'ลองเปลี่ยนคำค้นหา' : 'อนุมัติรายการได้จากหน้าข้อมูลการเข้าพัก' ?></div>
              </div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php ->close(); ->close(); include 'admin_layout_bottom.php'; ?>