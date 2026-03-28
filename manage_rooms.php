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

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$pageTitle  = "จัดการห้องพัก";
$activeMenu = "rooms";

$editData = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId   = (int)$_GET['edit'];
    $stmtEdit = $conn->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    if ($stmtEdit) {
        $stmtEdit->bind_param("i", $editId);
        $stmtEdit->execute();
        $resultEdit = $stmtEdit->get_result();
        if ($resultEdit && $resultEdit->num_rows > 0) {
            $editData = $resultEdit->fetch_assoc();
        }
        $stmtEdit->close();
    }
}

$rooms = $conn->query("SELECT * FROM rooms ORDER BY id DESC");

include 'admin_layout_top.php';
?>

<style>
/* ── Variables ── */
:root {
  --gold:     #c9a96e;
  --gold-dim: rgba(201,169,110,0.12);
  --ink:      #1a1a2e;
  --bg:       #f5f1eb;
  --card:     #ffffff;
  --muted:    #7a7a8c;
  --border:   #e8e4de;
  --danger:   #dc2626;
  --success:  #16a34a;
  --radius:   14px;
}

/* ── Page wrapper ── */
.rm-wrap {
  padding: 0 0 48px;
}

/* ── Section heading ── */
.rm-heading {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 28px;
}
.rm-heading-icon {
  width: 44px; height: 44px;
  background: var(--gold-dim);
  border: 1.5px solid rgba(201,169,110,0.3);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem;
}
.rm-heading h2 {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  font-style: italic;
  color: var(--ink);
  margin: 0;
}
.rm-heading p {
  font-size: 0.78rem;
  color: var(--muted);
  margin: 2px 0 0;
}

/* ── Alert ── */
.rm-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 18px;
  border-radius: var(--radius);
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 24px;
  animation: slideDown 0.3s ease;
}
@keyframes slideDown {
  from { opacity:0; transform: translateY(-8px); }
  to   { opacity:1; transform: translateY(0); }
}
.rm-alert-success { background: #f0fdf4; border: 1.5px solid #86efac; color: var(--success); }
.rm-alert-error   { background: #fef2f2; border: 1.5px solid #fca5a5; color: var(--danger); }

/* ── Two-col grid ── */
.rm-grid {
  display: grid;
  grid-template-columns: 380px 1fr;
  gap: 24px;
  align-items: start;
}

/* ── Card ── */
.rm-card {
  background: var(--card);
  border-radius: 18px;
  box-shadow: 0 4px 20px rgba(26,26,46,0.07), 0 1px 4px rgba(26,26,46,0.04);
  overflow: hidden;
}
.rm-card-header {
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.rm-card-title {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 8px;
}
.rm-card-title::before {
  content: '';
  display: inline-block;
  width: 3px;
  height: 14px;
  background: var(--gold);
  border-radius: 2px;
}
.rm-card-body { padding: 24px; }

/* ── Form ── */
.rm-form-group { margin-bottom: 18px; }
.rm-form-group label {
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 7px;
}
.rm-form-group input[type="text"],
.rm-form-group input[type="number"],
.rm-form-group textarea,
.rm-form-group select {
  width: 100%;
  padding: 11px 14px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-family: 'Sarabun', sans-serif;
  font-size: 0.9rem;
  color: var(--ink);
  background: #fafaf8;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.rm-form-group input:focus,
.rm-form-group textarea:focus,
.rm-form-group select:focus {
  border-color: var(--gold);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(201,169,110,0.12);
}
.rm-form-group textarea { min-height: 90px; resize: vertical; }

/* File upload zone */
.rm-file-zone {
  border: 2px dashed var(--border);
  border-radius: 10px;
  padding: 18px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
  position: relative;
}
.rm-file-zone:hover { border-color: var(--gold); background: var(--gold-dim); }
.rm-file-zone input[type="file"] {
  position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.rm-file-zone-icon { font-size: 1.6rem; margin-bottom: 6px; }
.rm-file-zone-text { font-size: 0.8rem; color: var(--muted); }

/* Preview image */
.rm-preview {
  margin-top: 12px;
  border-radius: 12px;
  overflow: hidden;
  border: 1.5px solid var(--border);
  position: relative;
}
.rm-preview img {
  width: 100%;
  height: 160px;
  object-fit: cover;
  display: block;
}
.rm-preview-label {
  position: absolute;
  top: 8px; left: 8px;
  background: rgba(26,26,46,0.7);
  color: #fff;
  font-size: 0.65rem;
  padding: 3px 8px;
  border-radius: 20px;
  letter-spacing: 0.08em;
}

/* Form row */
.rm-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

/* Buttons */
.rm-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 11px 20px;
  border: none;
  border-radius: 10px;
  font-family: 'Sarabun', sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
  letter-spacing: 0.04em;
}
.rm-btn-primary {
  background: var(--ink);
  color: #fff;
  flex: 1;
}
.rm-btn-primary:hover { background: #2a2a4a; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(26,26,46,0.2); }
.rm-btn-ghost {
  background: var(--bg);
  color: var(--muted);
  border: 1.5px solid var(--border);
}
.rm-btn-ghost:hover { border-color: var(--gold); color: var(--gold); }
.rm-btn-actions { display: flex; gap: 10px; margin-top: 20px; }

/* ── Table ── */
.rm-table-scroll { overflow-x: auto; }
.rm-table {
  width: 100%;
  border-collapse: collapse;
}
.rm-table thead th {
  padding: 12px 14px;
  font-size: 0.68rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--muted);
  border-bottom: 2px solid var(--border);
  text-align: left;
  background: #fdfcfa;
  font-weight: 700;
}
.rm-table tbody td {
  padding: 14px;
  font-size: 0.85rem;
  color: var(--ink);
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.rm-table tbody tr:last-child td { border-bottom: none; }
.rm-table tbody tr {
  transition: background 0.15s;
}
.rm-table tbody tr:hover { background: #fdfcfa; }

/* Thumbnail */
.rm-thumb {
  width: 80px;
  height: 56px;
  object-fit: cover;
  border-radius: 10px;
  border: 1.5px solid var(--border);
  display: block;
}
.rm-thumb-empty {
  width: 80px;
  height: 56px;
  background: #f1ede8;
  border-radius: 10px;
  border: 1.5px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: var(--border);
}

/* Room name + type */
.rm-room-name { font-weight: 700; color: var(--ink); }
.rm-room-type { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }

/* Price */
.rm-price {
  font-weight: 700;
  color: var(--ink);
}
.rm-price-unit { font-size: 0.72rem; color: var(--muted); font-weight: 400; }

/* Status badge */
.rm-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 20px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.rm-badge::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
}
.rm-badge-show { background: #f0fdf4; color: var(--success); }
.rm-badge-show::before { background: var(--success); }
.rm-badge-hide { background: #fef2f2; color: var(--danger); }
.rm-badge-hide::before { background: var(--danger); }

/* Action buttons */
.rm-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 12px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.15s;
}
.rm-action-edit  { background: #eff6ff; color: #1d4ed8; }
.rm-action-edit:hover  { background: #dbeafe; }
.rm-action-delete { background: #fef2f2; color: var(--danger); }
.rm-action-delete:hover { background: #fee2e2; }

/* Empty state */
.rm-empty {
  padding: 48px 24px;
  text-align: center;
  color: var(--muted);
}
.rm-empty-icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; }
.rm-empty-text { font-size: 0.85rem; }

/* Count badge */
.rm-count {
  background: var(--gold-dim);
  color: #a07c3a;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 20px;
  letter-spacing: 0.04em;
}

@media (max-width: 1100px) {
  .rm-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .rm-form-row { grid-template-columns: 1fr; }
}
</style>

<div class="rm-wrap">

  <!-- Alert -->
  <?php if (!empty($_SESSION['room_msg'])): ?>
    <div class="rm-alert <?= (!empty($_SESSION['room_msg_type']) && $_SESSION['room_msg_type'] === 'success') ? 'rm-alert-success' : 'rm-alert-error' ?>">
      <?= (!empty($_SESSION['room_msg_type']) && $_SESSION['room_msg_type'] === 'success') ? '✓' : '⚠' ?>
      <?= htmlspecialchars($_SESSION['room_msg']) ?>
    </div>
    <?php unset($_SESSION['room_msg'], $_SESSION['room_msg_type']); ?>
  <?php endif; ?>

  <!-- Main grid -->
  <div class="rm-grid">

    <!-- ── Form Card ── -->
    <div class="rm-card" style="position:sticky;top:24px;">
      <div class="rm-card-header">
        <div class="rm-card-title">
          <?= $editData ? 'แก้ไขห้องพัก' : 'เพิ่มห้องพักใหม่' ?>
        </div>
        <?php if ($editData): ?>
          <span style="font-size:0.72rem;color:var(--muted);">id #<?= $editData['id'] ?></span>
        <?php endif; ?>
      </div>
      <div class="rm-card-body">
        <form action="save_room.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id'] ?? '') ?>">

          <div class="rm-form-group">
            <label>ชื่อห้องพัก</label>
            <input type="text" name="room_name" required placeholder="เช่น Deluxe Suite 101"
                   value="<?= htmlspecialchars($editData['room_name'] ?? '') ?>">
          </div>

          <div class="rm-form-row">
            <div class="rm-form-group">
              <label>ประเภทห้อง</label>
              <input type="text" name="room_type" required placeholder="เช่น VIP, Standard"
                     value="<?= htmlspecialchars($editData['room_type'] ?? '') ?>">
            </div>
            <div class="rm-form-group">
              <label>ราคา / คืน (฿)</label>
              <input type="number" step="0.01" name="price" min="0" required
                     value="<?= htmlspecialchars($editData['price'] ?? '0') ?>">
            </div>
          </div>

          <div class="rm-form-group">
            <label>รายละเอียด</label>
            <textarea name="description" placeholder="อธิบายสิ่งอำนวยความสะดวก, วิว, บริการ..."><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
          </div>

          <div class="rm-form-row">
            <div class="rm-form-group">
              <label>จำนวนห้องทั้งหมด</label>
              <input type="number" name="total_rooms" min="1" required
                     value="<?= htmlspecialchars($editData['total_rooms'] ?? '5') ?>">
            </div>
            <div class="rm-form-group">
              <label>ผู้เข้าพักสูงสุด</label>
              <input type="number" name="max_guests" min="1" required
                     value="<?= htmlspecialchars($editData['max_guests'] ?? '2') ?>">
            </div>
          </div>
            <div class="rm-form-group">
              <label>ประเภทเตียง</label>
              <input type="text" name="bed_type" placeholder="เช่น เตียงคู่"
                     value="<?= htmlspecialchars($editData['bed_type'] ?? '') ?>">
            </div>
          </div>

          <div class="rm-form-group">
            <label>สถานะการแสดงผล</label>
            <select name="status">
              <option value="show" <?= (($editData['status'] ?? 'show') === 'show') ? 'selected' : '' ?>>แสดง</option>
              <option value="hide" <?= (($editData['status'] ?? 'show') === 'hide') ? 'selected' : '' ?>>ซ่อน</option>
            </select>
          </div>

          <div class="rm-form-group">
            <label>รูปหน้าห้องพัก</label>
            <div class="rm-file-zone" id="fileZone">
              <input type="file" name="room_image" accept="image/*" onchange="previewFile(this)">
              <div class="rm-file-zone-icon">🖼️</div>
              <div class="rm-file-zone-text">คลิกหรือลากไฟล์มาวางที่นี่<br><span style="color:var(--gold);font-weight:700;">Browse file</span></div>
            </div>
          </div>

          <?php if (!empty($editData['image_path'])): ?>
            <div class="rm-preview" id="imgPreview">
              <span class="rm-preview-label">รูปปัจจุบัน</span>
              <img src="<?= htmlspecialchars($editData['image_path']) ?>" alt="room image" id="previewImg">
            </div>
          <?php else: ?>
            <div class="rm-preview" id="imgPreview" style="display:none;">
              <span class="rm-preview-label">ตัวอย่าง</span>
              <img src="" alt="" id="previewImg">
            </div>
          <?php endif; ?>

          <div class="rm-btn-actions">
            <button type="submit" class="rm-btn rm-btn-primary">
              <?= $editData ? '💾 อัปเดตข้อมูล' : '➕ บันทึกห้องพัก' ?>
            </button>
            <?php if ($editData): ?>
              <a href="manage_rooms.php" class="rm-btn rm-btn-ghost">ยกเลิก</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Table Card ── -->
    <div class="rm-card">
      <div class="rm-card-header">
        <div class="rm-card-title">รายการห้องพักทั้งหมด</div>
        <?php
          $totalRooms = $rooms ? $rooms->num_rows : 0;
          // Reset pointer
          if ($rooms) $rooms->data_seek(0);
        ?>
        <span class="rm-count"><?= $totalRooms ?> ห้อง</span>
      </div>

      <div class="rm-table-scroll">
        <table class="rm-table">
          <thead>
            <tr>
              <th style="width:90px;">รูป</th>
              <th>ชื่อห้อง / ประเภท</th>
              <th>ราคา</th>
              <th>จำนวน</th>
              <th>สถานะ</th>
              <th style="width:140px;">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rooms && $rooms->num_rows > 0): ?>
              <?php while ($row = $rooms->fetch_assoc()): ?>
                <tr>
                  <td>
                    <?php if (!empty($row['image_path'])): ?>
                      <img src="<?= htmlspecialchars($row['image_path']) ?>" class="rm-thumb" alt="">
                    <?php else: ?>
                      <div class="rm-thumb-empty">🛏️</div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="rm-room-name"><?= htmlspecialchars($row['room_name'] ?? '') ?></div>
                    <div class="rm-room-type"><?= htmlspecialchars($row['room_type'] ?? '') ?></div>
                  </td>
                  <td>
                    <div class="rm-price">฿<?= number_format((float)($row['price'] ?? 0), 0) ?></div>
                    <div class="rm-price-unit">/ คืน</div>
                  </td>
                  <td><?= (int)($row['total_rooms'] ?? 0) ?> ห้อง</td>
                  <td>
                    <?php if (($row['status'] ?? 'show') === 'show'): ?>
                      <span class="rm-badge rm-badge-show">แสดง</span>
                    <?php else: ?>
                      <span class="rm-badge rm-badge-hide">ซ่อน</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a class="rm-action rm-action-edit" href="manage_rooms.php?edit=<?= (int)$row['id'] ?>">✏️ แก้ไข</a>
                    <a class="rm-action rm-action-delete" style="margin-top:4px;display:inline-flex;"
                       href="delete_room.php?id=<?= (int)$row['id'] ?>"
                       onclick="return confirm('ยืนยันการลบห้องพักนี้?')">🗑 ลบ</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6">
                  <div class="rm-empty">
                    <div class="rm-empty-icon">🛏️</div>
                    <div class="rm-empty-text">ยังไม่มีข้อมูลห้องพัก<br>เพิ่มห้องพักแรกของคุณได้เลย</div>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
function previewFile(input) {
  const preview = document.getElementById('imgPreview');
  const img     = document.getElementById('previewImg');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      img.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
    document.querySelector('.rm-file-zone-text').innerHTML =
      '<span style="color:var(--gold);font-weight:700;">' + input.files[0].name + '</span>';
  }
}
</script>

<?php include 'admin_layout_bottom.php'; ?>