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
<title>จองห้องพัก</title>
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}
body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#f5f7fb;
    color:#222;
}
.header{
    background:linear-gradient(135deg,#1d3557,#457b9d);
    color:#fff;
    padding:60px 20px 40px;
    text-align:center;
}
.header h1{
    font-size:38px;
    margin-bottom:10px;
}
.header p{
    font-size:18px;
    opacity:.95;
}
.container{
    width:min(1200px, 92%);
    margin:35px auto 50px;
}
.room-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(300px,1fr));
    gap:24px;
}
.room-card{
    background:#fff;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 12px 30px rgba(0,0,0,.08);
    transition:.25s ease;
    border:1px solid #edf0f5;
}
.room-card:hover{
    transform:translateY(-5px);
    box-shadow:0 18px 35px rgba(0,0,0,.12);
}
.room-image{
    width:100%;
    height:240px;
    object-fit:cover;
    display:block;
    background:#ddd;
}
.room-body{
    padding:20px;
}
.room-title{
    font-size:24px;
    font-weight:700;
    margin-bottom:12px;
    color:#1d3557;
}
.room-desc{
    font-size:15px;
    line-height:1.7;
    color:#555;
    margin-bottom:16px;
}
.room-meta{
    display:grid;
    grid-template-columns:1fr;
    gap:8px;
    margin-bottom:18px;
}
.meta-item{
    background:#f8fafc;
    border:1px solid #e9eef5;
    padding:10px 12px;
    border-radius:12px;
    font-size:14px;
    color:#333;
}
.price-box{
    display:flex;
    align-items:end;
    justify-content:space-between;
    gap:10px;
    margin-top:8px;
}
.price{
    font-size:28px;
    font-weight:800;
    color:#e63946;
}
.price span{
    font-size:14px;
    color:#666;
    font-weight:500;
}
.book-btn{
    display:inline-block;
    text-decoration:none;
    background:linear-gradient(135deg,#2a9d8f,#21867a);
    color:#fff;
    padding:12px 20px;
    border-radius:12px;
    font-weight:700;
    transition:.2s ease;
}
.book-btn:hover{
    transform:translateY(-1px);
    opacity:.95;
}
@media (max-width:768px){
    .header h1{
        font-size:28px;
    }
    .header p{
        font-size:15px;
    }
    .room-image{
        height:210px;
    }
    .room-title{
        font-size:21px;
    }
    .price{
        font-size:24px;
    }
    .price-box{
        flex-direction:column;
        align-items:flex-start;
    }
    .book-btn{
        width:100%;
        text-align:center;
    }
}
</style>
</head>
<body>

<div class="header">
    <h1>ห้องพักของเรา</h1>
    <p>เลือกห้องพักที่ต้องการ แล้วกดจองเพื่อกรอกข้อมูลการเข้าพัก</p>
</div>

<div class="container">
    <div class="room-grid">
        <?php foreach($rooms as $room): ?>
            <div class="room-card">
                <img src="<?php echo htmlspecialchars($room['image']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>" class="room-image">
                
                <div class="room-body">
                    <div class="room-title"><?php echo htmlspecialchars($room['name']); ?></div>
                    <div class="room-desc"><?php echo htmlspecialchars($room['description']); ?></div>

                    <div class="room-meta">
                        <div class="meta-item"><strong>ขนาดห้อง:</strong> <?php echo htmlspecialchars($room['size']); ?></div>
                        <div class="meta-item"><strong>ประเภทเตียง:</strong> <?php echo htmlspecialchars($room['bed']); ?></div>
                        <div class="meta-item"><strong>รองรับ:</strong> <?php echo htmlspecialchars($room['capacity']); ?></div>
                    </div>

                    <div class="price-box">
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
</div>

</body>
</html>