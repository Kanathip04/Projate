<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$currentPage = basename($_SERVER['PHP_SELF']);
$message = ''; $message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'approve' && $id > 0) {
        $conn->prepare("UPDATE tent_bookings SET booking_status='approved' WHERE id=?")->execute_simple($id);
        $st = $conn->prepare("UPDATE tent_bookings SET booking_status='approved' WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        header("Location: admin_tent_approved.php?msg=" . urlencode("อนุมัติรายการเรียบร้อยแล้ว") . "&type=success"); exit;
    }
    if ($action === 'reject' && $id > 0) {
        $st = $conn->prepare("UPDATE tent_bookings SET booking_status='rejected' WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "ปฏิเสธรายการเรียบร้อยแล้ว";
    }
    if ($action === 'delete' && $id > 0) {
        $st = $conn->prepare("DELETE FROM tent_bookings WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "ลบรายการเรียบร้อยแล้ว";
    }
    if ($action === 'archive' && $id > 0) {
        $st = $conn->prepare("UPDATE tent_bookings SET archived=1 WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "จัดเก็บรายการเรียบร้อยแล้ว";
    }
    header("Location: {$currentPage}?msg=" . urlencode($message) . "&type=" . urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }
$search = trim($_GET['search'] ?? '');

$rs = $conn->query("SELECT COUNT(*) t, SUM(booking_status='pending') p, SUM(booking_status='approved') a, SUM(booking_status='rejected') r FROM tent_bookings WHERE archived=0");
$st_row = $rs->fetch_assoc();
$stat_total    = (int)$st_row['t'];
$stat_pending  = (int)$st_row['p'];
$stat_approved = (int)$st_row['a'];
$stat_rejected = (int)$st_row['r'];

$where = "WHERE archived=0 AND booking_status IN ('pending','rejected','cancelled')";
$params = []; $types = "";
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR tent_type LIKE ?)";
    $like = "%{$search}%"; $params = [$like,$like,$like,$like]; $types = "ssss";
}
$stmt = $conn->prepare("SELECT * FROM tent_bookings {$where} ORDER BY id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();

$pageTitle = "จัดการการจองเต็นท์"; $activeMenu = "tent_booking";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,0.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;}
.tk-wrap{padding:0 0 48px;animation:tkUp .4s ease both;}
@keyframes tkUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.tk-banner{border-radius:18px;padding:26px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--ink) 0%,#2a2a4a 100%);}
.tk-banner::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,0.08) 0%,transparent 70%);top:-100px;right:-60px;pointer-events:none;}
.tk-banner h1{font-family:'Playfair Display',serif;font-style:italic;font-size:1.5rem;color:#fff;margin:0 0 5px;}
.tk-banner p{font-size:.8rem;color:rgba(255,255,255,0.7);margin:0;}
.tk-banner-links{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1;}
.tk-banner-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:.76rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(255,255,255,.22);background:rgba(255,255,255,.1);color:#fff;transition:all .2s;}
.tk-banner-link:hover{background:rgba(255,255,255,.2);transform:translateY(-1px);}
.tk-alert{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:22px;}
.tk-alert-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.tk-alert-error{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}
.tk-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;}
.tk-stat{background:var(--card);border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(26,26,46,.06);border-top:3px solid var(--border);transition:transform .2s,box-shadow .2s;}
.tk-stat:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(26,26,46,.1);}
.tk-stat:nth-child(1){border-top-color:var(--gold);}
.tk-stat:nth-child(2){border-top-color:#f59e0b;}
.tk-stat:nth-child(3){border-top-color:var(--success);}
.tk-stat:nth-child(4){border-top-color:var(--danger);}
.tk-stat-label{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:700;}
.tk-stat-value{font-size:1.9rem;font-weight:800;color:var(--ink);line-height:1;}
.tk-stat-sub{font-size:.71rem;color:var(--muted);margin-top:4px;}
.tk-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.06);overflow:hidden;}
.tk-card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.tk-card-title{font-size:.9rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
.tk-card-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--gold);border-radius:2px;}
.tk-card-sub{font-size:.74rem;color:var(--muted);}
.tk-count{background:var(--gold-dim);color:#a07c3a;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.tk-search{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:#fdfcfa;}
.tk-search-wrap{position:relative;flex:1;min-width:180px;}
.tk-search-wrap::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:.72rem;pointer-events:none;}
.tk-search-input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.84rem;color:var(--ink);background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;}
.tk-search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.tk-btn{display:inline-flex;align-items:center;gap:5px;padding:9px 16px;border:none;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;letter-spacing:.03em;white-space:nowrap;}
.tk-btn:hover{transform:translateY(-1px);}
.tk-btn-primary{background:var(--ink);color:#fff;}
.tk-btn-primary:hover{background:#2a2a4a;}
.tk-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.tk-btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.tk-btn-approve{background:var(--success);color:#fff;}
.tk-btn-approve:hover{background:#15803d;}
.tk-btn-warning{background:#fffbeb;color:var(--warning);border:1.5px solid #fde68a;}
.tk-btn-warning:hover{background:#fef3c7;}
.tk-btn-danger{background:#fef2f2;color:var(--danger);border:1.5px solid #fca5a5;}
.tk-btn-danger:hover{background:#fee2e2;}
.tk-table-wrap{overflow-x:auto;}
.tk-table{width:100%;border-collapse:collapse;min-width:860px;}
.tk-table thead th{padding:11px 14px;font-size:.67rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);border-bottom:2px solid var(--border);text-align:left;font-weight:700;background:#fdfcfa;}
.tk-table tbody td{padding:13px 14px;font-size:.83rem;color:var(--ink);border-bottom:1px solid var(--border);vertical-align:middle;}
.tk-table tbody tr:last-child td{border-bottom:none;}
.tk-table tbody tr:hover{background:#fdfcfa;}
.tk-name{font-weight:700;}
.tk-meta{font-size:.73rem;color:var(--muted);margin-top:2px;}
.tk-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.tk-inline{display:inline;margin:0;}
.tk-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.69rem;font-weight:700;white-space:nowrap;}
.tk-badge::before{content:'';width:5px;height:5px;border-radius:50%;}
.tk-badge-pending{background:#fffbeb;color:#92400e;}.tk-badge-pending::before{background:#f59e0b;}
.tk-badge-approved{background:#f0fdf4;color:#166534;}.tk-badge-approved::before{background:var(--success);}
.tk-badge-rejected{background:#fef2f2;color:#991b1b;}.tk-badge-rejected::before{background:var(--danger);}
.tk-badge-cancelled{background:#f3f4f6;color:#374151;}.tk-badge-cancelled::before{background:#9ca3af;}
.tk-empty{padding:48px 24px;text-align:center;}
.tk-empty-icon{font-size:2.2rem;margin-bottom:10px;opacity:.35;}
.tk-empty-text{font-size:.83rem;color:var(--muted);line-height:1.7;}
@media(max-width:900px){.tk-stats{grid-template-columns:repeat(2,1fr);}}
@media(max-width:560px){.tk-stats{grid-template-columns:1fr;}}
</style>

<div class="tk-wrap">
    <div class="tk-banner">
        <div>
            <h1>⛺ การจองเต็นท์</h1>
            <p>รายการรออนุมัติ — ตรวจสอบและจัดการได้จากหน้านี้</p>
        </div>
        <div class="tk-banner-links">
            <a href="admin_tent_approved.php" class="tk-banner-link">✅ รายการอนุมัติแล้ว</a>
            <a href="manage_tents.php" class="tk-banner-link">🏕 จัดการเต็นท์</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="tk-alert <?= $message_type==='error' ? 'tk-alert-error' : 'tk-alert-success' ?>">
            <?= $message_type==='error' ? '⚠' : '✓' ?> <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="tk-stats">
        <div class="tk-stat">
            <div class="tk-stat-label">รายการทั้งหมด</div>
            <div class="tk-stat-value"><?= $stat_total ?></div>
            <div class="tk-stat-sub">ไม่รวมที่จัดเก็บแล้ว</div>
        </div>
        <div class="tk-stat">
            <div class="tk-stat-label">รออนุมัติ</div>
            <div class="tk-stat-value" style="color:#d97706"><?= $stat_pending ?></div>
            <div class="tk-stat-sub">รอแอดมินอนุมัติ</div>
        </div>
        <div class="tk-stat">
            <div class="tk-stat-label">อนุมัติแล้ว</div>
            <div class="tk-stat-value" style="color:#16a34a"><?= $stat_approved ?></div>
            <div class="tk-stat-sub">ผ่านการอนุมัติ</div>
        </div>
        <div class="tk-stat">
            <div class="tk-stat-label">ไม่อนุมัติ</div>
            <div class="tk-stat-value" style="color:#dc2626"><?= $stat_rejected ?></div>
            <div class="tk-stat-sub">รายการที่ปฏิเสธ</div>
        </div>
    </div>

    <div class="tk-card">
        <div class="tk-card-header">
            <div>
                <div class="tk-card-title">รายการที่ต้องจัดการ</div>
                <div class="tk-card-sub">แสดงเฉพาะรายการรออนุมัติ / ปฏิเสธ / ยกเลิก</div>
            </div>
            <span class="tk-count"><?= $result->num_rows ?> รายการ</span>
        </div>

        <form method="GET">
            <div class="tk-search">
                <div class="tk-search-wrap">
                    <input type="text" name="search" class="tk-search-input"
                           placeholder="ค้นหาชื่อ, เบอร์โทร, อีเมล, ประเภทเต็นท์..."
                           value="<?= h($search) ?>">
                </div>
                <button type="submit" class="tk-btn tk-btn-primary">ค้นหา</button>
                <?php if ($search): ?>
                    <a href="<?= h($currentPage) ?>" class="tk-btn tk-btn-ghost">ล้าง</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="tk-table-wrap">
            <table class="tk-table">
                <thead>
                    <tr>
                        <th style="width:46px;">#</th>
                        <th>ผู้จอง</th>
                        <th>ติดต่อ</th>
                        <th>เต็นท์</th>
                        <th>วันเข้า–ออก</th>
                        <th>สถานะ</th>
                        <th>วันที่จอง</th>
                        <th style="width:230px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                $badgeMap = ['pending'=>'tk-badge-pending','approved'=>'tk-badge-approved','rejected'=>'tk-badge-rejected','cancelled'=>'tk-badge-cancelled'];
                                $labelMap = ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิก'];
                                $st = $row['booking_status'] ?? 'pending';
                            ?>
                            <tr>
                                <td style="color:var(--muted);font-size:.76rem;"><?= (int)$row['id'] ?></td>
                                <td>
                                    <div class="tk-name"><?= h($row['full_name']) ?></div>
                                    <div class="tk-meta"><?= (int)$row['guests'] ?> คน · <?= h($row['tent_type']) ?></div>
                                </td>
                                <td>
                                    <div><?= h($row['phone']) ?></div>
                                    <div class="tk-meta"><?= h($row['email']) ?></div>
                                </td>
                                <td><?= h($row['tent_type']) ?></td>
                                <td>
                                    <div style="font-size:.79rem;">📅 <?= h($row['checkin_date']) ?></div>
                                    <div style="font-size:.79rem;color:var(--muted);">→ <?= h($row['checkout_date']) ?></div>
                                </td>
                                <td>
                                    <span class="tk-badge <?= $badgeMap[$st] ?? 'tk-badge-pending' ?>">
                                        <?= $labelMap[$st] ?? $st ?>
                                    </span>
                                </td>
                                <td style="font-size:.76rem;color:var(--muted);"><?= h(substr($row['created_at'],0,16)) ?></td>
                                <td>
                                    <div class="tk-actions">
                                        <?php if ($st === 'pending'): ?>
                                            <form method="POST" class="tk-inline" onsubmit="return confirm('ยืนยันอนุมัติรายการนี้?')">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button class="tk-btn tk-btn-approve" style="padding:6px 11px;font-size:.74rem;">✓ อนุมัติ</button>
                                            </form>
                                            <form method="POST" class="tk-inline" onsubmit="return confirm('ยืนยันปฏิเสธรายการนี้?')">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button class="tk-btn tk-btn-danger" style="padding:6px 11px;font-size:.74rem;">✗ ปฏิเสธ</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="tk-inline" onsubmit="return confirm('จัดเก็บรายการนี้?')">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button class="tk-btn tk-btn-warning" style="padding:6px 11px;font-size:.74rem;">📦 จัดเก็บ</button>
                                        </form>
                                        <form method="POST" class="tk-inline" onsubmit="return confirm('ยืนยันการลบ?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button class="tk-btn tk-btn-danger" style="padding:6px 11px;font-size:.74rem;">🗑 ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8">
                            <div class="tk-empty">
                                <div class="tk-empty-icon">⛺</div>
                                <div class="tk-empty-text">
                                    <?= $search ? 'ไม่พบรายการที่ตรงกับ "'.h($search).'"' : 'ไม่พบรายการที่ต้องจัดการ' ?>
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
