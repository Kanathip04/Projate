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

if (!$result || $result->num_rows === 0) {
    header("Location: booking_tent.php"); exit;
}
$tent = $result->fetch_assoc();
$stmt->close();

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

/* ดึง email/name จาก session ถ้ามี */
$prefillEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$prefillName  = $_SESSION['user_name'] ?? $_SESSION['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>กรอกข้อมูลการจองเต็นท์</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#1a1a2e;--gold:#c9a96e;--gold-light:#f5ead8;
    --bg:#f5f1eb;--card:#ffffff;--muted:#7a7a8c;--border:#e8e4de;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;padding:40px 16px;}
.wrapper{width:min(960px,100%);margin:0 auto;}
.back-link{display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;color:var(--ink);text-decoration:none;font-weight:600;font-size:14px;opacity:.75;transition:opacity .2s;}
.back-link:hover{opacity:1;}
.card{background:var(--card);border-radius:20px;box-shadow:0 8px 32px rgba(26,26,46,.10);overflow:hidden;}
.header{background:linear-gradient(135deg,#1a1a2e 0%,#2d2d4e 100%);color:#fff;padding:32px 36px;border-bottom:3px solid var(--gold);}
.header h1{font-size:26px;font-weight:700;margin-bottom:6px;}
.header p{font-size:14px;font-weight:300;opacity:.8;}
.content{padding:32px 36px;}
.tent-box{background:var(--gold-light);border:1px solid var(--gold);border-radius:14px;padding:20px 24px;margin-bottom:28px;}
.tent-box h3{font-size:18px;font-weight:700;color:var(--ink);margin-bottom:10px;}
.tent-box p{font-size:14px;color:var(--ink);margin-bottom:5px;opacity:.85;}
.tent-box p strong{opacity:1;}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
.form-group{display:flex;flex-direction:column;}
.form-group.full{grid-column:1/-1;}
label{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:7px;letter-spacing:.4px;text-transform:uppercase;}
input,textarea,select{font-family:'Sarabun',sans-serif;font-size:15px;color:var(--ink);background:#faf9f7;border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;outline:none;transition:border-color .2s,box-shadow .2s;}
input:focus,textarea:focus,select:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.18);background:#fff;}
textarea{min-height:100px;resize:vertical;}
select{appearance:none;cursor:pointer;}
.btn-submit{margin-top:28px;width:100%;padding:15px;border:none;border-radius:12px;background:var(--ink);color:#fff;font-family:'Sarabun',sans-serif;font-size:16px;font-weight:700;cursor:pointer;transition:background .2s,transform .15s;}
.btn-submit:hover{background:#2d2d4e;transform:translateY(-1px);}
@media(max-width:640px){
    .header{padding:24px 20px;}
    .content{padding:24px 20px;}
    .form-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="wrapper">
    <a href="booking_tent.php" class="back-link">← กลับไปหน้าเต็นท์</a>

    <div class="card">
        <div class="header">
            <h1>⛺ กรอกข้อมูลการจองเต็นท์</h1>
            <p>กรุณากรอกข้อมูลให้ครบถ้วนเพื่อยืนยันการจอง</p>
        </div>

        <div class="content">
            <div class="tent-box">
                <h3><?= htmlspecialchars($tent['tent_name']) ?></h3>
                <?php if (!empty($tent['tent_type'])): ?>
                    <p><strong>ประเภท:</strong> <?= htmlspecialchars($tent['tent_type']) ?></p>
                <?php endif; ?>
                <p><strong>ราคา:</strong> ฿<?= number_format((float)$tent['price_per_night']) ?> / คืน</p>
                <p><strong>รองรับ:</strong> <?= (int)$tent['capacity'] ?> คน</p>
            </div>

            <form action="save_tent_booking.php" method="POST">
                <input type="hidden" name="tent_id"   value="<?= htmlspecialchars($tent['id']) ?>">
                <input type="hidden" name="tent_name" value="<?= htmlspecialchars($tent['tent_name']) ?>">
                <input type="hidden" name="tent_price" value="<?= htmlspecialchars($tent['price_per_night']) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>ชื่อผู้จอง</label>
                        <input type="text" name="customer_name" placeholder="กรอกชื่อ-นามสกุล"
                               value="<?= htmlspecialchars($prefillName) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>เบอร์โทร</label>
                        <input type="text" name="phone" placeholder="0XX-XXX-XXXX" required>
                    </div>

                    <div class="form-group">
                        <label>อีเมล</label>
                        <input type="email" name="email" placeholder="example@email.com"
                               value="<?= htmlspecialchars($prefillEmail) ?>">
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

                <button type="submit" class="btn-submit">ยืนยันการจองเต็นท์</button>
            </form>
        </div>
    </div>
</div>

<script>
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
