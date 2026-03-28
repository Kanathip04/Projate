<?php
date_default_timezone_set('Asia/Bangkok');
include 'config.php';

// Auto-create table if not exists
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

// Insert default row if empty
$chk = $conn->query("SELECT COUNT(*) as c FROM kengcamp_info");
if ($chk && $chk->fetch_assoc()['c'] == 0) {
    $default_equipment = json_encode([
        ['name' => 'เต็นท์ 1-2 คน', 'price' => '100', 'unit' => 'หลัง'],
        ['name' => 'เต็นท์ 3-4 คน', 'price' => '150', 'unit' => 'หลัง'],
        ['name' => 'เก้าอี้', 'price' => '30', 'unit' => 'ตัว'],
        ['name' => 'โต๊ะ', 'price' => '30', 'unit' => 'ตัว'],
        ['name' => 'เบาะรองนอน', 'price' => '30', 'unit' => 'ชิ้น'],
        ['name' => 'หมอน', 'price' => '30', 'unit' => 'ใบ'],
        ['name' => 'ชุดเก้าอี้สนาม (4 ตัว/โต๊ะ 1 ตัว)', 'price' => '120', 'unit' => 'ชุด'],
        ['name' => 'เครื่องนอน 1 ชุด (ผ้าปู/ผ้าห่ม/หมอน 2 ใบ)', 'price' => '100', 'unit' => 'ชุด'],
    ], JSON_UNESCAPED_UNICODE);
    $default_activities = json_encode([
        ['name' => 'พายเรือคายัค', 'price' => '20', 'unit' => 'คน'],
        ['name' => 'เส้นทางเดินศึกษาธรรมชาติ', 'price' => '0', 'unit' => ''],
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
        ['name' => 'คุณปอ', 'phone' => '088-5522308'],
        ['name' => 'คุณออย', 'phone' => '082-3069984'],
        ['name' => 'คุณโตโต้', 'phone' => '086-8529944'],
    ], JSON_UNESCAPED_UNICODE);
    $note = 'หากมาถึงก่อนเวลา แล้วมีพื้นที่ว่างสามารถกางได้เลย';
    $ins = $conn->prepare("INSERT INTO kengcamp_info (early_checkin_note, equipment_json, activities_json, rules_json, contacts_json) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("sssss", $note, $default_equipment, $default_activities, $default_rules, $default_contacts);
    $ins->execute();
    $ins->close();
}

$row = $conn->query("SELECT * FROM kengcamp_info ORDER BY id DESC LIMIT 1")->fetch_assoc();

$equipment  = json_decode($row['equipment_json'] ?? '[]', true) ?: [];
$activities = json_decode($row['activities_json'] ?? '[]', true) ?: [];
$rules      = json_decode($row['rules_json'] ?? '[]', true) ?: [];
$contacts   = json_decode($row['contacts_json'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เก็งแคมป์ — ENJOY YOUR LIFE WITH NATURE</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --ink:        #1a1a2e;
    --gold:       #c9a96e;
    --gold-light: #e8d5b0;
    --bg:         #f5f1eb;
    --card:       #fff;
    --muted:      #7a7a8c;
    --border:     #e8e4de;
    --forest:     #2d5a27;
}
body {
    font-family: 'Sarabun', sans-serif;
    background: var(--bg);
    color: var(--ink);
    font-size: 16px;
    line-height: 1.6;
}
.container { width: min(1100px, 92%); margin: 0 auto; }

/* ── Hero ── */
.hero {
    background: linear-gradient(160deg, #0a1a0a 0%, #1a2e1a 40%, #1a1a2e 100%);
    color: #fff;
    padding: 48px 20px 100px;
    position: relative;
    overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a96e' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.hero-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 36px; }
.top-menu { display: flex; gap: 10px; flex-wrap: wrap; }
.top-menu a {
    display: inline-flex; align-items: center;
    padding: 9px 18px; border-radius: 999px;
    text-decoration: none; color: #fff; font-weight: 700; font-size: 14px;
    border: 1px solid rgba(201,169,110,.45);
    background: rgba(201,169,110,.12);
    transition: background .25s ease, transform .2s ease;
}
.top-menu a:hover { background: rgba(201,169,110,.25); transform: translateY(-2px); }
.hero-badge {
    background: var(--gold); color: var(--ink);
    font-size: 11px; font-weight: 800; letter-spacing: .15em;
    text-transform: uppercase; padding: 5px 14px;
    border-radius: 999px; align-self: flex-start;
}
.hero-content { position: relative; z-index: 1; }
.hero-sub {
    font-size: 12px; letter-spacing: .3em; text-transform: uppercase;
    color: var(--gold); margin-bottom: 12px; font-weight: 600;
}
.hero h1 {
    font-family: 'Playfair Display', serif;
    font-style: italic; font-size: 62px; font-weight: 700;
    line-height: 1.1; margin-bottom: 10px;
    background: linear-gradient(135deg, #fff 40%, var(--gold-light));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.hero-tagline {
    font-size: 15px; letter-spacing: .18em; text-transform: uppercase;
    color: rgba(255,255,255,.55); margin-bottom: 20px;
}
.hero-desc {
    font-size: 17px; max-width: 660px;
    color: rgba(255,255,255,.8); line-height: 1.75;
}

/* ── Content ── */
.content { margin-top: -48px; padding-bottom: 60px; }
.section { margin-bottom: 28px; }
.section-title {
    font-size: 13px; font-weight: 800; letter-spacing: .18em;
    text-transform: uppercase; color: var(--gold); margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px;
}
.section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── Card ── */
.card {
    background: var(--card); border-radius: 20px; overflow: hidden;
    box-shadow: 0 8px 32px rgba(26,26,46,.09), 0 1px 4px rgba(26,26,46,.05);
    border: 1px solid var(--border); margin-bottom: 20px;
}
.card::before {
    content: ''; display: block; height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
}
.card-body { padding: 28px 30px; }

/* ── Pricing cards ── */
.price-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}
.price-card {
    border: 1.5px solid var(--border); border-radius: 14px;
    padding: 20px 22px; text-align: center;
    transition: border-color .25s, transform .2s;
}
.price-card:hover { border-color: var(--gold); transform: translateY(-2px); }
.price-card.featured {
    background: linear-gradient(145deg, var(--ink) 0%, #252545 100%);
    border-color: rgba(201,169,110,.4);
}
.price-card .label {
    font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: var(--muted); margin-bottom: 10px;
}
.price-card.featured .label { color: rgba(255,255,255,.5); }
.price-card .price {
    font-size: 42px; font-weight: 800; color: var(--ink); line-height: 1;
}
.price-card.featured .price { color: var(--gold); }
.price-card .unit { font-size: 13px; color: var(--muted); margin-top: 4px; }
.price-card.featured .unit { color: rgba(255,255,255,.5); }
.price-card .note { font-size: 13px; color: var(--forest); font-weight: 600; margin-top: 8px; }
.price-card.featured .note { color: rgba(255,255,255,.7); }

/* ── Time box ── */
.time-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.time-box {
    border: 1.5px solid var(--border); border-radius: 14px;
    padding: 18px 20px; text-align: center;
}
.time-box .t-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 6px; }
.time-box .t-value { font-size: 28px; font-weight: 800; color: var(--ink); }
.early-note {
    background: rgba(201,169,110,.1); border: 1px solid rgba(201,169,110,.35);
    border-radius: 10px; padding: 12px 16px;
    font-size: 14px; color: var(--ink); margin-top: 12px; text-align: center;
}
.early-note strong { color: var(--gold); }

/* ── Equipment table ── */
.eq-table { width: 100%; border-collapse: collapse; }
.eq-table thead th {
    padding: 10px 14px; font-size: 11px; text-transform: uppercase;
    letter-spacing: .1em; color: var(--muted); font-weight: 700;
    border-bottom: 2px solid var(--border); text-align: left; background: #fdfcfa;
}
.eq-table tbody td {
    padding: 12px 14px; font-size: 15px; border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.eq-table tbody tr:last-child td { border-bottom: none; }
.eq-table tbody tr:hover { background: #fdfcfa; }
.price-cell { font-weight: 800; color: var(--ink); white-space: nowrap; }
.price-cell .baht { color: var(--gold); }

/* ── Activities ── */
.act-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
.act-card {
    border: 1.5px solid var(--border); border-radius: 14px;
    padding: 20px; display: flex; flex-direction: column; align-items: center;
    text-align: center; gap: 8px;
    transition: border-color .25s, transform .2s;
}
.act-card:hover { border-color: var(--gold); transform: translateY(-2px); }
.act-icon { font-size: 32px; }
.act-name { font-weight: 700; font-size: 15px; color: var(--ink); }
.act-price {
    font-size: 13px; color: var(--muted);
    background: rgba(201,169,110,.1); padding: 3px 10px; border-radius: 999px;
}

/* ── Rules ── */
.rules-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.rules-list li {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 16px; background: var(--bg);
    border: 1px solid var(--border); border-radius: 10px;
    font-size: 15px;
}
.rules-list li::before {
    content: '⚠️'; flex-shrink: 0; margin-top: 1px;
}

/* ── Contact ── */
.contact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
.contact-card {
    border: 1.5px solid var(--border); border-radius: 14px;
    padding: 18px 20px; text-align: center;
    transition: border-color .25s, transform .2s;
}
.contact-card:hover { border-color: var(--gold); transform: translateY(-2px); }
.contact-name { font-weight: 700; font-size: 16px; color: var(--ink); margin-bottom: 6px; }
.contact-phone {
    font-size: 18px; font-weight: 800; color: var(--gold);
    text-decoration: none; display: block;
}
.contact-phone:hover { text-decoration: underline; }

/* ── QR Box ── */
.qr-box { text-align: center; padding: 28px; }
.qr-box img { max-width: 180px; border-radius: 12px; margin-bottom: 10px; }
.qr-label { font-size: 14px; color: var(--muted); font-weight: 600; }

/* ── Footer strip ── */
.footer-strip {
    background: var(--ink); color: rgba(255,255,255,.45);
    text-align: center; padding: 18px; font-size: 13px;
}
.footer-strip strong { color: var(--gold); }

@media (max-width: 700px) {
    .hero h1 { font-size: 38px; }
    .time-grid { grid-template-columns: 1fr; }
    .card-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="hero-top">
            <div class="top-menu">
                <a href="index.php">← หน้าหลัก</a>
                <a href="view_data.php">ลงทะเบียนกิจกรรม</a>
            </div>
            <div class="hero-badge">⛺ เปิดรับนักท่องเที่ยว</div>
        </div>
        <div class="hero-content">
            <div class="hero-sub">WRBRI · Mahasarakham University</div>
            <h1>เก็งแคมป์</h1>
            <div class="hero-tagline">Enjoy Your Life With Nature</div>
            <div class="hero-desc">
                สัมผัสธรรมชาติแท้ๆ กับการแคมปิ้งกลางป่า ภายในสถาบันวิจัยวลัยรุกขเวช
                มหาวิทยาลัยมหาสารคาม พร้อมกิจกรรมพายเรือคายัคและเส้นทางเดินศึกษาธรรมชาติ
            </div>
        </div>
    </div>
</section>

<!-- Content -->
<section class="content">
<div class="container">

    <!-- Pricing -->
    <div class="section">
        <div class="section-title">อัตราค่าบริการ</div>
        <div class="card">
            <div class="card-body">
                <div class="price-grid">
                    <div class="price-card featured">
                        <div class="label">ค่าเข้า (ต่อคน/คืน)</div>
                        <div class="price">฿<?= number_format((float)($row['entry_fee'] ?? 50)) ?></div>
                        <div class="unit">บาท / คน / คืน</div>
                        <div class="note">เด็กอายุต่ำกว่า <?= (int)($row['children_free_age'] ?? 9) ?> ขวบ เข้าฟรี</div>
                    </div>
                    <div class="price-card">
                        <div class="label">เต็นท์หลังคารถ / นอนในรถ</div>
                        <div class="price">฿<?= number_format((float)($row['rooftop_fee'] ?? 300)) ?></div>
                        <div class="unit">บาท / คัน</div>
                        <div class="note">ราคาต่อคืน</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Check-in / Check-out -->
    <div class="section">
        <div class="section-title">เวลาเช็คอิน / เช็คเอาท์</div>
        <div class="card">
            <div class="card-body">
                <div class="time-grid">
                    <div class="time-box">
                        <div class="t-label">เวลาเช็คอิน</div>
                        <div class="t-value"><?= htmlspecialchars($row['checkin_time'] ?? '14.00 น.') ?></div>
                    </div>
                    <div class="time-box">
                        <div class="t-label">เวลาเช็คเอาท์</div>
                        <div class="t-value"><?= htmlspecialchars($row['checkout_time'] ?? '12.00 น.') ?></div>
                    </div>
                </div>
                <?php if (!empty($row['early_checkin_note'])): ?>
                <div class="early-note">
                    <strong>**</strong> <?= htmlspecialchars($row['early_checkin_note']) ?> <strong>**</strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Equipment Rental -->
    <?php if (!empty($equipment)): ?>
    <div class="section">
        <div class="section-title">อุปกรณ์ให้เช่า</div>
        <div class="card">
            <div class="card-body" style="padding:0; overflow:hidden;">
                <table class="eq-table">
                    <thead>
                        <tr>
                            <th>รายการ</th>
                            <th>ราคา</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($equipment as $eq): ?>
                        <tr>
                            <td><?= htmlspecialchars($eq['name'] ?? '') ?></td>
                            <td class="price-cell">
                                <?php if (!empty($eq['price']) && (float)$eq['price'] > 0): ?>
                                    <span class="baht">฿</span><?= number_format((float)$eq['price']) ?>.-/<?= htmlspecialchars($eq['unit'] ?? '') ?>
                                <?php else: ?>
                                    <span style="color:var(--forest);font-weight:600;">ฟรี</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Activities -->
    <?php if (!empty($activities)): ?>
    <div class="section">
        <div class="section-title">กิจกรรมในพื้นที่</div>
        <div class="act-grid">
            <?php
            $act_icons = ['🚣', '🌿', '🦋', '🏕️', '🌄', '⛵'];
            foreach ($activities as $i => $act):
                $icon = $act_icons[$i % count($act_icons)];
            ?>
            <div class="act-card">
                <div class="act-icon"><?= $icon ?></div>
                <div class="act-name"><?= htmlspecialchars($act['name'] ?? '') ?></div>
                <?php if (!empty($act['price']) && (float)$act['price'] > 0): ?>
                    <div class="act-price">฿<?= number_format((float)$act['price']) ?>/<?= htmlspecialchars($act['unit'] ?? 'คน') ?></div>
                <?php else: ?>
                    <div class="act-price">ฟรี</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rules -->
    <?php if (!empty($rules)): ?>
    <div class="section">
        <div class="section-title">ข้อปฏิบัติ</div>
        <div class="card">
            <div class="card-body">
                <ul class="rules-list">
                    <?php foreach ($rules as $rule): ?>
                    <li><?= htmlspecialchars($rule) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contact + QR -->
    <div class="section">
        <div class="section-title">ติดต่อสอบถาม</div>
        <div class="card">
            <div class="card-body">
                <div class="contact-grid">
                    <?php foreach ($contacts as $c): ?>
                    <div class="contact-card">
                        <div class="contact-name"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                        <a href="tel:<?= preg_replace('/[^0-9]/', '', $c['phone'] ?? '') ?>" class="contact-phone">
                            <?= htmlspecialchars($c['phone'] ?? '') ?>
                        </a>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!empty($row['qr_image']) && file_exists(__DIR__ . '/' . $row['qr_image'])): ?>
                    <div class="contact-card qr-box">
                        <img src="<?= htmlspecialchars($row['qr_image']) ?>" alt="QR Code ชำระเงิน">
                        <div class="qr-label">QR Code ชำระเงิน</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
</section>

<div class="footer-strip">
    <strong>เก็งแคมป์</strong> · สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม
</div>

</body>
</html>
