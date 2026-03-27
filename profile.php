<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once 'config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$message  = '';
$msg_type = '';

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function getUserById(mysqli $conn, int $user_id): ?array {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $user ?: null;
}

function getBookingCountByEmail(mysqli $conn, string $email): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM room_bookings WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['c' => 0];
    $stmt->close();
    return (int)($row['c'] ?? 0);
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
        $errorMessage = 'รูปภาพต้องเป็น jpg, jpeg, png, webp หรือ gif เท่านั้น';
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

$user = getUserById($conn, $user_id);
if (!$user) {
    header("Location: logout.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullname   = trim($_POST['fullname'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $gender     = trim($_POST['gender'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $bio        = trim($_POST['bio'] ?? '');

        if ($fullname === '') {
            $message = 'กรุณากรอกชื่อ-นามสกุล';
            $msg_type = 'error';
        } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            $message = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
            $msg_type = 'error';
        } elseif ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            $message = 'รูปแบบวันเกิดไม่ถูกต้อง';
            $msg_type = 'error';
        } elseif (mb_strlen($bio) > 1000) {
            $message = 'Bio ต้องไม่เกิน 1000 ตัวอักษร';
            $msg_type = 'error';
        } else {
            $upload_error = '';
            $avatar_path = uploadAvatarIfAny($_FILES['avatar'] ?? [], $user_id, $upload_error);

            if ($upload_error !== '') {
                $message = $upload_error;
                $msg_type = 'error';
            } else {
                if ($avatar_path !== '') {
                    $stmt = $conn->prepare("
                        UPDATE users
                        SET fullname = ?, phone = ?, gender = ?, birth_date = ?, address = ?, bio = ?, avatar = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param(
                        "sssssssi",
                        $fullname,
                        $phone,
                        $gender,
                        $birth_date,
                        $address,
                        $bio,
                        $avatar_path,
                        $user_id
                    );
                } else {
                    $stmt = $conn->prepare("
                        UPDATE users
                        SET fullname = ?, phone = ?, gender = ?, birth_date = ?, address = ?, bio = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param(
                        "ssssssi",
                        $fullname,
                        $phone,
                        $gender,
                        $birth_date,
                        $address,
                        $bio,
                        $user_id
                    );
                }

                if ($stmt->execute()) {
                    if ($avatar_path !== '' && !empty($user['avatar'])) {
                        safeAvatarDelete((string)$user['avatar']);
                    }

                    $_SESSION['user_name'] = $fullname;
                    $message = 'อัปเดตข้อมูลเรียบร้อยแล้ว';
                    $msg_type = 'success';
                    $user = getUserById($conn, $user_id);
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
            $message = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
            $msg_type = 'error';
        } elseif ($new_pass !== $confirm) {
            $message = 'รหัสผ่านใหม่ไม่ตรงกัน';
            $msg_type = 'error';
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);

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

$user = getUserById($conn, $user_id);
if (!$user) {
    header("Location: logout.php");
    exit;
}

$booking_count = getBookingCountByEmail($conn, (string)($user['email'] ?? ''));
$join_days = !empty($user['created_at']) ? (int)((time() - strtotime($user['created_at'])) / 86400) : 0;

$roleLabel = ($user['role'] ?? 'user') === 'admin' ? 'Administrator' : 'Member';
$roleBadge = ($user['role'] ?? 'user') === 'admin' ? 'badge-admin' : 'badge-member';

$fullnameSafe = trim((string)($user['fullname'] ?? 'U'));
if (function_exists('mb_substr')) {
    $avatarInitial = mb_strtoupper(mb_substr($fullnameSafe, 0, 1), 'UTF-8');
} else {
    $avatarInitial = strtoupper(substr($fullnameSafe, 0, 1));
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
if ($isAdmin) {
    $pageTitle = "โปรไฟล์";
    $activeMenu = "profile";
    include 'admin_layout_top.php';
}
?>
<?php if (!$isAdmin): ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>โปรไฟล์ — Lumière</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet"/>
</head>
<body style="background:#f5f1eb;font-family:'Sarabun',sans-serif;margin:0;padding:0;">
<?php endif; ?>

<style>
:root{
  --ink:#1a1a2e; --gold:#c9a96e; --gold-dim:rgba(201,169,110,0.12);
  --bg:#f5f1eb; --card:#fff; --muted:#7a7a8c; --border:#e8e4de;
  --danger:#dc2626; --success:#16a34a;
}
.pf-wrap{max-width:960px;margin:0 auto;padding:32px 24px 64px;}
.pf-alert{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:12px;font-size:0.85rem;font-weight:600;margin-bottom:24px;animation:slideDown .3s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.pf-alert-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.pf-alert-error{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

.pf-hero{
  background:var(--ink);border-radius:20px;padding:36px 40px;
  display:flex;align-items:center;gap:32px;margin-bottom:24px;
  position:relative;overflow:hidden;
}
.pf-hero::before{
  content:'';position:absolute;width:320px;height:320px;border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,0.14) 0%,transparent 70%);
  top:-100px;right:-80px;pointer-events:none;
}
.pf-hero::after{
  content:'';position:absolute;width:180px;height:180px;border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,0.08) 0%,transparent 70%);
  bottom:-60px;left:-40px;pointer-events:none;
}
.pf-avatar-wrap{position:relative;flex-shrink:0;z-index:1;}
.pf-avatar{
  width:96px;height:96px;border-radius:50%;
  background:var(--gold-dim);border:3px solid rgba(201,169,110,0.5);
  display:flex;align-items:center;justify-content:center;
  font-family:'Playfair Display',serif;font-style:italic;
  font-size:2.4rem;color:var(--gold);overflow:hidden;
}
.pf-avatar img{width:100%;height:100%;object-fit:cover;}
.pf-avatar-edit{
  position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;
  background:var(--gold);border:2px solid var(--ink);display:flex;align-items:center;
  justify-content:center;font-size:0.7rem;cursor:pointer;transition:transform .2s;
}
.pf-avatar-edit:hover{transform:scale(1.1);}
.pf-hero-info{flex:1;z-index:1;}
.pf-hero-name{
  font-family:'Playfair Display',serif;font-style:italic;
  font-size:1.8rem;color:#fff;margin-bottom:6px;
}
.pf-hero-email{font-size:0.82rem;color:rgba(255,255,255,0.5);margin-bottom:12px;}
.pf-badges{display:flex;gap:8px;flex-wrap:wrap;}
.pf-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:0.7rem;font-weight:700;letter-spacing:.06em;}
.badge-admin{background:rgba(201,169,110,0.2);color:var(--gold);border:1px solid rgba(201,169,110,0.35);}
.badge-member{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.2);}
.badge-verified{background:rgba(22,163,74,0.2);color:#4ade80;border:1px solid rgba(22,163,74,0.3);}
.pf-hero-stats{display:flex;gap:28px;z-index:1;}
.pf-stat{text-align:center;}
.pf-stat-val{font-size:1.5rem;font-weight:800;color:#fff;}
.pf-stat-lbl{font-size:0.68rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,0.4);}

.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.pf-card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.06);overflow:hidden;}
.pf-card.full{grid-column:1/-1;}
.pf-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.pf-card-icon{
  width:36px;height:36px;border-radius:10px;background:var(--gold-dim);
  border:1.5px solid rgba(201,169,110,0.25);display:flex;align-items:center;justify-content:center;font-size:1rem;
}
.pf-card-title{font-size:0.88rem;font-weight:700;color:var(--ink);}
.pf-card-body{padding:24px;}

.pf-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.pf-form-group.full{grid-column:1/-1;}
.pf-form-group label{
  display:block;font-size:0.68rem;font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:var(--muted);margin-bottom:7px;
}
.pf-input-wrap{position:relative;}
.pf-input-icon{
  position:absolute;left:12px;top:50%;transform:translateY(-50%);
  font-size:0.85rem;color:var(--muted);pointer-events:none;
}
.pf-input,.pf-select,.pf-textarea{
  width:100%;padding:10px 12px 10px 36px;border:1.5px solid var(--border);
  border-radius:10px;font-family:'Sarabun',sans-serif;font-size:0.88rem;
  color:var(--ink);background:#fafaf8;outline:none;transition:border-color .2s,box-shadow .2s;
}
.pf-input:focus,.pf-select:focus,.pf-textarea:focus{
  border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.pf-textarea{padding:10px 12px;min-height:80px;resize:vertical;}
.pf-input[readonly]{background:#f0ece6;color:var(--muted);cursor:not-allowed;}
.pf-select-wrap::after{
  content:'▾';position:absolute;right:12px;top:50%;transform:translateY(-50%);
  font-size:0.8rem;color:var(--muted);pointer-events:none;
}
.pf-select{appearance:none;-webkit-appearance:none;}

.pf-btn{
  display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:none;border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;text-decoration:none;
  transition:all .2s;letter-spacing:.04em;
}
.pf-btn:hover{transform:translateY(-1px);}
.pf-btn-primary{background:var(--ink);color:#fff;}
.pf-btn-primary:hover{background:#2a2a4a;box-shadow:0 6px 16px rgba(26,26,46,.2);}
.pf-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.pf-btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.pf-btn-row{display:flex;gap:10px;margin-top:20px;justify-content:flex-end;flex-wrap:wrap;}

.pf-info-list{display:flex;flex-direction:column;gap:14px;}
.pf-info-item{display:flex;align-items:center;gap:12px;}
.pf-info-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;}
.pf-info-lbl{font-size:0.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:2px;}
.pf-info-val{font-size:0.88rem;font-weight:600;color:var(--ink);}

.strength-bar{height:4px;background:var(--border);border-radius:2px;margin-top:6px;overflow:hidden;}
.strength-fill{height:100%;width:0%;border-radius:2px;transition:width .3s,background .3s;}
#avatar-file{display:none;}

@media(max-width:700px){
  .pf-grid{grid-template-columns:1fr;}
  .pf-card.full{grid-column:1;}
  .pf-form-grid{grid-template-columns:1fr;}
  .pf-form-group.full{grid-column:1;}
  .pf-hero{flex-direction:column;text-align:center;padding:28px 24px;}
  .pf-hero-stats{justify-content:center;}
}
</style>

<div class="pf-wrap">

  <?php if ($message): ?>
    <div class="pf-alert <?= $msg_type === 'error' ? 'pf-alert-error' : 'pf-alert-success' ?>">
      <?= $msg_type === 'error' ? '⚠' : '✓' ?> <?= h($message) ?>
    </div>
  <?php endif; ?>

  <div class="pf-hero">
    <div class="pf-avatar-wrap">
      <div class="pf-avatar">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= h($user['avatar']) ?>?v=<?= time() ?>" alt="avatar">
        <?php else: ?>
          <?= h($avatarInitial) ?>
        <?php endif; ?>
      </div>
      <label for="avatar-file" class="pf-avatar-edit" title="เปลี่ยนรูปโปรไฟล์">📷</label>
    </div>

    <div class="pf-hero-info">
      <div class="pf-hero-name"><?= h($user['fullname'] ?? 'ผู้ใช้งาน') ?></div>
      <div class="pf-hero-email"><?= h($user['email'] ?? '') ?></div>
      <div class="pf-badges">
        <span class="pf-badge <?= h($roleBadge) ?>"><?= h($roleLabel) ?></span>
        <?php if (!empty($user['is_verified'])): ?>
          <span class="pf-badge badge-verified">✓ ยืนยันอีเมลแล้ว</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="pf-hero-stats">
      <div class="pf-stat">
        <div class="pf-stat-val"><?= (int)$booking_count ?></div>
        <div class="pf-stat-lbl">การจอง</div>
      </div>
      <div class="pf-stat">
        <div class="pf-stat-val"><?= (int)$join_days ?></div>
        <div class="pf-stat-lbl">วันสมาชิก</div>
      </div>
    </div>
  </div>

  <div class="pf-grid">
    <div class="pf-card full">
      <div class="pf-card-header">
        <div class="pf-card-icon">✏️</div>
        <div class="pf-card-title">แก้ไขข้อมูลส่วนตัว</div>
      </div>
      <div class="pf-card-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_profile">
          <input type="file" id="avatar-file" name="avatar" accept="image/*" onchange="previewAvatar(this)">

          <div class="pf-form-grid">
            <div class="pf-form-group">
              <label>ชื่อ-นามสกุล *</label>
              <div class="pf-input-wrap">
                <span class="pf-input-icon">👤</span>
                <input class="pf-input" type="text" name="fullname" value="<?= h($user['fullname'] ?? '') ?>" required>
              </div>
            </div>

            <div class="pf-form-group">
              <label>อีเมล</label>
              <div class="pf-input-wrap">
                <span class="pf-input-icon">✉️</span>
                <input class="pf-input" type="text" value="<?= h($user['email'] ?? '') ?>" readonly>
              </div>
            </div>

            <div class="pf-form-group">
              <label>เบอร์โทรศัพท์</label>
              <div class="pf-input-wrap">
                <span class="pf-input-icon">📱</span>
                <input class="pf-input" type="text" name="phone" value="<?= h($user['phone'] ?? '') ?>">
              </div>
            </div>

            <div class="pf-form-group">
              <label>เพศ</label>
              <div class="pf-input-wrap pf-select-wrap">
                <span class="pf-input-icon">⚧</span>
                <select class="pf-select" name="gender">
                  <option value="">เลือกเพศ</option>
                  <option value="ชาย" <?= (($user['gender'] ?? '') === 'ชาย') ? 'selected' : '' ?>>ชาย</option>
                  <option value="หญิง" <?= (($user['gender'] ?? '') === 'หญิง') ? 'selected' : '' ?>>หญิง</option>
                  <option value="อื่นๆ" <?= (($user['gender'] ?? '') === 'อื่นๆ') ? 'selected' : '' ?>>อื่นๆ</option>
                </select>
              </div>
            </div>

            <div class="pf-form-group">
              <label>วันเกิด</label>
              <div class="pf-input-wrap">
                <span class="pf-input-icon">🎂</span>
                <input class="pf-input" type="date" name="birth_date" value="<?= h($user['birth_date'] ?? '') ?>">
              </div>
            </div>

            <div class="pf-form-group full">
              <label>ที่อยู่</label>
              <textarea class="pf-textarea" name="address"><?= h($user['address'] ?? '') ?></textarea>
            </div>

            <div class="pf-form-group full">
              <label>Bio</label>
              <textarea class="pf-textarea" name="bio" maxlength="1000"><?= h($user['bio'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="pf-btn-row">
            <button type="submit" class="pf-btn pf-btn-primary">💾 บันทึกข้อมูล</button>
          </div>
        </form>
      </div>
    </div>

    <div class="pf-card">
      <div class="pf-card-header">
        <div class="pf-card-icon">🔐</div>
        <div class="pf-card-title">เปลี่ยนรหัสผ่าน</div>
      </div>
      <div class="pf-card-body">
        <form method="POST">
          <input type="hidden" name="action" value="change_password">

          <div class="pf-form-group">
            <label>รหัสผ่านปัจจุบัน</label>
            <div class="pf-input-wrap">
              <span class="pf-input-icon">🔒</span>
              <input class="pf-input" type="password" name="current_password" required>
            </div>
          </div>

          <div class="pf-form-group">
            <label>รหัสผ่านใหม่</label>
            <div class="pf-input-wrap">
              <span class="pf-input-icon">🔑</span>
              <input class="pf-input" type="password" name="new_password" placeholder="อย่างน้อย 6 ตัวอักษร" oninput="checkStrength(this.value)" required>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="sbar"></div></div>
          </div>

          <div class="pf-form-group">
            <label>ยืนยันรหัสผ่านใหม่</label>
            <div class="pf-input-wrap">
              <span class="pf-input-icon">🔑</span>
              <input class="pf-input" type="password" name="confirm_password" required>
            </div>
          </div>

          <div class="pf-btn-row">
            <button type="submit" class="pf-btn pf-btn-primary">🔄 เปลี่ยนรหัสผ่าน</button>
          </div>
        </form>
      </div>
    </div>

    <div class="pf-card">
      <div class="pf-card-header">
        <div class="pf-card-icon">📌</div>
        <div class="pf-card-title">ข้อมูลบัญชี</div>
      </div>
      <div class="pf-card-body">
        <div class="pf-info-list">
          <div class="pf-info-item">
            <div class="pf-info-dot"></div>
            <div>
              <div class="pf-info-lbl">สถานะ</div>
              <div class="pf-info-val"><?= h($roleLabel) ?></div>
            </div>
          </div>

          <div class="pf-info-item">
            <div class="pf-info-dot"></div>
            <div>
              <div class="pf-info-lbl">อีเมล</div>
              <div class="pf-info-val"><?= h($user['email'] ?? '') ?></div>
            </div>
          </div>

          <div class="pf-info-item">
            <div class="pf-info-dot"></div>
            <div>
              <div class="pf-info-lbl">ยืนยันอีเมล</div>
              <div class="pf-info-val"><?= !empty($user['is_verified']) ? '✅ ยืนยันแล้ว' : '⏳ ยังไม่ยืนยัน' ?></div>
            </div>
          </div>

          <div class="pf-info-item">
            <div class="pf-info-dot"></div>
            <div>
              <div class="pf-info-lbl">วันที่สมัคร</div>
              <div class="pf-info-val"><?= !empty($user['created_at']) ? h(date('d/m/Y H:i', strtotime($user['created_at']))) : '-' ?></div>
            </div>
          </div>

          <div class="pf-info-item">
            <div class="pf-info-dot"></div>
            <div>
              <div class="pf-info-lbl">ใช้งานมาแล้ว</div>
              <div class="pf-info-val"><?= (int)$join_days ?> วัน</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!$isAdmin): ?>
    <div style="text-align:center;margin-top:32px;">
      <a href="index.php" class="pf-btn pf-btn-ghost">← กลับหน้าหลัก</a>
      <a href="logout.php" style="margin-left:12px;" class="pf-btn pf-btn-ghost">ออกจากระบบ</a>
    </div>
  <?php endif; ?>

</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const av = document.querySelector('.pf-avatar');
      av.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function checkStrength(val) {
  const bar = document.getElementById('sbar');
  let s = 0;
  if (val.length >= 6) s++;
  if (val.length >= 10) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const colors = ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
  bar.style.width = (s * 20) + '%';
  bar.style.background = colors[s - 1] || '#e8e4de';
}
</script>

<?php if ($isAdmin): ?>
  <?php include 'admin_layout_bottom.php'; ?>
<?php else: ?>
</body>
</html>
<?php endif; ?>

<?php $conn->close(); ?>