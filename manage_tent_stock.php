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

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $tent_name   = trim($_POST['tent_name'] ?? '');
        $tent_type   = trim($_POST['tent_type'] ?? '');
        $capacity    = max(1, (int)($_POST['capacity'] ?? 4));
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
                $old = $conn->prepare("SELECT image_path FROM tents WHERE id=? LIMIT 1");
                $old->bind_param("i", $id); $old->execute();
                $oldImg = $old->get_result()->fetch_assoc()['image_path'] ?? '';
                $old->close();
                if ($image_path === '') $image_path = $oldImg;

                $st = $conn->prepare("UPDATE tents SET tent_name=?,tent_type=?,capacity=?,total_tents=?,description=?,image_path=?,status=? WHERE id=?");
                $st->bind_param("ssiisssi", $tent_name,$tent_type,$capacity,$total_tents,$description,$image_path,$status,$id);
                $message = "แก้ไขข้อมูลเต็นท์เรียบร้อยแล้ว";
            } else {
                $st = $conn->prepare("INSERT INTO tents (tent_name,tent_type,capacity,total_tents,description,image_path,status) VALUES (?,?,?,?,?,?,?)");
                $st->bind_param("ssiisss", $tent_name,$tent_type,$capacity,$total_tents,$description,$image_path,$status);
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

    header("Location: {$currentPage}?msg=".urlencode($message)."&type=".urlencode($message_type));
    exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }

$editTent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $er = $conn->prepare("SELECT * FROM tents WHERE id=? LIMIT 1");
    $er->bind_param("i", $editId); $er->execute();
    $editTent = $er->get_result()->fetch_assoc();
    $er->close();
}

$tentsResult = $conn->query("SELECT * FROM tents ORDER BY id DESC");
$totalTents  = $tentsResult ? $tentsResult->num_rows : 0;
$showCount   = (int)$conn->query("SELECT COUNT(*) c FROM tents WHERE status='show'")->fetch_assoc()['c'];

$pageTitle = "จัดเก็บเต็นท์"; $activeMenu = "tent_stock";
include 'admin_layout_top.php';
?>
<style>
:root{
  --gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);
  --ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;
  --danger:#dc2626;--success:#16a34a;--blue:#1d4ed8;
}

/* LAYOUT */
.ts-layout{display:grid;grid-template-columns:360px 1fr;gap:24px;align-items:start;}

/* FORM CARD */
.ts-form-card{background:var(--card);border-radius:20px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);overflow:hidden;position:sticky;top:20px;}
.ts-form-head{
  background:linear-gradient(135deg,var(--ink) 0%,#2a2a4a 100%);
  padding:20px 24px;color:#fff;
}
.ts-form-head-title{font-size:1rem;font-weight:700;}
.ts-form-head-sub{font-size:.7rem;opacity:.55;margin-top:2px;}
.ts-form-body{padding:22px;}

.ts-sec{font-size:.6rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase;
  color:var(--muted);margin:16px 0 9px;display:flex;align-items:center;gap:8px;}
.ts-sec::after{content:'';flex:1;height:1px;background:var(--border);}
.ts-sec:first-child{margin-top:0;}

.ts-fg{margin-bottom:12px;}
.ts-fg label{display:block;font-size:.68rem;font-weight:700;letter-spacing:.07em;
  text-transform:uppercase;color:var(--muted);margin-bottom:5px;}
.ts-fg input,.ts-fg textarea,.ts-fg select{
  width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);
  background:#fafaf8;outline:none;transition:border-color .2s,box-shadow .2s;
}
.ts-fg input:focus,.ts-fg textarea:focus,.ts-fg select:focus{
  border-color:var(--gold);background:#fff;
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.ts-fg textarea{min-height:70px;resize:vertical;}
.ts-row2{display:grid;grid-template-columns:1fr 1fr;gap:11px;}

/* Upload */
.ts-upload{border:2px dashed var(--border);border-radius:10px;padding:16px;
  text-align:center;cursor:pointer;transition:.2s;position:relative;}
.ts-upload:hover{border-color:var(--gold);background:var(--gold-dim);}
.ts-upload input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.ts-upload-ico{font-size:1.5rem;}
.ts-upload-txt{font-size:.75rem;color:var(--muted);margin-top:3px;}
.ts-img-preview{border-radius:10px;overflow:hidden;margin-top:8px;display:none;}
.ts-img-preview.show{display:block;}
.ts-img-preview img{width:100%;height:130px;object-fit:cover;display:block;}

/* Buttons */
.ts-btn-row{display:flex;gap:9px;margin-top:18px;}
.ts-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:10px 18px;border:none;border-radius:9px;
  font-family:'Sarabun',sans-serif;font-size:.83rem;font-weight:700;
  cursor:pointer;text-decoration:none;transition:all .2s;}
.ts-btn-save{background:var(--ink);color:#fff;flex:1;}
.ts-btn-save:hover{background:#2a2a4a;}
.ts-btn-cancel{background:var(--bg);color:var(--muted);border:1.5px solid var(--border);}
.ts-btn-cancel:hover{border-color:var(--gold);color:var(--gold);}

/* LIST */
.ts-list-head{
  background:var(--card);border-radius:20px 20px 0 0;
  padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
}
.ts-list-title{font-size:.9rem;font-weight:800;color:var(--ink);
  display:flex;align-items:center;gap:8px;}
.ts-list-title::before{content:'';width:3px;height:14px;background:var(--gold);border-radius:2px;display:inline-block;}
.ts-cnt{background:var(--gold-dim);color:#a07c3a;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}

.ts-cards{background:var(--card);border-radius:0 0 20px 20px;padding:14px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);
  display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:13px;}
.t-card{border-radius:14px;border:1.5px solid var(--border);overflow:hidden;transition:.2s;}
.t-card:hover{box-shadow:0 8px 24px rgba(26,26,46,.1);transform:translateY(-2px);}
.t-card-img{height:130px;object-fit:cover;width:100%;display:block;background:#f1ede8;}
.t-card-img-ph{height:130px;background:linear-gradient(135deg,#f1ede8,#e8e4de);
  display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--border);}
.t-card-body{padding:12px 14px;}
.t-card-name{font-weight:800;color:var(--ink);font-size:.88rem;}
.t-card-type{font-size:.7rem;color:var(--muted);margin-top:2px;}
.t-chips{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0;}
.t-chip{display:inline-flex;align-items:center;gap:3px;
  font-size:.67rem;font-weight:600;padding:3px 8px;border-radius:99px;
  background:#f1ede8;color:var(--muted);}
.t-card-foot{display:flex;align-items:center;justify-content:space-between;
  padding:9px 14px;border-top:1px solid var(--border);background:#fdfcfa;}
.t-status{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;}
.t-dot{width:7px;height:7px;border-radius:50%;display:inline-block;}
.t-dot-show{background:var(--success);}
.t-dot-hide{background:var(--danger);}

.t-act-edit{display:inline-flex;align-items:center;gap:3px;
  padding:5px 10px;border-radius:7px;font-size:.72rem;font-weight:700;
  background:var(--ink);color:#fff;text-decoration:none;border:none;cursor:pointer;transition:.15s;}
.t-act-edit:hover{background:#2a2a4a;}
.t-act-toggle{padding:5px 10px;border-radius:7px;font-size:.72rem;font-weight:700;
  background:#fffbeb;color:#d97706;border:none;cursor:pointer;transition:.15s;}
.t-act-toggle:hover{background:#fef3c7;}
.t-act-del{display:inline-flex;align-items:center;justify-content:center;
  width:30px;height:30px;border-radius:7px;font-size:.8rem;
  background:#fef2f2;color:var(--danger);border:1.5px solid #fecaca;cursor:pointer;transition:.15s;}
.t-act-del:hover{background:var(--danger);color:#fff;border-color:var(--danger);}

.ts-empty{padding:40px;text-align:center;color:var(--muted);grid-column:1/-1;}
.ts-alert{display:flex;align-items:center;gap:10px;padding:12px 18px;
  border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:22px;}
.ts-alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.ts-alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}
.mif{display:inline;}

@media(max-width:1100px){.ts-layout{grid-template-columns:1fr;}}
@media(max-width:600px){.ts-row2{grid-template-columns:1fr;}}
</style>

<?php if ($message !== ''): ?>
<div class="ts-alert <?= $message_type==='error'?'ts-alert-err':'ts-alert-ok' ?>">
  <?= $message_type==='error'?'⚠':'✓' ?> <?= h($message) ?>
</div>
<?php endif; ?>

<div class="ts-layout">

  <!-- ── FORM ── -->
  <div class="ts-form-card">
    <div class="ts-form-head">
      <div class="ts-form-head-title"><?= $editTent ? '✏️ แก้ไขเต็นท์' : '➕ เพิ่มเต็นท์ใหม่' ?></div>
      <div class="ts-form-head-sub"><?= $editTent ? 'ID #'.$editTent['id'] : 'กรอกข้อมูลเต็นท์ที่ต้องการเพิ่ม' ?></div>
    </div>
    <div class="ts-form-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editTent ? (int)$editTent['id'] : 0 ?>">

        <div class="ts-sec">ข้อมูลพื้นฐาน</div>

        <div class="ts-fg">
          <label>ชื่อเต็นท์ *</label>
          <input type="text" name="tent_name" placeholder="เช่น เต็นท์โดม A"
                 value="<?= h($editTent['tent_name'] ?? '') ?>" required>
        </div>

        <div class="ts-fg">
          <label>ประเภทเต็นท์</label>
          <input type="text" name="tent_type" placeholder="โดม, ครอบครัว, อุโมงค์..."
                 value="<?= h($editTent['tent_type'] ?? '') ?>">
        </div>

        <div class="ts-sec">ความจุ</div>

        <div class="ts-row2">
          <div class="ts-fg">
            <label>รองรับ (คน)</label>
            <input type="number" name="capacity" min="1"
                   value="<?= (int)($editTent['capacity'] ?? 4) ?>">
          </div>
          <div class="ts-fg">
            <label>จำนวนหลังทั้งหมด</label>
            <input type="number" name="total_tents" min="1"
                   value="<?= (int)($editTent['total_tents'] ?? 5) ?>">
          </div>
        </div>

        <div class="ts-fg">
          <label>รายละเอียด</label>
          <textarea name="description" placeholder="รายละเอียดเพิ่มเติม..."><?= h($editTent['description'] ?? '') ?></textarea>
        </div>

        <div class="ts-sec">การแสดงผลและรูปภาพ</div>

        <div class="ts-fg">
          <label>สถานะ</label>
          <select name="status">
            <option value="show" <?= ($editTent['status']??'show')==='show'?'selected':'' ?>>✅ แสดงให้ลูกค้าเห็น</option>
            <option value="hide" <?= ($editTent['status']??'')==='hide'?'selected':'' ?>>🙈 ซ่อน</option>
          </select>
        </div>

        <div class="ts-fg">
          <label>รูปภาพเต็นท์</label>
          <div class="ts-upload" id="uploadZone">
            <input type="file" name="image" accept="image/*" onchange="prevImg(this)">
            <div class="ts-upload-ico">🖼️</div>
            <div class="ts-upload-txt" id="uploadTxt">ลากหรือ <b style="color:var(--gold)">คลิกเลือกไฟล์</b></div>
          </div>
        </div>

        <div class="ts-img-preview <?= !empty($editTent['image_path'])?'show':'' ?>" id="imgPreview">
          <img src="<?= h($editTent['image_path']??'') ?>" id="previewImg" alt="">
        </div>
        <input type="hidden" name="image_path" value="<?= h($editTent['image_path']??'') ?>">

        <div class="ts-btn-row">
          <button type="submit" class="ts-btn ts-btn-save">
            <?= $editTent ? '💾 อัปเดตข้อมูล' : '➕ บันทึกเต็นท์' ?>
          </button>
          <?php if ($editTent): ?>
            <a href="manage_tent_stock.php" class="ts-btn ts-btn-cancel">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- ── LIST ── -->
  <div>
    <div class="ts-list-head">
      <div class="ts-list-title">รายการเต็นท์ทั้งหมด</div>
      <span class="ts-cnt"><?= $totalTents ?> หลัง · แสดง <?= $showCount ?></span>
    </div>

    <?php if ($tentsResult) $tentsResult->data_seek(0); ?>
    <?php if ($totalTents > 0): ?>
    <div class="ts-cards">
      <?php while ($row = $tentsResult->fetch_assoc()):
        $isShow = ($row['status']??'show') === 'show';
      ?>
      <div class="t-card">
        <?php if (!empty($row['image_path'])): ?>
          <img src="<?= h($row['image_path']) ?>" class="t-card-img"
               onerror="this.src='uploads/no-image.png'" alt="">
        <?php else: ?>
          <div class="t-card-img-ph">⛺</div>
        <?php endif; ?>
        <div class="t-card-body">
          <div class="t-card-name"><?= h($row['tent_name']) ?></div>
          <div class="t-card-type"><?= h($row['tent_type']?:'-') ?></div>
          <div class="t-chips">
            <span class="t-chip">⛺ <?= (int)$row['total_tents'] ?> หลัง</span>
            <span class="t-chip">👥 <?= (int)$row['capacity'] ?> คน</span>
          </div>
          <?php if (!empty($row['description'])): ?>
            <div style="font-size:.72rem;color:var(--muted);margin-top:2px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
              <?= h($row['description']) ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="t-card-foot">
          <span class="t-status">
            <span class="t-dot <?= $isShow?'t-dot-show':'t-dot-hide' ?>"></span>
            <?= $isShow?'แสดงอยู่':'ซ่อนอยู่' ?>
          </span>
          <div style="display:flex;gap:5px;align-items:center;">
            <a class="t-act-edit" href="?edit=<?= (int)$row['id'] ?>">✏️</a>
            <form method="POST" class="mif">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="t-act-toggle"><?= $isShow?'🙈':'👁' ?></button>
            </form>
            <form method="POST" class="mif" onsubmit="return confirm('ลบเต็นท์นี้?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="t-act-del">✕</button>
            </form>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="ts-cards">
      <div class="ts-empty">
        <div style="font-size:2.5rem;opacity:.25;margin-bottom:10px;">⛺</div>
        <div>ยังไม่มีข้อมูลเต็นท์<br>เพิ่มได้จากฟอร์มด้านซ้าย</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function prevImg(input) {
  const preview = document.getElementById('imgPreview');
  const img     = document.getElementById('previewImg');
  const txt     = document.getElementById('uploadTxt');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; preview.classList.add('show'); };
    reader.readAsDataURL(input.files[0]);
    txt.innerHTML = '<b style="color:var(--gold)">' + input.files[0].name + '</b>';
  }
}
</script>

<?php $conn->close(); include 'admin_layout_bottom.php'; ?>
