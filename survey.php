<?php
session_start();
require_once 'config.php';

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS survey_responses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  respondent_type VARCHAR(50) NOT NULL,
  q1_1 TINYINT, q1_2 TINYINT, q1_3 TINYINT, q1_4 TINYINT, q1_5 TINYINT,
  q2_1 TINYINT, q2_2 TINYINT, q2_3 TINYINT, q2_4 TINYINT,
  q3_1 TINYINT, q3_2 TINYINT, q3_3 TINYINT,
  overall TINYINT NOT NULL,
  suggestion TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_type (respondent_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $conn->real_escape_string(trim($_POST['respondent_type'] ?? ''));
    $overall = (int)($_POST['overall'] ?? 0);
    if ($type && $overall >= 1 && $overall <= 5) {
        $q = [];
        for ($s = 1; $s <= 3; $s++) {
            $max = ($s === 1) ? 5 : (($s === 2) ? 4 : 3);
            for ($i = 1; $i <= $max; $i++) {
                $v = (int)($_POST["q{$s}_{$i}"] ?? 0);
                $q["q{$s}_{$i}"] = ($v >= 1 && $v <= 5) ? $v : 'NULL';
            }
        }
        $sug = $conn->real_escape_string(trim($_POST['suggestion'] ?? ''));
        $ip  = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
        $cols = implode(',', array_keys($q));
        $vals = implode(',', array_values($q));
        $conn->query("INSERT INTO survey_responses
            (respondent_type,$cols,overall,suggestion,ip_address)
            VALUES ('$type',$vals,$overall,'$sug','$ip')");
        $submitted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>แบบประเมินความพึงพอใจ — WRBRI</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#1a1a2e;--accent:#c9a96e;--muted:#7a7a8c;--border:#e0ddd6;--bg:#f5f1eb;--card:#fff;--danger:#c0392b;--success:#2e7d32;--radius:14px}
body{min-height:100vh;background:var(--bg);background-image:radial-gradient(ellipse at 20% 50%,rgba(201,169,110,.1) 0%,transparent 60%);font-family:'Sarabun',sans-serif;color:var(--ink);padding:0 0 60px}
body::before{content:'';position:fixed;inset:0;pointer-events:none;background-image:repeating-linear-gradient(90deg,rgba(201,169,110,.04) 0px,rgba(201,169,110,.04) 1px,transparent 1px,transparent 80px)}

/* Header */
.site-header{background:var(--ink);padding:18px 0;position:sticky;top:0;z-index:50;box-shadow:0 2px 20px rgba(0,0,0,.3)}
.header-inner{max-width:760px;margin:0 auto;padding:0 24px;display:flex;align-items:center;gap:16px}
.back-link{color:rgba(255,255,255,.5);text-decoration:none;font-size:13px;transition:.2s;display:flex;align-items:center;gap:6px;white-space:nowrap}
.back-link:hover{color:var(--accent)}
.brand{font-family:'Playfair Display',serif;font-style:italic;font-size:1.4rem;color:#fff}

/* Hero */
.hero{background:var(--ink);text-align:center;padding:48px 24px 56px;position:relative;overflow:hidden}
.hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 100%,rgba(201,169,110,.15) 0%,transparent 65%);pointer-events:none}
.hero-badge{display:inline-block;background:rgba(201,169,110,.15);border:1px solid rgba(201,169,110,.3);color:var(--accent);font-size:11px;letter-spacing:.2em;text-transform:uppercase;padding:5px 16px;border-radius:50px;margin-bottom:18px}
.hero-title{font-family:'Playfair Display',serif;font-style:italic;font-size:2rem;color:#fff;margin-bottom:10px}
.hero-sub{font-size:.9rem;color:rgba(255,255,255,.5);max-width:500px;margin:0 auto}

/* Container */
.container{max-width:760px;margin:-28px auto 0;padding:0 20px;position:relative;z-index:1}

/* Card */
.card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);padding:28px 32px;margin-bottom:20px;box-shadow:0 4px 24px rgba(0,0,0,.06)}
.section-header{display:flex;align-items:center;gap:12px;margin-bottom:22px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.section-num{width:32px;height:32px;border-radius:50%;background:var(--ink);color:var(--accent);font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.section-title{font-size:1.05rem;font-weight:700;color:var(--ink)}
.section-sub{font-size:.78rem;color:var(--muted);margin-top:2px}

/* Type selector */
.type-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.type-card{position:relative}
.type-card input{position:absolute;opacity:0;pointer-events:none}
.type-card label{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 10px;border:2px solid var(--border);border-radius:10px;cursor:pointer;transition:.2s;font-size:.85rem;color:var(--muted);text-align:center}
.type-card label:hover{border-color:var(--accent);color:var(--ink)}
.type-card input:checked+label{border-color:var(--accent);background:rgba(201,169,110,.08);color:var(--ink);font-weight:600}
.type-icon{font-size:1.6rem}

/* Rating scale */
.rating-table{width:100%;border-collapse:collapse}
.rating-table thead th{font-size:.72rem;color:var(--muted);letter-spacing:.05em;text-transform:uppercase;padding:6px 4px;text-align:center;font-weight:600;border-bottom:1px solid var(--border)}
.rating-table thead th:first-child{text-align:left;padding-left:0}
.rating-table tbody tr{border-bottom:1px solid rgba(224,221,214,.5)}
.rating-table tbody tr:last-child{border-bottom:none}
.rating-table tbody td{padding:12px 4px;vertical-align:middle}
.rating-table tbody td:first-child{font-size:.9rem;color:var(--ink);padding-left:0;line-height:1.5}
.rating-table tbody td:not(:first-child){text-align:center}
.rating-table input[type=radio]{display:none}
.rating-table label{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:2px solid var(--border);border-radius:50%;cursor:pointer;font-size:.8rem;font-weight:600;color:var(--muted);transition:.18s}
.rating-table label:hover{border-color:var(--accent);color:var(--accent)}
.rating-table input[type=radio]:checked+label{background:var(--ink);border-color:var(--ink);color:var(--accent)}
.scale-labels{display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted);margin-top:10px;padding:0 2px}
.scale-label-end{font-style:italic}

/* Overall star rating */
.star-wrap{display:flex;gap:10px;justify-content:center;padding:10px 0;flex-direction:row-reverse}
.star-wrap input{display:none}
.star-wrap label{font-size:2.6rem;cursor:pointer;color:#d1cdc4;transition:.15s;line-height:1}
.star-wrap label:hover,.star-wrap label:hover~label,
.star-wrap input:checked~label{color:#c9a96e}

/* Suggestion */
textarea{width:100%;padding:13px 16px;border:1.5px solid var(--border);border-radius:10px;font-family:'Sarabun',sans-serif;font-size:.92rem;color:var(--ink);background:#fafaf8;outline:none;resize:vertical;min-height:100px;transition:.2s}
textarea:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 3px rgba(201,169,110,.12)}

/* Submit */
.btn-submit{width:100%;padding:16px;background:var(--ink);color:#fff;border:none;border-radius:10px;font-family:'Sarabun',sans-serif;font-size:.95rem;letter-spacing:.1em;text-transform:uppercase;font-weight:700;cursor:pointer;transition:.25s;margin-top:8px}
.btn-submit:hover{background:#2a2a4e;transform:translateY(-1px)}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}
.form-note{text-align:center;font-size:.75rem;color:var(--muted);margin-top:12px}

/* Error */
.field-error{display:none;font-size:.74rem;color:var(--danger);margin-top:6px}
.has-error .field-error{display:block}
.has-error .type-card label,.has-error textarea{border-color:var(--danger)!important}

/* Success */
.success-wrap{text-align:center;padding:60px 20px}
.success-icon{font-size:4rem;margin-bottom:16px}
.success-title{font-size:1.6rem;font-weight:700;color:var(--ink);margin-bottom:8px}
.success-sub{color:var(--muted);font-size:.92rem;margin-bottom:28px}
.btn-back{display:inline-block;padding:12px 28px;background:var(--ink);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;transition:.2s}
.btn-back:hover{background:#2a2a4e}

@media(max-width:600px){.card{padding:20px 18px}.type-grid{grid-template-columns:repeat(3,1fr)}.rating-table tbody td:first-child{font-size:.82rem}}
</style>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a href="index.php" class="back-link">← กลับ</a>
    <div class="brand">WRBRI</div>
  </div>
</header>

<div class="hero">
  <div class="hero-badge">Satisfaction Survey</div>
  <div class="hero-title">แบบประเมินความพึงพอใจ</div>
  <div class="hero-sub">สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม<br>กรุณาประเมินตามความเป็นจริง เพื่อพัฒนาการให้บริการ</div>
</div>

<div class="container">

<?php if ($submitted): ?>
<div class="card">
  <div class="success-wrap">
    <div class="success-icon">✅</div>
    <div class="success-title">ขอบคุณสำหรับการประเมิน</div>
    <div class="success-sub">ข้อมูลของท่านได้รับการบันทึกเรียบร้อยแล้ว<br>ทางสถาบันจะนำไปปรับปรุงและพัฒนาการให้บริการต่อไป</div>
    <a href="index.php" class="btn-back">← กลับหน้าหลัก</a>
  </div>
</div>
<?php else: ?>

<form method="POST" id="surveyForm" novalidate>

  <!-- ประเภทผู้ประเมิน -->
  <div class="card" id="sec-type">
    <div class="section-header">
      <div class="section-num">0</div>
      <div>
        <div class="section-title">ข้อมูลผู้ประเมิน</div>
        <div class="section-sub">กรุณาเลือกประเภทที่ตรงกับท่านมากที่สุด</div>
      </div>
    </div>
    <div class="type-grid">
      <?php
      $types = [
        ['นักศึกษา','🎓','student'],
        ['บุคลากร MSU','👔','staff'],
        ['นักวิจัย','🔬','researcher'],
        ['นักท่องเที่ยว','🌿','tourist'],
        ['ประชาชนทั่วไป','👤','public'],
        ['อื่นๆ','✨','other'],
      ];
      foreach ($types as [$label,$icon,$val]):
      ?>
      <div class="type-card">
        <input type="radio" name="respondent_type" id="type_<?=$val?>" value="<?=$val?>">
        <label for="type_<?=$val?>">
          <span class="type-icon"><?=$icon?></span>
          <?=$label?>
        </label>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="field-error" id="err-type">กรุณาเลือกประเภทผู้ประเมิน</div>
  </div>

  <!-- หมวด 1: การให้บริการ -->
  <div class="card">
    <div class="section-header">
      <div class="section-num">1</div>
      <div>
        <div class="section-title">ด้านการให้บริการ</div>
        <div class="section-sub">1 = น้อยที่สุด &nbsp;|&nbsp; 5 = มากที่สุด</div>
      </div>
    </div>
    <?php
    $q1 = [
      'q1_1' => 'เจ้าหน้าที่ให้บริการด้วยความสุภาพและเป็นมิตร',
      'q1_2' => 'เจ้าหน้าที่ให้คำแนะนำและแก้ไขปัญหาได้อย่างมีประสิทธิภาพ',
      'q1_3' => 'การให้บริการมีความรวดเร็วและทันต่อความต้องการ',
      'q1_4' => 'ขั้นตอนและกระบวนการให้บริการมีความชัดเจน',
      'q1_5' => 'ได้รับบริการตรงตามวัตถุประสงค์ที่ต้องการ',
    ];
    ?>
    <table class="rating-table">
      <thead>
        <tr>
          <th>หัวข้อ</th>
          <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($q1 as $name => $label): ?>
        <tr>
          <td><?=$label?></td>
          <?php for ($v=1;$v<=5;$v++): ?>
          <td>
            <input type="radio" name="<?=$name?>" id="<?=$name?>_<?=$v?>" value="<?=$v?>">
            <label for="<?=$name?>_<?=$v?>"><?=$v?></label>
          </td>
          <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="scale-labels">
      <span>น้อยที่สุด</span>
      <span class="scale-label-end">มากที่สุด</span>
    </div>
  </div>

  <!-- หมวด 2: สถานที่และสิ่งอำนวยความสะดวก -->
  <div class="card">
    <div class="section-header">
      <div class="section-num">2</div>
      <div>
        <div class="section-title">ด้านสถานที่และสิ่งอำนวยความสะดวก</div>
        <div class="section-sub">1 = น้อยที่สุด &nbsp;|&nbsp; 5 = มากที่สุด</div>
      </div>
    </div>
    <?php
    $q2 = [
      'q2_1' => 'สถานที่สะอาด เป็นระเบียบ และน่าเข้าชม',
      'q2_2' => 'สิ่งอำนวยความสะดวกมีครบถ้วนและพร้อมใช้งาน',
      'q2_3' => 'ความปลอดภัยภายในสถานที่อยู่ในระดับดี',
      'q2_4' => 'ป้ายบอกทางและสื่อแนะนำมีความชัดเจนและเข้าใจง่าย',
    ];
    ?>
    <table class="rating-table">
      <thead>
        <tr>
          <th>หัวข้อ</th>
          <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($q2 as $name => $label): ?>
        <tr>
          <td><?=$label?></td>
          <?php for ($v=1;$v<=5;$v++): ?>
          <td>
            <input type="radio" name="<?=$name?>" id="<?=$name?>_<?=$v?>" value="<?=$v?>">
            <label for="<?=$name?>_<?=$v?>"><?=$v?></label>
          </td>
          <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="scale-labels">
      <span>น้อยที่สุด</span>
      <span class="scale-label-end">มากที่สุด</span>
    </div>
  </div>

  <!-- หมวด 3: ระบบสารสนเทศ -->
  <div class="card">
    <div class="section-header">
      <div class="section-num">3</div>
      <div>
        <div class="section-title">ด้านระบบสารสนเทศและเว็บไซต์</div>
        <div class="section-sub">1 = น้อยที่สุด &nbsp;|&nbsp; 5 = มากที่สุด</div>
      </div>
    </div>
    <?php
    $q3 = [
      'q3_1' => 'เว็บไซต์และระบบออนไลน์ใช้งานได้สะดวกและรวดเร็ว',
      'q3_2' => 'ข้อมูลบนเว็บไซต์มีความครบถ้วน ถูกต้อง และเป็นปัจจุบัน',
      'q3_3' => 'ระบบการลงทะเบียนและจองห้องพักออนไลน์มีประสิทธิภาพ',
    ];
    ?>
    <table class="rating-table">
      <thead>
        <tr>
          <th>หัวข้อ</th>
          <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($q3 as $name => $label): ?>
        <tr>
          <td><?=$label?></td>
          <?php for ($v=1;$v<=5;$v++): ?>
          <td>
            <input type="radio" name="<?=$name?>" id="<?=$name?>_<?=$v?>" value="<?=$v?>">
            <label for="<?=$name?>_<?=$v?>"><?=$v?></label>
          </td>
          <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="scale-labels">
      <span>น้อยที่สุด</span>
      <span class="scale-label-end">มากที่สุด</span>
    </div>
  </div>

  <!-- ภาพรวม -->
  <div class="card" id="sec-overall">
    <div class="section-header">
      <div class="section-num">★</div>
      <div>
        <div class="section-title">ความพึงพอใจโดยภาพรวม</div>
        <div class="section-sub">คลิกดาวเพื่อให้คะแนน (1–5 ดาว)</div>
      </div>
    </div>
    <div class="star-wrap" id="starWrap">
      <?php for ($v=5;$v>=1;$v--): ?>
      <input type="radio" name="overall" id="star<?=$v?>" value="<?=$v?>">
      <label for="star<?=$v?>" title="<?=$v?> ดาว">★</label>
      <?php endfor; ?>
    </div>
    <div style="text-align:center;font-size:.8rem;color:var(--muted);margin-top:10px" id="starLabel">ยังไม่ได้เลือก</div>
    <div class="field-error" id="err-overall">กรุณาให้คะแนนความพึงพอใจโดยภาพรวม</div>
  </div>

  <!-- ข้อเสนอแนะ -->
  <div class="card">
    <div class="section-header">
      <div class="section-num">💬</div>
      <div>
        <div class="section-title">ข้อเสนอแนะเพิ่มเติม</div>
        <div class="section-sub">ไม่บังคับ — ความคิดเห็นของท่านมีคุณค่าอย่างยิ่ง</div>
      </div>
    </div>
    <textarea name="suggestion" placeholder="พิมพ์ข้อเสนอแนะหรือความคิดเห็นของท่านที่นี่..." maxlength="1000"></textarea>
  </div>

  <!-- Submit -->
  <div class="card" style="text-align:center">
    <button type="submit" class="btn-submit" id="submitBtn">📤 ส่งแบบประเมิน</button>
    <div class="form-note">ข้อมูลของท่านจะถูกเก็บเป็นความลับและนำไปใช้เพื่อพัฒนาคุณภาพการให้บริการเท่านั้น</div>
  </div>

</form>
<?php endif; ?>
</div>

<script>
const starLabels = ['','น้อยที่สุด','น้อย','ปานกลาง','ดี','ดีมาก'];
document.querySelectorAll('input[name="overall"]').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('starLabel').textContent = starLabels[r.value] + ' (' + r.value + ' ดาว)';
  });
});

document.getElementById('surveyForm').addEventListener('submit', function(e) {
  let valid = true;

  // Check type
  const typeChecked = document.querySelector('input[name="respondent_type"]:checked');
  const secType = document.getElementById('sec-type');
  if (!typeChecked) {
    secType.classList.add('has-error');
    valid = false;
  } else {
    secType.classList.remove('has-error');
  }

  // Check overall
  const overallChecked = document.querySelector('input[name="overall"]:checked');
  const secOverall = document.getElementById('sec-overall');
  if (!overallChecked) {
    secOverall.classList.add('has-error');
    valid = false;
  } else {
    secOverall.classList.remove('has-error');
  }

  if (!valid) {
    e.preventDefault();
    document.querySelector('.has-error').scrollIntoView({behavior:'smooth',block:'center'});
    return;
  }

  const btn = document.getElementById('submitBtn');
  btn.textContent = 'กำลังส่ง...';
  btn.disabled = true;
});
</script>
</body>
</html>
