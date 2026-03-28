<?php
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ── Hero banner ── */
$heroImage = "uploads/newbanner.jpg";
$resHero = $conn->query("SELECT image_path FROM site_banners ORDER BY id DESC LIMIT 1");
if ($resHero && $resHero->num_rows > 0) {
    $p = $resHero->fetch_assoc()['image_path'];
    if (!empty($p)) {
        $heroImage = (strpos($p, 'uploads/') === false) ? 'uploads/' . $p : $p;
    }
}
$heroImage .= '?v=' . time();

/* ── News preview ── */
$newsRows = [];
$resNews = $conn->query("SELECT title, image, created_at FROM news ORDER BY id DESC LIMIT 3");
if ($resNews) while ($r = $resNews->fetch_assoc()) $newsRows[] = $r;

/* ── User session ── */
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin    = ($_SESSION['user_role'] ?? '') === 'admin';
$userName   = $_SESSION['user_name'] ?? '';
$avatarInitial = strtoupper(mb_substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สถาบันวิจัยวลัยรุกขเวช — WRBRI</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet"/>

<style>
*{ box-sizing:border-box; margin:0; padding:0; }
:root{
  --ink:#1a1a2e; --gold:#c9a96e; --gold-dim:rgba(201,169,110,.12);
  --bg:#f5f1eb; --card:#fff; --muted:#7a7a8c; --border:#e8e4de;
  --shadow:0 8px 32px rgba(26,26,46,.09);
}
html{ scroll-behavior:smooth; }
body{ font-family:'Sarabun',sans-serif; background:var(--bg); color:var(--ink); }
a{ text-decoration:none; }

/* ═══════════════════════════════
   NAVBAR
═══════════════════════════════ */
.navbar{
  width:100%; background:#fff;
  padding:0 48px;
  display:flex; align-items:center; justify-content:space-between; gap:24px;
  position:sticky; top:0; z-index:200;
  box-shadow:0 1px 0 var(--border);
  height:76px;
}
.nav-brand{
  display:flex; align-items:center; gap:14px; flex-shrink:0;
}
.nav-brand img{ height:52px; }
.nav-brand-text{ line-height:1.35; }
.nav-brand-name{ font-size:1rem; font-weight:800; color:var(--ink); }
.nav-brand-sub{ font-size:0.7rem; color:var(--muted); }

.nav-links{
  display:flex; align-items:center; gap:4px;
  flex:1; justify-content:center;
}
.nav-links a{
  display:flex; align-items:center; gap:6px;
  padding:8px 14px; border-radius:8px;
  font-size:0.87rem; font-weight:600; color:#444;
  transition:all .2s;
}
.nav-links a:hover{ background:var(--gold-dim); color:var(--ink); }
.nav-links .nav-icon{ font-size:0.85rem; opacity:.7; }

.nav-actions{ display:flex; align-items:center; gap:10px; flex-shrink:0; }

/* Login button */
.btn-login{
  display:inline-flex; align-items:center; gap:7px;
  padding:9px 20px; background:var(--ink); color:#fff;
  border-radius:999px; font-size:0.82rem; font-weight:700;
  letter-spacing:.04em; transition:all .25s;
}
.btn-login:hover{ background:#2a2a4a; transform:translateY(-1px); box-shadow:0 6px 16px rgba(26,26,46,.2); }

/* User dropdown */
.user-menu{ position:relative; }
.user-trigger{
  display:flex; align-items:center; gap:10px;
  padding:5px 14px 5px 5px; border-radius:999px;
  border:1.5px solid var(--border); background:#fff;
  cursor:pointer; transition:all .2s; user-select:none;
}
.user-trigger:hover{ border-color:var(--gold); box-shadow:0 2px 10px rgba(201,169,110,.15); }
.user-avatar{
  width:36px; height:36px; border-radius:50%;
  background:var(--ink); color:var(--gold);
  display:flex; align-items:center; justify-content:center;
  font-size:0.85rem; font-weight:700; flex-shrink:0; overflow:hidden;
}
.user-avatar img{ width:100%; height:100%; object-fit:cover; border-radius:50%; }
.user-name{ font-size:0.85rem; font-weight:600; color:var(--ink); max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.user-chevron{ font-size:0.7rem; color:var(--muted); transition:transform .2s; }
.user-menu.open .user-chevron{ transform:rotate(180deg); }

.user-dropdown{
  position:absolute; top:calc(100% + 10px); right:0;
  background:#fff; border-radius:16px;
  box-shadow:0 16px 48px rgba(26,26,46,.15), 0 2px 8px rgba(26,26,46,.06);
  border:1px solid var(--border); min-width:230px; padding:8px;
  opacity:0; visibility:hidden; transform:translateY(-8px);
  transition:all .22s ease; z-index:300;
}
.user-menu.open .user-dropdown{ opacity:1; visibility:visible; transform:translateY(0); }

.dd-header{ padding:12px 14px 10px; border-bottom:1px solid var(--border); margin-bottom:6px; }
.dd-name{ font-size:0.88rem; font-weight:700; color:var(--ink); }
.dd-email{ font-size:0.74rem; color:var(--muted); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dd-role{
  display:inline-flex; align-items:center; gap:4px; margin-top:6px;
  padding:2px 8px; border-radius:20px; font-size:0.66rem; font-weight:700; letter-spacing:.06em;
}
.role-admin{ background:rgba(201,169,110,.15); color:#a07c3a; }
.role-user{ background:#f0f0f0; color:var(--muted); }

.dd-item{
  display:flex; align-items:center; gap:10px; padding:9px 14px;
  border-radius:9px; font-size:0.84rem; font-weight:500; color:var(--ink);
  cursor:pointer; transition:background .15s;
}
.dd-item:hover{ background:var(--bg); }
.dd-item.danger{ color:#dc2626; }
.dd-item.danger:hover{ background:#fef2f2; }
.dd-icon{
  width:30px; height:30px; border-radius:8px; background:var(--bg);
  display:flex; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0;
}
.dd-item.danger .dd-icon{ background:#fef2f2; }
.dd-divider{ border:none; border-top:1px solid var(--border); margin:6px 0; }

/* ═══════════════════════════════
   HERO
═══════════════════════════════ */
.hero{
  position:relative; height:520px; overflow:hidden;
  background:url('<?php echo htmlspecialchars($heroImage); ?>') center/cover no-repeat;
}
.hero-overlay{
  position:absolute; inset:0;
  background:linear-gradient(135deg, rgba(26,26,46,.82) 0%, rgba(26,26,46,.55) 50%, rgba(26,26,46,.3) 100%);
}
.hero-grid{
  position:absolute; inset:0; pointer-events:none;
  background-image:repeating-linear-gradient(90deg,rgba(201,169,110,.04) 0,rgba(201,169,110,.04) 1px,transparent 1px,transparent 80px);
}
.hero-content{
  position:relative; z-index:2;
  height:100%; display:flex; align-items:center;
  max-width:1200px; margin:0 auto; padding:0 48px;
}
.hero-text{ max-width:620px; }
.hero-eyebrow{
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 14px; border-radius:999px;
  background:rgba(201,169,110,.2); border:1px solid rgba(201,169,110,.35);
  font-size:0.7rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase;
  color:var(--gold); margin-bottom:20px;
}
.hero-title{
  font-family:'Playfair Display',serif; font-style:italic;
  font-size:clamp(2rem,4vw,3.2rem); color:#fff; line-height:1.2; margin-bottom:16px;
}
.hero-sub{ font-size:1rem; color:rgba(255,255,255,.7); line-height:1.8; margin-bottom:32px; }
.hero-actions{ display:flex; gap:14px; flex-wrap:wrap; }
.hero-btn{
  display:inline-flex; align-items:center; gap:8px;
  padding:13px 28px; border-radius:999px;
  font-size:0.88rem; font-weight:700; letter-spacing:.04em;
  transition:all .25s;
}
.hero-btn-primary{
  background:var(--gold); color:var(--ink);
}
.hero-btn-primary:hover{ background:#e0bb7a; transform:translateY(-2px); box-shadow:0 8px 24px rgba(201,169,110,.4); }
.hero-btn-ghost{
  background:rgba(255,255,255,.12); color:#fff;
  border:1.5px solid rgba(255,255,255,.3); backdrop-filter:blur(8px);
}
.hero-btn-ghost:hover{ background:rgba(255,255,255,.22); transform:translateY(-2px); }

/* ═══════════════════════════════
   STATS BAR
═══════════════════════════════ */
.stats-bar{
  background:var(--ink); padding:28px 48px;
  display:flex; justify-content:center; gap:0;
}
.stat-item{
  flex:1; max-width:220px; text-align:center;
  padding:0 32px; position:relative;
}
.stat-item:not(:last-child)::after{
  content:''; position:absolute; right:0; top:15%; height:70%;
  width:1px; background:rgba(255,255,255,.12);
}
.stat-num{ font-family:'Playfair Display',serif; font-size:2.2rem; color:var(--gold); font-weight:600; }
.stat-lbl{ font-size:0.72rem; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:.1em; margin-top:4px; }

/* ═══════════════════════════════
   SECTION WRAPPER
═══════════════════════════════ */
.section{ max-width:1200px; margin:0 auto; padding:80px 48px; }
.section-header{ text-align:center; margin-bottom:56px; }
.section-badge{
  display:inline-block; padding:5px 14px; border-radius:999px;
  background:var(--gold-dim); border:1px solid rgba(201,169,110,.25);
  font-size:0.68rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase;
  color:var(--gold); margin-bottom:14px;
}
.section-title{ font-family:'Playfair Display',serif; font-style:italic; font-size:2.2rem; color:var(--ink); margin-bottom:12px; }
.section-sub{ font-size:1rem; color:var(--muted); max-width:540px; margin:0 auto; line-height:1.8; }
.section-line{ width:50px; height:3px; background:var(--gold); border-radius:2px; margin:16px auto 0; }

/* ═══════════════════════════════
   ABOUT SECTION
═══════════════════════════════ */
.about-section{ background:#fff; }
.about-grid{ display:grid; grid-template-columns:1fr 1fr; gap:32px; }

.about-main-card{
  grid-column:1/-1; border-radius:20px; overflow:hidden;
  background:var(--ink); position:relative;
  display:grid; grid-template-columns:1fr 1.2fr; min-height:360px;
}
.about-main-text{
  padding:48px 44px; display:flex; flex-direction:column; justify-content:center;
  position:relative; z-index:1;
}
.about-main-text::before{
  content:''; position:absolute; width:280px; height:280px; border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,.15) 0%,transparent 70%);
  top:-80px; right:-60px;
}
.about-tag{ font-size:0.65rem; font-weight:700; letter-spacing:.2em; text-transform:uppercase; color:var(--gold); margin-bottom:14px; }
.about-main-title{ font-family:'Playfair Display',serif; font-style:italic; font-size:1.9rem; color:#fff; line-height:1.3; margin-bottom:16px; }
.about-main-desc{ font-size:0.9rem; color:rgba(255,255,255,.65); line-height:1.9; }
.about-main-img{ position:relative; overflow:hidden; }
.about-main-img img{ width:100%; height:100%; object-fit:cover; }

.info-card{
  background:var(--bg); border:1px solid var(--border);
  border-radius:18px; padding:28px 24px;
  transition:all .25s; position:relative; overflow:hidden;
}
.info-card::before{
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
  background:linear-gradient(90deg, var(--gold), rgba(201,169,110,.3));
  opacity:0; transition:opacity .25s;
}
.info-card:hover{ transform:translateY(-4px); box-shadow:var(--shadow); border-color:rgba(201,169,110,.3); }
.info-card:hover::before{ opacity:1; }
.info-card-icon{ font-size:1.8rem; margin-bottom:14px; }
.info-card-img{ width:100%; height:180px; object-fit:cover; border-radius:12px; margin-bottom:16px; }
.info-card h3{ font-size:1.05rem; font-weight:800; color:var(--ink); margin-bottom:10px; }
.info-card p{ font-size:0.85rem; color:var(--muted); line-height:1.8; }

/* ═══════════════════════════════
   QUICK LINKS
═══════════════════════════════ */
.quick-section{ background:var(--bg); }
.quick-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:20px; }
.quick-card{
  background:#fff; border-radius:18px; padding:32px 24px;
  border:1px solid var(--border); text-align:center;
  transition:all .25s; display:flex; flex-direction:column; align-items:center; gap:14px;
}
.quick-card:hover{ transform:translateY(-5px); box-shadow:var(--shadow); border-color:var(--gold); }
.quick-card-icon{
  width:60px; height:60px; border-radius:16px;
  background:var(--gold-dim); border:1.5px solid rgba(201,169,110,.25);
  display:flex; align-items:center; justify-content:center; font-size:1.6rem;
}
.quick-card:hover .quick-card-icon{ background:var(--ink); border-color:var(--ink); }
.quick-card h4{ font-size:0.95rem; font-weight:800; color:var(--ink); }
.quick-card p{ font-size:0.78rem; color:var(--muted); line-height:1.6; }
.quick-card-arrow{
  display:inline-flex; align-items:center; gap:5px;
  font-size:0.75rem; font-weight:700; color:var(--gold); margin-top:4px;
}

/* ═══════════════════════════════
   NEWS SECTION
═══════════════════════════════ */
.news-section{ background:#fff; }
.news-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
.news-card{
  border-radius:18px; overflow:hidden;
  border:1px solid var(--border); background:var(--bg);
  transition:all .25s;
}
.news-card:hover{ transform:translateY(-4px); box-shadow:var(--shadow); }
.news-card-img{ width:100%; height:200px; object-fit:cover; background:#ddd; display:block; }
.news-card-img-placeholder{
  width:100%; height:200px; background:linear-gradient(135deg,var(--ink),#2a2a4a);
  display:flex; align-items:center; justify-content:center; font-size:2.5rem;
}
.news-card-body{ padding:20px; }
.news-card-date{ font-size:0.72rem; color:var(--muted); margin-bottom:8px; }
.news-card-title{ font-size:0.95rem; font-weight:700; color:var(--ink); line-height:1.5; }
.news-see-all{
  display:inline-flex; align-items:center; gap:8px; margin-top:40px;
  padding:12px 28px; border-radius:999px;
  border:1.5px solid var(--border); color:var(--ink); font-weight:700; font-size:0.85rem;
  transition:all .2s;
}
.news-see-all:hover{ border-color:var(--gold); color:var(--gold); }
.news-center{ text-align:center; }

/* ═══════════════════════════════
   FOOTER
═══════════════════════════════ */
.footer{ background:var(--ink); }
.footer-top{
  max-width:1200px; margin:0 auto; padding:64px 48px 48px;
  display:grid; grid-template-columns:1.6fr 1fr 1fr; gap:48px;
}
.footer-brand-line{ width:32px; height:3px; background:var(--gold); border-radius:2px; margin-bottom:14px; }
.footer-brand-name{ font-family:'Playfair Display',serif; font-style:italic; font-size:1.5rem; color:#fff; margin-bottom:6px; }
.footer-brand-sub{ font-size:0.7rem; letter-spacing:.15em; text-transform:uppercase; color:rgba(255,255,255,.3); margin-bottom:18px; }
.footer-desc{ font-size:0.85rem; color:rgba(255,255,255,.5); line-height:1.9; }

.footer-col h4{ font-size:0.72rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:var(--gold); margin-bottom:18px; }
.footer-links{ list-style:none; display:flex; flex-direction:column; gap:10px; }
.footer-links a{ font-size:0.85rem; color:rgba(255,255,255,.5); transition:color .2s; }
.footer-links a:hover{ color:#fff; }
.footer-contact-item{ display:flex; align-items:flex-start; gap:10px; margin-bottom:12px; }
.footer-contact-icon{ color:var(--gold); font-size:0.85rem; margin-top:2px; flex-shrink:0; }
.footer-contact-text{ font-size:0.82rem; color:rgba(255,255,255,.5); line-height:1.6; }

.footer-bottom{
  border-top:1px solid rgba(255,255,255,.07);
  padding:20px 48px; text-align:center;
  font-size:0.75rem; color:rgba(255,255,255,.25);
  max-width:1200px; margin:0 auto;
}

/* ═══════════════════════════════
   RESPONSIVE
═══════════════════════════════ */
@media(max-width:1024px){
  .quick-grid{ grid-template-columns:repeat(2,1fr); }
  .news-grid{ grid-template-columns:repeat(2,1fr); }
  .news-grid .news-card:last-child{ display:none; }
  .footer-top{ grid-template-columns:1fr 1fr; }
}
@media(max-width:768px){
  .navbar{ padding:0 20px; height:64px; }
  .nav-brand img{ height:40px; }
  .nav-brand-sub{ display:none; }
  .nav-links{ display:none; }
  .hero{ height:420px; }
  .hero-content{ padding:0 24px; }
  .section{ padding:56px 24px; }
  .about-grid{ grid-template-columns:1fr; }
  .about-main-card{ grid-template-columns:1fr; }
  .about-main-img{ height:220px; }
  .quick-grid{ grid-template-columns:repeat(2,1fr); }
  .news-grid{ grid-template-columns:1fr; }
  .stats-bar{ padding:20px 24px; gap:0; flex-wrap:wrap; }
  .stat-item{ min-width:50%; padding:16px 0; }
  .footer-top{ grid-template-columns:1fr; gap:32px; padding:40px 24px 32px; }
  .footer-bottom{ padding:16px 24px; }
  .user-name{ display:none; }
}
@media(max-width:480px){
  .quick-grid{ grid-template-columns:1fr 1fr; }
  .hero-actions{ flex-direction:column; }
  .hero-btn{ justify-content:center; }
}
</style>
</head>
<body>

<!-- ════════════════════════════════
     NAVBAR
════════════════════════════════ -->
<nav class="navbar">
  <div class="nav-brand">
    <img src="Logo.png" alt="WRBRI Logo">
    <div class="nav-brand-text">
      <div class="nav-brand-name">สถาบันวิจัยวลัยรุกขเวช</div>
      <div class="nav-brand-sub">มหาวิทยาลัยมหาสารคาม</div>
    </div>
  </div>

  <div class="nav-links">
    <a href="news.php"><span class="nav-icon">📰</span> ข่าวสาร</a>
    <a href="view_data.php"><span class="nav-icon">📋</span> ลงทะเบียน</a>
    <a href="https://docs.google.com/forms/d/e/1FAIpQLSdukofM-5EFzR1Zddip7uZJ-pnBmLnhXCNyABKIEY8cwUxzyQ/viewform?usp=dialog" target="_blank"><span class="nav-icon">📝</span> แบบประเมิน</a>
    <a href="calendar.php"><span class="nav-icon">📅</span> ปฏิทิน</a>
    <a href="about_us.php"><span class="nav-icon">🌿</span> เกี่ยวกับ</a>
    <a href="booking_room.php"><span class="nav-icon">🏨</span> จองห้องพัก</a>
  </div>

  <div class="nav-actions">
    <?php if ($isLoggedIn): ?>
      <div class="user-menu" id="userMenu">
        <div class="user-trigger" onclick="toggleMenu()">
          <div class="user-avatar">
            <?php
              $avatarRow = $conn->query("SELECT avatar FROM users WHERE id=".(int)$_SESSION['user_id'])->fetch_assoc();
              if (!empty($avatarRow['avatar'])):
            ?>
              <img src="<?= htmlspecialchars($avatarRow['avatar']) ?>" alt="avatar">
            <?php else: ?>
              <?= $avatarInitial ?>
            <?php endif; ?>
          </div>
          <span class="user-name"><?= htmlspecialchars($userName) ?></span>
          <span class="user-chevron">▾</span>
        </div>

        <div class="user-dropdown">
          <div class="dd-header">
            <div class="dd-name"><?= htmlspecialchars($userName) ?></div>
            <div class="dd-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
            <span class="dd-role <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
              <?= $isAdmin ? '⚡ Administrator' : '👤 Member' ?>
            </span>
          </div>

          <a href="profile.php" class="dd-item">
            <div class="dd-icon">👤</div> โปรไฟล์ของฉัน
          </a>
          <a href="booking_status.php" class="dd-item">
            <div class="dd-icon">🛎️</div> สถานะการจอง
          </a>
          <?php if ($isAdmin): ?>
            <hr class="dd-divider">
            <a href="admin_dashboard.php" class="dd-item">
              <div class="dd-icon">📊</div> Admin Dashboard
            </a>
          <?php endif; ?>
          <hr class="dd-divider">
          <a href="logout.php" class="dd-item danger">
            <div class="dd-icon">🚪</div> ออกจากระบบ
          </a>
        </div>
      </div>
    <?php else: ?>
      <a href="login.php" class="btn-login">เข้าสู่ระบบ</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ════════════════════════════════
     HERO
════════════════════════════════ -->
<section class="hero">
  <div class="hero-overlay"></div>
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-text">
      <div class="hero-eyebrow">✦ สถาบันวิจัยวลัยรุกขเวช มมส</div>
      <h1 class="hero-title">แหล่งเรียนรู้<br>ด้านธรรมชาติและ<br>ความหลากหลายทางชีวภาพ</h1>
      <p class="hero-sub">มุ่งมั่นส่งเสริมการวิจัย การเรียนรู้ และการบริการวิชาการแก่ชุมชน เพื่อการพัฒนาที่ยั่งยืน</p>
      <div class="hero-actions">
        <a href="booking_room.php" class="hero-btn hero-btn-primary">🏨 จองห้องพัก</a>
        <a href="view_data.php" class="hero-btn hero-btn-ghost">📋 ลงทะเบียนกิจกรรม</a>
      </div>
    </div>
  </div>
</section>

<!-- ════════════════════════════════
     STATS BAR
════════════════════════════════ -->
<div class="stats-bar">
  <div class="stat-item">
    <div class="stat-num">30+</div>
    <div class="stat-lbl">ปีแห่งการวิจัย</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">500+</div>
    <div class="stat-lbl">งานวิจัยที่ตีพิมพ์</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">10,000+</div>
    <div class="stat-lbl">ผู้เข้าชมต่อปี</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">50+</div>
    <div class="stat-lbl">นักวิจัยและบุคลากร</div>
  </div>
</div>

<!-- ════════════════════════════════
     ABOUT SECTION
════════════════════════════════ -->
<section class="about-section">
  <div class="section">
    <div class="section-header">
      <div class="section-badge">เกี่ยวกับเรา</div>
      <h2 class="section-title">สถาบันวิจัยวลัยรุกขเวช</h2>
      <p class="section-sub">หน่วยงานที่มุ่งเน้นการส่งเสริมการเรียนรู้ การวิจัย และการบริการวิชาการแก่ชุมชน</p>
      <div class="section-line"></div>
    </div>

    <div class="about-grid">
      <!-- Main card -->
      <div class="about-main-card">
        <div class="about-main-text">
          <div class="about-tag">Our Mission</div>
          <h3 class="about-main-title">เรียนรู้จากธรรมชาติ<br>เพื่อชุมชนที่ยั่งยืน</h3>
          <p class="about-main-desc">
            สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม เป็นหน่วยงานที่มุ่งเน้นการส่งเสริมการเรียนรู้
            การวิจัย และการบริการวิชาการแก่ชุมชน โดยเฉพาะในด้านทรัพยากรธรรมชาติ ความหลากหลายทางชีวภาพ
            และภูมิปัญญาท้องถิ่น เพื่อสร้างองค์ความรู้ที่เป็นประโยชน์ต่อสังคมและการพัฒนาท้องถิ่นอย่างยั่งยืน
          </p>
        </div>
        <div class="about-main-img">
          <img src="ka0.jpg" alt="สถาบันวิจัยวลัยรุกขเวช">
        </div>
      </div>

      <!-- 4 info cards -->
      <div class="info-card">
        <div class="info-card-icon">📢</div>
        <img src="ka1.jpg" class="info-card-img" alt="กิจกรรม">
        <h3>งานประชาสัมพันธ์และกิจกรรม</h3>
        <p>เผยแพร่ข่าวสาร กิจกรรม การอบรม สัมมนา และโครงการต่างๆ ของสถาบัน เพื่อให้บุคลากร นักศึกษา และประชาชนเข้าถึงข้อมูลได้อย่างสะดวก</p>
      </div>

      <div class="info-card">
        <div class="info-card-icon">🔬</div>
        <img src="ka2.jpg" class="info-card-img" alt="วิจัย">
        <h3>งานวิจัยและองค์ความรู้</h3>
        <p>สนับสนุนและเผยแพร่งานวิจัยด้านธรรมชาติ พืชสมุนไพร สิ่งแวดล้อม และความหลากหลายทางชีวภาพ เพื่อสร้างคุณค่าเชิงวิชาการ</p>
      </div>

      <div class="info-card">
        <div class="info-card-icon">🤝</div>
        <img src="ka3.jpg" class="info-card-img" alt="บริการวิชาการ">
        <h3>การบริการวิชาการแก่สังคม</h3>
        <p>นำองค์ความรู้จากงานวิจัยไปต่อยอดสู่การบริการวิชาการ และกิจกรรมที่เชื่อมโยงกับชุมชน โรงเรียน และหน่วยงานภาครัฐ</p>
      </div>

      <div class="info-card">
        <div class="info-card-icon">🏛️</div>
        <img src="ka4.jpg" class="info-card-img" alt="ภาพลักษณ์">
        <h3>การสร้างภาพลักษณ์องค์กร</h3>
        <p>นำเสนอผลงาน ความร่วมมือ และกิจกรรมสำคัญของสถาบัน เพื่อสะท้อนบทบาทของศูนย์กลางด้านการวิจัย</p>
      </div>
    </div>
  </div>
</section>

<!-- ════════════════════════════════
     QUICK LINKS
════════════════════════════════ -->
<section class="quick-section">
  <div class="section">
    <div class="section-header">
      <div class="section-badge">บริการ</div>
      <h2 class="section-title">บริการของเรา</h2>
      <p class="section-sub">เข้าถึงบริการและข้อมูลต่างๆ ของสถาบันได้อย่างสะดวก</p>
      <div class="section-line"></div>
    </div>

    <div class="quick-grid">
      <a href="booking_room.php" class="quick-card">
        <div class="quick-card-icon">🏨</div>
        <h4>จองห้องพัก</h4>
        <p>ตรวจสอบและจองห้องพักภายในสถาบัน รองรับทั้งบุคลากรและผู้เยี่ยมชม</p>
        <div class="quick-card-arrow">ดูห้องพัก →</div>
      </a>

      <a href="news.php" class="quick-card">
        <div class="quick-card-icon">📰</div>
        <h4>ข่าวสารและกิจกรรม</h4>
        <p>ติดตามข่าวสาร กิจกรรม และประกาศล่าสุดจากสถาบัน</p>
        <div class="quick-card-arrow">อ่านข่าวสาร →</div>
      </a>

      <a href="view_data.php" class="quick-card">
        <div class="quick-card-icon">📋</div>
        <h4>ลงทะเบียนกิจกรรม</h4>
        <p>ลงทะเบียนเข้าร่วมกิจกรรม ทัศนศึกษา และการอบรมต่างๆ</p>
        <div class="quick-card-arrow">ลงทะเบียน →</div>
      </a>

      <a href="calendar.php" class="quick-card">
        <div class="quick-card-icon">📅</div>
        <h4>ปฏิทินกิจกรรม</h4>
        <p>ดูสถิติและรายละเอียดผู้เข้าชมรายวัน รายเดือน</p>
        <div class="quick-card-arrow">ดูปฏิทิน →</div>
      </a>
    </div>
  </div>
</section>

<!-- ════════════════════════════════
     NEWS SECTION
════════════════════════════════ -->
<?php if (!empty($newsRows)): ?>
<section class="news-section">
  <div class="section">
    <div class="section-header">
      <div class="section-badge">ข่าวสาร</div>
      <h2 class="section-title">ข่าวสารล่าสุด</h2>
      <p class="section-sub">ติดตามความเคลื่อนไหวและกิจกรรมล่าสุดของสถาบัน</p>
      <div class="section-line"></div>
    </div>

    <div class="news-grid">
      <?php foreach ($newsRows as $nr): ?>
      <a href="news.php" class="news-card">
        <?php if (!empty($nr['image'])): ?>
          <img src="uploads/<?= htmlspecialchars($nr['image']) ?>" class="news-card-img" alt="">
        <?php else: ?>
          <div class="news-card-img-placeholder">📰</div>
        <?php endif; ?>
        <div class="news-card-body">
          <div class="news-card-date"><?= date('d M Y', strtotime($nr['created_at'])) ?></div>
          <div class="news-card-title"><?= htmlspecialchars($nr['title']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="news-center">
      <a href="news.php" class="news-see-all">ดูข่าวสารทั้งหมด →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ════════════════════════════════
     FOOTER
════════════════════════════════ -->
<footer class="footer">
  <div class="footer-top">
    <div>
      <div class="footer-brand-line"></div>
      <div class="footer-brand-name">WRBRI</div>
      <div class="footer-brand-sub">สถาบันวิจัยวลัยรุกขเวช</div>
      <p class="footer-desc">
        มุ่งมั่นส่งเสริมการเรียนรู้ การวิจัย และการจัดกิจกรรม
        เพื่อพัฒนาศักยภาพด้านวิทยาศาสตร์ เทคโนโลยี
        และการบริการวิชาการแก่ชุมชนและสังคม
      </p>
    </div>

    <div class="footer-col">
      <h4>เมนูหลัก</h4>
      <ul class="footer-links">
        <li><a href="news.php">ข่าวสาร</a></li>
        <li><a href="view_data.php">ลงทะเบียน</a></li>
        <li><a href="calendar.php">ปฏิทิน</a></li>
        <li><a href="about_us.php">เกี่ยวกับสถาบัน</a></li>
        <li><a href="booking_room.php">จองห้องพัก</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>ติดต่อเรา</h4>
      <div class="footer-contact-item">
        <span class="footer-contact-icon">✉</span>
        <span class="footer-contact-text">walairukhavej@msu.ac.th</span>
      </div>
      <div class="footer-contact-item">
        <span class="footer-contact-icon">📞</span>
        <span class="footer-contact-text">043 719 816</span>
      </div>
      <div class="footer-contact-item">
        <span class="footer-contact-icon">📍</span>
        <span class="footer-contact-text">Kantharawichai, Maha Sarakham 44150</span>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    © <?= date('Y') ?> สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม — All Rights Reserved
  </div>
</footer>

<script>
function toggleMenu() {
  document.getElementById('userMenu').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const menu = document.getElementById('userMenu');
  if (menu && !menu.contains(e.target)) menu.classList.remove('open');
});
</script>

</body>
</html>
<?php $conn->close(); ?>
