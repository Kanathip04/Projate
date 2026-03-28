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
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$tent_id = isset($_GET['tent_id']) ? (int)$_GET['tent_id'] : 0;
if ($tent_id <= 0) { header("Location: booking_tent.php"); exit; }

$stmt = $conn->prepare("SELECT * FROM tents WHERE id = ? AND status = 'show' LIMIT 1");
$stmt->bind_param("i", $tent_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) { header("Location: booking_tent.php"); exit; }
$tent = $result->fetch_assoc();
$stmt->close();

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

/* ── ดึงข้อมูล user จาก session + DB ── */
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = '';
if (!empty($_SESSION['user_id'])) {
    $uStmt = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", (int)$_SESSION['user_id']);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    $user_phone = $uRow['phone'] ?? '';
}

/* ── ดึงหน่วยเต็นท์ที่ถูกจองแล้ว ── */
$total_tents = max(1, (int)($tent['total_tents'] ?? 1));
$takenUnits  = []; // unit_number => 'pending'|'approved'
$colRes = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA='backoffice_db' AND TABLE_NAME='tent_bookings' AND COLUMN_NAME='tent_units'");
$colRow = $colRes ? $colRes->fetch_assoc() : null;
if ($colRow && (int)$colRow['cnt'] > 0) {
    $takenStmt = $conn->prepare(
        "SELECT tent_units, booking_status FROM tent_bookings
         WHERE tent_id = ? AND booking_status IN ('pending','approved')
         AND tent_units IS NOT NULL AND tent_units != ''"
    );
    $takenStmt->bind_param("i", $tent_id);
    $takenStmt->execute();
    $takenResult = $takenStmt->get_result();
    while ($tr = $takenResult->fetch_assoc()) {
        $units = json_decode($tr['tent_units'], true) ?: [];
        foreach ($units as $u) { $takenUnits[(int)$u] = $tr['booking_status']; }
    }
    $takenStmt->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>กรอกข้อมูลการจองเต็นท์</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;--gold-light:#f5ead8;
    --bg:#f5f1eb;--card:#ffffff;--muted:#7a7a8c;--border:#e8e4de;
    --success:#15803d;--success-bg:#ecfdf3;
    --warning:#d97706;--warning-bg:#fffbeb;
    --danger:#dc2626;--danger-bg:#fef2f2;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;padding:40px 16px;}
.wrapper{width:min(980px,100%);margin:0 auto;}
.back-link{display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;color:var(--ink);text-decoration:none;font-weight:600;font-size:14px;opacity:.75;transition:opacity .2s;}
.back-link:hover{opacity:1;}
.card{background:var(--card);border-radius:20px;box-shadow:0 8px 32px rgba(26,26,46,.10);overflow:hidden;}
.header{background:linear-gradient(135deg,#0d1f0d 0%,#1a2e1a 40%,#1a1a2e 100%);color:#fff;padding:32px 36px;border-bottom:3px solid var(--gold);position:relative;overflow:hidden;}
.header::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 20% 50%,rgba(21,128,61,.25) 0%,transparent 55%);pointer-events:none;}
.header h1{font-size:26px;font-weight:700;margin-bottom:6px;position:relative;}
.header p{font-size:14px;font-weight:300;opacity:.8;position:relative;}
.content{padding:32px 36px;}

/* ── Tent Info Box ── */
.tent-box{background:var(--gold-light);border:1px solid var(--gold);border-radius:14px;padding:20px 24px;margin-bottom:28px;display:flex;gap:16px;align-items:flex-start;}
.tent-box-icon{font-size:32px;flex-shrink:0;}
.tent-box h3{font-size:18px;font-weight:700;color:var(--ink);margin-bottom:8px;}
.tent-box p{font-size:14px;color:var(--ink);margin-bottom:4px;opacity:.85;}
.tent-box p strong{opacity:1;}

/* ── Section Title ── */
.section-label{font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.section-label::before{content:'';display:inline-block;width:3px;height:14px;background:var(--gold);border-radius:2px;}

/* ── Unit Selection Grid ── */
.unit-section{margin-bottom:28px;}
.unit-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:10px;margin-bottom:10px;}
.unit-card{
    position:relative;border-radius:12px;padding:14px 10px;text-align:center;cursor:pointer;
    border:2px solid var(--border);background:#fafaf8;
    transition:all .2s ease;user-select:none;
}
.unit-card:not(.unit-taken):hover{border-color:var(--gold);background:var(--gold-light);}
.unit-card.unit-selected{background:var(--ink);border-color:var(--ink);color:#fff;}
.unit-card.unit-selected .unit-num{color:#fff;}
.unit-card.unit-selected .unit-lbl{color:rgba(255,255,255,.75);}
.unit-card.unit-selected::after{content:'✓';position:absolute;top:4px;right:6px;font-size:11px;font-weight:700;color:var(--gold);}
.unit-card.unit-pending{background:var(--warning-bg);border-color:#fde68a;cursor:not-allowed;}
.unit-card.unit-pending .unit-lbl{color:var(--warning);}
.unit-card.unit-approved{background:var(--danger-bg);border-color:#fca5a5;cursor:not-allowed;}
.unit-card.unit-approved .unit-lbl{color:var(--danger);}
.unit-card input[type=checkbox]{display:none;}
.unit-num{font-size:18px;font-weight:800;color:var(--ink);line-height:1.2;}
.unit-lbl{font-size:11px;color:var(--muted);margin-top:3px;font-weight:600;}

.unit-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:10px;}
.legend-item{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);}
.legend-dot{width:10px;height:10px;border-radius:3px;border:2px solid;}
.legend-dot.d-avail{background:#fafaf8;border-color:var(--border);}
.legend-dot.d-sel{background:var(--ink);border-color:var(--ink);}
.legend-dot.d-pend{background:var(--warning-bg);border-color:#fde68a;}
.legend-dot.d-book{background:var(--danger-bg);border-color:#fca5a5;}

.unit-summary{
    margin-top:10px;padding:10px 16px;border-radius:10px;
    background:var(--success-bg);border:1px solid #d1fadf;
    color:var(--success);font-size:13px;font-weight:700;
    display:none;
}
.unit-summary.show{display:block;}

/* ── Form ── */
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
.form-group{display:flex;flex-direction:column;}
.form-group.full{grid-column:1/-1;}
label{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:7px;letter-spacing:.4px;text-transform:uppercase;}
.prefilled-badge{font-size:10px;font-weight:700;color:var(--success);background:var(--success-bg);padding:2px 7px;border-radius:999px;margin-left:6px;text-transform:none;letter-spacing:0;border:1px solid #d1fadf;}
input,textarea,select{font-family:'Sarabun',sans-serif;font-size:15px;color:var(--ink);background:#faf9f7;border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;outline:none;transition:border-color .2s,box-shadow .2s;}
input:focus,textarea:focus,select:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.18);background:#fff;}
input[readonly]{background:#f0f0f0;color:var(--muted);cursor:default;}
textarea{min-height:100px;resize:vertical;}
.btn-submit{margin-top:28px;width:100%;padding:15px;border:none;border-radius:12px;background:var(--ink);color:#fff;font-family:'Sarabun',sans-serif;font-size:16px;font-weight:700;cursor:pointer;transition:background .2s,transform .15s;}
.btn-submit:hover{background:#2d2d4e;transform:translateY(-1px);}
.divider{height:1px;background:var(--border);margin:24px 0;}
@media(max-width:640px){
    .header{padding:24px 20px;}
    .content{padding:24px 20px;}
    .form-grid{grid-template-columns:1fr;}
    .unit-grid{grid-template-columns:repeat(auto-fill,minmax(76px,1fr));}
}
</style>
</head>
<body>
<div class="wrapper">
    <a href="booking_tent.php" class="back-link">← กลับไปหน้าเต็นท์</a>

    <div class="card">
        <div class="header">
            <h1>⛺ กรอกข้อมูลการจองเต็นท์</h1>
            <p>กรุณากรอกข้อมูลให้ครบถ้วนและเลือกหน่วยเต็นท์ที่ต้องการ</p>
        </div>

        <div class="content">
            <!-- Tent Info -->
            <div class="tent-box">
                <div class="tent-box-icon">⛺</div>
                <div>
                    <h3><?= htmlspecialchars($tent['tent_name']) ?></h3>
                    <?php if (!empty($tent['tent_type'])): ?>
                        <p><strong>ประเภท:</strong> <?= htmlspecialchars($tent['tent_type']) ?></p>
                    <?php endif; ?>
                    <p><strong>ราคา:</strong> ฿<?= number_format((float)$tent['price_per_night']) ?> / คืน</p>
                    <p><strong>รองรับ:</strong> <?= (int)$tent['capacity'] ?> คน / หน่วย &nbsp;|&nbsp; <strong>จำนวนทั้งหมด:</strong> <?= $total_tents ?> หน่วย</p>
                </div>
            </div>

            <!-- Unit Selection -->
            <div class="unit-section">
                <div class="section-label">เลือกหน่วยเต็นท์ที่ต้องการ</div>
                <div class="unit-grid" id="unitGrid">
                    <?php for ($u = 1; $u <= $total_tents; $u++):
                        $isTaken   = isset($takenUnits[$u]);
                        $status    = $takenUnits[$u] ?? '';
                        $cardClass = 'unit-card';
                        $lbl       = 'ว่าง';
                        if ($isTaken && $status === 'pending')  { $cardClass .= ' unit-taken unit-pending'; $lbl = 'รออนุมัติ'; }
                        if ($isTaken && $status === 'approved') { $cardClass .= ' unit-taken unit-approved'; $lbl = 'จองแล้ว'; }
                    ?>
                        <div class="<?= $cardClass ?>" data-unit="<?= $u ?>">
                            <?php if (!$isTaken): ?>
                                <input type="checkbox" value="<?= $u ?>">
                            <?php endif; ?>
                            <div class="unit-num"><?= $u ?></div>
                            <div class="unit-lbl"><?= $lbl ?></div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="unit-legend">
                    <div class="legend-item"><span class="legend-dot d-avail"></span>ว่าง</div>
                    <div class="legend-item"><span class="legend-dot d-sel"></span>เลือกแล้ว</div>
                    <div class="legend-item"><span class="legend-dot d-pend"></span>รออนุมัติ</div>
                    <div class="legend-item"><span class="legend-dot d-book"></span>จองแล้ว</div>
                </div>
                <div class="unit-summary" id="unitSummary"></div>
            </div>

            <div class="divider"></div>

            <!-- Booking Form -->
            <form action="save_tent_booking.php" method="POST" id="bookingForm">
                <input type="hidden" name="tent_id"    value="<?= htmlspecialchars($tent['id']) ?>">
                <input type="hidden" name="tent_name"  value="<?= htmlspecialchars($tent['tent_name']) ?>">
                <input type="hidden" name="tent_price" value="<?= htmlspecialchars($tent['price_per_night']) ?>">
                <div id="unitHiddenContainer"></div>

                <div class="section-label">ข้อมูลผู้จอง</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            ชื่อผู้จอง
                            <?php if ($user_name !== ''): ?><span class="prefilled-badge">ดึงจากโปรไฟล์</span><?php endif; ?>
                        </label>
                        <input type="text" name="customer_name" placeholder="กรอกชื่อ-นามสกุล"
                               value="<?= htmlspecialchars($user_name) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>
                            เบอร์โทร
                            <?php if ($user_phone !== ''): ?><span class="prefilled-badge">ดึงจากโปรไฟล์</span><?php endif; ?>
                        </label>
                        <input type="text" name="phone" placeholder="0XX-XXX-XXXX"
                               value="<?= htmlspecialchars($user_phone) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>
                            อีเมล
                            <?php if ($user_email !== ''): ?><span class="prefilled-badge">ดึงจากโปรไฟล์</span><?php endif; ?>
                        </label>
                        <input type="email" name="email" placeholder="example@email.com"
                               value="<?= htmlspecialchars($user_email) ?>">
                    </div>

                    <div class="form-group">
                        <label>จำนวนผู้เข้าพัก (คน)</label>
                        <input type="number" name="guests" min="1"
                               max="<?= (int)$tent['capacity'] ?>" value="1" required>
                    </div>

                    <div class="form-group">
                        <label>วันเช็คอิน</label>
                        <input type="date" name="checkin_date" value="<?= $today ?>" min="<?= $today ?>" required>
                    </div>

                    <div class="form-group">
                        <label>วันเช็คเอาท์</label>
                        <input type="date" name="checkout_date" value="<?= $tomorrow ?>" min="<?= $tomorrow ?>" required>
                    </div>

                    <div class="form-group full">
                        <label>หมายเหตุเพิ่มเติม</label>
                        <textarea name="note" placeholder="ข้อมูลเพิ่มเติม หรือความต้องการพิเศษ..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit">⛺ ยืนยันการจองเต็นท์</button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const cards   = document.querySelectorAll('.unit-card:not(.unit-taken)');
    const summary = document.getElementById('unitSummary');
    const hidden  = document.getElementById('unitHiddenContainer');

    cards.forEach(card => {
        card.addEventListener('click', () => {
            const cb = card.querySelector('input[type="checkbox"]');
            cb.checked = !cb.checked;
            card.classList.toggle('unit-selected', cb.checked);
            refreshSummary();
        });
    });

    function refreshSummary() {
        const selected = [...document.querySelectorAll('.unit-card input:checked')]
            .map(cb => parseInt(cb.value));
        if (selected.length > 0) {
            const names = selected.map(n => 'หน่วยที่ ' + n).join(', ');
            summary.textContent = '✓ เลือกแล้ว: ' + names + ' (รวม ' + selected.length + ' หน่วย)';
            summary.classList.add('show');
        } else {
            summary.textContent = '';
            summary.classList.remove('show');
        }
        hidden.innerHTML = '';
        selected.forEach(n => {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'tent_units[]';
            inp.value = n;
            hidden.appendChild(inp);
        });
    }

    document.getElementById('bookingForm').addEventListener('submit', function (e) {
        const selected = document.querySelectorAll('.unit-card input:checked').length;
        if (selected === 0) {
            alert('กรุณาเลือกหน่วยเต็นท์อย่างน้อย 1 หน่วย');
            e.preventDefault();
        }
    });
})();

/* ป้องกันวันเช็คเอาท์ก่อนเช็คอิน */
document.querySelector('[name=checkin_date]').addEventListener('change', function(){
    var co = document.querySelector('[name=checkout_date]');
    if(co.value <= this.value){
        var d = new Date(this.value);
        d.setDate(d.getDate()+1);
        co.value = d.toISOString().split('T')[0];
    }
    co.min = new Date(new Date(this.value).getTime()+86400000).toISOString().split('T')[0];
});
</script>
</body>
</html>
<?php $conn->close(); ?>
