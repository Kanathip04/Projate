<?php
date_default_timezone_set('Asia/Bangkok');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>

<style>
:root{
    --brand:#7a8f3b;
    --brand-dark:#5f7229;
    --text:#222;
    --muted:#666;
    --bg:#f7f7f5;
    --white:#ffffff;
    --border:#e8e8e8;
    --shadow:0 10px 30px rgba(0,0,0,0.08);
    --radius:18px;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family: "Segoe UI", Tahoma, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height:1.6;
}

a{
    text-decoration:none;
    color:inherit;
}

/* =========================
   HEADER
========================= */
.site-header{
    width:100%;
    background:#fff;
    border-bottom:1px solid #eee;
    position:sticky;
    top:0;
    z-index:1000;
}

.nav-wrap{
    max-width:1280px;
    margin:auto;
    padding:18px 28px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
}

.brand{
    display:flex;
    align-items:center;
    gap:18px;
    min-width:0;
}

.brand img{
    width:88px;
    height:88px;
    object-fit:contain;
}

.brand-text{
    line-height:1.25;
}

.brand-text h1{
    font-size:20px;
    font-weight:800;
    color:#1d1d1d;
}

.brand-text p{
    font-size:14px;
    color:#444;
    font-weight:600;
}

.nav-menu{
    display:flex;
    align-items:center;
    gap:36px;
    flex-wrap:wrap;
}

.nav-menu a{
    font-size:15px;
    font-weight:700;
    color:#333;
    transition:0.2s ease;
    position:relative;
}

.nav-menu a:hover,
.nav-menu a.active{
    color:var(--brand-dark);
}

.nav-menu a.active::after{
    content:"";
    position:absolute;
    left:0;
    bottom:-8px;
    width:100%;
    height:3px;
    border-radius:20px;
    background:var(--brand);
}

/* =========================
   HERO
========================= */
.hero{
    max-width:1280px;
    margin:34px auto 24px;
    padding:0 28px;
}

.hero-box{
    background:
        linear-gradient(rgba(0,0,0,0.38), rgba(0,0,0,0.35)),
        url('uploads/room-banner.jpg') center/cover no-repeat;
    border-radius:28px;
    min-height:360px;
    display:flex;
    align-items:center;
    padding:46px;
    box-shadow: var(--shadow);
    overflow:hidden;
}

.hero-content{
    max-width:650px;
    color:#fff;
}

.hero-badge{
    display:inline-block;
    background:rgba(255,255,255,0.18);
    backdrop-filter: blur(8px);
    border:1px solid rgba(255,255,255,0.25);
    color:#fff;
    padding:8px 16px;
    border-radius:999px;
    font-size:14px;
    margin-bottom:18px;
    font-weight:700;
}

.hero-content h2{
    font-size:40px;
    line-height:1.2;
    margin-bottom:14px;
    font-weight:800;
}

.hero-content p{
    font-size:16px;
    line-height:1.7;
    color:rgba(255,255,255,0.94);
}

/* =========================
   SEARCH BOX
========================= */
.booking-search{
    max-width:1280px;
    margin:0 auto 28px;
    padding:0 28px;
}

.search-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:24px;
    padding:24px;
    box-shadow: var(--shadow);
    margin-top:-58px;
    position:relative;
    z-index:10;
}

.search-card h3{
    font-size:24px;
    margin-bottom:18px;
    color:#222;
}

.form-grid{
    display:grid;
    grid-template-columns: repeat(5, 1fr);
    gap:16px;
    align-items:end;
}

.form-group{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.form-group label{
    font-size:14px;
    font-weight:700;
    color:#444;
}

.form-group input,
.form-group select{
    width:100%;
    height:50px;
    border:1px solid #ddd;
    border-radius:14px;
    padding:0 14px;
    font-size:15px;
    outline:none;
    transition:0.2s ease;
    background:#fff;
}

.form-group input:focus,
.form-group select:focus{
    border-color:var(--brand);
    box-shadow:0 0 0 4px rgba(122,143,59,0.12);
}

.search-btn{
    width:100%;
    height:50px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg, var(--brand), var(--brand-dark));
    color:#fff;
    font-size:15px;
    font-weight:800;
    cursor:pointer;
    transition:0.2s ease;
}

.search-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 20px rgba(95,114,41,0.22);
}

/* =========================
   SECTION
========================= */
.section{
    max-width:1280px;
    margin:0 auto;
    padding:10px 28px 50px;
}

.section-head{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:20px;
    margin-bottom:24px;
    flex-wrap:wrap;
}

.section-head h3{
    font-size:30px;
    color:#1f1f1f;
}

.section-head p{
    color:var(--muted);
    font-size:15px;
    max-width:700px;
}

/* =========================
   ROOM CARDS
========================= */
.rooms-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:24px;
}

.room-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:24px;
    overflow:hidden;
    box-shadow: var(--shadow);
    transition:0.25s ease;
    display:flex;
    flex-direction:column;
}

.room-card:hover{
    transform:translateY(-4px);
}

.room-image{
    width:100%;
    height:230px;
    object-fit:cover;
    display:block;
}

.room-body{
    padding:22px;
    display:flex;
    flex-direction:column;
    gap:14px;
    flex:1;
}

.room-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
}

.room-title{
    font-size:22px;
    font-weight:800;
    color:#222;
}

.room-price{
    font-size:22px;
    font-weight:800;
    color:var(--brand-dark);
    white-space:nowrap;
}

.room-price span{
    font-size:13px;
    color:#666;
    font-weight:600;
}

.room-desc{
    color:#666;
    font-size:15px;
    min-height:48px;
}

.room-features{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}

.feature{
    padding:8px 12px;
    border-radius:999px;
    background:#f4f7ea;
    color:var(--brand-dark);
    font-size:13px;
    font-weight:700;
}

.room-footer{
    margin-top:auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.room-capacity{
    color:#444;
    font-size:14px;
    font-weight:600;
}

.book-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:12px 22px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg, var(--brand), var(--brand-dark));
    color:#fff;
    font-size:14px;
    font-weight:800;
    cursor:pointer;
    transition:0.2s ease;
}

.book-btn:hover{
    transform:translateY(-2px);
}

/* =========================
   INFO
========================= */
.info-box{
    margin-top:36px;
    background:#fff;
    border:1px solid var(--border);
    border-radius:24px;
    padding:24px;
    box-shadow: var(--shadow);
}

.info-box h4{
    font-size:22px;
    margin-bottom:12px;
}

.info-box ul{
    padding-left:18px;
    color:#555;
}

.info-box li{
    margin-bottom:8px;
}

/* =========================
   FOOTER
========================= */
.footer{
    margin-top:40px;
    background:#fff;
    border-top:1px solid #eee;
}

.footer-inner{
    max-width:1280px;
    margin:auto;
    padding:20px 28px;
    text-align:center;
    color:#666;
    font-size:14px;
}

/* =========================
   MOBILE
========================= */
@media (max-width: 1100px){
    .form-grid{
        grid-template-columns: repeat(2, 1fr);
    }

    .rooms-grid{
        grid-template-columns:repeat(2, 1fr);
    }
}

@media (max-width: 860px){
    .nav-wrap{
        flex-direction:column;
        align-items:flex-start;
    }

    .nav-menu{
        gap:20px;
    }

    .hero-box{
        min-height:300px;
        padding:28px;
    }

    .hero-content h2{
        font-size:30px;
    }

    .search-card{
        margin-top:20px;
    }
}

@media (max-width: 640px){
    .brand{
        align-items:flex-start;
    }

    .brand img{
        width:70px;
        height:70px;
    }

    .brand-text h1{
        font-size:18px;
    }

    .nav-menu{
        gap:14px 18px;
    }

    .hero{
        margin-top:20px;
    }

    .hero-box{
        border-radius:22px;
        min-height:250px;
        padding:22px;
    }

    .hero-content h2{
        font-size:25px;
    }

    .hero-content p{
        font-size:14px;
    }

    .form-grid{
        grid-template-columns:1fr;
    }

    .rooms-grid{
        grid-template-columns:1fr;
    }

    .section-head h3{
        font-size:24px;
    }

    .room-title,
    .room-price{
        font-size:20px;
    }
}
</style>
</head>
<body>

<header class="site-header">
    <div class="nav-wrap">
        <div class="brand">
            <img src="uploads/logo.png" alt="โลโก้สถาบัน">
            <div class="brand-text">
                <h1>สถาบันวิจัยวลัยรุกขเวช</h1>
                <p>มหาวิทยาลัยมหาสารคาม</p>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="news.php">ข่าวสาร</a>
            <a href="register.php">ลงทะเบียน</a>
            <a href="evaluation.php">แบบประเมิน</a>
            <a href="calendar.php">ปฏิทิน</a>
            <a href="about.php">เกี่ยวกับ</a>
            <a href="booking_room.php" class="active">จองห้องพัก</a>
        </nav>
    </div>
</header>

<section class="hero">
    <div class="hero-box">
        <div class="hero-content">
            <div class="hero-badge">Room Reservation</div>
            <h2>ระบบจองห้องพักและที่พักภายในสถาบัน</h2>
            <p>
                เลือกประเภทห้องพัก วันที่เข้าพัก และจำนวนผู้เข้าพักได้จากหน้านี้
                เพื่ออำนวยความสะดวกในการเข้าพักสำหรับผู้มาติดต่อราชการ นักวิจัย
                ผู้เข้าร่วมกิจกรรม และผู้ใช้งานทั่วไป
            </p>
        </div>
    </div>
</section>

<section class="booking-search">
    <div class="search-card">
        <h3>ค้นหาห้องพักที่ว่าง</h3>

        <form action="" method="GET">
            <div class="form-grid">
                <div class="form-group">
                    <label for="checkin">วันที่เข้าพัก</label>
                    <input type="date" id="checkin" name="checkin" required>
                </div>

                <div class="form-group">
                    <label for="checkout">วันที่ออก</label>
                    <input type="date" id="checkout" name="checkout" required>
                </div>

                <div class="form-group">
                    <label for="guests">จำนวนผู้เข้าพัก</label>
                    <select id="guests" name="guests" required>
                        <option value="">เลือกจำนวน</option>
                        <option value="1">1 คน</option>
                        <option value="2">2 คน</option>
                        <option value="3">3 คน</option>
                        <option value="4">4 คน</option>
                        <option value="5">5 คน</option>
                        <option value="6">6 คน</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="roomtype">ประเภทห้องพัก</label>
                    <select id="roomtype" name="roomtype">
                        <option value="">ทั้งหมด</option>
                        <option value="standard">Standard Room</option>
                        <option value="deluxe">Deluxe Room</option>
                        <option value="family">Family Room</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="search-btn">ค้นหาห้องพัก</button>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h3>ห้องพักแนะนำ</h3>
            <p>
                ตัวอย่างห้องพักที่เปิดให้จองในระบบ สามารถปรับเปลี่ยนรูป ราคา
                และรายละเอียดแต่ละห้องได้ภายหลัง
            </p>
        </div>
    </div>

    <div class="rooms-grid">
        <div class="room-card">
            <img src="uploads/room1.jpg" alt="Standard Room" class="room-image">
            <div class="room-body">
                <div class="room-top">
                    <div class="room-title">Standard Room</div>
                    <div class="room-price">฿800 <span>/ คืน</span></div>
                </div>

                <div class="room-desc">
                    ห้องพักมาตรฐาน เหมาะสำหรับผู้เข้าพัก 1-2 คน
                    บรรยากาศเรียบง่าย สะอาด และเงียบสงบ
                </div>

                <div class="room-features">
                    <span class="feature">เตียง 1 เตียง</span>
                    <span class="feature">Wi-Fi</span>
                    <span class="feature">เครื่องปรับอากาศ</span>
                    <span class="feature">ห้องน้ำในตัว</span>
                </div>

                <div class="room-footer">
                    <div class="room-capacity">รองรับ 2 คน</div>
                    <a class="book-btn" href="booking_form.php?room_type=Standard Room&price=800&checkin=<?php echo urlencode($checkin); ?>&checkout=<?php echo urlencode($checkout); ?>&guests=<?php echo urlencode($guests); ?>">
                        จองห้องนี้
                    </a>
                </div>
            </div>
        </div>

        <div class="room-card">
            <img src="uploads/room2.jpg" alt="Deluxe Room" class="room-image">
            <div class="room-body">
                <div class="room-top">
                    <div class="room-title">Deluxe Room</div>
                    <div class="room-price">฿1,200 <span>/ คืน</span></div>
                </div>

                <div class="room-desc">
                    ห้องพักขนาดใหญ่ขึ้น พร้อมสิ่งอำนวยความสะดวกครบครัน
                    เหมาะสำหรับผู้เข้าพักที่ต้องการความสะดวกสบายมากขึ้น
                </div>

                <div class="room-features">
                    <span class="feature">เตียงใหญ่</span>
                    <span class="feature">Wi-Fi</span>
                    <span class="feature">ทีวี</span>
                    <span class="feature">ตู้เย็น</span>
                </div>

                <div class="room-footer">
                    <div class="room-capacity">รองรับ 2-3 คน</div>
                    <a class="book-btn" href="booking_form.php?room_type=Standard Room&price=800&checkin=<?php echo urlencode($checkin); ?>&checkout=<?php echo urlencode($checkout); ?>&guests=<?php echo urlencode($guests); ?>">
                        จองห้องนี้
                    </a>
                </div>
            </div>
        </div>

        <div class="room-card">
            <img src="uploads/room3.jpg" alt="Family Room" class="room-image">
            <div class="room-body">
                <div class="room-top">
                    <div class="room-title">Family Room</div>
                    <div class="room-price">฿1,800 <span>/ คืน</span></div>
                </div>

                <div class="room-desc">
                    ห้องพักสำหรับครอบครัวหรือผู้เข้าพักหลายคน
                    พื้นที่กว้าง เหมาะกับการเข้าพักเป็นกลุ่ม
                </div>

                <div class="room-features">
                    <span class="feature">2 เตียง</span>
                    <span class="feature">Wi-Fi</span>
                    <span class="feature">ทีวี</span>
                    <span class="feature">พื้นที่กว้าง</span>
                </div>

                <div class="room-footer">
                    <div class="room-capacity">รองรับ 4-5 คน</div>
                    <a class="book-btn" href="booking_form.php?room_type=Standard Room&price=800&checkin=<?php echo urlencode($checkin); ?>&checkout=<?php echo urlencode($checkout); ?>&guests=<?php echo urlencode($guests); ?>">
                        จองห้องนี้
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="info-box">
        <h4>ข้อมูลการเข้าพัก</h4>
        <ul>
            <li>เวลาเช็คอินหลัง 14:00 น. และเช็คเอาต์ก่อน 12:00 น.</li>
            <li>กรุณานำบัตรประชาชนหรือเอกสารยืนยันตัวตนมาแสดงในวันเข้าพัก</li>
            <li>การจองจะสมบูรณ์เมื่อเจ้าหน้าที่ตรวจสอบและยืนยันการจองแล้ว</li>
            <li>สามารถปรับส่วนนี้เป็นเงื่อนไขจริงของที่พักในระบบของคุณได้</li>
        </ul>
    </div>
</section>

<footer class="footer">
    <div class="footer-inner">
        © <?php echo date('Y'); ?> สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม
    </div>
</footer>

<script>
const today = new Date().toISOString().split('T')[0];
document.getElementById('checkin').setAttribute('min', today);
document.getElementById('checkout').setAttribute('min', today);

document.getElementById('checkin').addEventListener('change', function() {
    document.getElementById('checkout').value = '';
    document.getElementById('checkout').setAttribute('min', this.value);
});

function scrollToBooking(roomName){
    const roomType = document.getElementById('roomtype');
    if(roomType){
        if(roomName === 'Standard Room') roomType.value = 'standard';
        if(roomName === 'Deluxe Room') roomType.value = 'deluxe';
        if(roomName === 'Family Room') roomType.value = 'family';
    }
    window.scrollTo({
        top: document.querySelector('.booking-search').offsetTop - 90,
        behavior: 'smooth'
    });
}
</script>

</body>
</html>