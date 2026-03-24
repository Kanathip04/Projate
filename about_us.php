<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ประวัติสถาบัน</title>

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

:root{
    --main:#09422A;
    --sub:#176046;
    --white:#ffffff;
    --text:#2d2d2d;
    --shadow:0 10px 30px rgba(0,0,0,0.08);
}

body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#f7f7f7;
    color:var(--text);
    line-height:1.8;
}

/* ปุ่มกลับ */
.back-btn{
    position:fixed;
    top:25px;
    left:30px;
    text-decoration:none;
    color:var(--main);
    font-weight:600;
    font-size:15px;
    padding:10px 18px;
    border:2px solid var(--main);
    border-radius:30px;
    background:rgba(255,255,255,0.95);
    z-index:999;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    transition:all .3s ease;
}

.back-btn:hover{
    background:var(--main);
    color:#fff;
    transform:translateY(-2px);
}

/* กล่องหลัก */
.about-wrapper{
    max-width:1400px;
    margin:0 auto;
    padding:100px 40px 60px;
}

.about-section{
    display:grid;
    grid-template-columns:1.1fr 0.9fr;
    background:var(--white);
    border-radius:30px;
    overflow:hidden;
    box-shadow:var(--shadow);
}

/* ฝั่งข้อความ */
.about-text{
    padding:70px 65px;
    background:linear-gradient(to bottom, #fafafa, #f2f2f2);
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.about-text h4{
    color:var(--sub);
    letter-spacing:8px;
    font-weight:700;
    font-size:14px;
    margin-bottom:18px;
}

.about-text h1{
    font-size:64px;
    color:var(--main);
    margin-bottom:26px;
    line-height:1.1;
}

.about-text p{
    font-size:18px;
    margin-bottom:18px;
    text-align:justify;
}

/* ฝั่งรูป */
.about-image{
    min-height:700px;
    background:
        linear-gradient(rgba(9,66,42,0.08), rgba(9,66,42,0.08)),
        url('a0.jpg') center center / cover no-repeat;
}

/* Responsive */
@media (max-width:1100px){
    .about-section{
        grid-template-columns:1fr;
    }

    .about-image{
        min-height:450px;
        order:-1;
    }

    .about-text{
        padding:50px 35px;
    }

    .about-text h1{
        font-size:48px;
    }
}

@media (max-width:768px){
    .about-wrapper{
        padding:90px 18px 40px;
    }

    .about-text h4{
        letter-spacing:5px;
        font-size:13px;
    }

    .about-text h1{
        font-size:38px;
        margin-bottom:20px;
    }

    .about-text p{
        font-size:16px;
    }

    .back-btn{
        top:18px;
        left:18px;
        font-size:14px;
        padding:8px 14px;
    }

    .about-image{
        min-height:320px;
    }
}
</style>
</head>

<body>

<a href="javascript:history.back()" class="back-btn">← กลับหน้าหลัก</a>

<div class="about-wrapper">
    <section class="about-section">
        <div class="about-text">
            <h4>ABOUT</h4>
            <h1>ประวัติ</h1>

            <p>
                สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม (สวนรุกขเวช มมส)
                ก่อตั้งเมื่อปี พ.ศ. 2530 โดยความร่วมมือระหว่าง มศว.มหาสารคามและจังหวัด
                เพื่ออนุรักษ์พรรณไม้พื้นเมืองอีสาน ได้รับพระราชทานนาม “สวนวลัยรุกขเวช”
                จากสมเด็จพระเจ้าลูกเธอ เจ้าฟ้าจุฬาภรณวลัยลักษณ์ เมื่อวันที่ 28 กันยายน 2531
                ปัจจุบันตั้งอยู่บนพื้นที่ 2 แห่ง ได้แก่ สถานีปฏิบัติการบ้านเกิ้ง (อ.เมือง)
                และสถาบันวิจัยฯ (อ.นาดูน) ดำเนินงานด้านวิจัย ความหลากหลายทางชีวภาพ
                และอนุรักษ์ธรรมชาติ
            </p>

            <p>
                จุดเริ่มต้นของสถาบันเริ่มจากโครงการสวนพฤกษชาติและศูนย์สนเทศพรรณไม้อีสาน
                โดยความร่วมมือของมหาวิทยาลัยศรีนครินทรวิโรฒ วิทยาเขตมหาสารคาม
                และจังหวัดมหาสารคาม ใช้พื้นที่สาธารณประโยชน์กุดแดง บ้านเกิ้ง ตำบลเกิ้ง
                อำเภอเมือง จังหวัดมหาสารคาม จำนวนประมาณ 270 ไร่
                ต่อมาได้รับพระราชทานนามว่า “สวนวลัยรุกขเวช”
                และในช่วงปี พ.ศ. 2532–2535 ได้มีการขยายพื้นที่เพิ่มเติมที่อำเภอนาดูน
                พร้อมการสนับสนุนงบประมาณจากโครงการอีสานเขียว
            </p>

            <p>
                เมื่อวันที่ 22 ตุลาคม 2535 สถาบันได้รับการจัดตั้งเป็นหน่วยงานระดับสถาบันวิจัย
                และได้รับพระราชทานนามหน่วยงานว่า “สถาบันวิจัยวลัยรุกขเวช”
                ปัจจุบันเป็นหน่วยงานที่มุ่งเน้นงานวิจัยและบริการวิชาการด้านความหลากหลายทางชีวภาพ
                เกษตรอินทรีย์ และภูมิปัญญาท้องถิ่น
            </p>
        </div>

        <div class="about-image"></div>
    </section>
</div>

</body>
</html>