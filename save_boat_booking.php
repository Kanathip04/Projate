<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require_once 'auth_guard.php';

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: booking_boat.php"); exit; }

/* ── เพิ่มคอลัมน์ใหม่ถ้ายังไม่มี ── */
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `boat_type` VARCHAR(100) DEFAULT '' AFTER `boat_units`");
$conn->query("ALTER TABLE boat_bookings ADD COLUMN IF NOT EXISTS `daily_queue_no` INT UNSIGNED DEFAULT 0 AFTER `boat_type`");

/* ── รับค่าจากฟอร์ม ── */
$queue_id      = isset($_POST['queue_id'])    ? (int)$_POST['queue_id'] : 0;
$queue_name    = trim($_POST['queue_name']    ?? '');
$customer_name = trim($_POST['customer_name'] ?? '');
$phone         = trim($_POST['phone']         ?? '');
$email         = trim($_POST['email']         ?? '');
$guests        = max(1, (int)($_POST['guests'] ?? 1));
$boat_date     = trim($_POST['boat_date']     ?? '');
$time_start    = trim($_POST['time_start']    ?? '');
$time_end      = trim($_POST['time_end']      ?? '');
$note          = trim($_POST['note']          ?? '');
$boat_type     = trim($_POST['boat_type']     ?? '');
$raw_units     = $_POST['boat_units'] ?? [];
if (!is_array($raw_units)) $raw_units = [];
$selected_units  = array_values(array_filter(array_map('intval', $raw_units), fn($u) => $u > 0));
$boat_units_json = !empty($selected_units) ? json_encode($selected_units) : null;

/* ── Validate ── */
$errors = [];
if ($queue_id <= 0)         $errors[] = "ไม่พบรหัสคิว";
if ($customer_name === '')  $errors[] = "กรุณากรอกชื่อผู้จอง";
if ($phone === '')          $errors[] = "กรุณากรอกเบอร์โทร";
if ($boat_date === '')      $errors[] = "ไม่พบวันที่คิว";
if (empty($selected_units)) $errors[] = "กรุณาเลือกเรืออย่างน้อย 1 ลำ";
if ($boat_type === '')      $errors[] = "กรุณาเลือกประเภทเรือ";

if (!empty($errors)) {
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box;}body{font-family:"Sarabun",sans-serif;background:#f0f7ff;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;}
    .box{background:#fff;max-width:500px;width:100%;border-radius:20px;box-shadow:0 12px 30px rgba(0,0,0,.08);padding:36px 28px;}
    h2{color:#d92d20;margin-bottom:16px;}ul{padding-left:20px;color:#555;line-height:2;}
    .btn{display:inline-block;margin-top:20px;padding:12px 22px;border-radius:12px;background:#1a1a2e;color:#fff;font-weight:700;text-decoration:none;font-size:15px;}
    </style></head><body><div class="box"><h2>⚠ เกิดข้อผิดพลาด</h2><ul>';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>';
    echo '</ul><a href="javascript:history.back()" class="btn">← กลับแก้ไข</a></div></body></html>';
    exit;
}

/* ── คำนวณเลขคิวรายวัน (นับตั้งแต่เที่ยงคืน รีเซ็ตทุกวัน) ── */
$today = date('Y-m-d');
$cntRes = $conn->query(
    "SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(created_at) = '{$today}'"
);
$dailyCount   = (int)($cntRes->fetch_assoc()['cnt'] ?? 0);
$daily_queue_no = $dailyCount + 1;

/* ── บันทึกลง DB ── */
$status = 'pending';
$stmt = $conn->prepare(
    "INSERT INTO boat_bookings
     (queue_id, full_name, phone, email, queue_name, guests,
      boat_date, time_start, time_end, boat_units, boat_type, daily_queue_no, note, booking_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("issssisssssiss",
    $queue_id, $customer_name, $phone, $email, $queue_name,
    $guests, $boat_date, $time_start, $time_end,
    $boat_units_json, $boat_type, $daily_queue_no, $note, $status
);

if (!$stmt->execute()) {
    echo "บันทึกข้อมูลไม่สำเร็จ: " . $stmt->error;
    $stmt->close(); $conn->close(); exit;
}
$booking_id = $stmt->insert_id;
$stmt->close();
$conn->close();

/* ── format ── */
$queueLabel  = 'Q' . str_pad($daily_queue_no, 4, '0', STR_PAD_LEFT);
$bookingRef  = '#' . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
$dateDisplay = date('d/m/Y', strtotime($boat_date));
$timeDisplay = substr($time_start,0,5) . ' – ' . substr($time_end,0,5) . ' น.';
$boatDisplay = implode(', ', array_map(fn($u) => 'ลำที่ '.$u, $selected_units));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ใบคิวพายเรือ <?= $queueLabel ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --ink:#1a1a2e;--blue:#1d6fad;--blue-dark:#0d2344;
    --gold:#c9a96e;--gold-dark:#a8864d;
    --bg:#f0f7ff;--card:#fff;--muted:#7a7a8c;--border:#dce8f5;
    --success:#15803d;--success-bg:#ecfdf3;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;gap:20px;}

/* ── Ticket ── */
.ticket{
    width:min(480px,100%);
    background:#fff;
    border-radius:24px;
    box-shadow:0 20px 60px rgba(29,111,173,.15),0 4px 16px rgba(29,111,173,.08);
    overflow:hidden;
    position:relative;
}

/* Ticket header */
.ticket-header{
    background:linear-gradient(135deg,#0a1628 0%,#0d2344 50%,#1a3a5c 100%);
    padding:28px 28px 36px;
    position:relative;overflow:hidden;text-align:center;
}
.ticket-header::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse at 30% 50%,rgba(29,111,173,.4) 0%,transparent 55%),
               radial-gradient(ellipse at 80% 20%,rgba(201,169,110,.15) 0%,transparent 40%);
    pointer-events:none;
}
.ticket-logo{font-size:36px;margin-bottom:8px;position:relative;z-index:1;}
.ticket-org{font-size:12px;color:rgba(255,255,255,.55);letter-spacing:.15em;text-transform:uppercase;position:relative;z-index:1;margin-bottom:20px;}

/* Queue number — big */
.queue-number-wrap{position:relative;z-index:1;margin-bottom:6px;}
.queue-label-text{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.2em;text-transform:uppercase;margin-bottom:4px;}
.queue-number{
    font-family:'Kanit',sans-serif;
    font-size:88px;font-weight:900;line-height:1;
    color:#fff;
    text-shadow:0 4px 24px rgba(29,111,173,.5);
    letter-spacing:4px;
}
.queue-number span{color:#7ec8f4;}

/* Notch (ticket tear effect) */
.ticket-notch{
    display:flex;align-items:center;margin:0;
    position:relative;
}
.ticket-notch::before,.ticket-notch::after{
    content:'';width:28px;height:28px;border-radius:50%;
    background:var(--bg);flex-shrink:0;
}
.ticket-notch-line{
    flex:1;border-top:2px dashed #dce8f5;margin:0 4px;
}

/* Ticket body */
.ticket-body{padding:24px 28px 28px;}
.customer-name{
    text-align:center;margin-bottom:20px;
}
.customer-name .label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.15em;margin-bottom:4px;}
.customer-name .name{font-size:24px;font-weight:800;color:var(--ink);}

.ticket-rows{display:flex;flex-direction:column;gap:10px;margin-bottom:20px;}
.ticket-row{
    display:flex;align-items:center;gap:12px;
    padding:11px 14px;background:var(--bg);
    border-radius:12px;border:1px solid var(--border);
}
.ticket-row-ico{font-size:16px;width:28px;text-align:center;flex-shrink:0;}
.ticket-row-label{font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
.ticket-row-value{font-size:14px;font-weight:700;color:var(--ink);margin-top:1px;}

/* Boat pills */
.boat-pills{display:flex;flex-wrap:wrap;gap:5px;margin-top:4px;}
.boat-pill{
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 10px;border-radius:999px;
    background:rgba(29,111,173,.1);border:1px solid rgba(29,111,173,.25);
    color:var(--blue);font-size:12px;font-weight:700;
}

/* Status badge */
.status-wrap{display:flex;justify-content:center;margin-bottom:20px;}
.status-badge{
    display:inline-flex;align-items:center;gap:8px;
    padding:9px 20px;border-radius:999px;
    background:rgba(201,169,110,.12);border:1px solid rgba(201,169,110,.35);
    color:var(--gold-dark);font-size:13px;font-weight:700;
}
.status-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);animation:blink 1.4s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}

/* Note box */
.note-box{
    background:#fffbeb;border:1px solid #fde68a;border-radius:12px;
    padding:11px 14px;font-size:12px;color:#92400e;line-height:1.65;
    margin-bottom:20px;
}

/* Reset notice */
.reset-notice{
    display:flex;align-items:center;gap:7px;
    font-size:11px;color:var(--muted);
    padding:8px 14px;background:var(--bg);border-radius:8px;
    border:1px solid var(--border);margin-bottom:20px;
    text-align:center;justify-content:center;
}

/* Buttons */
.btn-group{display:flex;gap:10px;flex-wrap:wrap;}
.btn{
    flex:1;min-width:120px;display:inline-flex;align-items:center;justify-content:center;gap:7px;
    padding:13px 16px;border-radius:14px;font-family:'Sarabun',sans-serif;
    font-size:14px;font-weight:700;text-decoration:none;transition:all .2s;
    border:none;cursor:pointer;
}
.btn-primary{background:var(--ink);color:#fff;}
.btn-primary:hover{background:var(--blue);}
.btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.btn-ghost:hover{border-color:var(--blue);color:var(--blue);}
.btn-print{background:linear-gradient(135deg,var(--gold),#e8c98a);color:var(--ink);}
.btn-print:hover{filter:brightness(1.05);}

/* Booking ref */
.booking-ref{text-align:center;font-size:11px;color:var(--muted);margin-top:6px;}

@media print{
    body{background:#fff;padding:0;display:block;}
    .ticket{box-shadow:none;border:1px solid #ddd;margin:0 auto;border-radius:0;}
    .btn-group,.no-print{display:none!important;}
    .ticket-header{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
@media(max-width:480px){
    .queue-number{font-size:64px;}
    .ticket-body{padding:18px 18px 22px;}
}
</style>
</head>
<body>

<!-- ══════════ ใบคิว ══════════ -->
<div class="ticket" id="ticketEl">
    <!-- Header -->
    <div class="ticket-header">
        <div class="ticket-logo">🚣</div>
        <div class="ticket-org">สถาบันวิจัยวลัยรุกขเวช &nbsp;·&nbsp; ระบบจองคิวพายเรือ</div>
        <div class="queue-number-wrap">
            <div class="queue-label-text">หมายเลขคิวของคุณ</div>
            <div class="queue-number">
                <span><?= substr($queueLabel,0,1) ?></span><?= substr($queueLabel,1) ?>
            </div>
        </div>
    </div>

    <!-- Notch -->
    <div class="ticket-notch">
        <div class="ticket-notch::before"></div>
        <div style="display:flex;align-items:center;width:100%;padding:0 14px;">
            <div style="width:24px;height:24px;border-radius:50%;background:var(--bg);flex-shrink:0;margin-left:-26px;"></div>
            <div style="flex:1;border-top:2px dashed var(--border);margin:0 6px;"></div>
            <div style="width:24px;height:24px;border-radius:50%;background:var(--bg);flex-shrink:0;margin-right:-26px;"></div>
        </div>
    </div>

    <!-- Body -->
    <div class="ticket-body">
        <!-- ชื่อลูกค้า -->
        <div class="customer-name">
            <div class="label">ชื่อผู้จอง</div>
            <div class="name"><?= htmlspecialchars($customer_name) ?></div>
        </div>

        <!-- รายละเอียด -->
        <div class="ticket-rows">
            <div class="ticket-row">
                <div class="ticket-row-ico">🚣</div>
                <div>
                    <div class="ticket-row-label">คิว / ประเภทเรือ</div>
                    <div class="ticket-row-value"><?= htmlspecialchars($queue_name) ?> &nbsp;·&nbsp; <?= htmlspecialchars($boat_type) ?></div>
                </div>
            </div>
            <div class="ticket-row">
                <div class="ticket-row-ico">📅</div>
                <div>
                    <div class="ticket-row-label">วันที่ / เวลา</div>
                    <div class="ticket-row-value"><?= $dateDisplay ?> &nbsp;🕐 <?= $timeDisplay ?></div>
                </div>
            </div>
            <div class="ticket-row">
                <div class="ticket-row-ico">🛶</div>
                <div>
                    <div class="ticket-row-label">เรือที่จอง (<?= count($selected_units) ?> ลำ)</div>
                    <div class="boat-pills">
                        <?php foreach ($selected_units as $u): ?>
                            <span class="boat-pill">🚣 ลำที่ <?= (int)$u ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="ticket-row">
                <div class="ticket-row-ico">👥</div>
                <div>
                    <div class="ticket-row-label">จำนวนผู้เข้าร่วม</div>
                    <div class="ticket-row-value"><?= (int)$guests ?> คน</div>
                </div>
            </div>
        </div>

        <!-- สถานะ -->
        <div class="status-wrap">
            <div class="status-badge">
                <span class="status-dot"></span>
                รอการยืนยันจากเจ้าหน้าที่
            </div>
        </div>

        <!-- หมายเหตุ -->
        <div class="note-box">
            ⚠ กรุณาแสดงใบคิวนี้ต่อเจ้าหน้าที่ในวันที่มาใช้บริการ &nbsp;·&nbsp;
            หมายเลขคิว <strong><?= $queueLabel ?></strong> ใช้ได้เฉพาะวันที่ <strong><?= $dateDisplay ?></strong>
        </div>

        <!-- Reset notice -->
        <div class="reset-notice">
            🔄 ระบบคิวจะรีเซ็ตและเริ่มนับใหม่ทุกวันเที่ยงคืน (00:00 น.)
        </div>

        <!-- ปุ่ม -->
        <div class="btn-group no-print">
            <button onclick="window.print()" class="btn btn-print">🖨 พิมพ์ใบคิว</button>
            <a href="booking_boat.php" class="btn btn-primary">🚣 จองคิวเพิ่ม</a>
            <a href="booking_boat_status.php" class="btn btn-ghost">📋 ติดตามสถานะ</a>
        </div>
        <div class="booking-ref no-print">หมายเลขการจอง <?= $bookingRef ?> &nbsp;·&nbsp; <?= date('d/m/Y H:i') ?> น.</div>
    </div>
</div>

</body>
</html>
