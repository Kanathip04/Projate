<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองที่พัก | สถาบันวิจัยวลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --forest-dark: #1a2e1a;
    --forest-mid: #2d4a2d;
    --forest-light: #3d6b3d;
    --bark: #5c3d1e;
    --bark-light: #8b6340;
    --moss: #6b8c4a;
    --leaf: #a8c66c;
    --cream: #f5f0e8;
    --sand: #e8ddc8;
    --amber: #d4a843;
    --fire: #e07b39;
    --smoke: rgba(245,240,232,0.08);
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Sarabun', sans-serif;
    background-color: var(--forest-dark);
    color: var(--cream);
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* Background texture */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse at 20% 20%, rgba(61,107,61,0.15) 0%, transparent 50%),
      radial-gradient(ellipse at 80% 80%, rgba(92,61,30,0.12) 0%, transparent 50%),
      url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
  }

  /* Navbar */
  nav {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(26,46,26,0.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(168,198,108,0.15);
    padding: 14px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .nav-logo {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .logo-circle {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, var(--amber), var(--bark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    color: var(--forest-dark);
    font-family: 'Kanit', sans-serif;
    box-shadow: 0 0 0 2px rgba(212,168,67,0.3);
  }

  .nav-title { font-family: 'Kanit', sans-serif; font-size: 15px; font-weight: 600; color: var(--cream); line-height: 1.3; }
  .nav-sub { font-size: 11px; color: var(--leaf); opacity: 0.8; }

  .nav-links { display: flex; gap: 32px; }
  .nav-links a { color: rgba(245,240,232,0.7); text-decoration: none; font-size: 14px; transition: color 0.2s; }
  .nav-links a:hover, .nav-links a.active { color: var(--leaf); }
  .nav-links a.active { font-weight: 600; }

  /* Hero */
  .hero {
    position: relative;
    z-index: 1;
    padding: 60px 40px 40px;
    text-align: center;
  }

  .hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(168,198,108,0.12);
    border: 1px solid rgba(168,198,108,0.3);
    border-radius: 100px;
    padding: 6px 18px;
    font-size: 12px;
    color: var(--leaf);
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 20px;
    animation: fadeDown 0.6s ease both;
  }

  .hero h1 {
    font-family: 'Kanit', sans-serif;
    font-size: clamp(2.2rem, 4vw, 3.4rem);
    font-weight: 700;
    line-height: 1.15;
    color: var(--cream);
    animation: fadeDown 0.6s 0.1s ease both;
  }

  .hero h1 span { color: var(--amber); }

  .hero p {
    margin-top: 14px;
    color: rgba(245,240,232,0.6);
    font-size: 16px;
    animation: fadeDown 0.6s 0.2s ease both;
  }

  /* Divider trees */
  .tree-divider {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin: 24px 0;
    opacity: 0.4;
    font-size: 22px;
  }

  /* Main layout */
  .main-container {
    position: relative;
    z-index: 1;
    max-width: 1160px;
    margin: 0 auto;
    padding: 0 24px 80px;
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 32px;
    align-items: start;
  }

  /* Card base */
  .card {
    background: rgba(30,50,28,0.7);
    border: 1px solid rgba(168,198,108,0.12);
    border-radius: 20px;
    backdrop-filter: blur(8px);
    overflow: hidden;
  }

  .card-header {
    padding: 22px 28px 18px;
    border-bottom: 1px solid rgba(168,198,108,0.1);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .card-icon {
    width: 38px;
    height: 38px;
    background: rgba(168,198,108,0.12);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
  }

  .card-title {
    font-family: 'Kanit', sans-serif;
    font-size: 17px;
    font-weight: 600;
    color: var(--cream);
  }

  .card-body { padding: 24px 28px; }

  /* Room cards */
  .rooms-grid { display: flex; flex-direction: column; gap: 16px; }

  .room-card {
    background: rgba(26,46,26,0.6);
    border: 2px solid rgba(168,198,108,0.1);
    border-radius: 16px;
    display: grid;
    grid-template-columns: 130px 1fr auto;
    gap: 0;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
  }

  .room-card:hover {
    border-color: rgba(168,198,108,0.4);
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
  }

  .room-card.selected {
    border-color: var(--leaf);
    background: rgba(45,74,45,0.7);
  }

  .room-card.selected::after {
    content: '✓ เลือกแล้ว';
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--leaf);
    color: var(--forest-dark);
    font-size: 11px;
    font-weight: 700;
    font-family: 'Kanit', sans-serif;
    padding: 3px 10px;
    border-radius: 100px;
  }

  .room-img {
    width: 130px;
    height: 100%;
    min-height: 110px;
    position: relative;
    overflow: hidden;
  }

  .room-img-inner {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    background: linear-gradient(135deg, var(--forest-mid), var(--bark));
  }

  /* Each room type has its own bg */
  .type-tent { background: linear-gradient(135deg, #2d4a2d 0%, #1a3520 100%); }
  .type-cabin { background: linear-gradient(135deg, #3d2a1a 0%, #5c3d1e 100%); }
  .type-glamping { background: linear-gradient(135deg, #1a2e3d 0%, #2d4a5c 100%); }

  .room-info { padding: 16px 20px; }

  .room-name {
    font-family: 'Kanit', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: var(--cream);
    margin-bottom: 6px;
  }

  .room-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }

  .badge {
    background: rgba(168,198,108,0.1);
    border: 1px solid rgba(168,198,108,0.2);
    border-radius: 100px;
    padding: 2px 10px;
    font-size: 11px;
    color: var(--leaf);
  }

  .room-desc { font-size: 12px; color: rgba(245,240,232,0.5); line-height: 1.5; }

  .room-price-col {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 6px;
    min-width: 110px;
  }

  .price-per { font-size: 10px; color: rgba(245,240,232,0.4); }
  .price-amount {
    font-family: 'Kanit', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--amber);
    line-height: 1;
  }
  .price-unit { font-size: 11px; color: rgba(245,240,232,0.5); }

  /* Availability dot */
  .avail-dot {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    margin-top: 4px;
  }
  .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #4caf50;
    box-shadow: 0 0 6px #4caf50;
    animation: pulse 2s infinite;
  }
  .dot.low { background: var(--fire); box-shadow: 0 0 6px var(--fire); }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

  /* Form section */
  .form-section { display: flex; flex-direction: column; gap: 20px; }

  .form-group { display: flex; flex-direction: column; gap: 8px; }

  label {
    font-size: 13px;
    font-weight: 500;
    color: rgba(245,240,232,0.7);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  label span { font-size: 15px; }

  .input-wrap { position: relative; }

  input, select, textarea {
    width: 100%;
    background: rgba(26,46,26,0.8);
    border: 1.5px solid rgba(168,198,108,0.2);
    border-radius: 12px;
    padding: 12px 16px;
    color: var(--cream);
    font-family: 'Sarabun', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    -webkit-appearance: none;
  }

  input:focus, select:focus, textarea:focus {
    border-color: var(--leaf);
    box-shadow: 0 0 0 3px rgba(168,198,108,0.1);
  }

  select option { background: var(--forest-dark); }
  textarea { resize: vertical; min-height: 80px; }

  .date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

  /* Night counter */
  .nights-badge {
    background: rgba(212,168,67,0.15);
    border: 1px solid rgba(212,168,67,0.3);
    border-radius: 10px;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: rgba(245,240,232,0.7);
    margin-top: -8px;
  }
  .nights-num { font-family: 'Kanit', sans-serif; font-size: 20px; color: var(--amber); font-weight: 700; }

  /* Guest counter */
  .guest-counter {
    display: flex;
    align-items: center;
    gap: 16px;
    background: rgba(26,46,26,0.8);
    border: 1.5px solid rgba(168,198,108,0.2);
    border-radius: 12px;
    padding: 8px 16px;
  }
  .counter-btn {
    width: 32px; height: 32px;
    background: rgba(168,198,108,0.15);
    border: 1px solid rgba(168,198,108,0.3);
    border-radius: 8px;
    color: var(--leaf);
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
    line-height: 1;
    user-select: none;
    font-family: monospace;
  }
  .counter-btn:hover { background: rgba(168,198,108,0.3); }
  .counter-val { font-family: 'Kanit', sans-serif; font-size: 22px; font-weight: 600; color: var(--cream); min-width: 32px; text-align: center; }
  .counter-label { font-size: 13px; color: rgba(245,240,232,0.5); margin-left: auto; }

  /* Services checkboxes */
  .services-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .service-item {
    background: rgba(26,46,26,0.6);
    border: 1.5px solid rgba(168,198,108,0.12);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
  }
  .service-item:hover { border-color: rgba(168,198,108,0.3); }
  .service-item.checked {
    border-color: var(--leaf);
    background: rgba(45,74,45,0.5);
  }
  .service-check {
    width: 20px; height: 20px;
    border: 2px solid rgba(168,198,108,0.3);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
    font-size: 12px;
  }
  .service-item.checked .service-check {
    background: var(--leaf);
    border-color: var(--leaf);
    color: var(--forest-dark);
  }
  .service-text { font-size: 12px; line-height: 1.4; }
  .service-name { font-weight: 500; color: var(--cream); }
  .service-price { color: var(--amber); font-size: 11px; }
  .service-emoji { font-size: 18px; }

  /* Summary card */
  .summary-card { position: sticky; top: 80px; }

  .summary-body { padding: 24px; }

  .summary-room-preview {
    background: rgba(26,46,26,0.6);
    border-radius: 12px;
    padding: 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
    border: 1px solid rgba(168,198,108,0.15);
  }
  .preview-emoji { font-size: 32px; }
  .preview-name { font-family: 'Kanit', sans-serif; font-size: 15px; font-weight: 600; }
  .preview-type { font-size: 12px; color: var(--leaf); }

  .summary-lines { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
  .summary-line {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: rgba(245,240,232,0.65);
  }
  .summary-line .val { color: var(--cream); font-weight: 500; }
  .summary-divider {
    height: 1px;
    background: rgba(168,198,108,0.1);
    margin: 4px 0;
  }
  .summary-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding-top: 4px;
  }
  .total-label {
    font-family: 'Kanit', sans-serif;
    font-size: 15px;
    color: var(--cream);
  }
  .total-amount {
    font-family: 'Kanit', sans-serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--amber);
  }
  .total-unit { font-size: 13px; color: rgba(245,240,232,0.4); margin-left: 4px; }

  /* Campfire CTA */
  .cta-btn {
    width: 100%;
    background: linear-gradient(135deg, var(--moss), var(--forest-light));
    border: none;
    border-radius: 14px;
    padding: 16px;
    font-family: 'Kanit', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: var(--cream);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
    margin-top: 20px;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 20px rgba(61,107,61,0.4);
  }
  .cta-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.08), transparent);
    opacity: 0;
    transition: opacity 0.3s;
  }
  .cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(61,107,61,0.5); }
  .cta-btn:hover::before { opacity: 1; }
  .cta-btn:active { transform: translateY(0); }

  .cta-note {
    text-align: center;
    font-size: 11px;
    color: rgba(245,240,232,0.35);
    margin-top: 12px;
    line-height: 1.6;
  }

  /* Policies */
  .policies {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .policy-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 12px;
    color: rgba(245,240,232,0.45);
    line-height: 1.5;
  }
  .policy-item::before { content: '🌿'; flex-shrink: 0; }

  /* Section heading */
  .section-heading {
    font-family: 'Kanit', sans-serif;
    font-size: 18px;
    font-weight: 600;
    color: var(--cream);
    margin-bottom: 4px;
  }
  .section-sub {
    font-size: 12px;
    color: rgba(245,240,232,0.45);
    margin-bottom: 18px;
  }

  /* Animations */
  @keyframes fadeDown { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }

  .animate-in { animation: fadeDown 0.5s ease both; }
  .delay-1 { animation-delay: 0.1s; }
  .delay-2 { animation-delay: 0.2s; }
  .delay-3 { animation-delay: 0.3s; }
  .delay-4 { animation-delay: 0.4s; }

  /* Bottom nav trail */
  .nav-trail {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: rgba(245,240,232,0.4);
    margin-bottom: 20px;
  }
  .nav-trail a { color: var(--leaf); text-decoration: none; }
  .nav-trail span { opacity: 0.5; }

  /* Responsive */
  @media (max-width: 900px) {
    .main-container { grid-template-columns: 1fr; }
    .summary-card { position: static; }
    nav { padding: 12px 20px; }
    .nav-links { gap: 18px; font-size: 13px; }
  }
  @media (max-width: 600px) {
    .room-card { grid-template-columns: 80px 1fr; }
    .room-price-col { grid-column: 1 / -1; border-top: 1px solid rgba(168,198,108,0.1); flex-direction: row; justify-content: space-between; align-items: center; }
    .date-row { grid-template-columns: 1fr; }
    .services-grid { grid-template-columns: 1fr; }
    .hero { padding: 40px 20px 24px; }
    .main-container { padding: 0 16px 60px; }
  }
</style>
</head>
<body>

<!-- Nav -->
<nav>
  <div class="nav-logo">
    <div class="logo-circle">W</div>
    <div>
      <div class="nav-title">สถาบันวิจัยวลัยรุกขเวช</div>
      <div class="nav-sub">มหาวิทยาลัยมหาสารคาม</div>
    </div>
  </div>
  <div class="nav-links">
    <a href="#">ข่าวสาร</a>
    <a href="#" class="active">ลงทะเบียน</a>
    <a href="#">แบบประเมิน</a>
    <a href="#">ปฏิทิน</a>
    <a href="#">เกี่ยวกับ</a>
  </div>
</nav>

<!-- Hero -->
<div class="hero">
  <div class="hero-tag">🌿 จองที่พักแคมป์ปิ้ง</div>
  <h1>จองที่พักใน<span>ธรรมชาติ</span><br>วลัยรุกขเวช</h1>
  <p>สัมผัสประสบการณ์การพักผ่อนท่ามกลางป่าไม้ บรรยากาศสงบและร่มรื่น</p>
  <div class="tree-divider">🌲 🌳 🌲 🌿 🌲 🌳 🌲</div>
</div>

<!-- Main -->
<div class="main-container">

  <!-- Left column -->
  <div>

    <!-- Breadcrumb -->
    <div class="nav-trail">
      <a href="#">หน้าหลัก</a>
      <span>›</span>
      <a href="#">ที่พัก</a>
      <span>›</span>
      <span style="color:var(--cream)">จองที่พัก</span>
    </div>

    <!-- Room selection -->
    <div class="card animate-in">
      <div class="card-header">
        <div class="card-icon">🏕️</div>
        <div>
          <div class="card-title">เลือกประเภทที่พัก</div>
        </div>
      </div>
      <div class="card-body">
        <p class="section-sub">คลิกเพื่อเลือกประเภทที่พักที่ต้องการ</p>
        <div class="rooms-grid">

          <!-- Tent -->
          <div class="room-card selected" onclick="selectRoom(this,'เต็นท์มาตรฐาน','⛺','650')">
            <div class="room-img">
              <div class="room-img-inner type-tent">⛺</div>
            </div>
            <div class="room-info">
              <div class="room-name">เต็นท์มาตรฐาน</div>
              <div class="room-badges">
                <span class="badge">2 ท่าน</span>
                <span class="badge">กลางแจ้ง</span>
                <span class="badge">เครื่องนอน</span>
              </div>
              <div class="room-desc">เต็นท์สำหรับ 2 ท่าน พร้อมถุงนอนและหมอน<br>ใกล้ศาลาส่วนกลาง</div>
            </div>
            <div class="room-price-col">
              <span class="price-per">ราคาต่อคืน</span>
              <span class="price-amount">650</span>
              <span class="price-unit">บาท</span>
              <div class="avail-dot"><span class="dot"></span><span style="color:rgba(245,240,232,0.4);font-size:11px">ว่าง 8</span></div>
            </div>
          </div>

          <!-- Cabin -->
          <div class="room-card" onclick="selectRoom(this,'เรือนไม้กระท่อม','🛖','1,200')">
            <div class="room-img">
              <div class="room-img-inner type-cabin">🛖</div>
            </div>
            <div class="room-info">
              <div class="room-name">เรือนไม้กระท่อม</div>
              <div class="room-badges">
                <span class="badge">4 ท่าน</span>
                <span class="badge">ห้องน้ำใน</span>
                <span class="badge">แอร์</span>
              </div>
              <div class="room-desc">กระท่อมไม้สัก ห้องน้ำในตัว<br>วิวสวนป่าสวยงาม</div>
            </div>
            <div class="room-price-col">
              <span class="price-per">ราคาต่อคืน</span>
              <span class="price-amount">1,200</span>
              <span class="price-unit">บาท</span>
              <div class="avail-dot"><span class="dot low"></span><span style="color:rgba(245,240,232,0.4);font-size:11px">ว่าง 2</span></div>
            </div>
          </div>

          <!-- Glamping -->
          <div class="room-card" onclick="selectRoom(this,'กลามปิ้ง Deluxe','🌙','2,400')">
            <div class="room-img">
              <div class="room-img-inner type-glamping">🌙</div>
            </div>
            <div class="room-info">
              <div class="room-name">กลามปิ้ง Deluxe</div>
              <div class="room-badges">
                <span class="badge">2 ท่าน</span>
                <span class="badge">เตียงใหญ่</span>
                <span class="badge">Luxury</span>
              </div>
              <div class="room-desc">โดมโปร่งแสง ชมดาวในคืนใสๆ<br>ตกแต่งสไตล์ Boho ท่ามกลางธรรมชาติ</div>
            </div>
            <div class="room-price-col">
              <span class="price-per">ราคาต่อคืน</span>
              <span class="price-amount">2,400</span>
              <span class="price-unit">บาท</span>
              <div class="avail-dot"><span class="dot"></span><span style="color:rgba(245,240,232,0.4);font-size:11px">ว่าง 5</span></div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Date & Guest -->
    <div class="card animate-in delay-1" style="margin-top:20px">
      <div class="card-header">
        <div class="card-icon">📅</div>
        <div class="card-title">เลือกวันที่และจำนวนผู้เข้าพัก</div>
      </div>
      <div class="card-body">
        <div class="form-section">
          <div class="date-row">
            <div class="form-group">
              <label><span>🌅</span> วันเช็คอิน</label>
              <input type="date" id="checkin" onchange="calcNights()">
            </div>
            <div class="form-group">
              <label><span>🌄</span> วันเช็คเอาต์</label>
              <input type="date" id="checkout" onchange="calcNights()">
            </div>
          </div>

          <div class="nights-badge" id="nightsBadge" style="display:none">
            <span>🔥 ระยะเวลาพัก</span>
            <span><span class="nights-num" id="nightsNum">0</span> คืน</span>
          </div>

          <div class="form-group">
            <label><span>👥</span> จำนวนผู้เข้าพัก</label>
            <div class="guest-counter">
              <div class="counter-btn" onclick="changeGuest(-1)">−</div>
              <div class="counter-val" id="guestCount">2</div>
              <div class="counter-btn" onclick="changeGuest(1)">+</div>
              <div class="counter-label">ท่าน</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Extras -->
    <div class="card animate-in delay-2" style="margin-top:20px">
      <div class="card-header">
        <div class="card-icon">🎒</div>
        <div class="card-title">บริการเสริม</div>
      </div>
      <div class="card-body">
        <p class="section-sub">เพิ่มความสะดวกและความสนุกให้กับการเดินทาง</p>
        <div class="services-grid">
          <div class="service-item" onclick="toggleService(this,200)">
            <span class="service-emoji">🍳</span>
            <div class="service-text">
              <div class="service-name">อาหารเช้า</div>
              <div class="service-price">+200 บาท/ท่าน</div>
            </div>
            <div class="service-check" style="margin-left:auto">✓</div>
          </div>
          <div class="service-item checked" onclick="toggleService(this,150)">
            <span class="service-emoji">🔥</span>
            <div class="service-text">
              <div class="service-name">ชุดบาร์บีคิว</div>
              <div class="service-price">+150 บาท/ชุด</div>
            </div>
            <div class="service-check" style="margin-left:auto">✓</div>
          </div>
          <div class="service-item" onclick="toggleService(this,100)">
            <span class="service-emoji">🚴</span>
            <div class="service-text">
              <div class="service-name">เช่าจักรยาน</div>
              <div class="service-price">+100 บาท/คัน</div>
            </div>
            <div class="service-check" style="margin-left:auto">✓</div>
          </div>
          <div class="service-item" onclick="toggleService(this,300)">
            <span class="service-emoji">🥾</span>
            <div class="service-text">
              <div class="service-name">ไกด์นำเที่ยวป่า</div>
              <div class="service-price">+300 บาท/คน</div>
            </div>
            <div class="service-check" style="margin-left:auto">✓</div>
          </div>
          <div class="service-item" onclick="toggleService(this,80)">
            <span class="service-emoji">🌿</span>
            <div class="service-text">
              <div class="service-name">สปาสมุนไพร</div>
              <div class="service-price">+80 บาท/ท่าน</div>
            </div>
            <div class="service-check" style="margin-left:auto">✓</div>
          </div>
          <div class="service-item" onclick="toggleService(this,50)">
            <span class="service-emoji">📸</span>
            <div class="service-text">
              <div class="service-name">ถ่ายภาพเช้า</div>
              <div class="service-price">+50 บาท/ชุด</div>
            </div>
            <div class="service-check" style="margin-left:auto">✓</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact -->
    <div class="card animate-in delay-3" style="margin-top:20px">
      <div class="card-header">
        <div class="card-icon">🏡</div>
        <div class="card-title">ข้อมูลผู้จอง</div>
      </div>
      <div class="card-body">
        <div class="form-section">
          <div class="date-row">
            <div class="form-group">
              <label><span>👤</span> ชื่อ-นามสกุล</label>
              <input type="text" placeholder="กรอกชื่อ-นามสกุล">
            </div>
            <div class="form-group">
              <label><span>📞</span> เบอร์โทรศัพท์</label>
              <input type="tel" placeholder="0XX-XXX-XXXX">
            </div>
          </div>
          <div class="form-group">
            <label><span>✉️</span> อีเมล</label>
            <input type="email" placeholder="example@email.com">
          </div>
          <div class="form-group">
            <label><span>📝</span> หมายเหตุพิเศษ</label>
            <textarea placeholder="ต้องการอะไรพิเศษ? เช่น อาหารมังสวิรัติ ยาแพ้ ฯลฯ"></textarea>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Summary card -->
  <div class="summary-card card animate-in delay-4">
    <div class="card-header">
      <div class="card-icon">🧾</div>
      <div class="card-title">สรุปการจอง</div>
    </div>
    <div class="summary-body">

      <div class="summary-room-preview">
        <div class="preview-emoji" id="previewEmoji">⛺</div>
        <div>
          <div class="preview-name" id="previewName">เต็นท์มาตรฐาน</div>
          <div class="preview-type">🌲 สถาบันวิจัยวลัยรุกขเวช</div>
        </div>
      </div>

      <div class="summary-lines">
        <div class="summary-line">
          <span>ประเภทที่พัก</span>
          <span class="val" id="sumRoom">เต็นท์มาตรฐาน</span>
        </div>
        <div class="summary-line">
          <span>ราคาต่อคืน</span>
          <span class="val" id="sumPrice">650 บาท</span>
        </div>
        <div class="summary-line">
          <span>จำนวนคืน</span>
          <span class="val" id="sumNights">1 คืน</span>
        </div>
        <div class="summary-line">
          <span>จำนวนผู้เข้าพัก</span>
          <span class="val" id="sumGuests">2 ท่าน</span>
        </div>
        <div class="summary-line">
          <span>วันที่เข้าพัก</span>
          <span class="val" id="sumCheckin">-</span>
        </div>
        <div class="summary-line">
          <span>วันที่เช็คเอาต์</span>
          <span class="val" id="sumCheckout">-</span>
        </div>
        <div class="summary-line">
          <span>บริการเสริม</span>
          <span class="val" id="sumExtras">150 บาท</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-total">
          <span class="total-label">ยอดรวมทั้งหมด</span>
          <span>
            <span class="total-amount" id="sumTotal">800</span>
            <span class="total-unit">บาท</span>
          </span>
        </div>
      </div>

      <button class="cta-btn" onclick="submitBooking()">
        🔥 ยืนยันการจอง
      </button>

      <p class="cta-note">
        ชำระเงินล่วงหน้า 30% เพื่อยืนยันการจอง<br>
        สามารถยกเลิกก่อน 3 วัน คืนเงินเต็มจำนวน
      </p>

      <div class="policies">
        <div class="policy-item">เช็คอิน 14:00 น. / เช็คเอาต์ 12:00 น.</div>
        <div class="policy-item">ไม่อนุญาตนำสัตว์เลี้ยง</div>
        <div class="policy-item">รักษาความสะอาดและสิ่งแวดล้อม</div>
        <div class="policy-item">งดส่งเสียงดังหลัง 22:00 น.</div>
      </div>

    </div>
  </div>

</div>

<script>
  let selectedPrice = 650;
  let nights = 1;
  let guests = 2;
  let extrasCost = 150;

  function selectRoom(el, name, emoji, price) {
    document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedPrice = parseInt(price.replace(',',''));
    document.getElementById('previewEmoji').textContent = emoji;
    document.getElementById('previewName').textContent = name;
    document.getElementById('sumRoom').textContent = name;
    document.getElementById('sumPrice').textContent = price + ' บาท';
    updateTotal();
  }

  function calcNights() {
    const ci = document.getElementById('checkin').value;
    const co = document.getElementById('checkout').value;
    if (ci && co) {
      const diff = (new Date(co) - new Date(ci)) / 86400000;
      if (diff > 0) {
        nights = diff;
        document.getElementById('nightsNum').textContent = nights;
        document.getElementById('nightsBadge').style.display = 'flex';
        document.getElementById('sumNights').textContent = nights + ' คืน';
        const d1 = new Date(ci).toLocaleDateString('th-TH',{day:'numeric',month:'short'});
        const d2 = new Date(co).toLocaleDateString('th-TH',{day:'numeric',month:'short'});
        document.getElementById('sumCheckin').textContent = d1;
        document.getElementById('sumCheckout').textContent = d2;
        updateTotal();
      }
    }
  }

  function changeGuest(d) {
    guests = Math.max(1, Math.min(10, guests + d));
    document.getElementById('guestCount').textContent = guests;
    document.getElementById('sumGuests').textContent = guests + ' ท่าน';
    updateTotal();
  }

  function toggleService(el, cost) {
    el.classList.toggle('checked');
    const chk = el.querySelector('.service-check');
    if (el.classList.contains('checked')) {
      extrasCost += cost;
    } else {
      extrasCost -= cost;
    }
    document.getElementById('sumExtras').textContent = extrasCost + ' บาท';
    updateTotal();
  }

  function updateTotal() {
    const total = (selectedPrice * nights) + extrasCost;
    document.getElementById('sumTotal').textContent = total.toLocaleString();
  }

  function submitBooking() {
    const total = document.getElementById('sumTotal').textContent;
    alert(`✅ ส่งคำขอจองเรียบร้อย!\n\nยอดรวม: ${total} บาท\nเจ้าหน้าที่จะติดต่อกลับภายใน 24 ชั่วโมง 🌲`);
  }

  // init dates
  const today = new Date();
  const tom = new Date(today); tom.setDate(tom.getDate()+1);
  const fmt = d => d.toISOString().split('T')[0];
  document.getElementById('checkin').value = fmt(today);
  document.getElementById('checkout').value = fmt(tom);
  calcNights();
  updateTotal();
</script>
</body>
</html>