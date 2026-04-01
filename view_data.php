<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$currentDate = date('Y-m-d');
$currentTime = date('H:i');

/* ── ดึงข้อมูลโปรไฟล์ถ้า login แล้ว ── */
$profileName   = '';
$profileEmail  = '';
$profilePhone  = '';
$profileGender = '';
$profileAge    = '';
$isLoggedIn    = !empty($_SESSION['user_id']);

if ($isLoggedIn) {
    $uid   = (int)$_SESSION['user_id'];
    $pStmt = $conn->prepare("SELECT fullname, email, phone, gender, birth_date FROM users WHERE id = ? LIMIT 1");
    $pStmt->bind_param("i", $uid);
    $pStmt->execute();
    $pRow = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();
    if ($pRow) {
        $profileName   = $pRow['fullname']   ?? ($_SESSION['user_name'] ?? '');
        $profileEmail  = $pRow['email']      ?? ($_SESSION['user_email'] ?? '');
        $profilePhone  = $pRow['phone']      ?? '';
        $profileGender = $pRow['gender']     ?? '';
        if (!empty($pRow['birth_date'])) {
            $bd = new DateTime($pRow['birth_date']);
            $profileAge = (int)(new DateTime())->diff($bd)->y;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ลงทะเบียนกิจกรรม — WRBRI</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Kanit:wght@400;600;700&display=swap" rel="stylesheet"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --ink:    #1a1a2e;
  --gold:   #c9a96e;
  --gold2:  #e8d5b0;
  --bg:     #f0ede8;
  --card:   #ffffff;
  --muted:  #7a7a8c;
  --border: #e0ddd6;
  --green:  #2d6a4f;
  --green2: #40916c;
  --green3: #74c69d;
  --accent: #638411;
}

html { scroll-behavior: smooth; }
body {
  font-family: 'Sarabun', sans-serif;
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
}

/* ── Hero ── */
.hero {
  position: relative;
  min-height: 480px;
  background: url('uploads/88.png') center/cover no-repeat;
  display: flex;
  align-items: center;
  overflow: hidden;
}
.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    rgba(26,26,46,0.82) 0%,
    rgba(45,106,79,0.55) 60%,
    rgba(26,26,46,0.75) 100%
  );
}
/* Grid lines */
.hero::before {
  content: '';
  position: absolute; inset: 0; z-index: 1; pointer-events: none;
  background-image:
    repeating-linear-gradient(90deg, rgba(201,169,110,0.06) 0px, rgba(201,169,110,0.06) 1px, transparent 1px, transparent 80px),
    repeating-linear-gradient(0deg,  rgba(201,169,110,0.04) 0px, rgba(201,169,110,0.04) 1px, transparent 1px, transparent 80px);
}
/* Gold accent bar at bottom */
.hero::after {
  content: '';
  position: absolute; bottom: 0; left: 0; right: 0; height: 3px; z-index: 2;
  background: linear-gradient(90deg, transparent, var(--gold), var(--gold2), var(--gold), transparent);
}

.hero-content {
  position: relative; z-index: 2;
  width: 100%; max-width: 1160px;
  margin: 0 auto;
  padding: 60px 40px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(201,169,110,0.18);
  border: 1px solid rgba(201,169,110,0.45);
  color: var(--gold2);
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  backdrop-filter: blur(8px);
  margin-bottom: 20px;
}
.hero-badge .dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--gold);
  animation: pulse 2s ease-in-out infinite;
}

.hero h1 {
  font-family: 'Kanit', sans-serif;
  font-weight: 700;
  font-size: clamp(2rem, 5vw, 3.4rem);
  color: #fff;
  line-height: 1.15;
  margin-bottom: 14px;
}
.hero h1 span { color: var(--gold2); }

.hero-sub {
  font-size: 1rem;
  color: rgba(255,255,255,0.6);
  line-height: 1.8;
  max-width: 500px;
  margin-bottom: 32px;
}

.hero-actions {
  display: flex; gap: 12px; flex-wrap: wrap;
}
.back-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 22px;
  background: rgba(255,255,255,0.1);
  color: rgba(255,255,255,0.85);
  text-decoration: none;
  border: 1.5px solid rgba(255,255,255,0.25);
  border-radius: 999px;
  font-size: 0.82rem; font-weight: 600;
  backdrop-filter: blur(6px);
  transition: all 0.25s;
}
.back-btn:hover { background: rgba(255,255,255,0.2); color: #fff; transform: translateY(-1px); }

/* ── Stats strip ── */
.stats-strip {
  background: var(--ink);
  padding: 0;
}
.stats-inner {
  max-width: 1160px; margin: 0 auto;
  display: flex;
  padding: 0 40px;
}
.stat-item {
  flex: 1;
  padding: 20px 24px;
  display: flex; align-items: center; gap: 14px;
  border-right: 1px solid rgba(255,255,255,0.07);
}
.stat-item:last-child { border-right: none; }
.stat-icon { font-size: 1.5rem; line-height: 1; }
.stat-label { font-size: 0.72rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 2px; }
.stat-value { font-size: 0.92rem; font-weight: 600; color: var(--gold2); }

/* ── Main section ── */
.main-wrap {
  max-width: 1160px;
  margin: 48px auto 80px;
  padding: 0 40px;
}

.main-card {
  background: var(--card);
  border-radius: 28px;
  box-shadow:
    0 32px 80px rgba(26,26,46,0.13),
    0 4px 16px rgba(26,26,46,0.07);
  display: grid;
  grid-template-columns: 380px 1fr;
  overflow: hidden;
}

/* ── Left panel ── */
.panel-left {
  background: linear-gradient(155deg, #1a1a2e 0%, #162032 40%, #1d3a2a 100%);
  padding: 52px 44px;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
  min-height: 600px;
}
/* Gold orbs */
.panel-left::before {
  content: '';
  position: absolute;
  width: 340px; height: 340px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,169,110,0.12) 0%, transparent 65%);
  top: -100px; right: -120px;
}
.panel-left::after {
  content: '';
  position: absolute;
  width: 220px; height: 220px; border-radius: 50%;
  background: radial-gradient(circle, rgba(45,106,79,0.25) 0%, transparent 65%);
  bottom: 60px; left: -60px;
}

.pl-brand { position: relative; z-index: 1; margin-bottom: 40px; }
.pl-logo-ring {
  width: 52px; height: 52px;
  border-radius: 14px;
  border: 2px solid rgba(201,169,110,0.5);
  background: rgba(201,169,110,0.1);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.6rem;
  margin-bottom: 18px;
}
.pl-name {
  font-family: 'Kanit', sans-serif;
  font-weight: 700;
  font-size: 1.5rem;
  color: #fff;
  margin-bottom: 4px;
  letter-spacing: 0.04em;
}
.pl-name span { color: var(--gold); }
.pl-sub {
  font-size: 0.7rem;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.32);
}

.pl-divider {
  width: 100%; height: 1px;
  background: linear-gradient(90deg, rgba(201,169,110,0.4), transparent);
  margin: 0 0 36px;
  position: relative; z-index: 1;
}

.pl-info { position: relative; z-index: 1; flex: 1; }
.pl-title {
  font-family: 'Kanit', sans-serif;
  font-size: 1.35rem;
  font-weight: 600;
  color: #fff;
  line-height: 1.45;
  margin-bottom: 14px;
}
.pl-desc {
  font-size: 0.84rem;
  color: rgba(255,255,255,0.48);
  line-height: 1.85;
}

.pl-steps {
  position: relative; z-index: 1;
  margin-top: 44px;
  display: flex; flex-direction: column; gap: 0;
}
.pl-step {
  display: flex; align-items: flex-start; gap: 16px;
  padding-bottom: 24px;
  position: relative;
}
.pl-step:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 14px; top: 30px;
  width: 1px; height: calc(100% - 8px);
  background: rgba(201,169,110,0.2);
}
.pl-step-num {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  background: rgba(201,169,110,0.15);
  border: 1.5px solid rgba(201,169,110,0.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.72rem; font-weight: 700; color: var(--gold);
  margin-top: 1px;
}
.pl-step-body {}
.pl-step-label {
  font-size: 0.82rem; font-weight: 600;
  color: rgba(255,255,255,0.8);
  margin-bottom: 2px;
}
.pl-step-desc {
  font-size: 0.74rem;
  color: rgba(255,255,255,0.38);
  line-height: 1.5;
}

/* Nature tag at bottom */
.pl-nature-tag {
  position: relative; z-index: 1;
  margin-top: 32px;
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(45,106,79,0.3);
  border: 1px solid rgba(116,198,157,0.3);
  border-radius: 999px;
  padding: 7px 16px;
  font-size: 0.74rem; color: var(--green3);
  font-weight: 600;
}

/* ── Right panel (form) ── */
.panel-right {
  padding: 52px 56px;
  background: #fff;
}

.form-header { margin-bottom: 36px; }
.form-step-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(201,169,110,0.1);
  border: 1px solid rgba(201,169,110,0.3);
  border-radius: 999px; padding: 4px 14px;
  font-size: 0.68rem; font-weight: 700;
  color: #a07840; letter-spacing: 0.12em;
  text-transform: uppercase; margin-bottom: 12px;
}
.form-title {
  font-family: 'Kanit', sans-serif;
  font-size: 1.8rem; font-weight: 700;
  color: var(--ink); margin-bottom: 6px;
}
.form-subtitle { font-size: 0.88rem; color: var(--muted); line-height: 1.6; }

/* Section labels */
.section-label {
  font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
  text-transform: uppercase; color: var(--muted);
  margin-bottom: 14px; margin-top: 8px;
  display: flex; align-items: center; gap: 8px;
}
.section-label::after {
  content: ''; flex: 1; height: 1px;
  background: var(--border);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 24px;
}
.form-group { margin-bottom: 22px; }
.form-group.full { grid-column: 1 / -1; }

label {
  display: block;
  font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--muted);
  margin-bottom: 8px;
}

.input-wrap { position: relative; }
.input-icon {
  position: absolute; left: 14px; top: 50%;
  transform: translateY(-50%);
  font-size: 0.9rem; color: var(--muted);
  pointer-events: none;
}

input[type="text"],
input[type="number"],
input[type="date"],
input[type="time"],
select {
  width: 100%; height: 50px;
  padding: 0 14px 0 42px;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  font-family: 'Sarabun', sans-serif;
  font-size: 0.95rem; color: var(--ink);
  background: #fafaf8;
  outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
  appearance: none; -webkit-appearance: none;
}
input:focus, select:focus {
  border-color: var(--gold);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(201,169,110,0.14);
}
input.prefilled {
  background: #f0fdf4;
  border-color: #86efac;
}

/* Custom select arrow */
.select-wrap::after {
  content: '▾';
  position: absolute; right: 14px; top: 50%;
  transform: translateY(-50%);
  font-size: 0.8rem; color: var(--muted);
  pointer-events: none;
}

/* ── Gender pills ── */
.gender-pills {
  display: flex; gap: 10px;
}
.gender-pill {
  flex: 1;
  position: relative;
  cursor: pointer;
}
.gender-pill input[type="radio"] {
  position: absolute; opacity: 0; width: 0; height: 0;
}
.gender-pill-label {
  display: flex; align-items: center; justify-content: center;
  gap: 6px;
  height: 50px;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  background: #fafaf8;
  font-size: 0.88rem; font-weight: 600; color: var(--muted);
  transition: all 0.2s;
  cursor: pointer;
  text-transform: none; letter-spacing: 0;
}
.gender-pill input[type="radio"]:checked + .gender-pill-label {
  border-color: var(--gold);
  background: linear-gradient(135deg, #fdf6ec, #fffaf3);
  color: #8b5e1a;
  box-shadow: 0 0 0 3px rgba(201,169,110,0.14);
}
.gender-pill-label:hover { border-color: var(--gold2); background: #fffaf3; color: var(--ink); }

/* ── Visitor type cards ── */
.type-cards {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
}
.type-card {
  position: relative; cursor: pointer;
}
.type-card input[type="radio"] {
  position: absolute; opacity: 0; width: 0; height: 0;
}
.type-card-label {
  display: flex; flex-direction: column; align-items: center;
  gap: 8px; padding: 18px 12px;
  border: 1.5px solid var(--border);
  border-radius: 16px;
  background: #fafaf8;
  transition: all 0.25s;
  cursor: pointer;
  text-align: center;
  text-transform: none; letter-spacing: 0;
}
.tc-icon { font-size: 2rem; line-height: 1; }
.tc-label { font-size: 0.85rem; font-weight: 600; color: var(--muted); }
.tc-desc { font-size: 0.72rem; color: rgba(122,122,140,0.7); line-height: 1.4; }
.type-card input[type="radio"]:checked + .type-card-label {
  border-color: var(--green2);
  background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
  box-shadow: 0 0 0 3px rgba(45,106,79,0.1);
}
.type-card input[type="radio"]:checked + .type-card-label .tc-label {
  color: var(--green);
}
.type-card-label:hover { border-color: var(--green3); background: #f9fffe; }

/* ── Profile banner ── */
.profile-banner {
  grid-column: 1 / -1;
  display: flex; align-items: center; gap: 12px;
  border-radius: 14px; padding: 13px 18px;
  margin-bottom: 24px;
  font-size: 0.84rem; font-weight: 600;
}
.profile-banner.logged-in {
  background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
  border: 1.5px solid #a7f3d0;
  color: #065f46;
}
.profile-banner.not-logged-in {
  background: linear-gradient(135deg, #fffbeb, #fef9e7);
  border: 1.5px solid #fde68a;
  color: #92400e;
}
.profile-banner .pb-icon { font-size: 1.2rem; flex-shrink: 0; }
.profile-banner a { font-weight: 700; text-decoration: underline; }
.profile-banner.logged-in a { color: #059669; }
.profile-banner.not-logged-in a { color: #b45309; }
.prefilled-tag {
  display: inline-block; font-size: 0.6rem; font-weight: 700;
  background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;
  border-radius: 999px; padding: 2px 8px; margin-left: 6px;
  text-transform: none; letter-spacing: 0; vertical-align: middle;
}

/* ── Divider ── */
.form-divider {
  grid-column: 1 / -1;
  border: none; border-top: 1px solid var(--border);
  margin: 2px 0 22px;
}

/* ── Date-time pair ── */
.datetime-pair {
  display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
}

/* ── Submit ── */
.submit-row {
  grid-column: 1 / -1;
  margin-top: 10px;
}
.submit-btn {
  position: relative; width: 100%; height: 60px;
  background: linear-gradient(135deg, var(--green) 0%, var(--green2) 50%, var(--green) 100%);
  background-size: 200% 100%;
  color: #fff; border: none; border-radius: 14px;
  font-family: 'Kanit', sans-serif;
  font-size: 1.05rem; font-weight: 600;
  letter-spacing: 0.06em; cursor: pointer;
  transition: all 0.3s ease;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  box-shadow: 0 8px 24px rgba(45,106,79,0.35), 0 2px 8px rgba(45,106,79,0.2);
  overflow: hidden;
}
.submit-btn::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 60%);
  border-radius: inherit; pointer-events: none;
}
.submit-btn:hover {
  background-position: 100% 0;
  transform: translateY(-2px);
  box-shadow: 0 16px 40px rgba(45,106,79,0.4), 0 4px 14px rgba(45,106,79,0.25);
}
.submit-btn:active { transform: translateY(0); }
.submit-btn .btn-icon { font-size: 1.2rem; }
.submit-note {
  text-align: center; font-size: 0.74rem; color: var(--muted);
  line-height: 1.6; margin-top: 12px;
}

/* Required */
.req { color: #e55; margin-left: 2px; }

/* ── Responsive ── */
@media (max-width: 1000px) {
  .main-card { grid-template-columns: 1fr; }
  .panel-left {
    padding: 40px 36px; min-height: auto;
    flex-direction: row; flex-wrap: wrap; gap: 24px;
    align-items: flex-start;
  }
  .pl-steps, .pl-nature-tag { display: none; }
  .pl-brand, .pl-info { flex: 1 1 45%; }
  .pl-divider { display: none; }
}
@media (max-width: 760px) {
  .stats-inner { flex-wrap: wrap; }
  .stat-item { flex: 1 1 50%; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.07); }
  .type-cards { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .main-wrap { padding: 0 16px; margin-top: 32px; }
  .hero-content { padding: 40px 20px; }
  .panel-right { padding: 36px 24px; }
  .form-grid { grid-template-columns: 1fr; }
  .form-group.full { grid-column: 1; }
  .datetime-pair { grid-template-columns: 1fr; }
  .gender-pills { flex-direction: column; }
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.8); }
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<!-- ── Hero ── -->
<div class="hero">
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <div class="hero-badge"><span class="dot"></span> สถาบันวิจัยวลัยรุกขเวช</div>
    <h1>ลงทะเบียน<br><span>เข้าร่วมกิจกรรม</span></h1>
    <p class="hero-sub">กรอกข้อมูลของคุณเพื่อเข้าร่วมกิจกรรมและรับประสบการณ์พิเศษ ภายในพื้นที่ธรรมชาติของเรา</p>
    <div class="hero-actions">
      <a href="index.php" class="back-btn">← กลับหน้าหลัก</a>
    </div>
  </div>
</div>

<!-- ── Stats strip ── -->
<div class="stats-strip">
  <div class="stats-inner">
    <div class="stat-item">
      <span class="stat-icon">🌿</span>
      <div>
        <div class="stat-label">สถานที่</div>
        <div class="stat-value">สวนพฤกษศาสตร์วลัยรุกขเวช</div>
      </div>
    </div>
    <div class="stat-item">
      <span class="stat-icon">📅</span>
      <div>
        <div class="stat-label">วันที่ลงทะเบียน</div>
        <div class="stat-value"><?= date('d M Y', strtotime($currentDate)) ?></div>
      </div>
    </div>
    <div class="stat-item">
      <span class="stat-icon">🕐</span>
      <div>
        <div class="stat-label">เวลาปัจจุบัน</div>
        <div class="stat-value"><?= $currentTime ?> น.</div>
      </div>
    </div>
    <div class="stat-item">
      <span class="stat-icon">✅</span>
      <div>
        <div class="stat-label">ฟรี</div>
        <div class="stat-value">ไม่มีค่าใช้จ่ายในการลงทะเบียน</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Main card ── -->
<div class="main-wrap">
  <div class="main-card">

    <!-- Left panel -->
    <div class="panel-left">
      <div class="pl-brand">
        <div class="pl-logo-ring">🌳</div>
        <div class="pl-name">W<span>RBRI</span></div>
        <div class="pl-sub">Walai Rukhavej Botanical Research Institute</div>
      </div>

      <div class="pl-divider"></div>

      <div class="pl-info">
        <div class="pl-title">ยินดีต้อนรับ<br>สู่ธรรมชาติ</div>
        <p class="pl-desc">ลงทะเบียนเข้าร่วมกิจกรรมกับสถาบันวิจัยวลัยรุกขเวช เพื่อรับประสบการณ์การเรียนรู้ธรรมชาติอย่างใกล้ชิด</p>
      </div>

      <div class="pl-steps">
        <div class="pl-step">
          <div class="pl-step-num">1</div>
          <div class="pl-step-body">
            <div class="pl-step-label">กรอกข้อมูลส่วนตัว</div>
            <div class="pl-step-desc">ชื่อ เพศ และอายุของคุณ</div>
          </div>
        </div>
        <div class="pl-step">
          <div class="pl-step-num">2</div>
          <div class="pl-step-body">
            <div class="pl-step-label">เลือกวันและเวลา</div>
            <div class="pl-step-desc">วันที่ต้องการเข้าชมพื้นที่</div>
          </div>
        </div>
        <div class="pl-step">
          <div class="pl-step-num">3</div>
          <div class="pl-step-body">
            <div class="pl-step-label">เลือกสถานะผู้เข้าชม</div>
            <div class="pl-step-desc">นักศึกษา บุคลากร หรือนักท่องเที่ยว</div>
          </div>
        </div>
        <div class="pl-step">
          <div class="pl-step-num">4</div>
          <div class="pl-step-body">
            <div class="pl-step-label">ยืนยันการลงทะเบียน</div>
            <div class="pl-step-desc">รับหมายเลขยืนยันทันที</div>
          </div>
        </div>
      </div>

      <div class="pl-nature-tag">
        🌱 มีต้นไม้กว่า 3,000 สายพันธุ์
      </div>
    </div>

    <!-- Right panel -->
    <div class="panel-right">
      <div class="form-header">
        <div class="form-step-tag">✦ แบบฟอร์มลงทะเบียน</div>
        <div class="form-title">กรอกข้อมูลของคุณ</div>
        <div class="form-subtitle">กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้องเพื่อการลงทะเบียนที่รวดเร็ว</div>
      </div>

      <form action="save_tourist.php" method="POST">
        <div class="form-grid">

          <?php elseif (!$isLoggedIn): ?>
          <div class="profile-banner not-logged-in">
            <span class="pb-icon">💡</span>
            <div>
              <a href="login.php">เข้าสู่ระบบ</a> เพื่อดึงข้อมูลโปรไฟล์มากรอกอัตโนมัติ
            </div>
          </div>
          <?php endif; ?>

          <!-- ── ข้อมูลส่วนตัว ── -->
          <div class="form-group full">
            <div class="section-label">ข้อมูลส่วนตัว</div>
          </div>

          <!-- ชื่อ -->
          <div class="form-group full">
            <label for="nickname">
              ชื่อ-นามสกุล / ชื่อเล่น <span class="req">*</span>
            </label>
            <div class="input-wrap">
              <span class="input-icon">👤</span>
              <input type="text" id="nickname" name="nickname"
                     placeholder="กรอกชื่อของคุณ" required
                     value="<?= htmlspecialchars($profileName) ?>"
                     <?= $profileName ? 'class="prefilled"' : '' ?>>
            </div>
          </div>

          <!-- เพศ -->
          <div class="form-group full">
            <label>
              เพศ <span class="req">*</span>
            </label>
            <div class="gender-pills">
              <label class="gender-pill">
                <input type="radio" name="gender" value="ชาย" required <?= $profileGender==='ชาย' ? 'checked' : '' ?>>
                <span class="gender-pill-label">👨 ชาย</span>
              </label>
              <label class="gender-pill">
                <input type="radio" name="gender" value="หญิง" <?= $profileGender==='หญิง' ? 'checked' : '' ?>>
                <span class="gender-pill-label">👩 หญิง</span>
              </label>
              <label class="gender-pill">
                <input type="radio" name="gender" value="อื่นๆ" <?= $profileGender==='อื่นๆ' ? 'checked' : '' ?>>
                <span class="gender-pill-label">🧑 อื่นๆ</span>
              </label>
            </div>
          </div>

          <!-- อายุ -->
          <div class="form-group">
            <label for="age">
              อายุ (ปี)
            </label>
            <div class="input-wrap">
              <span class="input-icon">🎂</span>
              <input type="number" id="age" name="age" min="1" max="120" placeholder="ระบุอายุ"
                     value="<?= htmlspecialchars($profileAge) ?>"
                     <?= $profileAge ? 'class="prefilled"' : '' ?>>
            </div>
          </div>

          <div class="form-group"></div><!-- spacer -->

          <hr class="form-divider">

          <!-- ── วันและเวลา ── -->
          <div class="form-group full">
            <div class="section-label">วันและเวลาที่เข้าชม</div>
          </div>

          <div class="form-group full">
            <label>วันที่ และ เวลาที่เข้าชม <span class="req">*</span></label>
            <div class="datetime-pair">
              <div class="input-wrap">
                <span class="input-icon">📅</span>
                <input type="date" id="visit_date" name="visit_date" required
                       value="<?= $currentDate ?>">
              </div>
              <div class="input-wrap">
                <span class="input-icon">🕐</span>
                <input type="time" id="visit_time" name="visit_time" required
                       value="<?= $currentTime ?>">
              </div>
            </div>
          </div>

          <hr class="form-divider">

          <!-- ── สถานะผู้เข้าชม ── -->
          <div class="form-group full">
            <div class="section-label">สถานะผู้เข้าชม</div>
          </div>

          <div class="form-group full">
            <label>กรุณาเลือกสถานะ <span class="req">*</span></label>
            <div class="type-cards">
              <label class="type-card">
                <input type="radio" name="user_type" value="นักศึกษา" required>
                <span class="type-card-label">
                  <span class="tc-icon">🎓</span>
                  <span class="tc-label">นักศึกษา</span>
                  <span class="tc-desc">นักศึกษาและผู้เรียน</span>
                </span>
              </label>
              <label class="type-card">
                <input type="radio" name="user_type" value="บุคลากร">
                <span class="type-card-label">
                  <span class="tc-icon">👔</span>
                  <span class="tc-label">บุคลากร</span>
                  <span class="tc-desc">อาจารย์และเจ้าหน้าที่</span>
                </span>
              </label>
              <label class="type-card">
                <input type="radio" name="user_type" value="นักท่องเที่ยว">
                <span class="type-card-label">
                  <span class="tc-icon">🧳</span>
                  <span class="tc-label">นักท่องเที่ยว</span>
                  <span class="tc-desc">ผู้เยี่ยมชมทั่วไป</span>
                </span>
              </label>
            </div>
          </div>

          <!-- Submit -->
          <div class="submit-row">
            <button type="submit" class="submit-btn" id="submitBtn">
              <span class="btn-icon">🌿</span>
              <span class="btn-label">ยืนยันการลงทะเบียน</span>
            </button>
            <div class="submit-note">
              ช่อง <span class="req">*</span> จำเป็นต้องกรอก &nbsp;·&nbsp; ข้อมูลของคุณจะถูกเก็บเป็นความลับ
            </div>
          </div>

        </div>
      </form>
    </div>

  </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<span class="btn-icon" style="animation:spin .7s linear infinite;display:inline-block">⟳</span><span class="btn-label">กำลังบันทึก...</span>';
  btn.disabled = true;
  btn.style.opacity = '.85';
});
</script>
</body>
</html>
<?php $conn->close(); ?>
