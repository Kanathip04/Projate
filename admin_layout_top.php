<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ต้อง login ก่อน
if (empty($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}

// Admin → ไปหน้า admin_profile แทน
if (($_SESSION['user_role'] ?? '') === 'admin') {
    header("Location: admin_profile.php"); exit;
}

// ── เฉพาะ user ทั่วไปเท่านั้นที่เข้าถึงหน้านี้ได้ ──
require_once 'config.php';

$user_id = (int)$_SESSION['user_id'];
$message = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullname   = trim($_POST['fullname']   ?? '');
        $phone      = trim($_POST['phone']      ?? '');
        $gender     = trim($_POST['gender']     ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $address    = trim($_POST['address']    ?? '');
        $bio        = trim($_POST['bio']        ?? '');

        if (empty($fullname)) {
            $message = 'กรุณากรอกชื่อ-นามสกุล'; $msg_type = 'error';
        } else {
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
                    $st = $conn->prepare("UPDATE users SET fullname=?,phone=?,gender=?,birth_date=?,address=?,bio=?,avatar=? WHERE id=?");
                    $st->bind_param("sssssssi",$fullname,$phone,$gender,$birth_date,$address,$bio,$avatar_path,$user_id);
                } else {
                    $st = $conn->prepare("UPDATE users SET fullname=?,phone=?,gender=?,birth_date=?,address=?,bio=? WHERE id=?");
                    $st->bind_param("ssssssi",$fullname,$phone,$gender,$birth_date,$address,$bio,$user_id);
                }
                if ($st->execute()) {
                    $_SESSION['user_name'] = $fullname;
                    $message = 'อัปเดตข้อมูลเรียบร้อยแล้ว'; $msg_type = 'success';
                } else {
                    $message = 'เกิดข้อผิดพลาด: '.$st->error; $msg_type = 'error';
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
            $st->bind_param("si",$h,$user_id); $st->execute(); $st->close();
            $message = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'; $msg_type = 'success';
        }
    }
}

$user = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1")->fetch_assoc();
if (!$user) { header("Location: logout.php"); exit; }

// Stats
$bk_res        = $conn->query("SELECT COUNT(*) c FROM room_bookings WHERE email='".$conn->real_escape_string($user['email'])."'");
$booking_count = $bk_res ? (int)$bk_res->fetch_assoc()['c'] : 0;
$join_days     = (int)((time() - strtotime($user['created_at'])) / 86400);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
$avatarInitial = strtoupper(mb_substr($user['fullname']??'U',0,1));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>โปรไฟล์ — สถาบันวิจัยวลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{--ink:#1a1a2e;--gold:#c9a96e;--gold-dim:rgba(201,169,110,0.12);--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);}

.pf-nav{background:#fff;padding:0 48px;height:64px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 0 var(--border);position:sticky;top:0;z-index:100;}
.pf-nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--ink);}
.pf-nav-brand img{height:44px;}
.pf-nav-brand-text{font-size:0.88rem;font-weight:700;}
.pf-nav-brand-sub{font-size:0.7rem;color:var(--muted);}
.pf-nav-links{display:flex;align-items:center;gap:8px;}
.pf-nav-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;}
.ghost{color:var(--muted);}.ghost:hover{background:var(--bg);color:var(--ink);}
.danger{color:var(--danger);}.danger:hover{background:#fef2f2;}

.pf-hero{background:var(--ink);padding:36px 48px;display:flex;align-items:center;gap:32px;position:relative;overflow:hidden;}
.pf-hero::before{content:'';position:absolute;width:380px;height:380px;border-radius:50%;background:radial-gradient(circle,rgba(201,169,110,0.13) 0%,transparent 70%);top:-140px;right:-80px;pointer-events:none;}
.pf-hero::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(201,169,110,0.08) 0%,transparent 70%);bottom:-80px;left:-40px;pointer-events:none;}
.av-wrap{position:relative;flex-shrink:0;z-index:1;}
.av{width:96px;height:96px;border-radius:50%;background:var(--gold-dim);border:3px solid rgba(201,169,110,0.5);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-style:italic;font-size:2.4rem;color:var(--gold);overflow:hidden;}
.av img{width:100%;height:100%;object-fit:cover;}
.av-btn{position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:var(--gold);border:2px solid var(--ink);display:flex;align-items:center;justify-content:center;font-size:0.72rem;cursor:pointer;transition:transform .2s;}
.av-btn:hover{transform:scale(1.1);}
.hero-info{flex:1;z-index:1;}
.hero-name{font-family:'Playfair Display',serif;font-style:italic;font-size:1.8rem;color:#fff;margin-bottom:5px;}
.hero-email{font-size:0.8rem;color:rgba(255,255,255,0.5);margin-bottom:12px;}
.hero-badges{display:flex;gap:8px;flex-wrap:wrap;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 11px;border-radius:20px;font-size:0.67rem;font-weight:700;letter-spacing:.06em;}
.b-member{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.65);border:1px solid rgba(255,255,255,0.2);}
.b-verified{background:rgba(22,163,74,0.2);color:#4ade80;border:1px solid rgba(22,163,74,0.3);}
.hero-stats{display:flex;gap:30px;z-index:1;flex-shrink:0;}
.hs{text-align:center;}
.hs-val{font-size:1.6rem;font-weight:800;color:#fff;}
.hs-lbl{font-size:0.63rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,0.4);margin-top:3px;}

.pf-main{max-width:1000px;margin:0 auto;padding:28px 24px 64px;display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;}

.pf-alert-wrap{max-width:1000px;margin:16px auto 0;padding:0 24px;}
.pf-alert{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:12px;font-size:0.85rem;font-weight:600;}
.a-success{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.a-error{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

.card{background:var(--card);border-radius:18px;box-shadow:0 2px 12px rgba(26,26,46,.06);overflow:hidden;margin-bottom:20px;}
.card-hd{padding:15px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.card-ico{width:32px;height:32px;border-radius:8px;background:var(--gold-dim);border:1.5px solid rgba(201,169,110,0.25);display:flex;align-items:center;justify-content:center;font-size:0.85rem;}
.card-title{font-size:0.87rem;font-weight:700;color:var(--ink);}
.card-bd{padding:20px;}

.fg-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.fg{}.fg.full{grid-column:1/-1;}
.fg label{display:block;font-size:0.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
.iw{position:relative;}
.ii{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:0.82rem;color:var(--muted);pointer-events:none;}
.inp,.sel,.ta{width:100%;padding:10px 12px 10px 36px;border:1.5px solid var(--border);border-radius:10px;font-family:'Sarabun',sans-serif;font-size:0.87rem;color:var(--ink);background:#fafaf8;outline:none;transition:border-color .2s,box-shadow .2s;}
.inp:focus,.sel:focus,.ta:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.inp[readonly]{background:#f0ece6;color:var(--muted);cursor:not-allowed;}
.ta{padding:10px 12px;min-height:80px;resize:vertical;}
.sel{appearance:none;-webkit-appearance:none;}
.sw::after{content:'▾';position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:0.75rem;color:var(--muted);pointer-events:none;}
.btn-row{display:flex;justify-content:flex-end;margin-top:16px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:none;border-radius:10px;font-family:'Sarabun',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;transition:all .2s;}
.btn:hover{transform:translateY(-1px);}
.btn-ink{background:var(--ink);color:#fff;}.btn-ink:hover{background:#2a2a4a;box-shadow:0 6px 16px rgba(26,26,46,.2);}
.sbar{height:4px;background:var(--border);border-radius:2px;margin-top:6px;overflow:hidden;}
.sfill{height:100%;width:0%;border-radius:2px;transition:width .3s,background .3s;}

.il{display:flex;flex-direction:column;gap:14px;}
.ii-item{display:flex;align-items:flex-start;gap:11px;}
.ii-dot{width:7px;height:7px;border-radius:50%;background:var(--gold);flex-shrink:0;margin-top:5px;}
.ii-lbl{font-size:0.67rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:2px;}
.ii-val{font-size:0.87rem;font-weight:600;color:var(--ink);}

.bk{display:flex;align-items:center;gap:11px;padding:11px;border-radius:10px;border:1.5px solid var(--border);background:#fafaf8;margin-bottom:9px;transition:border-color .2s;}
.bk:hover{border-color:var(--gold);}
.bk:last-child{margin-bottom:0;}
.bk-ico{font-size:1.1rem;flex-shrink:0;}
.bk-room{font-size:0.83rem;font-weight:700;color:var(--ink);}
.bk-date{font-size:0.72rem;color:var(--muted);margin-top:2px;}
.bk-st{margin-left:auto;flex-shrink:0;display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:0.67rem;font-weight:700;}
.bk-st.approved{background:#f0fdf4;color:var(--success);}
.bk-st.pending{background:#fffbeb;color:#92400e;}
.bk-st.cancelled{background:#fef2f2;color:var(--danger);}
.bk-empty{text-align:center;padding:24px;color:var(--muted);font-size:0.82rem;}

#avatar-file{display:none;}

@media(max-width:820px){.pf-main{grid-template-columns:1fr;}.pf-hero{flex-direction:column;text-align:center;padding:28px 24px;}.hero-stats{justify-content:center;}.pf-nav{padding:0 20px;}}
@media(max-width:560px){.fg-grid{grid-template-columns:1fr;}.fg.full{grid-column:1;}.hero-name{font-size:1.4rem;}}
</style>
</head>
<body>

<nav class="pf-nav">
  <a href="index.php" class="pf-nav-brand">
    <img src="Logo.png" alt="Logo">
    <div>
      <div class="pf-nav-brand-text">สถาบันวิจัยวลัยรุกขเวช</div>
      <div class="pf-nav-brand-sub">มหาวิทยาลัยมหาสารคาม</div>
    </div>
  </a>
  <div class="pf-nav-links">
    <a href="index.php"        class="pf-nav-link ghost">🏠 หน้าหลัก</a>
    <a href="booking_room.php" class="pf-nav-link ghost">🏨 จองห้องพัก</a>
    <a href="logout.php"       class="pf-nav-link danger">🚪 ออกจากระบบ</a>
  </div>
</nav>

<div class="pf-hero">
  <div class="av-wrap">
    <div class="av" id="avDisplay">
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?= h($user['avatar']) ?>" alt="avatar">
      <?php else: ?>
        <?= $avatarInitial ?>
      <?php endif; ?>
    </div>
    <label for="avatar-file" class="av-btn" title="เปลี่ยนรูป">📷</label>
  </div>
  <div class="hero-info">
    <div class="hero-name"><?= h($user['fullname'] ?? 'ผู้ใช้งาน') ?></div>
    <div class="hero-email"><?= h($user['email']) ?></div>
    <div class="hero-badges">
      <span class="badge b-member">👤 Member</span>
      <?php if ($user['is_verified']): ?>
        <span class="badge b-verified">✓ ยืนยันอีเมลแล้ว</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-stats">
    <div class="hs"><div class="hs-val"><?= $booking_count ?></div><div class="hs-lbl">การจอง</div></div>
    <div class="hs"><div class="hs-val"><?= $join_days ?></div><div class="hs-lbl">วันที่ใช้งาน</div></div>
  </div>
</div>

<?php if ($message): ?>
  <div class="pf-alert-wrap">
    <div class="pf-alert <?= $msg_type==='error'?'a-error':'a-success' ?>">
      <?= $msg_type==='error'?'⚠':'✓' ?> <?= h($message) ?>
    </div>
  </div>
<?php endif; ?>

<div class="pf-main">
  <div>
    <div class="card">
      <div class="card-hd"><div class="card-ico">✏️</div><div class="card-title">แก้ไขข้อมูลส่วนตัว</div></div>
      <div class="card-bd">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_profile">
          <input type="file" id="avatar-file" name="avatar" accept="image/*" onchange="previewAv(this)">
          <div class="fg-grid">
            <div class="fg full"><label>ชื่อ-นามสกุล *</label><div class="iw"><span class="ii">👤</span><input class="inp" type="text" name="fullname" value="<?= h($user['fullname']??'') ?>" required></div></div>
            <div class="fg"><label>อีเมล</label><div class="iw"><span class="ii">✉️</span><input class="inp" type="text" value="<?= h($user['email']) ?>" readonly></div></div>
            <div class="fg"><label>เบอร์โทรศัพท์</label><div class="iw"><span class="ii">📱</span><input class="inp" type="text" name="phone" value="<?= h($user['phone']??'') ?>" placeholder="08x-xxx-xxxx"></div></div>
            <div class="fg"><label>เพศ</label><div class="iw sw"><span class="ii">⚧</span><select class="sel" name="gender"><option value="">ไม่ระบุ</option><option value="ชาย" <?= ($user['gender']??'')==='ชาย'?'selected':'' ?>>ชาย</option><option value="หญิง" <?= ($user['gender']??'')==='หญิง'?'selected':'' ?>>หญิง</option><option value="อื่นๆ" <?= ($user['gender']??'')==='อื่นๆ'?'selected':'' ?>>อื่นๆ</option></select></div></div>
            <div class="fg"><label>วันเกิด</label><div class="iw"><span class="ii">🎂</span><input class="inp" type="date" name="birth_date" value="<?= h($user['birth_date']??'') ?>"></div></div>
            <div class="fg full"><label>ที่อยู่</label><div class="iw"><span class="ii">🏠</span><input class="inp" type="text" name="address" value="<?= h($user['address']??'') ?>" placeholder="ที่อยู่ของคุณ"></div></div>
            <div class="fg full"><label>แนะนำตัวเอง</label><div class="iw"><textarea class="ta" name="bio" placeholder="เล่าเกี่ยวกับตัวคุณสักเล็กน้อย..."><?= h($user['bio']??'') ?></textarea></div></div>
          </div>
          <div class="btn-row"><button type="submit" class="btn btn-ink">💾 บันทึกข้อมูล</button></div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-hd"><div class="card-ico">🔐</div><div class="card-title">เปลี่ยนรหัสผ่าน</div></div>
      <div class="card-bd">
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="fg-grid">
            <div class="fg"><label>รหัสผ่านใหม่</label><div class="iw"><span class="ii">🔑</span><input class="inp" type="password" name="new_password" placeholder="อย่างน้อย 6 ตัวอักษร" required oninput="checkStr(this.value)"></div><div class="sbar"><div class="sfill" id="sfill"></div></div></div>
            <div class="fg"><label>ยืนยันรหัสผ่านใหม่</label><div class="iw"><span class="ii">🔑</span><input class="inp" type="password" name="confirm_password" placeholder="••••••••" required></div></div>
          </div>
          <div class="btn-row"><button type="submit" class="btn btn-ink">🔄 เปลี่ยนรหัสผ่าน</button></div>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-hd"><div class="card-ico">🔖</div><div class="card-title">ข้อมูลบัญชี</div></div>
      <div class="card-bd">
        <div class="il">
          <div class="ii-item"><div class="ii-dot"></div><div><div class="ii-lbl">สถานะ</div><div class="ii-val">👤 Member</div></div></div>
          <div class="ii-item"><div class="ii-dot"></div><div><div class="ii-lbl">วันที่สมัคร</div><div class="ii-val"><?= h(date('d M Y',strtotime($user['created_at']))) ?></div></div></div>
          <div class="ii-item"><div class="ii-dot"></div><div><div class="ii-lbl">ยืนยันอีเมล</div><div class="ii-val"><?= $user['is_verified']?'✅ ยืนยันแล้ว':'⏳ ยังไม่ยืนยัน' ?></div></div></div>
          <div class="ii-item"><div class="ii-dot"></div><div><div class="ii-lbl">การจองทั้งหมด</div><div class="ii-val"><?= $booking_count ?> ครั้ง</div></div></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-hd"><div class="card-ico">🏨</div><div class="card-title">การจองล่าสุด</div></div>
      <div class="card-bd" style="padding:14px;">
        <?php
        $bk = $conn->prepare("SELECT * FROM room_bookings WHERE email=? ORDER BY id DESC LIMIT 5");
        $bk->bind_param("s",$user['email']); $bk->execute();
        $bkr = $bk->get_result();
        $stLbl = ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','cancelled'=>'ยกเลิก'];
        if ($bkr->num_rows > 0):
          while ($r = $bkr->fetch_assoc()):
            $s = $r['booking_status']??'pending';
        ?>
          <div class="bk"><div class="bk-ico">🛏️</div><div><div class="bk-room"><?= h($r['room_type']) ?></div><div class="bk-date"><?= h($r['checkin_date']) ?> → <?= h($r['checkout_date']) ?></div></div><span class="bk-st <?= $s ?>"><?= $stLbl[$s]??$s ?></span></div>
        <?php endwhile; else: ?>
          <div class="bk-empty"><div style="font-size:1.8rem;opacity:.3;margin-bottom:8px;">🏨</div>ยังไม่มีประวัติการจอง<br><a href="booking_room.php" style="color:var(--gold);font-weight:600;text-decoration:none;font-size:0.8rem;">จองห้องพักเลย →</a></div>
        <?php endif; $bk->close(); ?>
      </div>
    </div>

    <div class="card">
      <div class="card-hd"><div class="card-ico">⚡</div><div class="card-title">เมนูด่วน</div></div>
      <div class="card-bd" style="display:flex;flex-direction:column;gap:8px;">
        <a href="booking_room.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);text-decoration:none;color:var(--ink);font-size:0.85rem;font-weight:600;transition:border-color .2s;" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">🏨 <span>จองห้องพัก</span></a>
        <a href="view_data.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);text-decoration:none;color:var(--ink);font-size:0.85rem;font-weight:600;transition:border-color .2s;" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">📋 <span>ลงทะเบียนกิจกรรม</span></a>
        <a href="news.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);text-decoration:none;color:var(--ink);font-size:0.85rem;font-weight:600;transition:border-color .2s;" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">📰 <span>ข่าวสาร</span></a>
        <a href="logout.php" style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;border:1.5px solid #fca5a5;text-decoration:none;color:var(--danger);font-size:0.85rem;font-weight:600;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">🚪 <span>ออกจากระบบ</span></a>
      </div>
    </div>
  </div>
</div>

<script>
function previewAv(input) {
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = e => { document.getElementById('avDisplay').innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;">'; };
    r.readAsDataURL(input.files[0]);
  }
}
function checkStr(val) {
  let s=0;
  if(val.length>=6)s++;if(val.length>=10)s++;
  if(/[A-Z]/.test(val))s++;if(/[0-9]/.test(val))s++;if(/[^A-Za-z0-9]/.test(val))s++;
  const c=['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
  const b=document.getElementById('sfill');
  b.style.width=(s*20)+'%'; b.style.background=c[s-1]||'#e8e4de';
}
</script>
</body>
</html>
<?php $conn->close(); ?>