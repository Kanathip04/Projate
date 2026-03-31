<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);
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

// รายการอนุมัติแล้ว
$whereApproved = "WHERE payment_provider='cash' AND payment_status='cash_paid'";
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
:root{--gold:#c9a96e;--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;}
.tk-wrap{padding:0 0 48px;animation:tkUp .4s ease both;}
@keyframes tkUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.tk-banner{border-radius:18px;padding:26px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:linear-gradient(135deg,#1a3a2e 0%,#1a2e1a 50%,#1a1a2e 100%);position:relative;overflow:hidden;}
.tk-banner::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(255,255,255,.06);top:-80px;right:-50px;pointer-events:none;}
.tk-banner h1{font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 5px;}
.tk-banner p{font-size:.8rem;color:rgba(255,255,255,.7);margin:0;}
.tk-banner-links{display:flex;gap:10px;position:relative;z-index:1;}
.tk-banner-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:.76rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(255,255,255,.22);background:rgba(255,255,255,.1);color:#fff;transition:all .2s;}
.tk-banner-link:hover{background:rgba(255,255,255,.2);}
.tk-alert{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:22px;}
.tk-alert-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.tk-alert-danger{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}
.tk-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;}
.tk-stat{background:var(--card);border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(26,26,46,.06);border-top:3px solid var(--border);}
.tk-stat:nth-child(1){border-top-color:var(--gold);}
.tk-stat:nth-child(2){border-top-color:#f59e0b;}
.tk-stat:nth-child(3){border-top-color:var(--success);}
.tk-stat:nth-child(4){border-top-color:var(--danger);}
.tk-stat-label{font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:700;}
.tk-stat-value{font-size:1.9rem;font-weight:800;color:var(--ink);line-height:1;}
.tk-stat-sub{font-size:.71rem;color:var(--muted);margin-top:4px;}
.tk-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.06);overflow:hidden;}
.tk-card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.tk-card-title{font-size:.9rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
.tk-card-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--warning);border-radius:2px;}
.tk-card-title.approved-title::before{background:var(--success);}
.tk-count{background:#fffbeb;color:var(--warning);font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.tk-count-success{background:#f0fdf4;color:var(--success);font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.s-approved{background:#f0fdf4;color:#166534;border:1px solid #86efac;}
.tk-search{padding:14px 22px;border-bottom:1px solid var(--border);display:flex;gap:10px;background:#fdfcfa;}
.tk-search-wrap{position:relative;flex:1;}
.tk-search-wrap::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:.72rem;pointer-events:none;}
.tk-search-input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.84rem;outline:none;}
.tk-search-input:focus{border-color:var(--gold);}
.tk-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border:none;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.tk-btn-primary{background:var(--ink);color:#fff;}
.tk-btn-success{background:#f0fdf4;color:var(--success);border:1.5px solid #86efac;}
.tk-btn-success:hover{background:#dcfce7;}
.tk-btn-danger{background:#fef2f2;color:var(--danger);border:1.5px solid #fca5a5;}
.tk-btn-danger:hover{background:#fee2e2;}
.tk-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.tk-table{width:100%;border-collapse:collapse;}
.tk-table th{padding:11px 16px;font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1.5px solid var(--border);background:#fdfcfa;text-align:left;}
.tk-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.83rem;color:var(--ink);vertical-align:top;}
.tk-table tr:last-child td{border-bottom:none;}
.tk-table tr:hover td{background:#fdfcfa;}
.tk-empty{text-align:center;padding:50px 20px;color:var(--muted);font-size:.9rem;}
.bk-name{font-weight:700;font-size:.88rem;}
.bk-sub{font-size:.75rem;color:var(--muted);margin-top:2px;}
.ref-pill{display:inline-block;padding:2px 10px;border-radius:999px;background:#f1f5f9;border:1px solid #e2e8f0;font-size:.72rem;font-weight:700;font-family:monospace;color:#334155;}
.status-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:.74rem;font-weight:700;}
.s-pending{background:#fffbeb;color:#d97706;border:1px solid #fde68a;}
.s-failed{background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;}
.actions{display:flex;flex-direction:column;gap:6px;}
@media(max-width:768px){.tk-stats{grid-template-columns:repeat(2,1fr);}.tk-table{display:block;overflow-x:auto;}}
</style>

<div class="tk-wrap">
    <div class="tk-banner">
        <div style="position:relative;z-index:1;">
            <h1>💵 อนุมัติการจองเรือ (เงินสด)</h1>
            <p>รายการรอชำระเงินสด — ลูกค้านำใบจองมายื่นที่สำนักงาน</p>
        </div>
        <div class="tk-banner-links">
            <a href="admin_boat_bookings.php" class="tk-banner-link">🚣 รายการทั้งหมด</a>
            <a href="admin_boat_queues.php" class="tk-banner-link">🛶 จัดการคิว</a>
            <a href="admin_boat_archive_view.php" class="tk-banner-link">📦 จัดเก็บข้อมูล</a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="tk-alert tk-alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
        <?= $message_type === 'success' ? '✓' : '✗' ?> <?= h($message) ?>
    </div>
    <?php endif; ?>

    <div class="tk-stats">
        <div class="tk-stat">
            <div class="tk-stat-label">รายการทั้งหมด</div>
            <div class="tk-stat-value"><?= $stat_total ?></div>
            <div class="tk-stat-sub">เงินสดในระบบ</div>
        </div>
        <div class="tk-stat">
            <div class="tk-stat-label">รออนุมัติ</div>
            <div class="tk-stat-value" style="color:#d97706;"><?= $stat_pending ?></div>
            <div class="tk-stat-sub">รอแอดมินตรวจสอบ</div>
        </div>
        <div class="tk-stat">
            <div class="tk-stat-label">อนุมัติแล้ว</div>
            <div class="tk-stat-value" style="color:var(--success);"><?= $stat_approved ?></div>
            <div class="tk-stat-sub">ผ่านการอนุมัติ</div>
        </div>
        <div class="tk-stat">
            <div class="tk-stat-label">ปฏิเสธ</div>
            <div class="tk-stat-value" style="color:var(--danger);"><?= $stat_rejected ?></div>
            <div class="tk-stat-sub">ไม่ผ่านการอนุมัติ</div>
        </div>
    </div>

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
                        <th>#</th>
                        <th>ผู้จอง</th>
                        <th>หมายเลขจอง</th>
                        <th>เรือ / วันที่</th>
                        <th>จำนวน</th>
                        <th>ยอด</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="8" class="tk-empty">ไม่มีรายการรออนุมัติ</td></tr>
                <?php else: $rowNum = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.78rem;font-weight:700;"><?= $rowNum++ ?></td>
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
                            <div class="bk-sub" style="margin-top:4px;"><?= h($row['queue_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= h($row['boat_type'] ?? '-') ?></div>
                            <div class="bk-sub">📅 <?= h($row['boat_date'] ?? '-') ?></div>
                            <div class="bk-sub" style="font-size:.7rem;margin-top:2px;">จอง <?= h(date('d/m H:i', strtotime($row['created_at']))) ?></div>
                        </td>
                        <td><?= (int)$row['guests'] ?> คน</td>
                        <td style="font-weight:700;color:var(--success);">฿<?= number_format((float)$row['total_amount'], 0) ?></td>
                        <td>
                            <?php
                            $ps = $row['payment_status'];
                            if ($ps === 'cash_pending'):
                            ?><span class="status-pill s-pending">💵 รอชำระสด</span>
                            <?php elseif ($ps === 'failed'): ?>
                            <span class="status-pill s-failed">✗ ปฏิเสธ</span>
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
                                    <button class="tk-btn tk-btn-danger" style="width:100%;font-size:.74rem;">🗑 ลบ</button>
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

    <!-- ── รายการอนุมัติแล้ว ── -->
    <div class="tk-card" style="margin-top:24px;">
        <div class="tk-card-header">
            <div class="tk-card-title approved-title">
                ✅ รายการอนุมัติแล้ว
                <span class="tk-count-success"><?= $resultA->num_rows ?> รายการ</span>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table class="tk-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ผู้จอง</th>
                        <th>หมายเลขจอง</th>
                        <th>เรือ / วันที่</th>
                        <th>จำนวน</th>
                        <th>ยอด</th>
                        <th>สถานะ</th>
                        <th>วันที่อนุมัติ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($resultA->num_rows === 0): ?>
                    <tr><td colspan="8" class="tk-empty">ยังไม่มีรายการอนุมัติ</td></tr>
                <?php else: $rowNumA = 1; while ($rowA = $resultA->fetch_assoc()): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:.78rem;font-weight:700;"><?= $rowNumA++ ?></td>
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
                            <div class="bk-sub" style="margin-top:4px;"><?= h($rowA['queue_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= h($rowA['boat_type'] ?? '-') ?></div>
                            <div class="bk-sub">📅 <?= h($rowA['boat_date'] ?? '-') ?></div>
                        </td>
                        <td><?= (int)$rowA['guests'] ?> คน</td>
                        <td style="font-weight:700;color:var(--success);">฿<?= number_format((float)$rowA['total_amount'], 0) ?></td>
                        <td><span class="status-pill s-approved">✓ อนุมัติแล้ว</span></td>
                        <td style="font-size:.76rem;color:var(--muted);"><?= h(substr($rowA['approved_at'] ?? $rowA['created_at'], 0, 16)) ?></td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $stmtA->close(); include 'admin_layout_bottom.php'; ?>
