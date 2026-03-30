<?php
$pageTitle  = "กราฟรายงาน";
$activeMenu = "charts";
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
require_once 'admin_layout_top.php';

date_default_timezone_set('Asia/Bangkok');

// ── Parameters ──
$view      = $_GET['view']  ?? 'daily';    // daily | monthly | yearly
$today     = date('Y-m-d');
$thisMonth = date('Y-m');
$thisYear  = (int)date('Y');

if ($view === 'daily') {
    $dateParam  = $_GET['date']  ?? $today;
    $dateParam  = date('Y-m-d', strtotime($dateParam));

    // build 7-day window ending on dateParam
    $winEnd   = $dateParam;
    $winStart = date('Y-m-d', strtotime('-6 days', strtotime($dateParam)));
    $prevUrl  = '?view=daily&date=' . date('Y-m-d', strtotime('-1 day', strtotime($dateParam)));
    $nextUrl  = '?view=daily&date=' . date('Y-m-d', strtotime('+1 day', strtotime($dateParam)));
    $todayUrl = '?view=daily&date=' . $today;
    $isCurrent = ($dateParam === $today);
    $periodLabel = 'วันที่ ' . date('d/m/Y', strtotime($dateParam));
    $subLabel    = '7 วันล่าสุด ณ วันนี้';
} elseif ($view === 'monthly') {
    $monthParam = $_GET['month'] ?? $thisMonth;
    $monthParam = date('Y-m', strtotime($monthParam . '-01'));

    $winStart   = $monthParam . '-01';
    $winEnd     = date('Y-m-t', strtotime($winStart));
    $prevUrl    = '?view=monthly&month=' . date('Y-m', strtotime('-1 month', strtotime($winStart)));
    $nextUrl    = '?view=monthly&month=' . date('Y-m', strtotime('+1 month', strtotime($winStart)));
    $todayUrl   = '?view=monthly&month=' . $thisMonth;
    $isCurrent  = ($monthParam === $thisMonth);
    $periodLabel = 'เดือน ' . date('m/Y', strtotime($winStart));
    $subLabel    = 'รายวันในเดือนนี้';
} else {
    $yearParam  = (int)($_GET['year'] ?? $thisYear);
    if ($yearParam < 2020) $yearParam = 2020;
    if ($yearParam > 2100) $yearParam = 2100;

    $winStart   = $yearParam . '-01-01';
    $winEnd     = $yearParam . '-12-31';
    $prevUrl    = '?view=yearly&year=' . ($yearParam - 1);
    $nextUrl    = '?view=yearly&year=' . ($yearParam + 1);
    $todayUrl   = '?view=yearly&year=' . $thisYear;
    $isCurrent  = ($yearParam === $thisYear);
    $periodLabel = 'ปี ' . ($yearParam + 543);
    $subLabel    = 'รายเดือนในปีนี้';
}

// ── Build timeline chart data ──
$tlLabels = $tlBoat = $tlRoom = $tlTent = $tlRevenue = [];

if ($view === 'daily') {
    // last 7 days ending on $dateParam
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days", strtotime($dateParam)));
        $tlLabels[]  = "'" . date('d/m', strtotime($d)) . "'";
        $tlBoat[]    = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at)='$d' AND archived=0")->fetch_assoc()['c'];
        $tlRoom[]    = (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at)='$d' AND archived=0")->fetch_assoc()['c'];
        $tlTent[]    = (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at)='$d' AND archived=0")->fetch_assoc()['c'];
        $tlRevenue[] = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at)='$d' AND payment_status='paid' AND archived=0")->fetch_assoc()['s'];
    }
} elseif ($view === 'monthly') {
    $days = (int)date('t', strtotime($winStart));
    for ($d = 1; $d <= $days; $d++) {
        $dt = date('Y-m-', strtotime($winStart)) . str_pad($d, 2, '0', STR_PAD_LEFT);
        $tlLabels[]  = "'$d'";
        $tlBoat[]    = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at)='$dt' AND archived=0")->fetch_assoc()['c'];
        $tlRoom[]    = (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at)='$dt' AND archived=0")->fetch_assoc()['c'];
        $tlTent[]    = (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at)='$dt' AND archived=0")->fetch_assoc()['c'];
        $tlRevenue[] = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at)='$dt' AND payment_status='paid' AND archived=0")->fetch_assoc()['s'];
    }
} else {
    for ($m = 1; $m <= 12; $m++) {
        $mm     = str_pad($m, 2, '0', STR_PAD_LEFT);
        $mStart = "$yearParam-{$mm}-01";
        $mEnd   = date('Y-m-t', strtotime($mStart));
        $thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        $tlLabels[]  = "'{$thMonths[$m-1]}'";
        $tlBoat[]    = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND archived=0")->fetch_assoc()['c'];
        $tlRoom[]    = (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND archived=0")->fetch_assoc()['c'];
        $tlTent[]    = (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND archived=0")->fetch_assoc()['c'];
        $tlRevenue[] = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND payment_status='paid' AND archived=0")->fetch_assoc()['s'];
    }
}

// ── Totals for the full period ──
$boatTotal = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '$winStart' AND '$winEnd' AND archived=0")->fetch_assoc()['c'];
$roomTotal = (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at) BETWEEN '$winStart' AND '$winEnd' AND archived=0")->fetch_assoc()['c'];
$tentTotal = (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at) BETWEEN '$winStart' AND '$winEnd' AND archived=0")->fetch_assoc()['c'];
$allTotal  = $boatTotal + $roomTotal + $tentTotal;

$boatPaid  = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '$winStart' AND '$winEnd' AND payment_status='paid' AND archived=0")->fetch_assoc()['c'];
$boatRev   = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at) BETWEEN '$winStart' AND '$winEnd' AND payment_status='paid' AND archived=0")->fetch_assoc()['s'];
$visitors  = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE DATE(visit_date) BETWEEN '$winStart' AND '$winEnd'")->fetch_assoc()['c'];

// ── Payment status breakdown (boat) ──
$payBreak = $conn->query("SELECT payment_status, COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '$winStart' AND '$winEnd' AND archived=0 GROUP BY payment_status")->fetch_all(MYSQLI_ASSOC);
$payMap = [];
foreach ($payBreak as $pb) $payMap[$pb['payment_status']] = (int)$pb['c'];
$pPaid    = $payMap['paid'] ?? 0;
$pWait    = ($payMap['waiting_verify'] ?? 0) + ($payMap['pending'] ?? 0);
$pUnpaid  = $payMap['unpaid'] ?? 0;
$pFailed  = $payMap['failed'] ?? 0;

// ── Comparison (previous period) ──
if ($view === 'daily') {
    $prevStart = $prevEnd = date('Y-m-d', strtotime('-1 day', strtotime($dateParam)));
} elseif ($view === 'monthly') {
    $prevMonthStart = date('Y-m', strtotime('-1 month', strtotime($winStart))) . '-01';
    $prevStart = $prevMonthStart;
    $prevEnd   = date('Y-m-t', strtotime($prevMonthStart));
} else {
    $prevStart = ($yearParam - 1) . '-01-01';
    $prevEnd   = ($yearParam - 1) . '-12-31';
}
$prevAll = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '$prevStart' AND '$prevEnd' AND archived=0")->fetch_assoc()['c']
         + (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at) BETWEEN '$prevStart' AND '$prevEnd' AND archived=0")->fetch_assoc()['c']
         + (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at) BETWEEN '$prevStart' AND '$prevEnd' AND archived=0")->fetch_assoc()['c'];
$prevRev = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at) BETWEEN '$prevStart' AND '$prevEnd' AND payment_status='paid' AND archived=0")->fetch_assoc()['s'];

function pctChange(float $cur, float $prev): array {
    if ($prev == 0) { $p = $cur > 0 ? 100 : 0; }
    else { $p = round(($cur - $prev) / $prev * 100, 1); }
    return ['val' => ($p >= 0 ? '+' : '') . $p . '%', 'cls' => $p > 0 ? 'up' : ($p < 0 ? 'down' : 'flat')];
}
$bkChg  = pctChange($allTotal, $prevAll);
$revChg = pctChange($boatRev, $prevRev);

$jsLabels  = implode(',', $tlLabels);
$jsBoat    = implode(',', $tlBoat);
$jsRoom    = implode(',', $tlRoom);
$jsTent    = implode(',', $tlTent);
$jsRevenue = implode(',', $tlRevenue);
?>

<style>
/* ── Tab bar ── */
.view-tabs{display:flex;gap:0;background:#fff;border-radius:14px;padding:5px;
  box-shadow:0 2px 12px rgba(26,26,46,.07);margin-bottom:16px;width:fit-content;}
.vtab{padding:9px 24px;border-radius:10px;font-family:'Sarabun',sans-serif;
  font-size:.85rem;font-weight:700;border:none;background:transparent;
  color:var(--muted);cursor:pointer;text-decoration:none;transition:.2s;white-space:nowrap;}
.vtab:hover{color:var(--ink);}
.vtab.active{background:var(--ink);color:#fff;box-shadow:0 3px 10px rgba(26,26,46,.2);}

/* ── Period nav ── */
.pnav{background:#fff;border-radius:14px;padding:14px 20px;
  box-shadow:0 2px 12px rgba(26,26,46,.07);display:flex;align-items:center;
  gap:14px;margin-bottom:14px;}
.pnav-arr{width:38px;height:38px;border-radius:50%;border:1.5px solid var(--border);
  background:#fafaf8;display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;text-decoration:none;color:var(--ink);transition:.15s;flex-shrink:0;}
.pnav-arr:hover{border-color:var(--accent);color:var(--accent);}
.pnav-mid{flex:1;text-align:center;}
.pnav-title{font-size:1.1rem;font-weight:800;color:var(--ink);}
.pnav-hint{font-size:.7rem;color:var(--muted);margin-top:2px;}
.pnav-back{padding:7px 16px;border-radius:20px;border:1.5px solid var(--accent);
  background:#fffbf5;color:var(--accent);font-family:'Sarabun',sans-serif;
  font-size:.78rem;font-weight:700;text-decoration:none;transition:.15s;}
.pnav-back:hover{background:var(--accent);color:var(--ink);}

/* ── Picker box ── */
.picker-box{
  background:#fff;border-radius:14px;padding:14px 18px;
  box-shadow:0 2px 12px rgba(26,26,46,.07);margin-bottom:16px;
}
.picker-form{
  display:flex;align-items:end;gap:12px;flex-wrap:wrap;
}
.picker-group{
  display:flex;flex-direction:column;gap:6px;min-width:180px;
}
.picker-label{
  font-size:.75rem;font-weight:700;color:var(--muted);
}
.picker-input, .picker-select{
  height:42px;border:1.5px solid #e5e7eb;border-radius:10px;
  padding:0 12px;font-family:'Sarabun',sans-serif;font-size:.92rem;
  color:var(--ink);background:#fff;outline:none;transition:.15s;
}
.picker-input:focus, .picker-select:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.picker-actions{
  display:flex;gap:10px;flex-wrap:wrap;
}
.picker-btn{
  height:42px;padding:0 18px;border:none;border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;font-weight:700;
  cursor:pointer;transition:.15s;text-decoration:none;display:inline-flex;
  align-items:center;justify-content:center;
}
.picker-btn.primary{
  background:var(--ink);color:#fff;
}
.picker-btn.primary:hover{
  opacity:.92;
}
.picker-btn.soft{
  background:#f8f6f1;color:var(--ink);border:1px solid #ece7dc;
}
.picker-btn.soft:hover{
  background:#f1ece2;
}

/* ── KPI row ── */
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px;}
.kpi{background:#fff;border-radius:14px;padding:16px 18px;
  box-shadow:0 2px 12px rgba(26,26,46,.07);position:relative;overflow:hidden;}
.kpi-accent{position:absolute;top:0;left:0;bottom:0;width:4px;border-radius:4px 0 0 4px;}
.kpi-ico{font-size:1.5rem;opacity:.18;position:absolute;right:12px;top:12px;}
.kpi-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
  color:var(--muted);margin-bottom:5px;}
.kpi-num{font-size:1.6rem;font-weight:900;color:var(--ink);line-height:1;}
.kpi-sub{font-size:.7rem;color:var(--muted);margin-top:3px;}
.kpi-chg{display:inline-block;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px;margin-top:4px;}
.kpi-chg.up{background:#e8f5e9;color:#2e7d32;}
.kpi-chg.down{background:#fef2f2;color:#dc2626;}
.kpi-chg.flat{background:#f5f5f5;color:var(--muted);}

/* ── Chart layout ── */
.charts-top{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
.charts-bot{display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:16px;}
.chart-card{background:#fff;border-radius:14px;padding:20px 22px;
  box-shadow:0 2px 12px rgba(26,26,46,.07);}
.chart-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
.chart-ttl{font-size:.85rem;font-weight:800;color:var(--ink);}
.chart-sub{font-size:.7rem;color:var(--muted);}
.chart-wrap{position:relative;}
.ch-tall{height:260px;}
.ch-mid{height:220px;}
.ch-sm{height:200px;}

/* ── Donut legend ── */
.donut-wrap{display:flex;gap:20px;align-items:center;}
.donut-canvas{flex-shrink:0;}
.donut-legend{flex:1;display:flex;flex-direction:column;gap:10px;}
.dl-row{display:flex;align-items:center;gap:9px;}
.dl-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
.dl-name{font-size:.8rem;color:var(--ink);font-weight:600;flex:1;}
.dl-val{font-size:.82rem;font-weight:800;color:var(--ink);}
.dl-pct{font-size:.7rem;color:var(--muted);}

/* ── Pay status bar ── */
.paybar-wrap{display:flex;flex-direction:column;gap:10px;padding-top:4px;}
.paybar-row{display:flex;flex-direction:column;gap:4px;}
.paybar-top{display:flex;justify-content:space-between;font-size:.78rem;}
.paybar-lbl{font-weight:600;color:var(--ink);}
.paybar-val{color:var(--muted);}
.paybar-track{height:8px;background:#f0f0f0;border-radius:99px;overflow:hidden;}
.paybar-fill{height:100%;border-radius:99px;transition:width .6s ease;}

@media(max-width:900px){
  .charts-top,.charts-bot{grid-template-columns:1fr;}
  .donut-wrap{flex-direction:column;}
  .picker-form{flex-direction:column;align-items:stretch;}
  .picker-group{min-width:unset;width:100%;}
}
@media print{
  .view-tabs,.pnav,.picker-box,.no-print{display:none!important;}
  .main{margin-left:0!important;width:100%!important;}
  .chart-card{box-shadow:none;border:1px solid #e0e0e0;}
}
</style>

<!-- Tab bar -->
<div class="view-tabs no-print">
  <a href="?view=daily<?= $view==='daily'  ? '&date='.urlencode($dateParam ?? $today) : '' ?>"
     class="vtab<?= $view==='daily'  ? ' active' : '' ?>">📅 รายวัน</a>
  <a href="?view=monthly<?= $view==='monthly' ? '&month='.urlencode($monthParam ?? $thisMonth) : '' ?>"
     class="vtab<?= $view==='monthly' ? ' active' : '' ?>">📆 รายเดือน</a>
  <a href="?view=yearly<?= $view==='yearly'  ? '&year='.urlencode($yearParam ?? $thisYear) : '' ?>"
     class="vtab<?= $view==='yearly' ? ' active' : '' ?>">🗓 รายปี</a>
</div>

<!-- Period navigator -->
<div class="pnav no-print">
  <a href="<?= htmlspecialchars($prevUrl) ?>" class="pnav-arr" title="ก่อนหน้า">&#8249;</a>
  <div class="pnav-mid">
    <div class="pnav-title"><?= $periodLabel ?></div>
    <div class="pnav-hint"><?= $subLabel ?></div>
  </div>
  <?php if (!$isCurrent): ?>
    <a href="<?= htmlspecialchars($todayUrl) ?>" class="pnav-back">↩ ปัจจุบัน</a>
  <?php endif; ?>
  <a href="<?= htmlspecialchars($nextUrl) ?>" class="pnav-arr" title="ถัดไป">&#8250;</a>
</div>

<!-- Date / Month / Year Picker -->
<div class="picker-box no-print">
  <form method="GET" class="picker-form">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">

    <?php if ($view === 'daily'): ?>
      <div class="picker-group">
        <label class="picker-label">เลือกวัน</label>
        <input type="date" name="date" class="picker-input" value="<?= htmlspecialchars($dateParam) ?>">
      </div>
    <?php elseif ($view === 'monthly'): ?>
      <div class="picker-group">
        <label class="picker-label">เลือกเดือน</label>
        <input type="month" name="month" class="picker-input" value="<?= htmlspecialchars($monthParam) ?>">
      </div>
    <?php else: ?>
      <div class="picker-group">
        <label class="picker-label">เลือกปี</label>
        <select name="year" class="picker-select">
          <?php for ($y = $thisYear + 2; $y >= 2020; $y--): ?>
            <option value="<?= $y ?>" <?= $y == $yearParam ? 'selected' : '' ?>>
              <?= $y + 543 ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="picker-actions">
      <button type="submit" class="picker-btn primary">แสดงรายงาน</button>

      <?php if ($view === 'daily'): ?>
        <a href="?view=daily&date=<?= $today ?>" class="picker-btn soft">วันนี้</a>
      <?php elseif ($view === 'monthly'): ?>
        <a href="?view=monthly&month=<?= $thisMonth ?>" class="picker-btn soft">เดือนปัจจุบัน</a>
      <?php else: ?>
        <a href="?view=yearly&year=<?= $thisYear ?>" class="picker-btn soft">ปีปัจจุบัน</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- KPI row -->
<div class="kpi-row">
  <div class="kpi">
    <div class="kpi-accent" style="background:#1d6fad;"></div>
    <div class="kpi-ico">📋</div>
    <div class="kpi-lbl">การจองทั้งหมด</div>
    <div class="kpi-num"><?= number_format($allTotal) ?></div>
    <div class="kpi-sub">รายการ</div>
    <span class="kpi-chg <?= $bkChg['cls'] ?>"><?= $bkChg['val'] ?> vs ก่อนหน้า</span>
  </div>
  <div class="kpi">
    <div class="kpi-accent" style="background:#c9a96e;"></div>
    <div class="kpi-ico">💰</div>
    <div class="kpi-lbl">รายได้ (เรือพาย)</div>
    <div class="kpi-num" style="font-size:1.25rem;">฿<?= number_format($boatRev, 0) ?></div>
    <div class="kpi-sub">บาท</div>
    <span class="kpi-chg <?= $revChg['cls'] ?>"><?= $revChg['val'] ?> vs ก่อนหน้า</span>
  </div>
  <div class="kpi">
    <div class="kpi-accent" style="background:#2e7d32;"></div>
    <div class="kpi-ico">✅</div>
    <div class="kpi-lbl">ชำระแล้ว</div>
    <div class="kpi-num"><?= number_format($boatPaid) ?></div>
    <div class="kpi-sub">รายการ (เรือ)</div>
  </div>
  <div class="kpi">
    <div class="kpi-accent" style="background:#0891b2;"></div>
    <div class="kpi-ico">👥</div>
    <div class="kpi-lbl">ผู้เข้าใช้งาน</div>
    <div class="kpi-num"><?= number_format($visitors) ?></div>
    <div class="kpi-sub">คน</div>
  </div>
</div>

<!-- Charts top row: bar + revenue line -->
<div class="charts-top">
  <div class="chart-card">
    <div class="chart-hd">
      <div>
        <div class="chart-ttl">📊 จำนวนการจองแยกประเภทบริการ</div>
        <div class="chart-sub"><?= $periodLabel ?></div>
      </div>
    </div>
    <div class="chart-wrap ch-tall"><canvas id="chartBar"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-hd">
      <div>
        <div class="chart-ttl">💵 รายได้เรือพาย</div>
        <div class="chart-sub">เฉพาะรายการที่ชำระแล้ว</div>
      </div>
    </div>
    <div class="chart-wrap ch-tall"><canvas id="chartRev"></canvas></div>
  </div>
</div>

<!-- Charts bottom row: donut + payment status -->
<div class="charts-bot">
  <div class="chart-card">
    <div class="chart-hd">
      <div>
        <div class="chart-ttl">🥧 สัดส่วนประเภทบริการ</div>
        <div class="chart-sub">รวม <?= number_format($allTotal) ?> รายการ</div>
      </div>
    </div>
    <div class="donut-wrap" style="margin-top:10px;">
      <div class="donut-canvas">
        <div class="chart-wrap ch-mid" style="width:200px;">
          <canvas id="chartDonut"></canvas>
        </div>
      </div>
      <div class="donut-legend">
        <?php
        $svcs = [
            ['เรือพาย', $boatTotal, '#1d6fad'],
            ['ห้องพัก',  $roomTotal, '#c9a96e'],
            ['เต็นท์',   $tentTotal, '#2e7d32'],
        ];
        foreach ($svcs as [$name, $cnt, $color]):
            $pct = $allTotal > 0 ? round($cnt / $allTotal * 100, 1) : 0;
        ?>
        <div class="dl-row">
          <div class="dl-dot" style="background:<?= $color ?>;"></div>
          <div class="dl-name"><?= $name ?></div>
          <div>
            <div class="dl-val"><?= number_format($cnt) ?></div>
            <div class="dl-pct"><?= $pct ?>%</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="chart-card">
    <div class="chart-hd">
      <div>
        <div class="chart-ttl">💳 สถานะการชำระเงิน</div>
        <div class="chart-sub">เรือพาย <?= $boatTotal ?> รายการ</div>
      </div>
    </div>
    <?php
    $boatTotalForBar = max($boatTotal, 1);
    $payRows = [
        ['ชำระแล้ว',    $pPaid,   '#2e7d32'],
        ['รอตรวจสอบ',  $pWait,   '#f59e0b'],
        ['ยังไม่ชำระ',  $pUnpaid, '#1d6fad'],
        ['ไม่ผ่าน',     $pFailed, '#dc2626'],
    ];
    ?>
    <div class="paybar-wrap" style="margin-top:16px;">
      <?php foreach ($payRows as [$lbl, $cnt, $col]):
        $w = round($cnt / $boatTotalForBar * 100, 1);
      ?>
      <div class="paybar-row">
        <div class="paybar-top">
          <span class="paybar-lbl"><?= $lbl ?></span>
          <span class="paybar-val"><?= $cnt ?> รายการ (<?= $w ?>%)</span>
        </div>
        <div class="paybar-track">
          <div class="paybar-fill" style="width:<?= $w ?>%;background:<?= $col ?>;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels = [<?= $jsLabels ?>];
const dBoat  = [<?= $jsBoat ?>];
const dRoom  = [<?= $jsRoom ?>];
const dTent  = [<?= $jsTent ?>];
const dRev   = [<?= $jsRevenue ?>];

const baseOpt = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } } },
};

// ── Bar chart (bookings) ──
new Chart(document.getElementById('chartBar'), {
  type: 'bar',
  data: { labels, datasets: [
    { label: 'เรือพาย', data: dBoat, backgroundColor: 'rgba(29,111,173,.75)',  borderRadius: 5 },
    { label: 'ห้องพัก', data: dRoom, backgroundColor: 'rgba(201,169,110,.75)', borderRadius: 5 },
    { label: 'เต็นท์',  data: dTent, backgroundColor: 'rgba(46,125,50,.75)',   borderRadius: 5 },
  ]},
  options: { ...baseOpt,
    scales: {
      x: { stacked: false, grid: { display: false }, ticks: { font: { size: 10 } } },
      y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 }, stepSize: 1 } },
    }
  }
});

// ── Revenue chart (line) ──
new Chart(document.getElementById('chartRev'), {
  type: 'line',
  data: { labels, datasets: [
    { label: 'รายได้ (฿)', data: dRev,
      borderColor: '#c9a96e', backgroundColor: 'rgba(201,169,110,.12)',
      fill: true, tension: 0.35, pointRadius: 4, pointBackgroundColor: '#c9a96e',
      borderWidth: 2.5 }
  ]},
  options: { ...baseOpt,
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 } } },
      y: { beginAtZero: true, grid: { color: '#f0f0f0' },
        ticks: { font: { size: 10 }, callback: v => '฿' + v.toLocaleString() } }
    }
  }
});

// ── Doughnut chart ──
new Chart(document.getElementById('chartDonut'), {
  type: 'doughnut',
  data: {
    labels: ['เรือพาย', 'ห้องพัก', 'เต็นท์'],
    datasets: [{ data: [<?= $boatTotal ?>, <?= $roomTotal ?>, <?= $tentTotal ?>],
      backgroundColor: ['rgba(29,111,173,.82)', 'rgba(201,169,110,.82)', 'rgba(46,125,50,.82)'],
      borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '68%',
    plugins: { legend: { display: false }, tooltip: { callbacks: {
      label: ctx => ` ${ctx.label}: ${ctx.parsed} รายการ`
    }}}
  }
});
</script>

<?php
require_once 'admin_layout_bottom.php';
$conn->close();
?>