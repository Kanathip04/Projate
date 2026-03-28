<?php
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "", "backoffice_db");
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

$stmt = $conn->prepare("
    SELECT id, room_name, room_type, price, room_size, bed_type, capacity, image, description
    FROM rooms
    WHERE id = ? AND status = 1
    LIMIT 1
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("ไม่พบข้อมูลห้องพัก");
}

$room = $result->fetch_assoc();

if ($guests === '' || !is_numeric($guests) || (int)$guests < 1) {
    $guests = 1;
}

if ($checkin === '') {
    $checkin = date('Y-m-d');
}

if ($checkout === '' || $checkout <= $checkin) {
    $checkout = date('Y-m-d', strtotime($checkin . ' +1 day'));
}
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
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Sarabun', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    padding: 40px 16px;
  }

  .wrapper {
    width: min(960px, 100%);
    margin: 0 auto;
  }

  /* Back link */
  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 20px;
    color: var(--ink);
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    opacity: .75;
    transition: opacity .2s;
  }
  .back-link:hover { opacity: 1; }

  /* Card */
  .card {
    background: var(--card);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(26,26,46,.10);
    overflow: hidden;
  }

  /* Card header */
  .header {
    background: linear-gradient(135deg, #1a1a2e 0%, #2d2d4e 100%);
    color: #fff;
    padding: 32px 36px;
    border-bottom: 3px solid var(--gold);
  }
  .header h1 {
    font-size: 26px;
    font-weight: 700;
    letter-spacing: .3px;
    margin-bottom: 6px;
  }
  .header p {
    font-size: 14px;
    font-weight: 300;
    opacity: .8;
  }

  /* Content area */
  .content { padding: 32px 36px; }

  /* Room info box */
  .room-box {
    background: var(--gold-light);
    border: 1px solid var(--gold);
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 28px;
  }
  .room-box h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 10px;
  }
  .room-box p {
    font-size: 14px;
    color: var(--ink);
    margin-bottom: 5px;
    opacity: .85;
  }
  .room-box p strong { opacity: 1; }

  /* Form grid */
  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
  }
  .form-group { display: flex; flex-direction: column; }
  .form-group.full { grid-column: 1 / -1; }

  label {
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: 7px;
    letter-spacing: .4px;
    text-transform: uppercase;
  }

  input,
  textarea,
  select {
    font-family: 'Sarabun', sans-serif;
    font-size: 15px;
    color: var(--ink);
    background: #faf9f7;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 11px 14px;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }
  input:focus,
  textarea:focus,
  select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201,169,110,.18);
    background: #fff;
  }
  input::placeholder,
  textarea::placeholder { color: var(--border); }
  textarea { min-height: 100px; resize: vertical; }
  select { appearance: none; cursor: pointer; }

  /* Submit button */
  .btn-submit {
    margin-top: 28px;
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 12px;
    background: var(--ink);
    color: #fff;
    font-family: 'Sarabun', sans-serif;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: .4px;
    cursor: pointer;
    transition: background .2s, transform .15s;
  }
  .btn-submit:hover {
    background: #2d2d4e;
    transform: translateY(-1px);
  }
  .btn-submit:active { transform: translateY(0); }

  @media (max-width: 640px) {
    .header  { padding: 24px 20px; }
    .content { padding: 24px 20px; }
    .form-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="wrapper">
  <a href="/Projate/booking_room.php?checkin=<?php echo urlencode($_GET['checkin'] ?? ''); ?>&checkout=<?php echo urlencode($_GET['checkout'] ?? ''); ?>&guests=<?php echo urlencode($_GET['guests'] ?? ''); ?>"
     class="back-link">
    &#8592; กลับไปหน้าห้องพัก
  </a>

  <div class="card">
    <div class="header">
      <h1>กรอกข้อมูลการจอง</h1>
      <p>กรุณากรอกข้อมูลให้ครบถ้วนเพื่อยืนยันการจองห้องพัก</p>
    </div>

    <div class="content">
      <div class="room-box">
        <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
        <p><strong>ประเภทห้อง:</strong> <?php echo htmlspecialchars($room['room_type']); ?></p>
        <p><strong>ราคา:</strong> ฿<?php echo number_format((float)$room['price']); ?> / คืน</p>
        <p><strong>รองรับ:</strong> <?php echo htmlspecialchars($room['capacity']); ?> คน</p>
      </div>

      <form action="save_booking.php" method="POST">
        <input type="hidden" name="room_id"    value="<?php echo htmlspecialchars($room['id']); ?>">
        <input type="hidden" name="room_name"  value="<?php echo htmlspecialchars($room['room_name']); ?>">
        <input type="hidden" name="room_price" value="<?php echo htmlspecialchars($room['price']); ?>">

        <div class="form-grid">
          <div class="form-group">
            <label>ชื่อผู้จอง</label>
            <input type="text" name="customer_name" placeholder="กรอกชื่อ-นามสกุล" required>
          </div>

          <div class="form-group">
            <label>เบอร์โทร</label>
            <input type="text" name="phone" placeholder="0XX-XXX-XXXX" required>
          </div>

          <div class="form-group">
            <label>อีเมล</label>
            <input type="email" name="email" placeholder="example@email.com">
          </div>

          <div class="form-group">
            <label>จำนวนผู้ใหญ่</label>
            <input type="number" name="adults" min="1" value="<?php echo (int)$guests; ?>" required>
          </div>

          <div class="form-group">
            <label>วันเช็คอิน</label>
            <input type="date" name="checkin_date" value="<?php echo htmlspecialchars($checkin); ?>" required>
          </div>

          <div class="form-group">
            <label>วันเช็คเอาท์</label>
            <input type="date" name="checkout_date" value="<?php echo htmlspecialchars($checkout); ?>" required>
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

        <button type="submit" class="btn-submit">ยืนยันการจอง</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>