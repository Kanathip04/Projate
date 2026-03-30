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
   AUTO ARCHIVE เที่ยงคืน
   ถ้าวันที่ last_reset != เมื่อวาน → archive ข้อมูลเมื่อวาน
════════════════════════════════ */
$yesterday = date('Y-m-d', strtotime('-1 day'));
$today     = date('Y-m-d');

$lastReset = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key='last_queue_reset_date' LIMIT 1")->fetch_assoc()['setting_value'] ?? '2000-01-01';

if ($lastReset < $today) {
    // archive เมื่อวาน (ถ้ายังไม่ได้ archive)
    $archCheck = $conn->prepare("SELECT id FROM boat_queue_daily_archive WHERE archive_date=? LIMIT 1");
    $archCheck->bind_param("s", $yesterday); $archCheck->execute();
    $alreadyArch = $archCheck->get_result()->fetch_assoc();
    $archCheck->close();

    if (!$alreadyArch) {
        $bkRes = $conn->query("SELECT * FROM boat_bookings WHERE DATE(approved_at)='$yesterday' AND booking_status='approved' AND payment_status='paid' ORDER BY daily_queue_no ASC");
        $bkRows = [];
        $totalRev = 0;
        while ($bk = $bkRes->fetch_assoc()) {
            $bkRows[] = $bk;
            $totalRev += (float)($bk['total_amount'] ?? 0);
        }
        if (!empty($bkRows)) {
            $json = json_encode($bkRows, JSON_UNESCAPED_UNICODE);
            $cnt  = count($bkRows);
            $archIns = $conn->prepare("INSERT IGNORE INTO boat_queue_daily_archive (archive_date, total_queues, total_revenue, bookings_json) VALUES (?,?,?,?)");
            $archIns->bind_param("siis", $yesterday, $cnt, $totalRev, $json);
            $archIns->execute(); $archIns->close();
        }
    }

    // อัปเดต last reset date
    $conn->query("UPDATE app_settings SET setting_value='$today' WHERE setting_key='last_queue_reset_date'");
}

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

    /* ── archive ด้วยตนเอง ── */
    if ($action === 'manual_archive') {
        $archDate = trim($_POST['archive_date'] ?? $yesterday);
        $bkRes2 = $conn->query("SELECT * FROM boat_bookings WHERE DATE(approved_at)='$archDate' AND booking_status='approved' AND payment_status='paid' ORDER BY daily_queue_no ASC");
        $bkRows2 = []; $totalRev2 = 0;
        while ($bk = $bkRes2->fetch_assoc()) { $bkRows2[] = $bk; $totalRev2 += (float)($bk['total_amount'] ?? 0); }
        $json2 = json_encode($bkRows2, JSON_UNESCAPED_UNICODE);
        $cnt2  = count($bkRows2);
        $archIns2 = $conn->prepare("INSERT INTO boat_queue_daily_archive (archive_date,total_queues,total_revenue,bookings_json) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE total_queues=VALUES(total_queues),total_revenue=VALUES(total_revenue),bookings_json=VALUES(bookings_json),archived_at=NOW()");
        $archIns2->bind_param("siis", $archDate, $cnt2, $totalRev2, $json2);
        $archIns2->execute(); $archIns2->close();
        $message = "จัดเก็บข้อมูลวันที่ $archDate สำเร็จ ($cnt2 คิว)";
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

/* ── ดึง archives ล่าสุด ── */
$archives = $conn->query("SELECT archive_date, total_queues, total_revenue, archived_at FROM boat_queue_daily_archive ORDER BY archive_date DESC LIMIT 10");

$pageTitle = "จัดการคิวพายเรือ"; $activeMenu = "boat_queue";
include 'admin_layout_top.php';
?>

<style>
:root{
  --navy:#0d1b2a;--navy2:#1a3a5c;--blue:#1565c0;--blue2:#1976d2;
  --gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);
  --ink:#0d1b2a;--muted:#5f7281;--bg:#f0f5fc;
  --card:#fff;--border:#e0eaf5;
  --green:#15803d;--green-bg:#ecfdf3;--green-bdr:#a7e8bb;
  --red:#dc2626;--red-bg:#fef2f2;
  --radius:14px;
}
.bq-wrap{padding-bottom:60px;}

/* BANNER */
.bq-banner{
  border-radius:var(--radius);padding:24px 28px;margin-bottom:22px;
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 60%,#1565c0 100%);
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
  position:relative;overflow:hidden;
}
.bq-banner::before{content:'';position:absolute;width:260px;height:260px;border-radius:50%;
  background:radial-gradient(circle,rgba(29,111,173,.15) 0%,transparent 70%);top:-80px;right:-40px;}
.bq-banner-left h1{font-family:'Kanit',sans-serif;font-size:1.45rem;font-weight:800;color:#fff;margin:0 0 4px;}
.bq-banner-left p{font-size:.8rem;color:rgba(255,255,255,.7);margin:0;}
.bq-banner-right{display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;}

/* ALERT */
.bq-alert{border-radius:10px;padding:11px 16px;margin-bottom:18px;font-size:.85rem;font-weight:600;
  display:flex;align-items:center;gap:8px;}
.bq-alert-success{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bdr);}
.bq-alert-danger{background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;}

/* FORM CARD */
.form-card{
  background:var(--card);border-radius:var(--radius);
  box-shadow:0 2px 12px rgba(13,27,42,.07);
  margin-bottom:20px;overflow:hidden;
}
.form-card-header{
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);
  padding:16px 22px;
  display:flex;align-items:center;gap:10px;
}
.form-card-header h2{font-family:'Kanit',sans-serif;font-size:1rem;font-weight:800;color:#fff;margin:0;}
.form-card-body{padding:22px 22px 20px;}

.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
.form-grid .full{grid-column:1/-1;}
.fg{display:flex;flex-direction:column;gap:5px;}
.fg label{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;}
.fg input,.fg textarea,.fg select{
  font-family:'Sarabun',sans-serif;font-size:.88rem;
  border:1.5px solid var(--border);border-radius:8px;
  padding:9px 12px;color:var(--ink);background:#fafbfd;
  outline:none;transition:border-color .2s,box-shadow .2s;
}
.fg input:focus,.fg textarea:focus,.fg select:focus{
  border-color:var(--blue);box-shadow:0 0 0 3px rgba(21,101,192,.1);
}
.fg textarea{min-height:72px;resize:vertical;}
.fg-hint{font-size:.72rem;color:var(--muted);margin-top:3px;}
.img-preview{max-width:120px;border-radius:8px;margin-top:6px;border:1px solid var(--border);display:none;}
.form-actions{display:flex;gap:10px;padding-top:6px;}

/* STATUS TOGGLE */
.toggle-wrap{display:flex;gap:8px;}
.toggle-btn{
  flex:1;padding:9px;border-radius:8px;border:2px solid var(--border);
  cursor:pointer;text-align:center;font-size:.83rem;font-weight:700;
  transition:all .2s;background:#fafbfd;color:var(--muted);
}
.toggle-btn.active-open{border-color:var(--green);background:var(--green-bg);color:var(--green);}
.toggle-btn.active-close{border-color:var(--red);background:var(--red-bg);color:var(--red);}

/* QUEUE TABLE */
.list-card{background:var(--card);border-radius:var(--radius);box-shadow:0 2px 12px rgba(13,27,42,.07);overflow:hidden;margin-bottom:20px;}
.list-card-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.list-card-title{font-family:'Kanit',sans-serif;font-size:.95rem;font-weight:800;color:var(--ink);}
.search-bar{display:flex;gap:8px;align-items:center;}
.search-bar input{border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;font-family:'Sarabun',sans-serif;font-size:.83rem;outline:none;background:#fafbfd;}
.search-bar input:focus{border-color:var(--blue);}

table.bq-table{width:100%;border-collapse:collapse;}
table.bq-table th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1.5px solid var(--border);background:#f8fbff;}
table.bq-table td{padding:11px 14px;border-bottom:1px solid #f0f5fc;font-size:.85rem;color:var(--ink);vertical-align:middle;}
table.bq-table tr:last-child td{border-bottom:none;}
table.bq-table tr:hover td{background:#f8fbff;}
.q-img{width:56px;height:44px;object-fit:cover;border-radius:8px;background:var(--border);}
.q-img-ph{width:56px;height:44px;border-radius:8px;background:linear-gradient(135deg,var(--navy),var(--blue2));display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
.badge-open{background:var(--green-bg);color:var(--green);border:1px solid var(--green-bdr);border-radius:99px;padding:3px 10px;font-size:.7rem;font-weight:700;}
.badge-close{background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;border-radius:99px;padding:3px 10px;font-size:.7rem;font-weight:700;}
.action-btns{display:flex;gap:5px;flex-wrap:nowrap;}

/* ARCHIVE SECTION */
.archive-card{background:var(--card);border-radius:var(--radius);box-shadow:0 2px 12px rgba(13,27,42,.07);overflow:hidden;}
.archive-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;}
.archive-title{font-family:'Kanit',sans-serif;font-size:.95rem;font-weight:800;color:var(--ink);}
.archive-row{padding:11px 20px;border-bottom:1px solid #f0f5fc;display:flex;align-items:center;gap:12px;font-size:.85rem;}
.archive-row:last-child{border-bottom:none;}
.arch-date{font-weight:700;color:var(--ink);min-width:90px;}
.arch-count{background:var(--blue-lt,#e3f2fd);color:var(--blue);border-radius:99px;padding:2px 10px;font-size:.72rem;font-weight:700;}
.arch-rev{color:var(--green);font-weight:600;}
.arch-time{font-size:.72rem;color:var(--muted);margin-left:auto;}
.arch-btn{background:transparent;border:1.5px solid var(--border);border-radius:8px;padding:4px 10px;font-size:.75rem;cursor:pointer;color:var(--muted);}
.arch-btn:hover{border-color:var(--blue);color:var(--blue);}

/* BTN overrides */
.btn{display:inline-flex;align-items:center;gap:5px;border-radius:99px;padding:8px 18px;font-size:.82rem;font-weight:700;text-decoration:none;border:none;cursor:pointer;transition:all .2s;font-family:'Sarabun',sans-serif;}
.btn-primary{background:var(--blue);color:#fff;}
.btn-primary:hover{background:#0d47a1;}
.btn-navy{background:var(--navy);color:#fff;}
.btn-navy:hover{background:#1a3a5c;}
.btn-ghost{background:transparent;border:1.5px solid var(--border);color:var(--muted);}
.btn-ghost:hover{border-color:var(--blue);color:var(--blue);}
.btn-danger{background:var(--red);color:#fff;}
.btn-danger:hover{background:#b91c1c;}
.btn-sm{padding:5px 12px;font-size:.78rem;border-radius:8px;}
.btn-success{background:var(--green);color:#fff;}
.btn-success:hover{background:#166534;}

.empty-state{text-align:center;padding:40px;color:var(--muted);font-size:.88rem;}

@media(max-width:640px){
  .bq-banner{flex-direction:column;padding:18px 16px;}
  .form-grid{grid-template-columns:1fr;}
  .form-grid .full,.form-grid .half{grid-column:1/-1;}
  .form-card-body{padding:16px;}
  table.bq-table th:nth-child(1),table.bq-table td:nth-child(1){display:none;}
}
</style>

<div class="main">
<div class="bq-wrap">

  <!-- BANNER -->
  <div class="bq-banner">
    <div class="bq-banner-left">
      <h1>🚣 จัดการคิวพายเรือ</h1>
      <p>เพิ่ม / แก้ไข / เปิด-ปิดการจอง · คิวรีเซ็ตทุกเที่ยงคืน</p>
    </div>
    <div class="bq-banner-right">
      <a href="admin_boat_bookings.php" class="btn btn-ghost btn-sm" style="border-color:rgba(255,255,255,.3);color:#fff;">📋 รายการจอง</a>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="bq-alert bq-alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
    <?= $message_type === 'success' ? '✅' : '⚠️' ?> <?= h($message) ?>
  </div>
  <?php endif; ?>

  <!-- ═══ FORM ═══ -->
  <div class="form-card">
    <div class="form-card-header">
      <span style="font-size:1.2rem;"><?= $editQueue ? '✏️' : '➕' ?></span>
      <h2><?= $editQueue ? 'แก้ไขคิว: ' . h($editQueue['queue_name']) : 'เพิ่มคิวพายเรือใหม่' ?></h2>
    </div>
    <div class="form-card-body">
      <form method="POST" enctype="multipart/form-data" id="queueForm">
        <input type="hidden" name="action" value="<?= $editQueue ? 'edit' : 'add' ?>">
        <?php if ($editQueue): ?>
          <input type="hidden" name="id" value="<?= (int)$editQueue['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="image_path_current" value="<?= h($editQueue['image_path'] ?? '') ?>" id="imgPathCurrent">

        <div class="form-grid">
          <div class="fg">
            <label>ชื่อคิว *</label>
            <input type="text" name="queue_name" required value="<?= h($editQueue['queue_name'] ?? '') ?>" placeholder="เช่น รอบเช้า / ทัวร์ชมธรรมชาติ">
          </div>
          <div class="fg">
            <label>วันที่ *</label>
            <input type="date" name="queue_date" required value="<?= h($editQueue['queue_date'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="fg">
            <label>ราคา / ลำ (บาท · 0 = ฟรี)</label>
            <input type="number" name="price_per_boat" min="0" step="1" value="<?= (int)($editQueue['price_per_boat'] ?? 0) ?>">
          </div>
          <div class="fg">
            <label>สถานะการจอง</label>
            <input type="hidden" name="status" id="statusInput" value="<?= h($editQueue['status'] ?? 'show') ?>">
            <div class="toggle-wrap">
              <div class="toggle-btn <?= ($editQueue['status'] ?? 'show') === 'show' ? 'active-open' : '' ?>" onclick="setStatus('show',this)">
                🟢 เปิดการจอง
              </div>
              <div class="toggle-btn <?= ($editQueue['status'] ?? 'show') === 'hide' ? 'active-close' : '' ?>" onclick="setStatus('hide',this)">
                🔴 ปิดการจอง
              </div>
            </div>
          </div>
          <div class="fg full">
            <label>คำอธิบาย</label>
            <textarea name="description" placeholder="รายละเอียดเพิ่มเติม..."><?= h($editQueue['description'] ?? '') ?></textarea>
          </div>
          <div class="fg full">
            <label>ประเภทเรือ (คั่นด้วยจุลภาค)</label>
            <input type="text" name="boat_types" value="<?= h($editQueue['boat_types'] ?? 'เรือพาย,เรือคายัค,เรือบด') ?>" placeholder="เรือพาย,เรือคายัค,เรือบด">
            <div class="fg-hint">ลูกค้าจะเห็นตัวเลือกเรือตามที่กำหนด</div>
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
          <button class="btn btn-primary" type="submit">
            <?= $editQueue ? '💾 บันทึกการแก้ไข' : '➕ เพิ่มคิว' ?>
          </button>
          <?php if ($editQueue): ?>
            <a href="<?= $currentPage ?>" class="btn btn-ghost">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ TABLE ═══ -->
  <div class="list-card">
    <div class="list-card-header">
      <div class="list-card-title">คิวทั้งหมด (<?= $result->num_rows ?>)</div>
      <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="🔍 ค้นหาชื่อคิว..." value="<?= h($search) ?>">
        <button class="btn btn-ghost btn-sm" type="submit">ค้นหา</button>
        <?php if ($search): ?><a href="<?= $currentPage ?>" class="btn btn-ghost btn-sm">ล้าง</a><?php endif; ?>
      </form>
    </div>
    <div style="overflow-x:auto;">
      <table class="bq-table">
        <thead>
          <tr>
            <th>#</th><th>รูป</th><th>ชื่อคิว</th><th>วันที่</th><th>ราคา/ลำ</th><th>การจอง</th><th>สถานะ</th><th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
          <tr><td colspan="8" class="empty-state">📋 ยังไม่มีคิว กรุณาเพิ่มคิวใหม่</td></tr>
        <?php endif; ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem;"><?= (int)$row['id'] ?></td>
            <td>
              <?php if (!empty($row['image_path'])): ?>
                <img src="<?= h($row['image_path']) ?>" class="q-img" alt="" onerror="this.style.display='none'">
              <?php else: ?>
                <div class="q-img-ph">🚣</div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:700;font-size:.88rem;"><?= h($row['queue_name']) ?></div>
              <?php if (!empty($row['description'])): ?>
              <div style="font-size:.75rem;color:var(--muted);margin-top:2px;"><?= h(mb_substr($row['description'],0,40)) ?>…</div>
              <?php endif; ?>
            </td>
            <td><?= date('d/m/Y', strtotime($row['queue_date'])) ?></td>
            <td>
              <?php if ((float)$row['price_per_boat'] > 0): ?>
                <span style="font-weight:700;color:var(--blue);">฿<?= number_format((float)$row['price_per_boat']) ?></span>
              <?php else: ?>
                <span style="color:var(--green);font-weight:600;">ฟรี</span>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-weight:700;"><?= (int)$row['booking_count'] ?></span>
              <span style="font-size:.75rem;color:var(--muted);"> รายการ</span>
            </td>
            <td>
              <?php if ($row['status'] === 'show'): ?>
                <span class="badge-open">🟢 เปิดจอง</span>
              <?php else: ?>
                <span class="badge-close">🔴 ปิดจอง</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="action-btns">
                <a href="?edit_id=<?= $row['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <input type="hidden" name="cur_status" value="<?= h($row['status']) ?>">
                  <button class="btn btn-sm <?= $row['status']==='show'?'btn-danger':'btn-success' ?>" type="submit">
                    <?= $row['status']==='show'?'🔴 ปิด':'🟢 เปิด' ?>
                  </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('ลบคิวนี้?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:var(--red);" type="submit">🗑</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══ ARCHIVE ═══ -->
  <div class="archive-card">
    <div class="archive-header">
      <div class="archive-title">🗄️ ข้อมูลจัดเก็บรายวัน</div>
      <form method="POST" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="action" value="manual_archive">
        <input type="date" name="archive_date" value="<?= $yesterday ?>" style="border:1.5px solid var(--border);border-radius:8px;padding:6px 10px;font-family:'Sarabun',sans-serif;font-size:.82rem;outline:none;">
        <button class="btn btn-navy btn-sm" type="submit">📥 จัดเก็บวันที่เลือก</button>
      </form>
    </div>
    <?php if ($archives->num_rows === 0): ?>
      <div class="empty-state">ยังไม่มีข้อมูลจัดเก็บ (จะ archive อัตโนมัติทุกเที่ยงคืน)</div>
    <?php else: ?>
      <?php while ($arch = $archives->fetch_assoc()): ?>
      <div class="archive-row">
        <div class="arch-date">📅 <?= date('d/m/Y', strtotime($arch['archive_date'])) ?></div>
        <span class="arch-count"><?= (int)$arch['total_queues'] ?> คิว</span>
        <?php if ((float)$arch['total_revenue'] > 0): ?>
        <span class="arch-rev">฿<?= number_format((float)$arch['total_revenue']) ?></span>
        <?php endif; ?>
        <span class="arch-time">จัดเก็บ <?= date('d/m H:i', strtotime($arch['archived_at'])) ?></span>
        <a href="admin_boat_archive_view.php?date=<?= urlencode($arch['archive_date']) ?>" class="arch-btn">ดูรายละเอียด →</a>
      </div>
      <?php endwhile; ?>
    <?php endif; ?>
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
function setStatus(val, el) {
    document.getElementById('statusInput').value = val;
    document.querySelectorAll('.toggle-btn').forEach(b => {
        b.classList.remove('active-open','active-close');
    });
    el.classList.add(val === 'show' ? 'active-open' : 'active-close');
}
</script>

<?php include 'admin_layout_bottom.php'; ?>
<?php $conn->close(); ?>
