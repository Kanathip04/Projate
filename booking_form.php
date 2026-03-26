<?php
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

$roomImage = !empty($room['image']) ? $room['image'] : 'uploads/no-image.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>กรอกข้อมูลการจองห้องพัก</title>
</head>
<body>
    <h2>ห้องที่เลือก: <?php echo htmlspecialchars($room['room_name']); ?></h2>
    <p>ราคา: <?php echo number_format((float)$room['price']); ?> บาท / คืน</p>

    <form action="save_booking.php" method="POST">
        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
        <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room['room_name']); ?>">
        <input type="hidden" name="room_price" value="<?php echo htmlspecialchars($room['price']); ?>">

        <label>ชื่อผู้จอง</label>
        <input type="text" name="customer_name" required>

        <label>เบอร์โทร</label>
        <input type="text" name="phone" required>

        <label>อีเมล</label>
        <input type="email" name="email">

        <label>จำนวนผู้ใหญ่</label>
        <input type="number" name="adults" min="1" value="<?php echo (int)$guests; ?>" required>

        <label>วันเช็คอิน</label>
        <input type="date" name="checkin_date" value="<?php echo htmlspecialchars($checkin); ?>" required>

        <label>วันเช็คเอาท์</label>
        <input type="date" name="checkout_date" value="<?php echo htmlspecialchars($checkout); ?>" required>

        <label>จำนวนเด็ก</label>
        <input type="number" name="children" min="0" value="0">

        <label>หมายเหตุ</label>
        <textarea name="note"></textarea>

        <button type="submit">ยืนยันการจอง</button>
    </form>
</body>
</html>