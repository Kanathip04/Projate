<?php
include 'config.php';
$result = $conn->query("SELECT * FROM news ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข่าวสาร</title>
<style>
    *{
        box-sizing:border-box;
        margin:0;
        padding:0;
    }

    :root{
        --bg:#f4f6f8;
        --card:#ffffff;
        --text:#1f2937;
        --muted:#6b7280;
        --primary:#638411;
        --primary-dark:#4f6b0d;
        --line:#e5e7eb;
        --shadow:0 10px 30px rgba(0,0,0,.07);
        --radius:22px;
    }

    body{
        font-family:'Segoe UI', Tahoma, sans-serif;
        background:
            radial-gradient(circle at top, rgba(99,132,17,.08), transparent 28%),
            linear-gradient(180deg, #f7f9fb 0%, #f3f5f7 100%);
        color:var(--text);
        min-height:100vh;
    }

    a{
        text-decoration:none;
    }

    .page-wrap{
        width:100%;
        max-width:1100px;
        margin:0 auto;
        padding:28px 18px 60px;
    }

    .topbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:16px;
        flex-wrap:wrap;
        margin-bottom:26px;
    }

    .back-btn{
        display:inline-flex;
        align-items:center;
        gap:10px;
        background:#fff;
        color:var(--primary);
        border:1px solid rgba(99,132,17,.18);
        padding:12px 18px;
        border-radius:999px;
        font-size:15px;
        font-weight:700;
        box-shadow:0 6px 18px rgba(0,0,0,.05);
        transition:.25s ease;
    }

    .back-btn:hover{
        background:var(--primary);
        color:#fff;
        transform:translateY(-2px);
    }

    .page-head{
        text-align:center;
        padding:18px 0 8px;
        margin-bottom:28px;
    }

    .page-head .badge{
        display:inline-block;
        background:rgba(99,132,17,.12);
        color:var(--primary-dark);
        font-size:13px;
        font-weight:700;
        padding:8px 14px;
        border-radius:999px;
        margin-bottom:14px;
    }

    .page-head h1{
        font-size:44px;
        line-height:1.2;
        margin-bottom:10px;
        color:#121826;
        font-weight:800;
        letter-spacing:-0.5px;
    }

    .page-head p{
        color:var(--muted);
        font-size:17px;
        max-width:700px;
        margin:0 auto;
        line-height:1.8;
    }

    .news-list{
        display:flex;
        flex-direction:column;
        gap:26px;
    }

    .news-card{
        background:var(--card);
        border-radius:var(--radius);
        box-shadow:var(--shadow);
        border:1px solid rgba(0,0,0,.04);
        overflow:hidden;
        transition:.25s ease;
    }

    .news-card:hover{
        transform:translateY(-3px);
        box-shadow:0 16px 34px rgba(0,0,0,.09);
    }

    .news-inner{
        padding:28px;
    }

    .news-meta{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
        margin-bottom:14px;
    }

    .news-date{
        display:inline-flex;
        align-items:center;
        background:#f3f4f6;
        color:#4b5563;
        padding:8px 14px;
        border-radius:999px;
        font-size:13px;
        font-weight:600;
    }

    .news-title{
        font-size:34px;
        font-weight:800;
        line-height:1.35;
        margin-bottom:18px;
        color:#111827;
        word-break:break-word;
    }

    .news-image{
        margin-bottom:20px;
        border-radius:18px;
        overflow:hidden;
        background:#eef1f4;
        border:1px solid #edf0f2;
    }

    .news-image img{
        width:100%;
        display:block;
        max-height:500px;
        object-fit:cover;
        transition:transform .35s ease;
    }

    .news-card:hover .news-image img{
        transform:scale(1.02);
    }

    .news-content{
        font-size:17px;
        line-height:2;
        color:#374151;
        white-space:pre-line;
        word-break:break-word;
    }

    .empty-box{
        background:#fff;
        border-radius:22px;
        box-shadow:var(--shadow);
        padding:60px 30px;
        text-align:center;
        color:#6b7280;
    }

    .empty-box h2{
        color:#111827;
        font-size:28px;
        margin-bottom:10px;
    }

    .empty-box p{
        font-size:16px;
        line-height:1.8;
    }

    .footer-note{
        text-align:center;
        color:#8a8f98;
        font-size:14px;
        margin-top:26px;
    }

    @media (max-width: 768px){
        .page-wrap{
            padding:20px 14px 40px;
        }

        .topbar{
            margin-bottom:18px;
        }

        .page-head{
            margin-bottom:22px;
        }

        .page-head h1{
            font-size:32px;
        }

        .page-head p{
            font-size:15px;
        }

        .news-inner{
            padding:18px;
        }

        .news-title{
            font-size:24px;
        }

        .news-content{
            font-size:15px;
            line-height:1.85;
        }

        .back-btn{
            width:100%;
            justify-content:center;
        }
    }
</style>
</head>
<body>

<div class="page-wrap">

    <div class="topbar">
        <a href="index.php" class="back-btn">← กลับหน้าหลัก</a>
    </div>

    <div class="page-head">
        <div class="badge">News & Updates</div>
        <h1>ข่าวสาร</h1>
        <p>ติดตามข่าวสาร กิจกรรม ประกาศ และความเคลื่อนไหวล่าสุดของหน่วยงานได้ที่นี่</p>
    </div>

    <div class="news-list">
        <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <article class="news-card">
                    <div class="news-inner">
                        <div class="news-meta">
                            <div class="news-date">
                                <?php echo date("d/m/Y H:i", strtotime($row["created_at"])); ?>
                            </div>
                        </div>

                        <h2 class="news-title">
                            <?php echo htmlspecialchars($row["title"]); ?>
                        </h2>

                        <?php if(!empty($row["image"])): ?>
                            <div class="news-image">
                                <img src="uploads/<?php echo htmlspecialchars($row["image"]); ?>" alt="<?php echo htmlspecialchars($row["title"]); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="news-content">
                            <?php echo htmlspecialchars($row["content"]); ?>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-box">
                <h2>ยังไม่มีข่าวสาร</h2>
                <p>ขณะนี้ยังไม่มีข้อมูลข่าวประชาสัมพันธ์ กรุณาตรวจสอบอีกครั้งภายหลัง</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-note">
        © ข่าวสารหน่วยงาน
    </div>

</div>

</body>
</html>