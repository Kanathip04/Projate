<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once 'config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$message  = '';
$msg_type = '';

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function getAdminById(mysqli $conn, int $user_id): ?array {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $user ?: null;
}

function safeAvatarDelete(string $relativePath): void {
    if ($relativePath === '') {
        return;
    }

    $relativePath = str_replace('\\', '/', $relativePath);
    if (strpos($relativePath, 'uploads/avatars/') !== 0) {
        return;
    }

    $full = __DIR__ . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

function uploadAvatarIfAny(array $file, int $user_id, string &$errorMessage): string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ';
        return '';
    }

    $upload_dir = __DIR__ . '/uploads/avatars/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowed, true)) {
        $errorMessage = 'รูปภาพต้องเป็น jpg, jpeg, png, webp หรือ gif';
        return '';
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $errorMessage = 'ขนาดไฟล์ต้องไม่เกิน 2MB';
        return '';
    }

    $new_name = 'avatar_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $target = $upload_dir . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errorMessage = 'อัปโหลดรูปภาพไม่สำเร็จ';
        return '';
    }

    return 'uploads/avatars/' . $new_name;
}

$user = getAdminById($conn, $user_id);
if (!$user) {
    header("Location: logout.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if ($fullname === '') {
            $message = 'กรุณากรอกชื่อ-นามสกุล';
            $msg_type = 'error';
        } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            $message = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
            $msg_type = 'error';
        } else {
            $upload_error = '';
            $avatar_path = uploadAvatarIfAny($_FILES['avatar'] ?? [], $user_id, $upload_error);

            if ($upload_error !== '') {
                $message = $upload_error;
                $msg_type = 'error';
            } else {
                if ($avatar_path !== '') {
                    $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, avatar = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $fullname, $phone, $avatar_path, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $fullname, $phone, $user_id);
                }

                if ($stmt->execute()) {
                    if ($avatar_path !== '' && !empty($user['avatar'])) {
                        safeAvatarDelete((string)$user['avatar']);
                    }

                    $_SESSION['user_name'] = $fullname;
                    $message = 'อัปเดตข้อมูลเรียบร้อยแล้ว';
                    $msg_type = 'success';
                    $user = getAdminById($conn, $user_id);
                } else {
                    if ($avatar_path !== '') {
                        safeAvatarDelete($avatar_path);
                    }
                    $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                    $msg_type = 'error';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row || !password_verify($current, $row['password'])) {
            $message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            $msg_type = 'error';
        } elseif (strlen($new_pass) < 6) {
            $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
            $msg_type = 'error';
        } elseif ($new_pass !== $confirm) {
            $message = 'รหัสผ่านไม่ตรงกัน';
            $msg_type = 'error';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $user_id);

            if ($stmt->execute()) {
                $message = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
                $msg_type = 'success';
            } else {
                $message = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
                $msg_type = 'error';
            }
            $stmt->close();
        }
    }
}

$user = getAdminById($conn, $user_id);
if (!$user) {
    header("Location: logout.php");
    exit;
}

$join_days = 0;
if (!empty($user['created_at'])) {
    $join_days = (int)((time() - strtotime($user['created_at'])) / 86400);
}

$fullnameSafe = trim((string)($user['fullname'] ?? 'A'));
if (function_exists('mb_substr')) {
    $avatarInitial = mb_strtoupper(mb_substr($fullnameSafe, 0, 1), 'UTF-8');
} else {
    $avatarInitial = strtoupper(substr($fullnameSafe, 0, 1));
}

$pageTitle  = "โปรไฟล์แอดมิน";
$activeMenu = "profile";
include 'admin_layout_top.php';
?>

<style>
:root{
  --gold:#c9a96e;
  --gold-dim:rgba(201,169,110,0.12);
  --ink:#1a1a2e;
  --card:#fff;
  --muted:#7a7a8c;
  --border:#e8e4de;
  --danger:#dc2626;
  --success:#16a34a;
}

.ap-wrap{
  max-width:860px;
  margin:0 auto;
  padding:0 0 48px;
}

.ap-alert{
  display:flex;
  align-items:center;
  gap:10px;
  padding:13px 18px;
  border-radius:12px;
  font-size:0.85rem;
  font-weight:600;
  margin-bottom:22px;
  animation:apDown .3s ease;
}
@keyframes apDown{
  from{opacity:0;transform:translateY(-6px)}
  to{opacity:1;transform:translateY(0)}
}
.ap-alert-success{
  background:#f0fdf4;
  border:1.5px solid #86efac;
  color:var(--success);
}
.ap-alert-error{
  background:#fef2f2;
  border:1.5px solid #fca5a5;
  color:var(--danger);
}

.ap-hero{
  background:var(--ink);
  border-radius:18px;
  padding:32px 36px;
  display:flex;
  align-items:center;
  gap:28px;
  margin-bottom:22px;
  position:relative;
  overflow:hidden;
}
.ap-hero::before{
  content:'';
  position:absolute;
  width:320px;
  height:320px;
  border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,0.13) 0%,transparent 70%);
  top:-120px;
  right:-60px;
  pointer-events:none;
}
.ap-hero::after{
  content:'';
  position:absolute;
  width:180px;
  height:180px;
  border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,0.08) 0%,transparent 70%);
  bottom:-60px;
  left:-30px;
  pointer-events:none;
}

.ap-av-wrap{
  position:relative;
  flex-shrink:0;
  z-index:1;
}
.ap-av{
  width:88px;
  height:88px;
  border-radius:50%;
  background:var(--gold-dim);
  border:3px solid rgba(201,169,110,0.5);
  display:flex;
  align-items:center;
  justify-content:center;
  font-family:'Playfair Display',serif;
  font-style:italic;
  font-size:2.2rem;
  color:var(--gold);
  overflow:hidden;
}
.ap-av img{
  width:100%;
  height:100%;
  object-fit:cover;
}
.ap-av-btn{
  position:absolute;
  bottom:2px;
  right:2px;
  width:26px;
  height:26px;
  border-radius:50%;
  background:var(--gold);
  border:2px solid var(--ink);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:0.68rem;
  cursor:pointer;
  transition:transform .2s;
}
.ap-av-btn:hover{
  transform:scale(1.1);
}

.ap-hero-info{
  flex:1;
  z-index:1;
}
.ap-hero-name{
  font-family:'Playfair Display',serif;
  font-style:italic;
  font-size:1.7rem;
  color:#fff;
  margin-bottom:4px;
}
.ap-hero-email{
  font-size:0.8rem;
  color:rgba(255,255,255,0.5);
  margin-bottom:12px;
}
.ap-hero-badges{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.ap-badge{
  display:inline-flex;
  align-items:center;
  gap:5px;
  padding:4px 12px;
  border-radius:20px;
  font-size:0.68rem;
  font-weight:700;
  letter-spacing:.06em;
}
.ap-badge-admin{
  background:rgba(201,169,110,0.2);
  color:var(--gold);
  border:1px solid rgba(201,169,110,0.35);
}
.ap-badge-verified{
  background:rgba(22,163,74,0.2);
  color:#4ade80;
  border:1px solid rgba(22,163,74,0.3);
}

.ap-hero-stat{
  z-index:1;
  text-align:center;
  flex-shrink:0;
}
.ap-stat-val{
  font-size:1.6rem;
  font-weight:800;
  color:#fff;
}
.ap-stat-lbl{
  font-size:0.63rem;
  letter-spacing:.1em;
  text-transform:uppercase;
  color:rgba(255,255,255,0.4);
  margin-top:3px;
}

.ap-grid{
  display:grid;
  grid-template-columns:1fr 300px;
  gap:20px;
  align-items:start;
}

.ap-card{
  background:var(--card);
  border-radius:18px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
  overflow:hidden;
  margin-bottom:20px;
}
.ap-card-header{
  padding:16px 22px;
  border-bottom:1px solid var(--border);
  display:flex;
  align-items:center;
  gap:10px;
}
.ap-card-icon{
  width:34px;
  height:34px;
  border-radius:8px;
  background:var(--gold-dim);
  border:1.5px solid rgba(201,169,110,0.25);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:0.9rem;
}
.ap-card-title{
  font-size:0.88rem;
  font-weight:700;
  color:var(--ink);
}
.ap-card-body{
  padding:22px;
}

.ap-fg{
  margin-bottom:18px;
}
.ap-fg label{
  display:block;
  font-size:0.67rem;
  font-weight:700;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--muted);
  margin-bottom:7px;
}
.ap-iw{
  position:relative;
}
.ap-ii{
  position:absolute;
  left:12px;
  top:50%;
  transform:translateY(-50%);
  font-size:0.85rem;
  color:var(--muted);
  pointer-events:none;
}
.ap-input,
.ap-textarea{
  width:100%;
  padding:11px 12px 11px 36px;
  border:1.5px solid var(--border);
  border-radius:10px;
  font-family:'Sarabun',sans-serif;
  font-size:0.88rem;
  color:var(--ink);
  background:#fafaf8;
  outline:none;
  transition:border-color .2s, box-shadow .2s;
}
.ap-input:focus,
.ap-textarea:focus{
  border-color:var(--gold);
  background:#fff;
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.ap-input[readonly]{
  background:#f0ece6;
  color:var(--muted);
  cursor:not-allowed;
}
.ap-textarea{
  padding:11px 12px;
  min-height:80px;
  resize:vertical;
}

.ap-btn-row{
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:12px;
  margin-top:20px;
  padding-top:18px;
  border-top:1px solid var(--border);
  flex-wrap:wrap;
}
.ap-btn{
  display:inline-flex;
  align-items:center;
  gap:7px;
  padding:11px 24px;
  border:none;
  border-radius:10px;
  font-family:'Sarabun',sans-serif;
  font-size:0.85rem;
  font-weight:700;
  cursor:pointer;
  transition:all .2s;
  letter-spacing:.04em;
  text-decoration:none;
}
.ap-btn:hover{
  transform:translateY(-1px);
}
.ap-btn-primary{
  background:var(--ink);
  color:#fff;
}
.ap-btn-primary:hover{
  background:#2a2a4a;
  box-shadow:0 6px 16px rgba(26,26,46,.2);
}
.ap-btn-primary:disabled{
  opacity:.6;
  cursor:not-allowed;
  transform:none;
}
.ap-btn-ghost{
  background:transparent;
  color:var(--muted);
  border:1.5px solid var(--border);
}
.ap-btn-ghost:hover{
  border-color:var(--gold);
  color:var(--gold);
}

.ap-info-list{
  display:flex;
  flex-direction:column;
  gap:14px;
}
.ap-info-item{
  display:flex;
  align-items:flex-start;
  gap:11px;
}
.ap-info-dot{
  width:7px;
  height:7px;
  border-radius:50%;
  background:var(--gold);
  flex-shrink:0;
  margin-top:5px;
}
.ap-info-lbl{
  font-size:0.67rem;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.1em;
  margin-bottom:2px;
}
.ap-info-val{
  font-size:0.87rem;
  font-weight:600;
  color:var(--ink);
}

.ap-sbar{
  height:4px;
  background:var(--border);
  border-radius:2px;
  margin-top:6px;
  overflow:hidden;
}
.ap-sfill{
  height:100%;
  width:0%;
  border-radius:2px;
  transition:width .3s, background .3s;
}

#ap-avatar-file{
  display:none;
}

@media(max-width:760px){
  .ap-grid{
    grid-template-columns:1fr;
  }
  .ap-hero{
    flex-direction:column;
    text-align:center;
    padding:28px 22px;
  }
}
</style>

<div class="ap-wrap">

  <?php if ($message): ?>
    <div class="ap-alert <?= $msg_type === 'error' ? 'ap-alert-error' : 'ap-alert-success' ?>">
      <?= $msg_type === 'error' ? '⚠' : '✓' ?> <?= h($message) ?>
    </div>
  <?php endif; ?>

  <div class="ap-hero">
    <div class="ap-av-wrap">
      <div class="ap-av" id="apAvDisplay">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= h($user['avatar']) ?>?v=<?= time() ?>" alt="avatar">
        <?php else: ?>
          <?= h($avatarInitial) ?>
        <?php endif; ?>
      </div>
      <label for="ap-avatar-file" class="ap-av-btn" title="เปลี่ยนรูปโปรไฟล์">📷</label>
    </div>

    <div class="ap-hero-info">
      <div class="ap-hero-name"><?= h($user['fullname'] ?? 'Admin') ?></div>
      <div class="ap-hero-email"><?= h($user['email'] ?? '') ?></div>
      <div class="ap-hero-badges">
        <span class="ap-badge ap-badge-admin">⚡ Administrator</span>
        <?php if (!empty($user['is_verified'])): ?>
          <span class="ap-badge ap-badge-verified">✓ ยืนยันอีเมลแล้ว</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="ap-hero-stat">
      <div class="ap-stat-val"><?= (int)$join_days ?></div>
      <div class="ap-stat-lbl">วันที่ใช้งาน</div>
    </div>
  </div>

  <div class="ap-grid">
    <div>
      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">✏️</div>
          <div class="ap-card-title">แก้ไขข้อมูลส่วนตัว</div>
        </div>
        <div class="ap-card-body">
          <form method="POST" enctype="multipart/form-data" id="profileForm">
            <input type="hidden" name="action" value="update_profile">
            <input type="file" id="ap-avatar-file" name="avatar" accept="image/*" onchange="previewApAv(this)">

            <div class="ap-fg">
              <label>ชื่อ-นามสกุล *</label>
              <div class="ap-iw">
                <span class="ap-ii">👤</span>
                <input class="ap-input" type="text" name="fullname" value="<?= h($user['fullname'] ?? '') ?>" required placeholder="ชื่อ-นามสกุล">
              </div>
            </div>

            <div class="ap-fg">
              <label>อีเมล <span style="font-size:.6rem;color:var(--muted);text-transform:none;">(ไม่สามารถแก้ไขได้)</span></label>
              <div class="ap-iw">
                <span class="ap-ii">✉️</span>
                <input class="ap-input" type="text" value="<?= h($user['email'] ?? '') ?>" readonly>
              </div>
            </div>

            <div class="ap-fg">
              <label>เบอร์โทรศัพท์</label>
              <div class="ap-iw">
                <span class="ap-ii">📱</span>
                <input class="ap-input" type="text" name="phone" value="<?= h($user['phone'] ?? '') ?>" placeholder="เบอร์โทรศัพท์">
              </div>
            </div>

            <div class="ap-btn-row">
              <button type="submit" class="ap-btn ap-btn-primary" id="saveProfileBtn">💾 บันทึกข้อมูล</button>
            </div>
          </form>
        </div>
      </div>

      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">🔐</div>
          <div class="ap-card-title">เปลี่ยนรหัสผ่าน</div>
        </div>
        <div class="ap-card-body">
          <form method="POST">
            <input type="hidden" name="action" value="change_password">

            <div class="ap-fg">
              <label>รหัสผ่านปัจจุบัน</label>
              <div class="ap-iw">
                <span class="ap-ii">🔒</span>
                <input class="ap-input" type="password" name="current_password" required>
              </div>
            </div>

            <div class="ap-fg">
              <label>รหัสผ่านใหม่</label>
              <div class="ap-iw">
                <span class="ap-ii">🔑</span>
                <input class="ap-input" type="password" name="new_password" oninput="apCheckStr(this.value)" placeholder="อย่างน้อย 6 ตัวอักษร" required>
              </div>
              <div class="ap-sbar"><div class="ap-sfill" id="apSfill"></div></div>
            </div>

            <div class="ap-fg">
              <label>ยืนยันรหัสผ่านใหม่</label>
              <div class="ap-iw">
                <span class="ap-ii">🔁</span>
                <input class="ap-input" type="password" name="confirm_password" required>
              </div>
            </div>

            <div class="ap-btn-row">
              <button type="submit" class="ap-btn ap-btn-primary">🔄 เปลี่ยนรหัสผ่าน</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div>
      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">📌</div>
          <div class="ap-card-title">ข้อมูลบัญชี</div>
        </div>
        <div class="ap-card-body">
          <div class="ap-info-list">
            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">สถานะ</div>
                <div class="ap-info-val">Administrator</div>
              </div>
            </div>

            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">อีเมล</div>
                <div class="ap-info-val"><?= h($user['email'] ?? '') ?></div>
              </div>
            </div>

            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">เบอร์โทรศัพท์</div>
                <div class="ap-info-val"><?= h($user['phone'] ?? '-') ?></div>
              </div>
            </div>

            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">วันที่สมัคร</div>
                <div class="ap-info-val"><?= !empty($user['created_at']) ? h(date('d/m/Y H:i', strtotime($user['created_at']))) : '-' ?></div>
              </div>
            </div>

            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">ยืนยันอีเมล</div>
                <div class="ap-info-val"><?= !empty($user['is_verified']) ? '✅ ยืนยันแล้ว' : '⏳ ยังไม่ยืนยัน' ?></div>
              </div>
            </div>

            <div class="ap-info-item">
              <div class="ap-info-dot"></div>
              <div>
                <div class="ap-info-lbl">ใช้งานมาแล้ว</div>
                <div class="ap-info-val"><?= (int)$join_days ?> วัน</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="ap-card">
        <div class="ap-card-header">
          <div class="ap-card-icon">🖼️</div>
          <div class="ap-card-title">รูปโปรไฟล์</div>
        </div>
        <div class="ap-card-body" style="text-align:center;">
          <div class="ap-av" style="width:80px;height:80px;font-size:2rem;margin:0 auto 14px;" id="apAvPreview">
            <?php if (!empty($user['avatar'])): ?>
              <img src="<?= h($user['avatar']) ?>?v=<?= time() ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else: ?>
              <?= h($avatarInitial) ?>
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
    </div>
  </div>
</div>

<script>
function previewApAv(input) {
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = function(e) {
      const img = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
      document.getElementById('apAvDisplay').innerHTML = img;
      document.getElementById('apAvPreview').innerHTML = img;
    };
    r.readAsDataURL(input.files[0]);
  }
}

function apCheckStr(val) {
  let s = 0;
  if (val.length >= 6) s++;
  if (val.length >= 10) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;

  const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
  const bar = document.getElementById('apSfill');
  bar.style.width = (s * 20) + '%';
  bar.style.background = colors[s - 1] || '#e8e4de';
}

document.getElementById('profileForm').addEventListener('submit', function() {
  const btn = document.getElementById('saveProfileBtn');
  btn.textContent = '⏳ กำลังบันทึก...';
  btn.disabled = true;
});
</script>

<?php
include 'admin_layout_bottom.php';
$conn->close();
?>