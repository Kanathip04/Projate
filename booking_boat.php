<?php
session_start();
require_once 'auth_guard.php';
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

/* ── สร้างตารางถ้ายังไม่มี ── */
$conn->query("CREATE TABLE IF NOT EXISTS `boat_queues` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_name` VARCHAR(200) NOT NULL,
    `queue_date` DATE NOT NULL,
    `time_start` TIME NOT NULL DEFAULT '00:00:00',
    `time_end`   TIME NOT NULL DEFAULT '00:00:00',
    `total_boats` INT DEFAULT 5,
    `price_per_boat` DECIMAL(10,2) DEFAULT 0,
    `description` TEXT,
    `image_path` VARCHAR(500) DEFAULT '',
    `boat_types` VARCHAR(500) DEFAULT 'เรือพาย,เรือคายัค,เรือบด',
    `status` ENUM('show','hide') DEFAULT 'show',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `boat_bookings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id` INT UNSIGNED DEFAULT NULL,
    `booking_ref` VARCHAR(50) DEFAULT NULL,
    `full_name` VARCHAR(200) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `email` VARCHAR(200) DEFAULT '',
    `queue_name` VARCHAR(200) DEFAULT '',
    `guests` INT DEFAULT 1,
    `boat_date` DATE DEFAULT NULL,
    `boat_type` VARCHAR(100) DEFAULT '',
    `price_per_boat` DECIMAL(10,2) DEFAULT 0,
    `total_amount` DECIMAL(10,2) DEFAULT 0,
    `payment_status` ENUM('unpaid','pending','waiting_verify','paid','failed','expired') DEFAULT 'unpaid',
    `payment_slip` VARCHAR(255) DEFAULT NULL,
    `payment_provider` VARCHAR(50) DEFAULT NULL,
    `provider_txn_id` VARCHAR(100) DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `webhook_payload` LONGTEXT DEFAULT NULL,
    `daily_queue_no` INT UNSIGNED DEFAULT 0,
    `note` TEXT,
    `booking_status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    `archived` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `boat_type` VARCHAR(100) DEFAULT '' AFTER `queue_name`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `daily_queue_no` INT UNSIGNED DEFAULT 0 AFTER `boat_type`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `booking_ref` VARCHAR(50) DEFAULT NULL AFTER `id`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `price_per_boat` DECIMAL(10,2) DEFAULT 0 AFTER `boat_type`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `total_amount` DECIMAL(10,2) DEFAULT 0 AFTER `price_per_boat`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `payment_status` ENUM('unpaid','pending','waiting_verify','paid','failed','expired') DEFAULT 'unpaid' AFTER `booking_status`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `payment_slip` VARCHAR(255) DEFAULT NULL AFTER `payment_status`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `payment_provider` VARCHAR(50) DEFAULT NULL AFTER `payment_slip`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `provider_txn_id` VARCHAR(100) DEFAULT NULL AFTER `payment_provider`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `paid_at` DATETIME DEFAULT NULL AFTER `provider_txn_id`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `approved_at` DATETIME DEFAULT NULL AFTER `paid_at`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `webhook_payload` LONGTEXT DEFAULT NULL AFTER `approved_at`");

/* ── ดึงโปรไฟล์ ── */
$user_name  = '';
$user_email = '';
$user_phone = '';
$isLoggedIn = !empty($_SESSION['user_id']);
if ($isLoggedIn) {
    $uid   = (int)$_SESSION['user_id'];
    $uStmt = $conn->prepare("SELECT fullname, email, phone FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $uid);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($uRow) {
        $user_name  = $uRow['fullname'] ?? ($_SESSION['user_name']  ?? '');
        $user_email = $uRow['email']    ?? ($_SESSION['user_email'] ?? '');
        $user_phone = $uRow['phone']    ?? '';
    }
}

/* ── ดึงคิว ── */
$today   = date('Y-m-d');
$qResult = $conn->query("SELECT * FROM boat_queues WHERE status='show' AND queue_date >= '$today' ORDER BY queue_date ASC, id ASC");
$queueList = [];
while ($q = $qResult->fetch_assoc()) $queueList[] = $q;

/* ── Handle POST (AJAX) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $queue_id      = (int)($_POST['queue_id'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $guests        = max(1, (int)($_POST['guests'] ?? 1));
    $boat_date     = trim($_POST['boat_date'] ?? '');
    $queue_name    = trim($_POST['queue_name'] ?? '');
    $boat_type     = trim($_POST['boat_type'] ?? '');
    $note          = trim($_POST['note'] ?? '');

    $errors = [];
    if ($queue_id <= 0)        $errors[] = "ไม่พบรหัสคิว";
    if ($customer_name === '') $errors[] = "กรุณากรอกชื่อผู้จอง";
    if ($phone === '')         $errors[] = "กรุณากรอกเบอร์โทร";
    if ($boat_type === '')     $errors[] = "กรุณาเลือกประเภทเรือ";

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }

    $qstmt = $conn->prepare("SELECT id, queue_name, queue_date, price_per_boat FROM boat_queues WHERE id = ? LIMIT 1");
    $qstmt->bind_param("i", $queue_id);
    $qstmt->execute();
    $qres = $qstmt->get_result();
    $queueInfo = $qres->fetch_assoc();
    $qstmt->close();

    if (!$queueInfo) {
        echo json_encode(['ok' => false, 'errors' => ['ไม่พบข้อมูลคิวเรือ']]);
        exit;
    }

    $price_per_boat = (float)$queueInfo['price_per_boat'];
    $total_amount   = $price_per_boat;

    $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(created_at) = ?");
    $cntStmt->bind_param("s", $today);
    $cntStmt->execute();
    $cntRes = $cntStmt->get_result()->fetch_assoc();
    $cntStmt->close();
    $daily_queue_no = (int)($cntRes['cnt'] ?? 0) + 1;

    $temp_booking_ref = 'TMP' . time() . rand(100, 999);

    $stmt = $conn->prepare("
        INSERT INTO boat_bookings
        (
            booking_ref,
            queue_id,
            full_name,
            phone,
            email,
            queue_name,
            guests,
            boat_date,
            boat_type,
            price_per_boat,
            total_amount,
            daily_queue_no,
            note,
            booking_status,
            payment_status
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')
    ");

    $stmt->bind_param(
        "sissssissddis",
        $temp_booking_ref,
        $queue_id,
        $customer_name,
        $phone,
        $email,
        $queue_name,
        $guests,
        $boat_date,
        $boat_type,
        $price_per_boat,
        $total_amount,
        $daily_queue_no,
        $note
    );

    if (!$stmt->execute()) {
        echo json_encode(['ok' => false, 'errors' => ['บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error]]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $booking_id = $stmt->insert_id;
    $stmt->close();

    $booking_ref = 'BK' . date('Ymd') . str_pad($booking_id, 6, '0', STR_PAD_LEFT);

    $ustmt = $conn->prepare("UPDATE boat_bookings SET booking_ref = ? WHERE id = ?");
    $ustmt->bind_param("si", $booking_ref, $booking_id);
    $ustmt->execute();
    $ustmt->close();

    echo json_encode([
        'ok'            => true,
        'queue_no'      => str_pad($daily_queue_no, 4, '0', STR_PAD_LEFT),
        'booking_ref'   => $booking_ref,
        'customer_name' => $customer_name,
        'boat_type'     => $boat_type,
        'queue_name'    => $queue_name,
        'boat_date'     => date('d/m/Y', strtotime($boat_date)),
        'guests'        => $guests,
        'total_amount'  => number_format($total_amount, 2),
        'created_at'    => date('d/m/Y H:i'),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองคิวพายเรือ | สถาบันวิจัยวลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
  --bg:#f0f7ff;--card:#fff;--muted:#7a7a8c;--border:#dce8f5;
  --blue:#1d6fad;--blue-light:#e8f4ff;--blue-dark:#0d2344;
  --success:#15803d;--success-bg:#ecfdf3;
  --warning:#d97706;--warning-bg:#fffbeb;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;color:inherit;}
.hero{background:linear-gradient(145deg,#0a1628 0%,#0d2344 40%,#1a3a5c 100%);color:#fff;padding:60px 20px 80px;position:relative;overflow:hidden;}
.hero::before{content:'';position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(circle at 15% 80%,rgba(29,111,173,.35) 0%,transparent 45%),radial-gradient(circle at 85% 20%,rgba(201,169,110,.12) 0%,transparent 40%);}
.hero::after{content:'';position:absolute;inset:0;pointer-events:none;background:linear-gradient(to bottom,transparent 55%,var(--bg) 100%);}
.hero-inner{max-width:960px;margin:0 auto;position:relative;z-index:2;}
.top-nav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;}
.nav-btn{display:inline-flex;align-items:center;padding:9px 18px;border-radius:999px;font-size:13px;font-weight:600;color:#fff;background:rgba(29,111,173,.25);border:1px solid rgba(29,111,173,.5);backdrop-filter:blur(8px);transition:.2s;}
.nav-btn:hover{background:rgba(29,111,173,.45);color:#7ec8f4;}
.hero h1{font-family:'Kanit',sans-serif;font-size:clamp(2rem,5vw,3rem);font-weight:800;margin-bottom:10px;}
.hero h1 span{color:#7ec8f4;}
.hero p{font-size:1rem;color:rgba(255,255,255,.75);max-width:560px;line-height:1.75;}
.page-wrap{max-width:960px;margin:-50px auto 80px;padding:0 16px;position:relative;z-index:5;}
.queue-selector{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:28px;}
.queue-opt{border:2px solid var(--border);border-radius:16px;background:#fff;cursor:pointer;overflow:hidden;transition:all .2s;position:relative;}
.queue-opt:hover{border-color:var(--blue);transform:translateY(-2px);}
.queue-opt.active{border-color:var(--blue);box-shadow:0 0 0 3px rgba(29,111,173,.12);}
.queue-opt.active::after{content:'✓ เลือกแล้ว';position:absolute;top:10px;right:10px;background:var(--blue);color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;}
.qo-img{width:100%;height:130px;object-fit:cover;display:block;background:#c9dff5;}
.qo-body{padding:14px 16px;}
.qo-name{font-size:16px;font-weight:700;margin-bottom:4px;}
.qo-date{font-size:13px;color:var(--blue);font-weight:600;}
.qo-price{font-size:13px;color:var(--muted);margin-top:4px;}
.form-card{background:#fff;border-radius:20px;box-shadow:0 12px 40px rgba(29,111,173,.1);overflow:hidden;}
.form-header{background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 100%);padding:24px 32px;color:#fff;position:relative;overflow:hidden;}
.form-header::before{content:'';position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse at 20% 50%,rgba(29,111,173,.4) 0%,transparent 55%);}
.form-header h2{font-family:'Kanit',sans-serif;font-size:1.3rem;font-weight:700;position:relative;}
.form-header p{font-size:.85rem;opacity:.7;position:relative;margin-top:4px;}
.form-body{padding:28px 32px;}
.profile-banner{background:var(--success-bg);border:1px solid #d1fadf;border-radius:12px;padding:10px 16px;margin-bottom:22px;display:flex;align-items:center;gap:10px;font-size:13px;color:var(--success);font-weight:600;}
.sec-label{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.sec-label::before{content:'';width:3px;height:14px;background:var(--blue);border-radius:2px;flex-shrink:0;}
.boat-type-grid{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;}
.bt-card{position:relative;cursor:pointer;border:2px solid var(--border);background:#f8fbff;border-radius:12px;padding:12px 18px;display:flex;align-items:center;gap:10px;transition:all .2s;user-select:none;}
.bt-card:hover{border-color:var(--blue);background:var(--blue-light);}
.bt-card input{display:none;}
.bt-card.active{background:var(--ink);border-color:var(--ink);color:#fff;}
.bt-card.active .bt-lbl{color:#fff;}
.bt-card.active::after{content:'✓';position:absolute;top:5px;right:8px;font-size:11px;font-weight:800;color:#7ec8f4;}
.bt-ico{font-size:20px;}
.bt-lbl{font-size:14px;font-weight:700;color:var(--ink);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group.full{grid-column:1/-1;}
.form-group label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.badge-pre{font-size:.65rem;font-weight:700;color:var(--success);background:var(--success-bg);padding:2px 7px;border-radius:999px;margin-left:6px;text-transform:none;letter-spacing:0;border:1px solid #d1fadf;}
input,textarea,select{font-family:'Sarabun',sans-serif;font-size:.95rem;color:var(--ink);background:#f8fbff;border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;outline:none;transition:border-color .2s,box-shadow .2s;}
input:focus,textarea:focus,select:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(29,111,173,.12);}
textarea{min-height:90px;resize:vertical;}
.submit-btn{width:100%;padding:15px;border:none;border-radius:12px;background:linear-gradient(135deg,var(--ink),#1a3a5c);color:#fff;font-family:'Kanit',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.submit-btn:hover{background:linear-gradient(135deg,var(--blue),#1a5a9c);transform:translateY(-1px);}
.submit-btn:disabled{opacity:.65;cursor:not-allowed;transform:none;}
.empty-box{background:#fff;border:1px solid var(--border);border-radius:20px;padding:60px 24px;text-align:center;color:var(--muted);}
.empty-box h3{font-size:1.3rem;color:var(--ink);margin-bottom:8px;}
.ticket-overlay{display:none;position:fixed;inset:0;z-index:1000;background:rgba(10,22,40,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:20px;overflow:auto;}
.ticket-overlay.show{display:flex;}
.ticket{width:min(760px,100%);background:#fff;border-radius:24px;box-shadow:0 24px 64px rgba(0,0,0,.25);overflow:hidden;animation:ticketIn .35s cubic-bezier(.34,1.56,.64,1) both;}
@keyframes ticketIn{from{opacity:0;transform:scale(.88) translateY(24px)}to{opacity:1;transform:none}}
.ticket-head{background:linear-gradient(135deg,#0a1628 0%,#0d2344 50%,#1a3a5c 100%);padding:28px 28px 36px;text-align:center;position:relative;overflow:hidden;}
.ticket-head::before{content:'';position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse at 30% 50%,rgba(29,111,173,.4) 0%,transparent 55%),radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.15) 0%,transparent 40%);}
.ticket-org{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.15em;text-transform:uppercase;position:relative;z-index:1;margin-bottom:20px;}
.queue-no-label{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.2em;text-transform:uppercase;position:relative;z-index:1;margin-bottom:4px;}
.queue-no{font-family:'Kanit',sans-serif;font-size:80px;font-weight:900;line-height:1;color:#fff;letter-spacing:4px;text-shadow:0 4px 24px rgba(29,111,173,.5);position:relative;z-index:1;}
.queue-no span{color:#7ec8f4;}
.ticket-notch{display:flex;align-items:center;padding:0;}
.ticket-notch .circle{width:26px;height:26px;border-radius:50%;background:var(--bg);flex-shrink:0;}
.ticket-notch .line{flex:1;border-top:2px dashed var(--border);margin:0 6px;}
.ticket-body{padding:22px 26px 26px;}
.ticket-customer{text-align:center;margin-bottom:18px;}
.ticket-customer .lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.12em;margin-bottom:4px;}
.ticket-customer .name{font-size:22px;font-weight:800;}
.ticket-rows{display:flex;flex-direction:column;gap:8px;margin-bottom:18px;}
.ticket-row{display:flex;align-items:center;gap:12px;background:var(--bg);border-radius:10px;padding:10px 13px;border:1px solid var(--border);}
.tr-ico{font-size:15px;width:24px;text-align:center;flex-shrink:0;}
.tr-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
.tr-val{font-size:13px;font-weight:700;margin-top:1px;}
.status-badge{display:flex;justify-content:center;margin-bottom:16px;}
.status-inner{display:inline-flex;align-items:center;gap:8px;padding:8px 18px;border-radius:999px;background:rgba(201,169,110,.12);border:1px solid rgba(201,169,110,.35);color:var(--gold-dark);font-size:13px;font-weight:700;}
.status-dot{width:7px;height:7px;border-radius:50%;background:var(--gold);animation:blink 1.4s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
.note-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 13px;font-size:12px;color:#92400e;line-height:1.6;margin-bottom:16px;}
.reset-note{text-align:center;font-size:11px;color:var(--muted);margin-bottom:18px;}
.ticket-btns{display:flex;gap:8px;}
.t-btn{flex:1;padding:12px;border-radius:12px;font-family:'Sarabun',sans-serif;font-size:13px;font-weight:700;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:.2s;}
.t-btn-print{background:linear-gradient(135deg,var(--gold),#e8c98a);color:var(--ink);}
.t-btn-again{background:var(--ink);color:#fff;}
.t-btn-again:hover{background:var(--blue);}
.booking-ref{text-align:center;font-size:10px;color:var(--muted);margin-top:8px;}
.payment-box{margin-top:20px;padding:18px;background:#fff;border-radius:16px;border:1px solid #dce8f5;}
.payment-grid{display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start;}
.qr-img{width:220px;max-width:100%;border:1px solid #ddd;border-radius:12px;padding:8px;background:#fff;}
.pay-title{font-size:1.1rem;font-weight:800;margin-bottom:10px;color:var(--blue-dark);}
.pay-info{margin-bottom:8px;font-size:14px;}
.pay-helper{font-size:13px;color:#666;margin-bottom:12px;}
.pay-upload-btn{padding:12px 18px;border:none;border-radius:12px;background:#0d2344;color:#fff;font-weight:700;cursor:pointer;}
.pay-upload-btn:hover{background:#1d6fad;}
.slip-msg{margin-top:12px;font-size:14px;}
@media print{
  .ticket-overlay{position:relative;background:none;backdrop-filter:none;padding:0;display:flex;}
  .ticket{box-shadow:none;border-radius:0;border:1px solid #ddd;}
  .ticket-btns,.booking-ref,.no-print,.payment-box{display:none!important;}
  .ticket-head{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
@media(max-width:640px){
  .form-grid{grid-template-columns:1fr;}
  .form-body{padding:20px 18px;}
  .queue-no{font-size:60px;}
  .payment-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<section class="hero">
  <div class="hero-inner">
    <div class="top-nav">
      <a href="index.php" class="nav-btn">← กลับหน้าหลัก</a>
      <a href="booking_boat_status.php" class="nav-btn">ติดตามสถานะการจอง</a>
      <a href="booking_room.php" class="nav-btn">จองห้องพัก</a>
      <a href="booking_tent.php" class="nav-btn">จองเต็นท์</a>
    </div>
    <h1>จองคิว<span>พายเรือ</span></h1>
    <p>เลือกรอบที่ต้องการ กรอกข้อมูล รับบัตรคิวทันที แล้วชำระเงินผ่าน QR Code พร้อมแนบสลิป</p>
  </div>
</section>

<div class="page-wrap">

<?php if (empty($queueList)): ?>
  <div class="empty-box">
    <div style="font-size:3rem;margin-bottom:12px;">🚣</div>
    <h3>ยังไม่มีคิวพายเรือที่เปิดจอง</h3>
    <p>กรุณาติดต่อเจ้าหน้าที่หรือรอเพิ่มคิวใหม่</p>
  </div>
<?php else: ?>

  <?php if (count($queueList) > 1): ?>
  <div style="margin-bottom:8px;font-size:.8rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;">เลือกรอบที่ต้องการ</div>
  <div class="queue-selector" id="queueSelector">
    <?php foreach ($queueList as $i => $q): ?>
    <div class="queue-opt <?= $i===0?'active':'' ?>"
         data-id="<?= (int)$q['id'] ?>"
         data-name="<?= htmlspecialchars($q['queue_name']) ?>"
         data-date="<?= htmlspecialchars($q['queue_date']) ?>"
         data-price="<?= (float)$q['price_per_boat'] ?>"
         data-types="<?= htmlspecialchars($q['boat_types'] ?? 'เรือพาย,เรือคายัค,เรือบด') ?>">
      <?php $img = !empty($q['image_path']) ? $q['image_path'] : 'uploads/no-image.png'; ?>
      <img class="qo-img" src="<?= htmlspecialchars($img) ?>" onerror="this.src='uploads/no-image.png'" alt="">
      <div class="qo-body">
        <div class="qo-name"><?= htmlspecialchars($q['queue_name']) ?></div>
        <div class="qo-date">📅 <?= date('d/m/Y', strtotime($q['queue_date'])) ?></div>
        <div class="qo-price"><?= (float)$q['price_per_boat'] > 0 ? '฿'.number_format((float)$q['price_per_boat']).' / ลำ' : 'ฟรี' ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="form-card">
    <div class="form-header">
      <h2>🚣 กรอกข้อมูลการจอง</h2>
      <p id="formSubtitle">
        <?= htmlspecialchars($queueList[0]['queue_name']) ?> &nbsp;·&nbsp;
        <?= date('d/m/Y', strtotime($queueList[0]['queue_date'])) ?>
        <?php if ((float)$queueList[0]['price_per_boat'] > 0): ?>
          &nbsp;·&nbsp; ฿<?= number_format((float)$queueList[0]['price_per_boat']) ?> / ลำ
        <?php else: ?>
          &nbsp;·&nbsp; ฟรี
        <?php endif; ?>
      </p>
    </div>
    <div class="form-body">

      <?php if ($isLoggedIn && $user_name): ?>
      <div class="profile-banner">
        <span>✓</span> ดึงข้อมูลจากโปรไฟล์อัตโนมัติ &nbsp;·&nbsp; แก้ไขได้ก่อนยืนยัน
      </div>
      <?php endif; ?>

      <form id="bookingForm">
        <input type="hidden" id="f_queue_id" value="<?= (int)$queueList[0]['id'] ?>">
        <input type="hidden" id="f_queue_name" value="<?= htmlspecialchars($queueList[0]['queue_name']) ?>">
        <input type="hidden" id="f_boat_date" value="<?= htmlspecialchars($queueList[0]['queue_date']) ?>">

        <div class="sec-label">เลือกประเภทเรือ</div>
        <div class="boat-type-grid" id="boatTypeGrid">
          <?php
          $typeIcons = ['เรือพาย'=>'🚣','เรือคายัค'=>'🛶','เรือบด'=>'⛵','เรือแคนู'=>'🛻','เรือยาง'=>'🔵'];
          $firstTypes = array_filter(array_map('trim', explode(',', $queueList[0]['boat_types'] ?? 'เรือพาย,เรือคายัค,เรือบด')));
          foreach (array_values($firstTypes) as $i => $bt):
            $ico = $typeIcons[$bt] ?? '🚤';
          ?>
          <label class="bt-card <?= $i===0?'active':'' ?>">
            <input type="radio" name="boat_type" value="<?= htmlspecialchars($bt) ?>" <?= $i===0?'checked':'' ?> required>
            <span class="bt-ico"><?= $ico ?></span>
            <span class="bt-lbl"><?= htmlspecialchars($bt) ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="sec-label">ข้อมูลผู้จอง</div>
        <div class="form-grid">
          <div class="form-group">
            <label>ชื่อผู้จอง <?php if ($user_name): ?><span class="badge-pre">จากโปรไฟล์</span><?php endif; ?></label>
            <input type="text" id="f_name" placeholder="ชื่อ-นามสกุล" value="<?= htmlspecialchars($user_name) ?>" required>
          </div>
          <div class="form-group">
            <label>เบอร์โทร <?php if ($user_phone): ?><span class="badge-pre">จากโปรไฟล์</span><?php endif; ?></label>
            <input type="text" id="f_phone" placeholder="0XX-XXX-XXXX" value="<?= htmlspecialchars($user_phone) ?>" required>
          </div>
          <div class="form-group">
            <label>อีเมล <?php if ($user_email): ?><span class="badge-pre">จากโปรไฟล์</span><?php endif; ?></label>
            <input type="email" id="f_email" placeholder="example@email.com" value="<?= htmlspecialchars($user_email) ?>">
          </div>
          <div class="form-group">
            <label>จำนวนผู้เข้าร่วม (คน)</label>
            <input type="number" id="f_guests" min="1" value="1" required>
          </div>
          <div class="form-group full">
            <label>หมายเหตุ (ถ้ามี)</label>
            <textarea id="f_note" placeholder="ข้อมูลเพิ่มเติม หรือความต้องการพิเศษ..."></textarea>
          </div>
        </div>

        <div id="formError" style="display:none;background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:10px 14px;color:#dc2626;font-size:13px;font-weight:600;margin-bottom:14px;"></div>

        <button type="submit" class="submit-btn" id="submitBtn">
          <span>🚣</span><span>ยืนยันการจองและรับบัตรคิว</span>
        </button>
      </form>
    </div>
  </div>

<?php endif; ?>
</div>

<div class="ticket-overlay" id="ticketOverlay">
  <div class="ticket" id="ticketEl">
    <div class="ticket-head">
      <div class="ticket-org">🚣 สถาบันวิจัยวลัยรุกขเวช &nbsp;·&nbsp; ระบบจองคิวพายเรือ</div>
      <div class="queue-no-label">หมายเลขคิวของคุณ</div>
      <div class="queue-no" id="tQueueNo"><span>Q</span>0001</div>
    </div>
    <div class="ticket-notch">
      <div class="circle"></div>
      <div class="line"></div>
      <div class="circle"></div>
    </div>
    <div class="ticket-body">
      <div class="ticket-customer">
        <div class="lbl">ชื่อผู้จอง</div>
        <div class="name" id="tName">—</div>
      </div>
      <div class="ticket-rows">
        <div class="ticket-row">
          <span class="tr-ico">🚣</span>
          <div>
            <div class="tr-lbl">คิว / ประเภทเรือ</div>
            <div class="tr-val" id="tQueue">—</div>
          </div>
        </div>
        <div class="ticket-row">
          <span class="tr-ico">📅</span>
          <div>
            <div class="tr-lbl">วันที่</div>
            <div class="tr-val" id="tDate">—</div>
          </div>
        </div>
        <div class="ticket-row">
          <span class="tr-ico">👥</span>
          <div>
            <div class="tr-lbl">จำนวนผู้เข้าร่วม</div>
            <div class="tr-val" id="tGuests">—</div>
          </div>
        </div>
      </div>

      <div class="status-badge">
        <div class="status-inner">
          <span class="status-dot"></span> จองสำเร็จแล้ว รอชำระเงินและแนบสลิป
        </div>
      </div>

      <div class="payment-box" id="paymentBox" style="display:none;">
        <div class="pay-title">ชำระเงินผ่าน QR Code</div>
        <div class="payment-grid">
          <div style="text-align:center;">
            <img id="qrPreview" src="uploads/qr_payment.jpg" alt="QR Payment" class="qr-img" onerror="this.src='uploads/no-image.png'">
            <div style="font-size:12px;color:#666;margin-top:8px;">สแกนเพื่อชำระเงิน</div>
          </div>
          <div>
            <div class="pay-info">เลขอ้างอิงการจอง: <strong id="payBookingRef"></strong></div>
            <div class="pay-info">ยอดที่ต้องชำระ: <strong>฿<span id="payAmount"></span></strong></div>
            <div class="pay-helper">หลังจากโอนเงินแล้ว กรุณาแนบสลิปด้านล่าง</div>

            <form id="slipForm" enctype="multipart/form-data">
              <input type="hidden" name="booking_ref" id="slip_booking_ref">
              <input type="file" name="payment_slip" id="payment_slip" accept=".jpg,.jpeg,.png,.webp" required style="display:block;margin-bottom:12px;">
              <button type="submit" class="pay-upload-btn">แนบสลิปการโอนเงิน</button>
            </form>

            <div class="slip-msg" id="slipMsg"></div>
          </div>
        </div>
      </div>

      <div class="note-box">
        ⚠ กรุณาแสดงบัตรคิวนี้ต่อเจ้าหน้าที่ในวันที่มาใช้บริการ
      </div>
      <div class="reset-note">🔄 ระบบคิวรีเซ็ตทุกวันเที่ยงคืน (00:00 น.)</div>
      <div class="ticket-btns no-print">
        <button class="t-btn t-btn-print" onclick="window.print()">🖨 พิมพ์บัตรคิว</button>
        <button class="t-btn t-btn-again" onclick="bookAgain()">🚣 จองอีกครั้ง</button>
      </div>
      <div class="booking-ref no-print" id="tRef"></div>
    </div>
  </div>
</div>

<script>
(function(){
  document.querySelectorAll('.bt-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.bt-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      card.querySelector('input[type=radio]').checked = true;
    });
  });

  const typeIcons = {'เรือพาย':'🚣','เรือคายัค':'🛶','เรือบด':'⛵','เรือแคนู':'🛻','เรือยาง':'🔵'};
  document.querySelectorAll('.queue-opt').forEach(opt => {
    opt.addEventListener('click', () => {
      document.querySelectorAll('.queue-opt').forEach(o => o.classList.remove('active'));
      opt.classList.add('active');
      document.getElementById('f_queue_id').value   = opt.dataset.id;
      document.getElementById('f_queue_name').value = opt.dataset.name;
      document.getElementById('f_boat_date').value  = opt.dataset.date;
      const price = parseFloat(opt.dataset.price);
      document.getElementById('formSubtitle').textContent =
        opt.dataset.name + '  ·  ' +
        new Date(opt.dataset.date).toLocaleDateString('th-TH') +
        (price > 0 ? '  ·  ฿' + price.toLocaleString() + ' / ลำ' : '  ·  ฟรี');

      const grid = document.getElementById('boatTypeGrid');
      grid.innerHTML = '';
      const types = opt.dataset.types.split(',').map(t => t.trim()).filter(Boolean);
      types.forEach((bt, i) => {
        const ico = typeIcons[bt] || '🚤';
        const lbl = document.createElement('label');
        lbl.className = 'bt-card' + (i === 0 ? ' active' : '');
        lbl.innerHTML = `<input type="radio" name="boat_type" value="${bt}" ${i===0?'checked':''}><span class="bt-ico">${ico}</span><span class="bt-lbl">${bt}</span>`;
        lbl.addEventListener('click', () => {
          grid.querySelectorAll('.bt-card').forEach(c => c.classList.remove('active'));
          lbl.classList.add('active');
          lbl.querySelector('input').checked = true;
        });
        grid.appendChild(lbl);
      });
    });
  });

  document.getElementById('bookingForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const errEl = document.getElementById('formError');
    errEl.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<span style="animation:spin .7s linear infinite;display:inline-block">⟳</span><span>กำลังบันทึก...</span>';

    const boatType = document.querySelector('input[name="boat_type"]:checked')?.value || '';
    const data = new FormData();
    data.append('ajax', '1');
    data.append('queue_id', document.getElementById('f_queue_id').value);
    data.append('queue_name', document.getElementById('f_queue_name').value);
    data.append('boat_date', document.getElementById('f_boat_date').value);
    data.append('customer_name', document.getElementById('f_name').value.trim());
    data.append('phone', document.getElementById('f_phone').value.trim());
    data.append('email', document.getElementById('f_email').value.trim());
    data.append('guests', document.getElementById('f_guests').value);
    data.append('boat_type', boatType);
    data.append('note', document.getElementById('f_note').value.trim());

    try {
      const res = await fetch('booking_boat.php', { method: 'POST', body: data });
      const json = await res.json();

      if (json.ok) {
        showTicket(json);
      } else {
        errEl.textContent = (json.errors || ['เกิดข้อผิดพลาด']).join(' / ');
        errEl.style.display = 'block';
      }
    } catch (err) {
      errEl.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
      errEl.style.display = 'block';
    }

    btn.disabled = false;
    btn.innerHTML = '<span>🚣</span><span>ยืนยันการจองและรับบัตรคิว</span>';
  });

  function showTicket(d) {
    document.getElementById('tQueueNo').innerHTML = '<span>Q</span>' + d.queue_no;
    document.getElementById('tName').textContent   = d.customer_name;
    document.getElementById('tQueue').textContent  = d.queue_name + ' · ' + d.boat_type;
    document.getElementById('tDate').textContent   = d.boat_date;
    document.getElementById('tGuests').textContent = d.guests + ' คน';
    document.getElementById('tRef').textContent    = 'หมายเลขการจอง #' + d.booking_ref + ' · ' + d.created_at + ' น.';
    document.getElementById('ticketOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';

    document.getElementById('paymentBox').style.display = 'block';
    document.getElementById('payBookingRef').textContent = d.booking_ref;
    document.getElementById('payAmount').textContent = d.total_amount || '0.00';
    document.getElementById('slip_booking_ref').value = d.booking_ref;
    document.getElementById('slipMsg').innerHTML = '';
    document.getElementById('slipForm').reset();
    document.getElementById('slip_booking_ref').value = d.booking_ref;
  }

  document.getElementById('slipForm').addEventListener('submit', async function(e){
    e.preventDefault();

    const msg = document.getElementById('slipMsg');
    msg.innerHTML = 'กำลังอัปโหลดสลิป...';

    const formData = new FormData(this);

    try {
      const res = await fetch('payment_upload.php', {
        method: 'POST',
        body: formData
      });

      const json = await res.json();

      if (json.ok) {
        msg.innerHTML = '<span style="color:green;">แนบสลิปสำเร็จ ระบบบันทึกแล้ว และส่งไปรอตรวจสอบ</span>';
      } else {
        msg.innerHTML = '<span style="color:red;">' + (json.message || 'อัปโหลดไม่สำเร็จ') + '</span>';
      }
    } catch (err) {
      msg.innerHTML = '<span style="color:red;">เกิดข้อผิดพลาดในการอัปโหลดสลิป</span>';
    }
  });

  window.bookAgain = function() {
    document.getElementById('ticketOverlay').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('f_name').value   = '';
    document.getElementById('f_phone').value  = '';
    document.getElementById('f_email').value  = '';
    document.getElementById('f_guests').value = '1';
    document.getElementById('f_note').value   = '';
    document.getElementById('paymentBox').style.display = 'none';
    document.getElementById('bookingForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
})();
</script>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>
</body>
</html>
<?php $conn->close(); ?>