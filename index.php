<?php
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===============================
   HERO IMAGE (แบนเนอร์หน้าเว็บ)
================================ */
$heroImage = "uploads/newbanner.jpg";
$resHero = $conn->query("SELECT image_path FROM site_banners ORDER BY id DESC LIMIT 1");
if ($resHero && $resHero->num_rows > 0) {
    $p = $resHero->fetch_assoc()['image_path'];
    if (!empty($p)) {
        $heroImage = (strpos($p, 'uploads/') === false) ? 'uploads/' . $p : $p;
    }
}
$heroImage .= '?v=' . time();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สถาบันวิจัยวลัยรุกขเวช</title>

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#ffffff;
    color:#222;
}

/* ================= NAVBAR ================= */
.navbar{
    width:100%;
    background:#ffffff;
    padding:18px 60px;
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.nav-left{
    display:flex;
    align-items:center;
    gap:20px;
}

.nav-left img{
    height:120px;
}

.site-text h1{
    font-size:26px;
    font-weight:700;
}

.site-text span{
    font-size:16px;
}

.nav-center{
    display:flex;
    gap:45px;
    align-items:center;
    flex-wrap:wrap;
    justify-content:flex-end;
}

.nav-center a{
    text-decoration:none;
    color:#333;
    font-size:15px;
    font-weight:500;
    position:relative;
    transition:.3s;
}

.nav-center a::after{
    content:"";
    position:absolute;
    left:0;
    bottom:-6px;
    width:0%;
    height:2px;
    background:#000;
    transition:.3s;
}

.nav-center a:hover{
    color:#000;
}

.nav-center a:hover::after{
    width:100%;
}

/* ================= HERO ================= */
.hero{
    width:90%;
    max-width:1200px;
    height:420px;
    margin:40px auto;
    border-radius:18px;
    overflow:hidden;
    background:url('<?php echo htmlspecialchars($heroImage); ?>') center/cover no-repeat;
    position:relative;
    box-shadow:0 10px 30px rgba(0,0,0,0.15);
}

.hero::after{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.18);
}

/* ================= ABOUT SECTION ================= */
.about-section-home{
    padding:80px 20px;
    background:#ffffff;
}

.about-home-container{
    width:90%;
    max-width:1000px;
    margin:0 auto;
}

.section-title{
    text-align:center;
    margin-bottom:35px;
}

.section-title h2{
    font-size:40px;
    font-weight:700;
    color:#111;
    display:inline-block;
    position:relative;
    padding-bottom:18px;
}

.section-title h2::after{
    content:"";
    position:absolute;
    left:50%;
    bottom:0;
    transform:translateX(-50%);
    width:70px;
    height:4px;
    background:#b88a44;
    border-radius:999px;
}

/* กล่องทั้งหมดใช้ตัวนี้ร่วมกัน */
.info-card{
    background:#fff;
    border:1px solid #ececec;
    border-radius:18px;
    padding:30px 28px;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
    transition:0.3s ease;
    text-align:center;
}

.info-card:hover{
    transform:translateY(-4px);
    box-shadow:0 16px 30px rgba(0,0,0,0.08);
}

/* รูปทั้งหมดใช้ตัวนี้ร่วมกัน */
.section-image{
    width:100%;
    max-width:620px;   /* แก้ตรงนี้ครั้งเดียว รูปทุกใบเปลี่ยนหมด */
    height:400px;      /* แก้ตรงนี้ครั้งเดียว รูปทุกใบเปลี่ยนหมด */
    object-fit:cover;
    display:block;
    margin:18px auto 20px;
    border-radius:14px;
    box-shadow:0 8px 18px rgba(0,0,0,0.10);
}

.info-card h3{
    font-size:24px;
    font-weight:700;
    color:#1f1f1f;
    margin-bottom:14px;
}

.info-card p{
    font-size:17px;
    line-height:1.9;
    color:#5a5a5a;
}

.about-main-card{
    max-width:900px;
    margin:0 auto 30px;
}

.about-home-grid{
    display:grid;
    grid-template-columns:repeat(2, 1fr);
    gap:28px;
    max-width:900px;
    margin:0 auto;
}

/* ================= FOOTER ================= */
.site-footer{
    background:#ffffff;
    color:#000000;
    margin-top:20px;
}

.footer-container{
    max-width:1000px;
    margin:0 auto;
    padding:60px 40px 40px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:120px;
    align-items:start;
}

.footer-column h2{
    font-size:34px;
    font-weight:700;
    margin-bottom:18px;
    position:relative;
    line-height:1.2;
    text-align:left;
}

.footer-column h2::after{
    content:"";
    display:block;
    width:100%;
    max-width:230px;
    height:1px;
    background:rgba(0,0,0,0.12);
    margin-top:12px;
}

.footer-column p{
    font-size:18px;
    line-height:1.9;
    margin-top:18px;
    text-align:left;
}

.footer-column ul{
    list-style:none;
    padding:0;
    margin-top:18px;
    text-align:left;
}

.footer-column ul li{
    margin-bottom:14px;
    font-size:18px;
}

/* ================= RESPONSIVE ================= */
@media (max-width: 992px){
    .navbar{
        padding:18px 20px;
        flex-direction:column;
        gap:20px;
    }

    .nav-left{
        flex-direction:column;
        text-align:center;
    }

    .nav-left img{
        height:90px;
    }

    .nav-center{
        justify-content:center;
        gap:20px;
    }

    .hero{
        height:260px;
        width:94%;
    }

    .about-section-home{
        padding:60px 16px;
    }

    .section-title h2{
        font-size:30px;
    }

    .info-card{
        padding:22px 18px;
    }

    .info-card h3{
        font-size:21px;
    }

    .info-card p{
        font-size:16px;
    }

    .section-image{
        max-width:240px;
        height:160px;
    }

    .footer-container{
        grid-template-columns:1fr;
        gap:35px;
    }

    .footer-column h2{
        font-size:28px;
    }

    .footer-column p,
    .footer-column ul li{
        font-size:16px;
    }
}
</style>
</head>

<body>

<div class="navbar">
    <div class="nav-left">
        <img src="Logo.png" alt="WRBRI Logo">
        <div class="site-text">
            <h1>สถาบันวิจัยวลัยรุกขเวช</h1>
            <span>มหาวิทยาลัยมหาสารคาม</span>
        </div>
    </div>

    <div class="nav-center">
        <a href="news.php">ข่าวสาร</a>
        <a href="view_data.php">ลงทะเบียน</a>
        <a href="https://docs.google.com/forms/d/e/1FAIpQLSdukofM-5EFzR1Zddip7uZJ-pnBmLnhXCNyABKIEY8cwUxzyQ/viewform?usp=dialog" target="_blank">แบบประเมิน</a>
        <a href="calendar.html">ปฏิทิน</a>
        <a href="about_us.php">เกี่ยวกับ</a>
        <a href="game.php">เกม</a>
    </div>
</div>

<section class="hero"></section>

<section class="about-section-home">
    <div class="about-home-container">

        <div class="section-title">
            <h2>เกี่ยวกับสถาบันวิจัยวลัยรุกขเวช</h2>
        </div>

        <div class="info-card about-main-card">
            <img src="ka0.jpg" class="section-image" alt="กิจกรรม">
            <p>
                สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม เป็นหน่วยงานที่มุ่งเน้น
                การส่งเสริมการเรียนรู้ การวิจัย และการบริการวิชาการแก่ชุมชน
                โดยเฉพาะในด้านทรัพยากรธรรมชาติ ความหลากหลายทางชีวภาพ
                และภูมิปัญญาท้องถิ่น เพื่อสร้างองค์ความรู้ที่เป็นประโยชน์ต่อสังคม
                และการพัฒนาท้องถิ่นอย่างยั่งยืน
            </p>
        </div>

        <div class="about-home-grid">
            <div class="info-card">
                <h3>งานประชาสัมพันธ์และกิจกรรม</h3>
                <img src="ka1.jpg" class="section-image" alt="กิจกรรม">
                <p>
                    เผยแพร่ข่าวสาร กิจกรรม การอบรม สัมมนา และโครงการต่าง ๆ
                    ของสถาบัน เพื่อให้บุคลากร นักศึกษา และประชาชนทั่วไป
                    เข้าถึงข้อมูลที่สำคัญได้อย่างสะดวก
                </p>
            </div>

            <div class="info-card">
                <h3>งานวิจัยและองค์ความรู้</h3>
                <img src="ka2.jpg" class="section-image" alt="กิจกรรม">
                <p>
                    สนับสนุนและเผยแพร่งานวิจัยด้านธรรมชาติ พืชสมุนไพร
                    สิ่งแวดล้อม และความหลากหลายทางชีวภาพ
                    เพื่อสร้างคุณค่าเชิงวิชาการและประโยชน์ต่อชุมชน
                </p>
            </div>

            <div class="info-card">
                <h3>การบริการวิชาการแก่สังคม</h3>
                <img src="ka3.jpg" class="section-image" alt="กิจกรรม">
                <p>
                    นำองค์ความรู้จากงานวิจัยไปต่อยอดสู่การบริการวิชาการ
                    และกิจกรรมที่เชื่อมโยงกับชุมชน โรงเรียน หน่วยงานภาครัฐ
                    และประชาชนในพื้นที่
                </p>
            </div>

            <div class="info-card">
                <h3>การสร้างภาพลักษณ์องค์กร</h3>
                <img src="ka4.jpg" class="section-image" alt="กิจกรรม">
                <p>
                    นำเสนอผลงาน ความร่วมมือ และกิจกรรมสำคัญของสถาบัน
                    เพื่อสะท้อนบทบาทขององค์กรในฐานะแหล่งเรียนรู้
                    และศูนย์กลางด้านการวิจัยของมหาวิทยาลัยมหาสารคาม
                </p>
            </div>
        </div>
    </div>
</section>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-column">
            <h2>เกี่ยวกับเรา</h2>
            <p>
                สถาบันวิจัยดาราศาสตร์และเทคโนโลยี มุ่งเน้นการส่งเสริมการเรียนรู้
                การวิจัย และการจัดกิจกรรมเพื่อพัฒนาศักยภาพด้านวิทยาศาสตร์
                เทคโนโลยี และการบริการวิชาการแก่ชุมชนและสังคม
            </p>
        </div>

        <div class="footer-column">
            <h2>ติดต่อเรา</h2>
            <ul class="footer-contact">
                <li>อีเมล: walairukhavej@msu.ac.th</li>
                <li>โทร: 043 719 816</li>
                <li>Facebook: สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม </li>
                <li>ที่อยู่: Kantharawichai, Thailand, 44150</li>
            </ul>
        </div>
    </div>
</footer>

</body>
</html>