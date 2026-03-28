<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require_once 'auth_guard.php';

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);

/* === เพิ่มคอลัมน์ tent_units ถ้ายังไม่มี === */
$colCheck = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA='backoffice_db' AND TABLE_NAME='tent_bookings' AND COLUMN_NAME='tent_units'");
if ($colCheck && (int)$colCheck->fetch_assoc()['cnt'] === 0) {
    $conn->query("ALTER TABLE tent_bookings ADD COLUMN tent_units TEXT DEFAULT NULL");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: booking_tent.php"); exit;
}

/* ── รับค่าจากฟอร์ม ── */
$tent_id       = isset($_POST['tent_id']) ? (int)$_POST['tent_id'] : 0;
$tent_name     = trim($_POST['tent_name'] ?? '');
$customer_name = trim($_POST['customer_name'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$guests        = max(1, (int)($_POST['guests'] ?? 1));
$checkin_date  = trim($_POST['checkin_date'] ?? '');
$checkout_date = trim($_POST['checkout_date'] ?? '');
$note          = trim($_POST['note'] ?? '');
$raw_units     = $_POST['tent_units'] ?? [];
if (!is_array($raw_units)) $raw_units = [];
$selected_units   = array_values(array_filter(array_map('intval', $raw_units), fn($u) => $u > 0));
$tent_units_json  = !empty($selected_units) ? json_encode($selected_units) : null;

/* ── Validation ── */
$errors = [];
if ($tent_id <= 0)         $errors[] = "ไม่พบรหัสเต็นท์";
if ($tent_name === '')     $errors[] = "ไม่พบชื่อเต็นท์";
if ($customer_name === '') $errors[] = "กรุณากรอกชื่อผู้จอง";
if ($phone === '')         $errors[] = "กรุณากรอกเบอร์โทร";
if ($checkin_date === '')  $errors[] = "กรุณาเลือกวันเช็คอิน";
if ($checkout_date === '') $errors[] = "กรุณาเลือกวันเช็คเอาท์";
if (empty($selected_units)) $errors[] = "กรุณาเลือกหน่วยเต็นท์อย่างน้อย 1 หน่วย";
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
    "INSERT INTO tent_bookings (tent_id, full_name, phone, email, tent_type, guests, checkin_date, checkout_date, note, booking_status, tent_units)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt2->bind_param("issssisssss",
    $tent_id, $customer_name, $phone, $email, $tent_name,
    $guests, $checkin_date, $checkout_date, $note, $status, $tent_units_json
);

if ($stmt2->execute()):
    $booking_id = $stmt2->insert_id;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>จองเต็นท์สำเร็จ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
    --bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;
    --success:#15803d;--success-bg:#ecfdf3;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.page{max-width:560px;width:100%;}

/* ── top bar ── */
.top-bar{
    background:linear-gradient(135deg,#0d1f0d 0%,#1a2e1a 40%,#1a1a2e 100%);
    border-radius:22px 22px 0 0;padding:32px 32px 28px;text-align:center;
    position:relative;overflow:hidden;
}
.top-bar::before{content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse at 20% 50%,rgba(21,128,61,.3) 0%,transparent 60%),
               radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.15) 0%,transparent 50%);
    pointer-events:none;}
.checkmark{width:72px;height:72px;border-radius:50%;background:rgba(21,128,61,.25);border:2px solid rgba(21,128,61,.5);
    display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:32px;
    position:relative;z-index:1;animation:pop .4s cubic-bezier(.175,.885,.32,1.275) both;}
@keyframes pop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
.top-bar h1{font-size:26px;font-weight:800;color:#fff;margin-bottom:6px;position:relative;z-index:1;}
.top-bar p{font-size:14px;color:rgba(255,255,255,.72);position:relative;z-index:1;}
.booking-ref{display:inline-block;margin-top:14px;padding:6px 18px;border-radius:999px;
    background:rgba(201,169,110,.18);border:1px solid rgba(201,169,110,.4);
    color:var(--gold);font-size:13px;font-weight:700;position:relative;z-index:1;}

/* ── body card ── */
.card-body{background:var(--card);border:1px solid var(--border);border-top:none;
    border-radius:0 0 22px 22px;padding:28px 28px 32px;box-shadow:0 12px 30px rgba(26,26,46,.08);}

/* ── info rows ── */
.info-grid{display:flex;flex-direction:column;gap:12px;margin-bottom:24px;}
.info-row{display:flex;align-items:flex-start;gap:14px;padding:14px 16px;
    background:var(--bg);border:1px solid var(--border);border-radius:14px;}
.info-icon{width:36px;height:36px;border-radius:10px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:17px;background:rgba(26,26,46,.06);}
.info-label{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:3px;}
.info-value{font-size:15px;font-weight:700;color:var(--ink);}

/* ── unit pills ── */
.unit-pills{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;}
.unit-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:999px;
    background:rgba(21,128,61,.1);border:1px solid rgba(21,128,61,.3);color:var(--success);font-size:12px;font-weight:700;}

/* ── status badge ── */
.status-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 18px;border-radius:999px;
    background:rgba(201,169,110,.12);border:1px solid rgba(201,169,110,.35);color:var(--gold-dark);font-size:14px;font-weight:700;}
.status-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);animation:blink 1.4s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.divider{height:1px;background:var(--border);margin:20px 0;}
.note-box{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:12px 16px;
    font-size:13px;color:#92400e;line-height:1.6;margin-bottom:22px;}
.btn-group{display:flex;gap:10px;flex-wrap:wrap;}
.btn{flex:1;min-width:130px;display:inline-flex;align-items:center;justify-content:center;gap:7px;
    padding:14px 18px;border-radius:14px;font-family:'Sarabun',sans-serif;font-size:15px;font-weight:700;text-decoration:none;transition:all .2s;}
.btn-primary{background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);color:#fff;}
.btn-primary:hover{background:var(--gold);color:var(--ink);}
.btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.btn-ghost:hover{border-color:var(--gold);color:var(--gold-dark);}
@media(max-width:480px){.card-body{padding:20px 18px 26px;}.btn-group{flex-direction:column;}}
</style>
</head>
<body>
<div class="page">
    <div class="top-bar">
        <div class="checkmark">✓</div>
        <h1>ส่งคำขอจองสำเร็จ</h1>
        <p>ระบบบันทึกข้อมูลการจองเรียบร้อยแล้ว</p>
        <div class="booking-ref">หมายเลขการจอง #<?= str_pad($booking_id, 5, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="card-body">
        <div class="info-grid">
            <div class="info-row">
                <div class="info-icon">⛺</div>
                <div>
                    <div class="info-label">เต็นท์</div>
                    <div class="info-value"><?= htmlspecialchars($tent_name) ?></div>
                </div>
            </div>

            <?php if (!empty($selected_units)): ?>
            <div class="info-row">
                <div class="info-icon">🔑</div>
                <div>
                    <div class="info-label">หน่วยที่จอง (<?= count($selected_units) ?> หน่วย)</div>
                    <div class="unit-pills">
                        <?php foreach ($selected_units as $u): ?>
                            <span class="unit-pill">✓ หน่วยที่ <?= (int)$u ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-row">
                <div class="info-icon">👤</div>
                <div>
                    <div class="info-label">ชื่อผู้จอง</div>
                    <div class="info-value"><?= htmlspecialchars($customer_name) ?></div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon">📞</div>
                <div>
                    <div class="info-label">เบอร์โทร</div>
                    <div class="info-value"><?= htmlspecialchars($phone) ?></div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon">👥</div>
                <div>
                    <div class="info-label">จำนวนผู้เข้าพัก</div>
                    <div class="info-value"><?= (int)$guests ?> คน</div>
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
            <a href="/Projate/booking_tent.php" class="btn btn-primary">⛺ จองเต็นท์เพิ่ม</a>
            <a href="/Projate/booking_tent_status.php" class="btn btn-ghost">📋 ติดตามสถานะ</a>
        </div>
    </div>
</div>
</body>
</html>
<?php
else:
    echo "บันทึกข้อมูลไม่สำเร็จ: " . $stmt2->error;
endif;
$stmt2->close();
$conn->close();
?>
