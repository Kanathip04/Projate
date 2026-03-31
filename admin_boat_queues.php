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
    `time_start` TIME NOT NULL DEFAULT '00:00:00',
    `time_end` TIME NOT NULL DEFAULT '23:59:00',
    `total_boats` INT DEFAULT 5,
    `price_per_boat` DECIMAL(10,2) DEFAULT 0,
    `description` TEXT,
    `image_path` VARCHAR(500) DEFAULT '',
    `boat_types` VARCHAR(500) DEFAULT 'เรือพาย,เรือคายัค,เรือบด',
    `status` ENUM('show','hide') DEFAULT 'show',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE boat_queues ADD COLUMN IF NOT EXISTS `boat_types` VARCHAR(500) DEFAULT 'เรือพาย,เรือคายัค,เรือบด' AFTER `image_path`");

/* ── ตารางเก็บข้อมูล archive รายวัน ── */
$conn->query("CREATE TABLE IF NOT EXISTS `boat_queue_daily_archive` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `archive_date` DATE NOT NULL,
    `total_queues` INT DEFAULT 0,
    `total_revenue` DECIMAL(12,2) DEFAULT 0,
    `bookings_json` LONGTEXT,
    `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_date` (`archive_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── ตาราง settings สำหรับ last_reset_date ── */
$conn->query("CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('last_queue_reset_date', '2000-01-01')");

/* ════════════════════════════════
   AUTO CLOSE เที่ยงคืน
   ปิดการจองคิวที่วันที่ผ่านมาแล้วอัตโนมัติ (ไม่ลบข้อมูล)
   เจ้าหน้าที่ต้องมาเปิดเองด้วยปุ่ม เปิดการจอง
════════════════════════════════ */
$today = date('Y-m-d');
$conn->query("UPDATE boat_queues SET status='hide' WHERE queue_date < '$today' AND status='show'");

$currentPage = basename($_SERVER['PHP_SELF']);
$message = ''; $message_type = 'success';

/* ═══════════════════════ POST ACTIONS ═══════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── เพิ่ม / แก้ไขคิว ── */
    if (in_array($action, ['add', 'edit'])) {
        $queue_name     = trim($_POST['queue_name']     ?? '');
        $queue_date     = trim($_POST['queue_date']     ?? date('Y-m-d'));
        $time_start     = '00:00:00';
        $time_end       = '23:59:00';
        $price_per_boat = max(0, (float)($_POST['price_per_boat'] ?? 0));
        $description    = trim($_POST['description']    ?? '');
        $boat_types     = trim($_POST['boat_types']     ?? 'เรือพาย,เรือคายัค,เรือบด');
        $status         = in_array($_POST['status'] ?? '', ['show','hide']) ? $_POST['status'] : 'show';
        $image_path     = trim($_POST['image_path_current'] ?? '');

        if (!empty($_FILES['image_file']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $allowed)) {
                $dir = 'uploads/boat_queues/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'queue_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dir . $fname))
                    $image_path = $dir . $fname;
            }
        }

        if ($action === 'add') {
            $st = $conn->prepare("INSERT INTO boat_queues (queue_name,queue_date,time_start,time_end,price_per_boat,description,boat_types,image_path,status) VALUES (?,?,?,?,?,?,?,?,?)");
            $st->bind_param("ssssdssss", $queue_name,$queue_date,$time_start,$time_end,$price_per_boat,$description,$boat_types,$image_path,$status);
            $st->execute(); $st->close();
            $message = "เพิ่มคิวพายเรือเรียบร้อยแล้ว";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $st = $conn->prepare("UPDATE boat_queues SET queue_name=?,queue_date=?,time_start=?,time_end=?,price_per_boat=?,description=?,boat_types=?,image_path=?,status=? WHERE id=?");
            $st->bind_param("ssssdssss i", $queue_name,$queue_date,$time_start,$time_end,$price_per_boat,$description,$boat_types,$image_path,$status,$id);
            $st->execute(); $st->close();
            $message = "แก้ไขคิวเรียบร้อยแล้ว";
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

    /* ── เปิด/ปิดการจอง ── */
    if ($action === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = trim($_POST['cur_status'] ?? 'show');
        $new = ($cur === 'show') ? 'hide' : 'show';
        if ($id > 0) {
            $st = $conn->prepare("UPDATE boat_queues SET status=? WHERE id=?");
            $st->bind_param("si", $new, $id); $st->execute(); $st->close();
            $message = $new === 'show' ? "เปิดการจองแล้ว" : "ปิดการจองแล้ว";
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
    $editQueue = $es->get_result()->fetch_assoc(); $es->close();
}

/* ── ดึงคิวทั้งหมด ── */
$search = trim($_GET['search'] ?? '');
$where  = "WHERE 1=1"; $params = []; $types = "";
if ($search !== '') {
    $where .= " AND (queue_name LIKE ? OR description LIKE ?)";
    $like = "%{$search}%"; $params = [$like,$like]; $types = "ss";
}
$stmt = $conn->prepare("SELECT q.*, (SELECT COUNT(*) FROM boat_bookings b WHERE b.queue_id=q.id AND b.booking_status IN ('pending','approved')) AS booking_count FROM boat_queues q {$where} ORDER BY q.queue_date DESC, q.id DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute(); $result = $stmt->get_result();


$pageTitle = "จัดการคิวพายเรือ"; $activeMenu = "boat_queue";

/* ── สถิติ ── */
$statRes = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(status='show') AS open_count,
    SUM(status='hide') AS closed_count,
    SUM(queue_date = '$today') AS today_count
    FROM boat_queues");
$stat = $statRes->fetch_assoc();

include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;--blue:#1565c0;}
.bq-wrap{padding:0 0 56px;animation:bqUp .35s ease both;}
@keyframes bqUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* BANNER */
.bq-banner{border-radius:18px;padding:26px 32px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:linear-gradient(135deg,#0d1b2a 0%,#1a3a5c 60%,#1565c0 100%);position:relative;overflow:hidden;}
.bq-banner::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(255,255,255,.05);top:-90px;right:-50px;pointer-events:none;}
.bq-banner h1{font-family:'Playfair Display',serif;font-style:italic;font-size:1.5rem;color:#fff;margin:0 0 4px;}
.bq-banner p{font-size:.8rem;color:rgba(255,255,255,.7);margin:0;}
.bq-banner-links{display:flex;gap:10px;position:relative;z-index:1;}
.bq-banner-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:.76rem;font-weight:700;text-decoration:none;border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#fff;transition:all .2s;}
.bq-banner-link:hover{background:rgba(255,255,255,.2);transform:translateY(-1px);}

/* ALERT */
.bq-alert{border-radius:12px;padding:12px 18px;margin-bottom:18px;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:8px;}
.bq-alert-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.bq-alert-danger{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

/* STATS */
.bq-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
.bq-stat{background:var(--card);border-radius:14px;padding:18px 20px;box-shadow:0 2px 10px rgba(26,26,46,.06);border-top:3px solid var(--border);transition:transform .2s;}
.bq-stat:hover{transform:translateY(-2px);}
.bq-stat:nth-child(1){border-top-color:var(--gold);}
.bq-stat:nth-child(2){border-top-color:var(--success);}
.bq-stat:nth-child(3){border-top-color:var(--danger);}
.bq-stat:nth-child(4){border-top-color:var(--blue);}
.bq-stat-label{font-size:.67rem;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;font-weight:700;}
.bq-stat-value{font-size:1.85rem;font-weight:800;color:var(--ink);line-height:1;}
.bq-stat-sub{font-size:.7rem;color:var(--muted);margin-top:3px;}

/* FORM CARD */
.bq-form-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.07);margin-bottom:20px;overflow:hidden;}
.bq-form-hd{background:linear-gradient(135deg,#0d1b2a 0%,#1a3a5c 100%);padding:16px 24px;display:flex;align-items:center;gap:10px;}
.bq-form-hd h2{font-family:'Playfair Display',serif;font-size:1rem;font-style:italic;color:#fff;margin:0;}
.bq-form-body{padding:24px;}
.bq-form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
.bq-form-grid .full{grid-column:1/-1;}
.bfg{display:flex;flex-direction:column;gap:5px;}
.bfg label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;}
.bfg input,.bfg textarea{font-family:'Sarabun',sans-serif;font-size:.88rem;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;color:var(--ink);background:#fdfcfa;outline:none;transition:border-color .2s,box-shadow .2s;}
.bfg input:focus,.bfg textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.bfg textarea{min-height:76px;resize:vertical;}
.bfg-hint{font-size:.7rem;color:var(--muted);margin-top:3px;}
.img-preview{max-width:110px;border-radius:9px;margin-top:6px;border:1px solid var(--border);display:none;}

/* STATUS TOGGLE */
.status-wrap{display:flex;gap:8px;}
.status-btn{flex:1;padding:9px 8px;border-radius:10px;border:2px solid var(--border);cursor:pointer;text-align:center;font-size:.82rem;font-weight:700;transition:all .2s;background:#fdfcfa;color:var(--muted);user-select:none;}
.status-btn.s-open{border-color:var(--success);background:#f0fdf4;color:var(--success);}
.status-btn.s-close{border-color:var(--danger);background:#fef2f2;color:var(--danger);}

.bq-form-actions{display:flex;gap:10px;padding-top:8px;}

/* TABLE CARD */
.bq-list-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.07);overflow:hidden;}
.bq-list-hd{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.bq-list-title{font-size:.9rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
.bq-list-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--blue);border-radius:2px;}
.bq-count{background:#eff6ff;color:var(--blue);font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.bq-search{display:flex;gap:8px;align-items:center;}
.bq-search input{border:1.5px solid var(--border);border-radius:8px;padding:7px 12px 7px 32px;font-family:'Sarabun',sans-serif;font-size:.82rem;outline:none;background:#fdfcfa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%237a7a8c' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.868-3.834zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E") no-repeat 10px center;}
.bq-search input:focus{border-color:var(--gold);}

table.bq-tbl{width:100%;border-collapse:collapse;min-width:700px;}
table.bq-tbl th{padding:10px 14px;text-align:left;font-size:.67rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;border-bottom:2px solid var(--border);background:#fdfcfa;}
table.bq-tbl td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:.84rem;color:var(--ink);vertical-align:middle;}
table.bq-tbl tr:last-child td{border-bottom:none;}
table.bq-tbl tr:hover td{background:#fdfcfa;}

.q-img{width:52px;height:42px;object-fit:cover;border-radius:8px;}
.q-img-ph{width:52px;height:42px;border-radius:8px;background:linear-gradient(135deg,#0d1b2a,#1565c0);display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
.badge-open{background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:99px;padding:3px 10px;font-size:.69rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-open::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--success);}
.badge-close{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:99px;padding:3px 10px;font-size:.69rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-close::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--danger);}
.badge-today{background:#eff6ff;color:var(--blue);border:1px solid #bfdbfe;border-radius:99px;padding:2px 7px;font-size:.65rem;font-weight:700;margin-left:4px;}

.act-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border:none;border-radius:7px;font-family:'Sarabun',sans-serif;font-size:.75rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.act-btn:hover{transform:translateY(-1px);}
.act-edit{background:#f1f5f9;color:#334155;border:1.5px solid #e2e8f0;}
.act-edit:hover{border-color:var(--gold);color:#92400e;}
.act-open{background:#f0fdf4;color:var(--success);border:1.5px solid #86efac;}
.act-open:hover{background:#dcfce7;}
.act-close{background:#fef2f2;color:var(--danger);border:1.5px solid #fca5a5;}
.act-close:hover{background:#fee2e2;}
.act-del{background:#fafafa;color:#94a3b8;border:1.5px solid #e2e8f0;}
.act-del:hover{color:var(--danger);border-color:#fca5a5;}

/* SUBMIT BTN */
.submit-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border:none;border-radius:10px;font-family:'Sarabun',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;transition:all .2s;background:linear-gradient(135deg,#0d1b2a,#1565c0);color:#fff;box-shadow:0 3px 10px rgba(13,27,42,.2);}
.submit-btn:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(13,27,42,.28);}
.cancel-btn{display:inline-flex;align-items:center;gap:5px;padding:10px 18px;border:1.5px solid var(--border);border-radius:10px;font-family:'Sarabun',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;background:#fff;color:var(--muted);text-decoration:none;transition:all .2s;}
.cancel-btn:hover{border-color:var(--danger);color:var(--danger);}

.bq-empty{text-align:center;padding:48px 20px;color:var(--muted);font-size:.88rem;}
.bq-empty-icon{font-size:2.5rem;margin-bottom:10px;opacity:.3;}

@media(max-width:900px){.bq-stats{grid-template-columns:repeat(2,1fr);}}
@media(max-width:640px){.bq-form-grid{grid-template-columns:1fr;}.bq-stats{grid-template-columns:1fr 1fr;}}
</style>

<div class="bq-wrap">

  <!-- BANNER -->
  <div class="bq-banner">
    <div style="position:relative;z-index:1;">
      <h1>🚣 จัดการคิวพายเรือ</h1>
      <p>เพิ่ม / แก้ไข / เปิด-ปิดการจอง · ปิดการจองอัตโนมัติทุกเที่ยงคืน</p>
    </div>
    <div class="bq-banner-links">
      <a href="admin_boat_bookings.php" class="bq-banner-link">📋 รายการจอง</a>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="bq-alert bq-alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
    <?= $message_type === 'success' ? '✓' : '✗' ?> <?= h($message) ?>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="bq-stats">
    <div class="bq-stat">
      <div class="bq-stat-label">คิวทั้งหมด</div>
      <div class="bq-stat-value"><?= (int)$stat['total'] ?></div>
      <div class="bq-stat-sub">ในระบบ</div>
    </div>
    <div class="bq-stat">
      <div class="bq-stat-label">เปิดการจอง</div>
      <div class="bq-stat-value" style="color:var(--success);"><?= (int)$stat['open_count'] ?></div>
      <div class="bq-stat-sub">รับจองอยู่</div>
    </div>
    <div class="bq-stat">
      <div class="bq-stat-label">ปิดการจอง</div>
      <div class="bq-stat-value" style="color:var(--danger);"><?= (int)$stat['closed_count'] ?></div>
      <div class="bq-stat-sub">ปิดแล้ว / หมดเวลา</div>
    </div>
    <div class="bq-stat">
      <div class="bq-stat-label">คิววันนี้</div>
      <div class="bq-stat-value" style="color:var(--blue);"><?= (int)$stat['today_count'] ?></div>
      <div class="bq-stat-sub"><?= date('d/m/Y') ?></div>
    </div>
  </div>

  <!-- FORM -->
  <div class="bq-form-card">
    <div class="bq-form-hd">
      <span style="font-size:1.1rem;"><?= $editQueue ? '✏️' : '➕' ?></span>
      <h2><?= $editQueue ? 'แก้ไขคิว: ' . h($editQueue['queue_name']) : 'เพิ่มคิวพายเรือใหม่' ?></h2>
    </div>
    <div class="bq-form-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editQueue ? 'edit' : 'add' ?>">
        <?php if ($editQueue): ?><input type="hidden" name="id" value="<?= (int)$editQueue['id'] ?>"><?php endif; ?>
        <input type="hidden" name="image_path_current" value="<?= h($editQueue['image_path'] ?? '') ?>" id="imgPathCurrent">

        <div class="bq-form-grid">
          <div class="bfg">
            <label>ชื่อคิว *</label>
            <input type="text" name="queue_name" required value="<?= h($editQueue['queue_name'] ?? '') ?>" placeholder="เช่น รอบเช้า / ทัวร์ชมธรรมชาติ">
          </div>
          <div class="bfg">
            <label>วันที่ *</label>
            <input type="date" name="queue_date" required value="<?= h($editQueue['queue_date'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="bfg">
            <label>ราคา / ลำ (บาท · 0 = ฟรี)</label>
            <input type="number" name="price_per_boat" min="0" step="1" value="<?= (int)($editQueue['price_per_boat'] ?? 0) ?>">
          </div>
          <div class="bfg">
            <label>สถานะการจอง</label>
            <input type="hidden" name="status" id="statusInput" value="<?= h($editQueue['status'] ?? 'show') ?>">
            <div class="status-wrap">
              <div class="status-btn <?= ($editQueue['status'] ?? 'show') === 'show' ? 's-open' : '' ?>" onclick="setStatus('show',this)">● เปิดการจอง</div>
              <div class="status-btn <?= ($editQueue['status'] ?? 'show') === 'hide' ? 's-close' : '' ?>" onclick="setStatus('hide',this)">● ปิดการจอง</div>
            </div>
          </div>
          <div class="bfg full">
            <label>คำอธิบาย</label>
            <textarea name="description" placeholder="รายละเอียดเพิ่มเติม..."><?= h($editQueue['description'] ?? '') ?></textarea>
          </div>
          <div class="bfg full">
            <label>ประเภทเรือ (คั่นด้วยจุลภาค)</label>
            <input type="text" name="boat_types" value="<?= h($editQueue['boat_types'] ?? 'เรือพาย,เรือคายัค,เรือบด') ?>" placeholder="เรือพาย,เรือคายัค,เรือบด">
            <div class="bfg-hint">ลูกค้าจะเห็นตัวเลือกเรือตามที่กำหนด</div>
          </div>
          <div class="bfg full">
            <label>รูปภาพ (JPG/PNG/WEBP)</label>
            <input type="file" name="image_file" accept="image/*" onchange="previewImg(this)">
            <?php if (!empty($editQueue['image_path'])): ?>
              <img src="<?= h($editQueue['image_path']) ?>" class="img-preview" id="imgPreview" style="display:block;" alt="">
            <?php else: ?>
              <img id="imgPreview" class="img-preview" alt="">
            <?php endif; ?>
          </div>
        </div>

        <div class="bq-form-actions">
          <button class="submit-btn" type="submit">
            <?= $editQueue ? '💾 บันทึกการแก้ไข' : '➕ เพิ่มคิว' ?>
          </button>
          <?php if ($editQueue): ?>
            <a href="<?= $currentPage ?>" class="cancel-btn">✕ ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- TABLE -->
  <div class="bq-list-card">
    <div class="bq-list-hd">
      <div class="bq-list-title">
        คิวทั้งหมด
        <span class="bq-count"><?= $result->num_rows ?> รายการ</span>
      </div>
      <form method="GET" class="bq-search">
        <input type="text" name="search" placeholder="ค้นหาชื่อคิว..." value="<?= h($search) ?>">
        <button class="act-btn act-edit" type="submit">ค้นหา</button>
        <?php if ($search): ?><a href="<?= $currentPage ?>" class="act-btn act-edit">ล้าง</a><?php endif; ?>
      </form>
    </div>
    <div style="overflow-x:auto;">
      <table class="bq-tbl">
        <thead>
          <tr>
            <th>#</th><th>รูป</th><th>ชื่อคิว</th><th>วันที่</th><th>ราคา/ลำ</th><th>การจอง</th><th>สถานะ</th><th style="width:180px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
          <tr><td colspan="8">
            <div class="bq-empty"><div class="bq-empty-icon">🚣</div>ยังไม่มีคิว กรุณาเพิ่มคิวใหม่ด้านบน</div>
          </td></tr>
        <?php endif; ?>
        <?php $rowNo = 1; while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--muted);font-size:.76rem;font-weight:700;"><?= $rowNo++ ?></td>
            <td>
              <?php if (!empty($row['image_path'])): ?>
                <img src="<?= h($row['image_path']) ?>" class="q-img" alt="" onerror="this.style.display='none'">
              <?php else: ?>
                <div class="q-img-ph">🚣</div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:700;font-size:.87rem;"><?= h($row['queue_name']) ?></div>
              <?php if (!empty($row['description'])): ?>
              <div style="font-size:.73rem;color:var(--muted);margin-top:2px;"><?= h(mb_substr($row['description'],0,45)) ?>…</div>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
              <?= date('d/m/Y', strtotime($row['queue_date'])) ?>
              <?php if ($row['queue_date'] === $today): ?>
                <span class="badge-today">วันนี้</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((float)$row['price_per_boat'] > 0): ?>
                <span style="font-weight:700;color:var(--blue);">฿<?= number_format((float)$row['price_per_boat']) ?></span>
              <?php else: ?>
                <span style="color:var(--success);font-weight:600;">ฟรี</span>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-weight:700;"><?= (int)$row['booking_count'] ?></span>
              <span style="font-size:.73rem;color:var(--muted);"> รายการ</span>
            </td>
            <td>
              <?php if ($row['status'] === 'show'): ?>
                <span class="badge-open">เปิดจอง</span>
              <?php else: ?>
                <span class="badge-close">ปิดจอง</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                <a href="?edit_id=<?= $row['id'] ?>" class="act-btn act-edit">✏️</a>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="cur_status" value="<?= h($row['status']) ?>">
                  <button class="act-btn <?= $row['status']==='show'?'act-close':'act-open' ?>" type="submit">
                    <?= $row['status']==='show'?'ปิด':'เปิด' ?>
                  </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('ลบคิวนี้?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="act-btn act-del" type="submit">🗑</button>
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

<script>
function previewImg(input) {
    const p = document.getElementById('imgPreview');
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => { p.src = e.target.result; p.style.display = 'block'; };
        r.readAsDataURL(input.files[0]);
        document.getElementById('imgPathCurrent').value = '';
    }
}
function setStatus(val, el) {
    document.getElementById('statusInput').value = val;
    document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('s-open','s-close'));
    el.classList.add(val === 'show' ? 's-open' : 's-close');
}
</script>

<?php include 'admin_layout_bottom.php'; ?>
<?php $conn->close(); ?>
