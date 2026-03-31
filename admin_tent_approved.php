<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

// เพิ่มคอลัมน์ archived ถ้ายังไม่มี
$conn->query("ALTER TABLE equipment_bookings ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0");

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$currentPage = basename($_SERVER['PHP_SELF']);
$message = ''; $message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'archive_all') {
        // จัดเก็บทุกรายการที่ payment_status='paid' และยังไม่ถูก archive
        $conn->query("UPDATE equipment_bookings SET archived=1 WHERE payment_status='paid' AND (archived IS NULL OR archived=0)");
        header("Location: manage_tent_stock.php?msg=" . urlencode("จัดเก็บข้อมูลเรียบร้อยแล้ว") . "&type=success"); exit;
    }
    if ($action === 'cancel' && $id > 0) {
        $st = $conn->prepare("UPDATE equipment_bookings SET payment_status='failed', booking_status='pending' WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "ยกเลิกรายการเรียบร้อยแล้ว";
    }
    if ($action === 'delete' && $id > 0) {
        $st = $conn->prepare("DELETE FROM equipment_bookings WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $message = "ลบรายการเรียบร้อยแล้ว";
    }
    header("Location: {$currentPage}?msg=" . urlencode($message) . "&type=" . urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }
$search = trim($_GET['search'] ?? '');

$where = "WHERE payment_status='paid' AND (archived IS NULL OR archived=0)";
$params = []; $types = "";
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $like = "%{$search}%"; $params = [$like,$like,$like]; $types = "sss";
}
$stmt = $conn->prepare("SELECT * FROM equipment_bookings {$where} ORDER BY id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();

$pageTitle = "รายการอนุมัติอุปกรณ์"; $activeMenu = "tent_approved";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,0.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;}
.tk-wrap{padding:0 0 48px;}
.tk-banner{border-radius:18px;padding:26px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:linear-gradient(135deg,#15803d 0%,#166534 100%);position:relative;overflow:hidden;}
.tk-banner::before{content:'';position:absolute;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.06);top:-80px;right:-50px;pointer-events:none;}
.tk-banner h1{font-family:'Playfair Display',serif;font-style:italic;font-size:1.5rem;color:#fff;margin:0 0 4px;}
.tk-banner p{font-size:.8rem;color:rgba(255,255,255,.75);margin:0;}
.tk-banner-links{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1;}
.tk-banner-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:.76rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);color:#fff;transition:all .2s;}
.tk-banner-link:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.tk-alert{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:22px;}
.tk-alert-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.tk-alert-error{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}
.tk-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.06);overflow:hidden;}
.tk-card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.tk-card-title{font-size:.9rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
.tk-card-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--success);border-radius:2px;}
.tk-count{background:#f0fdf4;color:var(--success);font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.tk-search{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:#fdfcfa;}
.tk-search-wrap{position:relative;flex:1;min-width:180px;}
.tk-search-wrap::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:.72rem;pointer-events:none;}
.tk-search-input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.84rem;color:var(--ink);background:#fff;outline:none;}
.tk-search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.tk-btn{display:inline-flex;align-items:center;gap:5px;padding:9px 16px;border:none;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.tk-btn:hover{transform:translateY(-1px);}
.tk-btn-primary{background:var(--ink);color:#fff;}
.tk-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.tk-btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.tk-btn-success{background:#f0fdf4;color:var(--success);border:1.5px solid #86efac;}
.tk-btn-success:hover{background:#dcfce7;}
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
.badge-approved{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.69rem;font-weight:700;background:#f0fdf4;color:#166534;}
.badge-approved::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--success);}
.tk-empty{padding:48px 24px;text-align:center;}
.tk-empty-icon{font-size:2.2rem;margin-bottom:10px;opacity:.35;}
.tk-empty-text{font-size:.83rem;color:var(--muted);line-height:1.7;}
.item-pills{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px;}
.item-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:.65rem;font-weight:700;background:rgba(21,128,61,.1);border:1px solid rgba(21,128,61,.28);color:#15803d;}
</style>

<div class="tk-wrap">
    <div class="tk-banner">
        <div>
            <h1>✅ รายการชำระแล้ว (อุปกรณ์)</h1>
            <p>รายการเช่าอุปกรณ์ที่ชำระเงินเรียบร้อยแล้ว</p>
        </div>
        <div class="tk-banner-links">
            <a href="admin_tent_list.php" class="tk-banner-link">📋 รายการรออนุมัติ</a>
            <a href="manage_tents.php" class="tk-banner-link">🏕 จัดการเต็นท์</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันจัดเก็บข้อมูลที่ชำระแล้วทั้งหมด?')">
                <input type="hidden" name="action" value="archive_all">
                <button type="submit" class="tk-banner-link" style="cursor:pointer;border:1.5px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);">📦 จัดเก็บข้อมูล</button>
            </form>
            <a href="manage_tent_stock.php" class="tk-banner-link">🗂 ดูข้อมูลจัดเก็บ</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="tk-alert <?= $message_type==='error' ? 'tk-alert-error' : 'tk-alert-success' ?>">
            <?= $message_type==='error' ? '⚠' : '✓' ?> <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="tk-card">
        <div class="tk-card-header">
            <div>
                <div class="tk-card-title">รายการชำระแล้ว</div>
            </div>
            <span class="tk-count"><?= $result->num_rows ?> รายการ</span>
        </div>

        <form method="GET">
            <div class="tk-search">
                <div class="tk-search-wrap">
                    <input type="text" name="search" class="tk-search-input"
                           placeholder="ค้นหาชื่อ, เบอร์โทร, อีเมล..."
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
                        <th>เลขที่บิล</th>
                        <th>ผู้จอง</th>
                        <th>ติดต่อ</th>
                        <th>อุปกรณ์ / ราคา</th>
                        <th>วันเข้า–ออก</th>
                        <th>สถานะ</th>
                        <th>วันที่จอง</th>
                        <th style="width:220px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                            <?php
                            // เลขที่บิล
                            $_bkDate = date('Ymd', strtotime($row['created_at']));
                            $_seqR   = $conn->query("SELECT COUNT(*) AS seq FROM equipment_bookings WHERE DATE(created_at) = DATE('" . $conn->real_escape_string($row['created_at']) . "') AND id <= " . (int)$row['id']);
                            $_seq    = (int)($_seqR ? $_seqR->fetch_assoc()['seq'] : 1);
                            $_billRef = 'EQUIP-' . $_bkDate . '-' . str_pad($_seq, 3, '0', STR_PAD_LEFT);

                            // อุปกรณ์
                            $items = [];
                            if (!empty($row['items_json'])) {
                                $decoded = json_decode($row['items_json'], true);
                                if (is_array($decoded)) {
                                    foreach ($decoded as $item) {
                                        $name = $item['name'] ?? ($item['tent_name'] ?? '');
                                        $qty  = (int)($item['qty'] ?? ($item['quantity'] ?? 1));
                                        $unit = $item['unit'] ?? 'ชิ้น';
                                        if ($name) $items[] = h($name) . ' &times;' . $qty . ' ' . h($unit);
                                    }
                                }
                            }

                            // คืน
                            $nights = 1;
                            if (!empty($row['checkin_date']) && !empty($row['checkout_date'])) {
                                $d1 = new DateTime($row['checkin_date']);
                                $d2 = new DateTime($row['checkout_date']);
                                $nights = max(1, (int)$d1->diff($d2)->days);
                            }
                            ?>
                            <tr>
                                <td style="color:var(--muted);font-size:.76rem;font-weight:700;"><?= $no++ ?></td>
                                <td style="font-size:.78rem;font-weight:700;color:#15803d;white-space:nowrap;"><?= h($_billRef) ?></td>
                                <td>
                                    <div class="tk-name"><?= h($row['full_name']) ?></div>
                                </td>
                                <td>
                                    <div><?= h($row['phone']) ?></div>
                                    <div class="tk-meta"><?= h($row['email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($items)): ?>
                                        <div class="item-pills">
                                            <?php foreach ($items as $itemStr): ?>
                                                <span class="item-pill">🎒 <?= $itemStr ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--muted);font-size:.75rem;">-</span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['total_price'])): ?>
                                        <div class="tk-meta">฿ <?= number_format((float)$row['total_price'], 2) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:.79rem;">📅 <?= h($row['checkin_date']) ?></div>
                                    <div style="font-size:.79rem;color:var(--muted);">→ <?= h($row['checkout_date']) ?> (<?= $nights ?> คืน)</div>
                                </td>
                                <td><span class="badge-approved">ชำระแล้ว</span></td>
                                <td style="font-size:.76rem;color:var(--muted);"><?= h(substr($row['created_at'],0,16)) ?></td>
                                <td>
                                    <div class="tk-actions">
                                        <a href="equipment_ticket.php?id=<?= (int)$row['id'] ?>" class="tk-btn tk-btn-success" style="padding:6px 11px;font-size:.74rem;" target="_blank">🎫 สลิป</a>
                                        <a href="equipment_bill.php?id=<?= (int)$row['id'] ?>" class="tk-btn tk-btn-ghost" style="padding:6px 11px;font-size:.74rem;" target="_blank">🧾 บิล</a>
                                        <form method="POST" class="tk-inline" onsubmit="return confirm('ยืนยันยกเลิกรายการนี้?')">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button class="tk-btn tk-btn-warning" style="padding:6px 11px;font-size:.74rem;">✗ ยกเลิก</button>
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
                        <tr><td colspan="9">
                            <div class="tk-empty">
                                <div class="tk-empty-icon">✅</div>
                                <div class="tk-empty-text">
                                    <?= $search ? 'ไม่พบรายการที่ตรงกับ "'.h($search).'"' : 'ยังไม่มีรายการชำระแล้ว' ?>
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
