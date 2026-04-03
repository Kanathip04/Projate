<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$pageTitle  = "จัดการห้องพัก";
$activeMenu = "rooms";

// ── Settings table ──
$conn->query("CREATE TABLE IF NOT EXISTS `room_settings` (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` VARCHAR(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("INSERT IGNORE INTO room_settings (`key`,`value`) VALUES ('checkout_restore_time','12:00')");

// ── บันทึกเวลาคืนสถานะ ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_restore_time'])) {
    $rt = trim($_POST['restore_time'] ?? '12:00');
    if (preg_match('/^\d{2}:\d{2}$/', $rt)) {
        $conn->query("UPDATE room_settings SET `value`='$rt' WHERE `key`='checkout_restore_time'");
    }
    header("Location: manage_rooms.php"); exit;
}

// ── ดึงเวลาคืนสถานะ ──
$restoreTime = $conn->query("SELECT `value` FROM room_settings WHERE `key`='checkout_restore_time'")->fetch_assoc()['value'] ?? '12:00';
$nowTime = date('H:i');
$today   = date('Y-m-d');

// ── Auto-restore: archive booking ที่ checkout_date ผ่านไปแล้ว หรือ วันนี้และถึงเวลาแล้ว ──
$restoreCondition = "
    (checkout_date < '$today')
    OR (checkout_date = '$today' AND '$nowTime' >= '$restoreTime')
";
$autoRestored = (int)$conn->query(
    "SELECT COUNT(*) c FROM room_bookings
     WHERE ($restoreCondition)
     AND booking_status IN ('approved','pending')
     AND (archived IS NULL OR archived=0)"
)->fetch_assoc()['c'];
if ($autoRestored > 0) {
    $conn->query(
        "UPDATE room_bookings SET archived=1
         WHERE ($restoreCondition)
         AND booking_status IN ('approved','pending')
         AND (archived IS NULL OR archived=0)"
    );
}

$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId   = (int)$_GET['edit'];
    $stmtEdit = $conn->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    if ($stmtEdit) {
        $stmtEdit->bind_param("i", $editId);
        $stmtEdit->execute();
        $res = $stmtEdit->get_result();
        if ($res && $res->num_rows > 0) $editData = $res->fetch_assoc();
        $stmtEdit->close();
    }
}

// เพิ่ม column checkin_time / checkout_time ถ้ายังไม่มี
foreach (['checkin_time VARCHAR(10) DEFAULT "14:00"', 'checkout_time VARCHAR(10) DEFAULT "12:00"'] as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM rooms LIKE '$colName'");
    if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE rooms ADD COLUMN `$colName` $colDef");
}
$rooms = $conn->query("SELECT * FROM rooms ORDER BY id DESC");
$totalRooms = $rooms ? $rooms->num_rows : 0;
$showCount  = $rooms ? (int)$conn->query("SELECT COUNT(*) c FROM rooms WHERE status='show'")->fetch_assoc()['c'] : 0;

include 'admin_layout_top.php';
?>
<style>
:root{
  --gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);--gold-border:rgba(201,169,110,.3);
  --ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;
  --danger:#dc2626;--success:#16a34a;--blue:#1d4ed8;
}

/* ── STATS ROW ── */
.rm-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:28px;}
.rm-stat{background:var(--card);border-radius:14px;padding:16px 20px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
  display:flex;align-items:center;gap:14px;border-left:3px solid var(--gold);}
.rm-stat-icon{font-size:1.6rem;flex-shrink:0;}
.rm-stat-val{font-size:1.5rem;font-weight:900;color:var(--ink);line-height:1;}
.rm-stat-lbl{font-size:.7rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-top:2px;}

/* ── LAYOUT ── */
.rm-layout{display:grid;grid-template-columns:400px 1fr;gap:24px;align-items:start;}

/* ── FORM CARD ── */
.rm-form-card{background:var(--card);border-radius:20px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);overflow:hidden;position:sticky;top:20px;}
.rm-form-head{
  background:linear-gradient(135deg,var(--ink) 0%,#2a2a4a 100%);
  padding:22px 24px;color:#fff;
}
.rm-form-head-title{font-family:'Playfair Display',serif;font-size:1.15rem;font-style:italic;font-weight:600;}
.rm-form-head-sub{font-size:.72rem;opacity:.55;margin-top:3px;}

.rm-form-body{padding:24px;}
.rm-section-label{
  font-size:.62rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase;
  color:var(--muted);margin:18px 0 10px;display:flex;align-items:center;gap:8px;
}
.rm-section-label::after{content:'';flex:1;height:1px;background:var(--border);}
.rm-section-label:first-child{margin-top:0;}

.rm-fg{margin-bottom:14px;}
.rm-fg label{
  display:block;font-size:.7rem;font-weight:700;letter-spacing:.08em;
  text-transform:uppercase;color:var(--muted);margin-bottom:6px;
}
.rm-fg input,.rm-fg textarea,.rm-fg select{
  width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);
  background:#fafaf8;outline:none;transition:border-color .2s,box-shadow .2s;
}
.rm-fg input:focus,.rm-fg textarea:focus,.rm-fg select:focus{
  border-color:var(--gold);background:#fff;
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.rm-fg textarea{min-height:80px;resize:vertical;}
.rm-row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.rm-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}

/* File upload */
.rm-upload{
  border:2px dashed var(--border);border-radius:12px;padding:20px;
  text-align:center;cursor:pointer;transition:.2s;position:relative;
}
.rm-upload:hover{border-color:var(--gold);background:var(--gold-dim);}
.rm-upload input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.rm-upload-ico{font-size:1.8rem;margin-bottom:5px;}
.rm-upload-txt{font-size:.78rem;color:var(--muted);}
.rm-upload-txt b{color:var(--gold);}

/* Preview */
.rm-img-preview{border-radius:12px;overflow:hidden;border:1.5px solid var(--border);
  margin-top:10px;position:relative;display:none;}
.rm-img-preview.show{display:block;}
.rm-img-preview img{width:100%;height:150px;object-fit:cover;display:block;}
.rm-img-badge{
  position:absolute;top:8px;left:8px;background:rgba(26,26,46,.72);
  color:#fff;font-size:.62rem;padding:3px 9px;border-radius:20px;letter-spacing:.06em;
}

/* Buttons */
.rm-btn-row{display:flex;gap:10px;margin-top:20px;}
.rm-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:7px;
  padding:11px 20px;border:none;border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:.85rem;font-weight:700;
  cursor:pointer;text-decoration:none;transition:all .2s;
}
.rm-btn-save{background:var(--ink);color:#fff;flex:1;}
.rm-btn-save:hover{background:#2a2a4a;transform:translateY(-1px);box-shadow:0 6px 16px rgba(26,26,46,.2);}
.rm-btn-cancel{background:var(--bg);color:var(--muted);border:1.5px solid var(--border);}
.rm-btn-cancel:hover{border-color:var(--gold);color:var(--gold);}

/* ── ROOM GRID ── */
.rm-list-head{
  background:var(--card);border-radius:20px 20px 0 0;
  padding:18px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
}
.rm-list-title{font-size:.92rem;font-weight:800;color:var(--ink);
  display:flex;align-items:center;gap:8px;}
.rm-list-title::before{content:'';width:3px;height:14px;background:var(--gold);border-radius:2px;display:inline-block;}
.rm-cnt-badge{background:var(--gold-dim);color:#a07c3a;font-size:.7rem;font-weight:700;
  padding:3px 10px;border-radius:20px;}

.rm-cards{
  background:var(--card);border-radius:0 0 20px 20px;padding:16px;
  box-shadow:0 4px 24px rgba(26,26,46,.08);
  display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;
}
.room-card{
  border-radius:14px;border:1.5px solid var(--border);overflow:hidden;
  transition:box-shadow .2s,transform .2s;
}
.room-card:hover{box-shadow:0 8px 24px rgba(26,26,46,.1);transform:translateY(-2px);}
.room-card-img{
  height:140px;object-fit:cover;width:100%;display:block;
  background:#f1ede8;
}
.room-card-img-ph{
  height:140px;background:linear-gradient(135deg,#f1ede8,#e8e4de);
  display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:var(--border);
}
.room-card-body{padding:14px 16px;}
.room-card-name{font-weight:800;color:var(--ink);font-size:.92rem;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.room-card-type{font-size:.72rem;color:var(--muted);margin-top:2px;}
.room-card-meta{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0;}
.room-meta-chip{
  display:inline-flex;align-items:center;gap:4px;
  font-size:.7rem;font-weight:600;padding:3px 9px;border-radius:99px;
  background:#f1ede8;color:var(--muted);
}
.chip-price{background:rgba(201,169,110,.15);color:#a07c3a;}
.room-card-foot{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 16px;border-top:1px solid var(--border);background:#fdfcfa;
}
.rm-status-dot{
  display:inline-flex;align-items:center;gap:5px;
  font-size:.72rem;font-weight:700;
}
.dot{width:7px;height:7px;border-radius:50%;display:inline-block;}
.dot-show{background:var(--success);}
.dot-hide{background:var(--danger);}
.rm-act-edit{
  display:inline-flex;align-items:center;gap:4px;
  padding:5px 10px;border-radius:7px;font-size:.72rem;font-weight:700;
  background:#eff6ff;color:var(--blue);text-decoration:none;transition:.15s;
}
.rm-act-edit:hover{background:#dbeafe;}
.rm-act-del{
  display:inline-flex;align-items:center;gap:4px;
  padding:5px 10px;border-radius:7px;font-size:.72rem;font-weight:700;
  background:#fef2f2;color:var(--danger);text-decoration:none;transition:.15s;
}
.rm-act-del:hover{background:#fee2e2;}

.rm-empty{padding:48px;text-align:center;color:var(--muted);}
.rm-empty-ico{font-size:2.5rem;opacity:.3;margin-bottom:10px;}

/* Amenity checkboxes */
.rm-amenity-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.am-chip{
  display:flex;align-items:center;gap:6px;padding:7px 10px;
  border-radius:10px;border:1.5px solid var(--border);background:#fafaf8;
  font-size:.78rem;font-weight:600;color:var(--muted);cursor:pointer;
  transition:.15s;user-select:none;
}
.am-chip input{display:none;}
.am-chip.checked{border-color:var(--gold);background:var(--gold-dim);color:var(--ink);}
.am-chip:hover{border-color:var(--gold);}

/* Alert */
.rm-alert{display:flex;align-items:center;gap:10px;padding:13px 18px;
  border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:20px;
  animation:fadeIn .3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.rm-alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.rm-alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

@media(max-width:1100px){.rm-layout{grid-template-columns:1fr;}}
@media(max-width:600px){.rm-stats{grid-template-columns:1fr 1fr;}.rm-row2,.rm-row3{grid-template-columns:1fr;}}
</style>

<?php if (!empty($_SESSION['room_msg'])): ?>
<div class="rm-alert <?= $_SESSION['room_msg_type']==='success'?'rm-alert-ok':'rm-alert-err' ?>">
  <?= $_SESSION['room_msg_type']==='success'?'✓':'⚠' ?>
  <?= htmlspecialchars($_SESSION['room_msg']) ?>
</div>
<?php unset($_SESSION['room_msg'],$_SESSION['room_msg_type']); ?>
<?php endif; ?>

<?php if ($autoRestored > 0): ?>
<div class="rm-alert rm-alert-ok" style="margin-bottom:16px;">
  ♻️ คืนสถานะอัตโนมัติ: ห้องพักจาก <strong><?= $autoRestored ?> การจอง</strong> ที่ checkout ผ่านไปแล้ว ถูกคืนเป็น "ว่าง" แล้ว
</div>
<?php endif; ?>

<!-- Stats -->
<div class="rm-stats">
  <div class="rm-stat" style="border-left-color:#1d6fad;">
    <div class="rm-stat-icon">🏨</div>
    <div>
      <div class="rm-stat-val"><?= $totalRooms ?></div>
      <div class="rm-stat-lbl">ห้องทั้งหมด</div>
    </div>
  </div>
  <div class="rm-stat" style="border-left-color:var(--success);">
    <div class="rm-stat-icon">✅</div>
    <div>
      <div class="rm-stat-val"><?= $showCount ?></div>
      <div class="rm-stat-lbl">กำลังแสดง</div>
    </div>
  </div>
  <div class="rm-stat" style="border-left-color:var(--danger);">
    <div class="rm-stat-icon">🙈</div>
    <div>
      <div class="rm-stat-val"><?= $totalRooms - $showCount ?></div>
      <div class="rm-stat-lbl">ซ่อนอยู่</div>
    </div>
  </div>
</div>

<!-- ── Restore Time Setting ── -->
<div style="background:#fff;border-radius:14px;padding:16px 22px;margin-bottom:20px;
     box-shadow:0 2px 12px rgba(26,26,46,.06);border-left:4px solid #f59e0b;
     display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
  <div style="font-size:1.4rem;">⏰</div>
  <div style="flex:1;">
    <div style="font-weight:800;color:var(--ink);font-size:.92rem;">เวลาคืนสถานะห้องพักอัตโนมัติ</div>
    <div style="font-size:.76rem;color:var(--muted);margin-top:2px;">
      ห้องที่ checkout วันนี้จะถูกคืนสถานะเป็น "ว่าง" เมื่อถึงเวลาที่กำหนด · ปัจจุบัน: <strong><?= $restoreTime ?> น.</strong> · เวลาเซิร์ฟเวอร์: <?= $nowTime ?> น.
    </div>
  </div>
  <form method="POST" style="display:flex;align-items:center;gap:8px;">
    <input type="hidden" name="save_restore_time" value="1">
    <input type="time" name="restore_time" value="<?= htmlspecialchars($restoreTime, ENT_QUOTES) ?>"
           style="padding:8px 12px;border:1.5px solid #e8e4de;border-radius:8px;
                  font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);">
    <button type="submit"
            style="padding:8px 16px;background:var(--ink);color:#fff;border:none;border-radius:8px;
                   font-family:'Sarabun',sans-serif;font-size:.84rem;font-weight:700;cursor:pointer;">
      💾 บันทึก
    </button>
  </form>
</div>

<div class="rm-layout">

  <!-- ── FORM ── -->
  <div class="rm-form-card">
    <div class="rm-form-head">
      <div class="rm-form-head-title"><?= $editData ? '✏️ แก้ไขห้องพัก' : '➕ เพิ่มห้องพักใหม่' ?></div>
      <div class="rm-form-head-sub"><?= $editData ? 'กำลังแก้ไข ID #'.$editData['id'] : 'กรอกข้อมูลห้องพักที่ต้องการเพิ่ม' ?></div>
    </div>
    <div class="rm-form-body">
      <form action="save_room.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id'] ?? '') ?>">

        <div class="rm-section-label">ข้อมูลพื้นฐาน</div>

        <div class="rm-fg">
          <label>ชื่อห้องพัก</label>
          <input type="text" name="room_name" required placeholder="เช่น Deluxe Suite 101"
                 value="<?= htmlspecialchars($editData['room_name'] ?? '') ?>">
        </div>

        <div class="rm-row2">
          <div class="rm-fg">
            <label>ประเภทห้อง</label>
            <input type="text" name="room_type" required placeholder="VIP, Standard"
                   value="<?= htmlspecialchars($editData['room_type'] ?? '') ?>">
          </div>
          <div class="rm-fg">
            <label>ราคา / คืน (฿)</label>
            <input type="number" step="0.01" name="price" min="0" required
                   value="<?= htmlspecialchars($editData['price'] ?? '0') ?>">
          </div>
        </div>

        <div class="rm-fg">
          <label>รายละเอียด</label>
          <textarea name="description" placeholder="สิ่งอำนวยความสะดวก, วิว, บริการ..."><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
        </div>

        <div class="rm-section-label">เวลาเช็คอิน / เช็คเอาท์</div>
        <div class="rm-row2">
          <div class="rm-fg">
            <label>เวลาเช็คอิน</label>
            <input type="time" name="checkin_time"
                   value="<?= htmlspecialchars($editData['checkin_time'] ?? '14:00') ?>">
          </div>
          <div class="rm-fg">
            <label>เวลาเช็คเอาท์</label>
            <input type="time" name="checkout_time"
                   value="<?= htmlspecialchars($editData['checkout_time'] ?? '12:00') ?>">
          </div>
        </div>

        <div class="rm-section-label">ความจุ</div>

<?php
  $savedBeds = array_filter(array_map('trim', explode('|', $editData['bed_type'] ?? '')));
  // รองรับทั้งรูปแบบเก่า "เตียงคู่:2" และใหม่ "เตียงคู่"
  $hasDouble = (strpos($editData['bed_type'] ?? '', 'เตียงคู่') !== false);
  $hasSingle = (strpos($editData['bed_type'] ?? '', 'เตียงเดี่ยว') !== false);
?>
        <input type="hidden" name="bed_type" id="bed_type_hidden" value="<?= htmlspecialchars($editData['bed_type'] ?? '') ?>">

        <div class="rm-row2">
          <div class="rm-fg">
            <label>จำนวนห้อง</label>
            <input type="number" name="total_rooms" min="1" required
                   value="<?= htmlspecialchars($editData['total_rooms'] ?? '1') ?>">
          </div>
          <div class="rm-fg">
            <label>ผู้เข้าพักสูงสุด</label>
            <input type="number" name="max_guests" min="1" required
                   value="<?= htmlspecialchars($editData['max_guests'] ?? '2') ?>">
          </div>
        </div>

        <div class="rm-section-label">ประเภทเตียง</div>
        <div class="rm-amenity-grid" style="grid-template-columns:1fr 1fr;">
          <label class="am-chip <?= $hasDouble ? 'checked' : '' ?>" onclick="toggleAmenity(this)">
            <input type="checkbox" data-bed="เตียงคู่" <?= $hasDouble ? 'checked' : '' ?>>
            🛏️ เตียงคู่
          </label>
          <label class="am-chip <?= $hasSingle ? 'checked' : '' ?>" onclick="toggleAmenity(this)">
            <input type="checkbox" data-bed="เตียงเดี่ยว" <?= $hasSingle ? 'checked' : '' ?>>
            🛌 เตียงเดี่ยว
          </label>
        </div>

<?php
  $amenityList = [
    ['แอร์','❄️'], ['TV','📺'], ['Wi-Fi','📶'],
    ['ตู้เย็น','🧊'], ['ห้องน้ำในตัว','🚿'],
    ['เครื่องทำน้ำอุ่น','🔥'], ['ระเบียง','🌅'],
  ];
  $savedAmenities = array_filter(array_map('trim', explode('|', $editData['amenities'] ?? '')));
?>
        <div class="rm-section-label">สิ่งอำนวยความสะดวก</div>
        <input type="hidden" name="amenities" id="amenities_hidden" value="<?= htmlspecialchars($editData['amenities'] ?? '') ?>">
        <div class="rm-amenity-grid">
          <?php foreach ($amenityList as [$amName, $amIcon]):
            $isChecked = in_array($amName, $savedAmenities, true);
          ?>
          <label class="am-chip <?= $isChecked ? 'checked' : '' ?>" onclick="toggleAmenity(this)">
            <input type="checkbox" value="<?= htmlspecialchars($amName) ?>" <?= $isChecked ? 'checked' : '' ?>>
            <?= $amIcon ?> <?= htmlspecialchars($amName) ?>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="rm-section-label">การแสดงผลและรูปภาพ</div>

        <div class="rm-fg">
          <label>สถานะ</label>
          <select name="status">
            <option value="show" <?= (($editData['status'] ?? 'show')==='show')?'selected':'' ?>>✅ แสดงให้ลูกค้าเห็น</option>
            <option value="hide" <?= (($editData['status'] ?? 'show')==='hide')?'selected':'' ?>>🙈 ซ่อน</option>
          </select>
        </div>

        <div class="rm-fg">
          <label>รูปห้องพัก</label>
          <div class="rm-upload" id="uploadZone">
            <input type="file" name="room_image" accept="image/*" onchange="previewImg(this)">
            <div class="rm-upload-ico">🖼️</div>
            <div class="rm-upload-txt">ลากหรือ <b>คลิกเพื่อเลือกไฟล์</b><br><span style="font-size:.68rem;">JPG, PNG, WEBP</span></div>
          </div>
        </div>

        <div class="rm-img-preview <?= !empty($editData['image_path'])?'show':'' ?>" id="imgPreview">
          <span class="rm-img-badge"><?= $editData ? 'รูปปัจจุบัน' : 'ตัวอย่าง' ?></span>
          <img src="<?= htmlspecialchars($editData['image_path'] ?? '') ?>" id="previewImg" alt="">
        </div>

        <div class="rm-btn-row">
          <button type="submit" class="rm-btn rm-btn-save">
            <?= $editData ? '💾 อัปเดตข้อมูล' : '➕ บันทึกห้องพัก' ?>
          </button>
          <?php if ($editData): ?>
            <a href="manage_rooms.php" class="rm-btn rm-btn-cancel">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- ── ROOM LIST ── -->
  <div>
    <div class="rm-list-head">
      <div class="rm-list-title">รายการห้องพักทั้งหมด</div>
      <span class="rm-cnt-badge"><?= $totalRooms ?> ห้อง</span>
    </div>

    <?php if ($rooms) $rooms->data_seek(0); ?>

    <?php if ($totalRooms > 0): ?>
    <div class="rm-cards">
      <?php while ($row = $rooms->fetch_assoc()):
        $isShow = ($row['status'] ?? 'show') === 'show';
      ?>
      <div class="room-card">
        <?php if (!empty($row['image_path'])): ?>
          <img src="<?= htmlspecialchars($row['image_path']) ?>" class="room-card-img" alt="">
        <?php else: ?>
          <div class="room-card-img-ph">🛏️</div>
        <?php endif; ?>
        <div class="room-card-body">
          <div class="room-card-name"><?= htmlspecialchars($row['room_name'] ?? '') ?></div>
          <div class="room-card-type"><?= htmlspecialchars($row['room_type'] ?? '') ?></div>
          <div class="room-card-meta">
            <span class="room-meta-chip chip-price">฿<?= number_format((float)($row['price'] ?? 0), 0) ?>/คืน</span>
            <span class="room-meta-chip">🏠 <?= (int)($row['total_rooms'] ?? 0) ?> ห้อง</span>
            <span class="room-meta-chip">👥 สูงสุด <?= (int)($row['max_guests'] ?? 0) ?> คน</span>
<?php
              $bt = $row['bed_type'] ?? '';
              $bchips = [];
              if (strpos($bt, 'เตียงคู่') !== false)   $bchips[] = '🛏️ เตียงคู่';
              if (strpos($bt, 'เตียงเดี่ยว') !== false) $bchips[] = '🛌 เตียงเดี่ยว';
              foreach ($bchips as $chip):
            ?>
            <span class="room-meta-chip"><?= htmlspecialchars($chip) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="room-card-foot">
          <span class="rm-status-dot">
            <span class="dot <?= $isShow?'dot-show':'dot-hide' ?>"></span>
            <?= $isShow ? 'แสดงอยู่' : 'ซ่อนอยู่' ?>
          </span>
          <div style="display:flex;gap:6px;">
            <a class="rm-act-edit" href="manage_rooms.php?edit=<?= (int)$row['id'] ?>">✏️ แก้ไข</a>
            <a class="rm-act-del" href="delete_room.php?id=<?= (int)$row['id'] ?>"
               onclick="return confirm('ยืนยันการลบห้องพักนี้?')">🗑 ลบ</a>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="rm-cards">
      <div class="rm-empty" style="grid-column:1/-1;">
        <div class="rm-empty-ico">🛏️</div>
        <div>ยังไม่มีข้อมูลห้องพัก<br>เพิ่มห้องพักแรกของคุณได้เลย</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function previewImg(input) {
  const preview = document.getElementById('imgPreview');
  const img     = document.getElementById('previewImg');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; preview.classList.add('show'); };
    reader.readAsDataURL(input.files[0]);
    document.querySelector('.rm-upload-txt').innerHTML =
      '<b style="color:var(--gold);">' + input.files[0].name + '</b>';
  }
}

document.querySelector('form').addEventListener('submit', () => {
  // รวม bed type จาก checkbox
  const beds = [...document.querySelectorAll('.am-chip input[data-bed]:checked')].map(cb => cb.dataset.bed);
  document.getElementById('bed_type_hidden').value = beds.join('|');

  // รวม amenities
  const amChecked = [...document.querySelectorAll('.am-chip input[value]:checked')].map(cb => cb.value);
  document.getElementById('amenities_hidden').value = amChecked.join('|');
});

function toggleAmenity(label) {
  const cb = label.querySelector('input');
  cb.checked = !cb.checked;
  label.classList.toggle('checked', cb.checked);
}
</script>

<?php include 'admin_layout_bottom.php'; ?>
