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

/* === เพิ่มคอลัมน์สำหรับระบบชำระเงินใน room_bookings (safe: ไม่ error ถ้ามีอยู่แล้ว) === */
foreach ([
    "payment_status ENUM('unpaid','waiting_verify','paid','failed','manual_review') DEFAULT 'unpaid'",
    "payment_slip VARCHAR(500) DEFAULT NULL",
    "paid_at DATETIME DEFAULT NULL",
    "approved_at DATETIME DEFAULT NULL",
    "total_price DECIMAL(10,2) DEFAULT NULL",
    "room_price DECIMAL(10,2) DEFAULT NULL",
    "booking_ref VARCHAR(50) DEFAULT NULL",
] as $sbColDef) {
    $sbColName = strtok($sbColDef, ' ');
    $sbChk = $conn->query("SHOW COLUMNS FROM room_bookings LIKE '$sbColName'");
    if ($sbChk && $sbChk->num_rows === 0) {
        $conn->query("ALTER TABLE room_bookings ADD COLUMN $sbColDef");
    }
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
    $booking_id = $stmt->insert_id;

    /* === อัปเดต booking_ref, room_price, total_price === */
    $booking_ref_val = 'ROOM-' . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
    $room_price_val  = (float)($_POST['room_price'] ?? 0);

    /* คำนวณจำนวนคืน */
    $sb_nights = 1;
    if (!empty($checkin_date) && !empty($checkout_date)) {
        $sb_d1 = new DateTime($checkin_date);
        $sb_d2 = new DateTime($checkout_date);
        $sb_nights = max(1, (int)$sb_d1->diff($sb_d2)->days);
    }

    /* ถ้าไม่ได้ส่ง room_price มาใน POST ให้ดึงจาก rooms */
    if ($room_price_val <= 0 && $room_id > 0) {
        $rpSt = $conn->prepare("SELECT price FROM rooms WHERE id = ? LIMIT 1");
        $rpSt->bind_param("i", $room_id);
        $rpSt->execute();
        $rpRow = $rpSt->get_result()->fetch_assoc();
        $rpSt->close();
        $room_price_val = (float)($rpRow['price'] ?? 0);
    }

    $total_price_val = $room_price_val * $sb_nights * max(1, count($selected_units));

    $upStmt = $conn->prepare(
        "UPDATE room_bookings SET booking_ref = ?, room_price = ?, total_price = ? WHERE id = ?"
    );
    $upStmt->bind_param("sddi", $booking_ref_val, $room_price_val, $total_price_val, $booking_id);
    $upStmt->execute();
    $upStmt->close();

    $stmt->close();
    $conn->close();
    header("Location: room_bill.php?id=$booking_id");
    exit;

    // ── รหัสล่างนี้ไม่ถูก execute แล้ว (เก็บไว้เพื่อ reference) ──
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>จองห้องพักสำเร็จ</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        :root{
            --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
            --bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;
            --border:#e8e4de;--success:#15803d;--success-bg:#ecfdf3;
        }
        body{
            font-family:'Sarabun','Segoe UI',Tahoma,sans-serif;
            background:var(--bg);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .page{max-width:560px;width:100%;}

        /* ── top bar ── */
        .top-bar{
            background:linear-gradient(135deg,#0d1f0d 0%,#1a2e1a 50%,#1a1a2e 100%);
            border-radius:22px 22px 0 0;
            padding:32px 32px 28px;
            text-align:center;
            position:relative;
            overflow:hidden;
        }
        .top-bar::before{
            content:'';position:absolute;inset:0;
            background:radial-gradient(ellipse at 20% 50%,rgba(21,128,61,.3) 0%,transparent 60%),
                        radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.15) 0%,transparent 50%);
            pointer-events:none;
        }
        .checkmark{
            width:72px;height:72px;border-radius:50%;
            background:rgba(21,128,61,.25);
            border:2px solid rgba(21,128,61,.5);
            display:flex;align-items:center;justify-content:center;
            margin:0 auto 18px;
            font-size:32px;
            position:relative;z-index:1;
            animation:pop .4s cubic-bezier(.175,.885,.32,1.275) both;
        }
        @keyframes pop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
        .top-bar h1{
            font-size:26px;font-weight:800;color:#fff;
            margin-bottom:6px;position:relative;z-index:1;
        }
        .top-bar p{
            font-size:14px;color:rgba(255,255,255,.72);
            position:relative;z-index:1;
        }
        .booking-ref{
            display:inline-block;margin-top:14px;
            padding:6px 18px;border-radius:999px;
            background:rgba(201,169,110,.18);
            border:1px solid rgba(201,169,110,.4);
            color:var(--gold);font-size:13px;font-weight:700;
            position:relative;z-index:1;
        }

        /* ── body card ── */
        .card-body{
            background:var(--card);
            border:1px solid var(--border);
            border-top:none;
            border-radius:0 0 22px 22px;
            padding:28px 28px 32px;
            box-shadow:0 12px 30px rgba(26,26,46,.08);
        }

        /* ── info rows ── */
        .info-grid{display:flex;flex-direction:column;gap:12px;margin-bottom:24px;}
        .info-row{
            display:flex;align-items:flex-start;gap:14px;
            padding:14px 16px;
            background:var(--bg);
            border:1px solid var(--border);
            border-radius:14px;
        }
        .info-icon{
            width:36px;height:36px;border-radius:10px;flex-shrink:0;
            display:flex;align-items:center;justify-content:center;
            font-size:17px;
            background:rgba(26,26,46,.06);
        }
        .info-label{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:3px;}
        .info-value{font-size:15px;font-weight:700;color:var(--ink);}

        /* ── unit pills ── */
        .unit-pills{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;}
        .unit-pill{
            display:inline-flex;align-items:center;gap:5px;
            padding:4px 12px;border-radius:999px;
            background:rgba(21,128,61,.1);
            border:1px solid rgba(21,128,61,.3);
            color:var(--success);font-size:12px;font-weight:700;
        }

        /* ── status badge ── */
        .status-badge{
            display:inline-flex;align-items:center;gap:8px;
            padding:8px 18px;border-radius:999px;
            background:rgba(201,169,110,.12);
            border:1px solid rgba(201,169,110,.35);
            color:var(--gold-dark);font-size:14px;font-weight:700;
        }
        .status-dot{
            width:8px;height:8px;border-radius:50%;
            background:var(--gold);
            animation:blink 1.4s ease-in-out infinite;
        }
        @keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

        /* ── divider ── */
        .divider{height:1px;background:var(--border);margin:20px 0;}

        /* ── footer note ── */
        .note-box{
            background:#fffbeb;border:1px solid #fde68a;
            border-radius:12px;padding:12px 16px;
            font-size:13px;color:#92400e;line-height:1.6;
            margin-bottom:22px;
        }

        /* ── buttons ── */
        .btn-group{display:flex;gap:10px;flex-wrap:wrap;}
        .btn{
            flex:1;min-width:130px;
            display:inline-flex;align-items:center;justify-content:center;gap:7px;
            padding:14px 18px;border-radius:14px;
            font-family:'Sarabun',sans-serif;font-size:15px;font-weight:700;
            text-decoration:none;transition:all .2s;
        }
        .btn-primary{
            background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);
            color:#fff;
        }
        .btn-primary:hover{background:var(--gold);color:var(--ink);}
        .btn-ghost{
            background:transparent;color:var(--muted);
            border:1.5px solid var(--border);
        }
        .btn-ghost:hover{border-color:var(--gold);color:var(--gold-dark);}

        @media(max-width:480px){
            .top-bar{padding:26px 20px 22px;}
            .card-body{padding:20px 18px 26px;}
            .btn-group{flex-direction:column;}
        }
        </style>
    </head>
    <body>
    <div class="page">
        <!-- top hero -->
        <div class="top-bar">
            <div class="checkmark">✓</div>
            <h1>ส่งคำขอจองสำเร็จ</h1>
            <p>ระบบบันทึกข้อมูลการจองเรียบร้อยแล้ว</p>
            <div class="booking-ref">หมายเลขการจอง #<?= str_pad($booking_id, 5, '0', STR_PAD_LEFT) ?></div>
        </div>

        <!-- detail card -->
        <div class="card-body">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-icon">🏨</div>
                    <div>
                        <div class="info-label">ประเภทห้องพัก</div>
                        <div class="info-value"><?= htmlspecialchars($room_type) ?></div>
                    </div>
                </div>

                <?php if (!empty($selected_units)): ?>
                <div class="info-row">
                    <div class="info-icon">🔑</div>
                    <div>
                        <div class="info-label">ห้องที่จอง (<?= count($selected_units) ?> ห้อง)</div>
                        <div class="unit-pills">
                            <?php foreach ($selected_units as $u): ?>
                                <span class="unit-pill">✓ ห้องที่ <?= (int)$u ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <div class="info-icon">👤</div>
                    <div>
                        <div class="info-label">ชื่อผู้จอง</div>
                        <div class="info-value"><?= htmlspecialchars($full_name) ?></div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">👥</div>
                    <div>
                        <div class="info-label">จำนวนผู้เข้าพัก</div>
                        <div class="info-value"><?= (int)$guests ?> คน (ผู้ใหญ่ <?= (int)$adults ?> <?= $children > 0 ? '· เด็ก '.(int)$children : '' ?>)</div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">📅</div>
                    <div>
                        <div class="info-label">วันเช็คอิน → เช็คเอาท์</div>
                        <div class="info-value"><?= htmlspecialchars($checkin_date) ?> → <?= htmlspecialchars($checkout_date) ?></div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">📋</div>
                    <div>
                        <div class="info-label">สถานะ</div>
                        <div style="margin-top:6px;">
                            <span class="status-badge">
                                <span class="status-dot"></span>
                                รอการยืนยันจากเจ้าหน้าที่
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="note-box">
                ⚠ กรุณารอการยืนยันจากเจ้าหน้าที่ การจองจะสมบูรณ์เมื่อได้รับการอนุมัติแล้ว สามารถติดตามสถานะได้ที่เมนู "ติดตามสถานะการจอง"
            </div>

            <div class="btn-group">
                <a href="/Projate/booking_room.php" class="btn btn-primary">🏨 จองห้องเพิ่ม</a>
                <a href="/Projate/booking_status.php" class="btn btn-ghost">📋 ติดตามสถานะ</a>
            </div>
        </div>
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