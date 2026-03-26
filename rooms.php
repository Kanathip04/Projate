<?php
date_default_timezone_set('Asia/Bangkok');

/*
    ตอนนี้ยังไม่ได้ดึงจากฐานข้อมูล
    ผมทำเป็น array ไว้ก่อน เพื่อให้ใช้งานได้ทันที
    ถ้าคุณอยากให้ดึงจาก DB ภายหลัง ผมแปลงให้ได้
*/
$rooms = [
    [
        "id" => 1,
        "name" => "Deluxe Room",
        "price" => 1200,
        "size" => "28 ตร.ม.",
        "bed" => "เตียงใหญ่ 1 เตียง",
        "capacity" => "พักได้ 2 คน",
        "image" => "uploads/room1.jpg",
        "description" => "ห้องพักขนาดพอดีสำหรับผู้เข้าพัก 2 ท่าน พร้อมเครื่องปรับอากาศ Wi-Fi ฟรี และห้องน้ำส่วนตัว"
    ],
    [
        "id" => 2,
        "name" => "Superior Twin Room",
        "price" => 1500,
        "size" => "32 ตร.ม.",
        "bed" => "เตียงเดี่ยว 2 เตียง",
        "capacity" => "พักได้ 2 คน",
        "image" => "uploads/room2.jpg",
        "description" => "เหมาะสำหรับเพื่อนหรือผู้เข้าพักที่ต้องการแยกเตียง มีสิ่งอำนวยความสะดวกครบ"
    ],
    [
        "id" => 3,
        "name" => "Family Room",
        "price" => 2200,
        "size" => "40 ตร.ม.",
        "bed" => "เตียงใหญ่ 1 + เตียงเดี่ยว 2",
        "capacity" => "พักได้ 4 คน",
        "image" => "uploads/room3.jpg",
        "description" => "ห้องพักสำหรับครอบครัว พื้นที่กว้างขวาง เหมาะสำหรับเข้าพักหลายคน"
    ]
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>
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
    --card-shadow:0 12px 35px rgba(0,0,0,.08);
}
body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:var(--bg);
    color:var(--text);
}
a{
    text-decoration:none;
}
.page-wrap{
    min-height:100vh;
}

/* HERO */
.hero{
    background:
        linear-gradient(135deg, rgba(62,79,14,.92), rgba(111,132,40,.88)),
        url('uploads/room-banner.jpg') center/cover no-repeat;
    color:#fff;
    padding:70px 20px 120px;
    position:relative;
    overflow:hidden;
}
.hero::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(to bottom, rgba(255,255,255,0) 65%, rgba(248,250,252,1) 100%);
}
.hero-inner{
    width:min(1180px, 92%);
    margin:0 auto;
    position:relative;
    z-index:2;
}
.hero-badge{
    display:inline-block;
    padding:10px 18px;
    border:1px solid rgba(255,255,255,.35);
    background:rgba(255,255,255,.12);
    backdrop-filter:blur(8px);
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    margin-bottom:18px;
}
.hero h1{
    font-size:48px;
    line-height:1.2;
    margin-bottom:14px;
    max-width:760px;
}
.hero p{
    font-size:18px;
    line-height:1.8;
    color:rgba(255,255,255,.92);
    max-width:760px;
}

/* SEARCH BOX */
.search-box{
    width:min(1180px, 92%);
    margin:-55px auto 30px;
    background:var(--white);
    border-radius:26px;
    box-shadow:var(--card-shadow);
    border:1px solid #eef1f4;
    position:relative;
    z-index:5;
    padding:26px;
}
.search-box h2{
    font-size:30px;
    margin-bottom:8px;
    color:#111827;
}
.search-box p{
    color:var(--muted);
    margin-bottom:22px;
}
.search-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr) 180px;
    gap:14px;
}
.field label{
    display:block;
    font-size:14px;
    font-weight:600;
    margin-bottom:8px;
    color:#374151;
}
.field input,
.field select{
    width:100%;
    height:52px;
    border:1px solid var(--line);
    border-radius:14px;
    padding:0 14px;
    font-size:15px;
    background:#fff;
    outline:none;
}
.field input:focus,
.field select:focus{
    border-color:var(--brand);
    box-shadow:0 0 0 4px rgba(111,132,40,.12);
}
.search-btn{
    border:none;
    height:52px;
    border-radius:14px;
    background:var(--brand);
    color:#fff;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    margin-top:30px;
    transition:.2s ease;
}
.search-btn:hover{
    background:var(--brand-dark);
    transform:translateY(-1px);
}

/* SECTION */
.section{
    width:min(1180px, 92%);
    margin:0 auto 60px;
}
.section-head{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:20px;
    margin-bottom:24px;
}
.section-head h3{
    font-size:34px;
    color:#111827;
}
.section-head p{
    color:var(--muted);
    max-width:700px;
    line-height:1.7;
}

/* ROOM GRID */
.room-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(320px,1fr));
    gap:24px;
}
.room-card{
    background:#fff;
    border-radius:24px;
    overflow:hidden;
    border:1px solid #edf0f5;
    box-shadow:var(--card-shadow);
    transition:.25s ease;
}
.room-card:hover{
    transform:translateY(-6px);
}
.room-image-wrap{
    position:relative;
}
.room-image{
    width:100%;
    height:240px;
    object-fit:cover;
    display:block;
    background:#ddd;
}
.room-price-tag{
    position:absolute;
    top:16px;
    right:16px;
    background:rgba(17,24,39,.82);
    color:#fff;
    padding:10px 14px;
    border-radius:999px;
    font-size:14px;
    font-weight:700;
    backdrop-filter:blur(8px);
}
.room-body{
    padding:22px;
}
.room-title{
    font-size:24px;
    font-weight:800;
    margin-bottom:10px;
    color:#111827;
}
.room-desc{
    font-size:15px;
    line-height:1.7;
    color:#4b5563;
    margin-bottom:18px;
    min-height:76px;
}
.room-meta{
    display:grid;
    grid-template-columns:1fr;
    gap:10px;
    margin-bottom:20px;
}
.meta-item{
    background:#f8fafc;
    border:1px solid #e9eef5;
    padding:12px 14px;
    border-radius:14px;
    font-size:14px;
    color:#374151;
}
.meta-item strong{
    color:#111827;
}
.room-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.price{
    font-size:28px;
    font-weight:800;
    color:var(--brand-dark);
}
.price span{
    font-size:14px;
    color:#6b7280;
    font-weight:500;
}
.book-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:150px;
    padding:13px 18px;
    border-radius:14px;
    background:var(--brand);
    color:#fff;
    font-weight:700;
    transition:.2s ease;
}
.book-btn:hover{
    background:var(--brand-dark);
}

/* FOOTER */
.footer-note{
    width:min(1180px, 92%);
    margin:0 auto 50px;
    background:var(--brand-light);
    border:1px solid #e2ecd0;
    border-radius:20px;
    padding:20px;
    color:#3f4b1d;
    line-height:1.8;
}

/* RESPONSIVE */
@media (max-width: 1024px){
    .search-grid{
        grid-template-columns:repeat(2, 1fr);
    }
    .search-btn{
        margin-top:0;
    }
}
@media (max-width: 768px){
    .hero{
        padding:50px 16px 100px;
    }
    .hero h1{
        font-size:34px;
    }
    .hero p{
        font-size:15px;
    }
    .search-box{
        margin-top:-45px;
        padding:20px;
        border-radius:20px;
    }
    .search-box h2{
        font-size:24px;
    }
    .search-grid{
        grid-template-columns:1fr;
    }
    .section-head{
        flex-direction:column;
        align-items:flex-start;
    }
    .section-head h3{
        font-size:28px;
    }
    .room-image{
        height:220px;
    }
    .room-title{
        font-size:21px;
    }
    .room-footer{
        flex-direction:column;
        align-items:flex-start;
    }
    .book-btn{
        width:100%;
    }
}
</style>
</head>
<body>

<div class="page-wrap">

    <section class="hero">
        <div class="hero-inner">
            <div class="hero-badge">Room Reservation</div>
            <h1>ระบบจองห้องพักและที่พักภายในสถาบัน</h1>
            <p>
                เลือกประเภทห้องพัก วันที่เข้าพัก และจำนวนผู้เข้าพักได้จากหน้านี้
                เพื่ออำนวยความสะดวกในการเข้าพักสำหรับผู้มาติดต่อราชการ นักวิจัย
                ผู้เข้าร่วมกิจกรรม และผู้ใช้งานทั่วไป
            </p>
        </div>
    </section>

    <section class="search-box">
        <h2>ค้นหาห้องพักว่าง</h2>
        <p>กรอกข้อมูลเบื้องต้นเพื่อค้นหาห้องพักที่เหมาะสมกับการเข้าพักของคุณ</p>

        <form action="" method="get">
            <div class="search-grid">
                <div class="field">
                    <label>วันที่เข้าพัก</label>
                    <input type="date" name="checkin">
                </div>

                <div class="field">
                    <label>วันที่ออก</label>
                    <input type="date" name="checkout">
                </div>

                <div class="field">
                    <label>จำนวนผู้เข้าพัก</label>
                    <select name="guests">
                        <option value="">เลือกจำนวน</option>
                        <option value="1">1 คน</option>
                        <option value="2">2 คน</option>
                        <option value="3">3 คน</option>
                        <option value="4">4 คน</option>
                    </select>
                </div>

                <div class="field">
                    <label>ประเภทห้องพัก</label>
                    <select name="type">
                        <option value="">ทั้งหมด</option>
                        <option value="Deluxe Room">Deluxe Room</option>
                        <option value="Superior Twin Room">Superior Twin Room</option>
                        <option value="Family Room">Family Room</option>
                    </select>
                </div>

                <button type="submit" class="search-btn">ค้นหาห้องพัก</button>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>ห้องพักแนะนำ</h3>
                <p>เลือกห้องพักที่เหมาะสมกับจำนวนผู้เข้าพักและความต้องการของคุณ โดยข้อมูลห้องพักและลิงก์จองยังคงใช้แบบเดิมทั้งหมด</p>
            </div>
        </div>

        <div class="room-grid">
            <?php foreach($rooms as $room): ?>
                <div class="room-card">
                    <div class="room-image-wrap">
                        <img src="<?php echo htmlspecialchars($room['image']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>" class="room-image">
                        <div class="room-price-tag">฿<?php echo number_format($room['price']); ?> / คืน</div>
                    </div>

                    <div class="room-body">
                        <div class="room-title"><?php echo htmlspecialchars($room['name']); ?></div>
                        <div class="room-desc"><?php echo htmlspecialchars($room['description']); ?></div>

                        <div class="room-meta">
                            <div class="meta-item"><strong>ขนาดห้อง:</strong> <?php echo htmlspecialchars($room['size']); ?></div>
                            <div class="meta-item"><strong>ประเภทเตียง:</strong> <?php echo htmlspecialchars($room['bed']); ?></div>
                            <div class="meta-item"><strong>รองรับ:</strong> <?php echo htmlspecialchars($room['capacity']); ?></div>
                        </div>

                        <div class="room-footer">
                            <div class="price">
                                ฿<?php echo number_format($room['price']); ?>
                                <span>/ คืน</span>
                            </div>

                            <a class="book-btn"
                               href="booking_form.php?room_id=<?php echo urlencode($room['id']); ?>&room_name=<?php echo urlencode($room['name']); ?>&room_price=<?php echo urlencode($room['price']); ?>">
                                จองห้องนี้
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="footer-note">
        หมายเหตุ: หน้านี้ออกแบบใหม่เฉพาะส่วนการแสดงผล โดยยังคงข้อมูลห้องพักเดิม
        และยังส่งค่าการจองไปหน้า <strong>booking_form.php</strong> แบบเดิม
    </div>

</div>

</body>
</html>