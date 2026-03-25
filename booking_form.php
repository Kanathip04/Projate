<?php
date_default_timezone_set('Asia/Bangkok');

$room_id    = $_GET['room_id'] ?? '';
$room_name  = $_GET['room_name'] ?? '';
$room_price = $_GET['room_price'] ?? '';

if ($room_id == '' || $room_name == '' || $room_price == '') {
    die("ไม่พบข้อมูลห้องพัก");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>กรอกข้อมูลการจองห้องพัก</title>
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}
body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#f4f7fb;
    color:#222;
}
.wrapper{
    width:min(900px, 92%);
    margin:40px auto;
}
.topbar{
    margin-bottom:20px;
}
.back-link{
    text-decoration:none;
    color:#1d3557;
    font-weight:600;
}
.form-card{
    background:#fff;
    border-radius:22px;
    box-shadow:0 12px 30px rgba(0,0,0,.08);
    overflow:hidden;
    border:1px solid #ebeff5;
}
.form-header{
    background:linear-gradient(135deg,#1d3557,#457b9d);
    color:#fff;
    padding:24px;
}
.form-header h1{
    font-size:30px;
    margin-bottom:8px;
}
.form-header p{
    opacity:.95;
}
.room-summary{
    background:#f8fbff;
    border:1px solid #dbe9f6;
    margin:24px;
    padding:18px;
    border-radius:16px;
}
.room-summary h3{
    color:#1d3557;
    margin-bottom:10px;
}
.room-summary .price{
    font-size:24px;
    font-weight:800;
    color:#e63946;
}
.form-body{
    padding:0 24px 24px;
}
.form-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
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
    font-weight:700;
    margin-bottom:8px;
    color:#1f2937;
}
input, textarea, select{
    width:100%;
    padding:13px 14px;
    border:1px solid #d8dee8;
    border-radius:12px;
    font-size:15px;
    outline:none;
    background:#fff;
}
input:focus, textarea:focus, select:focus{
    border-color:#457b9d;
    box-shadow:0 0 0 3px rgba(69,123,157,.12);
}
textarea{
    min-height:110px;
    resize:vertical;
}
.submit-btn{
    margin-top:22px;
    width:100%;
    border:none;
    background:linear-gradient(135deg,#2a9d8f,#21867a);
    color:#fff;
    font-size:17px;
    font-weight:700;
    padding:15px;
    border-radius:14px;
    cursor:pointer;
    transition:.2s ease;
}
.submit-btn:hover{
    opacity:.95;
    transform:translateY(-1px);
}
.note{
    margin-top:12px;
    font-size:13px;
    color:#666;
}
@media (max-width:768px){
    .form-grid{
        grid-template-columns:1fr;
    }
    .form-header h1{
        font-size:24px;
    }
    .room-summary{
        margin:18px;
    }
    .form-body{
        padding:0 18px 18px;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="topbar">
        <a href="rooms.php" class="back-link">← กลับไปหน้าห้องพัก</a>
    </div>

    <div class="form-card">
        <div class="form-header">
            <h1>กรอกข้อมูลการจอง</h1>
            <p>กรุณากรอกข้อมูลให้ครบถ้วนเพื่อส่งคำขอจองห้องพัก</p>
        </div>

        <div class="room-summary">
            <h3>ห้องที่เลือก</h3>
            <p><strong>ชื่อห้อง:</strong> <?php echo htmlspecialchars($room_name); ?></p>
            <p class="price">฿<?php echo number_format((float)$room_price); ?> / คืน</p>
        </div>

        <div class="form-body">
            <form action="save_booking.php" method="POST">
                <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">
                <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room_name); ?>">
                <input type="hidden" name="room_price" value="<?php echo htmlspecialchars($room_price); ?>">

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
                        <input type="number" name="adults" min="1" value="1" required>
                    </div>

                    <div class="form-group">
                        <label>วันเช็คอิน</label>
                        <input type="date" name="checkin_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>วันเช็คเอาท์</label>
                        <input type="date" name="checkout_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>

                    <div class="form-group">
                        <label>จำนวนเด็ก</label>
                        <input type="number" name="children" min="0" value="0">
                    </div>

                    <div class="form-group full">
                        <label>หมายเหตุเพิ่มเติม</label>
                        <textarea name="note" placeholder="เช่น ต้องการเตียงเสริม, เช็คอินดึก, หรือข้อมูลอื่นๆ"></textarea>
                    </div>
                </div>

                <button type="submit" class="submit-btn">ยืนยันการจอง</button>
                <div class="note">เมื่อกดบันทึกแล้ว ข้อมูลจะถูกส่งไปเก็บในระบบหลังบ้าน</div>
            </form>
        </div>
    </div>
</div>

</body>
</html>