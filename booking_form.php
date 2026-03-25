<?php
date_default_timezone_set('Asia/Bangkok');

$room_type = $_GET['room_type'] ?? '';
$price     = $_GET['price'] ?? 0;
$checkin   = $_GET['checkin'] ?? '';
$checkout  = $_GET['checkout'] ?? '';
$guests    = $_GET['guests'] ?? 1;

if ($room_type == '' || $checkin == '' || $checkout == '') {
    die("ข้อมูลการจองไม่ครบ");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>กรอกข้อมูลการจอง</title>
<style>
body{
    font-family:'Segoe UI',sans-serif;
    background:#f4f4f4;
    margin:0;
    padding:0;
    color:#222;
}
.container{
    max-width:900px;
    margin:40px auto;
    background:#fff;
    border-radius:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    padding:30px;
}
h2{
    margin-top:0;
    color:#2d2d2d;
}
.summary{
    background:#f8f8f0;
    border:1px solid #e5e5d8;
    border-radius:16px;
    padding:20px;
    margin-bottom:25px;
}
.summary p{
    margin:8px 0;
    font-size:16px;
}
form{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}
.form-group{
    display:flex;
    flex-direction:column;
}
.form-group.full{
    grid-column:1 / -1;
}
label{
    margin-bottom:8px;
    font-weight:600;
}
input, textarea{
    padding:14px;
    border:1px solid #ddd;
    border-radius:12px;
    font-size:15px;
}
textarea{
    min-height:120px;
    resize:vertical;
}
.actions{
    grid-column:1 / -1;
    display:flex;
    gap:12px;
    margin-top:10px;
}
.btn{
    display:inline-block;
    padding:14px 22px;
    border-radius:12px;
    text-decoration:none;
    border:none;
    cursor:pointer;
    font-size:16px;
    font-weight:700;
}
.btn-back{
    background:#ddd;
    color:#222;
}
.btn-save{
    background:#6b7f1a;
    color:#fff;
}
@media (max-width: 768px){
    .container{
        margin:20px;
        padding:20px;
    }
    form{
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>

<div class="container">
    <h2>ข้อมูลการจองห้องพัก</h2>

    <div class="summary">
        <p><strong>ประเภทห้อง:</strong> <?php echo htmlspecialchars($room_type); ?></p>
        <p><strong>ราคา:</strong> ฿<?php echo number_format((float)$price, 0); ?> / คืน</p>
        <p><strong>วันเช็คอิน:</strong> <?php echo htmlspecialchars($checkin); ?></p>
        <p><strong>วันเช็คเอาท์:</strong> <?php echo htmlspecialchars($checkout); ?></p>
        <p><strong>จำนวนผู้เข้าพัก:</strong> <?php echo htmlspecialchars($guests); ?> คน</p>
    </div>

    <form action="save_booking.php" method="POST">
        <input type="hidden" name="room_type" value="<?php echo htmlspecialchars($room_type); ?>">
        <input type="hidden" name="price" value="<?php echo htmlspecialchars($price); ?>">
        <input type="hidden" name="checkin" value="<?php echo htmlspecialchars($checkin); ?>">
        <input type="hidden" name="checkout" value="<?php echo htmlspecialchars($checkout); ?>">
        <input type="hidden" name="guests" value="<?php echo htmlspecialchars($guests); ?>">

        <div class="form-group">
            <label>ชื่อ-นามสกุล</label>
            <input type="text" name="full_name" required>
        </div>

        <div class="form-group">
            <label>เบอร์โทรศัพท์</label>
            <input type="text" name="phone" required>
        </div>

        <div class="form-group">
            <label>อีเมล</label>
            <input type="email" name="email">
        </div>

        <div class="form-group">
            <label>จำนวนผู้เข้าพัก</label>
            <input type="number" value="<?php echo htmlspecialchars($guests); ?>" disabled>
        </div>

        <div class="form-group full">
            <label>หมายเหตุเพิ่มเติม</label>
            <textarea name="note" placeholder="เช่น ขอเตียงเสริม, เข้าพักดึก, หรือข้อมูลอื่นๆ"></textarea>
        </div>

        <div class="actions">
            <a href="booking_room.php?checkin=<?php echo urlencode($checkin); ?>&checkout=<?php echo urlencode($checkout); ?>&guests=<?php echo urlencode($guests); ?>" class="btn btn-back">ย้อนกลับ</a>
            <button type="submit" class="btn btn-save">ยืนยันการจอง</button>
        </div>
    </form>
</div>

</body>
</html>