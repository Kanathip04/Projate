<?php
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

$result = $conn->query("SELECT * FROM room_bookings ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายการจองห้องพัก</title>

<style>
body{font-family:sans-serif;background:#f4f4f4;padding:20px;}
table{width:100%;border-collapse:collapse;background:#fff;}
th,td{padding:12px;border:1px solid #ddd;text-align:left;}
th{background:#7a8f3b;color:#fff;}
</style>

</head>
<body>

<h2>รายการจองห้องพัก</h2>

<table>
<tr>
<th>ชื่อ</th>
<th>เบอร์</th>
<th>ห้อง</th>
<th>คน</th>
<th>เข้า</th>
<th>ออก</th>
<th>สถานะ</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= $row['full_name'] ?></td>
<td><?= $row['phone'] ?></td>
<td><?= $row['room_type'] ?></td>
<td><?= $row['guests'] ?></td>
<td><?= $row['checkin_date'] ?></td>
<td><?= $row['checkout_date'] ?></td>
<td><?= $row['booking_status'] ?></td>
</tr>
<?php endwhile; ?>

</table>

</body>
</html>