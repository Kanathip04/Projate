<?php
$pageTitle  = "ผลการประเมินความพึงพอใจ";
$activeMenu = "survey";
require_once 'config.php';
require_once 'admin_layout_top.php';

// Filters
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to']   ?? '');
$type     = trim($_GET['type'] ?? '');

$where = [];
if ($dateFrom) $where[] = "DATE(created_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo)   $where[] = "DATE(created_at) <= '" . $conn->real_escape_string($dateTo) . "'";
if ($type)     $where[] = "respondent_type = '" . $conn->real_escape_string($type) . "'";
$sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Summary stats
$statRes = $conn->query("SELECT
  COUNT(*) AS total,
  ROUND(AVG(overall),2) AS avg_overall,
  ROUND(AVG((COALESCE(q1_1,0)+COALESCE(q1_2,0)+COALESCE(q1_3,0)+COALESCE(q1_4,0)+COALESCE(q1_5,0))/5),2) AS avg_s1,
  ROUND(AVG((COALESCE(q2_1,0)+COALESCE(q2_2,0)+COALESCE(q2_3,0)+COALESCE(q2_4,0))/4),2) AS avg_s2,
  ROUND(AVG((COALESCE(q3_1,0)+COALESCE(q3_2,0)+COALESCE(q3_3,0))/3),2) AS avg_s3
  FROM survey_responses $sql")->fetch_assoc();

// Type breakdown
$typeRes = $conn->query("SELECT respondent_type, COUNT(*) as cnt FROM survey_responses $sql GROUP BY respondent_type ORDER BY cnt DESC");
$typeData = [];
while ($r = $typeRes->fetch_assoc()) $typeData[] = $r;

// Star distribution
$starRes = $conn->query("SELECT overall, COUNT(*) as cnt FROM survey_responses $sql GROUP BY overall ORDER BY overall DESC");
$starData = [];
while ($r = $starRes->fetch_assoc()) $starData[$r['overall']] = (int)$r['cnt'];

// Recent responses (paginated)
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$total  = (int)$conn->query("SELECT COUNT(*) FROM survey_responses $sql")->fetch_row()[0];
$pages  = max(1, ceil($total / $limit));
$rows   = $conn->query("SELECT * FROM survey_responses $sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

$typeLabels = [
  'student'    => 'นักศึกษา',
  'staff'      => 'บุคลากร MSU',
  'researcher' => 'นักวิจัย',
  'tourist'    => 'นักท่องเที่ยว',
  'public'     => 'ประชาชนทั่วไป',
  'other'      => 'อื่นๆ',
];

function scoreBar($val, $max=5) {
    $pct = $val ? round(($val/$max)*100) : 0;
    $color = $pct >= 80 ? '#2e7d32' : ($pct >= 60 ? '#c9a96e' : '#c0392b');
    return "<div style='display:flex;align-items:center;gap:8px'>
      <div style='flex:1;height:8px;background:#eee;border-radius:4px;overflow:hidden'>
        <div style='height:100%;width:{$pct}%;background:{$color};border-radius:4px;transition:.3s'></div>
      </div>
      <span style='font-size:.8rem;font-weight:600;color:{$color};min-width:28px'>{$val}</span>
    </div>";
}
?>
<style>
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px 22px}
.stat-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:6px}
.stat-value{font-size:2rem;font-weight:700;color:var(--ink)}
.stat-sub{font-size:.76rem;color:var(--muted);margin-top:4px}
.stars-display{color:#c9a96e;font-size:1.1rem;letter-spacing:2px}

.filter-bar{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px 22px;margin-bottom:24px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:5px}
.filter-group label{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}
.filter-group select,.filter-group input{padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);background:#fafaf8;outline:none}
.filter-group select:focus,.filter-group input:focus{border-color:var(--accent)}
.btn-filter{padding:9px 20px;background:var(--ink);color:#fff;border:none;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.88rem;cursor:pointer;align-self:flex-end;transition:.2s}
.btn-filter:hover{background:#2a2a4e}
.btn-reset{padding:9px 16px;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.88rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;transition:.2s}
.btn-reset:hover{border-color:var(--ink);color:var(--ink)}

.section-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px 24px;margin-bottom:24px}
.section-card-title{font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border)}

.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
@media(max-width:640px){.two-col{grid-template-columns:1fr}}

.type-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:.88rem}
.type-row-label{min-width:110px;color:var(--ink)}
.type-row-bar{flex:1;height:10px;background:#eee;border-radius:5px;overflow:hidden}
.type-row-fill{height:100%;background:var(--ink);border-radius:5px}
.type-row-cnt{min-width:28px;text-align:right;color:var(--muted);font-size:.82rem}

.star-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:.88rem}
.star-row-label{min-width:60px;color:#c9a96e}
.star-row-bar{flex:1;height:10px;background:#eee;border-radius:5px;overflow:hidden}
.star-row-fill{height:100%;background:#c9a96e;border-radius:5px}
.star-row-cnt{min-width:28px;text-align:right;color:var(--muted);font-size:.82rem}

.data-table{width:100%;border-collapse:collapse;font-size:.85rem}
.data-table th{background:var(--ink);color:var(--accent);padding:10px 12px;text-align:left;font-weight:600;font-size:.75rem;letter-spacing:.05em;text-transform:uppercase}
.data-table th:first-child{border-radius:8px 0 0 0}
.data-table th:last-child{border-radius:0 8px 0 0}
.data-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover td{background:rgba(201,169,110,.05)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
.badge-student{background:#dbeafe;color:#1e40af}
.badge-staff{background:#fef9c3;color:#854d0e}
.badge-researcher{background:#f0fdf4;color:#166534}
.badge-tourist{background:#fce7f3;color:#9d174d}
.badge-public{background:#f3f4f6;color:#374151}
.badge-other{background:#ede9fe;color:#5b21b6}

.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px;flex-wrap:wrap}
.pagination a,.pagination span{padding:7px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;text-decoration:none;color:var(--ink);transition:.2s}
.pagination a:hover{border-color:var(--accent);color:var(--accent)}
.pagination .active{background:var(--ink);color:var(--accent);border-color:var(--ink)}

.suggestion-cell{max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:.82rem;cursor:pointer}
.suggestion-cell:hover{color:var(--ink)}
</style>

<!-- Filter -->
<form method="GET" class="filter-bar">
  <div class="filter-group">
    <label>วันที่เริ่มต้น</label>
    <input type="date" name="from" value="<?=htmlspecialchars($dateFrom)?>">
  </div>
  <div class="filter-group">
    <label>วันที่สิ้นสุด</label>
    <input type="date" name="to" value="<?=htmlspecialchars($dateTo)?>">
  </div>
  <div class="filter-group">
    <label>ประเภทผู้ประเมิน</label>
    <select name="type">
      <option value="">ทั้งหมด</option>
      <?php foreach ($typeLabels as $val => $lbl): ?>
      <option value="<?=$val?>" <?=$type===$val?'selected':''?>><?=$lbl?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn-filter">🔍 กรอง</button>
  <a href="admin_survey.php" class="btn-reset">✕ รีเซ็ต</a>
</form>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">จำนวนผู้ประเมินทั้งหมด</div>
    <div class="stat-value"><?= number_format($statRes['total']) ?></div>
    <div class="stat-sub">คน</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">ความพึงพอใจโดยภาพรวม</div>
    <div class="stat-value"><?= $statRes['avg_overall'] ?? '—' ?></div>
    <div class="stat-sub">
      <span class="stars-display"><?= str_repeat('★', round($statRes['avg_overall'] ?? 0)) ?><?= str_repeat('☆', 5 - round($statRes['avg_overall'] ?? 0)) ?></span>
      / 5.00
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-label">ด้านการให้บริการ</div>
    <div class="stat-value"><?= $statRes['avg_s1'] ?? '—' ?></div>
    <div class="stat-sub">คะแนนเฉลี่ย / 5.00</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">ด้านสถานที่</div>
    <div class="stat-value"><?= $statRes['avg_s2'] ?? '—' ?></div>
    <div class="stat-sub">คะแนนเฉลี่ย / 5.00</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">ด้านระบบสารสนเทศ</div>
    <div class="stat-value"><?= $statRes['avg_s3'] ?? '—' ?></div>
    <div class="stat-sub">คะแนนเฉลี่ย / 5.00</div>
  </div>
</div>

<!-- Charts row -->
<div class="two-col">
  <!-- Type breakdown -->
  <div class="section-card">
    <div class="section-card-title">📊 ประเภทผู้ประเมิน</div>
    <?php if ($typeData): ?>
      <?php $maxCnt = max(array_column($typeData,'cnt')); ?>
      <?php foreach ($typeData as $t): ?>
      <div class="type-row">
        <div class="type-row-label"><?= $typeLabels[$t['respondent_type']] ?? $t['respondent_type'] ?></div>
        <div class="type-row-bar">
          <div class="type-row-fill" style="width:<?= round($t['cnt']/$maxCnt*100) ?>%"></div>
        </div>
        <div class="type-row-cnt"><?= $t['cnt'] ?></div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="color:var(--muted);font-size:.88rem">ยังไม่มีข้อมูล</p>
    <?php endif; ?>
  </div>

  <!-- Star distribution -->
  <div class="section-card">
    <div class="section-card-title">⭐ การกระจายคะแนนความพึงพอใจ</div>
    <?php
    $totalStar = array_sum($starData);
    for ($s = 5; $s >= 1; $s--):
      $cnt = $starData[$s] ?? 0;
      $pct = $totalStar ? round($cnt/$totalStar*100) : 0;
    ?>
    <div class="star-row">
      <div class="star-row-label"><?= str_repeat('★',$s) ?></div>
      <div class="star-row-bar">
        <div class="star-row-fill" style="width:<?=$pct?>%"></div>
      </div>
      <div class="star-row-cnt"><?= $cnt ?></div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- Detail table -->
<div class="section-card">
  <div class="section-card-title">📋 รายการประเมินทั้งหมด (<?= number_format($total) ?> รายการ)</div>
  <div style="overflow-x:auto">
  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>วันที่/เวลา</th>
        <th>ประเภท</th>
        <th>บริการ</th>
        <th>สถานที่</th>
        <th>ระบบ</th>
        <th>ภาพรวม</th>
        <th>ข้อเสนอแนะ</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($total === 0): ?>
    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:32px">ยังไม่มีข้อมูลการประเมิน</td></tr>
    <?php else: ?>
    <?php while ($r = $rows->fetch_assoc()):
      $s1avg = round(array_sum([$r['q1_1'],$r['q1_2'],$r['q1_3'],$r['q1_4'],$r['q1_5']])/5, 1);
      $s2avg = round(array_sum([$r['q2_1'],$r['q2_2'],$r['q2_3'],$r['q2_4']])/4, 1);
      $s3avg = round(array_sum([$r['q3_1'],$r['q3_2'],$r['q3_3']])/3, 1);
      $dt = new DateTime($r['created_at']);
      $thYear = (int)$dt->format('Y') + 543;
      $dateStr = $dt->format('d/m/') . $thYear . ' ' . $dt->format('H:i');
      $badgeClass = 'badge-' . ($r['respondent_type'] ?? 'other');
    ?>
    <tr>
      <td style="color:var(--muted)"><?= $r['id'] ?></td>
      <td style="white-space:nowrap"><?= $dateStr ?></td>
      <td><span class="badge <?=$badgeClass?>"><?= $typeLabels[$r['respondent_type']] ?? $r['respondent_type'] ?></span></td>
      <td><?= scoreBar($s1avg) ?></td>
      <td><?= scoreBar($s2avg) ?></td>
      <td><?= scoreBar($s3avg) ?></td>
      <td style="text-align:center">
        <span style="color:#c9a96e;font-size:.9rem"><?= str_repeat('★',$r['overall']) ?><?= str_repeat('☆',5-$r['overall']) ?></span>
      </td>
      <td class="suggestion-cell" title="<?= htmlspecialchars($r['suggestion'] ?? '') ?>">
        <?= htmlspecialchars($r['suggestion'] ?? '—') ?>
      </td>
    </tr>
    <?php endwhile; ?>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $pages; $p++):
      $q = http_build_query(array_merge($_GET, ['page' => $p]));
    ?>
    <?php if ($p === $page): ?>
      <span class="active"><?= $p ?></span>
    <?php else: ?>
      <a href="?<?=$q?>"><?=$p?></a>
    <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once 'admin_layout_bottom.php'; ?>
