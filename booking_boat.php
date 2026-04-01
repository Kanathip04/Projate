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
    `payment_status` ENUM('pending','waiting_verify','checking','paid','failed','expired','duplicate','suspicious','manual_review') DEFAULT 'pending',
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
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `payment_status` ENUM('pending','waiting_verify','checking','paid','failed','expired','duplicate','suspicious','manual_review') DEFAULT 'pending' AFTER `booking_status`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `payment_slip` VARCHAR(255) DEFAULT NULL AFTER `payment_status`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `payment_provider` VARCHAR(50) DEFAULT NULL AFTER `payment_slip`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `provider_txn_id` VARCHAR(100) DEFAULT NULL AFTER `payment_provider`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `paid_at` DATETIME DEFAULT NULL AFTER `provider_txn_id`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `approved_at` DATETIME DEFAULT NULL AFTER `paid_at`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `webhook_payload` LONGTEXT DEFAULT NULL AFTER `approved_at`");
$conn->query("ALTER TABLE boat_bookings MODIFY COLUMN `payment_status` ENUM('pending','waiting_verify','checking','paid','failed','expired','duplicate','suspicious','manual_review','cash_pending','cash_paid') DEFAULT 'pending'");

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
    $boat_date      = trim($_POST['boat_date'] ?? '');
    $queue_name     = trim($_POST['queue_name'] ?? '');
    $boat_type      = trim($_POST['boat_type'] ?? '');
    $note           = trim($_POST['note'] ?? '');
    $pay_method     = in_array($_POST['pay_method'] ?? '', ['qr','cash']) ? $_POST['pay_method'] : 'qr';

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

    /* param count: s(booking_ref) i(queue_id) s(full_name) s(phone) s(email) s(queue_name) i(guests) s(boat_date) s(boat_type) d(price_per_boat) d(total_amount) i(daily_queue_no) s(note) = 13 params */
    $init_pay_status = ($pay_method === 'cash') ? 'cash_pending' : 'pending';
    $init_provider   = ($pay_method === 'cash') ? 'cash' : null;

    $stmt = $conn->prepare(
        "INSERT INTO boat_bookings
         (booking_ref, queue_id, full_name, phone, email, queue_name, guests, boat_date, boat_type, price_per_boat, total_amount, daily_queue_no, note, booking_status, payment_status, payment_provider)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)"
    );

    if (!$stmt) {
        echo json_encode(['ok' => false, 'errors' => ['SQL prepare error: ' . $conn->error]]);
        $conn->close(); exit;
    }

    // types: s i s s s s i s s d d i s s s (15 params)
    $stmt->bind_param("sissssissddisss",
        $temp_booking_ref,  // 1  s
        $queue_id,          // 2  i
        $customer_name,     // 3  s
        $phone,             // 4  s
        $email,             // 5  s
        $queue_name,        // 6  s
        $guests,            // 7  i
        $boat_date,         // 8  s
        $boat_type,         // 9  s
        $price_per_boat,    // 10 d
        $total_amount,      // 11 d
        $daily_queue_no,    // 12 i
        $note,              // 13 s
        $init_pay_status,   // 14 s
        $init_provider      // 15 s
    );

    if (!$stmt->execute()) {
        echo json_encode(['ok' => false, 'errors' => ['บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error]]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $booking_id = $stmt->insert_id;
    $stmt->close();

    $seqResBoat = $conn->query("SELECT COUNT(*) AS seq FROM boat_bookings WHERE DATE(created_at) = CURDATE() AND id <= $booking_id");
    $dailySeqBoat = (int)($seqResBoat ? $seqResBoat->fetch_assoc()['seq'] : 1);
    $booking_ref = 'BK' . date('Ymd') . '-' . str_pad($dailySeqBoat, 3, '0', STR_PAD_LEFT);

    $ustmt = $conn->prepare("UPDATE boat_bookings SET booking_ref = ? WHERE id = ?");
    $ustmt->bind_param("si", $booking_ref, $booking_id);
    $ustmt->execute();
    $ustmt->close();

    $redirect = ($pay_method === 'cash')
        ? 'cash_waiting.php?ref=' . urlencode($booking_ref)
        : 'payment_slip.php?ref=' . urlencode($booking_ref);

    echo json_encode([
        'ok'           => true,
        'booking_ref'  => $booking_ref,
        'total_amount' => $total_amount,
        'pay_method'   => $pay_method,
        'redirect'     => $redirect,
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
  --ink:#0d1b2a;--blue:#1565c0;--blue2:#1976d2;--blue-lt:#e3f2fd;
  --gold:#c9a96e;--gold-dark:#a8864d;
  --bg:#eef4fb;--card:#fff;--muted:#5f7281;--border:#dde6f0;
  --success:#15803d;--success-bg:#ecfdf3;
  --navy:#0d1b2a;--navy2:#1a3a5c;
  --radius:18px;
}
*,*::before,*::after{box-sizing:border-box;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;color:inherit;}

/* ── HERO ── */
.hero{
  background:linear-gradient(160deg,#071423 0%,#0d2344 45%,#1565c0 100%);
  color:#fff;padding:52px 20px 100px;position:relative;overflow:hidden;
}
.hero::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 10% 80%,rgba(21,101,192,.4) 0%,transparent 50%),
             radial-gradient(ellipse at 90% 10%,rgba(201,169,110,.1) 0%,transparent 45%);
}
.hero::after{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(to bottom,transparent 60%,var(--bg) 100%);
}
.hero-inner{max-width:900px;margin:0 auto;position:relative;z-index:2;}
.top-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px;}
.nav-btn{
  display:inline-flex;align-items:center;padding:8px 16px;border-radius:99px;
  font-size:.8rem;font-weight:600;color:#fff;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);
  transition:.2s;
}
.nav-btn:hover{background:rgba(255,255,255,.2);}
.hero h1{font-family:'Kanit',sans-serif;font-size:clamp(2.2rem,5vw,3.2rem);font-weight:900;margin-bottom:10px;line-height:1.1;}
.hero h1 span{color:#64b5f6;}
.hero-sub{font-size:.95rem;color:rgba(255,255,255,.7);max-width:520px;line-height:1.8;}

/* ── PAGE WRAP ── */
.page-wrap{max-width:900px;margin:-60px auto 80px;padding:0 16px;position:relative;z-index:5;}

/* ── QUEUE CARDS ── */
.qs-label{font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;}
.queue-selector{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
  gap:14px;margin-bottom:28px;
}
.queue-opt{
  border:2px solid var(--border);border-radius:var(--radius);
  background:var(--card);cursor:pointer;overflow:hidden;
  transition:all .25s;position:relative;
  box-shadow:0 2px 10px rgba(13,27,42,.06);
}
.queue-opt:hover{border-color:var(--blue2);transform:translateY(-3px);box-shadow:0 8px 24px rgba(21,101,192,.15);}
.queue-opt.active{
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(21,101,192,.15),0 8px 24px rgba(21,101,192,.15);
}
.queue-opt.active::after{
  content:'✓ เลือกแล้ว';position:absolute;top:12px;right:12px;
  background:var(--blue);color:#fff;font-size:.68rem;font-weight:800;
  padding:4px 12px;border-radius:99px;letter-spacing:.02em;
}
.qo-img{width:100%;height:155px;object-fit:cover;display:block;}
.qo-img-ph{
  width:100%;height:155px;
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 60%,var(--blue2) 100%);
  display:flex;align-items:center;justify-content:center;font-size:3rem;
}
.qo-body{padding:14px 16px 16px;}
.qo-name{font-size:.95rem;font-weight:800;color:var(--ink);margin-bottom:5px;}
.qo-date{font-size:.78rem;color:var(--blue);font-weight:700;}
.qo-price{
  display:inline-flex;align-items:center;margin-top:6px;
  font-size:.78rem;font-weight:700;
  padding:3px 10px;border-radius:99px;
  background:var(--blue-lt);color:var(--blue);
}
.qo-desc{font-size:.75rem;color:var(--muted);margin-top:6px;line-height:1.4;}

/* ── FORM CARD ── */
.form-card{
  background:var(--card);border-radius:var(--radius);
  box-shadow:0 8px 32px rgba(13,27,42,.1);overflow:hidden;
}
.form-header{
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 55%,var(--blue2) 100%);
  padding:26px 32px 24px;color:#fff;position:relative;overflow:hidden;
  min-height:90px;
}
.form-header::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 15% 60%,rgba(21,101,192,.45) 0%,transparent 55%);
}
.fh-inner{position:relative;z-index:1;}
.form-header h2{font-family:'Kanit',sans-serif;font-size:1.25rem;font-weight:800;margin-bottom:3px;}
.form-header p{font-size:.82rem;opacity:.75;line-height:1.5;}

/* ── FORM BODY ── */
.form-body{padding:26px 30px 28px;}
.profile-banner{
  background:var(--success-bg);border:1px solid #a7f3d0;border-radius:10px;
  padding:10px 16px;margin-bottom:20px;
  display:flex;align-items:center;gap:8px;font-size:.82rem;color:var(--success);font-weight:600;
}
.sec-label{
  font-size:.7rem;font-weight:800;color:var(--muted);text-transform:uppercase;
  letter-spacing:.07em;margin-bottom:10px;
  display:flex;align-items:center;gap:7px;
}
.sec-label::before{content:'';width:3px;height:13px;background:var(--blue);border-radius:2px;flex-shrink:0;}

/* Boat type selector */
.boat-type-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px;}
.bt-card{
  position:relative;cursor:pointer;
  border:2px solid var(--border);background:#f6f9fd;
  border-radius:99px;padding:9px 18px;
  display:flex;align-items:center;gap:8px;
  transition:all .2s;user-select:none;
}
.bt-card:hover{border-color:var(--blue2);background:var(--blue-lt);}
.bt-card input{display:none;}
.bt-card.active{background:var(--navy);border-color:var(--navy);color:#fff;}
.bt-card.active .bt-lbl{color:#fff;}
.bt-ico{font-size:1.1rem;}
.bt-lbl{font-size:.85rem;font-weight:700;color:var(--ink);}

/* Form fields */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group.full{grid-column:1/-1;}
.form-group label{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.badge-pre{
  font-size:.62rem;font-weight:700;color:var(--success);
  background:var(--success-bg);padding:2px 7px;border-radius:99px;
  margin-left:5px;text-transform:none;letter-spacing:0;border:1px solid #a7f3d0;
}
input[type=text],input[type=email],input[type=number],textarea,select{
  font-family:'Sarabun',sans-serif;font-size:.92rem;color:var(--ink);
  background:#f8fbff;border:1.5px solid var(--border);border-radius:10px;
  padding:10px 14px;outline:none;transition:border-color .2s,box-shadow .2s;
  width:100%;
}
input:focus,textarea:focus,select:focus{
  border-color:var(--blue);background:#fff;
  box-shadow:0 0 0 3px rgba(21,101,192,.1);
}
textarea{min-height:80px;resize:vertical;}

/* Submit */
.submit-btn{
  width:100%;padding:15px;border:none;border-radius:12px;
  background:linear-gradient(135deg,var(--navy) 0%,var(--blue2) 100%);
  color:#fff;font-family:'Kanit',sans-serif;font-size:1rem;font-weight:800;
  cursor:pointer;transition:all .25s;
  display:flex;align-items:center;justify-content:center;gap:8px;
  letter-spacing:.02em;
}
.submit-btn:hover{
  background:linear-gradient(135deg,var(--blue) 0%,#1e88e5 100%);
  transform:translateY(-2px);box-shadow:0 8px 24px rgba(21,101,192,.3);
}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Error */
.form-error{
  background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;
  padding:10px 14px;color:#dc2626;font-size:.82rem;font-weight:600;
  margin-bottom:14px;display:none;
}

/* ── 2-COLUMN LAYOUT ── */
.booking-layout{
  display:grid;
  grid-template-columns:300px 1fr;
  gap:20px;
  align-items:start;
}
/* Queue info sidebar */
.q-sidebar{
  background:var(--card);border-radius:var(--radius);
  box-shadow:0 4px 20px rgba(13,27,42,.09);
  overflow:hidden;border:1px solid var(--border);
  position:sticky;top:20px;
}
.q-sidebar-img{
  width:100%;height:180px;object-fit:cover;display:block;
}
.q-sidebar-img-ph{
  width:100%;height:180px;
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 55%,var(--blue2) 100%);
  display:flex;align-items:center;justify-content:center;font-size:3.5rem;
}
.q-sidebar-body{padding:18px;}
.q-sidebar-name{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:800;color:var(--ink);margin-bottom:10px;}
.q-info-row{display:flex;align-items:center;gap:8px;font-size:.82rem;margin-bottom:7px;color:var(--muted);}
.q-info-row .ico{width:20px;text-align:center;font-size:.9rem;}
.q-info-row .val{font-weight:600;color:var(--ink);}
.q-price-badge{
  display:inline-flex;align-items:center;gap:6px;margin-top:10px;
  background:linear-gradient(135deg,var(--navy),var(--blue2));
  color:#fff;border-radius:10px;padding:10px 14px;
  font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:800;
  width:100%;justify-content:center;
}
.q-type-chip{
  display:inline-flex;align-items:center;gap:6px;margin-top:8px;
  background:var(--blue-lt);color:var(--blue);
  border-radius:99px;padding:5px 12px;font-size:.78rem;font-weight:700;
  border:1px solid rgba(21,101,192,.2);
}

/* Queue mini selector (top) */
.queue-selector{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
  gap:10px;margin-bottom:20px;
}
.queue-opt{
  border:2px solid var(--border);border-radius:14px;
  background:var(--card);cursor:pointer;overflow:hidden;
  transition:all .2s;position:relative;
  box-shadow:0 2px 8px rgba(13,27,42,.05);
}
.queue-opt:hover{border-color:var(--blue2);transform:translateY(-2px);box-shadow:0 6px 18px rgba(21,101,192,.12);}
.queue-opt.active{border-color:var(--blue);box-shadow:0 0 0 3px rgba(21,101,192,.12);}
.queue-opt.active::after{
  content:'✓';position:absolute;top:8px;right:8px;
  background:var(--blue);color:#fff;font-size:.68rem;font-weight:800;
  width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;
}
.qo-img{width:100%;height:90px;object-fit:cover;display:block;}
.qo-img-ph{width:100%;height:90px;background:linear-gradient(135deg,var(--navy),var(--blue2));display:flex;align-items:center;justify-content:center;font-size:1.8rem;}
.qo-body{padding:10px 12px;}
.qo-name{font-size:.82rem;font-weight:800;color:var(--ink);}
.qo-date{font-size:.72rem;color:var(--blue);font-weight:600;margin-top:2px;}
.qo-price{font-size:.72rem;color:var(--muted);margin-top:2px;}
.qo-desc{font-size:.68rem;color:var(--muted);margin-top:4px;line-height:1.4;}

/* ── FORM CARD (no sidebar mode) ── */
.form-card{
  background:var(--card);border-radius:var(--radius);
  box-shadow:0 4px 20px rgba(13,27,42,.09);overflow:hidden;
  border:1px solid var(--border);
}

/* Empty */
.empty-box{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:60px 24px;text-align:center;color:var(--muted);
  box-shadow:0 2px 10px rgba(13,27,42,.06);
}
.empty-box h3{font-size:1.2rem;color:var(--ink);margin-bottom:8px;}

/* ── TICKET OVERLAY ── */
.ticket-overlay{
  display:none;position:fixed;inset:0;z-index:1000;
  background:rgba(7,20,35,.82);backdrop-filter:blur(8px);
  align-items:center;justify-content:center;padding:20px;overflow:auto;
}
.ticket-overlay.show{display:flex;}
.ticket{
  width:min(680px,100%);background:#fff;border-radius:24px;
  box-shadow:0 24px 64px rgba(0,0,0,.3);overflow:hidden;
  animation:tickIn .38s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes tickIn{from{opacity:0;transform:scale(.85) translateY(30px)}to{opacity:1;transform:none}}
.ticket-head{
  background:linear-gradient(135deg,#071423 0%,#0d2344 50%,#1565c0 100%);
  padding:32px 28px 40px;text-align:center;position:relative;overflow:hidden;
}
.ticket-head::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 30% 50%,rgba(21,101,192,.5) 0%,transparent 55%),
             radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.15) 0%,transparent 40%);
}
.ticket-org{font-size:.68rem;color:rgba(255,255,255,.45);letter-spacing:.18em;text-transform:uppercase;position:relative;z-index:1;margin-bottom:22px;}
.queue-no-label{font-size:.68rem;color:rgba(255,255,255,.45);letter-spacing:.2em;text-transform:uppercase;position:relative;z-index:1;margin-bottom:6px;}
.queue-no{font-family:'Kanit',sans-serif;font-size:86px;font-weight:900;line-height:1;color:#fff;letter-spacing:4px;text-shadow:0 4px 28px rgba(21,101,192,.6);position:relative;z-index:1;}
.queue-no span{color:#64b5f6;}
.ticket-notch{display:flex;align-items:center;}
.ticket-notch .circle{width:28px;height:28px;border-radius:50%;background:var(--bg);flex-shrink:0;}
.ticket-notch .line{flex:1;border-top:2px dashed var(--border);}
.ticket-body{padding:22px 26px 26px;}
.ticket-customer{text-align:center;margin-bottom:16px;}
.ticket-customer .lbl{font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.12em;margin-bottom:4px;}
.ticket-customer .name{font-size:1.35rem;font-weight:800;}
.ticket-rows{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;}
.ticket-row{display:flex;align-items:center;gap:12px;background:var(--bg);border-radius:10px;padding:10px 13px;border:1px solid var(--border);}
.tr-ico{font-size:.9rem;width:22px;text-align:center;flex-shrink:0;}
.tr-lbl{font-size:.62rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
.tr-val{font-size:.85rem;font-weight:700;margin-top:1px;}
.status-badge{display:flex;justify-content:center;margin-bottom:14px;}
.status-inner{display:inline-flex;align-items:center;gap:8px;padding:8px 18px;border-radius:99px;background:rgba(201,169,110,.1);border:1px solid rgba(201,169,110,.3);color:var(--gold-dark);font-size:.82rem;font-weight:700;}
.status-dot{width:6px;height:6px;border-radius:50%;background:var(--gold);animation:blink 1.4s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.15}}
.note-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 13px;font-size:.78rem;color:#92400e;line-height:1.6;margin-bottom:14px;}
.reset-note{text-align:center;font-size:.7rem;color:var(--muted);margin-bottom:16px;}
.ticket-btns{display:flex;gap:8px;}
.t-btn{flex:1;padding:12px;border-radius:12px;font-family:'Sarabun',sans-serif;font-size:.85rem;font-weight:700;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:.2s;}
.t-btn-print{background:linear-gradient(135deg,var(--gold),#e8c98a);color:var(--ink);}
.t-btn-again{background:var(--navy);color:#fff;}
.t-btn-again:hover{background:var(--blue);}
.booking-ref{text-align:center;font-size:.65rem;color:var(--muted);margin-top:8px;}
.payment-box{margin-top:18px;padding:16px;background:var(--bg);border-radius:14px;border:1px solid var(--border);}
.payment-grid{display:grid;grid-template-columns:200px 1fr;gap:18px;align-items:start;}
.qr-img{width:200px;max-width:100%;border:1px solid #ddd;border-radius:12px;padding:8px;background:#fff;}
.pay-title{font-size:1rem;font-weight:800;margin-bottom:10px;color:var(--navy);}
.pay-info{margin-bottom:7px;font-size:.85rem;}
.pay-helper{font-size:.8rem;color:var(--muted);margin-bottom:12px;}
.pay-upload-btn{padding:11px 18px;border:none;border-radius:10px;background:var(--navy);color:#fff;font-weight:700;font-family:'Sarabun',sans-serif;cursor:pointer;font-size:.85rem;}
.pay-upload-btn:hover{background:var(--blue);}
.slip-msg{margin-top:10px;font-size:.85rem;}

@media print{
  .ticket-overlay{position:relative;background:none;backdrop-filter:none;padding:0;display:flex;}
  .ticket{box-shadow:none;border-radius:0;border:1px solid #ddd;}
  .ticket-btns,.booking-ref,.no-print,.payment-box{display:none!important;}
  .ticket-head{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
@media(max-width:640px){
  .form-grid{grid-template-columns:1fr;}
  .form-body{padding:18px 16px;}
  .form-header{padding:20px 18px;}
  .queue-no{font-size:64px;}
  .payment-grid{grid-template-columns:1fr;}
  .page-wrap{margin-top:-40px;}
  .booking-layout{grid-template-columns:1fr;}
  .q-sidebar{position:static;}
}
</style>
</head>
<body>

<section class="hero">
  <div class="hero-inner">
    <div class="top-nav">
      <a href="index.php" class="nav-btn">← กลับหน้าหลัก</a>
      <a href="booking_status.php" class="nav-btn">ติดตามสถานะการจอง</a>
      <a href="booking_room.php" class="nav-btn">จองห้องพัก</a>
      <a href="booking_tent.php" class="nav-btn">จองเต็นท์</a>
    </div>
    <h1>จองคิว<span>พายเรือ</span></h1>
    <p class="hero-sub">เลือกรอบที่ต้องการ กรอกข้อมูล รับบัตรคิวทันที แล้วชำระเงินผ่าน QR Code พร้อมแนบสลิป</p>
  </div>
</section>

<div class="page-wrap">

<?php
$q0       = $queueList[0] ?? null;
$q0types  = $q0 ? array_values(array_filter(array_map('trim', explode(',', $q0['boat_types'] ?? 'เรือคายัค')))) : ['เรือคายัค'];
$typeIcons = ['เรือพาย'=>'🚣','เรือคายัค'=>'🛶','เรือบด'=>'⛵','เรือแคนู'=>'🛻','เรือยาง'=>'🔵'];
?>

<?php if (empty($queueList)): ?>
  <div class="empty-box">
    <div style="font-size:3rem;margin-bottom:12px;">🚣</div>
    <h3>ยังไม่มีคิวพายเรือที่เปิดจอง</h3>
    <p>กรุณาติดต่อเจ้าหน้าที่หรือรอเพิ่มคิวใหม่</p>
  </div>
<?php else: ?>

  <?php if (count($queueList) > 1): ?>
  <div class="qs-label" style="margin-bottom:10px;">เลือกรอบที่ต้องการ</div>
  <div class="queue-selector" id="queueSelector">
    <?php foreach ($queueList as $i => $q):
      $img = !empty($q['image_path']) ? $q['image_path'] : null;
    ?>
    <div class="queue-opt <?= $i===0?'active':'' ?>"
         data-id="<?= (int)$q['id'] ?>"
         data-name="<?= htmlspecialchars($q['queue_name']) ?>"
         data-date="<?= htmlspecialchars($q['queue_date']) ?>"
         data-price="<?= (float)$q['price_per_boat'] ?>"
         data-types="<?= htmlspecialchars($q['boat_types'] ?? 'เรือคายัค') ?>"
         data-img="<?= htmlspecialchars($q['image_path'] ?? '') ?>">
      <?php if ($img): ?>
        <img class="qo-img" src="<?= htmlspecialchars($img) ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="qo-img-ph" style="display:none;">🚣</div>
      <?php else: ?>
        <div class="qo-img-ph">🚣</div>
      <?php endif; ?>
      <div class="qo-body">
        <div class="qo-name"><?= htmlspecialchars($q['queue_name']) ?></div>
        <div class="qo-date">📅 <?= date('d/m/Y', strtotime($q['queue_date'])) ?></div>
        <div class="qo-price"><?= (float)$q['price_per_boat'] > 0 ? '฿'.number_format((float)$q['price_per_boat']).' / ลำ' : 'ฟรี' ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── 2-COLUMN LAYOUT ── -->
  <div class="booking-layout" id="bookingLayout">

    <!-- LEFT: Queue Info Sidebar -->
    <div class="q-sidebar" id="qSidebar">
      <?php $q0img = $q0['image_path'] ?? ''; ?>
      <?php if ($q0img): ?>
        <img class="q-sidebar-img" id="sideImg" src="<?= htmlspecialchars($q0img) ?>" alt="" onerror="this.style.display='none';document.getElementById('sideImgPh').style.display='flex';">
        <div class="q-sidebar-img-ph" id="sideImgPh" style="display:none;">🚣</div>
      <?php else: ?>
        <img class="q-sidebar-img" id="sideImg" src="" alt="" style="display:none;">
        <div class="q-sidebar-img-ph" id="sideImgPh">🚣</div>
      <?php endif; ?>
      <div class="q-sidebar-body">
        <div class="q-sidebar-name" id="sideName"><?= htmlspecialchars($q0['queue_name'] ?? '') ?></div>
        <div class="q-info-row">
          <span class="ico">📅</span>
          <span class="val" id="sideDate"><?= date('d/m/Y', strtotime($q0['queue_date'] ?? 'today')) ?></span>
        </div>
        <div class="q-info-row">
          <span class="ico">🛶</span>
          <span class="val" id="sideType"><?= htmlspecialchars($q0types[0] ?? 'เรือคายัค') ?></span>
        </div>
        <?php if (!empty($q0['description'])): ?>
        <div class="q-info-row" style="align-items:flex-start;">
          <span class="ico">📝</span>
          <span style="font-size:.78rem;color:var(--muted);line-height:1.5;"><?= htmlspecialchars($q0['description']) ?></span>
        </div>
        <?php endif; ?>
        <div class="q-price-badge" id="sidePriceBadge">
          <?php if ((float)($q0['price_per_boat'] ?? 0) > 0): ?>
            💰 ฿<?= number_format((float)$q0['price_per_boat']) ?> <span style="font-size:.75rem;font-weight:400;opacity:.8;">/ ลำ</span>
          <?php else: ?>
            ✓ ฟรี
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: Booking Form -->
    <div class="form-card">
      <div class="form-header">
        <div class="fh-inner">
          <h2>📋 กรอกข้อมูลการจอง</h2>
          <p id="formSubtitle"><?= htmlspecialchars($q0['queue_name'] ?? '') ?> · <?= date('d/m/Y', strtotime($q0['queue_date'] ?? 'today')) ?></p>
        </div>
      </div>
      <div class="form-body">

        <?php if ($isLoggedIn && $user_name): ?>
        <div class="profile-banner">✓ ดึงข้อมูลจากโปรไฟล์อัตโนมัติ · แก้ไขได้ก่อนยืนยัน</div>
        <?php endif; ?>

        <form id="bookingForm">
          <input type="hidden" id="f_queue_id"   value="<?= (int)($queueList[0]['id'] ?? 0) ?>">
          <input type="hidden" id="f_queue_name" value="<?= htmlspecialchars($queueList[0]['queue_name'] ?? '') ?>">
          <input type="hidden" id="f_boat_date"  value="<?= htmlspecialchars($queueList[0]['queue_date'] ?? '') ?>">
          <input type="hidden" id="f_boat_type_single" name="boat_type_single" value="<?= htmlspecialchars($q0types[0] ?? 'เรือคายัค') ?>">

          <!-- ประเภทเรือ: ถ้ามีแค่ 1 แสดงเป็น chip, ถ้ามีหลายแสดง selector -->
          <?php if (count($q0types) === 1): ?>
          <!-- auto-select เดียว -->
          <input type="hidden" name="boat_type" id="f_boat_type_hidden" value="<?= htmlspecialchars($q0types[0]) ?>">
          <div style="margin-bottom:20px;">
            <div class="sec-label">ประเภทเรือ</div>
            <div class="q-type-chip" id="boatTypeChip">
              <?= ($typeIcons[$q0types[0]] ?? '🚤') ?> <?= htmlspecialchars($q0types[0]) ?>
              <span style="margin-left:4px;font-size:.7rem;opacity:.7;">(เลือกอัตโนมัติ)</span>
            </div>
          </div>
          <?php else: ?>
          <div style="margin-bottom:20px;">
            <div class="sec-label">เลือกประเภทเรือ</div>
            <div class="boat-type-grid" id="boatTypeGrid">
              <?php foreach ($q0types as $i => $bt):
                $ico = $typeIcons[$bt] ?? '🚤';
              ?>
              <label class="bt-card <?= $i===0?'active':'' ?>">
                <input type="radio" name="boat_type" value="<?= htmlspecialchars($bt) ?>" <?= $i===0?'checked':'' ?> required>
                <span class="bt-ico"><?= $ico ?></span>
                <span class="bt-lbl"><?= htmlspecialchars($bt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

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

          <!-- วิธีชำระเงิน -->
          <div style="margin-bottom:20px;">
            <div class="sec-label">วิธีชำระเงิน</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;" id="payMethodGrid">
              <label id="pmQR" style="border:2px solid var(--blue);border-radius:12px;padding:14px 16px;cursor:pointer;background:var(--blue-lt);display:flex;align-items:center;gap:10px;transition:.2s;">
                <input type="radio" name="pay_method" value="qr" checked style="display:none;">
                <span style="font-size:1.4rem;">📱</span>
                <div>
                  <div style="font-size:.85rem;font-weight:800;color:var(--blue);">QR Code</div>
                  <div style="font-size:.7rem;color:var(--muted);">โอนผ่านแอปธนาคาร</div>
                </div>
              </label>
              <label id="pmCash" style="border:2px solid var(--border);border-radius:12px;padding:14px 16px;cursor:pointer;background:#f8fbff;display:flex;align-items:center;gap:10px;transition:.2s;">
                <input type="radio" name="pay_method" value="cash" style="display:none;">
                <span style="font-size:1.4rem;">💵</span>
                <div>
                  <div style="font-size:.85rem;font-weight:800;color:var(--ink);">เงินสด</div>
                  <div style="font-size:.7rem;color:var(--muted);">จ่ายกับเจ้าหน้าที่</div>
                </div>
              </label>
            </div>
          </div>

          <!-- ราคาสรุป -->
          <div id="priceSummary" style="background:linear-gradient(135deg,var(--navy) 0%,var(--blue2) 100%);border-radius:14px;padding:18px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 4px 16px rgba(21,101,192,.3);">
            <div>
              <div style="font-size:.72rem;font-weight:700;color:rgba(255,255,255,.65);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">ยอดที่ต้องชำระ</div>
              <div style="font-size:.8rem;color:rgba(255,255,255,.55);">ต่อ 1 ลำ</div>
            </div>
            <div style="text-align:right;">
              <div id="priceDisplay" style="font-family:'Kanit',sans-serif;font-size:2.2rem;font-weight:900;color:#fff;line-height:1;text-shadow:0 2px 8px rgba(0,0,0,.2);">
                <?= (float)($q0['price_per_boat'] ?? 0) > 0 ? '฿'.number_format((float)$q0['price_per_boat']) : 'ฟรี' ?>
              </div>
              <div style="font-size:.7rem;color:rgba(255,255,255,.55);margin-top:2px;">บาท / ลำ</div>
            </div>
          </div>

          <div class="form-error" id="formError"></div>

          <button type="submit" class="submit-btn" id="submitBtn">
            <span>🚣</span><span>ยืนยันการจองและรับบัตรคิว</span>
          </button>
        </form>
      </div>
    </div>

  </div><!-- end booking-layout -->

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
            <img id="qrPreview" src="uploads/QRcode.jpg" alt="QR Payment" class="qr-img" onerror="this.src='uploads/no-image.png'">
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
      const priceText = price > 0 ? '฿' + price.toLocaleString() : 'ฟรี';

      // อัปเดต form subtitle
      const dateObj = new Date(opt.dataset.date);
      const dateStr = dateObj.toLocaleDateString('th-TH');
      document.getElementById('formSubtitle').textContent =
        opt.dataset.name + '  ·  ' + dateStr + (price > 0 ? '  ·  ฿' + price.toLocaleString() + ' / ลำ' : '  ·  ฟรี');

      // อัปเดต sidebar
      const imgEl  = document.getElementById('sideImg');
      const imgPh  = document.getElementById('sideImgPh');
      const imgUrl = opt.dataset.img;
      if (imgUrl) {
        imgEl.src = imgUrl; imgEl.style.display = 'block'; imgPh.style.display = 'none';
      } else {
        imgEl.src = ''; imgEl.style.display = 'none'; imgPh.style.display = 'flex';
      }
      document.getElementById('sideName').textContent = opt.dataset.name;
      const dd = dateObj.getDate().toString().padStart(2,'0');
      const mm = (dateObj.getMonth()+1).toString().padStart(2,'0');
      const yy = dateObj.getFullYear();
      document.getElementById('sideDate').textContent = dd+'/'+mm+'/'+yy;

      // อัปเดตประเภทเรือและ sidebar type
      const types = opt.dataset.types.split(',').map(t => t.trim()).filter(Boolean);
      document.getElementById('sideType').textContent = types[0] || 'เรือคายัค';

      // อัปเดต sidebar price badge
      document.getElementById('sidePriceBadge').innerHTML =
        price > 0 ? '💰 ฿' + price.toLocaleString() + ' <span style="font-size:.75rem;font-weight:400;opacity:.8;">/ ลำ</span>' : '✓ ฟรี';

      // อัปเดต price summary box
      document.getElementById('priceDisplay').textContent = priceText;

      // อัปเดต boat type grid หรือ chip
      const grid = document.getElementById('boatTypeGrid');
      const chip = document.getElementById('boatTypeChip');
      const hiddenType = document.getElementById('f_boat_type_hidden');
      if (types.length === 1) {
        if (grid) { grid.parentElement.style.display = 'none'; }
        if (chip) { chip.parentElement.style.display = 'block'; chip.innerHTML = (typeIcons[types[0]]||'🚤') + ' ' + types[0] + '<span style="margin-left:4px;font-size:.7rem;opacity:.7;">(เลือกอัตโนมัติ)</span>'; }
        if (hiddenType) hiddenType.value = types[0];
      } else {
        if (chip) chip.parentElement.style.display = 'none';
        if (grid) {
          grid.parentElement.style.display = 'block';
          grid.innerHTML = '';
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
        }
      }
    });
  });

  // Payment method toggle
  document.querySelectorAll('input[name="pay_method"]').forEach(radio => {
    radio.addEventListener('change', () => {
      const pmQR   = document.getElementById('pmQR');
      const pmCash = document.getElementById('pmCash');
      if (radio.value === 'qr') {
        pmQR.style.border   = '2px solid var(--blue)';
        pmQR.style.background = 'var(--blue-lt)';
        pmCash.style.border  = '2px solid var(--border)';
        pmCash.style.background = '#f8fbff';
        document.querySelector('.submit-btn span:last-child').textContent = 'ยืนยันการจองและรับบัตรคิว';
      } else {
        pmCash.style.border  = '2px solid var(--blue)';
        pmCash.style.background = 'var(--blue-lt)';
        pmQR.style.border   = '2px solid var(--border)';
        pmQR.style.background = '#f8fbff';
        document.querySelector('.submit-btn span:last-child').textContent = 'ยืนยันการจอง (จ่ายสดกับเจ้าหน้าที่)';
      }
    });
  });

  document.getElementById('bookingForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const errEl = document.getElementById('formError');
    errEl.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<span style="animation:spin .7s linear infinite;display:inline-block">⟳</span><span>กำลังบันทึก...</span>';

    const boatType = (document.querySelector('input[name="boat_type"]:checked') || document.querySelector('input[name="boat_type"]'))?.value || '';
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
    const payMethodEl = document.querySelector('input[name="pay_method"]:checked');
    data.append('pay_method', payMethodEl ? payMethodEl.value : 'qr');

    try {
      const res = await fetch('booking_boat.php', { method: 'POST', body: data });
      const json = await res.json();

      if (json.ok) {
        // redirect ไปหน้าชำระเงิน
        window.location.href = json.redirect;
        return;
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