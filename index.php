<?php
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ===============================
   HERO IMAGE (แบนเนอร์หน้าเว็บ)
================================ */
$heroImage = "uploads/newbanner.jpg";
$resHero = $conn->query("SELECT image_path FROM site_banners ORDER BY id DESC LIMIT 1");
if ($resHero && $resHero->num_rows > 0) {
    $p = $resHero->fetch_assoc()['image_path'];
    if (!empty($p)) {
        $heroImage = (strpos($p, 'uploads/') === false) ? 'uploads/' . $p : $p;
    }
}
$heroImage .= '?v=' . time();

// ── User session ──
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
<title>สถาบันวิจัยวลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>

<style>
*{ box-sizing:border-box; margin:0; padding:0; }

body{
    font-family:'Sarabun','Segoe UI',Tahoma,sans-serif;
    background:#ffffff;
    color:#222;
}

/* ================= NAVBAR ================= */
.navbar{
    width:100%;
    background:#ffffff;
    padding:18px 60px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    position:sticky;
    top:0;
    z-index:100;
    box-shadow:0 1px 0 rgba(0,0,0,0.06);
}

.nav-left{
    display:flex;
    align-items:center;
    gap:20px;
}

.nav-left img{ height:120px; }

.site-text h1{ font-size:26px; font-weight:700; }
.site-text span{ font-size:16px; }

.nav-center{
    display:flex;
    gap:32px;
    align-items:center;
    flex-wrap:wrap;
    justify-content:flex-end;
}

.nav-center a{
    text-decoration:none;
    color:#333;
    font-size:15px;
    font-weight:500;
    position:relative;
    transition:.3s;
}
.nav-center a::after{
    content:"";
    position:absolute;
    left:0; bottom:-6px;
    width:0%; height:2px;
    background:#000;
    transition:.3s;
}
.nav-center a:hover{ color:#000; }
.nav-center a:hover::after{ width:100%; }

/* ── User menu ── */
.nav-divider{
    width:1px;
    height:20px;
    background:#e0ddd6;
    flex-shrink:0;
}

/* Login button */
.btn-login{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:9px 20px;
    background:#1a1a2e;
    color:#fff;
    text-decoration:none;
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    letter-spacing:.04em;
    transition:all .25s ease;
    white-space:nowrap;
}
.btn-login:hover{
    background:#2a2a4a;
    transform:translateY(-1px);
    box-shadow:0 6px 16px rgba(26,26,46,.2);
}

/* User dropdown */
.user-menu{
    position:relative;
}
.user-trigger{
    display:flex;
    align-items:center;
    gap:10px;
    padding:6px 14px 6px 6px;
    border-radius:999px;
    border:1.5px solid #e0ddd6;
    background:#fff;
    cursor:pointer;
    transition:all .2s;
    user-select:none;
}
.user-trigger:hover{
    border-color:#c9a96e;
    box-shadow:0 2px 10px rgba(201,169,110,0.15);
}
.user-avatar{
    width:34px; height:34px;
    border-radius:50%;
    background:#1a1a2e;
    color:#c9a96e;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:0.85rem;
    font-weight:700;
    font-family:'Playfair Display',serif;
    flex-shrink:0;
    overflow:hidden;
}
.user-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
}
.user-name{
    font-size:14px;
    font-weight:600;
    color:#1a1a2e;
    max-width:120px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.user-chevron{
    font-size:0.7rem;
    color:#7a7a8c;
    transition:transform .2s;
}
.user-menu.open .user-chevron{ transform:rotate(180deg); }

/* Dropdown */
.user-dropdown{
    position:absolute;
    top:calc(100% + 10px);
    right:0;
    background:#fff;
    border-radius:14px;
    box-shadow:0 16px 40px rgba(26,26,46,.14), 0 2px 8px rgba(26,26,46,.06);
    border:1px solid #e8e4de;
    min-width:220px;
    padding:8px;
    opacity:0;
    visibility:hidden;
    transform:translateY(-8px);
    transition:all .2s ease;
    z-index:200;
}
.user-menu.open .user-dropdown{
    opacity:1;
    visibility:visible;
    transform:translateY(0);
}

.dropdown-header{
    padding:12px 14px 10px;
    border-bottom:1px solid #e8e4de;
    margin-bottom:6px;
}
.dropdown-name{
    font-size:0.88rem;
    font-weight:700;
    color:#1a1a2e;
}
.dropdown-email{
    font-size:0.74rem;
    color:#7a7a8c;
    margin-top:2px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.dropdown-role{
    display:inline-flex;
    align-items:center;
    gap:4px;
    margin-top:6px;
    padding:2px 8px;
    border-radius:20px;
    font-size:0.66rem;
    font-weight:700;
    letter-spacing:.06em;
}
.role-admin{ background:rgba(201,169,110,0.15); color:#a07c3a; }
.role-user { background:#f0f0f0; color:#7a7a8c; }

.dropdown-item{
    display:flex;
    align-items:center;
    gap:10px;
    padding:9px 14px;
    border-radius:9px;
    font-size:0.84rem;
    font-weight:500;
    color:#1a1a2e;
    text-decoration:none;
    transition:background .15s;
    cursor:pointer;
}
.dropdown-item:hover{ background:#f5f1eb; }
.dropdown-item.danger{ color:#dc2626; }
.dropdown-item.danger:hover{ background:#fef2f2; }
.dropdown-icon{
    width:28px; height:28px;
    border-radius:8px;
    background:#f5f1eb;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:0.85rem;
    flex-shrink:0;
}
.dropdown-item.danger .dropdown-icon{ background:#fef2f2; }
.dropdown-divider{
    border:none;
    border-top:1px solid #e8e4de;
    margin:6px 0;
}

/* ================= HERO ================= */
.hero{
    width:90%;
    max-width:1200px;
    height:420px;
    margin:40px auto;
    border-radius:18px;
    overflow:hidden;
    background:url('<?php echo htmlspecialchars($heroImage); ?>') center/cover no-repeat;
    position:relative;
    box-shadow:0 10px 30px rgba(0,0,0,0.15);
}
.hero::after{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.18);
}

/* ================= ABOUT SECTION ================= */
.about-section-home{
    padding:80px 20px;
    background:#ffffff;
}
.about-home-container{
    width:90%;
    max-width:1000px;
    margin:0 auto;
}
.section-title{
    text-align:center;
    margin-bottom:35px;
}
.section-title h2{
    font-size:40px;
    font-weight:700;
    color:#111;
    display:inline-block;
    position:relative;
    padding-bottom:18px;
}
.section-title h2::after{
    content:"";
    position:absolute;
    left:50%; bottom:0;
    transform:translateX(-50%);
    width:70px; height:4px;
    background:#b88a44;
    border-radius:999px;
}
.info-card{
    background:#fff;
    border:1px solid #ececec;
    border-radius:18px;
    padding:30px 28px;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
    transition:0.3s ease;
    text-align:center;
}
.info-card:hover{
    transform:translateY(-4px);
    box-shadow:0 16px 30px rgba(0,0,0,0.08);
}
.section-image{
    width:100%;
    max-width:620px;
    height:400px;
    object-fit:cover;
    display:block;
    margin:18px auto 20px;
    border-radius:14px;
    box-shadow:0 8px 18px rgba(0,0,0,0.10);
}
.info-card h3{ font-size:24px; font-weight:700; color:#1f1f1f; margin-bottom:14px; }
.info-card p{ font-size:17px; line-height:1.9; color:#5a5a5a; }
.about-main-card{ max-width:900px; margin:0 auto 30px; }
.about-home-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:28px;
    max-width:900px;
    margin:0 auto;
}

/* ================= FOOTER ================= */
.site-footer{
    background:#ffffff;
    color:#000000;
    margin-top:20px;
}
.footer-container{
    max-width:1000px;
    margin:0 auto;
    padding:60px 40px 40px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:120px;
    align-items:start;
}
.footer-column h2{
    font-size:34px;
    font-weight:700;
    margin-bottom:18px;
    position:relative;
    line-height:1.2;
    text-align:left;
}
.footer-column h2::after{
    content:"";
    display:block;
    width:100%;
    max-width:230px;
    height:1px;
    background:rgba(0,0,0,0.12);
    margin-top:12px;
}
.footer-column p{ font-size:18px; line-height:1.9; margin-top:18px; text-align:left; }
.footer-column ul{ list-style:none; padding:0; margin-top:18px; text-align:left; }
.footer-column ul li{ margin-bottom:14px; font-size:18px; }

/* ================= RESPONSIVE ================= */
@media (max-width:992px){
    .navbar{ padding:18px 20px; flex-direction:column; gap:20px; }
    .nav-left{ flex-direction:column; text-align:center; }
    .nav-left img{ height:90px; }
    .nav-center{ justify-content:center; gap:20px; }
    .hero{ height:260px; width:94%; }
    .about-section-home{ padding:60px 16px; }
    .section-title h2{ font-size:30px; }
    .info-card{ padding:22px 18px; }
    .info-card h3{ font-size:21px; }
    .info-card p{ font-size:16px; }
    .section-image{ max-width:240px; height:160px; }
    .footer-container{ grid-template-columns:1fr; gap:35px; }
    .footer-column h2{ font-size:28px; }
    .footer-column p, .footer-column ul li{ font-size:16px; }
    .user-name{ display:none; }
}
</style>
</head>
<body>

<div class="navbar">
  <div class="nav-left">
    <img src="Logo.png" alt="WRBRI Logo">
    <div class="site-text">
      <h1>สถาบันวิจัยวลัยรุกขเวช</h1>
      <span>มหาวิทยาลัยมหาสารคาม</span>
    </div>
  </div>

  <div class="nav-center">
    <a href="news.php">ข่าวสาร</a>
    <a href="view_data.php">ลงทะเบียน</a>
    <a href="https://docs.google.com/forms/d/e/1FAIpQLSdukofM-5EFzR1Zddip7uZJ-pnBmLnhXCNyABKIEY8cwUxzyQ/viewform?usp=dialog" target="_blank">แบบประเมิน</a>
    <a href="calendar.php">ปฏิทิน</a>
    <a href="about_us.php">เกี่ยวกับ</a>
    <a href="booking_room.php">จองห้องพัก</a>

    <div class="nav-divider"></div>

    <?php if ($isLoggedIn): ?>
      <!-- ── User dropdown ── -->
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
          <div class="dropdown-header">
            <div class="dropdown-name"><?= htmlspecialchars($userName) ?></div>
            <div class="dropdown-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
            <span class="dropdown-role <?= $isAdmin?'role-admin':'role-user' ?>">
              <?= $isAdmin ? '⚡ Administrator' : '👤 Member' ?>
            </span>
          </div>

          <a href="profile.php" class="dropdown-item">
            <div class="dropdown-icon">👤</div>
            โปรไฟล์ของฉัน
          </a>

          <a href="booking_room.php" class="dropdown-item">
            <div class="dropdown-icon">🏨</div>
            จองห้องพัก
          </a>

          <?php if ($isAdmin): ?>
            <hr class="dropdown-divider">
            <a href="admin_dashboard.php" class="dropdown-item">
              <div class="dropdown-icon">📊</div>
              Admin Dashboard
            </a>
          <?php endif; ?>

          <hr class="dropdown-divider">
          <a href="logout.php" class="dropdown-item danger">
            <div class="dropdown-icon">🚪</div>
            ออกจากระบบ
          </a>
        </div>
      </div>

    <?php else: ?>
      <!-- ── Login button ── -->
      <a href="login.php" class="btn-login">
        <span>🔑</span> เข้าสู่ระบบ
      </a>
    <?php endif; ?>
  </div>
</div>

<section class="hero"></section>

<section class="about-section-home">
  <div class="about-home-container">

    <div class="section-title">
      <h2>เกี่ยวกับสถาบันวิจัยวลัยรุกขเวช</h2>
    </div>

    <div class="info-card about-main-card">
      <img src="ka0.jpg" class="section-image" alt="กิจกรรม">
      <p>
        สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม เป็นหน่วยงานที่มุ่งเน้น
        การส่งเสริมการเรียนรู้ การวิจัย และการบริการวิชาการแก่ชุมชน
        โดยเฉพาะในด้านทรัพยากรธรรมชาติ ความหลากหลายทางชีวภาพ
        และภูมิปัญญาท้องถิ่น เพื่อสร้างองค์ความรู้ที่เป็นประโยชน์ต่อสังคม
        และการพัฒนาท้องถิ่นอย่างยั่งยืน
      </p>
    </div>

    <div class="about-home-grid">
      <div class="info-card">
        <h3>งานประชาสัมพันธ์และกิจกรรม</h3>
        <img src="ka1.jpg" class="section-image" alt="กิจกรรม">
        <p>
          เผยแพร่ข่าวสาร กิจกรรม การอบรม สัมมนา และโครงการต่าง ๆ
          ของสถาบัน เพื่อให้บุคลากร นักศึกษา และประชาชนทั่วไป
          เข้าถึงข้อมูลที่สำคัญได้อย่างสะดวก
        </p>
      </div>

      <div class="info-card">
        <h3>งานวิจัยและองค์ความรู้</h3>
        <img src="ka2.jpg" class="section-image" alt="กิจกรรม">
        <p>
          สนับสนุนและเผยแพร่งานวิจัยด้านธรรมชาติ พืชสมุนไพร
          สิ่งแวดล้อม และความหลากหลายทางชีวภาพ
          เพื่อสร้างคุณค่าเชิงวิชาการและประโยชน์ต่อชุมชน
        </p>
      </div>

      <div class="info-card">
        <h3>การบริการวิชาการแก่สังคม</h3>
        <img src="ka3.jpg" class="section-image" alt="กิจกรรม">
        <p>
          นำองค์ความรู้จากงานวิจัยไปต่อยอดสู่การบริการวิชาการ
          และกิจกรรมที่เชื่อมโยงกับชุมชน โรงเรียน หน่วยงานภาครัฐ
          และประชาชนในพื้นที่
        </p>
      </div>

      <div class="info-card">
        <h3>การสร้างภาพลักษณ์องค์กร</h3>
        <img src="ka4.jpg" class="section-image" alt="กิจกรรม">
        <p>
          นำเสนอผลงาน ความร่วมมือ และกิจกรรมสำคัญของสถาบัน
          เพื่อสะท้อนบทบาทขององค์กรในฐานะแหล่งเรียนรู้
          และศูนย์กลางด้านการวิจัยของมหาวิทยาลัยมหาสารคาม
        </p>
      </div>
    </div>
  </div>
</section>

<footer class="site-footer">
  <div class="footer-container">
    <div class="footer-column">
      <h2>เกี่ยวกับเรา</h2>
      <p>
        สถาบันวิจัยดาราศาสตร์และเทคโนโลยี มุ่งเน้นการส่งเสริมการเรียนรู้
        การวิจัย และการจัดกิจกรรมเพื่อพัฒนาศักยภาพด้านวิทยาศาสตร์
        เทคโนโลยี และการบริการวิชาการแก่ชุมชนและสังคม
      </p>
    </div>
    <div class="footer-column">
      <h2>ติดต่อเรา</h2>
      <ul class="footer-contact">
        <li>อีเมล: walairukhavej@msu.ac.th</li>
        <li>โทร: 043 719 816</li>
        <li>Facebook: สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม</li>
        <li>ที่อยู่: Kantharawichai, Thailand, 44150</li>
      </ul>
    </div>
  </div>
</footer>

<script>
function toggleMenu() {
  document.getElementById('userMenu').classList.toggle('open');
}

// ปิด dropdown เมื่อคลิกข้างนอก
document.addEventListener('click', function(e) {
  const menu = document.getElementById('userMenu');
  if (menu && !menu.contains(e.target)) {
    menu.classList.remove('open');
  }
});
</script>

</body>
</html>
<?php $conn->close(); ?>