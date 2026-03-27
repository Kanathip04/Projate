<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php"); exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

$user_id = (int)$_SESSION['user_id'];
$message = ''; $msg_type = '';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $phone    = trim($_POST['phone']    ?? '');

        if (empty($fullname)) {
            $message = 'กรุณากรอกชื่อ-นามสกุล'; $msg_type = 'error';
        } else {
            // Avatar upload
            $avatar_path = '';
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                    $message = 'รูปภาพต้องเป็น jpg, jpeg, png, webp หรือ gif'; $msg_type = 'error';
                } elseif ($_FILES['avatar']['size'] > 2*1024*1024) {
                    $message = 'ขนาดไฟล์ต้องไม่เกิน 2MB'; $msg_type = 'error';
                } else {
                    $old = $conn->query("SELECT avatar FROM users WHERE id=$user_id")->fetch_assoc();
                    if (!empty($old['avatar'])) { $f=__DIR__.'/'.$old['avatar']; if(file_exists($f)) @unlink($f); }
                    $n = 'avatar_'.$user_id.'_'.time().'.'.$ext;
                    move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir.$n);
                    $avatar_path = 'uploads/avatars/'.$n;
                }
            }

            if (empty($message)) {
                if ($avatar_path) {
                    $st = $conn->prepare("UPDATE users SET fullname=?, phone=?, avatar=? WHERE id=?");
                    $st->bind_param("sssi", $fullname, $phone, $avatar_path, $user_id);
                } else {
                    $st = $conn->prepare("UPDATE users SET fullname=?, phone=? WHERE id=?");
                    $st->bind_param("ssi", $fullname, $phone, $user_id);
                }
                if ($st->execute()) {
                    $_SESSION['user_name'] = $fullname;
                    $message = 'อัปเดตข้อมูลเรียบร้อยแล้ว'; $msg_type = 'success';
                } else {
                    $message = 'เกิดข้อผิดพลาด: ' . $st->error; $msg_type = 'error';
                }
                $st->close();
            }
        }
    }

    if ($action === 'change_password') {
        $new_pass = $_POST['new_password']     ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (strlen($new_pass) < 6) {
            $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'; $msg_type = 'error';
        } elseif ($new_pass !== $confirm) {
            $message = 'รหัสผ่านไม่ตรงกัน'; $msg_type = 'error';
        } else {
            $h  = password_hash($new_pass, PASSWORD_DEFAULT);
            $st = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $st->bind_param("si", $h, $user_id);
            $st->execute(); $st->close();
            $message = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'; $msg_type = 'success';
        }
    }
}

// ── Load user ──
$user = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1")->fetch_assoc();
if (!$user) { header("Location: logout.php"); exit; }

$join_days     = (int)((time() - strtotime($user['created_at'])) / 86400);
$avatarInitial = strtoupper(mb_substr($user['fullname'] ?? 'A', 0, 1));

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pageTitle  = "โปรไฟล์แอดมิน";
$activeMenu = "";
include 'admin_layout_top.php';
?>

<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,0.12);--ink:#1a1a2e;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;}

.ap-wrap{ max-width:860px; margin:0 auto; padding:0 0 48px; }

/* Alert */
.ap-alert{ display:flex; align-items:center; gap:10px; padding:13px 18px; border-radius:12px; font-size:0.85rem; font-weight:600; margin-bottom:22px; animation:apDown .3s ease; }
@keyframes apDown{ from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.ap-alert-success{ background:#f0fdf4; border:1.5px solid #86efac; color:var(--success); }
.ap-alert-error  { background:#fef2f2; border:1.5px solid #fca5a5; color:var(--danger); }

/* Hero banner */
.ap-hero{
  background:var(--ink); border-radius:18px;
  padding:32px 36px; display:flex; align-items:center; gap:28px;
  margin-bottom:22px; position:relative; overflow:hidden;
}
.ap-hero::before{
  content:''; position:absolute;
  width:320px; height:320px; border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,0.13) 0%,transparent 70%);
  top:-120px; right:-60px; pointer-events:none;
}
.ap-hero::after{
  content:''; position:absolute;
  width:180px; height:180px; border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,0.08) 0%,transparent 70%);
  bottom:-60px; left:-30px; pointer-events:none;
}

/* Avatar */
.ap-av-wrap{ position:relative; flex-shrink:0; z-index:1; }
.ap-av{
  width:88px; height:88px; border-radius:50%;
  background:var(--gold-dim); border:3px solid rgba(201,169,110,0.5);
  display:flex; align-items:center; justify-content:center;
  font-family:'Playfair Display',serif; font-style:italic;
  font-size:2.2rem; color:var(--gold); overflow:hidden;
}
.ap-av img{ width:100%; height:100%; object-fit:cover; }
.ap-av-btn{
  position:absolute; bottom:2px; right:2px;
  width:26px; height:26px; border-radius:50%;
  background:var(--gold); border:2px solid var(--ink);
  display:flex; align-items:center; justify-content:center;
  font-size:0.68rem; cursor:pointer; transition:transform .2s;
}
.ap-av-btn:hover{ transform:scale(1.1); }

/* Hero info */
.ap-hero-info{ flex:1; z-index:1; }
.ap-hero-name{
  font-family:'Playfair Display',serif; font-style:italic;
  font-size:1.7rem; color:#fff; margin-bottom:4px;
}
.ap-hero-email{ font-size:0.8rem; color:rgba(255,255,255,0.5); margin-bottom:12px; }
.ap-hero-badges{ display:flex; gap:8px; }
.ap-badge{
  display:inline-flex; align-items:center; gap:5px;
  padding:4px 12px; border-radius:20px;
  font-size:0.68rem; font-weight:700; letter-spacing:.06em;
}
.ap-badge-admin{ background:rgba(201,169,110,0.2); color:var(--gold); border:1px solid rgba(201,169,110,0.35); }
.ap-badge-verified{ background:rgba(22,163,74,0.2); color:#4ade80; border:1px solid rgba(22,163,74,0.3); }

.ap-hero-stat{ z-index:1; text-align:center; flex-shrink:0; }
.ap-stat-val{ font-size:1.6rem; font-weight:800; color:#fff; }
.ap-stat-lbl{ font-size:0.63rem; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,0.4); margin-top:3px; }

/* Grid */
.ap-grid{ display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }

/* Card */
.ap-card{ background:var(--card); border-radius:18px; box-shadow:0 2px 12px rgba(26,26,46,.06); overflow:hidden; margin-bottom:20px; }
.ap-card-header{ padding:16px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
.ap-card-icon{ width:34px; height:34px; border-radius:8px; background:var(--gold-dim); border:1.5px solid rgba(201,169,110,0.25); display:flex; align-items:center; justify-content:center; font-size:0.9rem; }
.ap-card-title{ font-size:0.88rem; font-weight:700; color:var(--ink); }
.ap-card-body{ padding:22px; }

/* Form */
.ap-fg{ margin-bottom:18px; }
.ap-fg label{ display:block; font-size:0.67rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin-bottom:7px; }
.ap-iw{ position:relative; }
.ap-ii{ position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:0.85rem; color:var(--muted); pointer-events:none; }
.ap-input, .ap-textarea{
  width:100%; padding:11px 12px 11px 36px;
  border:1.5px solid var(--border); border-radius:10px;
  font-family:'Sarabun',sans-serif; font-size:0.88rem;
  color:var(--ink); background:#fafaf8; outline:none;
  transition:border-color .2s, box-shadow .2s;
}
.ap-input:focus, .ap-textarea:focus{
  border-color:var(--gold); background:#fff;
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.ap-input[readonly]{ background:#f0ece6; color:var(--muted); cursor:not-allowed; }
.ap-textarea{ padding:11px 12px; min-height:80px; resize:vertical; }

/* ✅ Save button — จุดหลักที่เพิ่ม */
.ap-btn-row{
  display:flex; align-items:center; justify-content:flex-end;
  gap:12px; margin-top:20px;
  padding-top:18px; border-top:1px solid var(--border);
}
.ap-btn{
  display:inline-flex; align-items:center; gap:7px;
  padding:11px 24px; border:none; border-radius:10px;
  font-family:'Sarabun',sans-serif; font-size:0.85rem;
  font-weight:700; cursor:pointer; transition:all .2s;
  letter-spacing:.04em; text-decoration:none;
}
.ap-btn:hover{ transform:translateY(-1px); }
.ap-btn-primary{ background:var(--ink); color:#fff; }
.ap-btn-primary:hover{ background:#2a2a4a; box-shadow:0 6px 16px rgba(26,26,46,.2); }
.ap-btn-primary:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
.ap-btn-ghost{ background:transparent; color:var(--muted); border:1.5px solid var(--border); }
.ap-btn-ghost:hover{ border-color:var(--gold); color:var(--gold); }

/* Info list */
.ap-info-list{ display:flex; flex-direction:column; gap:14px; }
.ap-info-item{ display:flex; align-items:flex-start; gap:11px; }
.ap-info-dot{ width:7px; height:7px; border-radius:50%; background:var(--gold); flex-shrink:0; margin-top:5px; }
.ap-info-lbl{ font-size:0.67rem; color:var(--muted); text-transform:uppercase; letter-spacing:.1em; margin-bottom:2px; }
.ap-info-val{ font-size:0.87rem; font-weight:600; color:var(--ink); }

/* Password strength */
.ap-sbar{ height:4px; background:var(--border); border-radius:2px; margin-top:6px; overflow:hidden; }
.ap-sfill{ height:100%; width:0%; border-radius:2px; transition:width .3s, background .3s; }

#ap-avatar-file{ display:none; }

@media(max-width:760px){
  .ap-grid{ grid-template-columns:1fr; }
  .ap-hero{ flex-direction:column; text-align:center; padding:28px 22px; }
}
</style>

<div class="ap-wrap">

  <?php if ($message): ?>
    <div class="ap-alert <?= $msg_type==='error'?'ap-alert-error':'ap-alert-success' ?>">
      <?= $msg_type==='error'?'⚠':'✓' ?> <?= h($message) ?>
    </div>
  <?php endif; ?>

  <!-- Hero -->
  <div class="ap-hero">
    <div class="ap-av-wrap">
      <div class="ap-av" id="apAvDisplay">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= h($user['avatar']) ?>" alt="avatar">
        <?php else: ?>
          <?= $avatarInitial ?>
        <?php endif; ?>
      </div>
      <label for="ap-avatar-file" class="ap-av-btn" title="เปลี่ยนรูปโปรไฟล์">📷</label>
    </div>

    <div class="ap-hero-info">
      <div class="ap-hero-name"><?= h($user['fullname'] ?? 'Admin') ?></div>
      <div class="ap-hero-email"><?= h($user['email']) ?></div>
      <div class="ap-hero-badges">
        <span class="ap-badge ap-badge-admin">⚡ Administrator</span>
        <?php if ($user['is_verified']): ?>
          <span class="ap-badge ap-badge-verified">✓ ยืนยันอีเมลแล้ว</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="ap-hero-stat">
      <div class="ap-stat-val"><?= $join_days ?></div>
      <div class="ap-stat-lbl">วันที่ใช้งาน</div>
    </div>
  </div>

  <!-- Main grid -->
  <div class="ap-grid">

    <!-- LEFT -->
    <div>

      <!-- แก้ไขข้อมูลส่วนตัว -->
      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">✏️</div>
          <div class="ap-card-title">แก้ไขข้อมูลส่วนตัว</div>
        </div>
        <div class="ap-card-body">
          <form method="POST" enctype="multipart/form-data" id="profileForm">
            <input type="hidden" name="action" value="update_profile">
            <input type="file" id="ap-avatar-file" name="avatar" accept="image/*"
                   onchange="previewApAv(this)">

            <div class="ap-fg">
              <label>ชื่อ-นามสกุล *</label>
              <div class="ap-iw">
                <span class="ap-ii">👤</span>
                <input class="ap-input" type="text" name="fullname"
                       value="<?= h($user['fullname'] ?? '') ?>" required
                       placeholder="ชื่อ-นามสกุล">
              </div>
            </div>

            <div class="ap-fg">
              <label>อีเมล <span style="font-size:.6rem;color:var(--muted);text-transform:none;">(ไม่สามารถแก้ไขได้)</span></label>
              <div class="ap-iw">
                <span class="ap-ii">✉️</span>
                <input class="ap-input" type="text" value="<?= h($user['email']) ?>" readonly>
              </div>
            </div>

            <div class="ap-fg">
              <label>เบอร์โทรศัพท์</label>
              <div class="ap-iw">
                <span class="ap-ii">📱</span>
                <input class="ap-input" type="text" name="phone"
                       value="<?= h($user['phone'] ?? '') ?>"
                       placeholder="08x-xxx-xxxx">
              </div>
            </div>

            <!-- ✅ ปุ่มบันทึก -->
            <div class="ap-btn-row">
              <span style="font-size:0.76rem;color:var(--muted);">
                กด บันทึกข้อมูล เพื่อยืนยันการเปลี่ยนแปลง
              </span>
              <button type="submit" class="ap-btn ap-btn-primary" id="saveProfileBtn">
                💾 บันทึกข้อมูล
              </button>
            </div>

          </form>
        </div>
      </div>

      <!-- เปลี่ยนรหัสผ่าน -->
      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">🔐</div>
          <div class="ap-card-title">ตั้งรหัสผ่านใหม่</div>
        </div>
        <div class="ap-card-body">
          <form method="POST" id="passForm">
            <input type="hidden" name="action" value="change_password">

            <div class="ap-fg">
              <label>รหัสผ่านใหม่</label>
              <div class="ap-iw">
                <span class="ap-ii">🔑</span>
                <input class="ap-input" type="password" name="new_password"
                       id="apNewPass" placeholder="อย่างน้อย 6 ตัวอักษร" required
                       oninput="apCheckStr(this.value)">
              </div>
              <div class="ap-sbar"><div class="ap-sfill" id="apSfill"></div></div>
            </div>

            <div class="ap-fg" style="margin-bottom:0;">
              <label>ยืนยันรหัสผ่านใหม่</label>
              <div class="ap-iw">
                <span class="ap-ii">🔑</span>
                <input class="ap-input" type="password" name="confirm_password"
                       placeholder="••••••••" required>
              </div>
            </div>

            <!-- ✅ ปุ่มเปลี่ยนรหัสผ่าน -->
            <div class="ap-btn-row">
              <button type="submit" class="ap-btn ap-btn-primary">
                🔄 เปลี่ยนรหัสผ่าน
              </button>
            </div>

          </form>
        </div>
      </div>

    </div><!-- end left -->

    <!-- RIGHT -->
    <div>

      <!-- ข้อมูลบัญชี -->
      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">🔖</div>
          <div class="ap-card-title">ข้อมูลบัญชี</div>
        </div>
        <div class="ap-card-body">
          <div class="ap-info-list">
            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">สถานะ</div>
                <div class="ap-info-val">⚡ Administrator</div>
              </div>
            </div>
            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">อีเมล</div>
                <div class="ap-info-val" style="word-break:break-all;"><?= h($user['email']) ?></div>
              </div>
            </div>
            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">วันที่สมัคร</div>
                <div class="ap-info-val"><?= h(date('d M Y', strtotime($user['created_at']))) ?></div>
              </div>
            </div>
            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">ยืนยันอีเมล</div>
                <div class="ap-info-val"><?= $user['is_verified'] ? '✅ ยืนยันแล้ว' : '⏳ ยังไม่ยืนยัน' ?></div>
              </div>
            </div>
            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">ใช้งานมาแล้ว</div>
                <div class="ap-info-val"><?= $join_days ?> วัน</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- เปลี่ยนรูปโปรไฟล์ -->
      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">🖼️</div>
          <div class="ap-card-title">รูปโปรไฟล์</div>
        </div>
        <div class="ap-card-body" style="text-align:center;">
          <div class="ap-av" style="width:80px;height:80px;font-size:2rem;margin:0 auto 14px;" id="apAvPreview">
            <?php if (!empty($user['avatar'])): ?>
              <img src="<?= h($user['avatar']) ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else: ?>
              <?= $avatarInitial ?>
            <?php endif; ?>
          </div>
          <p style="font-size:0.76rem;color:var(--muted);margin-bottom:14px;line-height:1.6;">
            คลิกไอคอนกล้องที่รูปโปรไฟล์<br>เพื่อเปลี่ยนรูป (สูงสุด 2MB)
          </p>
          <label for="ap-avatar-file" class="ap-btn ap-btn-ghost" style="cursor:pointer;width:100%;justify-content:center;">
            📷 เลือกรูปภาพ
          </label>
          <p style="font-size:0.7rem;color:var(--muted);margin-top:8px;">jpg, jpeg, png, webp, gif</p>
        </div>
      </div>

    </div><!-- end right -->

  </div><!-- end grid -->

</div><!-- end wrap -->

<script>
function previewApAv(input) {
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = e => {
      const img = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
      document.getElementById('apAvDisplay').innerHTML = img;
      document.getElementById('apAvPreview').innerHTML = img;
    };
    r.readAsDataURL(input.files[0]);
  }
}

function apCheckStr(val) {
  let s = 0;
  if (val.length>=6)  s++;
  if (val.length>=10) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const c = ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
  const b = document.getElementById('apSfill');
  b.style.width = (s*20)+'%';
  b.style.background = c[s-1] || '#e8e4de';
}

// ป้องกันกด submit ซ้ำ
document.getElementById('profileForm').addEventListener('submit', () => {
  const btn = document.getElementById('saveProfileBtn');
  btn.textContent = '⏳ กำลังบันทึก...';
  btn.disabled = true;
});
</script>

<?php include 'admin_layout_bottom.php'; $conn->close(); ?>