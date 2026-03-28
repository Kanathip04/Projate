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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *{
        box-sizing:border-box;
        margin:0;
        padding:0;
    }

    :root{
        --ink:#1a1a2e;
        --gold:#c9a96e;
        --gold-light:rgba(201,169,110,.15);
        --bg:#f5f1eb;
        --card:#fff;
        --muted:#7a7a8c;
        --border:#e8e4de;
        --shadow:0 10px 32px rgba(26,26,46,.08);
        --radius:18px;
    }

    body{
        font-family:'Sarabun', sans-serif;
        background:var(--bg);
        color:var(--ink);
        min-height:100vh;
    }

    a{
        text-decoration:none;
    }

    /* ── Hero header ── */
    .page-hero{
        background:linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
        padding:48px 18px 52px;
        text-align:center;
        position:relative;
        overflow:hidden;
    }

    .page-hero::before{
        content:'';
        position:absolute;
        inset:0;
        background:radial-gradient(ellipse at 50% 0%, rgba(201,169,110,.18) 0%, transparent 65%);
        pointer-events:none;
    }

    .topbar{
        display:flex;
        justify-content:flex-start;
        align-items:center;
        max-width:1100px;
        margin:0 auto 32px;
        position:relative;
        z-index:1;
    }

    .back-btn{
        display:inline-flex;
        align-items:center;
        gap:8px;
        background:transparent;
        color:#fff;
        border:1.5px solid rgba(255,255,255,.3);
        padding:10px 20px;
        border-radius:999px;
        font-family:'Sarabun', sans-serif;
        font-size:15px;
        font-weight:600;
        transition:.25s ease;
    }

    .back-btn:hover{
        background:var(--gold);
        border-color:var(--gold);
        color:var(--ink);
        transform:translateY(-2px);
    }

    .hero-badge{
        display:inline-block;
        background:var(--gold-light);
        color:var(--gold);
        border:1px solid rgba(201,169,110,.35);
        font-size:13px;
        font-weight:700;
        padding:6px 16px;
        border-radius:999px;
        margin-bottom:16px;
        letter-spacing:.5px;
        position:relative;
        z-index:1;
    }

    .hero-title{
        font-size:46px;
        font-weight:800;
        color:#fff;
        line-height:1.2;
        margin-bottom:12px;
        letter-spacing:-.5px;
        position:relative;
        z-index:1;
    }

    .hero-title span{
        color:var(--gold);
    }

    .hero-sub{
        color:rgba(255,255,255,.65);
        font-size:17px;
        max-width:680px;
        margin:0 auto;
        line-height:1.85;
        position:relative;
        z-index:1;
    }

    /* ── Content wrap ── */
    .page-wrap{
        width:100%;
        max-width:1100px;
        margin:0 auto;
        padding:36px 18px 64px;
    }

    /* ── News list ── */
    .news-list{
        display:flex;
        flex-direction:column;
        gap:28px;
    }

    .news-card{
        background:var(--card);
        border-radius:var(--radius);
        box-shadow:var(--shadow);
        border:1px solid var(--border);
        overflow:hidden;
        transition:.25s ease;
    }

    .news-card:hover{
        transform:translateY(-4px);
        box-shadow:0 20px 44px rgba(26,26,46,.12);
    }

    .news-inner{
        padding:30px;
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
        gap:6px;
        background:var(--gold-light);
        color:var(--ink);
        border:1px solid rgba(201,169,110,.25);
        padding:6px 14px;
        border-radius:999px;
        font-size:13px;
        font-weight:600;
    }

    .news-date::before{
        content:'📅';
        font-size:12px;
    }

    .news-title{
        font-size:28px;
        font-weight:800;
        line-height:1.4;
        margin-bottom:18px;
        color:var(--ink);
        word-break:break-word;
        border-left:4px solid var(--gold);
        padding-left:14px;
    }

    .news-image{
        margin-bottom:22px;
        border-radius:14px;
        overflow:hidden;
        background:var(--border);
        border:1px solid var(--border);
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
        font-size:16.5px;
        line-height:2;
        color:#3a3a4a;
        white-space:pre-line;
        word-break:break-word;
    }

    /* ── Empty state ── */
    .empty-box{
        background:var(--card);
        border-radius:var(--radius);
        box-shadow:var(--shadow);
        border:1px solid var(--border);
        padding:64px 30px;
        text-align:center;
    }

    .empty-icon{
        font-size:48px;
        margin-bottom:18px;
    }

    .empty-box h2{
        color:var(--ink);
        font-size:26px;
        font-weight:800;
        margin-bottom:10px;
    }

    .empty-box p{
        color:var(--muted);
        font-size:16px;
        line-height:1.8;
    }

    /* ── Footer note ── */
    .footer-note{
        text-align:center;
        color:var(--muted);
        font-size:14px;
        margin-top:28px;
        padding-top:24px;
        border-top:1px solid var(--border);
    }

    /* ── Responsive ── */
    @media (max-width: 768px){
        .page-hero{
            padding:36px 14px 42px;
        }

        .hero-title{
            font-size:32px;
        }

        .hero-sub{
            font-size:15px;
        }

        .topbar{
            margin-bottom:24px;
        }

        .back-btn{
            width:100%;
            justify-content:center;
        }

        .page-wrap{
            padding:24px 14px 44px;
        }

        .news-inner{
            padding:20px;
        }

        .news-title{
            font-size:21px;
        }

        .news-content{
            font-size:15px;
            line-height:1.9;
        }
    }
</style>
</head>
<body>

<div class="page-hero">
    <div class="topbar">
        <a href="index.php" class="back-btn">← กลับหน้าหลัก</a>
    </div>
    <div class="hero-badge">ประชาสัมพันธ์</div>
    <h1 class="hero-title">ข่าวสาร<span> &amp; กิจกรรม</span></h1>
    <p class="hero-sub">ติดตามข่าวสาร กิจกรรม ประกาศ และความเคลื่อนไหวล่าสุดของหน่วยงานได้ที่นี่</p>
</div>

<div class="page-wrap">

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
                <div class="empty-icon">📰</div>
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
