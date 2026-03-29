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
$avatarRow = $isLoggedIn ? $conn->query("SELECT avatar FROM users WHERE id=".(int)$_SESSION['user_id'])->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สถาบันวิจัยวลัยรุกขเวช — WRBRI</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,700&display=swap" rel="stylesheet"/>
<style>
/* ─── Reset & Variables ─────────────────── */
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --ink:#1a1a2e;
  --ink2:#2a2a4e;
  --gold:#c9a96e;
  --gold-light:#e8c98a;
  --gold-dim:rgba(201,169,110,.12);
  --gold-border:rgba(201,169,110,.28);
  --bg:#f5f1eb;
  --bg2:#f0ece4;
  --card:#fff;
  --muted:#7a7a8c;
  --border:#e8e4de;
  --shadow-sm:0 2px 12px rgba(26,26,46,.07);
  --shadow:0 8px 32px rgba(26,26,46,.10);
  --shadow-lg:0 20px 60px rgba(26,26,46,.14);
  --r:16px;
  --r-sm:10px;
}
html{scroll-behavior:smooth;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit;}
img{display:block;}

/* ─── Navbar ─────────────────────────────── */
.navbar{
  position:sticky;top:0;z-index:500;
  background:rgba(255,255,255,.92);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  height:68px;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 clamp(16px,4vw,52px);
  gap:16px;
}
.nav-brand{display:flex;align-items:center;gap:12px;flex-shrink:0;}
.nav-brand img{height:46px;width:auto;}
.nav-brand-text{line-height:1.3;}
.nav-brand-name{font-size:.95rem;font-weight:800;color:var(--ink);}
.nav-brand-sub{font-size:.65rem;color:var(--muted);white-space:nowrap;}

.nav-center{display:flex;align-items:center;gap:2px;flex:1;justify-content:center;}
.nav-center a{
  display:flex;align-items:center;gap:5px;
  padding:7px 13px;border-radius:999px;
  font-size:.84rem;font-weight:600;color:var(--muted);
  transition:all .2s;white-space:nowrap;
}
.nav-center a:hover,.nav-center a.active{background:var(--gold-dim);color:var(--ink);}
.nav-icon{font-size:.78rem;}

.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}

/* Login btn */
.btn-nav-login{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 20px;background:var(--ink);color:#fff;
  border-radius:999px;font-size:.82rem;font-weight:700;letter-spacing:.03em;
  transition:all .22s;border:none;cursor:pointer;
}
.btn-nav-login:hover{background:var(--ink2);transform:translateY(-1px);box-shadow:0 6px 18px rgba(26,26,46,.22);}

/* Hamburger */
.nav-ham{
  display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;
  width:40px;height:40px;border-radius:10px;background:var(--gold-dim);
  cursor:pointer;border:1.5px solid var(--gold-border);flex-shrink:0;
}
.nav-ham span{width:18px;height:2px;background:var(--ink);border-radius:2px;transition:all .3s;}
.nav-ham.open span:nth-child(1){transform:translateY(7px) rotate(45deg);}
.nav-ham.open span:nth-child(2){opacity:0;}
.nav-ham.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}

/* Mobile drawer */
.nav-drawer{
  display:none;
  position:fixed;top:68px;left:0;right:0;bottom:0;z-index:400;
  background:rgba(26,26,46,.45);backdrop-filter:blur(4px);
  opacity:0;visibility:hidden;transition:all .28s;
}
.nav-drawer.open{opacity:1;visibility:visible;}
.nav-drawer-inner{
  background:#fff;
  padding:16px;
  display:flex;flex-direction:column;gap:4px;
  box-shadow:var(--shadow-lg);
}
.nav-drawer a{
  display:flex;align-items:center;gap:12px;
  padding:13px 16px;border-radius:12px;
  font-size:.95rem;font-weight:600;color:var(--ink);
  transition:background .15s;
}
.nav-drawer a:hover{background:var(--gold-dim);}
.nav-drawer-icon{
  width:36px;height:36px;border-radius:10px;
  background:var(--gold-dim);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;
}
.drawer-divider{height:1px;background:var(--border);margin:8px 0;}

/* User dropdown */
.user-menu{position:relative;}
.user-trigger{
  display:flex;align-items:center;gap:9px;
  padding:4px 12px 4px 4px;border-radius:999px;
  border:1.5px solid var(--border);background:#fff;
  cursor:pointer;transition:all .2s;user-select:none;
}
.user-trigger:hover{border-color:var(--gold);box-shadow:0 2px 12px rgba(201,169,110,.18);}
.user-avatar{
  width:34px;height:34px;border-radius:50%;
  background:var(--ink);color:var(--gold);
  display:flex;align-items:center;justify-content:center;
  font-size:.82rem;font-weight:700;overflow:hidden;flex-shrink:0;
}
.user-avatar img{width:100%;height:100%;object-fit:cover;}
.user-name{font-size:.83rem;font-weight:600;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.user-chevron{font-size:.68rem;color:var(--muted);transition:transform .22s;}
.user-menu.open .user-chevron{transform:rotate(180deg);}
.user-dropdown{
  position:absolute;top:calc(100% + 8px);right:0;
  background:#fff;border-radius:18px;
  box-shadow:var(--shadow-lg),0 2px 8px rgba(26,26,46,.06);
  border:1px solid var(--border);min-width:230px;padding:8px;
  opacity:0;visibility:hidden;transform:translateY(-6px);
  transition:all .22s;z-index:600;
}
.user-menu.open .user-dropdown{opacity:1;visibility:visible;transform:translateY(0);}
.dd-header{padding:12px 14px 10px;border-bottom:1px solid var(--border);margin-bottom:6px;}
.dd-name{font-size:.87rem;font-weight:700;}
.dd-email{font-size:.72rem;color:var(--muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.dd-role{
  display:inline-flex;align-items:center;gap:4px;margin-top:6px;
  padding:2px 8px;border-radius:20px;font-size:.64rem;font-weight:700;letter-spacing:.06em;
}
.role-admin{background:rgba(201,169,110,.15);color:#a07c3a;}
.role-user{background:#f0f0f0;color:var(--muted);}
.dd-item{
  display:flex;align-items:center;gap:10px;padding:9px 14px;
  border-radius:9px;font-size:.83rem;font-weight:500;
  cursor:pointer;transition:background .15s;
}
.dd-item:hover{background:var(--bg);}
.dd-item.danger{color:#dc2626;}
.dd-item.danger:hover{background:#fef2f2;}
.dd-icon{
  width:30px;height:30px;border-radius:8px;background:var(--bg);
  display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;
}
.dd-item.danger .dd-icon{background:#fef2f2;}
.dd-divider{border:none;border-top:1px solid var(--border);margin:6px 0;}

/* ─── Hero ────────────────────────────────── */
.hero{
  position:relative;
  height:clamp(480px,60vh,620px);
  overflow:hidden;
  background:var(--ink);
}
.hero-bg{
  position:absolute;inset:0;
  background-image:url('<?php echo htmlspecialchars($heroImage); ?>');
  background-size:cover;background-position:center;
  transform:scale(1.04);
  transition:transform 8s ease;
}
.hero:hover .hero-bg{transform:scale(1);}
.hero-overlay{
  position:absolute;inset:0;
  background:linear-gradient(
    125deg,
    rgba(26,26,46,.88) 0%,
    rgba(26,26,46,.62) 45%,
    rgba(26,26,46,.28) 100%
  );
}
.hero-particles{
  position:absolute;inset:0;pointer-events:none;
  background-image:
    radial-gradient(circle at 15% 30%, rgba(201,169,110,.08) 0%, transparent 40%),
    radial-gradient(circle at 85% 70%, rgba(201,169,110,.06) 0%, transparent 35%),
    repeating-linear-gradient(90deg, rgba(201,169,110,.025) 0, rgba(201,169,110,.025) 1px, transparent 1px, transparent 72px),
    repeating-linear-gradient(0deg, rgba(201,169,110,.015) 0, rgba(201,169,110,.015) 1px, transparent 1px, transparent 72px);
}
.hero-content{
  position:relative;z-index:2;height:100%;
  display:flex;align-items:center;
  max-width:1200px;margin:0 auto;
  padding:0 clamp(20px,5vw,52px);
}
.hero-text{max-width:600px;}
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;
  padding:6px 14px;border-radius:999px;
  background:rgba(201,169,110,.18);border:1px solid rgba(201,169,110,.38);
  font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  color:var(--gold);margin-bottom:22px;backdrop-filter:blur(8px);
}
.hero-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--gold);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.3);}}
.hero-title{
  font-family:'Playfair Display',serif;
  font-size:clamp(1.9rem,4.5vw,3.4rem);
  color:#fff;line-height:1.18;margin-bottom:18px;
  font-weight:700;
}
.hero-title span{color:var(--gold);font-style:italic;}
.hero-sub{
  font-size:clamp(.88rem,1.5vw,1rem);
  color:rgba(255,255,255,.68);line-height:1.85;
  margin-bottom:36px;max-width:500px;
}
.hero-cta{display:flex;gap:14px;flex-wrap:wrap;}
.hero-btn{
  display:inline-flex;align-items:center;gap:9px;
  padding:13px 26px;border-radius:999px;
  font-size:.87rem;font-weight:700;letter-spacing:.03em;
  transition:all .25s;
}
.hero-btn-primary{background:var(--gold);color:var(--ink);}
.hero-btn-primary:hover{background:var(--gold-light);transform:translateY(-2px);box-shadow:0 10px 30px rgba(201,169,110,.45);}
.hero-btn-ghost{
  background:rgba(255,255,255,.1);color:#fff;
  border:1.5px solid rgba(255,255,255,.28);backdrop-filter:blur(10px);
}
.hero-btn-ghost:hover{background:rgba(255,255,255,.2);transform:translateY(-2px);}
.hero-btn-register{
  background:#fff;color:var(--ink);
  border:none;
  box-shadow:0 4px 20px rgba(0,0,0,.18),0 1px 4px rgba(0,0,0,.1);
  position:relative;overflow:hidden;
}
.hero-btn-register::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(90deg,transparent 0%,rgba(201,169,110,.18) 50%,transparent 100%);
  transform:translateX(-100%);transition:transform .5s ease;
}
.hero-btn-register:hover{
  background:#fff;color:var(--gold);
  transform:translateY(-3px);
  box-shadow:0 10px 32px rgba(0,0,0,.22),0 2px 8px rgba(201,169,110,.25);
}
.hero-btn-register:hover::before{transform:translateX(100%);}
.hero-btn-register .reg-dot{
  width:8px;height:8px;border-radius:50%;
  background:var(--gold);flex-shrink:0;
  box-shadow:0 0 0 0 rgba(201,169,110,.6);
  animation:pulse-dot 1.8s ease-in-out infinite;
}
@keyframes pulse-dot{
  0%,100%{box-shadow:0 0 0 0 rgba(201,169,110,.6);}
  50%{box-shadow:0 0 0 6px rgba(201,169,110,0);}
}

/* Scroll hint */
.hero-scroll{
  position:absolute;bottom:28px;left:50%;transform:translateX(-50%);
  display:flex;flex-direction:column;align-items:center;gap:8px;
  color:rgba(255,255,255,.4);font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;
  z-index:2;
}
.hero-scroll-line{width:1px;height:36px;background:linear-gradient(to bottom,rgba(255,255,255,.4),transparent);}

/* ─── Stats Bar ──────────────────────────── */
.stats-bar{
  background:linear-gradient(90deg, var(--ink) 0%, var(--ink2) 100%);
  position:relative;overflow:hidden;
}
.stats-bar::before{
  content:'';position:absolute;inset:0;
  background:repeating-linear-gradient(90deg, rgba(201,169,110,.04) 0, rgba(201,169,110,.04) 1px, transparent 1px, transparent 80px);
}
.stats-inner{
  max-width:1200px;margin:0 auto;
  padding:clamp(22px,3vw,32px) clamp(20px,5vw,52px);
  display:flex;justify-content:center;gap:0;position:relative;
  overflow-x:auto;scrollbar-width:none;
}
.stats-inner::-webkit-scrollbar{display:none;}
.stat-item{
  flex:1;min-width:140px;max-width:220px;text-align:center;
  padding:0 clamp(20px,3vw,36px);position:relative;
}
.stat-item:not(:last-child)::after{
  content:'';position:absolute;right:0;top:15%;height:70%;
  width:1px;background:rgba(255,255,255,.1);
}
.stat-num{
  font-family:'Playfair Display',serif;
  font-size:clamp(1.7rem,3vw,2.3rem);
  color:var(--gold);font-weight:700;line-height:1;
}
.stat-lbl{font-size:.7rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.1em;margin-top:6px;}

/* ─── Section Base ───────────────────────── */
.section-wrap{max-width:1200px;margin:0 auto;padding:clamp(60px,8vw,96px) clamp(20px,5vw,52px);}
.section-head{text-align:center;margin-bottom:clamp(36px,5vw,60px);}
.section-tag{
  display:inline-block;padding:5px 14px;border-radius:999px;
  background:var(--gold-dim);border:1px solid var(--gold-border);
  font-size:.66rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  color:var(--gold);margin-bottom:14px;
}
.section-title{
  font-family:'Playfair Display',serif;font-style:italic;
  font-size:clamp(1.6rem,3.5vw,2.3rem);color:var(--ink);margin-bottom:12px;
}
.section-desc{font-size:.95rem;color:var(--muted);max-width:520px;margin:0 auto;line-height:1.85;}
.section-rule{width:44px;height:3px;background:linear-gradient(90deg,var(--gold),rgba(201,169,110,.2));border-radius:3px;margin:16px auto 0;}

/* ─── Services Section ───────────────────── */
.services-section{background:var(--bg);}
.services-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:clamp(14px,2vw,22px);
}
.srv-card{
  background:var(--card);border:1px solid var(--border);
  border-radius:var(--r);padding:clamp(22px,3vw,32px) 24px;
  text-align:center;
  display:flex;flex-direction:column;align-items:center;gap:clamp(10px,1.5vw,16px);
  transition:all .28s;position:relative;overflow:hidden;
}
.srv-card::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--gold),var(--gold-light));
  transform:scaleX(0);transform-origin:left;transition:transform .3s;
}
.srv-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg);border-color:var(--gold-border);}
.srv-card:hover::after{transform:scaleX(1);}
.srv-icon{
  width:clamp(52px,6vw,66px);height:clamp(52px,6vw,66px);
  border-radius:18px;
  background:linear-gradient(135deg,var(--gold-dim),rgba(201,169,110,.04));
  border:1.5px solid var(--gold-border);
  display:flex;align-items:center;justify-content:center;
  font-size:clamp(1.4rem,2.5vw,1.8rem);
  transition:all .28s;
}
.srv-card:hover .srv-icon{background:var(--ink);border-color:var(--ink);}
.srv-title{font-size:clamp(.9rem,1.5vw,1rem);font-weight:800;color:var(--ink);}
.srv-desc{font-size:clamp(.75rem,1.2vw,.82rem);color:var(--muted);line-height:1.7;}
.srv-more{
  display:inline-flex;align-items:center;gap:5px;
  font-size:.76rem;font-weight:700;color:var(--gold);
  margin-top:2px;
}
/* ── Featured register card ── */
.srv-card-register{
  background:linear-gradient(145deg,var(--ink) 0%,#252545 100%);
  border-color:rgba(201,169,110,.35);
  order:-1;
}
.srv-card-register::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 50% 0%,rgba(201,169,110,.15) 0%,transparent 65%);
}
.srv-card-register::after{
  background:linear-gradient(90deg,var(--gold),var(--gold-light),var(--gold));
  transform:scaleX(1);height:4px;
}
.srv-card-register .srv-icon{
  background:rgba(201,169,110,.18);border-color:rgba(201,169,110,.5);
  font-size:clamp(1.5rem,2.8vw,2rem);
  width:clamp(60px,7vw,76px);height:clamp(60px,7vw,76px);
}
.srv-card-register .srv-title{color:#fff;font-size:clamp(1rem,1.6vw,1.1rem);}
.srv-card-register .srv-desc{color:rgba(255,255,255,.6);}
.srv-card-register .srv-more{
  color:var(--gold);background:rgba(201,169,110,.15);
  padding:6px 16px;border-radius:999px;border:1px solid rgba(201,169,110,.35);
  font-size:.78rem;margin-top:4px;transition:all .25s;
}
.srv-card-register:hover{border-color:rgba(201,169,110,.6);}
.srv-card-register:hover .srv-icon{background:var(--gold);border-color:var(--gold);}
.srv-card-register:hover .srv-more{background:var(--gold);color:var(--ink);}
.srv-reg-badge{
  position:absolute;top:14px;right:14px;
  background:var(--gold);color:var(--ink);
  font-size:.62rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
  padding:3px 10px;border-radius:999px;
}

/* ─── About Section ──────────────────────── */
.about-section{background:#fff;}
.about-flex{
  display:grid;grid-template-columns:1.1fr 0.9fr;
  gap:clamp(24px,4vw,48px);align-items:center;
}
.about-img-wrap{position:relative;border-radius:24px;overflow:hidden;}
.about-img-wrap img{width:100%;height:clamp(280px,40vw,480px);object-fit:cover;display:block;}
.about-img-badge{
  position:absolute;bottom:20px;left:20px;right:20px;
  background:rgba(26,26,46,.82);backdrop-filter:blur(12px);
  border-radius:14px;padding:14px 18px;
  display:flex;align-items:center;gap:14px;
  border:1px solid rgba(201,169,110,.25);
}
.about-img-badge-icon{font-size:1.6rem;flex-shrink:0;}
.about-img-badge-text .label{font-size:.68rem;color:var(--gold);font-weight:700;letter-spacing:.1em;text-transform:uppercase;}
.about-img-badge-text .val{font-size:.9rem;color:#fff;font-weight:700;margin-top:2px;}
.about-content{}
.about-tag{font-size:.66rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;}
.about-h{
  font-family:'Playfair Display',serif;font-style:italic;
  font-size:clamp(1.5rem,3vw,2.1rem);color:var(--ink);
  line-height:1.25;margin-bottom:18px;
}
.about-p{font-size:.92rem;color:var(--muted);line-height:1.9;margin-bottom:28px;}
.about-pills{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:30px;}
.about-pill{
  display:flex;align-items:center;gap:7px;
  padding:8px 14px;border-radius:999px;
  background:var(--bg);border:1px solid var(--border);
  font-size:.8rem;font-weight:600;color:var(--ink);
}
.about-pill-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;}
.btn-about{
  display:inline-flex;align-items:center;gap:9px;
  padding:12px 26px;border-radius:999px;
  background:var(--ink);color:#fff;
  font-size:.85rem;font-weight:700;letter-spacing:.04em;
  transition:all .25s;
}
.btn-about:hover{background:var(--ink2);transform:translateY(-1px);box-shadow:0 8px 22px rgba(26,26,46,.2);}

/* Feature strip */
.feature-strip{
  display:grid;grid-template-columns:repeat(4,1fr);gap:1px;
  background:var(--border);border-radius:18px;overflow:hidden;
  margin-top:clamp(40px,6vw,72px);
}
.feat-item{
  background:#fff;padding:clamp(20px,3vw,32px) 24px;
  text-align:center;transition:background .2s;
}
.feat-item:hover{background:var(--bg);}
.feat-ico{font-size:1.6rem;margin-bottom:12px;}
.feat-title{font-size:.88rem;font-weight:800;color:var(--ink);margin-bottom:6px;}
.feat-desc{font-size:.78rem;color:var(--muted);line-height:1.6;}

/* ─── News Section ───────────────────────── */
.news-section{background:var(--bg);}
.news-layout{display:grid;grid-template-columns:1.4fr 1fr;gap:clamp(20px,3vw,36px);}
.news-main{
  border-radius:var(--r);overflow:hidden;background:var(--card);
  border:1px solid var(--border);
  display:flex;flex-direction:column;
  transition:all .25s;
}
.news-main:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);}
.news-main-img{
  width:100%;height:clamp(200px,25vw,280px);
  object-fit:cover;display:block;background:var(--ink);
}
.news-main-body{padding:clamp(18px,2.5vw,28px);}
.news-main-date{font-size:.72rem;color:var(--gold);font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px;}
.news-main-title{
  font-size:clamp(1rem,1.8vw,1.2rem);font-weight:800;color:var(--ink);
  line-height:1.4;margin-bottom:12px;
}
.news-main-excerpt{font-size:.84rem;color:var(--muted);line-height:1.8;}

.news-side{display:flex;flex-direction:column;gap:14px;}
.news-side-card{
  background:var(--card);border-radius:var(--r-sm);
  border:1px solid var(--border);
  display:flex;gap:14px;padding:14px;
  align-items:center;transition:all .22s;
}
.news-side-card:hover{transform:translateX(4px);box-shadow:var(--shadow-sm);border-color:var(--gold-border);}
.news-side-img{
  width:72px;height:72px;border-radius:10px;object-fit:cover;
  background:var(--ink);flex-shrink:0;
}
.news-side-img-ph{
  width:72px;height:72px;border-radius:10px;
  background:linear-gradient(135deg,var(--ink),var(--ink2));
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;flex-shrink:0;
}
.news-side-body{}
.news-side-date{font-size:.68rem;color:var(--muted);margin-bottom:5px;}
.news-side-title{font-size:.85rem;font-weight:700;color:var(--ink);line-height:1.4;}

.news-see-all{
  display:inline-flex;align-items:center;gap:8px;
  margin-top:clamp(24px,3vw,40px);
  padding:11px 26px;border-radius:999px;
  border:1.5px solid var(--border);color:var(--ink);
  font-weight:700;font-size:.84rem;transition:all .2s;
}
.news-see-all:hover{border-color:var(--gold);color:var(--gold);background:var(--gold-dim);}


/* ─── Footer ─────────────────────────────── */
.footer{background:var(--ink);color:rgba(255,255,255,.55);}
.footer-top{
  max-width:1200px;margin:0 auto;
  padding:clamp(48px,6vw,72px) clamp(20px,5vw,52px) clamp(36px,5vw,56px);
  display:grid;grid-template-columns:1.7fr 1fr 1fr;
  gap:clamp(28px,4vw,56px);
}
.footer-brand{}
.footer-rule{width:32px;height:3px;background:var(--gold);border-radius:2px;margin-bottom:14px;}
.footer-logo{
  font-family:'Playfair Display',serif;font-style:italic;
  font-size:1.5rem;color:#fff;margin-bottom:5px;
}
.footer-sub-brand{font-size:.68rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:18px;}
.footer-desc{font-size:.84rem;line-height:1.9;}
.footer-socials{display:flex;gap:10px;margin-top:20px;}
.footer-social{
  width:36px;height:36px;border-radius:10px;
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);
  display:flex;align-items:center;justify-content:center;font-size:.95rem;
  transition:all .2s;
}
.footer-social:hover{background:var(--gold-dim);border-color:var(--gold-border);}

.footer-col h4{
  font-size:.68rem;font-weight:700;letter-spacing:.15em;
  text-transform:uppercase;color:var(--gold);margin-bottom:18px;
}
.footer-links{list-style:none;display:flex;flex-direction:column;gap:11px;}
.footer-links a{font-size:.84rem;color:rgba(255,255,255,.45);transition:color .18s;}
.footer-links a:hover{color:#fff;}

.footer-contact li{
  display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;
  font-size:.82rem;color:rgba(255,255,255,.45);list-style:none;
}
.footer-contact-icon{color:var(--gold);flex-shrink:0;margin-top:2px;}

.footer-bottom{
  max-width:1200px;margin:0 auto;
  padding:18px clamp(20px,5vw,52px);
  border-top:1px solid rgba(255,255,255,.07);
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
  font-size:.74rem;color:rgba(255,255,255,.2);
}
.footer-bottom-links{display:flex;gap:20px;}
.footer-bottom-links a{color:rgba(255,255,255,.2);transition:color .2s;}
.footer-bottom-links a:hover{color:rgba(255,255,255,.5);}

/* ─── Responsive ─────────────────────────── */
@media(max-width:1024px){
  .services-grid{grid-template-columns:repeat(2,1fr);}
  .news-layout{grid-template-columns:1fr;}
  .news-side{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .footer-top{grid-template-columns:1fr 1fr;}
  .feature-strip{grid-template-columns:repeat(2,1fr);}
  .about-flex{grid-template-columns:1fr;}
  .about-img-wrap{order:-1;}
}
@media(max-width:768px){
  /* navbar */
  .navbar{height:60px;padding:0 16px;}
  .nav-center{display:none;}
  .user-name{display:none;}
  .nav-brand img{height:38px;}
  .nav-brand-sub{display:none;}
  .nav-ham{display:flex;}
  .nav-drawer{display:block;}

  /* hero */
  .hero{height:clamp(400px,80vh,560px);}
  .hero-scroll{display:none;}

  /* stats */
  .stats-inner{justify-content:flex-start;}
  .stat-item{min-width:130px;}

  /* services */
  .services-grid{grid-template-columns:repeat(2,1fr);gap:12px;}

  /* news */
  .news-side{grid-template-columns:1fr;}

  /* footer */
  .footer-top{grid-template-columns:1fr;gap:28px;}
  .footer-bottom{flex-direction:column;text-align:center;}
  .feature-strip{grid-template-columns:repeat(2,1fr);}

  /* cta */
  .cta-inner{flex-direction:column;text-align:center;}
  .cta-btns{justify-content:center;}
}
@media(max-width:480px){
  .services-grid{grid-template-columns:1fr 1fr;gap:10px;}
  .srv-card{padding:18px 14px;}
  .hero-cta{flex-direction:column;}
  .hero-btn{justify-content:center;}
  .feature-strip{grid-template-columns:1fr 1fr;}
  .news-side{grid-template-columns:1fr;}
  .stat-item{min-width:110px;}
}

/* ─── Animations ─────────────────────────── */
@keyframes fadeUp{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}
.fade-up{opacity:0;animation:fadeUp .7s cubic-bezier(.23,1,.32,1) forwards;}
.fade-up-1{animation-delay:.1s;}
.fade-up-2{animation-delay:.2s;}
.fade-up-3{animation-delay:.3s;}
.fade-up-4{animation-delay:.4s;}

/* ─── Nav Booking Dropdown ────────────────── */
.nav-book-wrap{position:relative;display:flex;align-items:center;}
.nav-book-trigger{
  display:flex;align-items:center;gap:5px;
  padding:7px 13px;border-radius:999px;
  font-size:.84rem;font-weight:700;color:var(--ink);
  background:linear-gradient(135deg,rgba(201,169,110,.18),rgba(201,169,110,.08));
  border:1.5px solid var(--gold-border);
  cursor:pointer;transition:all .22s;white-space:nowrap;user-select:none;
}
.nav-book-trigger:hover,.nav-book-wrap.open .nav-book-trigger{
  background:linear-gradient(135deg,var(--gold),#e8c98a);
  color:var(--ink);border-color:var(--gold);
  box-shadow:0 4px 16px rgba(201,169,110,.35);
}
.nav-book-arrow{font-size:.62rem;transition:transform .22s;margin-left:2px;}
.nav-book-wrap.open .nav-book-arrow{transform:rotate(180deg);}
.nav-book-drop{
  position:absolute;top:calc(100% + 6px);left:50%;
  opacity:0;visibility:hidden;transform:translateX(-50%) translateY(-6px);
  transition:opacity .22s,transform .22s,visibility .22s;
  z-index:700;pointer-events:none;
}
.nav-book-wrap.open .nav-book-drop{
  opacity:1;visibility:visible;
  transform:translateX(-50%) translateY(0);
  pointer-events:auto;
}
/* bridge ป้องกัน gap ระหว่าง trigger กับ dropdown */
.nav-book-drop::before{
  content:'';position:absolute;top:-10px;left:0;right:0;height:12px;
}
.nav-book-drop-inner{
  background:#fff;border-radius:20px;
  box-shadow:0 20px 60px rgba(26,26,46,.16),0 4px 12px rgba(26,26,46,.08);
  border:1px solid var(--border);
  min-width:320px;padding:10px;
  overflow:hidden;
}
.nav-book-drop-inner::before{
  content:'';display:block;height:3px;
  background:linear-gradient(90deg,var(--gold),#1d6fad,#16a34a);
  border-radius:3px 3px 0 0;margin:-10px -10px 10px;
}
.nav-book-item{
  display:flex;align-items:center;gap:14px;
  padding:12px 14px;border-radius:12px;
  text-decoration:none;transition:background .18s;
  color:var(--ink);
}
.nav-book-item:hover{background:var(--bg);}
.nav-book-ico{
  width:46px;height:46px;border-radius:13px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;flex-shrink:0;
  transition:transform .2s;
}
.nav-book-item:hover .nav-book-ico{transform:scale(1.1);}
.nav-book-ico-room{background:rgba(29,111,173,.1);border:1.5px solid rgba(29,111,173,.2);}
.nav-book-ico-tent{background:rgba(21,128,61,.1);border:1.5px solid rgba(21,128,61,.2);}
.nav-book-ico-boat{background:rgba(201,169,110,.15);border:1.5px solid rgba(201,169,110,.35);}
.nav-book-label{font-size:.88rem;font-weight:700;color:var(--ink);margin-bottom:2px;}
.nav-book-sub{font-size:.73rem;color:var(--muted);}
.nav-book-footer{
  margin-top:6px;padding:10px 14px 6px;
  border-top:1px solid var(--border);
}
.nav-book-status{
  display:flex;align-items:center;justify-content:center;
  padding:8px;border-radius:10px;font-size:.78rem;font-weight:600;color:var(--muted);
  text-decoration:none;transition:all .18s;gap:6px;
}
.nav-book-status:hover{background:var(--gold-dim);color:var(--ink);}

/* ─── Mobile drawer booking group ─────────── */
.drawer-book-group{
  background:linear-gradient(135deg,rgba(201,169,110,.08),rgba(201,169,110,.04));
  border:1.5px solid var(--gold-border);
  border-radius:14px;padding:8px;margin:4px 0;
}
.drawer-book-label{
  font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:var(--gold);padding:4px 8px 8px;display:block;
}
.drawer-book-group a{border-radius:10px;}

/* ─── Booking Hero Section ────────────────── */
.booking-hero-section{
  background:linear-gradient(145deg,#0a1628 0%,#0d2344 40%,#1a3a5c 70%,#0d2344 100%);
  position:relative;overflow:hidden;
}
.booking-hero-section::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:
    radial-gradient(ellipse at 10% 50%,rgba(29,111,173,.3) 0%,transparent 50%),
    radial-gradient(ellipse at 90% 20%,rgba(201,169,110,.12) 0%,transparent 40%),
    repeating-linear-gradient(90deg,rgba(255,255,255,.02) 0,rgba(255,255,255,.02) 1px,transparent 1px,transparent 80px),
    repeating-linear-gradient(0deg,rgba(255,255,255,.01) 0,rgba(255,255,255,.01) 1px,transparent 1px,transparent 80px);
}
.bk-tag{background:rgba(201,169,110,.18);border-color:rgba(201,169,110,.35);color:var(--gold);}
.bk-cards{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:clamp(14px,2vw,24px);
  position:relative;z-index:1;
}
.bk-card{
  background:rgba(255,255,255,.06);
  border:1.5px solid rgba(255,255,255,.12);
  border-radius:24px;padding:28px 24px;
  text-decoration:none;color:#fff;
  position:relative;overflow:hidden;
  display:flex;flex-direction:column;gap:0;
  transition:all .3s cubic-bezier(.23,1,.32,1);
  backdrop-filter:blur(8px);
}
.bk-card:hover{
  transform:translateY(-8px);
  border-color:rgba(255,255,255,.28);
  box-shadow:0 28px 60px rgba(0,0,0,.3),0 8px 20px rgba(0,0,0,.15);
}
.bk-card-featured{
  background:linear-gradient(145deg,rgba(201,169,110,.2),rgba(201,169,110,.06));
  border-color:rgba(201,169,110,.45);
}
.bk-card-featured:hover{border-color:rgba(201,169,110,.8);}
.bk-card-room:hover{border-color:rgba(29,111,173,.6);}
.bk-card-tent:hover{border-color:rgba(21,128,61,.5);}
.bk-card-glow{
  position:absolute;inset:0;pointer-events:none;opacity:0;
  transition:opacity .3s;border-radius:24px;
}
.bk-card-room .bk-card-glow{background:radial-gradient(ellipse at 50% 0%,rgba(29,111,173,.2) 0%,transparent 60%);}
.bk-card-tent .bk-card-glow{background:radial-gradient(ellipse at 50% 0%,rgba(21,128,61,.2) 0%,transparent 60%);}
.bk-card-featured .bk-card-glow{background:radial-gradient(ellipse at 50% 0%,rgba(201,169,110,.25) 0%,transparent 60%);}
.bk-card:hover .bk-card-glow{opacity:1;}
.bk-badge-new{
  position:absolute;top:16px;right:16px;
  padding:3px 12px;border-radius:999px;
  font-size:.64rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
  background:rgba(255,255,255,.15);color:rgba(255,255,255,.8);
  border:1px solid rgba(255,255,255,.2);
}
.bk-badge-hot{background:rgba(201,169,110,.25);color:var(--gold);border-color:rgba(201,169,110,.45);}
.bk-card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
.bk-icon{
  width:clamp(54px,6vw,68px);height:clamp(54px,6vw,68px);
  border-radius:18px;background:rgba(255,255,255,.1);
  border:1.5px solid rgba(255,255,255,.15);
  display:flex;align-items:center;justify-content:center;
  font-size:clamp(1.6rem,3vw,2rem);
  transition:all .3s;
}
.bk-card:hover .bk-icon{background:rgba(255,255,255,.18);transform:scale(1.06) rotate(-3deg);}
.bk-avail-dot{
  width:10px;height:10px;border-radius:50%;
  background:#4ade80;
  box-shadow:0 0 0 0 rgba(74,222,128,.5);
  animation:avail-pulse 2s ease-in-out infinite;
}
@keyframes avail-pulse{
  0%,100%{box-shadow:0 0 0 0 rgba(74,222,128,.5);}
  50%{box-shadow:0 0 0 7px rgba(74,222,128,0);}
}
.bk-card-body{flex:1;margin-bottom:18px;}
.bk-title{font-size:clamp(1.1rem,1.8vw,1.25rem);font-weight:800;color:#fff;margin-bottom:8px;}
.bk-desc{font-size:clamp(.78rem,1.2vw,.86rem);color:rgba(255,255,255,.65);line-height:1.75;margin-bottom:12px;}
.bk-features{display:flex;flex-direction:column;gap:4px;}
.bk-features span{font-size:.76rem;color:rgba(255,255,255,.55);display:flex;align-items:center;gap:6px;}
.bk-card-footer{border-top:1px solid rgba(255,255,255,.1);padding-top:16px;}
.bk-btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:9px 20px;border-radius:999px;
  background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.2);
  font-size:.82rem;font-weight:700;color:#fff;
  transition:all .25s;letter-spacing:.03em;
}
.bk-card:hover .bk-btn{background:rgba(255,255,255,.22);border-color:rgba(255,255,255,.4);}
.bk-btn-gold{background:rgba(201,169,110,.2);border-color:rgba(201,169,110,.5);color:var(--gold);}
.bk-card-featured:hover .bk-btn-gold{background:var(--gold);color:var(--ink);border-color:var(--gold);}
.bk-status-bar{
  display:flex;align-items:center;gap:clamp(8px,2vw,20px);flex-wrap:wrap;justify-content:center;
  margin-top:clamp(20px,3vw,32px);
  padding:14px 20px;border-radius:14px;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
  position:relative;z-index:1;
}
.bk-status-bar span{font-size:.82rem;color:rgba(255,255,255,.55);}
.bk-status-bar a{
  font-size:.82rem;font-weight:600;color:rgba(255,255,255,.7);
  text-decoration:none;padding:4px 10px;border-radius:8px;
  transition:all .18s;
}
.bk-status-bar a:hover{background:rgba(255,255,255,.1);color:#fff;}
@media(max-width:900px){.bk-cards{grid-template-columns:1fr;gap:14px;}}
@media(max-width:600px){.bk-status-bar{flex-direction:column;gap:8px;text-align:center;}}
</style>
</head>
<body>

<!-- ══════════ NAVBAR ══════════ -->
<nav class="navbar" id="navbar">
  <!-- Brand -->
  <a href="index.php" class="nav-brand">
    <img src="Logo.png" alt="WRBRI">
    <div class="nav-brand-text">
      <div class="nav-brand-name">สถาบันวิจัยวลัยรุกขเวช</div>
      <div class="nav-brand-sub">มหาวิทยาลัยมหาสารคาม</div>
    </div>
  </a>

  <!-- Desktop nav -->
  <div class="nav-center">
    <a href="news.php"><span class="nav-icon">📰</span> ข่าวสาร</a>
    <a href="view_data.php"><span class="nav-icon">📋</span> ลงทะเบียน</a>
    <a href="survey.php"><span class="nav-icon">📝</span> แบบประเมิน</a>
    <a href="calendar.php"><span class="nav-icon">📅</span> ปฏิทิน</a>
    <a href="about_us.php"><span class="nav-icon">🌿</span> เกี่ยวกับ</a>
    <a href="kengcamp.php"><span class="nav-icon">⛺</span> เก็งแคมป์</a>
    <div class="nav-book-wrap">
      <div class="nav-book-trigger">
        <span class="nav-icon">🗓️</span> จอง <span class="nav-book-arrow">▾</span>
      </div>
      <div class="nav-book-drop">
        <div class="nav-book-drop-inner">
          <a href="booking_room.php" class="nav-book-item">
            <span class="nav-book-ico nav-book-ico-room">🏨</span>
            <div><div class="nav-book-label">จองห้องพัก</div><div class="nav-book-sub">ห้องพักรายคืนภายในสถาบัน</div></div>
          </a>
          <a href="booking_tent.php" class="nav-book-item">
            <span class="nav-book-ico nav-book-ico-tent">⛺</span>
            <div><div class="nav-book-label">จองเต็นท์</div><div class="nav-book-sub">แคมป์ปิ้งสัมผัสธรรมชาติ</div></div>
          </a>
          <a href="booking_boat.php" class="nav-book-item">
            <span class="nav-book-ico nav-book-ico-boat">🚣</span>
            <div><div class="nav-book-label">จองคิวพายเรือ</div><div class="nav-book-sub">เลือกรอบ เลือกเรือได้เลย</div></div>
          </a>
          <div class="nav-book-footer">
            <a href="booking_status.php" class="nav-book-status">📋 ติดตามสถานะการจอง</a>
          </div>
        </div>
      </div>
    </div>
    <?php if ($isLoggedIn): ?><a href="chat.php"><span class="nav-icon">💬</span> แชทโลก</a><?php endif; ?>
  </div>

  <!-- Right actions -->
  <div class="nav-right">
    <?php if ($isLoggedIn): ?>
      <div class="user-menu" id="userMenu">
        <div class="user-trigger" onclick="toggleMenu()">
          <div class="user-avatar">
            <?php if (!empty($avatarRow['avatar'])): ?>
              <img src="<?= htmlspecialchars($avatarRow['avatar']) ?>" alt="">
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
          <a href="profile.php" class="dd-item"><div class="dd-icon">👤</div> โปรไฟล์ของฉัน</a>
          <a href="chat.php" class="dd-item"><div class="dd-icon">💬</div> แชทโลก</a>
          <a href="booking_status.php" class="dd-item"><div class="dd-icon">🛎️</div> สถานะการจอง</a>
          <?php if ($isAdmin): ?>
            <hr class="dd-divider">
            <a href="admin_dashboard.php" class="dd-item"><div class="dd-icon">📊</div> Admin Dashboard</a>
          <?php endif; ?>
          <hr class="dd-divider">
          <a href="logout.php" class="dd-item danger"><div class="dd-icon">🚪</div> ออกจากระบบ</a>
        </div>
      </div>
    <?php else: ?>
      <a href="login.php" class="btn-nav-login">เข้าสู่ระบบ</a>
    <?php endif; ?>

    <!-- Hamburger (mobile) -->
    <div class="nav-ham" id="hamBtn" onclick="toggleDrawer()">
      <span></span><span></span><span></span>
    </div>
  </div>
</nav>

<!-- Mobile Drawer -->
<div class="nav-drawer" id="navDrawer" onclick="closeDrawer(event)">
  <div class="nav-drawer-inner">
    <?php if ($isLoggedIn): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px 16px;border-bottom:1px solid var(--border);margin-bottom:8px;">
      <div class="user-avatar" style="width:44px;height:44px;font-size:1rem;">
        <?php if (!empty($avatarRow['avatar'])): ?>
          <img src="<?= htmlspecialchars($avatarRow['avatar']) ?>" alt="">
        <?php else: echo $avatarInitial; endif; ?>
      </div>
      <div>
        <div style="font-size:.9rem;font-weight:700;"><?= htmlspecialchars($userName) ?></div>
        <div style="font-size:.72rem;color:var(--muted);"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
      </div>
    </div>
    <?php endif; ?>
    <a href="index.php"><div class="nav-drawer-icon">🏠</div> หน้าแรก</a>
    <a href="news.php"><div class="nav-drawer-icon">📰</div> ข่าวสาร</a>
    <a href="kengcamp.php"><div class="nav-drawer-icon">⛺</div> เก็งแคมป์</a>
    <div class="drawer-book-group">
      <div class="drawer-book-label">🗓️ จองบริการ</div>
      <a href="booking_room.php"><div class="nav-drawer-icon" style="background:rgba(29,111,173,.1);">🏨</div> จองห้องพัก</a>
      <a href="booking_tent.php"><div class="nav-drawer-icon" style="background:rgba(21,128,61,.1);">⛺</div> จองเต็นท์</a>
      <a href="booking_boat.php"><div class="nav-drawer-icon" style="background:rgba(201,169,110,.15);">🚣</div> จองคิวพายเรือ</a>
    </div>
    <a href="view_data.php"><div class="nav-drawer-icon">📋</div> ลงทะเบียนกิจกรรม</a>
    <a href="calendar.php"><div class="nav-drawer-icon">📅</div> ปฏิทินกิจกรรม</a>
    <a href="survey.php"><div class="nav-drawer-icon">📝</div> แบบประเมิน</a>
    <a href="about_us.php"><div class="nav-drawer-icon">🌿</div> เกี่ยวกับสถาบัน</a>
    <div class="drawer-divider"></div>
    <?php if ($isLoggedIn): ?>
      <a href="profile.php"><div class="nav-drawer-icon">👤</div> โปรไฟล์ของฉัน</a>
      <a href="chat.php"><div class="nav-drawer-icon">💬</div> แชทโลก</a>
      <a href="booking_status.php"><div class="nav-drawer-icon">🛎️</div> สถานะการจอง</a>
      <?php if ($isAdmin): ?><a href="admin_dashboard.php"><div class="nav-drawer-icon">📊</div> Admin Dashboard</a><?php endif; ?>
      <a href="logout.php" style="color:#dc2626;"><div class="nav-drawer-icon" style="background:#fef2f2;">🚪</div> ออกจากระบบ</a>
    <?php else: ?>
      <a href="login.php" style="justify-content:center;background:var(--ink);color:#fff;border-radius:12px;padding:14px;">เข้าสู่ระบบ</a>
      <a href="register.php" style="justify-content:center;border:1.5px solid var(--border);border-radius:12px;padding:14px;">สมัครสมาชิก</a>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════ HERO ══════════ -->
<section class="hero">
  <div class="hero-bg" id="heroBg"></div>
  <div class="hero-overlay"></div>
  <div class="hero-particles"></div>
  <div class="hero-content">
    <div class="hero-text">
      <div class="hero-badge fade-up fade-up-1">✦ สถาบันวิจัยวลัยรุกขเวช มมส</div>
      <h1 class="hero-title fade-up fade-up-2">
        แหล่งเรียนรู้<br>
        <span>ด้านธรรมชาติ</span><br>
        และความหลากหลาย
      </h1>
      <p class="hero-sub fade-up fade-up-3">
        มุ่งมั่นส่งเสริมการวิจัย การเรียนรู้ และการบริการวิชาการแก่ชุมชน
        เพื่อการพัฒนาที่ยั่งยืนและการสร้างองค์ความรู้
      </p>
      <div class="hero-cta fade-up fade-up-4">
        <a href="#booking-section" class="hero-btn hero-btn-primary">🗓️ จองบริการ</a>
        <a href="view_data.php" class="hero-btn hero-btn-register"><span class="reg-dot"></span> ลงทะเบียนกิจกรรม</a>
      </div>
    </div>
  </div>
  <div class="hero-scroll">
    <span>เลื่อนลง</span>
    <div class="hero-scroll-line"></div>
  </div>
</section>


<!-- ══════════ BOOKING SECTION ══════════ -->
<section id="booking-section" class="booking-hero-section">
  <div class="section-wrap">
    <div class="section-head">
      <div class="section-tag bk-tag">จองบริการ</div>
      <h2 class="section-title" style="color:#fff;">จองบริการ<span style="color:var(--gold);font-style:italic;"> ทุกอย่าง</span>ที่นี่</h2>
      <p class="section-desc" style="color:rgba(255,255,255,.7);">เลือกประเภทการจองที่ต้องการ ระบบรองรับการจองแบบ real-time</p>
      <div class="section-rule"></div>
    </div>
    <div class="bk-cards">

      <a href="booking_room.php" class="bk-card bk-card-room">
        <div class="bk-card-glow"></div>
        <div class="bk-badge-new">พร้อมใช้งาน</div>
        <div class="bk-card-top">
          <div class="bk-icon">🏨</div>
          <div class="bk-avail-dot"></div>
        </div>
        <div class="bk-card-body">
          <h3 class="bk-title">จองห้องพัก</h3>
          <p class="bk-desc">ห้องพักสะอาด สะดวกสบาย ภายในสถาบัน รองรับบุคลากรและผู้เยี่ยมชม</p>
          <div class="bk-features">
            <span>✓ เช็กอิน-เอาท์ยืดหยุ่น</span>
            <span>✓ หลายขนาดห้อง</span>
          </div>
        </div>
        <div class="bk-card-footer">
          <span class="bk-btn">จองเลย →</span>
        </div>
      </a>

      <a href="booking_tent.php" class="bk-card bk-card-tent">
        <div class="bk-card-glow"></div>
        <div class="bk-badge-new">แคมป์ปิ้ง</div>
        <div class="bk-card-top">
          <div class="bk-icon">⛺</div>
          <div class="bk-avail-dot"></div>
        </div>
        <div class="bk-card-body">
          <h3 class="bk-title">จองเต็นท์</h3>
          <p class="bk-desc">กางเต็นท์สัมผัสธรรมชาติ บรรยากาศแคมป์ปิ้งกลางป่า อากาศบริสุทธิ์</p>
          <div class="bk-features">
            <span>✓ หลายขนาด</span>
            <span>✓ อุปกรณ์ครบ</span>
          </div>
        </div>
        <div class="bk-card-footer">
          <span class="bk-btn">จองเลย →</span>
        </div>
      </a>

      <a href="booking_boat.php" class="bk-card bk-card-boat bk-card-featured">
        <div class="bk-card-glow"></div>
        <div class="bk-badge-new bk-badge-hot">🔥 ใหม่!</div>
        <div class="bk-card-top">
          <div class="bk-icon">🚣</div>
          <div class="bk-avail-dot"></div>
        </div>
        <div class="bk-card-body">
          <h3 class="bk-title">จองคิวพายเรือ</h3>
          <p class="bk-desc">เลือกรอบ เลือกหมายเลขเรือที่ต้องการ ระบบแสดงเรือว่าง real-time</p>
          <div class="bk-features">
            <span>✓ เลือกหมายเลขเรือได้</span>
            <span>✓ หลายรอบต่อวัน</span>
          </div>
        </div>
        <div class="bk-card-footer">
          <span class="bk-btn bk-btn-gold">จองเลย →</span>
        </div>
      </a>

    </div>
    <div class="bk-status-bar">
      <span>📋 มีการจองอยู่แล้ว?</span>
      <a href="booking_status.php">ติดตามสถานะห้องพัก →</a>
      <a href="booking_tent_status.php">ติดตามสถานะเต็นท์ →</a>
      <a href="booking_boat_status.php">ติดตามสถานะเรือ →</a>
    </div>
  </div>
</section>

<!-- ══════════ SERVICES ══════════ -->
<section class="services-section">
  <div class="section-wrap">
    <div class="section-head">
      <div class="section-tag">บริการ</div>
      <h2 class="section-title">บริการของเรา</h2>
      <p class="section-desc">เข้าถึงบริการและข้อมูลต่างๆ ของสถาบันได้อย่างสะดวกรวดเร็ว</p>
      <div class="section-rule"></div>
    </div>
    <div class="services-grid">
      <a href="news.php" class="srv-card">
        <div class="srv-icon">📰</div>
        <div class="srv-title">ข่าวสารและกิจกรรม</div>
        <div class="srv-desc">ติดตามข่าวสาร กิจกรรม และประกาศล่าสุดจากสถาบัน</div>
        <div class="srv-more">อ่านข่าวสาร →</div>
      </a>
      <a href="view_data.php" class="srv-card srv-card-register">
        <div class="srv-reg-badge">เปิดรับสมัคร</div>
        <div class="srv-icon">📋</div>
        <div class="srv-title">ลงทะเบียนกิจกรรม</div>
        <div class="srv-desc">ลงทะเบียนเข้าร่วมกิจกรรม ทัศนศึกษา และการอบรมต่างๆ</div>
        <div class="srv-more">ลงทะเบียนเลย →</div>
      </a>
      <a href="kengcamp.php" class="srv-card">
        <div class="srv-icon">⛺</div>
        <div class="srv-title">เก็งแคมป์</div>
        <div class="srv-desc">กางเต็นท์สัมผัสธรรมชาติ พายเรือคายัค เส้นทางเดินป่าศึกษาธรรมชาติ</div>
        <div class="srv-more">ดูรายละเอียด →</div>
      </a>
      <a href="calendar.php" class="srv-card">
        <div class="srv-icon">📅</div>
        <div class="srv-title">ปฏิทินกิจกรรม</div>
        <div class="srv-desc">ดูตารางกิจกรรม สัมมนา และงานสำคัญรายวัน รายเดือน</div>
        <div class="srv-more">ดูปฏิทิน →</div>
      </a>
    </div>
  </div>
</section>

<!-- ══════════ ABOUT ══════════ -->
<section class="about-section">
  <div class="section-wrap">
    <div class="about-flex">
      <div class="about-img-wrap">
        <img src="ka0.jpg" alt="สถาบันวิจัยวลัยรุกขเวช">
      </div>
      <div class="about-content">
        <div class="about-tag">เกี่ยวกับเรา</div>
        <h2 class="about-h">เรียนรู้จากธรรมชาติ<br>เพื่อชุมชนที่ยั่งยืน</h2>
        <p class="about-p">
          สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม มุ่งเน้นการส่งเสริมการเรียนรู้
          การวิจัย และการบริการวิชาการแก่ชุมชน โดยเฉพาะในด้านทรัพยากรธรรมชาติ
          ความหลากหลายทางชีวภาพ และภูมิปัญญาท้องถิ่น
        </p>
        <div class="about-pills">
          <div class="about-pill"><div class="about-pill-dot"></div> การวิจัยธรรมชาติ</div>
          <div class="about-pill"><div class="about-pill-dot"></div> พืชสมุนไพร</div>
          <div class="about-pill"><div class="about-pill-dot"></div> ความหลากหลายชีวภาพ</div>
          <div class="about-pill"><div class="about-pill-dot"></div> บริการวิชาการ</div>
        </div>
        <a href="about_us.php" class="btn-about">🌿 อ่านเพิ่มเติม</a>
      </div>
    </div>

    <!-- Feature strip -->
    <div class="feature-strip">
      <div class="feat-item">
        <div class="feat-ico">📢</div>
        <div class="feat-title">ประชาสัมพันธ์</div>
        <div class="feat-desc">เผยแพร่ข่าวสาร กิจกรรม และประกาศสำคัญของสถาบัน</div>
      </div>
      <div class="feat-item">
        <div class="feat-ico">🔬</div>
        <div class="feat-title">งานวิจัย</div>
        <div class="feat-desc">สนับสนุนและเผยแพร่งานวิจัยด้านธรรมชาติและสิ่งแวดล้อม</div>
      </div>
      <div class="feat-item">
        <div class="feat-ico">🤝</div>
        <div class="feat-title">บริการวิชาการ</div>
        <div class="feat-desc">เชื่อมโยงความรู้สู่ชุมชน โรงเรียน และหน่วยงานภาครัฐ</div>
      </div>
      <div class="feat-item">
        <div class="feat-ico">🏛️</div>
        <div class="feat-title">ภาพลักษณ์องค์กร</div>
        <div class="feat-desc">นำเสนอผลงานและความร่วมมือในระดับชาติและนานาชาติ</div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ NEWS ══════════ -->
<?php if (!empty($newsRows)): ?>
<section class="news-section">
  <div class="section-wrap">
    <div class="section-head">
      <div class="section-tag">ข่าวสาร</div>
      <h2 class="section-title">ข่าวสารล่าสุด</h2>
      <p class="section-desc">ติดตามความเคลื่อนไหวและกิจกรรมล่าสุดของสถาบัน</p>
      <div class="section-rule"></div>
    </div>

    <?php
      $mainNews = $newsRows[0] ?? null;
      $sideNews = array_slice($newsRows, 1);
    ?>
    <div class="news-layout">
      <!-- Main news card -->
      <?php if ($mainNews): ?>
      <a href="news.php" class="news-main">
        <?php if (!empty($mainNews['image'])): ?>
          <img src="uploads/<?= htmlspecialchars($mainNews['image']) ?>" class="news-main-img" alt="">
        <?php else: ?>
          <div class="news-main-img" style="display:flex;align-items:center;justify-content:center;font-size:3rem;">📰</div>
        <?php endif; ?>
        <div class="news-main-body">
          <div class="news-main-date"><?= date('d M Y', strtotime($mainNews['created_at'])) ?></div>
          <div class="news-main-title"><?= htmlspecialchars($mainNews['title']) ?></div>
          <div class="news-main-excerpt">คลิกเพื่ออ่านข่าวสารและกิจกรรมจากสถาบัน →</div>
        </div>
      </a>
      <?php endif; ?>

      <!-- Side news -->
      <div class="news-side">
        <?php foreach ($sideNews as $sn): ?>
        <a href="news.php" class="news-side-card">
          <?php if (!empty($sn['image'])): ?>
            <img src="uploads/<?= htmlspecialchars($sn['image']) ?>" class="news-side-img" alt="">
          <?php else: ?>
            <div class="news-side-img-ph">📰</div>
          <?php endif; ?>
          <div class="news-side-body">
            <div class="news-side-date"><?= date('d M Y', strtotime($sn['created_at'])) ?></div>
            <div class="news-side-title"><?= htmlspecialchars($sn['title']) ?></div>
          </div>
        </a>
        <?php endforeach; ?>


      </div>
    </div>

    <div style="text-align:center;">
      <a href="news.php" class="news-see-all">ดูข่าวสารทั้งหมด →</a>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ══════════ FOOTER ══════════ -->
<footer class="footer">
  <div class="footer-top">
    <div class="footer-brand">
      <div class="footer-rule"></div>
      <div class="footer-logo">WRBRI</div>
      <div class="footer-sub-brand">สถาบันวิจัยวลัยรุกขเวช</div>
      <p class="footer-desc">
        มุ่งมั่นส่งเสริมการเรียนรู้ การวิจัย และการจัดกิจกรรม
        เพื่อพัฒนาศักยภาพด้านวิทยาศาสตร์ เทคโนโลยี
        และการบริการวิชาการแก่ชุมชนและสังคม
      </p>
      <div class="footer-socials">
        <a class="footer-social" href="#" title="Facebook">📘</a>
        <a class="footer-social" href="#" title="Line">💬</a>
        <a class="footer-social" href="#" title="Email">✉️</a>
      </div>
    </div>

    <div class="footer-col">
      <h4>เมนูหลัก</h4>
      <ul class="footer-links">
        <li><a href="news.php">ข่าวสาร</a></li>
        <li><a href="view_data.php">ลงทะเบียน</a></li>
        <li><a href="calendar.php">ปฏิทิน</a></li>
        <li><a href="about_us.php">เกี่ยวกับสถาบัน</a></li>
        <li><a href="#booking-section">🗓️ จองบริการ</a></li>
        <li><a href="booking_room.php">↳ จองห้องพัก</a></li>
        <li><a href="booking_tent.php">↳ จองเต็นท์</a></li>
        <li><a href="booking_boat.php">↳ จองคิวพายเรือ</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>ติดต่อเรา</h4>
      <ul class="footer-contact">
        <li><span class="footer-contact-icon">✉</span> walairukhavej@msu.ac.th</li>
        <li><span class="footer-contact-icon">📞</span> 043 719 816</li>
        <li><span class="footer-contact-icon">📍</span> Kantharawichai,<br>Maha Sarakham 44150</li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <span>© <?= date('Y') ?> สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม</span>
    <div class="footer-bottom-links">
      <a href="about_us.php">เกี่ยวกับ</a>
      <a href="news.php">ข่าวสาร</a>
      <a href="#booking-section">จองบริการ</a>
    </div>
  </div>
</footer>

<script>
/* ── User dropdown ── */
function toggleMenu() {
  document.getElementById('userMenu')?.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const m = document.getElementById('userMenu');
  if (m && !m.contains(e.target)) m.classList.remove('open');
});

/* ── Mobile drawer ── */
function toggleDrawer() {
  const drawer = document.getElementById('navDrawer');
  const ham = document.getElementById('hamBtn');
  drawer.classList.toggle('open');
  ham.classList.toggle('open');
  document.body.style.overflow = drawer.classList.contains('open') ? 'hidden' : '';
}
function closeDrawer(e) {
  if (e.target === document.getElementById('navDrawer')) {
    document.getElementById('navDrawer').classList.remove('open');
    document.getElementById('hamBtn').classList.remove('open');
    document.body.style.overflow = '';
  }
}

/* ── Scroll: navbar shadow ── */
window.addEventListener('scroll', function() {
  const nav = document.getElementById('navbar');
  nav.style.boxShadow = window.scrollY > 10
    ? '0 4px 24px rgba(26,26,46,.12)'
    : 'none';
});

/* ── Intersection observer for fade-up elements ── */
const obs = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      obs.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('.srv-card, .feat-item, .news-main, .news-side-card').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity .6s ease, transform .6s ease';
  obs.observe(el);
});
const obsReady = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
      obsReady.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('.srv-card, .feat-item, .news-main, .news-side-card').forEach(el => obsReady.observe(el));

/* ── Booking cards stagger animation ── */
const obsBk = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }, i * 80);
      obsBk.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });
document.querySelectorAll('.bk-card').forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(28px)';
  el.style.transition = `opacity .55s ease ${i*0.08}s, transform .55s cubic-bezier(.23,1,.32,1) ${i*0.08}s`;
  obsBk.observe(el);
});

/* ── Nav booking dropdown — click toggle ── */
const navBookWrap = document.querySelector('.nav-book-wrap');
const navBookTrigger = navBookWrap?.querySelector('.nav-book-trigger');
navBookTrigger?.addEventListener('click', e => {
  e.stopPropagation();
  navBookWrap.classList.toggle('open');
  document.getElementById('userMenu')?.classList.remove('open');
});
document.addEventListener('click', e => {
  if (!e.target.closest('.nav-book-wrap')) {
    navBookWrap?.classList.remove('open');
  }
});
/* ปิด dropdown เมื่อคลิกลิงก์ข้างใน */
navBookWrap?.querySelectorAll('.nav-book-item, .nav-book-status').forEach(a => {
  a.addEventListener('click', () => navBookWrap.classList.remove('open'));
});
</script>
</body>
</html>
