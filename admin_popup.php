<?php
date_default_timezone_set('Asia/Bangkok');
$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

/* ── สร้างตาราง ── */
$conn->query("CREATE TABLE IF NOT EXISTS `site_popups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(300) NOT NULL,
  `content` TEXT,
  `image` VARCHAR(500) DEFAULT '',
  `btn_text` VARCHAR(100) DEFAULT '',
  `btn_url` VARCHAR(500) DEFAULT '',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = ''; $msgType = '';

/* ── Handle actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $btn_text= trim($_POST['btn_text'] ?? '');
        $btn_url = trim($_POST['btn_url'] ?? '');
        $image   = '';
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $fname = 'popup_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $fname)) $image = $fname;
            }
        }
        if ($title !== '') {
            $st = $conn->prepare("INSERT INTO site_popups (title,content,image,btn_text,btn_url) VALUES (?,?,?,?,?)");
            $st->bind_param("sssss", $title, $content, $image, $btn_text, $btn_url);
            $st->execute(); $st->close();
            $msg = 'เพิ่ม Popup สำเร็จ'; $msgType = 'success';
        } else { $msg = 'กรุณากรอกหัวข้อ'; $msgType = 'error'; }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE site_popups SET is_active = 1 - is_active WHERE id=$id");
        $msg = 'อัปเดตสถานะแล้ว'; $msgType = 'success';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $row = $conn->query("SELECT image FROM site_popups WHERE id=$id")->fetch_assoc();
        if ($row && !empty($row['image'])) @unlink('uploads/' . $row['image']);
        $conn->query("DELETE FROM site_popups WHERE id=$id");
        $msg = 'ลบ Popup สำเร็จ'; $msgType = 'success';
    }
}

$popups = [];
$res = $conn->query("SELECT * FROM site_popups ORDER BY id DESC");
if ($res) while ($r = $res->fetch_assoc()) $popups[] = $r;

$pageTitle = "จัดการ Popup"; $activeMenu = "popup";
include 'admin_layout_top.php';
?>
<style>
:root{--navy:#0d1b2a;--blue:#1565c0;--blue2:#1976d2;--green:#15803d;--green-bg:#ecfdf3;
  --red:#dc2626;--red-bg:#fef2f2;--gold:#c9a96e;--muted:#5f7281;--border:#e0eaf5;
  --bg:#f0f5fc;--card:#fff;--ink:#0d1b2a;}
.pop-wrap{max-width:1000px;margin:0 auto;padding-bottom:60px;}
/* banner */
.pop-banner{border-radius:16px;padding:24px 28px;margin-bottom:24px;
  background:linear-gradient(135deg,var(--navy) 0%,#1a3a5c 60%,var(--blue) 100%);
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.pop-banner h1{font-family:'Kanit',sans-serif;font-size:1.3rem;font-weight:800;color:#fff;margin:0 0 3px;}
.pop-banner p{font-size:.78rem;color:rgba(255,255,255,.65);margin:0;}
/* msg */
.msg{border-radius:10px;padding:11px 16px;margin-bottom:18px;font-size:.85rem;font-weight:600;}
.msg.success{background:var(--green-bg);color:var(--green);border:1px solid #a7f3d0;}
.msg.error{background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;}
/* add form */
.add-card{background:var(--card);border-radius:16px;box-shadow:0 2px 12px rgba(13,27,42,.08);
  border:1px solid var(--border);overflow:hidden;margin-bottom:24px;}
.add-card-head{padding:16px 22px;border-bottom:1px solid var(--border);background:#f8fbff;
  font-family:'Kanit',sans-serif;font-size:.9rem;font-weight:800;color:var(--ink);}
.add-card-body{padding:22px;}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:16px;}
.fg label{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.fg input[type=text],.fg input[type=url],.fg textarea{
  font-family:'Sarabun',sans-serif;font-size:.9rem;background:#f8fbff;
  border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;outline:none;
  transition:border-color .2s;width:100%;color:var(--ink);}
.fg input:focus,.fg textarea:focus{border-color:var(--blue);background:#fff;}
.fg textarea{min-height:90px;resize:vertical;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.btn-add{display:inline-flex;align-items:center;gap:7px;padding:11px 24px;border-radius:10px;
  background:linear-gradient(135deg,var(--navy),var(--blue2));color:#fff;border:none;
  font-family:'Sarabun',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;transition:.2s;}
.btn-add:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(21,101,192,.3);}
/* list */
.list-card{background:var(--card);border-radius:16px;box-shadow:0 2px 12px rgba(13,27,42,.08);
  border:1px solid var(--border);overflow:hidden;}
.list-head{padding:14px 22px;border-bottom:1px solid var(--border);background:#f8fbff;
  font-family:'Kanit',sans-serif;font-size:.9rem;font-weight:800;color:var(--ink);
  display:flex;align-items:center;justify-content:space-between;}
.popup-row{display:grid;grid-template-columns:90px 1fr auto;gap:16px;align-items:start;
  padding:18px 22px;border-bottom:1px solid #f0f5fc;}
.popup-row:last-child{border-bottom:none;}
.pop-thumb{width:90px;height:62px;border-radius:10px;object-fit:cover;background:#e8edf5;
  display:flex;align-items:center;justify-content:center;font-size:1.8rem;overflow:hidden;}
.pop-thumb img{width:100%;height:100%;object-fit:cover;}
.pop-title{font-family:'Kanit',sans-serif;font-size:.95rem;font-weight:800;color:var(--ink);margin-bottom:5px;}
.pop-preview{font-size:.78rem;color:var(--muted);line-height:1.5;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.pop-meta{font-size:.72rem;color:var(--muted);margin-top:5px;}
.badge-on{background:var(--green-bg);color:var(--green);border-radius:99px;padding:2px 9px;font-size:.68rem;font-weight:700;}
.badge-off{background:#f1f5f9;color:var(--muted);border-radius:99px;padding:2px 9px;font-size:.68rem;font-weight:700;}
.pop-actions{display:flex;flex-direction:column;gap:7px;align-items:flex-end;}
.btn-sm{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:8px;
  font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:'Sarabun',sans-serif;transition:.2s;}
.btn-toggle-on{background:#ecfdf3;color:var(--green);}
.btn-toggle-on:hover{background:#d1fae5;}
.btn-toggle-off{background:#f1f5f9;color:var(--muted);}
.btn-toggle-off:hover{background:#e2e8f0;}
.btn-del{background:var(--red-bg);color:var(--red);}
.btn-del:hover{background:#fee2e2;}
.empty-state{padding:50px;text-align:center;color:var(--muted);}
/* preview popup */
.prev-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;
  background:#eff6ff;color:var(--blue);border:none;cursor:pointer;font-size:.75rem;font-weight:700;
  font-family:'Sarabun',sans-serif;transition:.2s;}
.prev-btn:hover{background:#dbeafe;}
@media(max-width:640px){
  .form-row{grid-template-columns:1fr;}
  .popup-row{grid-template-columns:1fr;gap:10px;}
}
</style>

<div class="main">
<div class="pop-wrap">

  <div class="pop-banner">
    <div>
      <h1>💬 จัดการ Popup</h1>
      <p>ตั้งค่าข้อความ popup ที่จะแสดงเมื่อผู้ใช้เข้าเว็บไซต์</p>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="msg <?= $msgType ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Add Form -->
  <div class="add-card">
    <div class="add-card-head">➕ เพิ่ม Popup ใหม่</div>
    <div class="add-card-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="fg">
          <label>หัวข้อ Popup *</label>
          <input type="text" name="title" placeholder="เช่น ประกาศสำคัญ" required>
        </div>
        <div class="fg">
          <label>เนื้อหา</label>
          <textarea name="content" placeholder="รายละเอียดของประกาศ..."></textarea>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>รูปภาพ (ไม่บังคับ)</label>
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif" style="font-size:.85rem;padding:8px 0;">
          </div>
          <div class="fg">
            <label>ปุ่ม CTA (ไม่บังคับ)</label>
            <input type="text" name="btn_text" placeholder="เช่น อ่านเพิ่มเติม">
          </div>
        </div>
        <div class="fg">
          <label>ลิงก์ปุ่ม CTA (ถ้ามี)</label>
          <input type="text" name="btn_url" placeholder="https://... หรือ news.php">
        </div>
        <button type="submit" class="btn-add">💾 บันทึก Popup</button>
      </form>
    </div>
  </div>

  <!-- List -->
  <div class="list-card">
    <div class="list-head">
      <span>รายการ Popup ทั้งหมด (<?= count($popups) ?> รายการ)</span>
      <span style="font-size:.75rem;color:var(--muted);font-weight:500;">popup ที่เปิดใช้จะแสดงบนหน้าเว็บ</span>
    </div>
    <?php if (empty($popups)): ?>
      <div class="empty-state">📭 ยังไม่มี Popup กรุณาเพิ่มด้านบน</div>
    <?php else: ?>
      <?php foreach ($popups as $p): ?>
      <div class="popup-row">
        <!-- thumb -->
        <div class="pop-thumb">
          <?php if (!empty($p['image'])): ?>
            <img src="uploads/<?= h($p['image']) ?>" alt="">
          <?php else: ?>
            💬
          <?php endif; ?>
        </div>
        <!-- info -->
        <div>
          <div class="pop-title"><?= h($p['title']) ?></div>
          <?php if (!empty($p['content'])): ?>
            <div class="pop-preview"><?= h($p['content']) ?></div>
          <?php endif; ?>
          <div class="pop-meta">
            <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
            &nbsp;·&nbsp;
            <?php if ($p['is_active']): ?>
              <span class="badge-on">● เปิดใช้งาน</span>
            <?php else: ?>
              <span class="badge-off">○ ปิดใช้งาน</span>
            <?php endif; ?>
            <?php if (!empty($p['btn_text'])): ?>
              &nbsp;·&nbsp; ปุ่ม: <?= h($p['btn_text']) ?>
            <?php endif; ?>
          </div>
        </div>
        <!-- actions -->
        <div class="pop-actions">
          <button class="prev-btn" onclick="previewPopup(<?= $p['id'] ?>)">👁 ตัวอย่าง</button>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <?php if ($p['is_active']): ?>
              <button type="submit" class="btn-sm btn-toggle-on">✓ เปิดอยู่ → ปิด</button>
            <?php else: ?>
              <button type="submit" class="btn-sm btn-toggle-off">✗ ปิดอยู่ → เปิด</button>
            <?php endif; ?>
          </form>
          <form method="POST" style="margin:0;" onsubmit="return confirm('ลบ Popup นี้?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="btn-sm btn-del">🗑 ลบ</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
</div>

<!-- ── PREVIEW POPUP (admin) ── -->
<style>
#apOverlay{display:none;position:fixed;inset:0;z-index:5000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:20px;}
#apOverlay.open{display:flex;}
#apBox{width:min(480px,100%);background:#fff;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.18);position:relative;overflow:hidden;animation:apIn .3s ease both;max-height:92vh;display:flex;flex-direction:column;}
@keyframes apIn{from{opacity:0;transform:translateY(-16px) scale(.97)}to{opacity:1;transform:none}}
#apClose2{position:absolute;top:12px;right:14px;z-index:10;background:none;border:none;cursor:pointer;color:#9ca3af;font-size:1.3rem;line-height:1;padding:4px;transition:color .15s;}
#apClose2:hover{color:#111;}
</style>
<div id="apOverlay" onclick="if(event.target===this)document.getElementById('apOverlay').classList.remove('open')">
  <div id="apBox">
    <button id="apClose2" onclick="document.getElementById('apOverlay').classList.remove('open')">&#x2715;</button>
    <div id="apImgWrap"></div>
    <div style="padding:36px 36px 10px;text-align:center;overflow-y:auto;flex:1;">
      <div id="apTitle" style="font-family:'Kanit',sans-serif;font-size:1.75rem;font-weight:900;color:#111;line-height:1.25;margin-bottom:12px;"></div>
      <div id="apContent" style="font-size:.93rem;color:#6b7280;line-height:1.75;white-space:pre-line;"></div>
    </div>
    <div style="padding:20px 36px 28px;display:flex;flex-direction:column;align-items:center;gap:12px;">
      <div id="apCta" style="width:100%;"></div>
      <button onclick="document.getElementById('apOverlay').classList.remove('open')" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:.82rem;font-family:'Sarabun',sans-serif;text-decoration:underline;">ปิด</button>
    </div>
  </div>
</div>

<script>
const popupData = <?= json_encode(array_map(fn($p) => [
  'id'      => $p['id'],
  'title'   => $p['title'],
  'content' => $p['content'],
  'image'   => $p['image'] ? 'uploads/'.$p['image'] : '',
  'btn_text'=> $p['btn_text'],
  'btn_url' => $p['btn_url'],
], $popups)) ?>;

function previewPopup(id) {
  const p = popupData.find(x => x.id == id);
  if (!p) return;
  document.getElementById('apImgWrap').innerHTML = p.image
    ? `<img src="${p.image}" style="width:100%;max-height:220px;object-fit:cover;display:block;">`
    : '';
  document.getElementById('apTitle').textContent   = p.title;
  document.getElementById('apContent').textContent = p.content;
  document.getElementById('apCta').innerHTML = (p.btn_text && p.btn_url)
    ? `<a href="${p.btn_url}" target="_blank" style="display:block;width:100%;padding:14px;background:#1e3a8a;color:#fff;border-radius:4px;font-family:'Sarabun',sans-serif;font-size:.95rem;font-weight:700;text-align:center;text-decoration:none;">${p.btn_text}</a>`
    : '';
  document.getElementById('apOverlay').classList.add('open');
}
</script>

<?php include 'admin_layout_bottom.php'; $conn->close(); ?>
