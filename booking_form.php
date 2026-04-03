<?php
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$room_id  = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$checkin  = trim($_GET['checkin'] ?? '');
$checkout = trim($_GET['checkout'] ?? '');
$guests   = trim($_GET['guests'] ?? '1');

if ($room_id <= 0) {
    die("ไม่พบข้อมูลห้องพัก");
}

/* === ดึงข้อมูลห้องพัก === */
$stmt = $conn->prepare("
    SELECT id, room_name, room_type, price, room_size, bed_type, capacity, image, description, total_rooms
    FROM rooms
    WHERE id = ? AND status = 'show'
    LIMIT 1
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("ไม่พบข้อมูลห้องพัก");
}

$room = $result->fetch_assoc();
$stmt->close();

$total_rooms = max(1, (int)($room['total_rooms'] ?? 1));

/* === ดึงข้อมูล profile ของ user === */
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = '';
if (!empty($_SESSION['user_id'])) {
    $uStmt = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $_SESSION['user_id']);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    $user_phone = $uRow['phone'] ?? '';
}

/* === ดึงหน่วยห้องที่ถูกจองแล้ว (pending + approved) === */
$takenUnits = []; // unit_number => 'pending'|'approved'
$colRes = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA='backoffice_db' AND TABLE_NAME='room_bookings' AND COLUMN_NAME='room_units'");
$colRow = $colRes ? $colRes->fetch_assoc() : null;
if ($colRow && (int)$colRow['cnt'] > 0) {
    $takenStmt = $conn->prepare(
        "SELECT room_units, booking_status FROM room_bookings
         WHERE room_id = ? AND booking_status IN ('pending','approved')
         AND (archived IS NULL OR archived=0)
         AND room_units IS NOT NULL AND room_units != ''"
    );
    $takenStmt->bind_param("i", $room_id);
    $takenStmt->execute();
    $takenResult = $takenStmt->get_result();
    while ($tr = $takenResult->fetch_assoc()) {
        $units = json_decode($tr['room_units'], true) ?: [];
        foreach ($units as $u) {
            $takenUnits[(int)$u] = $tr['booking_status'];
        }
    }
    $takenStmt->close();
}

if ($guests === '' || !is_numeric($guests) || (int)$guests < 1) {
    $guests = 1;
}
$today = date('Y-m-d');
if ($checkin === '' || $checkin < $today) {
    $checkin = $today;
}
if ($checkout === '' || $checkout <= $checkin) {
    $checkout = date('Y-m-d', strtotime($checkin . ' +1 day'));
}

$approvedCount = count(array_filter($takenUnits, fn($s) => in_array($s, ['approved','pending'])));
$availCount = $total_rooms - $approvedCount;

$roomImg = '';
foreach (['image_path','image'] as $col) {
    if (!empty($room[$col])) {
        $candidate = $room[$col];
        // ตรวจว่าไฟล์มีจริงใน server
        $localPath = __DIR__ . '/' . ltrim($candidate, '/');
        if (file_exists($localPath)) { $roomImg = $candidate; break; }
    }
}
$pricePerNight = (float)$room['price'];
$capacity      = (int)($room['capacity'] ?? 2);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก — <?= htmlspecialchars($room['room_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Kanit:ital,wght@0,700;0,800;1,700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --ink:#0d1b2a;--gold:#c9a96e;--gold-dim:#f5ead8;--gold-dark:#a8864d;
  --bg:#f0f4f8;--card:#fff;--border:#e2e8f0;--muted:#64748b;
  --success:#15803d;--danger:#dc2626;--warning:#d97706;
  --navy:#0d1b2a;--navy2:#1e3a5c;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;}

/* ── page shell ── */
.pg-wrap{max-width:1100px;margin:0 auto;padding:28px 16px 60px;}
.back-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:var(--card);border:1.5px solid var(--border);border-radius:10px;color:var(--muted);font-size:.82rem;font-weight:700;margin-bottom:22px;transition:.2s;}
.back-btn:hover{border-color:var(--gold);color:var(--gold-dark);}

/* ── layout ── */
.layout{display:grid;grid-template-columns:340px 1fr;gap:24px;align-items:start;}

/* ── LEFT PANEL ── */
.side-panel{position:sticky;top:24px;display:flex;flex-direction:column;gap:16px;}

/* room card */
.room-card{background:var(--card);border-radius:20px;overflow:hidden;box-shadow:0 4px 20px rgba(13,27,42,.10);border:1px solid var(--border);}
.room-img{width:100%;height:180px;object-fit:cover;display:block;}
.room-img-ph{width:100%;height:180px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);display:flex;align-items:center;justify-content:center;font-size:3rem;}
.room-info{padding:18px 20px;}
.room-type-badge{display:inline-block;padding:3px 10px;border-radius:99px;font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;background:var(--gold-dim);color:var(--gold-dark);margin-bottom:8px;}
.room-name{font-family:'Kanit',sans-serif;font-size:1.05rem;font-weight:800;color:var(--ink);margin-bottom:10px;line-height:1.3;}
.room-meta{display:flex;flex-direction:column;gap:6px;}
.room-meta-row{display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--muted);}
.room-meta-row span:first-child{font-size:.9rem;}
.room-meta-row strong{color:var(--ink);}
.avail-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;font-size:.73rem;font-weight:700;margin-top:10px;}
.avail-pill.ok{background:#f0fdf4;color:var(--success);border:1px solid #bbf7d0;}
.avail-pill.full{background:#fef2f2;color:var(--danger);border:1px solid #fecaca;}

/* price summary */
.price-box{background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);border-radius:20px;padding:20px;color:#fff;box-shadow:0 4px 20px rgba(13,27,42,.18);}
.price-box-title{font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:14px;}
.price-rows{display:flex;flex-direction:column;gap:8px;margin-bottom:14px;}
.price-row{display:flex;justify-content:space-between;align-items:center;font-size:.82rem;}
.price-row .lbl{color:rgba(255,255,255,.7);}
.price-row .val{font-weight:700;color:#fff;}
.price-divider{border:none;border-top:1px solid rgba(255,255,255,.15);margin:8px 0;}
.price-total{display:flex;justify-content:space-between;align-items:center;}
.price-total .lbl{font-size:.85rem;font-weight:700;color:rgba(255,255,255,.85);}
.price-total .val{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:900;color:var(--gold);}
.price-note{font-size:.68rem;color:rgba(255,255,255,.45);margin-top:6px;text-align:right;}

/* ── RIGHT PANEL ── */
.form-panel{background:var(--card);border-radius:20px;box-shadow:0 4px 20px rgba(13,27,42,.08);border:1px solid var(--border);overflow:hidden;}

/* section header */
.sec-hd{padding:20px 28px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.sec-num{width:28px;height:28px;border-radius:50%;background:var(--navy);color:#fff;font-family:'Kanit',sans-serif;font-size:.78rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sec-title{font-family:'Kanit',sans-serif;font-size:.95rem;font-weight:800;color:var(--ink);}
.sec-sub{font-size:.75rem;color:var(--muted);margin-top:1px;}
.sec-body{padding:20px 28px 24px;}

/* unit grid */
.unit-legend{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px;}
.leg{display:flex;align-items:center;gap:5px;font-size:.73rem;color:var(--muted);}
.leg-dot{width:11px;height:11px;border-radius:3px;border:1.5px solid;}
.leg-dot.a{background:#f8fafc;border-color:var(--border);}
.leg-dot.s{background:var(--navy);border-color:var(--navy);}
.leg-dot.b{background:#f1f5f9;border-color:#cbd5e1;}

.unit-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;margin-bottom:12px;}
.uc{
  display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:14px 8px;border:2px solid var(--border);border-radius:14px;
  cursor:pointer;transition:all .2s;background:#f8fafc;user-select:none;position:relative;
  -webkit-tap-highlight-color:transparent;
}
.uc input{display:none;}
.uc:not(.ut):active{transform:scale(.95);}
.uc:not(.ut):hover{border-color:var(--gold);background:var(--gold-dim);transform:translateY(-2px);box-shadow:0 4px 12px rgba(201,169,110,.18);}
.uc.us{border-color:var(--navy);background:var(--navy);box-shadow:0 4px 14px rgba(13,27,42,.25);}
.uc.us .un{color:#fff;}
.uc.us .ust{color:rgba(255,255,255,.6);}
.uc.us::after{content:'✓';position:absolute;top:5px;right:7px;color:var(--gold);font-size:.78rem;font-weight:900;}
.uc.ut{background:#f1f5f9;border-color:#e2e8f0;cursor:not-allowed;opacity:.55;}
.ui{font-size:1.5rem;line-height:1;}
.un{font-size:.8rem;font-weight:800;color:var(--ink);text-align:center;}
.ust{font-size:.67rem;color:var(--muted);text-align:center;}

.unit-summary{display:none;padding:10px 14px;border-radius:10px;font-size:.82rem;font-weight:700;background:#f0fdf4;color:var(--success);border:1.5px solid #bbf7d0;margin-top:4px;}
.unit-summary.show{display:block;}

/* form fields */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.fg{display:flex;flex-direction:column;gap:6px;}
.fg.full{grid-column:1/-1;}
.fg label{font-size:.72rem;font-weight:700;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;display:flex;align-items:center;gap:4px;}
.fg label .req{color:var(--danger);}
.fi{position:relative;}
.fi-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:.9rem;pointer-events:none;opacity:.5;}
.fi input,.fi select,.fi textarea{
  width:100%;padding:10px 13px 10px 36px;
  font-family:'Sarabun',sans-serif;font-size:.9rem;color:var(--ink);
  background:#f8fafc;border:1.5px solid var(--border);border-radius:11px;
  outline:none;transition:.2s;
}
.fi input:focus,.fi select:focus,.fi textarea:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(201,169,110,.15);}
.fi textarea{padding-top:10px;min-height:80px;resize:vertical;}
.fi select{appearance:none;}
.fi-no-icon input,.fi-no-icon select,.fi-no-icon textarea{padding-left:13px;}

/* date row */
.date-row{display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:start;}
.date-sep{padding-top:34px;text-align:center;color:var(--muted);font-size:.8rem;}

/* nights badge */
.nights-badge{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;background:var(--gold-dim);border:1.5px solid var(--gold);border-radius:11px;font-size:.82rem;font-weight:700;color:var(--gold-dark);margin-top:4px;grid-column:1/-1;}

/* submit */
.submit-wrap{padding:20px 28px 28px;border-top:1px solid var(--border);}
.submit-btn{
  width:100%;padding:15px;border:none;border-radius:14px;
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);
  color:#fff;font-family:'Kanit',sans-serif;font-size:1rem;font-weight:800;
  cursor:pointer;transition:all .2s;letter-spacing:.03em;
  display:flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 4px 16px rgba(13,27,42,.2);
}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(13,27,42,.28);}
.submit-btn:active{transform:translateY(0);}
.submit-sub{text-align:center;font-size:.72rem;color:var(--muted);margin-top:10px;}

/* payment cards */
.pay-card {
  display:flex; align-items:center; gap:12px;
  padding:14px 16px; border:2px solid var(--border); border-radius:14px;
  cursor:pointer; transition:all .2s; background:#f8fafc; user-select:none;
}
.pay-card:hover { border-color:var(--gold); background:var(--gold-dim); }
.pay-card--active {
  border-color:var(--navy); background:var(--navy); color:#fff;
  box-shadow:0 4px 14px rgba(13,27,42,.2);
}
.pay-card--active .pay-card-sub { color:rgba(255,255,255,.6) !important; }
.pay-card--active div > div:last-child { color:rgba(255,255,255,.6) !important; }

@media(max-width:860px){
  .layout{grid-template-columns:1fr;}
  .side-panel{position:static;}
}
@media(max-width:560px){
  .sec-body{padding:16px 18px 20px;}
  .submit-wrap{padding:16px 18px 24px;}
  .form-grid{grid-template-columns:1fr;}
  .date-row{grid-template-columns:1fr;}.date-sep{display:none;}
  .unit-grid{grid-template-columns:repeat(auto-fill,minmax(80px,1fr));}
}
</style>
</head>
<body>
<div class="pg-wrap">

  <a href="/Projate/booking_room.php?checkin=<?= urlencode($checkin) ?>&checkout=<?= urlencode($checkout) ?>&guests=<?= urlencode($guests) ?>" class="back-btn">&#8592; กลับหน้าห้องพัก</a>

  <div class="layout">

    <!-- ════ LEFT: Room Info + Price ════ -->
    <div class="side-panel">

      <div class="room-card">
        <?php if ($roomImg): ?>
          <img src="<?= htmlspecialchars($roomImg) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>" class="room-img"
               onerror="this.style.display='none';document.getElementById('room-img-ph-<?= $room_id ?>').style.display='flex';">
          <div class="room-img-ph" id="room-img-ph-<?= $room_id ?>" style="display:none;">🏨</div>
        <?php else: ?>
          <div class="room-img-ph">🏨</div>
        <?php endif; ?>
        <div class="room-info">
          <div class="room-type-badge"><?= htmlspecialchars($room['room_type'] ?? 'STANDARD') ?></div>
          <div class="room-name"><?= htmlspecialchars($room['room_name']) ?></div>
          <div class="room-meta">
            <div class="room-meta-row"><span>💰</span><span>ราคา <strong>฿<?= number_format($pricePerNight) ?></strong> / คืน / ห้อง</span></div>
            <div class="room-meta-row"><span>👥</span><span>รองรับ <strong><?= $capacity ?> คน</strong> / ห้อง</span></div>
            <div class="room-meta-row"><span>🏠</span><span>ทั้งหมด <strong><?= $total_rooms ?> ห้อง</strong></span></div>
            <?php if (!empty($room['room_size'])): ?>
            <div class="room-meta-row"><span>📐</span><span>ขนาด <strong><?= htmlspecialchars($room['room_size']) ?></strong></span></div>
            <?php endif; ?>
            <?php if (!empty($room['bed_type'])): ?>
            <div class="room-meta-row"><span>🛏</span><span><?= htmlspecialchars($room['bed_type']) ?></span></div>
            <?php endif; ?>
          </div>
          <div class="avail-pill <?= $availCount > 0 ? 'ok' : 'full' ?>">
            <?= $availCount > 0 ? "✓ ว่าง {$availCount}/{$total_rooms} ห้อง" : "✗ เต็มทุกห้องแล้ว" ?>
          </div>
        </div>
      </div>

      <div class="price-box">
        <div class="price-box-title">สรุปยอดชำระ</div>
        <div class="price-rows">
          <div class="price-row"><span class="lbl">ราคาต่อห้อง/คืน</span><span class="val">฿<?= number_format($pricePerNight) ?></span></div>
          <div class="price-row"><span class="lbl">จำนวนห้อง</span><span class="val" id="sumRooms">—</span></div>
          <div class="price-row"><span class="lbl">จำนวนคืน</span><span class="val" id="sumNights">—</span></div>
        </div>
        <hr class="price-divider">
        <div class="price-total">
          <span class="lbl">ยอดรวมทั้งหมด</span>
          <span class="val" id="sumTotal">—</span>
        </div>
        <div class="price-note" id="sumBreakdown">&nbsp;</div>
      </div>

    </div>

    <!-- ════ RIGHT: Form ════ -->
    <div class="form-panel">
      <form action="save_booking.php" method="POST" id="bookingForm">
        <input type="hidden" name="room_id"    value="<?= (int)$room['id'] ?>">
        <input type="hidden" name="room_name"  value="<?= htmlspecialchars($room['room_name']) ?>">
        <input type="hidden" name="room_price" value="<?= htmlspecialchars($room['price']) ?>">

        <!-- ── Section 1: เลือกห้อง ── -->
        <div class="sec-hd">
          <div class="sec-num">1</div>
          <div>
            <div class="sec-title">เลือกห้องที่ต้องการจอง</div>
            <div class="sec-sub">เลือกได้มากกว่า 1 ห้อง · ว่างอยู่ <?= $availCount ?> จาก <?= $total_rooms ?> ห้อง</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="unit-legend">
            <div class="leg"><div class="leg-dot a"></div>ว่าง</div>
            <div class="leg"><div class="leg-dot s"></div>เลือกแล้ว</div>
            <div class="leg"><div class="leg-dot b"></div>จองแล้ว</div>
            <div class="leg"><div class="leg-dot" style="background:#f59e0b;"></div>ติดการจอง</div>
          </div>
          <div class="unit-grid">
            <?php for ($u = 1; $u <= $total_rooms; $u++):
              $uSt  = $takenUnits[$u] ?? 'available';
              $isTaken = in_array($uSt, ['approved','pending']);
              $isAv = !$isTaken;
              $cls  = 'uc' . ($isTaken ? ' ut' : '');
              $icon = $uSt === 'approved' ? '🔒' : ($uSt === 'pending' ? '⏳' : '🏠');
              $label = $uSt === 'approved' ? 'จองแล้ว' : ($uSt === 'pending' ? 'ติดการจอง' : 'ว่าง');
            ?>
            <label class="<?= $cls ?>" id="ul<?= $u ?>">
              <input type="checkbox" name="room_units[]" value="<?= $u ?>" <?= $isAv?'':'disabled' ?>>
              <span class="ui"><?= $icon ?></span>
              <span class="un">ห้อง <?= $u ?></span>
              <span class="ust"><?= $label ?></span>
            </label>
            <?php endfor; ?>
          </div>
          <div class="unit-summary" id="unitSummary"></div>
        </div>

        <!-- ── Section 2: วันที่พัก ── -->
        <div class="sec-hd" style="border-top:1px solid var(--border);">
          <div class="sec-num">2</div>
          <div>
            <div class="sec-title">กำหนดวันเข้าพัก</div>
            <div class="sec-sub">เช็คอิน 14:00 น. · เช็คเอาท์ 12:00 น.</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="date-row">
            <div class="fg">
              <label>เช็คอิน <span class="req">*</span></label>
              <div class="fi"><span class="fi-icon">📅</span>
                <input type="date" name="checkin_date" id="checkinDate" value="<?= htmlspecialchars($checkin) ?>" min="<?= date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="date-sep">→</div>
            <div class="fg">
              <label>เช็คเอาท์ <span class="req">*</span></label>
              <div class="fi"><span class="fi-icon">📅</span>
                <input type="date" name="checkout_date" id="checkoutDate" value="<?= htmlspecialchars($checkout) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
              </div>
            </div>
            <div class="nights-badge" id="nightsBadge">— คืน</div>
          </div>
        </div>

        <!-- ── Section 3: ข้อมูลผู้จอง ── -->
        <div class="sec-hd" style="border-top:1px solid var(--border);">
          <div class="sec-num">3</div>
          <div>
            <div class="sec-title">ข้อมูลผู้เข้าพัก</div>
            <div class="sec-sub">กรุณากรอกข้อมูลให้ถูกต้องเพื่อรับการยืนยัน</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="form-grid">
            <div class="fg">
              <label>ชื่อ-นามสกุล <span class="req">*</span></label>
              <div class="fi"><span class="fi-icon">👤</span>
                <input type="text" name="customer_name" placeholder="กรอกชื่อ-นามสกุล"
                       value="<?= htmlspecialchars($user_name) ?>" required>
              </div>
            </div>
            <div class="fg">
              <label>เบอร์โทรศัพท์ <span class="req">*</span></label>
              <div class="fi"><span class="fi-icon">📞</span>
                <input type="text" name="phone" placeholder="0XX-XXX-XXXX"
                       value="<?= htmlspecialchars($user_phone) ?>" required>
              </div>
            </div>
            <div class="fg">
              <label>อีเมล</label>
              <div class="fi"><span class="fi-icon">✉️</span>
                <input type="email" name="email" placeholder="example@email.com"
                       value="<?= htmlspecialchars($user_email) ?>">
              </div>
            </div>
            <div class="fg">
              <label>ผู้ใหญ่ <span class="req">*</span></label>
              <div class="fi"><span class="fi-icon">👥</span>
                <input type="number" name="adults" id="adultsInp" min="1" value="<?= (int)$guests ?>" required>
              </div>
            </div>
            <div class="fg">
              <label>เด็ก</label>
              <div class="fi"><span class="fi-icon">🧒</span>
                <input type="number" name="children" min="0" value="0">
              </div>
            </div>
            <div class="fg full">
              <label>วิธีชำระเงิน</label>
              <input type="hidden" name="payment_method" id="paymentMethodInput" value="โอนเงิน">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px;">
                <label class="pay-card pay-card--active" id="payCard_transfer" onclick="selectPay('โอนเงิน')">
                  <span style="font-size:1.4rem;">💳</span>
                  <div>
                    <div style="font-weight:800;font-size:.9rem;">QR Code</div>
                    <div style="font-size:.75rem;color:var(--muted);">โอนผ่านแอปธนาคาร</div>
                  </div>
                </label>
                <label class="pay-card" id="payCard_cash" onclick="selectPay('ชำระเงินสด')">
                  <span style="font-size:1.4rem;">💵</span>
                  <div>
                    <div style="font-weight:800;font-size:.9rem;">เงินสด</div>
                    <div style="font-size:.75rem;color:var(--muted);">ชำระ ณ ที่พัก</div>
                  </div>
                </label>
              </div>
            </div>
            <div class="fg full">
              <label>หมายเหตุ / ความต้องการพิเศษ</label>
              <div class="fi"><span class="fi-icon" style="top:14px;">📝</span>
                <textarea name="note" placeholder="เช่น ต้องการเตียงเสริม, แพ้อาหาร, เวลาเช็คอินพิเศษ..."></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Submit ── -->
        <div class="submit-wrap">
          <button type="submit" class="submit-btn" id="submitBtn">
            <span>🏨</span><span>ยืนยันการจองและไปชำระเงิน</span>
          </button>
          <div class="submit-sub" id="submitSub">ระบบจะนำท่านไปหน้าชำระเงิน QR Code ทันที</div>
        </div>

      </form>
    </div><!-- end form-panel -->

  </div><!-- end layout -->
</div>

<script>
function selectPay(method) {
  document.getElementById('paymentMethodInput').value = method;
  document.getElementById('payCard_transfer').classList.toggle('pay-card--active', method === 'โอนเงิน');
  document.getElementById('payCard_cash').classList.toggle('pay-card--active', method === 'ชำระเงินสด');
  const sub = document.getElementById('submitSub');
  if (sub) sub.textContent = method === 'โอนเงิน'
    ? 'ระบบจะนำท่านไปหน้าชำระเงิน QR Code ทันที'
    : 'ระบบจะรับการจอง และชำระเงินสด ณ ที่พัก';
}

(function(){
  const PRICE = <?= (float)$room['price'] ?>;
  const cards  = document.querySelectorAll('.uc:not(.ut)');
  const sumEl  = document.getElementById('unitSummary');
  const checkinEl  = document.getElementById('checkinDate');
  const checkoutEl = document.getElementById('checkoutDate');
  const nightsBadge= document.getElementById('nightsBadge');
  const sumRooms   = document.getElementById('sumRooms');
  const sumNights  = document.getElementById('sumNights');
  const sumTotal   = document.getElementById('sumTotal');
  const sumBreak   = document.getElementById('sumBreakdown');

  function getNights(){
    const ci=new Date(checkinEl.value), co=new Date(checkoutEl.value);
    if(isNaN(ci)||isNaN(co)||co<=ci) return 0;
    return Math.round((co-ci)/864e5);
  }
  function getSelCount(){
    return document.querySelectorAll('.uc input:checked').length;
  }
  function fmt(n){ return n.toLocaleString('th-TH',{minimumFractionDigits:0,maximumFractionDigits:0}); }

  function updateCalc(){
    const nights = getNights();
    const rooms  = getSelCount();
    nightsBadge.textContent = nights > 0 ? '🌙 '+nights+' คืน' : '— คืน';
    if(rooms>0&&nights>0){
      const total = PRICE*nights*rooms;
      sumRooms.textContent  = rooms+' ห้อง';
      sumNights.textContent = nights+' คืน';
      sumTotal.textContent  = '฿'+fmt(total);
      sumBreak.textContent  = '฿'+fmt(PRICE)+' × '+rooms+' ห้อง × '+nights+' คืน';
    } else {
      sumRooms.textContent  = rooms > 0 ? rooms+' ห้อง' : '—';
      sumNights.textContent = nights > 0 ? nights+' คืน' : '—';
      sumTotal.textContent  = '—';
      sumBreak.textContent  = '\u00a0';
    }
  }

  function syncUnits(){
    const selected = [...document.querySelectorAll('.uc input:checked')].map(c=>parseInt(c.value));
    if(selected.length>0){
      sumEl.textContent='✓ เลือกแล้ว: '+selected.map(n=>'ห้อง '+n).join(', ')+' ('+selected.length+' ห้อง)';
      sumEl.classList.add('show');
    } else {
      sumEl.textContent=''; sumEl.classList.remove('show');
    }
    updateCalc();
  }

  cards.forEach(card=>{
    card.addEventListener('click',()=>{
      const cb=card.querySelector('input');
      cb.checked=!cb.checked;
      card.classList.toggle('us',cb.checked);
      syncUnits();
    });
  });

  checkinEl.addEventListener('change',()=>{
    if(checkoutEl.value <= checkinEl.value){
      const d=new Date(checkinEl.value); d.setDate(d.getDate()+1);
      checkoutEl.value=d.toISOString().slice(0,10);
    }
    updateCalc();
  });
  checkoutEl.addEventListener('change', updateCalc);

  document.getElementById('bookingForm').addEventListener('submit',function(e){
    if(getSelCount()===0){ alert('กรุณาเลือกห้องอย่างน้อย 1 ห้อง'); e.preventDefault(); return; }
    if(getNights()===0){ alert('กรุณาเลือกวันเช็คอินและเช็คเอาท์ให้ถูกต้อง'); e.preventDefault(); return; }
    const btn=document.getElementById('submitBtn');
    btn.innerHTML='<span>⏳</span><span>กำลังดำเนินการ...</span>';
    btn.disabled=true;
  });

  updateCalc();
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
