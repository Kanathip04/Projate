<?php
date_default_timezone_set('Asia/Bangkok');

/* =========================
   เชื่อมต่อฐานข้อมูล
========================= */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =========================
   รับค่าจาก URL
========================= */
$room_id  = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$checkin  = trim($_GET['checkin'] ?? '');
$checkout = trim($_GET['checkout'] ?? '');
$guests   = trim($_GET['guests'] ?? '1');

if ($room_id <= 0) {
    die("ไม่พบข้อมูลห้องพัก");
}

/* =========================
   ดึงข้อมูลห้องจากฐานข้อมูล
========================= */
$stmt = $conn->prepare("SELECT id, room_name, room_type, price, room_size, bed_type, capacity, image, description 
                        FROM rooms 
                        WHERE id = ? AND status = 1 
                        LIMIT 1");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("ไม่พบข้อมูลห้องพัก");
}

$room = $result->fetch_assoc();

/* =========================
   ตั้งค่าค่าเริ่มต้นให้ฟอร์ม
========================= */
if ($guests === '' || !is_numeric($guests) || (int)$guests < 1) {
    $guests = 1;
}

if ($checkin === '') {
    $checkin = date('Y-m-d');
}

if ($checkout === '') {
    $checkout = date('Y-m-d', strtotime('+1 day'));
}

/* ป้องกัน checkout น้อยกว่าหรือเท่ากับ checkin */
if ($checkout <= $checkin) {
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
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}
:root{
    --brand:#6f8428;
    --brand-dark:#58691f;
    --brand-light:#f4f8ea;
    --text:#1f2937;
    --muted:#6b7280;
    --line:#e5e7eb;
    --white:#ffffff;
    --bg:#f8fafc;
    --shadow:0 12px 35px rgba(0,0,0,.08);
}
body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#f4f7fb;
    color:#222;
}
.wrapper{
    width:min(1100px, 92%);
    margin:40px auto;
}
.topbar{
    margin-bottom:20px;
}
.back-link{
    text-decoration:none;
    color:var(--brand-dark);
    font-weight:700;
}
.main-grid{
    display:grid;
    grid-template-columns:380px 1fr;
    gap:24px;
}
.info-card,
.form-card{
    background:#fff;
    border-radius:24px;
    box-shadow:var(--shadow);
    overflow:hidden;
    border:1px solid #ebeff5;
}
.info-card{
    align-self:start;
}
.room-image{
    width:100%;
    height:260px;
    object-fit:cover;
    display:block;
    background:#ddd;
}
.info-body{
    padding:22px;
}
.room-type{
    display:inline-block;
    padding:8px 14px;
    border-radius:999px;
    background:var(--brand-light);
    color:var(--brand-dark);
    font-size:13px;
    font-weight:700;
    margin-bottom:12px;
}
.room-title{
    font-size:28px;
    font-weight:800;
    color:#111827;
    margin-bottom:10px;
}
.room-price{
    font-size:30px;
    font-weight:900;
    color:#b42318;
    margin-bottom:18px;
}
.room-price span{
    font-size:14px;
    color:#6b7280;
    font-weight:500;
}
.info-list{
    display:grid;
    gap:10px;
    margin-bottom:16px;
}
.info-item{
    padding:12px 14px;
    border:1px solid #e8edf3;
    border-radius:14px;
    background:#f8fafc;
    font-size:14px;
    color:#374151;
}
.info-item strong{
    color:#111827;
}
.room-desc{
    color:#4b5563;
    line-height:1.7;
    font-size:15px;
}
.form-header{
    background:linear-gradient(135deg,var(--brand-dark),var(--brand));
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
.form-body{
    padding:24px;
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
    border-color:var(--brand);
    box-shadow:0 0 0 3px rgba(111,132,40,.12);
}
textarea{
    min-height:110px;
    resize:vertical;
}
.submit-btn{
    margin-top:22px;
    width:100%;
    border:none;
    background:linear-gradient(135deg,var(--brand),var(--brand-dark));
    color:#fff;
    font-size:17px;
    font-weight:700;
    padding:15px;
    border-radius:14px;
    cursor:pointer;
    transition:.2s ease;
}
.submit-btn:hover{
    opacity:.96;
    transform:translateY(-1px);
}
.note{
    margin-top:12px;
    font-size:13px;
    color:#666;
    line-height:1.6;
}
@media (max-width: 900px){
    .main-grid{
        grid-template-columns:1fr;
    }
}
@media (max-width: 768px){
    .form-grid{
        grid-template-columns:1fr;
    }
    .form-header h1{
        font-size:24px;
    }
    .form-body{
        padding:18px;
    }
    .info-body{
        padding:18px;
    }
}
</style>
</head>
<body>

<div class="wrapper">

    <div class="topbar">
        <a href="rooms.php?checkin=<?php echo urlencode($checkin); ?>&checkout=<?php echo urlencode($checkout); ?>&guests=<?php echo urlencode($guests); ?>" class="back-link">
            ← กลับไปหน้าห้องพัก
        </a>
    </div>

    <div class="main-grid">

        <div class="info-card">
            <img src="<?php echo htmlspecialchars($roomImage); ?>" 
                 alt="<?php echo htmlspecialchars($room['room_name']); ?>" 
                 class="room-image"
                 onerror="this.src='uploads/no-image.png'">

            <div class="info-body">
                <div class="room-type"><?php echo htmlspecialchars($room['room_type']); ?></div>
                <div class="room-title"><?php echo htmlspecialchars($room['room_name']); ?></div>
                <div class="room-price">
                    ฿<?php echo number_format((float)$room['price']); ?>
                    <span>/ คืน</span>
                </div>

                <div class="info-list">
                    <div class="info-item"><strong>ขนาดห้อง:</strong> <?php echo htmlspecialchars($room['room_size']); ?></div>
                    <div class="info-item"><strong>ประเภทเตียง:</strong> <?php echo htmlspecialchars($room['bed_type']); ?></div>
                    <div class="info-item"><strong>รองรับสูงสุด:</strong> <?php echo htmlspecialchars($room['capacity']); ?> คน</div>
                </div>

                <div class="room-desc">
                    <?php echo nl2br(htmlspecialchars($room['description'])); ?>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-header">
                <h1>กรอกข้อมูลการจอง</h1>
                <p>กรุณากรอกข้อมูลให้ครบถ้วนเพื่อส่งคำขอจองห้องพักเข้าสู่ระบบหลังบ้าน</p>
            </div>

            <div class="form-body">
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
                            <label>จำนวนผู้เข้าพัก</label>
                            <input type="number" name="adults" min="1" value="<?php echo (int)$guests; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>วันเช็คอิน</label>
                            <input type="date" name="checkin_date" required value="<?php echo htmlspecialchars($checkin); ?>" min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label>วันเช็คเอาท์</label>
                            <input type="date" name="checkout_date" required value="<?php echo htmlspecialchars($checkout); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
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
                            <textarea name="note" placeholder="เช่น ต้องการเตียงเสริม, เช็คอินดึก, หรือข้อมูลอื่นๆ"></textarea>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">ยืนยันการจอง</button>
                    <div class="note">
                        เมื่อกดบันทึกแล้ว ข้อมูลจะถูกส่งไปที่ไฟล์ <strong>save_booking.php</strong> เพื่อบันทึกลงฐานข้อมูลและแสดงในระบบหลังบ้าน
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

</body>
</html>