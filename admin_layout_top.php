<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ เช็คทั้ง login และเป็น admin เท่านั้น — ตรวจจาก DB ทุกครั้ง
if (empty($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
    $conn->set_charset("utf8mb4");
}
$_chk = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$_chk->bind_param("i", $_SESSION['user_id']);
$_chk->execute();
$_chkRow = $_chk->get_result()->fetch_assoc();
$_chk->close();
if (!$_chkRow || $_chkRow['role'] !== 'admin') {
    $_SESSION['user_role'] = $_chkRow['role'] ?? 'user';
    header("Location: login.php"); exit;
}
$_SESSION['user_role'] = 'admin';

if (!isset($pageTitle))  $pageTitle  = "Admin Panel";
if (!isset($activeMenu)) $activeMenu = "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= htmlspecialchars($pageTitle) ?> — MSU</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --ink:      #1a1a2e;
  --ink2:     #121220;
  --accent:   #c9a96e;
  --accent2:  #e8d5b0;
  --accent-dim: rgba(201,169,110,0.15);
  --bg:       #f5f1eb;
  --card:     #ffffff;
  --muted:    #7a7a8c;
  --border:   #e0ddd6;
  --danger:   #c0392b;
  --success:  #2e7d32;
  --text:     #1a1a2e;
  --sidebar-w: 248px;
}

html, body { height: 100%; }

body {
  font-family: 'Sarabun', sans-serif;
  background: var(--bg);
  color: var(--text);
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background-image: repeating-linear-gradient(
    90deg, rgba(201,169,110,0.03) 0px, rgba(201,169,110,0.03) 1px,
    transparent 1px, transparent 80px
  );
}

/* ══════════════ SIDEBAR ══════════════ */
.sidebar {
  width: var(--sidebar-w);
  height: 100vh;
  background: var(--ink);
  position: fixed; left: 0; top: 0;
  display: flex; flex-direction: column;
  z-index: 100;
  overflow: hidden;
}

.sidebar::before {
  content: '';
  position: absolute; width: 280px; height: 280px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,169,110,0.12) 0%, transparent 70%);
  top: -80px; right: -80px; pointer-events: none;
}
.sidebar::after {
  content: '';
  position: absolute; width: 180px; height: 180px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,169,110,0.08) 0%, transparent 70%);
  bottom: 60px; left: -60px; pointer-events: none;
}

.sidebar-brand {
  padding: 28px 24px 20px;
  position: relative; z-index: 1; flex-shrink: 0;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sidebar-brand-line { width: 28px; height: 2px; background: var(--accent); margin-bottom: 10px; }
.sidebar-brand-name {
  font-family: 'Playfair Display', serif;
  font-style: italic; font-size: 1.5rem; color: #fff;
  letter-spacing: 0.02em; line-height: 1.2;
}
.sidebar-brand-sub {
  margin-top: 4px; font-size: 0.65rem;
  color: rgba(255,255,255,0.3); letter-spacing: 0.2em; text-transform: uppercase;
}

.sidebar-scroll {
  flex: 1; overflow-y: auto; overflow-x: hidden;
  padding: 16px 12px; position: relative; z-index: 1;
}
.sidebar-scroll::-webkit-scrollbar { width: 4px; }
.sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(201,169,110,0.4); border-radius: 4px; }

.menu-section { margin-bottom: 2px; }

/* ── Accordion toggle header ── */
.menu-toggle {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 10px 6px; cursor: pointer; border-radius: 6px;
  transition: background .15s; user-select: none;
}
.menu-toggle:hover { background: rgba(255,255,255,.04); }
.menu-toggle-label {
  font-size: 0.58rem; letter-spacing: 0.18em; text-transform: uppercase;
  color: rgba(255,255,255,0.28); font-weight: 700;
}
.menu-toggle-arrow {
  font-size: 0.55rem; color: rgba(255,255,255,.22);
  transition: transform .2s ease;
}
.menu-section.open .menu-toggle-arrow { transform: rotate(90deg); }

/* ── Collapsible body ── */
.menu-body {
  overflow: hidden;
  max-height: 0;
  transition: max-height .25s ease;
}
.menu-section.open .menu-body { max-height: 600px; }

/* ── Sub-group inside section ── */
.menu-sub-label {
  font-size: 0.6rem; color: rgba(255,255,255,.18);
  padding: 6px 10px 3px 12px; display: block; letter-spacing: .08em;
}

.menu-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 8px;
  color: rgba(255,255,255,0.6); text-decoration: none;
  font-size: 0.8rem; font-weight: 500;
  transition: all 0.2s ease; margin-bottom: 1px;
  position: relative;
}
.menu-item .icon { font-size: .9rem; flex-shrink: 0; width: 20px; text-align: center; opacity: 0.8; }
.menu-item:hover  { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.9); }
.menu-item.active {
  background: var(--accent-dim);
  color: var(--accent);
  font-weight: 600;
}
.menu-item.active::before {
  content: '';
  position: absolute; left: 0; top: 50%; transform: translateY(-50%);
  width: 3px; height: 60%; background: var(--accent); border-radius: 0 2px 2px 0;
}
.menu-item.active .icon { opacity: 1; }

/* ── hide labels on small sidebar ── */
@media(max-width:900px){
  .menu-toggle-label,.menu-toggle-arrow,.menu-sub-label{ display:none; }
  .menu-body{ max-height:none!important; overflow:visible; }
}

.sidebar-footer {
  padding: 16px 12px; flex-shrink: 0; position: relative; z-index: 1;
  border-top: 1px solid rgba(255,255,255,0.06);
}

.sidebar-user {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 10px 14px;
}
.sidebar-user-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--accent-dim); border: 1.5px solid var(--accent);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; color: var(--accent); font-weight: 700; flex-shrink: 0;
}
.sidebar-user-name { font-size: 0.78rem; color: rgba(255,255,255,0.75); font-weight: 500; line-height: 1.3; }
.sidebar-user-role { font-size: 0.65rem; color: var(--accent); letter-spacing: 0.1em; text-transform: uppercase; }

.back-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 10px 14px; border-radius: 8px; text-decoration: none;
  font-size: 0.8rem; font-weight: 600;
  background: linear-gradient(135deg, var(--accent), #b8934a);
  color: var(--ink); letter-spacing: 0.05em;
  transition: all 0.2s ease; width: 100%;
}
.back-btn:hover { filter: brightness(1.08); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(201,169,110,0.3); }

/* ══════════════ MAIN ══════════════ */
.main {
  margin-left: var(--sidebar-w);
  width: calc(100% - var(--sidebar-w));
  min-height: 100vh;
  padding: 28px 28px 40px;
  position: relative; z-index: 1;
}

.topbar {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 28px;
}
.topbar-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.4rem; color: var(--ink); font-weight: 600;
}
.topbar-sub { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }
.topbar-actions { display: flex; gap: 10px; align-items: center; }

.breadcrumb {
  display: flex; align-items: center; gap: 6px;
  font-size: 0.72rem; color: var(--muted); margin-bottom: 20px;
}
.breadcrumb span { color: var(--accent); }

/* ══════════════ SHARED COMPONENTS ══════════════ */
.lm-card {
  background: var(--card); border-radius: 12px;
  box-shadow: 0 2px 12px rgba(26,26,46,0.06), 0 1px 3px rgba(26,26,46,0.04);
  overflow: hidden;
}
.lm-card-header {
  padding: 16px 20px; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
}
.lm-card-title { font-size: 0.88rem; font-weight: 700; color: var(--ink); }
.lm-card-body { padding: 20px; }

.stat-card {
  background: var(--card); border-radius: 12px;
  padding: 20px; position: relative; overflow: hidden;
  box-shadow: 0 2px 12px rgba(26,26,46,0.06);
  border-top: 3px solid var(--accent);
  transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,26,46,0.1); }
.stat-card::after {
  content: ''; position: absolute; bottom: -20px; right: -20px;
  width: 80px; height: 80px; border-radius: 50%;
  background: radial-gradient(circle, var(--accent-dim) 0%, transparent 70%);
}
.stat-label { font-size: 0.72rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
.stat-value { font-size: 2rem; font-weight: 700; color: var(--ink); line-height: 1; }
.stat-unit  { font-size: 0.78rem; color: var(--muted); margin-top: 4px; }

.lm-table { width: 100%; border-collapse: collapse; }
.lm-table thead th {
  padding: 11px 14px; font-size: 0.7rem; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--muted);
  border-bottom: 2px solid var(--border); text-align: left;
  font-weight: 600; background: #fdfcfa;
}
.lm-table tbody td {
  padding: 12px 14px; font-size: 0.85rem; color: var(--text);
  border-bottom: 1px solid var(--border); vertical-align: middle;
}
.lm-table tbody tr:last-child td { border-bottom: none; }
.lm-table tbody tr:hover { background: #fdfcfa; }

.badge {
  display: inline-block; padding: 3px 9px; border-radius: 20px;
  font-size: 0.7rem; font-weight: 600; letter-spacing: 0.04em;
}
.badge-blue   { background: #e3f2fd; color: #1565c0; }
.badge-orange { background: #fff3e0; color: #e65100; }
.badge-green  { background: #e8f5e9; color: #2e7d32; }
.badge-gold   { background: rgba(201,169,110,0.15); color: #a07c3a; }

.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 6px; border: none;
  font-family: 'Sarabun', sans-serif; font-size: 0.82rem;
  font-weight: 600; cursor: pointer; text-decoration: none;
  transition: all 0.2s ease; letter-spacing: 0.04em;
}
.btn-primary { background: var(--ink); color: #fff; }
.btn-primary:hover { background: #2a2a4a; transform: translateY(-1px); }
.btn-accent  { background: var(--accent); color: var(--ink); }
.btn-accent:hover { filter: brightness(1.06); transform: translateY(-1px); }
.btn-danger  { background: #fff; color: var(--danger); border: 1.5px solid #fecaca; }
.btn-danger:hover { background: #fef2f2; }
.btn-ghost   { background: transparent; color: var(--muted); border: 1.5px solid var(--border); }
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm { padding: 6px 12px; font-size: 0.78rem; }

.search-wrap { position: relative; }
.search-wrap input {
  padding: 8px 12px 8px 36px; border: 1.5px solid var(--border);
  border-radius: 6px; font-family: 'Sarabun', sans-serif; font-size: 0.85rem;
  color: var(--text); background: #fafaf8; outline: none; width: 240px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.search-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(201,169,110,0.12); }
.search-wrap::before {
  content: '🔍'; position: absolute; left: 10px; top: 50%;
  transform: translateY(-50%); font-size: 0.75rem; pointer-events: none;
}

.alert { padding: 12px 16px; border-radius: 8px; font-size: 0.82rem; margin-bottom: 20px; }
.alert-danger  { background: #fdf0ef; border: 1px solid #fecaca; color: var(--danger); }
.alert-success { background: #edf7ed; border: 1px solid #c8e6c9; color: var(--success); }

/* ══════════════ RESPONSIVE ══════════════ */
@media (max-width: 900px) {
  :root { --sidebar-w: 64px; }
  .sidebar-brand-name, .sidebar-brand-sub, .menu-item .text,
  .sidebar-brand-line, .menu-section-label, .sidebar-user-name,
  .sidebar-user-role, .back-btn span { display: none; }
  .menu-item { justify-content: center; padding: 12px; }
  .menu-item.active::before { display: none; }
  .back-btn { padding: 10px; }
  .sidebar-user { justify-content: center; padding: 10px; }
}

@media (max-width: 600px) {
  :root { --sidebar-w: 0px; }
  .sidebar { display: none; }
  .main { margin-left: 0; width: 100%; padding: 16px; }
}
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-line"></div>
    <div class="sidebar-brand-name">WRBRI</div>
    <div class="sidebar-brand-sub">สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม</div>
  </div>

  <div class="sidebar-scroll">

<?php
// กำหนด section ที่ active menu อยู่ใน section ไหน
$_sectionMap = [
  'overview'  => ['dashboard','report','admin_report','charts','walkin_archive'],
  'booking'   => ['booking','booking_approved','booking_archive','rooms','tent_booking','tent_approved','tent_manage','tent_stock','boat_booking','boat_queue','boat_approve','boat_archive'],
  'content'   => ['news_add','news_manage','popup','banner','about','survey'],
  'settings'  => ['users','password','profile'],
];
$_activeSection = '';
foreach ($_sectionMap as $_sec => $_items) {
  if (in_array($activeMenu, $_items)) { $_activeSection = $_sec; break; }
}
?>

<!-- ภาพรวม -->
<div class="menu-section <?= $_activeSection==='overview'?'open':'' ?>" data-sec="overview">
  <div class="menu-toggle" onclick="toggleMenu(this)">
    <span class="menu-toggle-label">ภาพรวม</span>
    <span class="menu-toggle-arrow">▶</span>
  </div>
  <div class="menu-body">
    <a href="admin_dashboard.php" class="menu-item <?= $activeMenu==='dashboard'?'active':'' ?>">
      <span class="icon">📊</span><span class="text">Dashboard</span>
    </a>
    <a href="daily_report.php" class="menu-item <?= $activeMenu==='report'?'active':'' ?>">
      <span class="icon">📈</span><span class="text">รายงานเช็คอิน</span>
    </a>
    <a href="booking_report.php" class="menu-item <?= $activeMenu==='admin_report'?'active':'' ?>">
      <span class="icon">📊</span><span class="text">กราฟรายงาน</span>
    </a>
    <a href="admin_checkin_archive.php" class="menu-item <?= $activeMenu==='walkin_archive'?'active':'' ?>">
      <span class="icon">📦</span><span class="text">จัดเก็บ Check-in</span>
    </a>
  </div>
</div>

<!-- จัดการการจอง -->
<div class="menu-section <?= $_activeSection==='booking'?'open':'' ?>" data-sec="booking">
  <div class="menu-toggle" onclick="toggleMenu(this)">
    <span class="menu-toggle-label">การจอง</span>
    <span class="menu-toggle-arrow">▶</span>
  </div>
  <div class="menu-body">
    <span class="menu-sub-label">🏨 ห้องพัก</span>
    <a href="admin_booking_list.php" class="menu-item <?= $activeMenu==='booking'?'active':'' ?>">
      <span class="icon">🏨</span><span class="text">ข้อมูลการเข้าพัก</span>
    </a>
    <a href="admin_booking_approved.php" class="menu-item <?= $activeMenu==='booking_approved'?'active':'' ?>">
      <span class="icon">✅</span><span class="text">อนุมัติแล้ว</span>
    </a>
    <a href="admin_booking_archive.php" class="menu-item <?= $activeMenu==='booking_archive'?'active':'' ?>">
      <span class="icon">🗂️</span><span class="text">ย้อนหลัง</span>
    </a>
    <a href="manage_rooms.php" class="menu-item <?= $activeMenu==='rooms'?'active':'' ?>">
      <span class="icon">🛏️</span><span class="text">จัดการห้องพัก</span>
    </a>
    <span class="menu-sub-label">⛺ เต็นท์</span>
    <a href="admin_tent_list.php" class="menu-item <?= $activeMenu==='tent_booking'?'active':'' ?>">
      <span class="icon">⛺</span><span class="text">การจองเต็นท์</span>
    </a>
    <a href="admin_tent_approved.php" class="menu-item <?= $activeMenu==='tent_approved'?'active':'' ?>">
      <span class="icon">✅</span><span class="text">อนุมัติแล้ว</span>
    </a>
    <a href="manage_tents.php" class="menu-item <?= $activeMenu==='tent_manage'?'active':'' ?>">
      <span class="icon">🏕️</span><span class="text">จัดการเต็นท์</span>
    </a>
    <a href="manage_tent_stock.php" class="menu-item <?= $activeMenu==='tent_stock'?'active':'' ?>">
      <span class="icon">📦</span><span class="text">จัดเก็บเต็นท์</span>
    </a>
    <span class="menu-sub-label">🚣 พายเรือ</span>
    <a href="admin_boat_bookings.php" class="menu-item <?= $activeMenu==='boat_booking'?'active':'' ?>">
      <span class="icon">🚣</span><span class="text">การจองพายเรือ</span>
    </a>
    <a href="admin_boat_approved.php" class="menu-item <?= $activeMenu==='boat_approve'?'active':'' ?>">
      <span class="icon">✅</span><span class="text">อนุมัติการจอง</span>
    </a>
    <a href="admin_boat_archive_view.php" class="menu-item <?= $activeMenu==='boat_archive'?'active':'' ?>">
      <span class="icon">📦</span><span class="text">จัดเก็บข้อมูลคิว</span>
    </a>
    <a href="admin_boat_queues.php" class="menu-item <?= $activeMenu==='boat_queue'?'active':'' ?>">
      <span class="icon">🛶</span><span class="text">จัดการคิวพายเรือ</span>
    </a>
  </div>
</div>

<!-- คอนเทนต์ + ประเมินผล -->
<div class="menu-section <?= $_activeSection==='content'?'open':'' ?>" data-sec="content">
  <div class="menu-toggle" onclick="toggleMenu(this)">
    <span class="menu-toggle-label">คอนเทนต์</span>
    <span class="menu-toggle-arrow">▶</span>
  </div>
  <div class="menu-body">
    <a href="admin_add_news.php" class="menu-item <?= $activeMenu==='news_add'?'active':'' ?>">
      <span class="icon">📰</span><span class="text">เพิ่มข่าวสาร</span>
    </a>
    <a href="manage_news.php" class="menu-item <?= $activeMenu==='news_manage'?'active':'' ?>">
      <span class="icon">✏️</span><span class="text">จัดการข่าว</span>
    </a>

    <a href="change_banner.php" class="menu-item <?= $activeMenu==='banner'?'active':'' ?>">
      <span class="icon">🖼️</span><span class="text">เปลี่ยนแบนเนอร์</span>
    </a>
    <a href="edit_about.php" class="menu-item <?= $activeMenu==='about'?'active':'' ?>">
      <span class="icon">📝</span><span class="text">เกี่ยวกับเรา</span>
    </a>
    <a href="admin_survey.php" class="menu-item <?= $activeMenu==='survey'?'active':'' ?>">
      <span class="icon">📊</span><span class="text">ผลการประเมิน</span>
    </a>
  </div>
</div>

<!-- ตั้งค่า -->
<div class="menu-section <?= $_activeSection==='settings'?'open':'' ?>" data-sec="settings">
  <div class="menu-toggle" onclick="toggleMenu(this)">
    <span class="menu-toggle-label">ผู้ใช้ / ตั้งค่า</span>
    <span class="menu-toggle-arrow">▶</span>
  </div>
  <div class="menu-body">
    <a href="manage_users.php" class="menu-item <?= $activeMenu==='users'?'active':'' ?>">
      <span class="icon">👥</span><span class="text">จัดการผู้ใช้งาน</span>
    </a>
    <a href="change_password.php" class="menu-item <?= $activeMenu==='password'?'active':'' ?>">
      <span class="icon">🔐</span><span class="text">เปลี่ยนรหัสผ่าน</span>
    </a>
    <a href="admin_profile.php" class="menu-item <?= $activeMenu==='profile'?'active':'' ?>">
      <span class="icon">👤</span><span class="text">โปรไฟล์แอดมิน</span>
    </a>
  </div>
</div>

  </div><!-- end sidebar-scroll -->

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
        <div class="sidebar-user-role">Administrator</div>
      </div>
    </div>
    <a href="index.php" class="back-btn">
      <span>←</span> <span>กลับหน้าเว็บไซต์</span>
    </a>
  </div>
</div>

<script>
function toggleMenu(btn) {
  const section = btn.closest('.menu-section');
  const isOpen = section.classList.contains('open');
  section.classList.toggle('open', !isOpen);
  // save state
  try {
    const sec = section.dataset.sec;
    const saved = JSON.parse(localStorage.getItem('menuState') || '{}');
    saved[sec] = !isOpen;
    localStorage.setItem('menuState', JSON.stringify(saved));
  } catch(e) {}
}
// restore state for sections not forced open by PHP
document.addEventListener('DOMContentLoaded', () => {
  try {
    const saved = JSON.parse(localStorage.getItem('menuState') || '{}');
    document.querySelectorAll('.menu-section').forEach(sec => {
      const key = sec.dataset.sec;
      // PHP already opens the active section; only restore others
      if (!sec.classList.contains('open') && saved[key] === true) {
        sec.classList.add('open');
      }
    });
  } catch(e) {}
});
</script>

<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
      <div class="topbar-sub"><?= date('l, d F Y') ?></div>
    </div>
    <div class="topbar-actions">
      <a href="logout.php" class="btn btn-ghost btn-sm">ออกจากระบบ</a>
    </div>
  </div>