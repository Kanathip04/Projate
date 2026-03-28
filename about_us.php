<?php
require_once 'auth_guard.php';
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT * FROM about_content WHERE id = 1 LIMIT 1");
$about = $result ? $result->fetch_assoc() : null;

if (!$about) {
    $about = [
        'section_tag' => 'ABOUT',
        'title' => 'ประวัติ',
        'lead_text' => '',
        'paragraph1' => '',
        'paragraph2' => '',
        'paragraph3' => '',
        'image_path' => 'uploads/a0.jpg',
        'image_badge_title' => '',
        'image_badge_text' => ''
    ];
}

$imagePath = !empty($about['image_path']) ? $about['image_path'] : 'uploads/a0.jpg';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ประวัติสถาบัน</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}
:root{
    --ink:#1a1a2e;
    --ink-mid:#2a2a4a;
    --gold:#c9a96e;
    --bg:#f5f1eb;
    --card:#fff;
    --muted:#7a7a8c;
    --border:#e8e4de;
    --text:#2b2b2b;
    --shadow:0 18px 45px rgba(0,0,0,0.10);
    --shadow-hover:0 25px 55px rgba(0,0,0,0.14);
    --radius-xl:32px;
}
body{
    font-family:'Sarabun', 'Segoe UI', Tahoma, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(26,26,46,0.05), transparent 28%),
        radial-gradient(circle at bottom right, rgba(201,169,110,0.08), transparent 24%),
        var(--bg);
    color:var(--text);
    line-height:1.85;
}
.back-btn{
    position:fixed;
    top:24px;
    left:28px;
    text-decoration:none;
    color:var(--ink);
    font-weight:700;
    font-size:15px;
    padding:11px 18px;
    border:1.8px solid rgba(26,26,46,0.75);
    border-radius:999px;
    background:rgba(255,255,255,0.88);
    z-index:999;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
    transition:all .28s ease;
}
.back-btn:hover{
    background:var(--ink);
    color:#fff;
    transform:translateY(-2px);
}
.about-wrapper{
    max-width:1380px;
    margin:0 auto;
    padding:95px 30px 60px;
}
.about-section{
    display:grid;
    grid-template-columns:1.08fr 0.92fr;
    background:rgba(255,255,255,0.94);
    border:1px solid var(--border);
    border-radius:var(--radius-xl);
    overflow:hidden;
    box-shadow:var(--shadow);
}
.about-text{
    position:relative;
    padding:72px 64px;
    background:linear-gradient(180deg, #fafaf8 0%, #f5f3ef 100%);
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
    background:linear-gradient(90deg, var(--ink) 0%, var(--gold) 100%);
}
.about-text h4{
    display:inline-block;
    color:var(--ink-mid);
    letter-spacing:7px;
    font-weight:800;
    font-size:13px;
    margin-bottom:16px;
    width:fit-content;
}
.about-text h4::after{
    content:"";
    display:block;
    width:58px;
    height:2px;
    background:var(--gold);
    margin-top:8px;
    border-radius:10px;
}
.about-text h1{
    font-size:64px;
    color:var(--ink);
    margin-bottom:22px;
    line-height:1.05;
    font-weight:800;
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
    text-align:left;
    letter-spacing:0;
    word-spacing:0;
    line-height:1.9;
}
.about-image{
    position:relative;
    min-height:760px;
    overflow:hidden;
    background:#dde0e8;
}
.about-image::before{
    content:"";
    position:absolute;
    inset:0;
    background:
        linear-gradient(rgba(26,26,46,0.10), rgba(26,26,46,0.08)),
        url('<?php echo htmlspecialchars($imagePath) . '?v=' . time(); ?>') center center / cover no-repeat;
    transform:scale(1.03);
}
.about-image::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(26,26,46,0.05) 100%);
}
.image-badge{
    position:absolute;
    right:24px;
    bottom:24px;
    background:rgba(255,255,255,0.90);
    color:var(--ink);
    padding:14px 18px;
    border-radius:18px;
    box-shadow:0 12px 25px rgba(0,0,0,0.12);
    border:1px solid var(--border);
    max-width:260px;
    z-index:2;
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
@media (max-width:1050px){
    .about-section{grid-template-columns:1fr;}
    .about-image{min-height:430px;order:-1;}
    .about-text{padding:48px 34px 42px;}
    .about-text h1{font-size:46px;}
}
@media (max-width:768px){
    .about-wrapper{padding:88px 16px 34px;}
    .about-text{padding:38px 22px 30px;}
    .about-text h1{font-size:36px;margin-bottom:18px;}
    .about-text p{font-size:15.5px;line-height:1.9;}
    .back-btn{top:16px;left:16px;font-size:14px;padding:9px 14px;}
    .about-image{min-height:300px;}
    .image-badge{left:16px;right:16px;bottom:16px;max-width:none;}
}
</style>
</head>
<body>

<a href="javascript:history.back()" class="back-btn">← กลับหน้าหลัก</a>

<div class="about-wrapper">
    <section class="about-section">
        <div class="about-text">
            <h4><?php echo htmlspecialchars($about['section_tag']); ?></h4>
            <h1><?php echo htmlspecialchars($about['title']); ?></h1>

            <?php if (!empty($about['lead_text'])): ?>
                <p class="lead"><?php echo nl2br(htmlspecialchars($about['lead_text'])); ?></p>
            <?php endif; ?>

            <?php if (!empty($about['paragraph1'])): ?>
                <p><?php echo nl2br(htmlspecialchars($about['paragraph1'])); ?></p>
            <?php endif; ?>

            <?php if (!empty($about['paragraph2'])): ?>
                <p><?php echo nl2br(htmlspecialchars($about['paragraph2'])); ?></p>
            <?php endif; ?>

            <?php if (!empty($about['paragraph3'])): ?>
                <p><?php echo nl2br(htmlspecialchars($about['paragraph3'])); ?></p>
            <?php endif; ?>
        </div>

        <div class="about-image">
            <?php if (!empty($about['image_badge_title']) || !empty($about['image_badge_text'])): ?>
                <div class="image-badge">
                    <?php if (!empty($about['image_badge_title'])): ?>
                        <strong><?php echo htmlspecialchars($about['image_badge_title']); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($about['image_badge_text'])): ?>
                        <span><?php echo htmlspecialchars($about['image_badge_text']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

</body>
</html>
