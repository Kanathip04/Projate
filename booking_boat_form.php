<?php
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$queue_id = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : 0;
if ($queue_id <= 0) { header("Location: booking_boat.php"); exit; }

$stmt = $conn->prepare("SELECT * FROM boat_queues WHERE id = ? AND status = 'show' LIMIT 1");
$stmt->bind_param("i", $queue_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) { header("Location: booking_boat.php"); exit; }
$queue = $result->fetch_assoc();
$stmt->close();

/* ── ดึงข้อมูล user จาก session + DB ── */
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = '';
if (!empty($_SESSION['user_id'])) {
    $uid   = (int)$_SESSION['user_id'];
    $uStmt = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $uid);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    $user_phone = $uRow['phone'] ?? '';
}

/* ── ดึงเรือที่ถูกจองแล้ว ── */
$total_boats = max(1, (int)($queue['total_boats'] ?? 1));
$takenBoats  = [];
$takenStmt = $conn->prepare(
    "SELECT boat_units, booking_status FROM boat_bookings
     WHERE queue_id = ? AND booking_status IN ('pending','approved')
     AND boat_units IS NOT NULL AND boat_units != ''"
);
$takenStmt->bind_param("i", $queue_id);
$takenStmt->execute();
$takenResult = $takenStmt->get_result();
while ($tr = $takenResult->fetch_assoc()) {
    $units = json_decode($tr['boat_units'], true) ?: [];
    foreach ($units as $u) { $takenBoats[(int)$u] = $tr['booking_status']; }
}
$takenStmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>กรอกข้อมูลจองคิวพายเรือ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
    --bg:#f0f7ff;--card:#ffffff;--muted:#7a7a8c;--border:#dce8f5;
    --blue:#1d6fad;--blue-light:#e8f4ff;--blue-dark:#0d2344;
    --success:#15803d;--success-bg:#ecfdf3;
    --warning:#d97706;--warning-bg:#fffbeb;
    --danger:#dc2626;--danger-bg:#fef2f2;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;padding:40px 16px;}
.wrapper{width:min(980px,100%);margin:0 auto;}
.back-link{display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;color:var(--ink);text-decoration:none;font-weight:600;font-size:14px;opacity:.75;transition:opacity .2s;}
.back-link:hover{opacity:1;}
.card{background:var(--card);border-radius:20px;box-shadow:0 8px 32px rgba(29,111,173,.10);overflow:hidden;}
.header{background:linear-gradient(135deg,#0a1628 0%,#0d2344 50%,#1a3a5c 100%);color:#fff;padding:32px 36px;border-bottom:3px solid var(--blue);position:relative;overflow:hidden;}
.header::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 20% 50%,rgba(29,111,173,.35) 0%,transparent 55%);pointer-events:none;}
.header h1{font-size:26px;font-weight:700;margin-bottom:6px;position:relative;}
.header p{font-size:14px;font-weight:300;opacity:.8;position:relative;}
.content{padding:32px 36px;}

/* ── Queue Info Box ── */
.queue-box{background:var(--blue-light);border:1px solid #b8d8f5;border-radius:14px;padding:20px 24px;margin-bottom:28px;display:flex;gap:16px;align-items:flex-start;}
.queue-box-icon{font-size:32px;flex-shrink:0;}
.queue-box h3{font-size:18px;font-weight:700;color:var(--ink);margin-bottom:8px;}
.queue-box p{font-size:14px;color:var(--ink);margin-bottom:4px;opacity:.85;}
.queue-box p strong{opacity:1;}
.time-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;background:var(--blue);color:#fff;font-size:13px;font-weight:700;margin-top:6px;}

/* ── Section Title ── */
.section-label{font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.section-label::before{content:'';display:inline-block;width:3px;height:14px;background:var(--blue);border-radius:2px;}

/* ── Boat Selection Grid ── */
.boat-section{margin-bottom:28px;}
.boat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:10px;margin-bottom:10px;}
.boat-card{
    position:relative;border-radius:12px;padding:14px 10px;text-align:center;cursor:pointer;
    border:2px solid var(--border);background:#f8fbff;
    transition:all .2s ease;user-select:none;
}
.boat-card:not(.boat-taken):hover{border-color:var(--blue);background:var(--blue-light);}
.boat-card.boat-selected{background:var(--ink);border-color:var(--ink);color:#fff;}
.boat-card.boat-selected .boat-num{color:#fff;}
.boat-card.boat-selected .boat-lbl{color:rgba(255,255,255,.75);}
.boat-card.boat-selected::after{content:'✓';position:absolute;top:4px;right:6px;font-size:11px;font-weight:700;color:#7ec8f4;}
.boat-card.boat-pending{background:var(--warning-bg);border-color:#fde68a;cursor:not-allowed;}
.boat-card.boat-pending .boat-lbl{color:var(--warning);}
.boat-card.boat-approved{background:var(--danger-bg);border-color:#fca5a5;cursor:not-allowed;}
.boat-card.boat-approved .boat-lbl{color:var(--danger);}
.boat-card input[type=checkbox]{display:none;}
.boat-num{font-size:20px;line-height:1.2;font-weight:800;color:var(--ink);}
.boat-emoji{font-size:18px;display:block;margin-bottom:4px;}
.boat-lbl{font-size:11px;color:var(--muted);margin-top:3px;font-weight:600;}
.boat-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:10px;}
.legend-item{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);}
.legend-dot{width:10px;height:10px;border-radius:3px;border:2px solid;}
.legend-dot.d-avail{background:#f8fbff;border-color:var(--border);}
.legend-dot.d-sel{background:var(--ink);border-color:var(--ink);}
.legend-dot.d-pend{background:var(--warning-bg);border-color:#fde68a;}
.legend-dot.d-book{background:var(--danger-bg);border-color:#fca5a5;}
.boat-summary{margin-top:10px;padding:10px 16px;border-radius:10px;background:var(--success-bg);border:1px solid #d1fadf;color:var(--success);font-size:13px;font-weight:700;display:none;}
.boat-summary.show{display:block;}

/* ── Form ── */
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
.form-group{display:flex;flex-direction:column;}
.form-group.full{grid-column:1/-1;}
label{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:7px;letter-spacing:.4px;text-transform:uppercase;}
.prefilled-badge{font-size:10px;font-weight:700;color:var(--success);background:var(--success-bg);padding:2px 7px;border-radius:999px;margin-left:6px;text-transform:none;letter-spacing:0;border:1px solid #d1fadf;}
input,textarea,select{font-family:'Sarabun',sans-serif;font-size:15px;color:var(--ink);background:#f8fbff;border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;outline:none;transition:border-color .2s,box-shadow .2s;}
input:focus,textarea:focus,select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(29,111,173,.14);background:#fff;}
textarea{min-height:100px;resize:vertical;}
.divider{height:1px;background:var(--border);margin:24px 0;}
.btn-submit{margin-top:28px;width:100%;padding:15px;border:none;border-radius:12px;background:var(--ink);color:#fff;font-family:'Sarabun',sans-serif;font-size:16px;font-weight:700;cursor:pointer;transition:background .2s,transform .15s;}
.btn-submit:hover{background:var(--blue);transform:translateY(-1px);}
@media(max-width:640px){
    .header{padding:24px 20px;}.content{padding:24px 20px;}
    .form-grid{grid-template-columns:1fr;}
    .boat-grid{grid-template-columns:repeat(auto-fill,minmax(76px,1fr));}
}
</style>
</head>
<body>
<div class="wrapper">
    <a href="booking_boat.php" class="back-link">← กลับไปหน้าคิวพายเรือ</a>

    <div class="card">
        <div class="header">
            <h1>🚣 กรอกข้อมูลจองคิวพายเรือ</h1>
            <p>กรุณาเลือกหมายเลขเรือและกรอกข้อมูลให้ครบถ้วน</p>
        </div>

        <div class="content">
            <!-- Queue Info -->
            <div class="queue-box">
                <div class="queue-box-icon">🚣</div>
                <div>
                    <h3><?= htmlspecialchars($queue['queue_name']) ?></h3>
                    <p><strong>วันที่:</strong> <?= date('l, d F Y', strtotime($queue['queue_date'])) ?></p>
                    <p><strong>เรือทั้งหมด:</strong> <?= $total_boats ?> ลำ</p>
                    <?php if ((float)$queue['price_per_boat'] > 0): ?>
                        <p><strong>ราคา:</strong> ฿<?= number_format((float)$queue['price_per_boat']) ?> / ลำ</p>
                    <?php else: ?>
                        <p><strong>ราคา:</strong> ฟรี</p>
                    <?php endif; ?>
                    <div class="time-badge">🕐 <?= substr($queue['time_start'],0,5) ?> – <?= substr($queue['time_end'],0,5) ?> น.</div>
                </div>
            </div>

            <!-- Boat Selection -->
            <div class="boat-section">
                <div class="section-label">เลือกหมายเลขเรือที่ต้องการ</div>
                <div class="boat-grid" id="boatGrid">
                    <?php for ($b = 1; $b <= $total_boats; $b++):
                        $isTaken   = isset($takenBoats[$b]);
                        $bStatus   = $takenBoats[$b] ?? '';
                        $cardClass = 'boat-card';
                        $lbl       = 'ว่าง';
                        if ($isTaken && $bStatus === 'pending')  { $cardClass .= ' boat-taken boat-pending'; $lbl = 'รออนุมัติ'; }
                        if ($isTaken && $bStatus === 'approved') { $cardClass .= ' boat-taken boat-approved'; $lbl = 'จองแล้ว'; }
                    ?>
                        <div class="<?= $cardClass ?>" data-boat="<?= $b ?>">
                            <?php if (!$isTaken): ?><input type="checkbox" value="<?= $b ?>"><?php endif; ?>
                            <span class="boat-emoji">🚣</span>
                            <div class="boat-num"><?= $b ?></div>
                            <div class="boat-lbl"><?= $lbl ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="boat-legend">
                    <div class="legend-item"><span class="legend-dot d-avail"></span>ว่าง</div>
                    <div class="legend-item"><span class="legend-dot d-sel"></span>เลือกแล้ว</div>
                    <div class="legend-item"><span class="legend-dot d-pend"></span>รออนุมัติ</div>
                    <div class="legend-item"><span class="legend-dot d-book"></span>จองแล้ว</div>
                </div>
                <div class="boat-summary" id="boatSummary"></div>
            </div>

            <div class="divider"></div>

            <!-- Booking Form -->
            <form action="save_boat_booking.php" method="POST" id="bookingForm">
                <input type="hidden" name="queue_id"    value="<?= (int)$queue['id'] ?>">
                <input type="hidden" name="queue_name"  value="<?= htmlspecialchars($queue['queue_name']) ?>">
                <input type="hidden" name="boat_date"   value="<?= htmlspecialchars($queue['queue_date']) ?>">
                <input type="hidden" name="time_start"  value="<?= htmlspecialchars($queue['time_start']) ?>">
                <input type="hidden" name="time_end"    value="<?= htmlspecialchars($queue['time_end']) ?>">
                <div id="boatHiddenContainer"></div>

                <div class="section-label">ข้อมูลผู้จอง</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ชื่อผู้จอง<?php if ($user_name): ?><span class="prefilled-badge">ดึงจากโปรไฟล์</span><?php endif; ?></label>
                        <input type="text" name="customer_name" placeholder="ชื่อ-นามสกุล"
                               value="<?= htmlspecialchars($user_name) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>เบอร์โทร<?php if ($user_phone): ?><span class="prefilled-badge">ดึงจากโปรไฟล์</span><?php endif; ?></label>
                        <input type="text" name="phone" placeholder="0XX-XXX-XXXX"
                               value="<?= htmlspecialchars($user_phone) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>อีเมล<?php if ($user_email): ?><span class="prefilled-badge">ดึงจากโปรไฟล์</span><?php endif; ?></label>
                        <input type="email" name="email" placeholder="example@email.com"
                               value="<?= htmlspecialchars($user_email) ?>">
                    </div>
                    <div class="form-group">
                        <label>จำนวนผู้เข้าร่วม (คน)</label>
                        <input type="number" name="guests" min="1" value="1" required>
                    </div>
                    <div class="form-group full">
                        <label>หมายเหตุ</label>
                        <textarea name="note" placeholder="ข้อมูลเพิ่มเติม หรือความต้องการพิเศษ..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-submit">🚣 ยืนยันการจองคิวพายเรือ</button>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    const cards   = document.querySelectorAll('.boat-card:not(.boat-taken)');
    const summary = document.getElementById('boatSummary');
    const hidden  = document.getElementById('boatHiddenContainer');
    cards.forEach(card => {
        card.addEventListener('click', () => {
            const cb = card.querySelector('input[type=checkbox]');
            cb.checked = !cb.checked;
            card.classList.toggle('boat-selected', cb.checked);
            refresh();
        });
    });
    function refresh() {
        const sel = [...document.querySelectorAll('.boat-card input:checked')].map(c => parseInt(c.value));
        if (sel.length > 0) {
            summary.textContent = '✓ เลือกแล้ว: เรือลำที่ ' + sel.join(', ') + ' (รวม ' + sel.length + ' ลำ)';
            summary.classList.add('show');
        } else {
            summary.textContent = '';
            summary.classList.remove('show');
        }
        hidden.innerHTML = '';
        sel.forEach(n => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = 'boat_units[]'; i.value = n;
            hidden.appendChild(i);
        });
    }
    document.getElementById('bookingForm').addEventListener('submit', e => {
        if (document.querySelectorAll('.boat-card input:checked').length === 0) {
            alert('กรุณาเลือกเรืออย่างน้อย 1 ลำ');
            e.preventDefault();
        }
    });
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
