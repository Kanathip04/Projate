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
    --main-soft:#176046;
    --accent:#C8A96B;
    --white:#ffffff;
    --text:#2b2b2b;
    --muted:#666;
    --bg:#f3f5f4;
    --line:#e7ece9;
    --shadow:0 18px 45px rgba(0,0,0,0.10);
    --shadow-hover:0 25px 55px rgba(0,0,0,0.14);
    --radius-xl:32px;
    --radius-lg:22px;
}

html{
    scroll-behavior:smooth;
}

body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(9,66,42,0.05), transparent 28%),
        radial-gradient(circle at bottom right, rgba(200,169,107,0.08), transparent 24%),
        var(--bg);
    color:var(--text);
    line-height:1.85;
}

/* ปุ่มกลับ */
.back-btn{
    position:fixed;
    top:24px;
    left:28px;
    text-decoration:none;
    color:var(--main);
    font-weight:700;
    font-size:15px;
    padding:11px 18px;
    border:1.8px solid rgba(9,66,42,0.75);
    border-radius:999px;
    background:rgba(255,255,255,0.88);
    backdrop-filter:blur(10px);
    -webkit-backdrop-filter:blur(10px);
    z-index:999;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
    transition:all .28s ease;
}

.back-btn:hover{
    background:var(--main);
    color:#fff;
    transform:translateY(-2px);
    box-shadow:0 14px 30px rgba(9,66,42,0.25);
}

/* กล่องหลัก */
.about-wrapper{
    max-width:1380px;
    margin:0 auto;
    padding:95px 30px 60px;
}

/* กรอบ section */
.about-section{
    display:grid;
    grid-template-columns:1.08fr 0.92fr;
    background:rgba(255,255,255,0.94);
    border:1px solid rgba(9,66,42,0.06);
    border-radius:var(--radius-xl);
    overflow:hidden;
    box-shadow:var(--shadow);
    transition:all .35s ease;
}

.about-section:hover{
    transform:translateY(-3px);
    box-shadow:var(--shadow-hover);
}

/* ฝั่งข้อความ */
.about-text{
    position:relative;
    padding:72px 64px;
    background:
        linear-gradient(180deg, #fcfcfc 0%, #f6f7f6 100%);
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.about-text::before{
    content:"";
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:8px;
    background:linear-gradient(90deg, var(--main) 0%, var(--accent) 100%);
}

.about-text h4{
    display:inline-block;
    color:var(--main-soft);
    letter-spacing:7px;
    font-weight:800;
    font-size:13px;
    margin-bottom:16px;
    position:relative;
    width:fit-content;
}

.about-text h4::after{
    content:"";
    display:block;
    width:58px;
    height:2px;
    background:var(--accent);
    margin-top:8px;
    border-radius:10px;
}

.about-text h1{
    font-size:64px;
    color:var(--main);
    margin-bottom:22px;
    line-height:1.05;
    font-weight:800;
    text-shadow:0 2px 6px rgba(0,0,0,0.03);
}

.about-text .lead{
    font-size:18px;
    color:#404040;
    margin-bottom:18px;
}

.about-text p{
    font-size:17px;
    color:var(--muted);
    margin-bottom:18px;
    text-align:justify;
}

.about-text p:last-child{
    margin-bottom:0;
}

/* ฝั่งรูป */
.about-image{
    position:relative;
    min-height:760px;
    overflow:hidden;
    background:#dfe8e2;
}

.about-image::before{
    content:"";
    position:absolute;
    inset:0;
    background:
        linear-gradient(rgba(9,66,42,0.10), rgba(9,66,42,0.08)),
        url('a0.jpg') center center / cover no-repeat;
    transform:scale(1.03);
    transition:transform .8s ease;
}

.about-section:hover .about-image::before{
    transform:scale(1.07);
}

.about-image::after{
    content:"";
    position:absolute;
    inset:0;
    background:
        linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(9,66,42,0.05) 100%);
    pointer-events:none;
}

/* กล่องข้อความซ้อนบนรูป */
.image-badge{
    position:absolute;
    right:24px;
    bottom:24px;
    background:rgba(255,255,255,0.90);
    color:var(--main);
    padding:14px 18px;
    border-radius:18px;
    box-shadow:0 12px 25px rgba(0,0,0,0.12);
    border:1px solid rgba(9,66,42,0.08);
    backdrop-filter:blur(8px);
    -webkit-backdrop-filter:blur(8px);
    max-width:260px;
}

.image-badge strong{
    display:block;
    font-size:16px;
    margin-bottom:4px;
}

.image-badge span{
    font-size:13px;
    color:#555;
    line-height:1.6;
}

/* Responsive */
@media (max-width:1200px){
    .about-text{
        padding:60px 48px;
    }

    .about-text h1{
        font-size:54px;
    }

    .about-image{
        min-height:680px;
    }
}

@media (max-width:1050px){
    .about-section{
        grid-template-columns:1fr;
    }

    .about-image{
        min-height:430px;
        order:-1;
    }

    .about-text{
        padding:48px 34px 42px;
    }

    .about-text h1{
        font-size:46px;
    }

    .image-badge{
        right:18px;
        bottom:18px;
        max-width:220px;
    }
}

@media (max-width:768px){
    .about-wrapper{
        padding:88px 16px 34px;
    }

    .about-section{
        border-radius:24px;
    }

    .about-text{
        padding:38px 22px 30px;
    }

    .about-text h4{
        letter-spacing:5px;
        font-size:12px;
        margin-bottom:14px;
    }

    .about-text h1{
        font-size:36px;
        margin-bottom:18px;
    }

    .about-text .lead{
        font-size:16px;
    }

    .about-text p{
        font-size:15.5px;
        line-height:1.9;
    }

    .back-btn{
        top:16px;
        left:16px;
        font-size:14px;
        padding:9px 14px;
    }

    .about-image{
        min-height:300px;
    }

    .image-badge{
        left:16px;
        right:16px;
        bottom:16px;
        max-width:none;
        padding:12px 14px;
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

            <p class="lead">
                สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม เป็นหน่วยงานด้านวิจัย
                และบริการวิชาการที่มุ่งเน้นความหลากหลายทางชีวภาพ การอนุรักษ์ทรัพยากรธรรมชาติ
                และภูมิปัญญาท้องถิ่นอย่างยั่งยืน
            </p>

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

        <div class="about-image">
            <div class="image-badge">
                <strong>Walai Rukhavej Research Institute</strong>
                <span>สถาบันวิจัยด้านความหลากหลายทางชีวภาพ เกษตรอินทรีย์ และภูมิปัญญาท้องถิ่น</span>
            </div>
        </div>

    </section>
</div>

</body>
</html>