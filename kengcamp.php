<?php
date_default_timezone_set('Asia/Bangkok');
include 'config.php';

$conn->query("CREATE TABLE IF NOT EXISTS kengcamp_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entry_fee DECIMAL(10,2) DEFAULT 50.00,
    children_free_age INT DEFAULT 9,
    rooftop_fee DECIMAL(10,2) DEFAULT 300.00,
    checkin_time VARCHAR(30) DEFAULT '14.00 น.',
    checkout_time VARCHAR(30) DEFAULT '12.00 น.',
    early_checkin_note TEXT,
    equipment_json TEXT,
    activities_json TEXT,
    rules_json TEXT,
    contacts_json TEXT,
    qr_image VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$chk = $conn->query("SELECT COUNT(*) as c FROM kengcamp_info");
if ($chk && $chk->fetch_assoc()['c'] == 0) {
    $default_equipment = json_encode([
        ['name'=>'เต็นท์ 1-2 คน','price'=>'100','unit'=>'หลัง'],
        ['name'=>'เต็นท์ 3-4 คน','price'=>'150','unit'=>'หลัง'],
        ['name'=>'เก้าอี้','price'=>'30','unit'=>'ตัว'],
        ['name'=>'โต๊ะ','price'=>'30','unit'=>'ตัว'],
        ['name'=>'เบาะรองนอน','price'=>'30','unit'=>'ชิ้น'],
        ['name'=>'หมอน','price'=>'30','unit'=>'ใบ'],
        ['name'=>'ชุดเก้าอี้สนาม (4 ตัว/โต๊ะ 1 ตัว)','price'=>'120','unit'=>'ชุด'],
        ['name'=>'เครื่องนอน 1 ชุด (ผ้าปู/ผ้าห่ม/หมอน 2 ใบ)','price'=>'100','unit'=>'ชุด'],
    ], JSON_UNESCAPED_UNICODE);
    $default_activities = json_encode([
        ['name'=>'พายเรือคายัค','price'=>'20','unit'=>'คน'],
        ['name'=>'เส้นทางเดินศึกษาธรรมชาติ','price'=>'0','unit'=>''],
    ], JSON_UNESCAPED_UNICODE);
    $default_rules = json_encode([
        'ห้ามก่อกองไฟ ห้ามวางเตาถ่านบนสนามหญ้า',
        'ไม่ส่งเสียงดังรบกวนผู้อื่น',
        'งดใช้เสียงหลัง 22.00 น.',
        'ไม่ทิ้งขยะ เศษอาหารบนพื้นหญ้า',
        'ห้ามลงเล่นน้ำเด็ดขาด',
        'งดเครื่องดื่มแอลกอฮอล์',
    ], JSON_UNESCAPED_UNICODE);
    $default_contacts = json_encode([
        ['name'=>'คุณปอ','phone'=>'088-5522308'],
        ['name'=>'คุณออย','phone'=>'082-3069984'],
        ['name'=>'คุณโตโต้','phone'=>'086-8529944'],
    ], JSON_UNESCAPED_UNICODE);
    $note = 'หากมาถึงก่อนเวลา แล้วมีพื้นที่ว่างสามารถกางได้เลย';
    $ins = $conn->prepare("INSERT INTO kengcamp_info (early_checkin_note, equipment_json, activities_json, rules_json, contacts_json) VALUES (?,?,?,?,?)");
    $ins->bind_param("sssss", $note, $default_equipment, $default_activities, $default_rules, $default_contacts);
    $ins->execute(); $ins->close();
}

$row        = $conn->query("SELECT * FROM kengcamp_info ORDER BY id DESC LIMIT 1")->fetch_assoc();
$equipment  = json_decode($row['equipment_json']  ?? '[]', true) ?: [];
$activities = json_decode($row['activities_json'] ?? '[]', true) ?: [];
$rules      = json_decode($row['rules_json']      ?? '[]', true) ?: [];
$contacts   = json_decode($row['contacts_json']   ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เก็งแคมป์ — ENJOY YOUR LIFE WITH NATURE</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --ink:#1a1a2e;
  --gold:#c9a96e;
  --gold2:#e8d5b0;
  --bg:#f5f1eb;
  --card:#fff;
  --muted:#7a7a8c;
  --border:#e8e4de;
  --green:#1e3a1e;
  --green2:#2d5a27;
}
html{scroll-behavior:smooth}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);line-height:1.6;overflow-x:hidden}

/* ─── NAV ─── */
.nav{
  position:fixed;top:0;left:0;right:0;z-index:200;
  padding:0 32px;height:62px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(10,26,10,.72);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border-bottom:1px solid rgba(201,169,110,.18);
  transition:background .3s;
}
.nav-logo{
  font-family:'Playfair Display',serif;font-style:italic;
  font-size:1.3rem;color:#fff;text-decoration:none;
  display:flex;align-items:center;gap:8px;
}
.nav-logo span{color:var(--gold);}
.nav-links{display:flex;align-items:center;gap:6px;}
.nav-links a{
  padding:7px 16px;border-radius:999px;
  text-decoration:none;color:rgba(255,255,255,.8);
  font-size:.82rem;font-weight:600;
  transition:background .2s,color .2s;
}
.nav-links a:hover{background:rgba(201,169,110,.18);color:#fff}
.nav-links a.pill{
  background:var(--gold);color:var(--ink);
  font-weight:700;
}
.nav-links a.pill:hover{filter:brightness(1.08)}

/* ─── HERO ─── */
.hero{
  min-height:100vh;
  background:
    radial-gradient(ellipse at 20% 80%, rgba(45,90,39,.55) 0%, transparent 60%),
    radial-gradient(ellipse at 80% 20%, rgba(201,169,110,.12) 0%, transparent 50%),
    linear-gradient(170deg, #060d06 0%, #0d1f0d 35%, #111827 70%, #0a0e1a 100%);
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  text-align:center;padding:100px 24px 80px;
  position:relative;overflow:hidden;
}
/* animated tree silhouette pattern */
.hero::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background-image:
    radial-gradient(circle at 15% 85%, rgba(45,90,39,.4) 0 120px, transparent 120px),
    radial-gradient(circle at 85% 80%, rgba(45,90,39,.3) 0 90px, transparent 90px),
    radial-gradient(circle at 50% 95%, rgba(30,58,30,.5) 0 200px, transparent 200px);
}
.hero::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:180px;
  background:linear-gradient(to top,var(--bg),transparent);
  pointer-events:none;
}
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(201,169,110,.15);border:1px solid rgba(201,169,110,.4);
  color:var(--gold);padding:7px 20px;border-radius:999px;
  font-size:.72rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  margin-bottom:28px;position:relative;z-index:1;
}
.hero-badge-dot{width:6px;height:6px;border-radius:50%;background:var(--gold);
  box-shadow:0 0 0 0 rgba(201,169,110,.6);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(201,169,110,.5)}50%{box-shadow:0 0 0 8px rgba(201,169,110,0)}}
.hero h1{
  font-family:'Playfair Display',serif;font-style:italic;
  font-size:clamp(52px,9vw,96px);font-weight:700;line-height:1.05;
  color:#fff;position:relative;z-index:1;margin-bottom:8px;
  text-shadow:0 4px 40px rgba(0,0,0,.5);
}
.hero h1 em{color:var(--gold);font-style:inherit;}
.hero-en{
  font-size:.8rem;letter-spacing:.35em;text-transform:uppercase;
  color:rgba(255,255,255,.35);position:relative;z-index:1;margin-bottom:24px;
}
.hero-desc{
  max-width:560px;font-size:1.05rem;line-height:1.8;
  color:rgba(255,255,255,.65);position:relative;z-index:1;margin-bottom:40px;
}
.hero-actions{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;position:relative;z-index:1;}
.hero-btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:14px 28px;border-radius:999px;
  text-decoration:none;font-weight:700;font-size:.95rem;
  transition:transform .2s,box-shadow .2s,filter .2s;
}
.hero-btn-primary{background:var(--gold);color:var(--ink);}
.hero-btn-primary:hover{filter:brightness(1.08);transform:translateY(-2px);box-shadow:0 8px 28px rgba(201,169,110,.35)}
.hero-btn-ghost{
  background:rgba(255,255,255,.08);color:#fff;
  border:1px solid rgba(255,255,255,.22);
}
.hero-btn-ghost:hover{background:rgba(255,255,255,.14);transform:translateY(-2px)}

/* scroll hint */
.scroll-hint{
  position:absolute;bottom:32px;left:50%;transform:translateX(-50%);
  display:flex;flex-direction:column;align-items:center;gap:6px;
  color:rgba(255,255,255,.3);font-size:.68rem;letter-spacing:.15em;text-transform:uppercase;
  z-index:1;animation:bob 2.5s ease-in-out infinite;
}
@keyframes bob{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(6px)}}
.scroll-arrow{width:20px;height:20px;border-right:2px solid rgba(255,255,255,.25);border-bottom:2px solid rgba(255,255,255,.25);transform:rotate(45deg)}

/* ─── STAT STRIP ─── */
.stats{
  background:var(--ink);
  display:flex;justify-content:center;flex-wrap:wrap;
  gap:0;border-bottom:3px solid var(--gold);
}
.stat{
  flex:1;min-width:160px;max-width:260px;
  padding:28px 24px;text-align:center;
  border-right:1px solid rgba(255,255,255,.06);
  position:relative;
}
.stat:last-child{border-right:none}
.stat-num{font-size:2.4rem;font-weight:800;color:var(--gold);line-height:1}
.stat-unit{font-size:.75rem;color:rgba(255,255,255,.4);margin-top:4px;letter-spacing:.06em}

/* ─── SECTIONS ─── */
.wrap{width:min(1080px,92%);margin:0 auto}
.section{padding:72px 0}
.section-label{
  display:inline-flex;align-items:center;gap:10px;
  font-size:.68rem;font-weight:800;letter-spacing:.22em;text-transform:uppercase;
  color:var(--gold);margin-bottom:12px;
}
.section-label::before{content:'';width:28px;height:2px;background:var(--gold);}
.section-title{font-size:clamp(1.6rem,3.5vw,2.1rem);font-weight:800;color:var(--ink);margin-bottom:8px;line-height:1.2}
.section-sub{color:var(--muted);font-size:1rem;max-width:520px;line-height:1.7}

/* ─── PRICING ─── */
.price-section{background:var(--ink);padding:72px 0;position:relative;overflow:hidden}
.price-section::before{
  content:'';position:absolute;width:600px;height:600px;border-radius:50%;
  background:radial-gradient(circle,rgba(45,90,39,.2) 0%,transparent 70%);
  top:-200px;right:-150px;pointer-events:none;
}
.price-section .section-label{color:var(--gold2)}
.price-section .section-title{color:#fff}
.price-section .section-sub{color:rgba(255,255,255,.5)}
.price-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-top:40px}
.price-card{
  border-radius:20px;padding:32px 28px;text-align:center;
  border:1px solid rgba(255,255,255,.08);
  background:rgba(255,255,255,.04);
  transition:transform .25s,border-color .25s;position:relative;overflow:hidden;
}
.price-card::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(145deg,rgba(201,169,110,.06) 0%,transparent 60%);
}
.price-card:hover{transform:translateY(-4px);border-color:rgba(201,169,110,.35)}
.price-card.main{
  background:linear-gradient(145deg,rgba(201,169,110,.18) 0%,rgba(201,169,110,.06) 100%);
  border-color:rgba(201,169,110,.45);
}
.price-card .pc-icon{font-size:2.4rem;margin-bottom:16px}
.price-card .pc-label{font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:12px;font-weight:700}
.price-card .pc-price{font-size:3.2rem;font-weight:800;color:var(--gold);line-height:1}
.price-card .pc-unit{font-size:.82rem;color:rgba(255,255,255,.4);margin-top:6px}
.price-card .pc-note{
  margin-top:16px;padding:8px 16px;border-radius:999px;
  background:rgba(45,90,39,.3);border:1px solid rgba(45,90,39,.6);
  color:rgba(255,255,255,.7);font-size:.8rem;display:inline-block;
}

/* ─── TIME ─── */
.time-wrap{display:flex;align-items:stretch;gap:0;border-radius:20px;overflow:hidden;box-shadow:0 8px 40px rgba(26,26,46,.12);margin-top:36px}
.time-half{
  flex:1;padding:36px 28px;text-align:center;
  background:var(--card);border:1px solid var(--border);
  position:relative;
}
.time-half:first-child{border-right:none;border-radius:20px 0 0 20px}
.time-half:last-child{border-radius:0 20px 20px 0}
.time-half::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.time-half:first-child::before{background:linear-gradient(90deg,var(--gold),var(--gold2))}
.time-half:last-child::before{background:linear-gradient(90deg,var(--gold2),var(--gold))}
.time-icon{font-size:2rem;margin-bottom:10px}
.time-label{font-size:.68rem;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);font-weight:700;margin-bottom:8px}
.time-val{font-size:2.8rem;font-weight:800;color:var(--ink);line-height:1}
.time-sub{font-size:.78rem;color:var(--muted);margin-top:6px}
.time-note{
  margin-top:20px;padding:16px 24px;border-radius:14px;
  background:linear-gradient(135deg,rgba(201,169,110,.1),rgba(201,169,110,.05));
  border:1px solid rgba(201,169,110,.3);
  font-size:.9rem;color:var(--ink);text-align:center;line-height:1.7;
}
.time-note strong{color:var(--gold)}

/* ─── EQUIPMENT ─── */
.equip-section{background:#fff;padding:72px 0}
.equip-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px;margin-top:36px}
.equip-card{
  border:1.5px solid var(--border);border-radius:16px;
  padding:20px 20px;display:flex;align-items:center;gap:14px;
  transition:border-color .2s,transform .2s,box-shadow .2s;
  background:var(--bg);
}
.equip-card:hover{border-color:var(--gold);transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,26,46,.08)}
.equip-icon{
  width:46px;height:46px;border-radius:12px;flex-shrink:0;
  background:linear-gradient(135deg,rgba(201,169,110,.18),rgba(201,169,110,.08));
  border:1px solid rgba(201,169,110,.3);
  display:flex;align-items:center;justify-content:center;font-size:1.4rem;
}
.equip-name{font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:3px;line-height:1.3}
.equip-price{font-size:.82rem;color:var(--gold);font-weight:700}
.equip-unit{color:var(--muted);font-weight:400}

/* ─── ACTIVITIES ─── */
.act-section{background:var(--bg);padding:72px 0}
.act-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-top:36px}
.act-card{
  border-radius:20px;overflow:hidden;
  background:var(--card);border:1px solid var(--border);
  transition:transform .25s,box-shadow .25s;
}
.act-card:hover{transform:translateY(-4px);box-shadow:0 16px 48px rgba(26,26,46,.12)}
.act-top{
  height:140px;display:flex;align-items:center;justify-content:center;
  font-size:4rem;
  background:linear-gradient(135deg,#0d1f0d 0%,#1e3a1e 100%);
  position:relative;overflow:hidden;
}
.act-top::after{
  content:'';position:absolute;inset:0;
  background:radial-gradient(circle at 50% 120%,rgba(201,169,110,.18) 0%,transparent 60%);
}
.act-body{padding:22px}
.act-name{font-size:1.1rem;font-weight:800;color:var(--ink);margin-bottom:8px}
.act-tag{
  display:inline-flex;align-items:center;gap:6px;
  padding:5px 14px;border-radius:999px;font-size:.75rem;font-weight:700;
}
.act-tag.paid{background:rgba(201,169,110,.12);color:#a07c3a;border:1px solid rgba(201,169,110,.3)}
.act-tag.free{background:rgba(45,160,45,.1);color:#1a7a1a;border:1px solid rgba(45,160,45,.25)}

/* ─── RULES ─── */
.rules-section{
  background:linear-gradient(160deg,#0a1a0a 0%,#111827 100%);
  padding:72px 0;
}
.rules-section .section-label,.rules-section .section-title{color:#fff}
.rules-section .section-sub{color:rgba(255,255,255,.5)}
.rules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-top:36px}
.rule-card{
  display:flex;align-items:flex-start;gap:14px;
  padding:18px 20px;border-radius:14px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  transition:background .2s,border-color .2s;
}
.rule-card:hover{background:rgba(255,255,255,.07);border-color:rgba(201,169,110,.25)}
.rule-num{
  width:30px;height:30px;border-radius:50%;flex-shrink:0;
  background:rgba(201,169,110,.15);border:1px solid rgba(201,169,110,.4);
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;font-weight:800;color:var(--gold);
}
.rule-text{font-size:.9rem;color:rgba(255,255,255,.75);line-height:1.6;padding-top:3px}

/* ─── CONTACT ─── */
.contact-section{background:var(--bg);padding:72px 0}
.contact-inner{display:grid;grid-template-columns:1fr auto;gap:48px;align-items:start;margin-top:40px}
.contact-cards{display:flex;flex-direction:column;gap:14px}
.contact-card{
  display:flex;align-items:center;gap:18px;
  padding:20px 24px;border-radius:16px;
  background:var(--card);border:1.5px solid var(--border);
  transition:border-color .2s,transform .2s,box-shadow .2s;
}
.contact-card:hover{border-color:var(--gold);transform:translateX(4px);box-shadow:0 4px 20px rgba(26,26,46,.08)}
.contact-avatar{
  width:50px;height:50px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--ink),#252545);
  display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;border:2px solid rgba(201,169,110,.3);
}
.contact-name{font-weight:800;color:var(--ink);font-size:1rem;margin-bottom:2px}
.contact-phone{
  font-size:1.1rem;font-weight:700;color:var(--gold);
  text-decoration:none;
}
.contact-phone:hover{text-decoration:underline}
.qr-wrap{
  background:var(--card);border-radius:20px;padding:24px;
  border:1.5px solid var(--border);text-align:center;
  box-shadow:0 4px 24px rgba(26,26,46,.07);
}
.qr-wrap img{width:160px;height:160px;object-fit:cover;border-radius:12px;margin-bottom:10px;display:block;}
.qr-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}

/* ─── FOOTER ─── */
.footer{background:var(--ink);padding:32px 24px;text-align:center}
.footer-logo{font-family:'Playfair Display',serif;font-style:italic;font-size:1.6rem;color:#fff;margin-bottom:6px}
.footer-logo span{color:var(--gold)}
.footer-sub{font-size:.78rem;color:rgba(255,255,255,.35);letter-spacing:.06em}

/* ─── RESPONSIVE ─── */
@media(max-width:768px){
  .nav{padding:0 16px}
  .nav-links a:not(.pill){display:none}
  .time-wrap{flex-direction:column}
  .time-half:first-child{border-right:1px solid var(--border);border-radius:20px 20px 0 0;border-bottom:none}
  .time-half:last-child{border-radius:0 0 20px 20px}
  .contact-inner{grid-template-columns:1fr}
  .qr-wrap{max-width:200px;margin:0 auto}
}
</style>
</head>
<body>

<!-- ─── NAV ─── -->
<nav class="nav">
  <a href="index.php" class="nav-logo">⛺ <span>เก็งแคมป์</span></a>
  <div class="nav-links">
    <a href="index.php">หน้าหลัก</a>
    <a href="view_data.php">ลงทะเบียน</a>
    <a href="#contact" class="pill">📞 ติดต่อ</a>
  </div>
</nav>

<!-- ─── HERO ─── -->
<section class="hero">
  <div class="hero-badge">
    <span class="hero-badge-dot"></span>
    เปิดรับนักท่องเที่ยว · WRBRI MSU
  </div>
  <h1><em>เก็งแคมป์</em></h1>
  <div class="hero-en">Enjoy Your Life With Nature</div>
  <p class="hero-desc">
    สัมผัสธรรมชาติแท้ๆ กลางป่าวลัยรุกขเวช<br>
    กางเต็นท์ใต้ดาว พายเรือในลำธาร เดินป่าศึกษาธรรมชาติ<br>
    มหาวิทยาลัยมหาสารคาม
  </p>
  <div class="hero-actions">
    <a href="#pricing" class="hero-btn hero-btn-primary">⛺ ดูราคา & บริการ</a>
    <a href="#contact" class="hero-btn hero-btn-ghost">📞 ติดต่อเรา</a>
  </div>
  <div class="scroll-hint">
    <div class="scroll-arrow"></div>
    เลื่อนดูข้อมูล
  </div>
</section>

<!-- ─── STAT STRIP ─── -->
<div class="stats">
  <div class="stat">
    <div class="stat-num">฿<?= number_format((float)($row['entry_fee']??50)) ?></div>
    <div class="stat-unit">บาท / คน / คืน</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= htmlspecialchars($row['checkin_time']??'14.00 น.') ?></div>
    <div class="stat-unit">เวลาเช็คอิน</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= htmlspecialchars($row['checkout_time']??'12.00 น.') ?></div>
    <div class="stat-unit">เวลาเช็คเอาท์</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= count($activities) ?></div>
    <div class="stat-unit">กิจกรรมในพื้นที่</div>
  </div>
</div>

<!-- ─── PRICING ─── -->
<section class="price-section" id="pricing">
  <div class="wrap">
    <div class="section-label">อัตราค่าบริการ</div>
    <div class="section-title" style="color:#fff">ราคาที่คุ้มค่า เข้าถึงได้</div>
    <div class="section-sub">โปร่งใส ไม่มีค่าใช้จ่ายซ่อนเร้น</div>
    <div class="price-cards">
      <div class="price-card main">
        <div class="pc-icon">🏕️</div>
        <div class="pc-label">ค่าเข้ากางเต็นท์</div>
        <div class="pc-price">฿<?= number_format((float)($row['entry_fee']??50)) ?></div>
        <div class="pc-unit">บาท / คน / คืน</div>
        <div class="pc-note">🎒 เด็กอายุต่ำกว่า <?= (int)($row['children_free_age']??9) ?> ขวบ เข้าฟรี!</div>
      </div>
      <div class="price-card">
        <div class="pc-icon">🚗</div>
        <div class="pc-label">เต็นท์หลังคารถ / นอนในรถ</div>
        <div class="pc-price">฿<?= number_format((float)($row['rooftop_fee']??300)) ?></div>
        <div class="pc-unit">บาท / คัน / คืน</div>
        <div class="pc-note" style="background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.1);">พักในรถได้เลย</div>
      </div>
    </div>
  </div>
</section>

<!-- ─── CHECK-IN / OUT ─── -->
<section class="section" style="background:var(--bg)">
  <div class="wrap">
    <div class="section-label">เวลาบริการ</div>
    <div class="section-title">เช็คอิน — เช็คเอาท์</div>
    <div class="time-wrap">
      <div class="time-half">
        <div class="time-icon">🌤️</div>
        <div class="time-label">เวลาเช็คอิน</div>
        <div class="time-val"><?= htmlspecialchars($row['checkin_time']??'14.00 น.') ?></div>
        <div class="time-sub">เริ่มเข้าพักได้ตั้งแต่บ่าย</div>
      </div>
      <div class="time-half">
        <div class="time-icon">🌅</div>
        <div class="time-label">เวลาเช็คเอาท์</div>
        <div class="time-val"><?= htmlspecialchars($row['checkout_time']??'12.00 น.') ?></div>
        <div class="time-sub">กรุณาออกก่อนเที่ยงวัน</div>
      </div>
    </div>
    <?php if (!empty($row['early_checkin_note'])): ?>
    <div class="time-note">
      <strong>★</strong> <?= htmlspecialchars($row['early_checkin_note']) ?> <strong>★</strong>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ─── EQUIPMENT ─── -->
<?php if (!empty($equipment)): ?>
<section class="equip-section">
  <div class="wrap">
    <div class="section-label">อุปกรณ์ให้เช่า</div>
    <div class="section-title">ไม่ต้องพกมาก เช่าได้ที่นี่</div>
    <div class="section-sub">อุปกรณ์ครบ ราคาเป็นมิตร พร้อมรับทุกความต้องการ</div>
    <?php
    $eq_icons = ['🏕️','🏕️','🪑','🪑','🛏️','🛌','🪑','🛏️','🌂','⛺'];
    ?>
    <div class="equip-grid">
      <?php foreach ($equipment as $i=>$eq): ?>
      <div class="equip-card">
        <div class="equip-icon"><?= $eq_icons[$i % count($eq_icons)] ?></div>
        <div>
          <div class="equip-name"><?= htmlspecialchars($eq['name']??'') ?></div>
          <?php if (!empty($eq['price']) && (float)$eq['price'] > 0): ?>
          <div class="equip-price">฿<?= number_format((float)$eq['price']) ?>.-
            <span class="equip-unit">/ <?= htmlspecialchars($eq['unit']??'') ?></span>
          </div>
          <?php else: ?>
          <div class="equip-price" style="color:#2e7d32;">ฟรี</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── ACTIVITIES ─── -->
<?php if (!empty($activities)): ?>
<?php $act_icons = ['🚣','🌿','🦋','🌄','🏕️','⛵','🌳','🐦']; ?>
<section class="act-section">
  <div class="wrap">
    <div class="section-label">กิจกรรม</div>
    <div class="section-title">สนุกกับกิจกรรมในพื้นที่</div>
    <div class="section-sub">ประสบการณ์ที่หาไม่ได้จากที่ไหน</div>
    <div class="act-cards">
      <?php foreach ($activities as $i=>$act): ?>
      <div class="act-card">
        <div class="act-top"><?= $act_icons[$i % count($act_icons)] ?></div>
        <div class="act-body">
          <div class="act-name"><?= htmlspecialchars($act['name']??'') ?></div>
          <?php if (!empty($act['price']) && (float)$act['price'] > 0): ?>
          <span class="act-tag paid">฿<?= number_format((float)$act['price']) ?> / <?= htmlspecialchars($act['unit']??'คน') ?></span>
          <?php else: ?>
          <span class="act-tag free">✓ ไม่มีค่าใช้จ่าย</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── RULES ─── -->
<?php if (!empty($rules)): ?>
<section class="rules-section">
  <div class="wrap">
    <div class="section-label">ข้อปฏิบัติ</div>
    <div class="section-title">กฎระเบียบในพื้นที่</div>
    <div class="section-sub">เพื่อความสุขของทุกคน กรุณาปฏิบัติตามอย่างเคร่งครัด</div>
    <div class="rules-grid">
      <?php foreach ($rules as $i=>$rule): ?>
      <div class="rule-card">
        <div class="rule-num"><?= $i+1 ?></div>
        <div class="rule-text"><?= htmlspecialchars($rule) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── CONTACT ─── -->
<section class="contact-section" id="contact">
  <div class="wrap">
    <div class="section-label">ติดต่อ</div>
    <div class="section-title">สอบถามข้อมูลเพิ่มเติม</div>
    <div class="section-sub">ทีมงานพร้อมตอบทุกคำถาม</div>
    <div class="contact-inner">
      <div class="contact-cards">
        <?php
        $avatars = ['👩','👨','🧑','👩‍🦱','👨‍🦱'];
        foreach ($contacts as $i=>$c):
        ?>
        <div class="contact-card">
          <div class="contact-avatar"><?= $avatars[$i % count($avatars)] ?></div>
          <div>
            <div class="contact-name"><?= htmlspecialchars($c['name']??'') ?></div>
            <a href="tel:<?= preg_replace('/[^0-9]/','',$c['phone']??'') ?>"
               class="contact-phone"><?= htmlspecialchars($c['phone']??'') ?></a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($row['qr_image']) && file_exists(__DIR__.'/'.$row['qr_image'])): ?>
      <div class="qr-wrap">
        <img src="<?= htmlspecialchars($row['qr_image']) ?>" alt="QR Code">
        <div class="qr-label">📱 QR Code ชำระเงิน</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ─── FOOTER ─── -->
<footer class="footer">
  <div class="footer-logo"><span>เก็ง</span>แคมป์</div>
  <div class="footer-sub">สถาบันวิจัยวลัยรุกขเวช · มหาวิทยาลัยมหาสารคาม · WRBRI MSU</div>
</footer>

</body>
</html>
