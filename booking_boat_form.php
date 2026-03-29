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
$user_id    = (int)($_SESSION['user_id'] ?? 0);
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = '';

if ($user_id > 0) {
    $uStmt = $conn->prepare("SELECT phone, name, email FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    if ($uRow) {
        if (empty($user_name)  && !empty($uRow['name']))  $user_name  = $uRow['name'];
        if (empty($user_email) && !empty($uRow['email'])) $user_email = $uRow['email'];
        $user_phone = $uRow['phone'] ?? '';
    }
}

/* ── ประเภทเรือที่แอดมินกำหนด ── */
$boatTypesRaw = trim($queue['boat_types'] ?? '');
$boatTypeList = $boatTypesRaw !== ''
    ? array_filter(array_map('trim', explode(',', $boatTypesRaw)))
    : ['เรือพาย', 'เรือคายัค', 'เรือบด'];

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
<?php
// Redirect ทุกการเข้าถึง booking_boat_form.php ไป booking_boat.php ทันที
header("Location: booking_boat.php");
exit;
                    <div class="form-group">
                        <label>จำนวนผู้เข้าร่วม (คน)</label>
                        <input type="number" name="guests" min="1" value="1" required>
                    </div>
                    <div class="form-group full">
                        <label>หมายเหตุ</label>
                        <textarea name="note" placeholder="ข้อมูลเพิ่มเติม หรือความต้องการพิเศษ..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-submit">🚣 ยืนยันการจองและรับใบคิว</button>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    /* ── ประเภทเรือ ── */
    document.querySelectorAll('.boat-type-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.boat-type-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            card.querySelector('input[type=radio]').checked = true;
        });
    });

    /* ── เลือกหมายเลขเรือ ── */
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
