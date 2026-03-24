<?php
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

$currentDate = date('Y-m-d');
$currentTime = date('H:i');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ลงทะเบียนกิจกรรม</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#f4f4f4;
    color:#222;
}

/* HERO */
.hero{
    min-height:420px;
    background:
        linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)),
        url('uploads/88.png') center/cover no-repeat;
    display:flex;
    align-items:center;
    padding:60px 10%;
}

.hero-content{
    max-width:700px;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-bottom:24px;
    padding:10px 18px;
    background:rgba(255,255,255,0.15);
    color:#fff;
    text-decoration:none;
    border:1px solid rgba(255,255,255,0.45);
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    backdrop-filter: blur(4px);
    transition:all 0.3s ease;
}

.back-btn:hover{
    background:#ffffff;
    color:#111;
    border-color:#ffffff;
    transform:translateY(-2px);
}

.hero h1{
    font-size:48px;
    line-height:1.2;
    margin-bottom:12px;
    color:#fff;
    font-weight:700;
}

.hero p{
    font-size:18px;
    color:#f1f1f1;
}

/* CONTENT */
.section{
    max-width:1200px;
    margin:-70px auto 60px;
    background:#fff;
    border-radius:24px;
    box-shadow:0 18px 50px rgba(0,0,0,0.08);
    display:flex;
    gap:60px;
    padding:60px;
    position:relative;
    z-index:2;
}

/* LEFT */
.left{
    width:40%;
    padding-top:10px;
}

.left h2{
    font-size:34px;
    margin-bottom:16px;
    color:#111;
    line-height:1.3;
}

.left p{
    color:#666;
    line-height:1.8;
    font-size:16px;
}

/* RIGHT */
.right{
    width:60%;
}

form{
    width:100%;
}

.form-group{
    margin-bottom:24px;
}

label{
    display:block;
    margin-bottom:8px;
    font-size:14px;
    font-weight:600;
    color:#555;
}

input, select{
    width:100%;
    height:50px;
    padding:0 14px;
    border:1px solid #d9d9d9;
    border-radius:12px;
    background:#fff;
    color:#222;
    font-size:15px;
    transition:all 0.25s ease;
}

input:focus, select:focus{
    outline:none;
    border-color:#638411;
    box-shadow:0 0 0 3px rgba(99,132,17,0.12);
}

/* BUTTON */
.submit-btn{
    background:#111;
    border:none;
    padding:14px 30px;
    color:white;
    font-size:15px;
    font-weight:600;
    border-radius:12px;
    cursor:pointer;
    margin-top:10px;
    transition:all 0.3s ease;
}

.submit-btn:hover{
    background:#638411;
    transform:translateY(-2px);
    box-shadow:0 10px 25px rgba(99,132,17,0.25);
}

/* RESPONSIVE */
@media (max-width: 992px){
    .section{
        flex-direction:column;
        gap:30px;
        margin:-40px 20px 40px;
        padding:35px 25px;
    }

    .left, .right{
        width:100%;
    }

    .hero{
        min-height:360px;
        padding:50px 20px 80px;
    }

    .hero h1{
        font-size:36px;
    }

    .left h2{
        font-size:28px;
    }
}

@media (max-width: 576px){
    .hero h1{
        font-size:28px;
    }

    .hero p{
        font-size:15px;
    }

    .back-btn{
        font-size:13px;
        padding:9px 14px;
    }

    .section{
        border-radius:18px;
    }
}
</style>
</head>

<body>

<div class="hero">
    <div class="hero-content">
        <a href="index.php" class="back-btn">← กลับหน้าหลัก</a>
        <h1>Start participating in activities</h1>
        <p>เริ่มต้นการเข้าร่วมกิจกรรม</p>
    </div>
</div>

<div class="section">

    <div class="left">
        <h2>ประสบการณ์พิเศษรอคุณอยู่</h2>
        <p>ลงทะเบียนเข้าร่วมกิจกรรมกับเรา เพื่อรับประสบการณ์ดี ๆ และเข้าร่วมกิจกรรมที่น่าสนใจได้อย่างสะดวก รวดเร็ว และเป็นระเบียบ</p>
    </div>

    <div class="right">
        <form action="save_tourist.php" method="POST">

            <div class="form-group">
                <label for="nickname">ชื่อเล่น *</label>
                <input type="text" id="nickname" name="nickname" required>
            </div>

            <div class="form-group">
                <label for="gender">เพศ *</label>
                <select id="gender" name="gender" required>
                    <option value="">เลือกเพศ</option>
                    <option value="ชาย">ชาย</option>
                    <option value="หญิง">หญิง</option>
                    <option value="อื่นๆ">อื่นๆ</option>
                </select>
            </div>

            <div class="form-group">
                <label for="age">อายุ</label>
                <input type="number" id="age" name="age" min="1">
            </div>

            <div class="form-group">
                <label for="visit_date">วันที่เข้าชม *</label>
                <input type="date" id="visit_date" name="visit_date" required value="<?php echo $currentDate; ?>">
            </div>

            <div class="form-group">
                <label for="visit_time">เวลาที่เข้าชม *</label>
                <input type="time" id="visit_time" name="visit_time" required value="<?php echo $currentTime; ?>">
            </div>

            <div class="form-group">
                <label for="user_type">สถานะ *</label>
                <select id="user_type" name="user_type" required>
                    <option value="">เลือกสถานะ</option>
                    <option value="นักศึกษา">นักศึกษา</option>
                    <option value="บุคลากร">บุคลากร</option>
                    <option value="นักท่องเที่ยว">นักท่องเที่ยว</option>
                </select>
            </div>

            <button type="submit" class="submit-btn">ยืนยันการลงทะเบียน</button>

        </form>
    </div>

</div>

</body>
</html>

<?php $conn->close(); ?>