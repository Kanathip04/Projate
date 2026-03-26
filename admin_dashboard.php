<?php
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$pageTitle  = "Dashboard";
$activeMenu = "dashboard";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where  = "WHERE visit_date = CURDATE()";

if ($search !== '') {
    $safe   = $conn->real_escape_string($search);
    $where .= " AND (nickname LIKE '%$safe%' OR user_type LIKE '%$safe%')";
}

$sql    = "SELECT id, nickname, user_type, visit_date, visit_time, created_at FROM tourists $where ORDER BY id DESC";
$result = $conn->query($sql);
if (!$result) die("SQL Error: " . $conn->error);

$count_sql    = "SELECT user_type, COUNT(*) as total FROM tourists WHERE visit_date = CURDATE() GROUP BY user_type";
$count_result = $conn->query($count_sql);

$type_counts  = [];
$chart_labels = [];
$chart_data   = [];
$total_today  = 0;

if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        $type = $row['user_type']; $cnt = (int)$row['total'];
        $type_counts[$type] = $cnt;
        $chart_labels[]     = $type;
        $chart_data[]       = $cnt;
        $total_today       += $cnt;
    }
}

include "admin_layout_top.php";
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ── Dashboard grid ── */
.dash-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
  animation: fadeUp 0.5s both;
}
.dash-grid .stat-card:nth-child(1) { animation-delay: 0.05s; }
.dash-grid .stat-card:nth-child(2) { animation-delay: 0.10s; }
.dash-grid .stat-card:nth-child(3) { animation-delay: 0.15s; }
.dash-grid .stat-card:nth-child(4) { animation-delay: 0.20s; }

@keyframes fadeUp {
  from { opacity:0; transform:translateY(14px); }
  to   { opacity:1; transform:translateY(0); }
}

/* ── Two-column layout ── */
.mid-grid {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 20px;
  margin-bottom: 24px;
  animation: fadeUp 0.5s 0.1s both;
}

/* ── Chart ── */
.chart-container {
  position: relative;
  height: 260px;
  display: flex; align-items: center; justify-content: center;
}

/* ── Type breakdown ── */
.type-list { display: flex; flex-direction: column; gap: 10px; padding: 4px 0; }
.type-row  { display: flex; align-items: center; justify-content: space-between; }
.type-bar-wrap { flex: 1; margin: 0 12px; height: 6px; background: #f0ece4; border-radius: 4px; overflow: hidden; }
.type-bar { height: 100%; border-radius: 4px; transition: width 0.8s cubic-bezier(.23,1,.32,1); }
.type-label { font-size: 0.78rem; color: var(--muted); min-width: 60px; }
.type-count { font-size: 0.82rem; font-weight: 700; color: var(--ink); min-width: 36px; text-align: right; }

/* ── Table section ── */
.table-section { animation: fadeUp 0.5s 0.2s both; }

.table-toolbar {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
}

.live-dot {
  display: inline-block; width: 7px; height: 7px;
  background: #22c55e; border-radius: 50%; margin-right: 6px;
  box-shadow: 0 0 0 3px rgba(34,197,94,0.2);
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0%,100% { box-shadow: 0 0 0 3px rgba(34,197,94,0.2); }
  50%      { box-shadow: 0 0 0 6px rgba(34,197,94,0.08); }
}

.lm-table thead th:nth-child(1),
.lm-table tbody td:nth-child(1) { width: 50px; text-align: center; color: var(--muted); }
.lm-table thead th:nth-child(4),
.lm-table tbody td:nth-child(4) { width: 100px; text-align: center; }
.lm-table thead th:nth-child(5),
.lm-table tbody td:nth-child(5) { width: 100px; text-align: center; }

.empty-row { text-align: center; color: var(--muted); font-size: 0.85rem; padding: 32px !important; }

@media (max-width: 1100px) {
  .mid-grid { grid-template-columns: 1fr; }
  .chart-container { height: 220px; }
}
</style>

<!-- ── Stat cards ── -->
<div class="dash-grid">
  <div class="stat-card">
    <div class="stat-label">ผู้ลงทะเบียนวันนี้</div>
    <div class="stat-value"><?= (int)$total_today ?></div>
    <div class="stat-unit">คน</div>
  </div>
  <?php
  $colors = ['#1565c0','#e65100','#2e7d32','#6a1b9a'];
  $i = 0;
  foreach ($type_counts as $type => $cnt):
    $c = $colors[$i % count($colors)]; $i++;
  ?>
  <div class="stat-card" style="border-top-color:<?= $c ?>">
    <div class="stat-label"><?= htmlspecialchars($type) ?></div>
    <div class="stat-value" style="color:<?= $c ?>"><?= (int)$cnt ?></div>
    <div class="stat-unit">คน</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Chart + breakdown ── -->
<div class="mid-grid">
  <div class="lm-card">
    <div class="lm-card-header">
      <span class="lm-card-title">สัดส่วนประเภทผู้เยี่ยมชม</span>
      <span class="badge badge-gold">วันนี้</span>
    </div>
    <div class="lm-card-body">
      <div class="chart-container">
        <?php if ($total_today > 0): ?>
          <canvas id="donutChart"></canvas>
        <?php else: ?>
          <div style="text-align:center;color:var(--muted);font-size:.85rem;">ยังไม่มีข้อมูลวันนี้</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="lm-card">
    <div class="lm-card-header">
      <span class="lm-card-title">รายละเอียดตามประเภท</span>
    </div>
    <div class="lm-card-body">
      <?php if (!empty($type_counts)): ?>
        <div class="type-list">
          <?php
          $barColors = ['#1565c0','#e65100','#2e7d32','#6a1b9a'];
          $j = 0;
          foreach ($type_counts as $type => $cnt):
            $pct = $total_today > 0 ? round($cnt / $total_today * 100) : 0;
            $bc  = $barColors[$j % count($barColors)]; $j++;
          ?>
          <div>
            <div class="type-row" style="margin-bottom:4px;">
              <span class="type-label"><?= htmlspecialchars($type) ?></span>
              <span style="font-size:.7rem;color:var(--muted)"><?= $pct ?>%</span>
              <span class="type-count"><?= $cnt ?> คน</span>
            </div>
            <div class="type-bar-wrap">
              <div class="type-bar" style="width:<?= $pct ?>%;background:<?= $bc ?>"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align:center;color:var(--muted);font-size:.85rem;padding:40px 0">ยังไม่มีข้อมูล</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Table ── -->
<div class="table-section">
  <div class="lm-card">
    <div class="lm-card-header">
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="live-dot"></span>
        <span class="lm-card-title">รายการเข้าชมวันนี้</span>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
          <div class="search-wrap">
            <input type="text" name="search" placeholder="ค้นหา..."
                   value="<?= htmlspecialchars($search) ?>"/>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
          <?php if ($search): ?>
            <a href="admin_dashboard.php" class="btn btn-ghost btn-sm">ล้าง</a>
          <?php endif; ?>
        </form>
        <a href="archive_today.php" class="btn btn-accent btn-sm"
           onclick="return confirm('ต้องการปิดวันหรือไม่?')">ปิดวัน</a>
      </div>
    </div>

    <div style="overflow-x:auto;">
      <table class="lm-table">
        <thead>
          <tr>
            <th>#</th>
            <th>ชื่อเล่น</th>
            <th>ประเภท</th>
            <th>เวลา</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody id="liveTable">
          <?php if ($result && $result->num_rows > 0): ?>
            <?php $n = 1; while ($row = $result->fetch_assoc()): ?>
              <?php
              $badgeClass = ($row['user_type'] == 'นักศึกษา') ? 'badge-blue'
                          : (($row['user_type'] == 'บุคลากร') ? 'badge-orange' : 'badge-green');
              $t = '-';
              if (!empty($row['created_at'])) {
                $ts = strtotime($row['created_at']);
                if ($ts) $t = date('H:i', $ts);
              } elseif (!empty($row['visit_time'])) {
                $ts2 = strtotime($row['visit_time']);
                if ($ts2) $t = date('H:i', $ts2);
              }
              ?>
              <tr>
                <td><?= $n++ ?></td>
                <td><strong><?= htmlspecialchars($row['nickname']) ?></strong></td>
                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['user_type']) ?></span></td>
                <td><?= $t ?></td>
                <td>
                  <a class="btn btn-danger btn-sm"
                     href="delete_tourist.php?id=<?= (int)$row['id'] ?>"
                     onclick="return confirm('ยืนยันการลบ?')">ลบ</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="empty-row">ยังไม่มีข้อมูลสำหรับวันนี้</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
<?php if ($total_today > 0): ?>
new Chart(document.getElementById('donutChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($chart_labels) ?>,
    datasets: [{
      data: <?= json_encode($chart_data) ?>,
      backgroundColor: ['#1565c0','#e65100','#2e7d32','#6a1b9a'],
      borderWidth: 3,
      borderColor: '#ffffff',
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '72%',
    plugins: {
      legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 12 }, padding: 14, usePointStyle: true } }
    }
  }
});
<?php endif; ?>

// Live refresh ทุก 5 วินาที
setInterval(() => {
  const params = new URLSearchParams(window.location.search);
  const url = 'fetch_today.php' + (params.toString() ? '?' + params.toString() : '');
  fetch(url).then(r => r.text()).then(html => {
    const el = document.getElementById('liveTable');
    if (el) el.innerHTML = html;
  });
}, 5000);
</script>

<?php
$conn->close();
include "admin_layout_bottom.php";
?>