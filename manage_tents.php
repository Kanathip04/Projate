<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php"); exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* === สร้างตาราง tents === */
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

/* === สร้างตาราง tent_equipment === */
$conn->query("CREATE TABLE IF NOT EXISTS `tent_equipment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `price` DECIMAL(10,2) DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT '',
    `note` VARCHAR(500) DEFAULT '',
    `sort_order` INT DEFAULT 0,
    `is_available` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* === seed ข้อมูลเริ่มต้นอุปกรณ์ === */
$eqCount = (int)$conn->query("SELECT COUNT(*) c FROM tent_equipment")->fetch_assoc()['c'];
if ($eqCount === 0) {
    $defaults = [
        ['เต็นท์ 1-2 คน',   100, 'หลัง', '', 1],
        ['เต็นท์ 3-4 คน',   150, 'หลัง', '', 2],
        ['เก้าอี้',          30,  'ตัว',  '', 3],
        ['โต๊ะ',             30,  'ตัว',  '', 4],
        ['เบาะรองนอน',       30,  'อัน',  '', 5],
        ['หมอน',             30,  'ใบ',   '', 6],
        ['ชุดเก้าอี้สนาม',  120, 'ชุด',  'เก้าอี้สนาม 4 ตัว / โต๊ะ 1 ตัว', 7],
        ['เครื่องนอน 1 ชุด',100, 'ชุด',  'ผ้าปูรองนอน 1 ผืน / ผ้าห่ม 1 ผืน / หมอน 2 ใบ', 8],
    ];
    $ins = $conn->prepare("INSERT INTO tent_equipment (name,price,unit,note,sort_order) VALUES (?,?,?,?,?)");
    foreach ($defaults as $d) {
        $ins->bind_param("sdssi", $d[0], $d[1], $d[2], $d[3], $d[4]);
        $ins->execute();
    }
    $ins->close();
}

$message = ''; $message_type = 'success';
$currentPage = basename($_SERVER['PHP_SELF']);
$scrollToEq  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* เพิ่ม/แก้ไขเต็นท์ */
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

        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $uploadDir = 'uploads/tents/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = 'tent_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename))
                    $image_path = $uploadDir . $filename;
            }
        }

        if ($tent_name === '') {
            $message = "กรุณากรอกชื่อเต็นท์"; $message_type = "error";
        } else {
            if ($id > 0) {
                $st = $conn->prepare("UPDATE tents SET tent_name=?,tent_type=?,capacity=?,price_per_night=?,total_tents=?,description=?,image_path=?,status=? WHERE id=?");
                $st->bind_param("ssidisssi", $tent_name,$tent_type,$capacity,$price,$total_tents,$description,$image_path,$status,$id);
                $message = "แก้ไขข้อมูลเต็นท์เรียบร้อยแล้ว";
            } else {
                $st = $conn->prepare("INSERT INTO tents (tent_name,tent_type,capacity,price_per_night,total_tents,description,image_path,status) VALUES (?,?,?,?,?,?,?,?)");
                $st->bind_param("ssidisss", $tent_name,$tent_type,$capacity,$price,$total_tents,$description,$image_path,$status);
                $message = "เพิ่มเต็นท์เรียบร้อยแล้ว";
            }
            $st->execute(); $st->close();
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $conn->prepare("DELETE FROM tents WHERE id=?");
            $st->bind_param("i", $id); $st->execute(); $st->close();
            $message = "ลบเต็นท์เรียบร้อยแล้ว";
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE tents SET status=IF(status='show','hide','show') WHERE id={$id}");
            $message = "เปลี่ยนสถานะเรียบร้อยแล้ว";
        }
    }

    /* อุปกรณ์ให้เช่า */
    if ($action === 'save_equipment') {
        $eq_id    = (int)($_POST['eq_id'] ?? 0);
        $eq_name  = trim($_POST['eq_name'] ?? '');
        $eq_price = max(0, (float)($_POST['eq_price'] ?? 0));
        $eq_unit  = trim($_POST['eq_unit'] ?? '');
        $eq_note  = trim($_POST['eq_note'] ?? '');
        if ($eq_name !== '') {
            if ($eq_id > 0) {
                $st = $conn->prepare("UPDATE tent_equipment SET name=?,price=?,unit=?,note=? WHERE id=?");
                $st->bind_param("sdssi", $eq_name,$eq_price,$eq_unit,$eq_note,$eq_id);
            } else {
                $st = $conn->prepare("INSERT INTO tent_equipment (name,price,unit,note) VALUES (?,?,?,?)");
                $st->bind_param("sdss", $eq_name,$eq_price,$eq_unit,$eq_note);
            }
            $st->execute(); $st->close();
            $message = "บันทึกรายการอุปกรณ์เรียบร้อยแล้ว";
        }
        $scrollToEq = true;
    }

    if ($action === 'delete_equipment') {
        $eq_id = (int)($_POST['eq_id'] ?? 0);
        if ($eq_id > 0) {
            $st = $conn->prepare("DELETE FROM tent_equipment WHERE id=?");
            $st->bind_param("i", $eq_id); $st->execute(); $st->close();
            $message = "ลบรายการอุปกรณ์เรียบร้อยแล้ว";
        }
        $scrollToEq = true;
    }

    $qs = "msg=".urlencode($message)."&type=".urlencode($message_type);
    if ($scrollToEq) $qs .= "&eq=1";
    header("Location: {$currentPage}?{$qs}"); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }
$scrollToEq = isset($_GET['eq']);

/* โหมดแก้ไขเต็นท์ */
$editTent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $er = $conn->prepare("SELECT * FROM tents WHERE id=? LIMIT 1");
    $er->bind_param("i", $editId); $er->execute();
    $editTent = $er->get_result()->fetch_assoc();
    $er->close();
}

/* โหมดแก้ไขอุปกรณ์ */
$editEq = null;
if (isset($_GET['eq_edit'])) {
    $eqEditId = (int)$_GET['eq_edit'];
    $er2 = $conn->prepare("SELECT * FROM tent_equipment WHERE id=? LIMIT 1");
    $er2->bind_param("i", $eqEditId); $er2->execute();
    $editEq = $er2->get_result()->fetch_assoc();
    $er2->close();
    $scrollToEq = true;
}

$tentsResult    = $conn->query("SELECT * FROM tents ORDER BY id DESC");
$totalTents     = $tentsResult ? $tentsResult->num_rows : 0;
$showCount      = (int)$conn->query("SELECT COUNT(*) c FROM tents WHERE status='show'")->fetch_assoc()['c'];
$equipmentList  = $conn->query("SELECT * FROM tent_equipment ORDER BY sort_order, id");

$pageTitle = "จัดการเต็นท์"; $activeMenu = "tent_manage";
include 'admin_layout_top.php';
?>
<style>
:root{
  --gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);--gold-border:rgba(201,169,110,.3);
  --ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;
  --danger:#dc2626;--success:#16a34a;--blue:#1d4ed8;
}

/* STATS */
.tm-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:28px;}
.tm-stat{background:var(--card);border-radius:14px;padding:16px 20px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
  display:flex;align-items:center;gap:14px;border-left:3px solid var(--gold);}
.tm-stat-icon{font-size:1.6rem;flex-shrink:0;}
.tm-stat-val{font-size:1.5rem;font-weight:900;color:var(--ink);line-height:1;}
.tm-stat-lbl{font-size:.7rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-top:2px;}

/* LAYOUT */
.tm-layout{display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start;margin-bottom:32px;}

/* FORM CARD */
.tm-form-card{background:var(--card);border-radius:20px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);overflow:hidden;position:sticky;top:20px;}
.tm-form-head{
  background:linear-gradient(135deg,var(--ink) 0%,#2a2a4a 100%);
  padding:20px 24px;color:#fff;
}
.tm-form-head-title{font-size:1.05rem;font-weight:700;}
.tm-form-head-sub{font-size:.7rem;opacity:.55;margin-top:2px;}
.tm-form-body{padding:22px;}

.tm-sec-lbl{
  font-size:.6rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase;
  color:var(--muted);margin:16px 0 9px;display:flex;align-items:center;gap:8px;
}
.tm-sec-lbl::after{content:'';flex:1;height:1px;background:var(--border);}
.tm-sec-lbl:first-child{margin-top:0;}

.tm-fg{margin-bottom:13px;}
.tm-fg label{display:block;font-size:.68rem;font-weight:700;letter-spacing:.07em;
  text-transform:uppercase;color:var(--muted);margin-bottom:5px;}
.tm-fg input,.tm-fg textarea,.tm-fg select{
  width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);
  background:#fafaf8;outline:none;transition:border-color .2s,box-shadow .2s;
}
.tm-fg input:focus,.tm-fg textarea:focus,.tm-fg select:focus{
  border-color:var(--gold);background:#fff;
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.tm-fg textarea{min-height:72px;resize:vertical;}
.tm-row2{display:grid;grid-template-columns:1fr 1fr;gap:11px;}

/* Upload */
.tm-upload{
  border:2px dashed var(--border);border-radius:10px;padding:16px;
  text-align:center;cursor:pointer;transition:.2s;position:relative;
}
.tm-upload:hover{border-color:var(--gold);background:var(--gold-dim);}
.tm-upload input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.tm-upload-ico{font-size:1.5rem;}
.tm-upload-txt{font-size:.75rem;color:var(--muted);margin-top:3px;}
.tm-img-preview{border-radius:10px;overflow:hidden;margin-top:8px;display:none;}
.tm-img-preview.show{display:block;}
.tm-img-preview img{width:100%;height:130px;object-fit:cover;display:block;}

/* Buttons */
.tm-btn-row{display:flex;gap:9px;margin-top:18px;}
.tm-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:10px 18px;border:none;border-radius:9px;
  font-family:'Sarabun',sans-serif;font-size:.83rem;font-weight:700;
  cursor:pointer;text-decoration:none;transition:all .2s;
}
.tm-btn-save{background:var(--ink);color:#fff;flex:1;}
.tm-btn-save:hover{background:#2a2a4a;}
.tm-btn-cancel{background:var(--bg);color:var(--muted);border:1.5px solid var(--border);}
.tm-btn-cancel:hover{border-color:var(--gold);color:var(--gold);}

/* TENT CARDS */
.tm-list-head{
  background:var(--card);border-radius:20px 20px 0 0;
  padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
}
.tm-list-title{font-size:.9rem;font-weight:800;color:var(--ink);
  display:flex;align-items:center;gap:8px;}
.tm-list-title::before{content:'';width:3px;height:14px;background:var(--gold);border-radius:2px;display:inline-block;}
.tm-cnt-badge{background:var(--gold-dim);color:#a07c3a;font-size:.7rem;font-weight:700;
  padding:3px 10px;border-radius:20px;}
.tm-cards{
  background:var(--card);border-radius:0 0 20px 20px;padding:14px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);
  display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:13px;
}
.tent-card{border-radius:14px;border:1.5px solid var(--border);overflow:hidden;transition:.2s;}
.tent-card:hover{box-shadow:0 8px 24px rgba(26,26,46,.1);transform:translateY(-2px);}
.tent-card-img{height:130px;object-fit:cover;width:100%;display:block;background:#f1ede8;}
.tent-card-img-ph{height:130px;background:linear-gradient(135deg,#f1ede8,#e8e4de);
  display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:var(--border);}
.tent-card-body{padding:12px 14px;}
.tent-card-name{font-weight:800;color:var(--ink);font-size:.88rem;}
.tent-card-type{font-size:.7rem;color:var(--muted);margin-top:1px;}
.tent-card-chips{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0;}
.t-chip{
  display:inline-flex;align-items:center;gap:3px;
  font-size:.67rem;font-weight:600;padding:3px 8px;border-radius:99px;
  background:#f1ede8;color:var(--muted);
}
.t-chip-price{background:rgba(201,169,110,.15);color:#a07c3a;}
.tent-card-foot{
  display:flex;align-items:center;justify-content:space-between;
  padding:9px 14px;border-top:1px solid var(--border);background:#fdfcfa;
}
.tm-dot{width:7px;height:7px;border-radius:50%;display:inline-block;}
.tm-dot-show{background:var(--success);}
.tm-dot-hide{background:var(--danger);}
.tm-status-lbl{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;}
.tm-act-edit{
  display:inline-flex;align-items:center;gap:3px;
  padding:4px 9px;border-radius:7px;font-size:.7rem;font-weight:700;
  background:#eff6ff;color:var(--blue);text-decoration:none;transition:.15s;
}
.tm-act-edit:hover{background:#dbeafe;}
.tm-act-del{
  display:inline-flex;align-items:center;gap:3px;
  padding:4px 9px;border-radius:7px;font-size:.7rem;font-weight:700;
  background:#fef2f2;color:var(--danger);border:none;cursor:pointer;transition:.15s;
}
.tm-act-del:hover{background:#fee2e2;}
.tm-act-toggle{
  padding:4px 9px;border-radius:7px;font-size:.7rem;font-weight:700;
  background:#fffbeb;color:#d97706;border:none;cursor:pointer;transition:.15s;
}
.tm-act-toggle:hover{background:#fef3c7;}

/* EQUIPMENT SECTION */
.eq-section{background:var(--card);border-radius:20px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);overflow:hidden;margin-bottom:32px;}
.eq-header{
  background:linear-gradient(135deg,#14532d,#166534);
  padding:20px 26px;display:flex;align-items:center;justify-content:space-between;
}
.eq-header-left h2{font-size:1rem;font-weight:800;color:#fff;margin:0 0 3px;}
.eq-header-left p{font-size:.72rem;color:rgba(255,255,255,.7);margin:0;}
.eq-body{padding:24px;}
.eq-layout{display:grid;grid-template-columns:320px 1fr;gap:22px;align-items:start;}

/* Equipment form */
.eq-form-card{background:#f9fafb;border:1.5px solid var(--border);border-radius:14px;padding:18px;}
.eq-form-title{font-size:.78rem;font-weight:800;color:var(--ink);margin-bottom:14px;
  display:flex;align-items:center;gap:6px;}
.eq-fg{margin-bottom:11px;}
.eq-fg label{display:block;font-size:.66rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--muted);margin-bottom:4px;}
.eq-fg input{
  width:100%;padding:8px 11px;border:1.5px solid var(--border);border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.85rem;color:var(--ink);
  background:#fff;outline:none;transition:border-color .2s;
}
.eq-fg input:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.1);}
.eq-row2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.eq-btn-save{
  width:100%;padding:9px;background:#16a34a;color:#fff;border:none;border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.83rem;font-weight:700;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:5px;transition:.2s;
}
.eq-btn-save:hover{background:#15803d;}
.eq-btn-cancel{
  width:100%;margin-top:6px;padding:8px;background:transparent;color:var(--muted);
  border:1.5px solid var(--border);border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:600;cursor:pointer;
  text-decoration:none;display:flex;align-items:center;justify-content:center;transition:.2s;
}
.eq-btn-cancel:hover{border-color:var(--gold);color:var(--gold);}

/* Equipment table */
.eq-table-wrap{overflow-x:auto;}
.eq-table{width:100%;border-collapse:collapse;}
.eq-table thead th{
  padding:10px 14px;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;
  color:var(--muted);border-bottom:2px solid var(--border);text-align:left;font-weight:700;
}
.eq-table tbody td{
  padding:11px 14px;font-size:.85rem;color:var(--ink);border-bottom:1px solid var(--border);
  vertical-align:middle;
}
.eq-table tbody tr:last-child td{border-bottom:none;}
.eq-table tbody tr:hover{background:#f9fafb;}
.eq-name{font-weight:700;}
.eq-note{font-size:.72rem;color:var(--muted);margin-top:2px;}
.eq-price{font-weight:800;color:#16a34a;white-space:nowrap;}
.eq-unit{font-size:.72rem;color:var(--muted);}
.eq-avail-dot{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;}
.eq-actions{display:flex;gap:5px;}
.eq-act-edit{
  padding:4px 10px;border-radius:6px;font-size:.7rem;font-weight:700;
  background:#eff6ff;color:var(--blue);text-decoration:none;transition:.15s;
}
.eq-act-edit:hover{background:#dbeafe;}
.eq-act-del{
  padding:4px 10px;border-radius:6px;font-size:.7rem;font-weight:700;
  background:#fef2f2;color:var(--danger);border:none;cursor:pointer;transition:.15s;
}
.eq-act-del:hover{background:#fee2e2;}
.eq-empty{padding:32px;text-align:center;color:var(--muted);font-size:.85rem;}

/* Alert */
.tm-alert{display:flex;align-items:center;gap:10px;padding:12px 18px;
  border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:22px;}
.tm-alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.tm-alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

.tm-empty{padding:40px;text-align:center;color:var(--muted);grid-column:1/-1;}
.tm-empty-ico{font-size:2.2rem;opacity:.3;margin-bottom:8px;}
.miniform{display:inline;}

@media(max-width:1100px){.tm-layout{grid-template-columns:1fr;}}
@media(max-width:768px){
  .tm-stats{grid-template-columns:1fr 1fr;}
  .eq-layout{grid-template-columns:1fr;}
  .tm-row2{grid-template-columns:1fr;}
}
</style>

<?php if ($message !== ''): ?>
<div class="tm-alert <?= $message_type==='error' ? 'tm-alert-err' : 'tm-alert-ok' ?>">
  <?= $message_type==='error' ? '⚠' : '✓' ?> <?= h($message) ?>
</div>
<?php endif; ?>

<!-- Main Layout -->
<div class="tm-layout">

  <!-- Form -->
  <div class="tm-form-card">
    <div class="tm-form-head">
      <div class="tm-form-head-title"><?= $editTent ? '✏️ แก้ไขเต็นท์' : '➕ เพิ่มเต็นท์ใหม่' ?></div>
      <div class="tm-form-head-sub"><?= $editTent ? 'กำลังแก้ไข ID #'.$editTent['id'] : 'กรอกข้อมูลเต็นท์ที่ต้องการเพิ่ม' ?></div>
    </div>
    <div class="tm-form-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editTent ? (int)$editTent['id'] : 0 ?>">

        <div class="tm-sec-lbl">ข้อมูลพื้นฐาน</div>

        <div class="tm-fg">
          <label>ชื่อเต็นท์ *</label>
          <input type="text" name="tent_name" placeholder="เช่น เต็นท์โดม A"
                 value="<?= h($editTent['tent_name'] ?? '') ?>" required>
        </div>

        <div class="tm-fg">
          <label>ประเภทเต็นท์</label>
          <input type="text" name="tent_type" placeholder="โดม, ครอบครัว..."
                 value="<?= h($editTent['tent_type'] ?? '') ?>">
        </div>
        <input type="hidden" name="price_per_night" value="<?= number_format((float)($editTent['price_per_night'] ?? 0), 2, '.', '') ?>">

        <div class="tm-sec-lbl">ความจุ</div>

        <div class="tm-row2">
          <div class="tm-fg">
            <label>รองรับ (คน)</label>
            <input type="number" name="capacity" min="1" value="<?= (int)($editTent['capacity'] ?? 4) ?>">
          </div>
          <div class="tm-fg">
            <label>จำนวนเต็นท์ทั้งหมด</label>
            <input type="number" name="total_tents" min="1" value="<?= (int)($editTent['total_tents'] ?? 5) ?>">
          </div>
        </div>

        <div class="tm-fg">
          <label>รายละเอียด</label>
          <textarea name="description" placeholder="รายละเอียดเต็นท์..."><?= h($editTent['description'] ?? '') ?></textarea>
        </div>

        <div class="tm-sec-lbl">การแสดงผลและรูปภาพ</div>

        <div class="tm-fg">
          <label>สถานะ</label>
          <select name="status">
            <option value="show" <?= ($editTent['status'] ?? 'show')==='show' ? 'selected' : '' ?>>✅ แสดงให้ลูกค้าเห็น</option>
            <option value="hide" <?= ($editTent['status'] ?? '')==='hide' ? 'selected' : '' ?>>🙈 ซ่อน</option>
          </select>
        </div>

        <div class="tm-fg">
          <label>รูปภาพเต็นท์</label>
          <div class="tm-upload" id="uploadZone">
            <input type="file" name="image" accept="image/*" onchange="previewImg(this)">
            <div class="tm-upload-ico">🖼️</div>
            <div class="tm-upload-txt" id="uploadTxt">ลากหรือ <b style="color:var(--gold)">คลิกเลือกไฟล์</b></div>
          </div>
        </div>

        <div class="tm-img-preview <?= !empty($editTent['image_path']) ? 'show' : '' ?>" id="imgPreview">
          <img src="<?= h($editTent['image_path'] ?? '') ?>" id="previewImg" alt="">
        </div>
        <input type="hidden" name="image_path" value="<?= h($editTent['image_path'] ?? '') ?>">

        <div class="tm-btn-row">
          <button type="submit" class="tm-btn tm-btn-save">
            <?= $editTent ? '💾 อัปเดตข้อมูล' : '➕ บันทึกเต็นท์' ?>
          </button>
          <?php if ($editTent): ?>
            <a href="manage_tents.php" class="tm-btn tm-btn-cancel">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Tent Cards -->
  <div>
    <div class="tm-list-head">
      <div class="tm-list-title">รายการเต็นท์ทั้งหมด</div>
      <span class="tm-cnt-badge"><?= $totalTents ?> หลัง</span>
    </div>
    <?php if ($tentsResult) $tentsResult->data_seek(0); ?>
    <?php if ($totalTents > 0): ?>
    <div class="tm-cards">
      <?php while ($row = $tentsResult->fetch_assoc()):
        $isShow = ($row['status'] ?? 'show') === 'show';
      ?>
      <div class="tent-card">
        <?php if (!empty($row['image_path'])): ?>
          <img src="<?= h($row['image_path']) ?>" class="tent-card-img"
               onerror="this.src='uploads/no-image.png'" alt="">
        <?php else: ?>
          <div class="tent-card-img-ph">⛺</div>
        <?php endif; ?>
        <div class="tent-card-body">
          <div class="tent-card-name"><?= h($row['tent_name']) ?></div>
          <div class="tent-card-type"><?= h($row['tent_type'] ?: '-') ?></div>
          <div class="tent-card-chips">
            <span class="t-chip">⛺ <?= (int)$row['total_tents'] ?> หลัง</span>
            <span class="t-chip">👥 <?= (int)$row['capacity'] ?> คน</span>
          </div>
        </div>
        <div class="tent-card-foot">
          <span class="tm-status-lbl">
            <span class="tm-dot <?= $isShow ? 'tm-dot-show' : 'tm-dot-hide' ?>"></span>
            <?= $isShow ? 'แสดงอยู่' : 'ซ่อนอยู่' ?>
          </span>
          <div style="display:flex;gap:5px;align-items:center;">
            <a class="tm-act-edit" href="?edit=<?= (int)$row['id'] ?>">✏️</a>
            <form method="POST" class="miniform">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="tm-act-toggle"><?= $isShow ? '🙈' : '👁' ?></button>
            </form>
            <form method="POST" class="miniform" onsubmit="return confirm('ลบเต็นท์นี้?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="tm-act-del">🗑</button>
            </form>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="tm-cards">
      <div class="tm-empty">
        <div class="tm-empty-ico">⛺</div>
        <div>ยังไม่มีข้อมูลเต็นท์<br>เพิ่มได้จากฟอร์มด้านซ้าย</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Equipment Section -->
<div class="eq-section" id="eq-section">
  <div class="eq-header">
    <div class="eq-header-left">
      <h2>🛕 อุปกรณ์ให้เช่า</h2>
      <p>จัดการรายการอุปกรณ์และราคาให้เช่าสำหรับผู้เข้าพัก</p>
    </div>
  </div>
  <div class="eq-body">
    <div class="eq-layout">

      <!-- Equipment Form -->
      <div>
        <div class="eq-form-card">
          <div class="eq-form-title">
            <?= $editEq ? '✏️ แก้ไขรายการ' : '➕ เพิ่มรายการ' ?>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="save_equipment">
            <input type="hidden" name="eq_id" value="<?= $editEq ? (int)$editEq['id'] : 0 ?>">

            <div class="eq-fg">
              <label>ชื่ออุปกรณ์</label>
              <input type="text" name="eq_name" placeholder="เช่น เก้าอี้, หมอน..."
                     value="<?= h($editEq['name'] ?? '') ?>" required>
            </div>

            <div class="eq-row2">
              <div class="eq-fg">
                <label>ราคา (บาท)</label>
                <input type="number" name="eq_price" min="0" step="0.01"
                       value="<?= h($editEq ? number_format((float)$editEq['price'],2,'.','') : '0') ?>">
              </div>
              <div class="eq-fg">
                <label>หน่วย</label>
                <input type="text" name="eq_unit" placeholder="ตัว, ใบ, ชุด..."
                       value="<?= h($editEq['unit'] ?? '') ?>">
              </div>
            </div>

            <div class="eq-fg">
              <label>หมายเหตุ (ถ้ามี)</label>
              <input type="text" name="eq_note" placeholder="รายละเอียดเพิ่มเติม..."
                     value="<?= h($editEq['note'] ?? '') ?>">
            </div>

            <button type="submit" class="eq-btn-save">
              <?= $editEq ? '💾 บันทึกการแก้ไข' : '➕ เพิ่มรายการ' ?>
            </button>
            <?php if ($editEq): ?>
              <a href="manage_tents.php?eq=1" class="eq-btn-cancel">ยกเลิก</a>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <!-- Equipment Table -->
      <div class="eq-table-wrap">
        <table class="eq-table">
          <thead>
            <tr>
              <th>รายการอุปกรณ์</th>
              <th>ราคา</th>
              <th>หน่วย</th>
              <th style="width:100px;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($equipmentList && $equipmentList->num_rows > 0): ?>
              <?php while ($eq = $equipmentList->fetch_assoc()): ?>
              <tr>
                <td>
                  <div class="eq-name"><?= h($eq['name']) ?></div>
                  <?php if (!empty($eq['note'])): ?>
                    <div class="eq-note"><?= h($eq['note']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="eq-price">฿<?= number_format((float)$eq['price'], 0) ?>.-</td>
                <td class="eq-unit">/ <?= h($eq['unit']) ?></td>
                <td>
                  <div class="eq-actions">
                    <a href="?eq_edit=<?= (int)$eq['id'] ?>#eq-section" class="eq-act-edit">✏️</a>
                    <form method="POST" class="miniform"
                          onsubmit="return confirm('ลบรายการนี้?')">
                      <input type="hidden" name="action" value="delete_equipment">
                      <input type="hidden" name="eq_id" value="<?= (int)$eq['id'] ?>">
                      <button class="eq-act-del">🗑</button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="4"><div class="eq-empty">ยังไม่มีรายการอุปกรณ์</div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<script>
function previewImg(input) {
  const preview = document.getElementById('imgPreview');
  const img     = document.getElementById('previewImg');
  const txt     = document.getElementById('uploadTxt');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; preview.classList.add('show'); };
    reader.readAsDataURL(input.files[0]);
    txt.innerHTML = '<b style="color:var(--gold);">' + input.files[0].name + '</b>';
  }
}
<?php if ($scrollToEq): ?>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('eq-section')?.scrollIntoView({behavior:'smooth', block:'start'});
});
<?php endif; ?>
</script>

<?php $conn->close(); include 'admin_layout_bottom.php'; ?>
