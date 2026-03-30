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

/* ── CARD ── */
.eq-card{background:var(--card);border-radius:18px;
  box-shadow:0 2px 16px rgba(26,26,46,.07);overflow:hidden;margin-bottom:24px;}

/* ── HEADER ── */
.eq-head{
  padding:18px 24px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px;
}
.eq-head-title{font-size:1rem;font-weight:800;color:var(--ink);
  display:flex;align-items:center;gap:8px;}
.eq-head-title::before{content:'';display:inline-block;width:3px;height:16px;
  background:#16a34a;border-radius:2px;}
.eq-cnt{background:#dcfce7;color:#166534;font-size:.72rem;font-weight:700;
  padding:3px 10px;border-radius:20px;}

/* ── ADD FORM ROW ── */
.eq-add-bar{
  display:grid;
  grid-template-columns:2fr 120px 100px 2fr auto;
  gap:10px;align-items:end;
  padding:16px 24px;border-bottom:2px solid var(--border);
  background:#f9fafb;
}
.eq-add-bar.edit-mode{background:#fffbeb;border-bottom-color:#fde68a;}
.f-grp{display:flex;flex-direction:column;gap:4px;}
.f-grp label{font-size:.65rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:var(--muted);}
.f-grp input{
  padding:9px 11px;border:1.5px solid var(--border);border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);
  background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;
  width:100%;
}
.f-grp input:focus{
  border-color:#16a34a;
  box-shadow:0 0 0 3px rgba(22,163,74,.1);
}
.edit-mode .f-grp input:focus{border-color:#d97706;box-shadow:0 0 0 3px rgba(217,119,6,.1);}
.eq-add-btn{
  padding:9px 18px;border:none;border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.85rem;font-weight:700;
  cursor:pointer;white-space:nowrap;transition:.2s;
  display:flex;align-items:center;gap:5px;
}
.eq-add-btn.btn-add{background:#16a34a;color:#fff;}
.eq-add-btn.btn-add:hover{background:#15803d;}
.eq-add-btn.btn-edit{background:#d97706;color:#fff;}
.eq-add-btn.btn-edit:hover{background:#b45309;}
.eq-cancel-link{
  display:block;text-align:center;margin-top:6px;
  font-size:.75rem;color:var(--muted);text-decoration:none;cursor:pointer;
}
.eq-cancel-link:hover{color:var(--danger);}

/* ── TABLE ── */
.eq-table-wrap{overflow-x:auto;}
.eq-table{width:100%;border-collapse:collapse;}
.eq-table thead th{
  padding:11px 20px;font-size:.67rem;letter-spacing:.1em;text-transform:uppercase;
  color:var(--muted);border-bottom:1.5px solid var(--border);
  text-align:left;font-weight:700;background:#fdfcfa;
}
.eq-table tbody td{
  padding:13px 20px;font-size:.9rem;color:var(--ink);
  border-bottom:1px solid var(--border);vertical-align:middle;
}
.eq-table tbody tr:last-child td{border-bottom:none;}
.eq-table tbody tr:hover{background:#f9fafb;}
.eq-table tbody tr.active-edit{background:#fffbeb;}
.eq-num{
  display:inline-block;width:22px;height:22px;border-radius:50%;
  background:#f1ede8;color:var(--muted);
  font-size:.7rem;font-weight:800;text-align:center;line-height:22px;
  margin-right:6px;flex-shrink:0;
}
.eq-name-wrap{display:flex;align-items:center;}
.eq-name{font-weight:700;}
.eq-note{font-size:.75rem;color:var(--muted);margin-top:2px;}
.eq-price-cell{font-size:1rem;font-weight:800;color:#16a34a;white-space:nowrap;}
.eq-unit-cell{font-size:.82rem;color:var(--muted);}
.eq-actions{display:flex;gap:6px;align-items:center;}
.eq-btn-edit{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 14px;border-radius:8px;font-size:.78rem;font-weight:700;
  background:var(--ink);color:#fff;border:none;cursor:pointer;
  text-decoration:none;transition:all .18s;letter-spacing:.02em;
}
.eq-btn-edit:hover{background:#2a2a4a;transform:translateY(-1px);box-shadow:0 4px 10px rgba(26,26,46,.18);}
.eq-btn-del{
  display:inline-flex;align-items:center;justify-content:center;
  width:32px;height:32px;border-radius:8px;font-size:.85rem;
  background:#fef2f2;color:var(--danger);border:1.5px solid #fecaca;cursor:pointer;transition:all .18s;
}
.eq-btn-del:hover{background:var(--danger);color:#fff;border-color:var(--danger);transform:translateY(-1px);}
.eq-empty-row td{padding:36px;text-align:center;color:var(--muted);font-size:.9rem;}

/* Alert */
.tm-alert{display:flex;align-items:center;gap:10px;padding:12px 18px;
  border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:18px;}
.tm-alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.tm-alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

.miniform{display:inline;}

@media(max-width:900px){
  .eq-add-bar{grid-template-columns:1fr 1fr;gap:8px;}
  .eq-add-btn{grid-column:1/-1;justify-content:center;}
}
</style>

<?php if ($message !== ''): ?>
<div class="tm-alert <?= $message_type==='error' ? 'tm-alert-err' : 'tm-alert-ok' ?>">
  <?= $message_type==='error' ? '⚠' : '✓' ?> <?= h($message) ?>
</div>
<?php endif; ?>
<?php
  $eqCount2 = $equipmentList ? $equipmentList->num_rows : 0;
?>
<div class="eq-card" id="eq-section">

  <!-- Header -->
  <div class="eq-head">
    <div class="eq-head-title">⛺ อุปกรณ์ให้เช่า</div>
    <span class="eq-cnt"><?= $eqCount2 ?> รายการ</span>
  </div>

  <!-- Add / Edit Form Row -->
  <form method="POST">
    <input type="hidden" name="action" value="save_equipment">
    <input type="hidden" name="eq_id" value="<?= $editEq ? (int)$editEq['id'] : 0 ?>">
    <div class="eq-add-bar <?= $editEq ? 'edit-mode' : '' ?>">
      <div class="f-grp">
        <label>ชื่ออุปกรณ์ *</label>
        <input type="text" name="eq_name" placeholder="เช่น เก้าอี้, หมอน, เต็นท์..."
               value="<?= h($editEq['name'] ?? '') ?>" required autofocus>
      </div>
      <div class="f-grp">
        <label>ราคา (บาท)</label>
        <input type="number" name="eq_price" min="0" step="1" placeholder="0"
               value="<?= $editEq ? (int)$editEq['price'] : '' ?>">
      </div>
      <div class="f-grp">
        <label>หน่วย</label>
        <input type="text" name="eq_unit" placeholder="ตัว / ใบ / ชุด"
               value="<?= h($editEq['unit'] ?? '') ?>">
      </div>
      <div class="f-grp">
        <label>หมายเหตุ</label>
        <input type="text" name="eq_note" placeholder="รายละเอียดเพิ่มเติม (ไม่บังคับ)"
               value="<?= h($editEq['note'] ?? '') ?>">
      </div>
      <div class="f-grp">
        <label>&nbsp;</label>
        <button type="submit" class="eq-add-btn <?= $editEq ? 'btn-edit' : 'btn-add' ?>">
          <?= $editEq ? '💾 บันทึก' : '➕ เพิ่ม' ?>
        </button>
        <?php if ($editEq): ?>
          <a href="manage_tents.php" class="eq-cancel-link">✕ ยกเลิก</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Table -->
  <div class="eq-table-wrap">
    <table class="eq-table">
      <thead>
        <tr>
          <th style="width:40px;">#</th>
          <th>รายการ</th>
          <th>ราคา</th>
          <th>หน่วย</th>
          <th>หมายเหตุ</th>
          <th style="width:120px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($equipmentList && $equipmentList->num_rows > 0):
          $equipmentList->data_seek(0); $rowNum = 1;
          while ($eq = $equipmentList->fetch_assoc()):
            $isActiveEdit = $editEq && (int)$editEq['id'] === (int)$eq['id'];
        ?>
        <tr class="<?= $isActiveEdit ? 'active-edit' : '' ?>">
          <td style="color:var(--muted);font-size:.78rem;font-weight:700;"><?= $rowNum++ ?></td>
          <td>
            <div class="eq-name"><?= h($eq['name']) ?></div>
          </td>
          <td class="eq-price-cell">฿<?= number_format((float)$eq['price'], 0) ?>.-</td>
          <td class="eq-unit-cell">/ <?= h($eq['unit']) ?></td>
          <td style="color:var(--muted);font-size:.8rem;"><?= h($eq['note'] ?: '—') ?></td>
          <td>
            <div class="eq-actions">
              <a href="?eq_edit=<?= (int)$eq['id'] ?>#eq-section" class="eq-btn-edit">
                ✏️ แก้ไข
              </a>
              <form method="POST" class="miniform" onsubmit="return confirm('ลบรายการนี้?')">
                <input type="hidden" name="action" value="delete_equipment">
                <input type="hidden" name="eq_id" value="<?= (int)$eq['id'] ?>">
                <button class="eq-btn-del" title="ลบ">✕</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php else: ?>
        <tr class="eq-empty-row"><td colspan="6">ยังไม่มีรายการ — กรอกข้อมูลด้านบนแล้วกด ➕ เพิ่ม</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
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
