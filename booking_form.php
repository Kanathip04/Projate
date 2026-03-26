<?php
session_start();
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
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Tahoma,sans-serif;background:#f4f7fb;color:#222}
.wrapper{width:min(1000px,92%);margin:40px auto}
.card{background:#fff;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
.header{background:linear-gradient(135deg,#6f8428,#58691f);color:#fff;padding:24px}
.header h1{font-size:28px;margin-bottom:8px}
.content{padding:24px}
.room-box{background:#f8fafc;border:1px solid #e5e7eb;border-radius:16px;padding:18px;margin-bottom:20px}
.room-box h3{margin-bottom:8px}
.room-box p{margin-bottom:6px}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
.form-group{display:flex;flex-direction:column}
.form-group.full{grid-column:1 / -1}
label{font-weight:700;margin-bottom:8px}
input,textarea,select{padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;font-size:15px}
textarea{min-height:100px;resize:vertical}
button{margin-top:20px;width:100%;padding:14px;border:none;border-radius:12px;background:#6f8428;color:#fff;font-size:16px;font-weight:700;cursor:pointer}
.back-link{display:inline-block;margin-bottom:16px;color:#58691f;text-decoration:none;font-weight:700}
@media (max-width:768px){.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrapper">
<a href="/Projate/booking_room.php?checkin=<?php echo urlencode($_GET['checkin'] ?? ''); ?>&checkout=<?php echo urlencode($_GET['checkout'] ?? ''); ?>&guests=<?php echo urlencode($_GET['guests'] ?? ''); ?>"
   class="back-link">
   ← กลับไปหน้าห้องพัก
</a>
    <div class="card">
        <div class="header">
            <h1>กรอกข้อมูลการจอง</h1>
            <p>กรุณากรอกข้อมูลให้ครบถ้วน</p>
        </div>

        <div class="content">
            <div class="room-box">
                <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
                <p><strong>ประเภทห้อง:</strong> <?php echo htmlspecialchars($room['room_type']); ?></p>
                <p><strong>ราคา:</strong> ฿<?php echo number_format((float)$room['price']); ?> / คืน</p>
                <p><strong>รองรับ:</strong> <?php echo htmlspecialchars($room['capacity']); ?> คน</p>
            </div>

            <form action="save_booking.php" method="POST">
                <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room['id']); ?>">
                <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room['room_name']); ?>">
                <input type="hidden" name="room_price" value="<?php echo htmlspecialchars($room['price']); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>ชื่อผู้จอง</label>
                        <input type="text" name="customer_name" required>
                    </div>

                    <div class="form-group">
                        <label>เบอร์โทร</label>
                        <input type="text" name="phone" required>
                    </div>

                    <div class="form-group">
                        <label>อีเมล</label>
                        <input type="email" name="email">
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
                        <textarea name="note"></textarea>
                    </div>
                </div>

                <button type="submit">ยืนยันการจอง</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>