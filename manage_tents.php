<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* === สร้างตาราง tents ถ้ายังไม่มี === */
$conn->query("CREATE TABLE IF NOT EXISTS `tents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tent_name` VARCHAR(200) NOT NULL,
    `tent_type` VARCHAR(100) DEFAULT '',
    `capacity` INT DEFAULT 4,
    `price_per_night` DECIMAL(10,2) DEFAULT 0,
    `total_tents` INT DEFAULT 5,
    `description` TEXT,
    `image_path` VARCHAR(500) DEFAULT '',
    `status` ENUM('show','hide') DEFAULT 'show',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = ''; $message_type = 'success';
$currentPage = basename($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* === เพิ่ม/แก้ไขเต็นท์ === */
    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $tent_name   = trim($_POST['tent_name'] ?? '');
        $tent_type   = trim($_POST['tent_type'] ?? '');
        $capacity    = max(1, (int)($_POST['capacity'] ?? 4));
        $price       = max(0, (float)($_POST['price_per_night'] ?? 0));
        $total_tents = max(1, (int)($_POST['total_tents'] ?? 5));
        $description = trim($_POST['description'] ?? '');
        $status      = ($_POST['status'] ?? 'show') === 'hide' ? 'hide' : 'show';
        $image_path  = trim($_POST['image_path'] ?? '');

        /* อัปโหลดภาพ */
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $uploadDir = 'uploads/tents/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = 'tent_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                    $image_path = $uploadDir . $filename;
                }
            }
        }

        if ($tent_name === '') {
            $message = "กรุณากรอกชื่อเต็นท์"; $message_type = "error";
        } else {
            if ($id > 0) {
                $st = $conn->prepare("UPDATE tents SET tent_name=?,tent_type=?,capacity=?,price_per_night=?,total_tents=?,description=?,image_path=?,status=? WHERE id=?");
                $st->bind_param("ssidisssi", $tent_name,$tent_type,$capacity,$price,$total_tents,$description,$image_path,$status,$id);
                /* types: s=tent_name, s=tent_type, i=capacity, d=price, i=total_tents, s=description, s=image_path, s=status, i=id */
                $st->execute(); $st->close();
                $message = "แก้ไขข้อมูลเต็นท์เรียบร้อยแล้ว";
            } else {
                $st = $conn->prepare("INSERT INTO tents (tent_name,tent_type,capacity,price_per_night,total_tents,description,image_path,status) VALUES (?,?,?,?,?,?,?,?)");
                $st->bind_param("ssidisss", $tent_name,$tent_type,$capacity,$price,$total_tents,$description,$image_path,$status);
                $st->execute(); $st->close();
                $message = "เพิ่มเต็นท์เรียบร้อยแล้ว";
            }
        }
    }

    /* === ลบเต็นท์ === */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("DELETE FROM tents WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            $message = "ลบเต็นท์เรียบร้อยแล้ว";
        }
    }

    /* === toggle สถานะ === */
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE tents SET status = IF(status='show','hide','show') WHERE id={$id}");
            $message = "เปลี่ยนสถานะเรียบร้อยแล้ว";
        }
    }

    header("Location: {$currentPage}?msg=" . urlencode($message) . "&type=" . urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }

/* === โหมดแก้ไข === */
$editTent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $er = $conn->prepare("SELECT * FROM tents WHERE id=? LIMIT 1");
    $er->bind_param("i", $editId); $er->execute();
    $editTent = $er->get_result()->fetch_assoc();
    $er->close();
}

$result = $conn->query("SELECT * FROM tents ORDER BY id DESC");

$pageTitle = "จัดการเต็นท์"; $activeMenu = "tent_manage";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,0.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;}
.mg-wrap{padding:0 0 48px;}
.mg-banner{border-radius:18px;padding:26px 32px;margin-bottom:24px;background:linear-gradient(135deg,var(--ink),#2a2a4a);position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.mg-banner::before{content:'';position:absolute;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.05);top:-80px;right:-50px;pointer-events:none;}
.mg-banner h1{font-family:'Playfair Display',serif;font-style:italic;font-size:1.5rem;color:#fff;margin:0 0 4px;}
.mg-banner p{font-size:.8rem;color:rgba(255,255,255,.7);margin:0;}
.mg-banner-links{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1;}
.mg-banner-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:.76rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(255,255,255,.22);background:rgba(255,255,255,.1);color:#fff;transition:all .2s;}
.mg-banner-link:hover{background:rgba(255,255,255,.2);transform:translateY(-1px);}
.mg-alert{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:22px;}
.mg-alert-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.mg-alert-error{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}
.mg-layout{display:grid;grid-template-columns:380px 1fr;gap:22px;align-items:start;}
.mg-form-card,.mg-list-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.06);overflow:hidden;}
.mg-card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
.mg-card-title{font-size:.9rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
.mg-card-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--gold);border-radius:2px;}
.mg-form-body{padding:22px;}
.mg-form-group{margin-bottom:16px;}
.mg-form-group label{display:block;font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:7px;letter-spacing:.08em;text-transform:uppercase;}
.mg-form-group input,.mg-form-group textarea,.mg-form-group select{width:100%;font-family:'Sarabun',sans-serif;font-size:.9rem;color:var(--ink);background:#fafaf8;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;outline:none;transition:border-color .2s,box-shadow .2s;}
.mg-form-group input:focus,.mg-form-group textarea:focus,.mg-form-group select:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);background:#fff;}
.mg-form-group textarea{min-height:80px;resize:vertical;}
.mg-form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.img-preview{width:100%;height:120px;object-fit:cover;border-radius:8px;margin-top:8px;display:block;background:#f0f0f0;}
.mg-btn{display:inline-flex;align-items:center;gap:5px;padding:9px 18px;border:none;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.mg-btn:hover{transform:translateY(-1px);}
.mg-btn-primary{background:var(--ink);color:#fff;width:100%;justify-content:center;padding:11px;}
.mg-btn-primary:hover{background:#2a2a4a;}
.mg-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.mg-btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.mg-btn-danger{background:#fef2f2;color:var(--danger);border:1.5px solid #fca5a5;}
.mg-btn-danger:hover{background:#fee2e2;}
.mg-btn-warning{background:#fffbeb;color:#d97706;border:1.5px solid #fde68a;}
.mg-btn-warning:hover{background:#fef3c7;}
.mg-btn-sm{padding:6px 11px;font-size:.74rem;}
.mg-table-wrap{overflow-x:auto;}
.mg-table{width:100%;border-collapse:collapse;min-width:600px;}
.mg-table thead th{padding:11px 14px;font-size:.67rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);border-bottom:2px solid var(--border);text-align:left;font-weight:700;background:#fdfcfa;}
.mg-table tbody td{padding:12px 14px;font-size:.83rem;color:var(--ink);border-bottom:1px solid var(--border);vertical-align:middle;}
.mg-table tbody tr:last-child td{border-bottom:none;}
.mg-table tbody tr:hover{background:#fdfcfa;}
.mg-tent-img{width:56px;height:42px;object-fit:cover;border-radius:6px;border:1px solid var(--border);}
.mg-tent-name{font-weight:700;}
.mg-meta{font-size:.73rem;color:var(--muted);margin-top:2px;}
.mg-actions{display:flex;gap:6px;flex-wrap:wrap;}
.mg-inline{display:inline;margin:0;}
.badge-show{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.69rem;font-weight:700;background:#f0fdf4;color:#166534;}
.badge-hide{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.69rem;font-weight:700;background:#f3f4f6;color:#374151;}
.mg-empty{padding:40px 24px;text-align:center;color:var(--muted);}
@media(max-width:1024px){.mg-layout{grid-template-columns:1fr;}}
</style>

<div class="mg-wrap">
    <div class="mg-banner">
        <div>
            <h1>🏕 จัดการเต็นท์</h1>
            <p>เพิ่ม แก้ไข และจัดการข้อมูลเต็นท์ทั้งหมด</p>
        </div>
        <div class="mg-banner-links">
            <a href="admin_tent_list.php" class="mg-banner-link">📋 รายการจอง</a>
            <a href="admin_tent_approved.php" class="mg-banner-link">✅ รายการอนุมัติ</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="mg-alert <?= $message_type==='error' ? 'mg-alert-error' : 'mg-alert-success' ?>">
            <?= $message_type==='error' ? '⚠' : '✓' ?> <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="mg-layout">
        <!-- ฟอร์มเพิ่ม/แก้ไข -->
        <div class="mg-form-card">
            <div class="mg-card-header">
                <div class="mg-card-title"><?= $editTent ? '✏️ แก้ไขเต็นท์' : '➕ เพิ่มเต็นท์ใหม่' ?></div>
                <?php if ($editTent): ?>
                    <a href="manage_tents.php" class="mg-btn mg-btn-ghost mg-btn-sm">ยกเลิก</a>
                <?php endif; ?>
            </div>
            <div class="mg-form-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $editTent ? (int)$editTent['id'] : 0 ?>">

                    <div class="mg-form-group">
                        <label>ชื่อเต็นท์ *</label>
                        <input type="text" name="tent_name" placeholder="เช่น เต็นท์โดม A"
                               value="<?= h($editTent['tent_name'] ?? '') ?>" required>
                    </div>

                    <div class="mg-form-group">
                        <label>ประเภทเต็นท์</label>
                        <input type="text" name="tent_type" placeholder="เช่น โดม, อุโมงค์, ครอบครัว"
                               value="<?= h($editTent['tent_type'] ?? '') ?>">
                    </div>

                    <div class="mg-form-row">
                        <div class="mg-form-group">
                            <label>รองรับ (คน)</label>
                            <input type="number" name="capacity" min="1" value="<?= (int)($editTent['capacity'] ?? 4) ?>">
                        </div>
                        <div class="mg-form-group">
                            <label>จำนวนเต็นท์ทั้งหมด</label>
                            <input type="number" name="total_tents" min="1" value="<?= (int)($editTent['total_tents'] ?? 5) ?>">
                        </div>
                    </div>

                    <div class="mg-form-group">
                        <label>ราคาต่อคืน (บาท)</label>
                        <input type="number" name="price_per_night" min="0" step="0.01"
                               value="<?= number_format((float)($editTent['price_per_night'] ?? 0), 2, '.', '') ?>">
                    </div>

                    <div class="mg-form-group">
                        <label>รายละเอียด</label>
                        <textarea name="description" placeholder="รายละเอียดเต็นท์..."><?= h($editTent['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mg-form-group">
                        <label>รูปภาพเต็นท์</label>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($editTent['image_path'])): ?>
                            <img src="<?= h($editTent['image_path']) ?>" class="img-preview"
                                 onerror="this.style.display='none'" alt="รูปเต็นท์ปัจจุบัน">
                        <?php endif; ?>
                        <input type="hidden" name="image_path" value="<?= h($editTent['image_path'] ?? '') ?>">
                    </div>

                    <div class="mg-form-group">
                        <label>สถานะ</label>
                        <select name="status">
                            <option value="show" <?= ($editTent['status'] ?? 'show') === 'show' ? 'selected' : '' ?>>แสดง (show)</option>
                            <option value="hide" <?= ($editTent['status'] ?? '') === 'hide' ? 'selected' : '' ?>>ซ่อน (hide)</option>
                        </select>
                    </div>

                    <button type="submit" class="mg-btn mg-btn-primary">
                        <?= $editTent ? '💾 บันทึกการแก้ไข' : '➕ เพิ่มเต็นท์' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- รายการเต็นท์ -->
        <div class="mg-list-card">
            <div class="mg-card-header">
                <div class="mg-card-title">รายการเต็นท์ทั้งหมด</div>
                <span style="background:var(--gold-dim);color:#a07c3a;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;">
                    <?= $result ? $result->num_rows : 0 ?> รายการ
                </span>
            </div>
            <div class="mg-table-wrap">
                <table class="mg-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">รูป</th>
                            <th>ชื่อเต็นท์</th>
                            <th>ราคา/คืน</th>
                            <th>จำนวน</th>
                            <th>สถานะ</th>
                            <th style="width:180px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row['image_path'])): ?>
                                            <img src="<?= h($row['image_path']) ?>" class="mg-tent-img"
                                                 onerror="this.src='uploads/no-image.png'" alt="">
                                        <?php else: ?>
                                            <div style="width:56px;height:42px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">⛺</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mg-tent-name"><?= h($row['tent_name']) ?></div>
                                        <div class="mg-meta"><?= h($row['tent_type'] ?: '-') ?> · รองรับ <?= (int)$row['capacity'] ?> คน</div>
                                    </td>
                                    <td style="font-weight:700;color:#a8864d;">฿<?= number_format((float)$row['price_per_night']) ?></td>
                                    <td><?= (int)$row['total_tents'] ?> หลัง</td>
                                    <td>
                                        <span class="<?= $row['status']==='show' ? 'badge-show' : 'badge-hide' ?>">
                                            <?= $row['status']==='show' ? 'แสดง' : 'ซ่อน' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="mg-actions">
                                            <a href="?edit=<?= (int)$row['id'] ?>" class="mg-btn mg-btn-ghost mg-btn-sm">✏️ แก้ไข</a>
                                            <form method="POST" class="mg-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button class="mg-btn mg-btn-warning mg-btn-sm">
                                                    <?= $row['status']==='show' ? '🙈 ซ่อน' : '👁 แสดง' ?>
                                                </button>
                                            </form>
                                            <form method="POST" class="mg-inline" onsubmit="return confirm('ลบเต็นท์นี้?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button class="mg-btn mg-btn-danger mg-btn-sm">🗑 ลบ</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">
                                <div class="mg-empty">⛺ ยังไม่มีข้อมูลเต็นท์ — เพิ่มได้จากฟอร์มด้านซ้าย</div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $conn->close(); include 'admin_layout_bottom.php'; ?>
