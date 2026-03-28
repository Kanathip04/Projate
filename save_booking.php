<?php
date_default_timezone_set('Asia/Bangkok');

/* -----------------------------
   เปิด error ชั่วคราวไว้ debug
------------------------------ */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* -----------------------------
   เชื่อมต่อฐานข้อมูล
------------------------------ */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);
}

/* === เพิ่มคอลัมน์ room_units ถ้ายังไม่มี === */
$colCheck = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA='backoffice_db' AND TABLE_NAME='room_bookings' AND COLUMN_NAME='room_units'");
if ($colCheck && (int)$colCheck->fetch_assoc()['cnt'] === 0) {
    $conn->query("ALTER TABLE room_bookings ADD COLUMN room_units TEXT DEFAULT NULL");
}

/* -----------------------------
   รับเฉพาะ POST
------------------------------ */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: rooms.php");
    exit;
}

/* -----------------------------
   รับค่าจากฟอร์ม
------------------------------ */
$room_id       = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$room_name     = trim($_POST['room_name'] ?? '');   // จะเอาไปเก็บลง room_type
$customer_name = trim($_POST['customer_name'] ?? ''); // จะเอาไปเก็บลง full_name
$phone         = trim($_POST['phone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$checkin_date  = trim($_POST['checkin_date'] ?? '');
$checkout_date = trim($_POST['checkout_date'] ?? '');
$adults        = isset($_POST['adults']) ? (int)$_POST['adults'] : 1;
$children      = isset($_POST['children']) ? (int)$_POST['children'] : 0;
$note          = trim($_POST['note'] ?? '');
$raw_units     = $_POST['room_units'] ?? [];
if (!is_array($raw_units)) $raw_units = [];
$selected_units = array_values(array_filter(array_map('intval', $raw_units), fn($u) => $u > 0));
$room_units_json = !empty($selected_units) ? json_encode($selected_units) : null;

/* -----------------------------
   แปลงค่าให้ตรงกับโครงสร้างตารางจริง
------------------------------ */
$full_name = $customer_name;
$room_type = $room_name;
$guests    = $adults + $children;
$status    = 'pending'; // ตารางจริงใช้ enum: pending/approved/cancelled

/* -----------------------------
   ตรวจสอบข้อมูล
------------------------------ */
$errors = [];

if ($room_id <= 0) {
    $errors[] = "ไม่พบรหัสห้อง";
}
if ($room_type === '') {
    $errors[] = "ไม่พบชื่อห้อง";
}
if ($full_name === '') {
    $errors[] = "กรุณากรอกชื่อผู้จอง";
}
if ($phone === '') {
    $errors[] = "กรุณากรอกเบอร์โทร";
}
if ($checkin_date === '') {
    $errors[] = "กรุณาเลือกวันเช็คอิน";
}
if ($checkout_date === '') {
    $errors[] = "กรุณาเลือกวันเช็คเอาท์";
}
if ($adults < 1) {
    $errors[] = "จำนวนผู้ใหญ่ต้องไม่น้อยกว่า 1";
}
if (empty($selected_units)) {
    $errors[] = "กรุณาเลือกห้องอย่างน้อย 1 ห้อง";
}
if ($children < 0) {
    $errors[] = "จำนวนเด็กห้ามติดลบ";
}
if ($checkin_date !== '' && $checkout_date !== '') {
    if (strtotime($checkout_date) <= strtotime($checkin_date)) {
        $errors[] = "วันเช็คเอาท์ต้องมากกว่าวันเช็คอิน";
    }
}

if (!empty($errors)) {
    echo "<h2>เกิดข้อผิดพลาด</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
    echo '<a href="javascript:history.back()">← กลับไปแก้ไข</a>';
    exit;
}

/* -----------------------------
   เตรียม SQL
   ใช้คอลัมน์ตามตารางจริง
------------------------------ */
$sql = "INSERT INTO room_bookings
        (room_id, full_name, phone, email, room_type, guests, checkin_date, checkout_date, note, booking_status, room_units)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare SQL ไม่สำเร็จ: " . $conn->error);
}

/*
ชนิดข้อมูล
room_id       = i
full_name     = s
phone         = s
email         = s
room_type     = s
guests        = i
checkin_date  = s
checkout_date = s
note          = s
status        = s
*/
$bind = $stmt->bind_param(
    "issssisssss",
    $room_id,
    $full_name,
    $phone,
    $email,
    $room_type,
    $guests,
    $checkin_date,
    $checkout_date,
    $note,
    $status,
    $room_units_json
);

if (!$bind) {
    die("bind_param ไม่สำเร็จ: " . $stmt->error);
}

/* -----------------------------
   บันทึกข้อมูล
------------------------------ */
if ($stmt->execute()) {
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>จองห้องพักสำเร็จ</title>
        <style>
            *{box-sizing:border-box;}
            body{
                margin:0;
                font-family:'Segoe UI', Tahoma, sans-serif;
                background:#f4f7fb;
                display:flex;
                justify-content:center;
                align-items:center;
                min-height:100vh;
                padding:20px;
            }
            .box{
                background:#fff;
                max-width:600px;
                width:100%;
                border-radius:22px;
                box-shadow:0 12px 30px rgba(0,0,0,.08);
                padding:35px 25px;
                text-align:center;
            }
            h1{
                color:#1d3557;
                margin-bottom:12px;
            }
            p{
                color:#444;
                line-height:1.7;
                margin-bottom:10px;
            }
            .btn{
                display:inline-block;
                margin-top:16px;
                text-decoration:none;
                background:linear-gradient(135deg,#2a9d8f,#21867a);
                color:#fff;
                padding:12px 18px;
                border-radius:12px;
                font-weight:700;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>ส่งคำขอจองสำเร็จ</h1>
            <p>ระบบบันทึกข้อมูลการจองเรียบร้อยแล้ว</p>
            <p><strong>ห้อง:</strong> <?php echo htmlspecialchars($room_type); ?></p>
            <?php if (!empty($selected_units)): ?>
            <p><strong>ห้องที่จอง:</strong>
              <?php echo implode(', ', array_map(fn($u) => 'ห้องที่ '.$u, $selected_units)); ?>
              (<?= count($selected_units) ?> ห้อง)
            </p>
            <?php endif; ?>
            <p><strong>ผู้จอง:</strong> <?php echo htmlspecialchars($full_name); ?></p>
            <p><strong>จำนวนผู้เข้าพัก:</strong> <?php echo (int)$guests; ?> คน</p>
            <p><strong>สถานะ:</strong> รอการยืนยัน</p>
            <a href="/Projate/booking_room.php" class="btn">กลับไปหน้าห้องพัก</a>
        </div>
    </body>
    </html>
    <?php
} else {
    die("Execute ไม่สำเร็จ: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>