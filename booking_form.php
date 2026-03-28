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

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$room_id  = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$checkin  = trim($_GET['checkin'] ?? '');
$checkout = trim($_GET['checkout'] ?? '');
$guests   = trim($_GET['guests'] ?? '1');

if ($room_id <= 0) {
    die("ไม่พบข้อมูลห้องพัก");
}

/* === ดึงข้อมูลห้องพัก === */
$stmt = $conn->prepare("
    SELECT id, room_name, room_type, price, room_size, bed_type, capacity, image, description, total_rooms
    FROM rooms
    WHERE id = ? AND status = 'show'
    LIMIT 1
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("ไม่พบข้อมูลห้องพัก");
}

$room = $result->fetch_assoc();
$stmt->close();

$total_rooms = max(1, (int)($room['total_rooms'] ?? 1));

/* === ดึงข้อมูล profile ของ user === */
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = '';
if (!empty($_SESSION['user_id'])) {
    $uStmt = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $_SESSION['user_id']);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    $user_phone = $uRow['phone'] ?? '';
}

/* === ดึงหน่วยห้องที่ถูกจองแล้ว (pending + approved) === */
$takenUnits = []; // unit_number => 'pending'|'approved'
$colRes = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA='backoffice_db' AND TABLE_NAME='room_bookings' AND COLUMN_NAME='room_units'");
$colRow = $colRes ? $colRes->fetch_assoc() : null;
if ($colRow && (int)$colRow['cnt'] > 0) {
    $takenStmt = $conn->prepare(
        "SELECT room_units, booking_status FROM room_bookings
         WHERE room_id = ? AND booking_status IN ('pending','approved')
         AND room_units IS NOT NULL AND room_units != ''"
    );
    $takenStmt->bind_param("i", $room_id);
    $takenStmt->execute();
    $takenResult = $takenStmt->get_result();
    while ($tr = $takenResult->fetch_assoc()) {
        $units = json_decode($tr['room_units'], true) ?: [];
        foreach ($units as $u) {
            $takenUnits[(int)$u] = $tr['booking_status'];
        }
    }
    $takenStmt->close();
}

if ($guests === '' || !is_numeric($guests) || (int)$guests < 1) {
    $guests = 1;
}
if ($checkin === '') {
    $checkin = date('Y-m-d');
}
if ($checkout === '' || $checkout <= $checkin) {
    $checkout = date('Y-m-d', strtotime($checkin . ' +1 day'));
}

$availCount = $total_rooms - count($takenUnits);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>กรอกข้อมูลการจองห้องพัก</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --ink:        #1a1a2e;
    --gold:       #c9a96e;
    --gold-light: #f5ead8;
    --bg:         #f5f1eb;
    --card:       #ffffff;
    --muted:      #7a7a8c;
    --border:     #e8e4de;
    --success:    #15803d;
    --success-bg: #ecfdf3;
    --danger:     #dc2626;
    --warning:    #d97706;
    --warning-bg: #fffbeb;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Sarabun', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    padding: 40px 16px;
  }

  .wrapper { width: min(960px, 100%); margin: 0 auto; }

  .back-link {
    display: inline-flex; align-items: center; gap: 6px;
    margin-bottom: 20px; color: var(--ink); text-decoration: none;
    font-weight: 600; font-size: 14px; opacity: .75; transition: opacity .2s;
  }
  .back-link:hover { opacity: 1; }

  .card {
    background: var(--card); border-radius: 20px;
    box-shadow: 0 8px 32px rgba(26,26,46,.10); overflow: hidden;
  }

  .header {
    background: linear-gradient(135deg, #1a1a2e 0%, #2d2d4e 100%);
    color: #fff; padding: 32px 36px; border-bottom: 3px solid var(--gold);
  }
  .header h1 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
  .header p  { font-size: 14px; font-weight: 300; opacity: .8; }

  .content { padding: 32px 36px; }

  /* Room info box */
  .room-box {
    background: var(--gold-light); border: 1px solid var(--gold);
    border-radius: 14px; padding: 20px 24px; margin-bottom: 28px;
    display: flex; justify-content: space-between; align-items: flex-start;
    flex-wrap: wrap; gap: 12px;
  }
  .room-box-info h3 { font-size: 18px; font-weight: 700; color: var(--ink); margin-bottom: 8px; }
  .room-box-info p  { font-size: 14px; color: var(--ink); margin-bottom: 4px; opacity: .85; }
  .room-avail-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 700;
    white-space: nowrap;
  }
  .room-avail-badge.has-avail { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }
  .room-avail-badge.no-avail  { background: #fef2f2; color: var(--danger); border: 1px solid #fca5a5; }

  /* ── Unit selector ── */
  .unit-section { margin-bottom: 28px; }
  .unit-section-label {
    font-size: 13px; font-weight: 700; color: var(--muted);
    margin-bottom: 14px; letter-spacing: .4px; text-transform: uppercase;
    display: flex; align-items: center; gap: 8px;
  }
  .unit-section-label .req { color: var(--danger); }
  .unit-section-label .count-badge {
    background: rgba(26,26,46,.07); color: var(--ink);
    padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
  }
  .unit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
  }
  .unit-card {
    display: flex; flex-direction: column; align-items: center; gap: 5px;
    padding: 16px 10px; border: 2px solid var(--border); border-radius: 14px;
    cursor: pointer; transition: border-color .2s, background .2s, transform .15s;
    background: #faf9f7; user-select: none; position: relative;
  }
  .unit-card input[type="checkbox"] { display: none; }
  .unit-card:not(.unit-taken):hover {
    border-color: var(--gold); background: var(--gold-light); transform: translateY(-2px);
  }
  .unit-card.unit-selected {
    border-color: var(--ink); background: var(--ink);
  }
  .unit-card.unit-selected .unit-num   { color: #fff; }
  .unit-card.unit-selected .unit-stat  { color: rgba(255,255,255,.65); }
  .unit-card.unit-selected::after {
    content: '✓'; position: absolute; top: 6px; right: 8px;
    color: var(--gold); font-size: 13px; font-weight: 900;
  }
  .unit-card.unit-taken {
    background: #f3f4f6; border-color: #e5e7eb; cursor: not-allowed; opacity: .6;
  }
  .unit-card.unit-taken.unit-pending { background: var(--warning-bg); border-color: #fde68a; opacity: .75; }
  .unit-icon { font-size: 22px; line-height: 1; }
  .unit-num  { font-size: 14px; font-weight: 700; color: var(--ink); text-align: center; }
  .unit-stat { font-size: 11px; color: var(--muted); text-align: center; }

  .unit-summary {
    display: none; padding: 11px 16px; border-radius: 10px; font-size: 14px;
    font-weight: 600; background: rgba(26,26,46,.06); color: var(--ink);
    border: 1px solid rgba(26,26,46,.1); margin-top: 4px;
  }
  .unit-summary.show { display: block; }

  /* Legend */
  .unit-legend {
    display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;
    font-size: 12px; color: var(--muted);
  }
  .leg { display: flex; align-items: center; gap: 5px; }
  .leg-dot {
    width: 12px; height: 12px; border-radius: 3px; border: 1.5px solid;
  }
  .leg-dot.avail   { background: #faf9f7;        border-color: var(--border); }
  .leg-dot.sel     { background: var(--ink);     border-color: var(--ink); }
  .leg-dot.pending { background: var(--warning-bg); border-color: #fde68a; }
  .leg-dot.booked  { background: #f3f4f6;        border-color: #e5e7eb; }

  /* Form grid */
  .section-divider { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
  .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
  .form-group { display: flex; flex-direction: column; }
  .form-group.full { grid-column: 1 / -1; }

  label {
    font-size: 13px; font-weight: 600; color: var(--muted);
    margin-bottom: 7px; letter-spacing: .4px; text-transform: uppercase;
  }
  input, textarea, select {
    font-family: 'Sarabun', sans-serif; font-size: 15px; color: var(--ink);
    background: #faf9f7; border: 1.5px solid var(--border);
    border-radius: 10px; padding: 11px 14px; outline: none;
    transition: border-color .2s, box-shadow .2s;
  }
  input:focus, textarea:focus, select:focus {
    border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,169,110,.18); background: #fff;
  }
  input::placeholder, textarea::placeholder { color: var(--border); }
  textarea { min-height: 100px; resize: vertical; }
  select { appearance: none; cursor: pointer; }

  .btn-submit {
    margin-top: 28px; width: 100%; padding: 15px; border: none;
    border-radius: 12px; background: var(--ink); color: #fff;
    font-family: 'Sarabun', sans-serif; font-size: 16px; font-weight: 700;
    letter-spacing: .4px; cursor: pointer; transition: background .2s, transform .15s;
  }
  .btn-submit:hover { background: #2d2d4e; transform: translateY(-1px); }
  .btn-submit:active { transform: translateY(0); }

  @media (max-width: 640px) {
    .header  { padding: 24px 20px; }
    .content { padding: 24px 20px; }
    .form-grid { grid-template-columns: 1fr; }
    .unit-grid { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); }
  }
</style>
</head>
<body>
<div class="wrapper">
  <a href="/Projate/booking_room.php?checkin=<?= urlencode($_GET['checkin'] ?? '') ?>&checkout=<?= urlencode($_GET['checkout'] ?? '') ?>&guests=<?= urlencode($_GET['guests'] ?? '') ?>"
     class="back-link">&#8592; กลับไปหน้าห้องพัก</a>

  <div class="card">
    <div class="header">
      <h1>กรอกข้อมูลการจอง</h1>
      <p>กรุณากรอกข้อมูลให้ครบถ้วนเพื่อยืนยันการจองห้องพัก</p>
    </div>

    <div class="content">

      <!-- Room info -->
      <div class="room-box">
        <div class="room-box-info">
          <h3><?= htmlspecialchars($room['room_name']) ?></h3>
          <p><strong>ประเภทห้อง:</strong> <?= htmlspecialchars($room['room_type']) ?></p>
          <p><strong>ราคา:</strong> ฿<?= number_format((float)$room['price']) ?> / คืน</p>
          <p><strong>รองรับ:</strong> <?= (int)$room['capacity'] ?> คน / ห้อง</p>
          <p><strong>จำนวนห้องทั้งหมด:</strong> <?= $total_rooms ?> ห้อง</p>
        </div>
        <div class="room-avail-badge <?= $availCount > 0 ? 'has-avail' : 'no-avail' ?>">
          <?= $availCount > 0
            ? "ว่าง {$availCount} / {$total_rooms} ห้อง"
            : "เต็มทุกห้องแล้ว" ?>
        </div>
      </div>

      <!-- Unit selector -->
      <div class="unit-section">
        <div class="unit-section-label">
          เลือกห้องที่ต้องการจอง <span class="req">*</span>
          <span class="count-badge">ว่าง <?= $availCount ?> ห้อง</span>
        </div>

        <div class="unit-legend">
          <div class="leg"><div class="leg-dot avail"></div> ว่าง</div>
          <div class="leg"><div class="leg-dot sel"></div> เลือกแล้ว</div>
          <div class="leg"><div class="leg-dot pending"></div> รออนุมัติ</div>
          <div class="leg"><div class="leg-dot booked"></div> จองแล้ว</div>
        </div>

        <div class="unit-grid">
          <?php for ($u = 1; $u <= $total_rooms; $u++):
            $unitSt  = $takenUnits[$u] ?? 'available';
            $isAvail = ($unitSt === 'available');
            $cardCls = 'unit-card';
            $icon    = '🏠';
            $statTxt = 'ว่าง';
            if (!$isAvail) {
              $cardCls .= ' unit-taken';
              if ($unitSt === 'pending') {
                $cardCls .= ' unit-pending';
                $icon    = '⏳';
                $statTxt = 'รออนุมัติ';
              } else {
                $icon    = '🔒';
                $statTxt = 'จองแล้ว';
              }
            }
          ?>
            <label class="<?= $cardCls ?>" id="unit-label-<?= $u ?>">
              <input type="checkbox" name="room_units[]" value="<?= $u ?>"
                     <?= $isAvail ? '' : 'disabled' ?>>
              <span class="unit-icon"><?= $icon ?></span>
              <span class="unit-num">ห้องที่ <?= $u ?></span>
              <span class="unit-stat"><?= $statTxt ?></span>
            </label>
          <?php endfor; ?>
        </div>

        <div class="unit-summary" id="unitSummary"></div>
      </div>

      <hr class="section-divider">

      <form action="save_booking.php" method="POST" id="bookingForm">
        <!-- ส่ง room_units ที่เลือก (hidden fields จะถูก populate โดย JS) -->
        <div id="unitHiddenContainer"></div>

        <input type="hidden" name="room_id"    value="<?= (int)$room['id'] ?>">
        <input type="hidden" name="room_name"  value="<?= htmlspecialchars($room['room_name']) ?>">
        <input type="hidden" name="room_price" value="<?= htmlspecialchars($room['price']) ?>">

        <div class="form-grid">
          <div class="form-group">
            <label>ชื่อผู้จอง</label>
            <input type="text" name="customer_name" placeholder="กรอกชื่อ-นามสกุล"
                   value="<?= htmlspecialchars($user_name) ?>" required>
          </div>

          <div class="form-group">
            <label>เบอร์โทร</label>
            <input type="text" name="phone" placeholder="0XX-XXX-XXXX"
                   value="<?= htmlspecialchars($user_phone) ?>" required>
          </div>

          <div class="form-group">
            <label>อีเมล</label>
            <input type="email" name="email" placeholder="example@email.com"
                   value="<?= htmlspecialchars($user_email) ?>">
          </div>

          <div class="form-group">
            <label>จำนวนผู้ใหญ่</label>
            <input type="number" name="adults" min="1" value="<?= (int)$guests ?>" required>
          </div>

          <div class="form-group">
            <label>วันเช็คอิน</label>
            <input type="date" name="checkin_date"
                   value="<?= htmlspecialchars($checkin) ?>" required>
          </div>

          <div class="form-group">
            <label>วันเช็คเอาท์</label>
            <input type="date" name="checkout_date"
                   value="<?= htmlspecialchars($checkout) ?>" required>
          </div>

          <div class="form-group">
            <label>จำนวนเด็ก</label>
            <input type="number" name="children" min="0" value="0">
          </div>

          <div class="form-group">
            <label>วิธีชำระเงิน</label>
            <select name="payment_method">
              <option value="โอนเงิน">โอนเงิน</option>
              <option value="ชำระเงินสด">ชำระเงินสด</option>
            </select>
          </div>

          <div class="form-group full">
            <label>หมายเหตุเพิ่มเติม</label>
            <textarea name="note" placeholder="ข้อมูลเพิ่มเติม หรือความต้องการพิเศษ..."></textarea>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">ยืนยันการจอง</button>
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

    // อัปเดต summary
    if (selected.length > 0) {
      const names = selected.map(n => 'ห้องที่ ' + n).join(', ');
      summary.textContent = '✓ เลือกแล้ว: ' + names + ' (รวม ' + selected.length + ' ห้อง)';
      summary.classList.add('show');
    } else {
      summary.textContent = '';
      summary.classList.remove('show');
    }

    // sync hidden fields ไปกับ form
    hidden.innerHTML = '';
    selected.forEach(n => {
      const inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = 'room_units[]';
      inp.value = n;
      hidden.appendChild(inp);
    });
  }

  document.getElementById('bookingForm').addEventListener('submit', function (e) {
    const selected = document.querySelectorAll('.unit-card input:checked').length;
    if (selected === 0) {
      alert('กรุณาเลือกห้องอย่างน้อย 1 ห้อง');
      e.preventDefault();
      return;
    }
  });
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
