<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require_once 'auth_guard.php';

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: booking_tent.php"); exit;
}

$tent_id       = isset($_POST['tent_id']) ? (int)$_POST['tent_id'] : 0;
$tent_name     = trim($_POST['tent_name'] ?? '');
$customer_name = trim($_POST['customer_name'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$guests        = max(1, (int)($_POST['guests'] ?? 1));
$checkin_date  = trim($_POST['checkin_date'] ?? '');
$checkout_date = trim($_POST['checkout_date'] ?? '');
$note          = trim($_POST['note'] ?? '');

$errors = [];
if ($tent_id <= 0)          $errors[] = "ไม่พบรหัสเต็นท์";
if ($tent_name === '')      $errors[] = "ไม่พบชื่อเต็นท์";
if ($customer_name === '')  $errors[] = "กรุณากรอกชื่อผู้จอง";
if ($phone === '')          $errors[] = "กรุณากรอกเบอร์โทร";
if ($checkin_date === '')   $errors[] = "กรุณาเลือกวันเช็คอิน";
if ($checkout_date === '')  $errors[] = "กรุณาเลือกวันเช็คเอาท์";
if ($checkin_date !== '' && $checkout_date !== '' && strtotime($checkout_date) <= strtotime($checkin_date)) {
    $errors[] = "วันเช็คเอาท์ต้องมากกว่าวันเช็คอิน";
}

if (!empty($errors)) {
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box;}body{font-family:"Sarabun",sans-serif;background:#f5f1eb;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;}
    .box{background:#fff;max-width:500px;width:100%;border-radius:20px;box-shadow:0 12px 30px rgba(0,0,0,.08);padding:36px 28px;}
    h2{color:#d92d20;margin-bottom:16px;}ul{padding-left:20px;color:#555;line-height:2;}
    .btn{display:inline-block;margin-top:20px;padding:12px 22px;border-radius:12px;background:#1a1a2e;color:#fff;font-weight:700;text-decoration:none;font-size:15px;}
    </style></head><body><div class="box"><h2>⚠ เกิดข้อผิดพลาด</h2><ul>';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>';
    echo '</ul><a href="javascript:history.back()" class="btn">← กลับแก้ไข</a></div></body></html>';
    exit;
}

$status = 'pending';
$stmt2 = $conn->prepare(
    "INSERT INTO tent_bookings (tent_id, full_name, phone, email, tent_type, guests, checkin_date, checkout_date, note, booking_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
/* types: i=tent_id, s=full_name, s=phone, s=email, s=tent_type, i=guests, s=checkin, s=checkout, s=note, s=status */
$stmt2->bind_param("issssissss",
    $tent_id, $customer_name, $phone, $email, $tent_name,
    $guests, $checkin_date, $checkout_date, $note, $status
);

if ($stmt2->execute()) {
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>จองเต็นท์สำเร็จ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;}
body{margin:0;font-family:'Sarabun',sans-serif;background:#f5f1eb;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;}
.box{background:#fff;max-width:560px;width:100%;border-radius:24px;box-shadow:0 16px 40px rgba(0,0,0,.10);overflow:hidden;}
.box-header{background:linear-gradient(135deg,#1a1a2e,#2d2d4e);padding:32px 28px;text-align:center;}
.box-header .icon{font-size:52px;margin-bottom:12px;}
.box-header h1{color:#fff;font-size:24px;font-weight:800;margin:0;}
.box-header p{color:rgba(255,255,255,.75);font-size:14px;margin-top:6px;}
.box-body{padding:28px;}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #e8e4de;}
.info-row:last-child{border:none;}
.info-label{font-size:13px;color:#7a7a8c;font-weight:600;}
.info-value{font-size:15px;color:#1a1a2e;font-weight:700;}
.status-badge{background:#dcfce7;color:#166534;padding:5px 14px;border-radius:999px;font-size:13px;font-weight:700;}
.actions{display:flex;gap:12px;margin-top:24px;}
.btn{flex:1;display:block;padding:14px;border-radius:14px;text-align:center;font-size:15px;font-weight:700;text-decoration:none;transition:.2s;}
.btn-primary{background:#1a1a2e;color:#fff;}
.btn-primary:hover{background:#2d2d4e;}
.btn-outline{background:#fff;color:#1a1a2e;border:2px solid #1a1a2e;}
.btn-outline:hover{background:#f5f1eb;}
</style>
</head>
<body>
<div class="box">
    <div class="box-header">
        <div class="icon">⛺</div>
        <h1>ส่งคำขอจองสำเร็จ!</h1>
        <p>ระบบได้รับข้อมูลการจองของคุณแล้ว</p>
    </div>
    <div class="box-body">
        <div class="info-row">
            <span class="info-label">เต็นท์</span>
            <span class="info-value"><?= htmlspecialchars($tent_name) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">ผู้จอง</span>
            <span class="info-value"><?= htmlspecialchars($customer_name) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">เบอร์โทร</span>
            <span class="info-value"><?= htmlspecialchars($phone) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">วันเข้าพัก</span>
            <span class="info-value"><?= date('d/m/Y', strtotime($checkin_date)) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">วันออก</span>
            <span class="info-value"><?= date('d/m/Y', strtotime($checkout_date)) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">จำนวนผู้เข้าพัก</span>
            <span class="info-value"><?= (int)$guests ?> คน</span>
        </div>
        <div class="info-row">
            <span class="info-label">สถานะ</span>
            <span class="status-badge">รอการอนุมัติ</span>
        </div>
        <div class="actions">
            <a href="booking_tent_status.php" class="btn btn-outline">ดูสถานะการจอง</a>
            <a href="booking_tent.php" class="btn btn-primary">กลับหน้าเต็นท์</a>
        </div>
    </div>
</div>
</body>
</html>
<?php
} else {
    echo "บันทึกข้อมูลไม่สำเร็จ: " . $stmt2->error;
}
$stmt2->close();
$conn->close();
?>
