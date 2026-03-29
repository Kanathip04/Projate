<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$currentPage = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        if ($action === 'approve_payment') {
            // คำนวณเลขคิววันนี้
            $today  = date('Y-m-d');
            $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(approved_at) = '$today' AND booking_status = 'approved'");
            $qno    = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;

            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status  = 'paid',
                    booking_status  = 'approved',
                    daily_queue_no  = ?,
                    paid_at         = IFNULL(paid_at, NOW()),
                    approved_at     = NOW()
                WHERE id = ?
            ");
            $st->bind_param("ii", $qno, $id);
            $st->execute();
            $st->close();

            header("Location: {$currentPage}?tab=approved&msg=" . urlencode("อนุมัติการชำระเงินเรียบร้อยแล้ว (คิว Q" . str_pad($qno,4,'0',STR_PAD_LEFT) . ")") . "&type=success");
            exit;
        }

        if ($action === 'reject_payment') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status='failed',
                    booking_status='rejected'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "ปฏิเสธการชำระเงินเรียบร้อยแล้ว";
            $message_type = "danger";
        }

        if ($action === 'approve_booking') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET booking_status='approved'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            header("Location: {$currentPage}?tab=approved&msg=" . urlencode("อนุมัติรายการเรียบร้อยแล้ว") . "&type=success");
            exit;
        }

        if ($action === 'reject_booking') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET booking_status='rejected'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "ปฏิเสธรายการเรียบร้อยแล้ว";
            $message_type = "danger";
        }

        if ($action === 'mark_pending_payment') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status='waiting_verify'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "เปลี่ยนสถานะเป็นรอตรวจสอบแล้ว";
        }

        if ($action === 'archive') {
            $st = $conn->prepare("UPDATE boat_bookings SET archived=1 WHERE id=?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "จัดเก็บรายการเรียบร้อยแล้ว";
        }

        if ($action === 'delete') {
            $st = $conn->prepare("DELETE FROM boat_bookings WHERE id=?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "ลบรายการเรียบร้อยแล้ว";
        }
    }

    header("Location: {$currentPage}?tab=" . urlencode($_GET['tab'] ?? ($_POST['tab'] ?? 'pending')) . "&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

$tab    = $_GET['tab'] ?? 'pending';
$search = trim($_GET['search'] ?? '');

$rs = $conn->query("
    SELECT 
        COUNT(*) t,
        SUM(booking_status='pending') p,
        SUM(booking_status='approved') a,
        SUM(booking_status='rejected') r,
        SUM(payment_status='unpaid') pu,
        SUM(payment_status='waiting_verify') pw,
        SUM(payment_status='paid') pp,
        SUM(payment_status='failed') pf
    FROM boat_bookings
    WHERE archived=0
");
$st_row = $rs->fetch_assoc();

$stat_total            = (int)($st_row['t'] ?? 0);
$stat_pending          = (int)($st_row['p'] ?? 0);
$stat_approved         = (int)($st_row['a'] ?? 0);
$stat_rejected         = (int)($st_row['r'] ?? 0);
$stat_payment_unpaid   = (int)($st_row['pu'] ?? 0);
$stat_payment_waiting  = (int)($st_row['pw'] ?? 0);
$stat_payment_paid     = (int)($st_row['pp'] ?? 0);
$stat_payment_failed   = (int)($st_row['pf'] ?? 0);

$where = "WHERE archived=0";

if ($tab === 'approved') {
    $where .= " AND booking_status='approved'";
} elseif ($tab === 'waiting_payment') {
    $where .= " AND payment_status='waiting_verify'";
} elseif ($tab === 'paid') {
    $where .= " AND payment_status='paid'";
} else {
    $where .= " AND booking_status IN ('pending','rejected','cancelled')";
}

$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (
        full_name LIKE ? 
        OR phone LIKE ? 
        OR email LIKE ? 
        OR queue_name LIKE ?
        OR booking_ref LIKE ?
    )";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like, $like];
    $types = "sssss";
}

$sql = "
    SELECT *
    FROM boat_bookings
    {$where}
    ORDER BY id DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = "จัดการการจองคิวพายเรือ";
$activeMenu = "boat_booking";
include 'admin_layout_top.php';
?>
<style>
:root{
    --gold:#c9a96e;
    --gold-dim:rgba(201,169,110,0.12);
    --ink:#1a1a2e;
    --bg:#f5f1eb;
    --card:#fff;
    --muted:#7a7a8c;
    --border:#e8e4de;
    --danger:#dc2626;
    --success:#16a34a;
    --warning:#d97706;
    --info:#1d6fad;
}
.bk-wrap{padding:0 0 48px;animation:bkUp .4s ease both;}
@keyframes bkUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.bk-banner{
    border-radius:18px;
    padding:26px 32px;
    margin-bottom:24px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
    position:relative;
    overflow:hidden;
    background:linear-gradient(135deg,var(--ink) 0%,#1a3a5c 100%);
}
.bk-banner::before{
    content:'';
    position:absolute;
    width:300px;
    height:300px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(29,111,173,0.12) 0%,transparent 70%);
    top:-100px;
    right:-60px;
    pointer-events:none;
}
.bk-banner h1{
    font-family:'Playfair Display',serif;
    font-style:italic;
    font-size:1.5rem;
    color:#fff;
    margin:0 0 5px;
}
.bk-banner p{
    font-size:.8rem;
    color:rgba(255,255,255,0.7);
    margin:0;
}
.stats-row{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:14px;
}
.stats-row-2{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:24px;
}
.stat-box{
    background:var(--card);
    border-radius:12px;
    padding:18px 20px;
    border-top:3px solid var(--gold);
    box-shadow:0 2px 10px rgba(26,26,46,.06);
}
.stat-box .num{
    font-size:1.8rem;
    font-weight:700;
    color:var(--ink);
    line-height:1;
}
.stat-box .lbl{
    font-size:.72rem;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.1em;
    margin-top:5px;
}
.tab-row{
    display:flex;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}
.tab-btn{
    padding:8px 20px;
    border-radius:8px;
    font-size:.82rem;
    font-weight:600;
    text-decoration:none;
    border:1.5px solid var(--border);
    color:var(--muted);
    background:var(--card);
    transition:all .2s;
}
.tab-btn.active,.tab-btn:hover{
    border-color:var(--ink);
    background:var(--ink);
    color:#fff;
}
.bk-badge-pending,
.bk-badge-approved,
.bk-badge-rejected,
.bk-badge-cancelled,
.pay-unpaid,
.pay-waiting,
.pay-paid,
.pay-failed{
    padding:4px 10px;
    border-radius:20px;
    font-size:.72rem;
    font-weight:700;
    display:inline-block;
}
.bk-badge-pending{background:#fef3c7;color:#92400e;}
.bk-badge-approved{background:#dcfce7;color:#166534;}
.bk-badge-rejected{background:#fee2e2;color:#991b1b;}
.bk-badge-cancelled{background:#f3f4f6;color:#374151;}

.pay-unpaid{background:#eef2f7;color:#475569;}
.pay-waiting{background:#ffe8cc;color:#9a6700;}
.pay-paid{background:#dcfce7;color:#166534;}
.pay-failed{background:#fee2e2;color:#991b1b;}

.boat-pills{display:flex;flex-wrap:wrap;gap:4px;}
.boat-pill{
    display:inline-flex;
    align-items:center;
    padding:2px 8px;
    border-radius:999px;
    background:rgba(29,111,173,.1);
    border:1px solid rgba(29,111,173,.25);
    color:#1d6fad;
    font-size:.7rem;
    font-weight:700;
}
.empty-row td{
    padding:40px;
    text-align:center;
    color:var(--muted);
    font-size:.9rem;
}
.slip-thumb{
    width:68px;
    height:68px;
    object-fit:cover;
    border-radius:10px;
    border:1px solid #ddd;
    background:#fff;
}
.td-small{
    font-size:.76rem;
    color:var(--muted);
}
@media (max-width: 1100px){
    .stats-row,.stats-row-2{grid-template-columns:repeat(2,1fr);}
}
@media (max-width: 640px){
    .stats-row,.stats-row-2{grid-template-columns:1fr;}
}
</style>

<div class="main">
<div class="bk-wrap">

    <div class="bk-banner">
        <div>
            <h1>🚣 จัดการการจองคิวพายเรือ</h1>
            <p>จัดการรายการจอง การชำระเงิน สลิป และสถานะการอนุมัติ</p>
        </div>
        <a href="admin_boat_queues.php" class="btn btn-accent btn-sm">+ จัดการคิวพายเรือ</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-box"><div class="num"><?= $stat_total ?></div><div class="lbl">รายการทั้งหมด</div></div>
        <div class="stat-box" style="border-top-color:#d97706"><div class="num"><?= $stat_pending ?></div><div class="lbl">รออนุมัติ</div></div>
        <div class="stat-box" style="border-top-color:#16a34a"><div class="num"><?= $stat_approved ?></div><div class="lbl">อนุมัติแล้ว</div></div>
        <div class="stat-box" style="border-top-color:#dc2626"><div class="num"><?= $stat_rejected ?></div><div class="lbl">ไม่อนุมัติ</div></div>
    </div>

    <div class="stats-row-2">
        <div class="stat-box" style="border-top-color:#64748b"><div class="num"><?= $stat_payment_unpaid ?></div><div class="lbl">ยังไม่ชำระ</div></div>
        <div class="stat-box" style="border-top-color:#d97706"><div class="num"><?= $stat_payment_waiting ?></div><div class="lbl">รอตรวจสอบสลิป</div></div>
        <div class="stat-box" style="border-top-color:#16a34a"><div class="num"><?= $stat_payment_paid ?></div><div class="lbl">ชำระแล้ว</div></div>
        <div class="stat-box" style="border-top-color:#dc2626"><div class="num"><?= $stat_payment_failed ?></div><div class="lbl">ชำระไม่ผ่าน</div></div>
    </div>

    <div class="tab-row">
        <a href="?tab=pending" class="tab-btn <?= $tab === 'pending' ? 'active' : '' ?>">📋 รออนุมัติ / ทั้งหมด</a>
        <a href="?tab=waiting_payment" class="tab-btn <?= $tab === 'waiting_payment' ? 'active' : '' ?>">🧾 รอตรวจสลิป</a>
        <a href="?tab=paid" class="tab-btn <?= $tab === 'paid' ? 'active' : '' ?>">💳 ชำระแล้ว</a>
        <a href="?tab=approved" class="tab-btn <?= $tab === 'approved' ? 'active' : '' ?>">✅ อนุมัติแล้ว</a>
    </div>

    <div class="lm-card">
        <div class="lm-card-header">
            <span class="lm-card-title">รายการจองคิวพายเรือ (<?= $result->num_rows ?>)</span>
            <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="tab" value="<?= h($tab) ?>">
                <div class="search-wrap">
                    <input type="text" name="search" placeholder="ค้นหาชื่อ / เบอร์ / คิว / booking ref..." value="<?= h($search) ?>">
                </div>
                <button class="btn btn-ghost btn-sm" type="submit">ค้นหา</button>
                <?php if ($search): ?>
                    <a href="?tab=<?= h($tab) ?>" class="btn btn-ghost btn-sm">ล้าง</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="overflow-x:auto;">
        <table class="lm-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ผู้จอง</th>
                    <th>เลขอ้างอิง</th>
                    <th>คิว / วันที่</th>
                    <th>เวลา</th>
                    <th>เรือที่จอง</th>
                    <th>ผู้ร่วม</th>
                    <th>ยอดชำระ</th>
                    <th>สลิป</th>
                    <th>สถานะจอง</th>
                    <th>สถานะจ่าย</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td class="empty-row" colspan="12">ไม่มีรายการที่ตรงกัน</td></tr>
            <?php endif; ?>

            <?php while ($row = $result->fetch_assoc()):
                $units = json_decode($row['boat_units'] ?? '[]', true) ?: [];

                $bookingStatusClass = [
                    'pending'   => 'bk-badge-pending',
                    'approved'  => 'bk-badge-approved',
                    'rejected'  => 'bk-badge-rejected',
                    'cancelled' => 'bk-badge-cancelled'
                ];
                $bookingStatusLabel = [
                    'pending'   => 'รออนุมัติ',
                    'approved'  => 'อนุมัติแล้ว',
                    'rejected'  => 'ไม่อนุมัติ',
                    'cancelled' => 'ยกเลิก'
                ];

                $paymentStatusClass = [
                    'unpaid'         => 'pay-unpaid',
                    'pending'        => 'pay-waiting',
                    'waiting_verify' => 'pay-waiting',
                    'paid'           => 'pay-paid',
                    'failed'         => 'pay-failed',
                    'expired'        => 'pay-failed'
                ];
                $paymentStatusLabel = [
                    'unpaid'         => 'ยังไม่ชำระ',
                    'pending'        => 'กำลังดำเนินการ',
                    'waiting_verify' => 'รอตรวจสอบ',
                    'paid'           => 'ชำระแล้ว',
                    'failed'         => 'ชำระไม่ผ่าน',
                    'expired'        => 'หมดอายุ'
                ];

                $bCls = $bookingStatusClass[$row['booking_status'] ?? 'pending'] ?? 'bk-badge-pending';
                $bLbl = $bookingStatusLabel[$row['booking_status'] ?? 'pending'] ?? ($row['booking_status'] ?? '-');

                $pKey = $row['payment_status'] ?? 'unpaid';
                $pCls = $paymentStatusClass[$pKey] ?? 'pay-unpaid';
                $pLbl = $paymentStatusLabel[$pKey] ?? $pKey;
            ?>
                <tr>
                    <td>
                        <strong>#<?= str_pad((int)$row['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                    </td>

                    <td>
                        <div style="font-weight:700;"><?= h($row['full_name']) ?></div>
                        <div class="td-small"><?= h($row['phone']) ?></div>
                        <?php if (!empty($row['email'])): ?>
                            <div class="td-small"><?= h($row['email']) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div style="font-weight:700;"><?= h($row['booking_ref'] ?? '-') ?></div>
                        <?php if (!empty($row['provider_txn_id'])): ?>
                            <div class="td-small">Txn: <?= h($row['provider_txn_id']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($row['payment_provider'])): ?>
                            <div class="td-small">Provider: <?= h($row['payment_provider']) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div style="font-weight:600;"><?= h($row['queue_name']) ?></div>
                        <div class="td-small">
                            <?= !empty($row['boat_date']) ? date('d/m/Y', strtotime($row['boat_date'])) : '-' ?>
                        </div>
                    </td>

                    <td class="td-small">
                        <?= !empty($row['time_start']) ? substr($row['time_start'],0,5) . '–' . substr($row['time_end'],0,5) : '-' ?>
                    </td>

                    <td>
                        <?php if (!empty($units)): ?>
                            <div class="boat-pills">
                                <?php foreach ($units as $u): ?>
                                    <span class="boat-pill">🚣<?= (int)$u ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?= h($row['boat_type'] ?? '-') ?>
                        <?php endif; ?>
                    </td>

                    <td><?= (int)($row['guests'] ?? 0) ?> คน</td>

                    <td>
                        <div style="font-weight:700;">฿<?= number_format((float)($row['total_amount'] ?? 0), 2) ?></div>
                        <?php if (isset($row['price_per_boat'])): ?>
                            <div class="td-small">ราคาต่อหน่วย: ฿<?= number_format((float)$row['price_per_boat'], 2) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (!empty($row['payment_slip'])): ?>
                            <a href="<?= h($row['payment_slip']) ?>" target="_blank">
                                <img src="<?= h($row['payment_slip']) ?>" alt="slip" class="slip-thumb">
                            </a>
                            <div class="td-small" style="margin-top:4px;">คลิกเพื่อดู</div>
                        <?php else: ?>
                            <span class="td-small">ไม่มีสลิป</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="<?= $bCls ?>"><?= h($bLbl) ?></span>
                        <?php if (!empty($row['approved_at'])): ?>
                            <div class="td-small" style="margin-top:4px;">
                                <?= date('d/m/Y H:i', strtotime($row['approved_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="<?= $pCls ?>"><?= h($pLbl) ?></span>
                        <?php if (!empty($row['paid_at'])): ?>
                            <div class="td-small" style="margin-top:4px;">
                                <?= date('d/m/Y H:i', strtotime($row['paid_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;min-width:220px;">

                            <?php if (($row['payment_status'] ?? 'unpaid') === 'waiting_verify'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                    <input type="hidden" name="action" value="approve_payment">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-accent btn-sm" type="submit" onclick="return confirm('ยืนยันการชำระเงินรายการนี้?')">✓ อนุมัติชำระ</button>
                                </form>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                    <input type="hidden" name="action" value="reject_payment">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('ปฏิเสธการชำระเงินรายการนี้?')">✗ ปฏิเสธชำระ</button>
                                </form>
                            <?php endif; ?>

                            <?php if (($row['booking_status'] ?? '') === 'pending' && ($row['payment_status'] ?? '') !== 'paid'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                    <input type="hidden" name="action" value="approve_booking">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('อนุมัติการจองนี้?')">อนุมัติจอง</button>
                                </form>
                            <?php endif; ?>

                            <?php if (($row['payment_status'] ?? '') === 'unpaid' && !empty($row['payment_slip'])): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                    <input type="hidden" name="action" value="mark_pending_payment">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('เปลี่ยนสถานะเป็นรอตรวจสอบสลิป?')">ตั้งเป็นรอตรวจสอบ</button>
                                </form>
                            <?php endif; ?>

                            <?php if (($row['booking_status'] ?? '') === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                    <input type="hidden" name="action" value="reject_booking">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('ปฏิเสธรายการนี้?')">ปฏิเสธจอง</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('จัดเก็บรายการนี้?')">🗂 เก็บ</button>
                            </form>

                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="tab" value="<?= h($tab) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('ลบรายการนี้ถาวร?')">🗑 ลบ</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>
</div>

<?php include 'admin_layout_bottom.php'; ?>
<?php
$stmt->close();
$conn->close();
?>