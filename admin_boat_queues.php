<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ── สร้างตารางถ้ายังไม่มี ── */
$conn->query("CREATE TABLE IF NOT EXISTS `boat_queues` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_name` VARCHAR(200) NOT NULL,
    `queue_date` DATE NOT NULL,
    `time_start` TIME NOT NULL,
    `time_end` TIME NOT NULL,
    `total_boats` INT DEFAULT 5,
    `price_per_boat` DECIMAL(10,2) DEFAULT 0,
    `description` TEXT,
    `image_path` VARCHAR(500) DEFAULT '',
    `boat_types` VARCHAR(500) DEFAULT 'เรือพาย,เรือคายัค,เรือบด',
    `status` ENUM('show','hide') DEFAULT 'show',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE boat_queues ADD COLUMN IF NOT EXISTS `boat_types` VARCHAR(500) DEFAULT 'เรือพาย,เรือคายัค,เรือบด' AFTER `image_path`");

$currentPage = basename($_SERVER['PHP_SELF']);
$message = ''; $message_type = 'success';

/* ═══════════════════════ POST ACTIONS ═══════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── เพิ่ม / แก้ไขคิว ── */
    if (in_array($action, ['add', 'edit'])) {
        $queue_name    = trim($_POST['queue_name']    ?? '');
        $queue_date    = trim($_POST['queue_date']    ?? '');
        $time_start    = '00:00:00';
        $time_end      = '00:00:00';
        $total_boats   = 5;
        $price_per_boat= max(0, (float)($_POST['price_per_boat'] ?? 0));
        $description   = trim($_POST['description']  ?? '');
        $boat_types    = trim($_POST['boat_types']   ?? 'เรือพาย,เรือคายัค,เรือบด');
        $status        = in_array($_POST['status'] ?? '', ['show','hide']) ? $_POST['status'] : 'show';
        $image_path    = trim($_POST['image_path_current'] ?? '');

        /* อัพโหลดรูป */
        if (!empty($_FILES['image_file']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $allowed)) {
                $dir = 'uploads/boat_queues/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'queue_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dir . $fname)) {
                    $image_path = $dir . $fname;
                }
            }
        }

        if ($action === 'add') {
            $st = $conn->prepare("INSERT INTO boat_queues (queue_name,queue_date,time_start,time_end,total_boats,price_per_boat,description,boat_types,image_path,status) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $st->bind_param("ssssiidsss", $queue_name,$queue_date,$time_start,$time_end,$total_boats,$price_per_boat,$description,$boat_types,$image_path,$status);
            $st->execute(); $st->close();
            $message = "เพิ่มคิวพายเรือเรียบร้อยแล้ว";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $st = $conn->prepare("UPDATE boat_queues SET queue_name=?,queue_date=?,time_start=?,time_end=?,total_boats=?,price_per_boat=?,description=?,boat_types=?,image_path=?,status=? WHERE id=?");
            $st->bind_param("ssssiidsssi", $queue_name,$queue_date,$time_start,$time_end,$total_boats,$price_per_boat,$description,$boat_types,$image_path,$status,$id);
            $st->execute(); $st->close();
            $message = "แก้ไขคิวพายเรือเรียบร้อยแล้ว";
        }
    }

    /* ── ลบคิว ── */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("DELETE FROM boat_queues WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            $message = "ลบคิวเรียบร้อยแล้ว";
        }
    }

    /* ── เปลี่ยนสถานะ (show/hide) ── */
    if ($action === 'toggle') {
        $id  = (int)($_POST['id']  ?? 0);
        $cur = trim($_POST['cur_status'] ?? 'show');
        $new = ($cur === 'show') ? 'hide' : 'show';
        if ($id > 0) {
            $st = $conn->prepare("UPDATE boat_queues SET status=? WHERE id=?");
            $st->bind_param("si", $new, $id); $st->execute(); $st->close();
            $message = "เปลี่ยนสถานะเรียบร้อยแล้ว";
        }
    }

    header("Location: {$currentPage}?msg=" . urlencode($message) . "&type=" . urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }

/* ── โหมดแก้ไข ── */
$editQueue = null;
if (isset($_GET['edit_id'])) {
    $eid = (int)$_GET['edit_id'];
    $es  = $conn->prepare("SELECT * FROM boat_queues WHERE id=? LIMIT 1");
    $es->bind_param("i", $eid); $es->execute();
    $editQueue = $es->get_result()->fetch_assoc();
    $es->close();
}

$search = trim($_GET['search'] ?? '');
$where  = "WHERE 1=1";
$params = []; $types = "";
if ($search !== '') {
    $where .= " AND (queue_name LIKE ? OR description LIKE ?)";
    $like = "%{$search}%"; $params = [$like,$like]; $types = "ss";
}
$stmt = $conn->prepare("SELECT q.*, (SELECT COUNT(*) FROM boat_bookings b WHERE b.queue_id=q.id AND b.booking_status IN ('pending','approved')) AS booking_count FROM boat_queues q {$where} ORDER BY q.queue_date DESC, q.time_start ASC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();

$pageTitle = "จัดการคิวพายเรือ"; $activeMenu = "boat_queue";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,0.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;}
.bq-wrap{padding:0 0 48px;animation:bqUp .4s ease both;}
@keyframes bqUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.bq-banner{border-radius:18px;padding:26px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:linear-gradient(135deg,var(--ink) 0%,#1a3a5c 100%);position:relative;overflow:hidden;}
.bq-banner::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(29,111,173,0.12) 0%,transparent 70%);top:-100px;right:-60px;pointer-events:none;}
.bq-banner h1{font-family:'Playfair Display',serif;font-style:italic;font-size:1.5rem;color:#fff;margin:0 0 5px;}
.bq-banner p{font-size:.8rem;color:rgba(255,255,255,0.7);margin:0;}
.form-card{background:var(--card);border-radius:14px;box-shadow:0 2px 12px rgba(26,26,46,.06);padding:24px 28px;margin-bottom:24px;border-top:3px solid var(--gold);}
.form-card h2{font-size:1rem;font-weight:700;margin-bottom:20px;color:var(--ink);}
.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.form-grid .full{grid-column:1/-1;}
.form-grid .half{grid-column:span 2;}
.fg{display:flex;flex-direction:column;gap:6px;}
.fg label{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;}
.fg input,.fg textarea,.fg select{font-family:'Sarabun',sans-serif;font-size:.88rem;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--ink);background:#fafaf8;outline:none;transition:border-color .2s;}
.fg input:focus,.fg textarea:focus,.fg select:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.fg textarea{min-height:80px;resize:vertical;}
.img-preview{max-width:140px;max-height:90px;border-radius:8px;margin-top:6px;border:1px solid var(--border);display:none;}
.form-actions{display:flex;gap:10px;margin-top:20px;}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px;}
.status-show{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.status-hide{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.queue-img-cell{width:70px;height:50px;object-fit:cover;border-radius:6px;background:#e5e7eb;}
.empty-row td{padding:40px;text-align:center;color:var(--muted);font-size:.9rem;}
</style>

<div class="main">
<div class="bq-wrap">

    <div class="bq-banner">
        <div>
            <h1>🚣 จัดการคิวพายเรือ</h1>
            <p>เพิ่ม / แก้ไข / ลบรอบเวลาพายเรือ</p>
        </div>
        <a href="admin_boat_bookings.php" class="btn btn-accent btn-sm">📋 ดูการจองทั้งหมด</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <!-- ── ฟอร์มเพิ่ม / แก้ไข ── -->
    <div class="form-card">
        <h2><?= $editQueue ? '✏️ แก้ไขคิว: ' . h($editQueue['queue_name']) : '➕ เพิ่มคิวพายเรือใหม่' ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $editQueue ? 'edit' : 'add' ?>">
            <?php if ($editQueue): ?>
                <input type="hidden" name="id" value="<?= (int)$editQueue['id'] ?>">
                <input type="hidden" name="image_path_current" value="<?= h($editQueue['image_path']) ?>" id="imgPathCurrent">
            <?php else: ?>
                <input type="hidden" name="image_path_current" value="" id="imgPathCurrent">
            <?php endif; ?>

            <div class="form-grid">
                <div class="fg half">
                    <label>ชื่อคิว *</label>
                    <input type="text" name="queue_name" required value="<?= h($editQueue['queue_name'] ?? '') ?>" placeholder="เช่น รอบเช้า / ทัวร์ชมธรรมชาติ">
                </div>
                <div class="fg">
                    <label>วันที่ *</label>
                    <input type="date" name="queue_date" required value="<?= h($editQueue['queue_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="fg">
                    <label>ราคา / ลำ (บาท, 0=ฟรี)</label>
                    <input type="number" name="price_per_boat" min="0" step="0.01" value="<?= (float)($editQueue['price_per_boat'] ?? 0) ?>">
                </div>
                <div class="fg">
                    <label>สถานะ</label>
                    <select name="status">
                        <option value="show" <?= ($editQueue['status'] ?? 'show') === 'show' ? 'selected' : '' ?>>แสดง (show)</option>
                        <option value="hide" <?= ($editQueue['status'] ?? '') === 'hide' ? 'selected' : '' ?>>ซ่อน (hide)</option>
                    </select>
                </div>
                <div class="fg full">
                    <label>คำอธิบาย</label>
                    <textarea name="description" placeholder="รายละเอียดเพิ่มเติม..."><?= h($editQueue['description'] ?? '') ?></textarea>
                </div>
                <div class="fg full">
                    <label>ประเภทเรือ (คั่นด้วยเครื่องหมายจุลภาค)</label>
                    <input type="text" name="boat_types"
                           value="<?= h($editQueue['boat_types'] ?? 'เรือพาย,เรือคายัค,เรือบด') ?>"
                           placeholder="เช่น เรือพาย,เรือคายัค,เรือบด">
                    <span style="font-size:.75rem;color:var(--muted);margin-top:4px;">ลูกค้าจะเห็นตัวเลือกประเภทเรือตามที่กำหนดนี้</span>
                </div>
                <div class="fg full">
                    <label>รูปภาพ (jpg/png/webp)</label>
                    <input type="file" name="image_file" accept="image/*" onchange="previewImg(this)">
                    <?php if (!empty($editQueue['image_path'])): ?>
                        <img src="<?= h($editQueue['image_path']) ?>" class="img-preview" id="imgPreview" style="display:block;" alt="">
                    <?php else: ?>
                        <img id="imgPreview" class="img-preview" alt="">
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $editQueue ? '💾 บันทึกการแก้ไข' : '➕ เพิ่มคิว' ?></button>
                <?php if ($editQueue): ?>
                    <a href="<?= $currentPage ?>" class="btn btn-ghost">ยกเลิก</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── ตารางคิว ── -->
    <div class="lm-card">
        <div class="lm-card-header">
            <span class="lm-card-title">คิวพายเรือทั้งหมด (<?= $result->num_rows ?>)</span>
            <form method="GET" style="display:flex;gap:8px;align-items:center;">
                <div class="search-wrap">
                    <input type="text" name="search" placeholder="ค้นหาชื่อคิว..." value="<?= h($search) ?>">
                </div>
                <button class="btn btn-ghost btn-sm" type="submit">ค้นหา</button>
                <?php if ($search): ?><a href="<?= $currentPage ?>" class="btn btn-ghost btn-sm">ล้าง</a><?php endif; ?>
            </form>
        </div>
        <div style="overflow-x:auto;">
        <table class="lm-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>รูป</th>
                    <th>ชื่อคิว</th>
                    <th>วันที่</th>
                    <th>ราคา/ลำ</th>
                    <th>การจอง</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td class="empty-row" colspan="8">ยังไม่มีคิวพายเรือ กรุณาเพิ่มคิวใหม่</td></tr>
            <?php endif; ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td>
                        <?php if (!empty($row['image_path'])): ?>
                            <img src="<?= h($row['image_path']) ?>" class="queue-img-cell" alt="" onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="queue-img-cell" style="display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🚣</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= h($row['queue_name']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($row['queue_date'])) ?></td>
                    <td><?= (float)$row['price_per_boat'] > 0 ? '฿'.number_format((float)$row['price_per_boat']) : '<span style="color:var(--success)">ฟรี</span>' ?></td>
                    <td><?= (int)$row['booking_count'] ?> รายการ</td>
                    <td>
                        <span class="<?= $row['status']==='show'?'status-show':'status-hide' ?>">
                            <?= $row['status']==='show'?'แสดง':'ซ่อน' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                            <a href="?edit_id=<?= $row['id'] ?>" class="btn btn-ghost btn-sm">✏️ แก้ไข</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="cur_status" value="<?= h($row['status']) ?>">
                                <button class="btn btn-ghost btn-sm" type="submit">
                                    <?= $row['status']==='show'?'🙈 ซ่อน':'👁 แสดง' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('ลบคิวนี้? การจองที่เกี่ยวข้องจะไม่ถูกลบ')">🗑</button>
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

<script>
function previewImg(input) {
    const preview = document.getElementById('imgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
        document.getElementById('imgPathCurrent').value = '';
    }
}
</script>

<?php include 'admin_layout_bottom.php'; ?>
<?php $conn->close(); ?>
