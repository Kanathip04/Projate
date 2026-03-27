<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Bangkok');

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
    $conn->set_charset("utf8mb4");
    if ($conn->connect_error) {
        die("DB Error: " . $conn->connect_error);
    }
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function safeAvatarDelete(string $relativePath): void {
    if ($relativePath === '') return;
    $relativePath = str_replace('\\', '/', $relativePath);
    if (strpos($relativePath, 'uploads/avatars/') !== 0) return;

    $fullPath = __DIR__ . '/' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function uploadAvatarIfAny(array $file, int $userId, string &$errorMessage): string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ';
        return '';
    }

    $uploadDir = __DIR__ . '/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowed, true)) {
        $errorMessage = 'อนุญาตเฉพาะไฟล์ jpg, jpeg, png, webp, gif';
        return '';
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $errorMessage = 'ขนาดไฟล์ต้องไม่เกิน 2MB';
        return '';
    }

    $newName = 'avatar_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $target = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errorMessage = 'อัปโหลดรูปภาพไม่สำเร็จ';
        return '';
    }

    return 'uploads/avatars/' . $newName;
}

$hasPhone     = hasColumn($conn, 'users', 'phone');
$hasAvatar    = hasColumn($conn, 'users', 'avatar');
$hasRole      = hasColumn($conn, 'users', 'role');
$hasVerified  = hasColumn($conn, 'users', 'is_verified');
$hasCreatedAt = hasColumn($conn, 'users', 'created_at');
$hasGender    = hasColumn($conn, 'users', 'gender');
$hasBirthDate = hasColumn($conn, 'users', 'birth_date');
$hasAddress   = hasColumn($conn, 'users', 'address');
$hasBio       = hasColumn($conn, 'users', 'bio');

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    die("ไม่พบรหัสผู้ใช้งาน");
}

$message = '';
$msgType = '';

$selectFields = ['id', 'fullname', 'email'];
if ($hasPhone)     $selectFields[] = 'phone';
if ($hasAvatar)    $selectFields[] = 'avatar';
if ($hasRole)      $selectFields[] = 'role';
if ($hasVerified)  $selectFields[] = 'is_verified';
if ($hasCreatedAt) $selectFields[] = 'created_at';
if ($hasGender)    $selectFields[] = 'gender';
if ($hasBirthDate) $selectFields[] = 'birth_date';
if ($hasAddress)   $selectFields[] = 'address';
if ($hasBio)       $selectFields[] = 'bio';

$stmt = $conn->prepare("SELECT " . implode(', ', $selectFields) . " FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_user') {
        $fullname = trim($_POST['fullname'] ?? '');
        $phone = $hasPhone ? trim($_POST['phone'] ?? '') : '';
        $gender = $hasGender ? trim($_POST['gender'] ?? '') : '';
        $birthDate = $hasBirthDate ? trim($_POST['birth_date'] ?? '') : '';
        $address = $hasAddress ? trim($_POST['address'] ?? '') : '';
        $bio = $hasBio ? trim($_POST['bio'] ?? '') : '';
        $role = $hasRole ? trim($_POST['role'] ?? 'user') : '';
        $isVerified = $hasVerified ? (int)($_POST['is_verified'] ?? 0) : 0;

        if ($fullname === '') {
            $message = 'กรุณากรอกชื่อ-นามสกุล';
            $msgType = 'error';
        } elseif ($hasPhone && $phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            $message = 'รูปแบบเบอร์โทรไม่ถูกต้อง';
            $msgType = 'error';
        } else {
            $uploadError = '';
            $newAvatar = $hasAvatar ? uploadAvatarIfAny($_FILES['avatar'] ?? [], $userId, $uploadError) : '';

            if ($uploadError !== '') {
                $message = $uploadError;
                $msgType = 'error';
            } else {
                $fields = ["fullname = ?"];
                $params = [$fullname];
                $types = "s";

                if ($hasPhone) {
                    $fields[] = "phone = ?";
                    $params[] = $phone;
                    $types .= "s";
                }

                if ($hasGender) {
                    $fields[] = "gender = ?";
                    $params[] = $gender;
                    $types .= "s";
                }

                if ($hasBirthDate) {
                    $fields[] = "birth_date = ?";
                    $params[] = ($birthDate !== '' ? $birthDate : null);
                    $types .= "s";
                }

                if ($hasAddress) {
                    $fields[] = "address = ?";
                    $params[] = $address;
                    $types .= "s";
                }

                if ($hasBio) {
                    $fields[] = "bio = ?";
                    $params[] = $bio;
                    $types .= "s";
                }

                if ($hasRole) {
                    $fields[] = "role = ?";
                    $params[] = $role;
                    $types .= "s";
                }

                if ($hasVerified) {
                    $fields[] = "is_verified = ?";
                    $params[] = $isVerified;
                    $types .= "i";
                }

                if ($hasAvatar && $newAvatar !== '') {
                    $fields[] = "avatar = ?";
                    $params[] = $newAvatar;
                    $types .= "s";
                }

                $params[] = $userId;
                $types .= "i";

                $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);

                if ($stmt->execute()) {
                    if ($hasAvatar && $newAvatar !== '' && !empty($user['avatar'])) {
                        safeAvatarDelete((string)$user['avatar']);
                    }
                    $message = 'อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว';
                    $msgType = 'success';
                } else {
                    if ($hasAvatar && $newAvatar !== '') {
                        safeAvatarDelete($newAvatar);
                    }
                    $message = 'ไม่สามารถอัปเดตข้อมูลได้';
                    $msgType = 'error';
                }
                $stmt->close();

                $stmt = $conn->prepare("SELECT " . implode(', ', $selectFields) . " FROM users WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result ? $result->fetch_assoc() : null;
                $stmt->close();
            }
        }
    }
}

$pageTitle = "โปรไฟล์ผู้ใช้งาน";
$activeMenu = "users";
include 'admin_layout_top.php';

$name = trim((string)($user['fullname'] ?? 'U'));
$firstChar = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($name, 0, 1), 'UTF-8')
    : strtoupper(substr($name, 0, 1));
?>

<style>
:root{
  --accent:#c9a96e;
  --accent-dim:rgba(201,169,110,.12);
  --ink:#1a1a2e;
  --card:#fff;
  --muted:#7a7a8c;
  --border:#e8e4de;
  --success:#16a34a;
  --danger:#dc2626;
}
.uv-wrap{max-width:1100px;margin:0 auto;padding:0 0 48px;}
.uv-alert{padding:14px 18px;border-radius:12px;font-weight:700;margin-bottom:18px;}
.uv-alert.success{background:#f0fdf4;border:1px solid #86efac;color:var(--success);}
.uv-alert.error{background:#fef2f2;border:1px solid #fca5a5;color:var(--danger);}

.uv-topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap;}
.uv-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:10px 14px;border-radius:10px;text-decoration:none;
  font-size:.85rem;font-weight:700;border:1px solid var(--border);
  color:var(--ink);background:#fff;transition:.2s ease;
}
.uv-btn:hover{transform:translateY(-1px);border-color:var(--accent);color:var(--accent);}
.uv-btn.primary{background:var(--ink);color:#fff;border-color:var(--ink);}
.uv-btn.primary:hover{background:#2b2b4f;color:#fff;}

.uv-hero{
  background:var(--ink);
  border-radius:20px;
  padding:28px 30px;
  color:#fff;
  display:flex;
  justify-content:space-between;
  gap:20px;
  align-items:center;
  margin-bottom:22px;
  position:relative;
  overflow:hidden;
}
.uv-hero::before{
  content:'';
  position:absolute;
  width:300px;height:300px;border-radius:50%;
  background:radial-gradient(circle, rgba(201,169,110,.14) 0%, transparent 70%);
  right:-80px;top:-100px;
}
.uv-user{display:flex;align-items:center;gap:16px;position:relative;z-index:1;}
.uv-avatar{
  width:88px;height:88px;border-radius:50%;
  overflow:hidden;background:var(--accent-dim);
  border:3px solid rgba(201,169,110,.35);
  display:flex;align-items:center;justify-content:center;
  color:var(--accent);font-size:2rem;font-weight:800;
}
.uv-avatar img{width:100%;height:100%;object-fit:cover;}
.uv-name{font-size:1.55rem;font-weight:800;margin-bottom:4px;}
.uv-email{font-size:.9rem;color:rgba(255,255,255,.68);}
.uv-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
.badge{
  display:inline-flex;align-items:center;gap:5px;
  padding:5px 10px;border-radius:999px;font-size:.75rem;font-weight:700;
}
.badge-admin{background:rgba(201,169,110,.15);color:#f1d29b;border:1px solid rgba(201,169,110,.3);}
.badge-user{background:rgba(255,255,255,.1);color:#e5e7eb;border:1px solid rgba(255,255,255,.15);}
.badge-success{background:rgba(22,163,74,.14);color:#86efac;border:1px solid rgba(22,163,74,.26);}
.badge-muted{background:rgba(148,163,184,.12);color:#cbd5e1;border:1px solid rgba(148,163,184,.18);}

.uv-grid{display:grid;grid-template-columns:2fr 1fr;gap:20px;}
.uv-card{
  background:var(--card);border-radius:18px;overflow:hidden;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
}
.uv-card-header{
  padding:18px 22px;border-bottom:1px solid var(--border);
  font-weight:800;color:var(--ink);
}
.uv-card-body{padding:22px;}

.uv-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.uv-group.full{grid-column:1/-1;}
.uv-group label{
  display:block;margin-bottom:7px;font-size:.72rem;font-weight:700;
  color:var(--muted);text-transform:uppercase;letter-spacing:.08em;
}
.uv-input,.uv-select,.uv-textarea{
  width:100%;padding:11px 13px;border:1.5px solid var(--border);
  border-radius:10px;background:#fafaf8;outline:none;font-size:.92rem;
}
.uv-input:focus,.uv-select:focus,.uv-textarea:focus{
  border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,169,110,.12);background:#fff;
}
.uv-input[readonly]{background:#f1ede7;color:var(--muted);}
.uv-textarea{min-height:90px;resize:vertical;}

.uv-info-list{display:flex;flex-direction:column;gap:14px;}
.uv-info-item{display:flex;align-items:flex-start;gap:10px;}
.uv-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:6px;flex-shrink:0;}
.uv-info-label{font-size:.74rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;}
.uv-info-value{font-size:.92rem;font-weight:700;color:var(--ink);margin-top:2px;}

.uv-preview{
  width:90px;height:90px;border-radius:50%;overflow:hidden;
  border:3px solid rgba(201,169,110,.25);background:var(--accent-dim);
  display:flex;align-items:center;justify-content:center;
  color:var(--accent);font-size:2rem;font-weight:800;margin-bottom:12px;
}
.uv-preview img{width:100%;height:100%;object-fit:cover;}
.uv-file{font-size:.82rem;color:var(--muted);margin-top:8px;}
.uv-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;flex-wrap:wrap;}

@media (max-width: 900px){
  .uv-grid{grid-template-columns:1fr;}
}
@media (max-width: 700px){
  .uv-form-grid{grid-template-columns:1fr;}
  .uv-group.full{grid-column:1;}
  .uv-hero{flex-direction:column;align-items:flex-start;}
}
</style>

<div class="uv-wrap">

  <div class="uv-topbar">
    <a href="manage_users.php" class="uv-btn">← กลับหน้าจัดการผู้ใช้งาน</a>
  </div>

  <?php if ($message !== ''): ?>
    <div class="uv-alert <?= $msgType === 'success' ? 'success' : 'error' ?>">
      <?= h($message) ?>
    </div>
  <?php endif; ?>

  <div class="uv-hero">
    <div class="uv-user">
      <div class="uv-avatar" id="avatarPreviewMain">
        <?php if ($hasAvatar && !empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
          <img src="<?= h($user['avatar']) ?>?v=<?= time() ?>" alt="avatar">
        <?php else: ?>
          <?= h($firstChar) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="uv-name"><?= h($user['fullname'] ?? '-') ?></div>
        <div class="uv-email"><?= h($user['email'] ?? '-') ?></div>
        <div class="uv-badges">
          <?php if ($hasRole && ($user['role'] ?? '') === 'admin'): ?>
            <span class="badge badge-admin">⚡ Administrator</span>
          <?php else: ?>
            <span class="badge badge-user">👤 Member</span>
          <?php endif; ?>

          <?php if ($hasVerified): ?>
            <?php if ((int)($user['is_verified'] ?? 0) === 1): ?>
              <span class="badge badge-success">✅ ยืนยันอีเมลแล้ว</span>
            <?php else: ?>
              <span class="badge badge-muted">⏳ ยังไม่ยืนยัน</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="position:relative;z-index:1;">
      <a href="manage_users.php" class="uv-btn">👥 รายชื่อผู้ใช้</a>
    </div>
  </div>

  <div class="uv-grid">
    <div class="uv-card">
      <div class="uv-card-header">แก้ไขข้อมูลผู้ใช้งาน</div>
      <div class="uv-card-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_user">

          <div class="uv-form-grid">
            <div class="uv-group">
              <label>ชื่อ-นามสกุล *</label>
              <input type="text" name="fullname" class="uv-input" value="<?= h($user['fullname'] ?? '') ?>" required>
            </div>

            <div class="uv-group">
              <label>อีเมล</label>
              <input type="text" class="uv-input" value="<?= h($user['email'] ?? '') ?>" readonly>
            </div>

            <?php if ($hasPhone): ?>
            <div class="uv-group">
              <label>เบอร์โทรศัพท์</label>
              <input type="text" name="phone" class="uv-input" value="<?= h($user['phone'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if ($hasRole): ?>
            <div class="uv-group">
              <label>สิทธิ์การใช้งาน</label>
              <select name="role" class="uv-select">
                <option value="user" <?= (($user['role'] ?? '') === 'user') ? 'selected' : '' ?>>Member</option>
                <option value="admin" <?= (($user['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrator</option>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($hasVerified): ?>
            <div class="uv-group">
              <label>สถานะยืนยันอีเมล</label>
              <select name="is_verified" class="uv-select">
                <option value="1" <?= ((int)($user['is_verified'] ?? 0) === 1) ? 'selected' : '' ?>>ยืนยันแล้ว</option>
                <option value="0" <?= ((int)($user['is_verified'] ?? 0) === 0) ? 'selected' : '' ?>>ยังไม่ยืนยัน</option>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($hasGender): ?>
            <div class="uv-group">
              <label>เพศ</label>
              <select name="gender" class="uv-select">
                <option value="">เลือกเพศ</option>
                <option value="ชาย" <?= (($user['gender'] ?? '') === 'ชาย') ? 'selected' : '' ?>>ชาย</option>
                <option value="หญิง" <?= (($user['gender'] ?? '') === 'หญิง') ? 'selected' : '' ?>>หญิง</option>
                <option value="อื่นๆ" <?= (($user['gender'] ?? '') === 'อื่นๆ') ? 'selected' : '' ?>>อื่นๆ</option>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($hasBirthDate): ?>
            <div class="uv-group">
              <label>วันเกิด</label>
              <input type="date" name="birth_date" class="uv-input" value="<?= h($user['birth_date'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if ($hasAddress): ?>
            <div class="uv-group full">
              <label>ที่อยู่</label>
              <textarea name="address" class="uv-textarea"><?= h($user['address'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <?php if ($hasBio): ?>
            <div class="uv-group full">
              <label>Bio</label>
              <textarea name="bio" class="uv-textarea"><?= h($user['bio'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <?php if ($hasAvatar): ?>
            <div class="uv-group full">
              <label>รูปโปรไฟล์</label>
              <input type="file" name="avatar" class="uv-input" accept="image/*" onchange="previewAvatar(event)">
              <div class="uv-file">รองรับ jpg, jpeg, png, webp, gif ขนาดไม่เกิน 2MB</div>
            </div>
            <?php endif; ?>
          </div>

          <div class="uv-actions">
            <a href="manage_users.php" class="uv-btn">ยกเลิก</a>
            <button type="submit" class="uv-btn primary">💾 บันทึกข้อมูล</button>
          </div>
        </form>
      </div>
    </div>

    <div>
      <div class="uv-card">
        <div class="uv-card-header">ข้อมูลบัญชี</div>
        <div class="uv-card-body">
          <div class="uv-preview" id="avatarPreviewSide">
            <?php if ($hasAvatar && !empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
              <img src="<?= h($user['avatar']) ?>?v=<?= time() ?>" alt="avatar">
            <?php else: ?>
              <?= h($firstChar) ?>
            <?php endif; ?>
          </div>

          <div class="uv-info-list">
            <div class="uv-info-item">
              <div class="uv-dot"></div>
              <div>
                <div class="uv-info-label">User ID</div>
                <div class="uv-info-value"><?= (int)$user['id'] ?></div>
              </div>
            </div>

            <div class="uv-info-item">
              <div class="uv-dot"></div>
              <div>
                <div class="uv-info-label">อีเมล</div>
                <div class="uv-info-value"><?= h($user['email'] ?? '-') ?></div>
              </div>
            </div>

            <?php if ($hasRole): ?>
            <div class="uv-info-item">
              <div class="uv-dot"></div>
              <div>
                <div class="uv-info-label">สิทธิ์</div>
                <div class="uv-info-value"><?= h($user['role'] ?? 'user') ?></div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($hasCreatedAt): ?>
            <div class="uv-info-item">
              <div class="uv-dot"></div>
              <div>
                <div class="uv-info-label">วันที่สมัคร</div>
                <div class="uv-info-value">
                  <?= !empty($user['created_at']) ? h(date('d/m/Y H:i', strtotime($user['created_at']))) : '-' ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($hasVerified): ?>
            <div class="uv-info-item">
              <div class="uv-dot"></div>
              <div>
                <div class="uv-info-label">ยืนยันอีเมล</div>
                <div class="uv-info-value">
                  <?= ((int)($user['is_verified'] ?? 0) === 1) ? 'ยืนยันแล้ว' : 'ยังไม่ยืนยัน' ?>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function previewAvatar(event) {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    const html = '<img src="' + e.target.result + '" alt="preview">';
    document.getElementById('avatarPreviewMain').innerHTML = html;
    document.getElementById('avatarPreviewSide').innerHTML = html;
  };
  reader.readAsDataURL(file);
}
</script>

<?php
include 'admin_layout_bottom.php';
$conn->close();
?>