<?php
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$currentDate = date('Y-m-d');
$currentTime = date('H:i');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ลงทะเบียนกิจกรรม — MSU</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --ink:    #1a1a2e;
  --gold:   #c9a96e;
  --gold2:  #e8d5b0;
  --bg:     #f5f1eb;
  --card:   #ffffff;
  --muted:  #7a7a8c;
  --border: #e0ddd6;
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
  min-height: 520px;
  background: url('uploads/88.png') center/cover no-repeat;
  display: flex;
  align-items: flex-end;
  padding: 0 0 0;
  overflow: hidden;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to bottom,
    rgba(26,26,46,0.55) 0%,
    rgba(26,26,46,0.72) 60%,
    rgba(26,26,46,0.88) 100%
  );
}

/* Decorative grid */
.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
  background-image: repeating-linear-gradient(
    90deg, rgba(201,169,110,0.05) 0px, rgba(201,169,110,0.05) 1px,
    transparent 1px, transparent 80px
  );
}

.hero-content {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 1160px;
  margin: 0 auto;
  padding: 0 40px 60px;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  background: rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.85);
  text-decoration: none;
  border: 1.5px solid rgba(255,255,255,0.25);
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  backdrop-filter: blur(6px);
  transition: all 0.25s ease;
  margin-bottom: 28px;
}
.back-btn:hover {
  background: rgba(255,255,255,0.22);
  color: #fff;
  transform: translateY(-1px);
}

.hero-eyebrow {
  font-size: 0.72rem;
  letter-spacing: 0.25em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 12px;
  font-weight: 600;
}

.hero h1 {
  font-family: 'Playfair Display', serif;
  font-style: italic;
  font-size: clamp(2rem, 5vw, 3.2rem);
  color: #fff;
  line-height: 1.2;
  margin-bottom: 14px;
}

.hero-sub {
  font-size: 1rem;
  color: rgba(255,255,255,0.65);
  line-height: 1.7;
  max-width: 480px;
}

/* ── Main section ── */
.main-wrap {
  max-width: 1160px;
  margin: -80px auto 80px;
  padding: 0 40px;
  position: relative;
  z-index: 3;
}

.main-card {
  background: var(--card);
  border-radius: 24px;
  box-shadow: 0 24px 64px rgba(26,26,46,0.12), 0 2px 8px rgba(26,26,46,0.06);
  display: grid;
  grid-template-columns: 1fr 1.6fr;
  overflow: hidden;
}

/* ── Left panel ── */
.panel-left {
  background: var(--ink);
  padding: 52px 44px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}
.panel-left::before {
  content: '';
  position: absolute;
  width: 300px; height: 300px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,169,110,0.15) 0%, transparent 70%);
  top: -80px; right: -100px;
}
.panel-left::after {
  content: '';
  position: absolute;
  width: 180px; height: 180px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,169,110,0.1) 0%, transparent 70%);
  bottom: 40px; left: -50px;
}

.pl-brand { position: relative; z-index: 1; }
.pl-line { width: 32px; height: 3px; background: var(--gold); margin-bottom: 16px; border-radius: 2px; }
.pl-name {
  font-family: 'Playfair Display', serif;
  font-style: italic;
  font-size: 1.6rem;
  color: #fff;
  margin-bottom: 6px;
}
.pl-sub {
  font-size: 0.68rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.3);
}

.pl-info { position: relative; z-index: 1; margin-top: 32px; flex: 1; }
.pl-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.4;
  margin-bottom: 16px;
}
.pl-desc {
  font-size: 0.85rem;
  color: rgba(255,255,255,0.55);
  line-height: 1.8;
}

.pl-steps {
  position: relative;
  z-index: 1;
  margin-top: 40px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.pl-step {
  display: flex;
  align-items: center;
  gap: 12px;
}
.pl-step-num {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: rgba(201,169,110,0.18);
  border: 1.5px solid rgba(201,169,110,0.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--gold);
  flex-shrink: 0;
}
.pl-step-text {
  font-size: 0.8rem;
  color: rgba(255,255,255,0.6);
}

/* ── Right panel (form) ── */
.panel-right {
  padding: 52px 52px;
}

.form-header { margin-bottom: 36px; }
.form-eyebrow {
  font-size: 0.68rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 8px;
  font-weight: 600;
}
.form-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--ink);
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
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 8px;
}

.input-wrap { position: relative; }
.input-icon {
  position: absolute;
  left: 14px; top: 50%;
  transform: translateY(-50%);
  font-size: 0.9rem;
  color: var(--muted);
  pointer-events: none;
}

input[type="text"],
input[type="number"],
input[type="date"],
input[type="time"],
select {
  width: 100%;
  height: 48px;
  padding: 0 14px 0 40px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-family: 'Sarabun', sans-serif;
  font-size: 0.92rem;
  color: var(--ink);
  background: #fafaf8;
  outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
  appearance: none;
  -webkit-appearance: none;
}
input:focus, select:focus {
  border-color: var(--gold);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(201,169,110,0.13);
}

/* Custom select arrow */
.select-wrap::after {
  content: '▾';
  position: absolute;
  right: 14px; top: 50%;
  transform: translateY(-50%);
  font-size: 0.8rem;
  color: var(--muted);
  pointer-events: none;
}

/* Divider */
.form-divider {
  grid-column: 1 / -1;
  border: none;
  border-top: 1px solid var(--border);
  margin: 4px 0 20px;
}

/* Submit button */
.submit-row {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 8px;
}
.submit-btn {
  flex: 1;
  height: 52px;
  background: var(--ink);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: 'Sarabun', sans-serif;
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.25s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.submit-btn:hover {
  background: #2a2a4a;
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(26,26,46,0.2);
}
.submit-note {
  font-size: 0.74rem;
  color: var(--muted);
  line-height: 1.6;
}

/* Required star */
.req { color: var(--gold); margin-left: 2px; }

/* ── Responsive ── */
@media (max-width: 960px) {
  .main-card { grid-template-columns: 1fr; }
  .panel-left { padding: 40px 36px; flex-direction: row; flex-wrap: wrap; gap: 20px; }
  .pl-steps { display: none; }
}
@media (max-width: 640px) {
  .main-wrap { padding: 0 16px; margin-top: -50px; }
  .hero-content { padding: 0 20px 50px; }
  .panel-right { padding: 36px 24px; }
  .form-grid { grid-template-columns: 1fr; }
  .form-group.full { grid-column: 1; }
  .submit-row { flex-direction: column; align-items: stretch; }
}
</style>
</head>
<body>

<!-- ── Hero ── -->
<div class="hero">
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <a href="index.php" class="back-btn">← กลับหน้าหลัก</a>
    <div class="hero-eyebrow">Activity Registration</div>
    <h1>ลงทะเบียนเข้าร่วมกิจกรรม</h1>
    <p class="hero-sub">กรอกข้อมูลเพื่อลงทะเบียนเข้าร่วมกิจกรรม รับประสบการณ์พิเศษที่รอคุณอยู่</p>
  </div>
</div>

<!-- ── Main card ── -->
<div class="main-wrap">
  <div class="main-card">

    <!-- Left panel -->
    <div class="panel-left">
      <div class="pl-brand">
        <div class="pl-line"></div>
        <div class="pl-name">MSU</div>
        <div class="pl-sub">Activity Center</div>
      </div>

      <div class="pl-info">
        <div class="pl-title">ประสบการณ์พิเศษ<br>รอคุณอยู่</div>
        <p class="pl-desc">ลงทะเบียนเข้าร่วมกิจกรรมกับเรา เพื่อรับประสบการณ์ดี ๆ และเข้าร่วมกิจกรรมที่น่าสนใจได้อย่างสะดวก รวดเร็ว และเป็นระเบียบ</p>
      </div>

      <div class="pl-steps">
        <div class="pl-step">
          <div class="pl-step-num">1</div>
          <div class="pl-step-text">กรอกชื่อและข้อมูลส่วนตัว</div>
        </div>
        <div class="pl-step">
          <div class="pl-step-num">2</div>
          <div class="pl-step-text">เลือกวันและเวลาที่ต้องการเข้าชม</div>
        </div>
        <div class="pl-step">
          <div class="pl-step-num">3</div>
          <div class="pl-step-text">ยืนยันการลงทะเบียน</div>
        </div>
      </div>
    </div>

    <!-- Right panel (form) -->
    <div class="panel-right">
      <div class="form-header">
        <div class="form-eyebrow">กรอกข้อมูล</div>
        <div class="form-title">แบบฟอร์มลงทะเบียน</div>
      </div>

      <form action="save_tourist.php" method="POST">
        <div class="form-grid">

          <!-- ชื่อเล่น -->
          <div class="form-group full">
            <label for="nickname">ชื่อเล่น <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">👤</span>
              <input type="text" id="nickname" name="nickname" placeholder="กรอกชื่อเล่นของคุณ" required>
            </div>
          </div>

          <!-- เพศ -->
          <div class="form-group">
            <label for="gender">เพศ <span class="req">*</span></label>
            <div class="input-wrap select-wrap">
              <span class="input-icon">⚧</span>
              <select id="gender" name="gender" required>
                <option value="">เลือกเพศ</option>
                <option value="ชาย">ชาย</option>
                <option value="หญิง">หญิง</option>
                <option value="อื่นๆ">อื่นๆ</option>
              </select>
            </div>
          </div>

          <!-- อายุ -->
          <div class="form-group">
            <label for="age">อายุ</label>
            <div class="input-wrap">
              <span class="input-icon">🎂</span>
              <input type="number" id="age" name="age" min="1" max="120" placeholder="ปี">
            </div>
          </div>

          <hr class="form-divider">

          <!-- วันที่ -->
          <div class="form-group">
            <label for="visit_date">วันที่เข้าชม <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">📅</span>
              <input type="date" id="visit_date" name="visit_date" required
                     value="<?php echo $currentDate; ?>">
            </div>
          </div>

          <!-- เวลา -->
          <div class="form-group">
            <label for="visit_time">เวลาที่เข้าชม <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">🕐</span>
              <input type="time" id="visit_time" name="visit_time" required
                     value="<?php echo $currentTime; ?>">
            </div>
          </div>

          <!-- สถานะ -->
          <div class="form-group full">
            <label for="user_type">สถานะผู้เข้าชม <span class="req">*</span></label>
            <div class="input-wrap select-wrap">
              <span class="input-icon">🏷</span>
              <select id="user_type" name="user_type" required>
                <option value="">เลือกสถานะ</option>
                <option value="นักศึกษา">นักศึกษา</option>
                <option value="บุคลากร">บุคลากร</option>
                <option value="นักท่องเที่ยว">นักท่องเที่ยว</option>
              </select>
            </div>
          </div>

          <!-- Submit -->
          <div class="submit-row">
            <button type="submit" class="submit-btn">
              ✓ ยืนยันการลงทะเบียน
            </button>
            <div class="submit-note">
              ช่อง <span class="req">*</span> จำเป็นต้องกรอก<br>
              ข้อมูลของคุณจะถูกเก็บเป็นความลับ
            </div>
          </div>

        </div>
      </form>
    </div>

  </div>
</div>

</body>
</html>
<?php $conn->close(); ?>