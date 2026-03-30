<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$pageTitle  = "รายงานภาพรวม";
$activeMenu = "admin_report";
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
require_once 'admin_layout_top.php';

// ── Parameters ──
$reportType  = $_GET['type']    ?? 'daily';
$serviceType = $_GET['service'] ?? 'all';
$bkStatus    = $_GET['bk_status'] ?? '';
$payStatus   = $_GET['pay_status'] ?? '';

// Date range
$today = date('Y-m-d');
$thisMonth = date('Y-m');
$thisYear  = date('Y');

if ($reportType === 'daily') {
    $dateParam = $_GET['date'] ?? $today;
    $dateFrom = $dateTo = $dateParam;
    $labelRange = "วันที่ " . date('d/m/Y', strtotime($dateParam));
} elseif ($reportType === 'monthly') {
    $monthParam = $_GET['month'] ?? $thisMonth;
    $dateFrom = $monthParam . '-01';
    $dateTo   = date('Y-m-t', strtotime($dateFrom));
    $labelRange = "เดือน " . date('m/Y', strtotime($dateFrom));
} else {
    $yearParam = $_GET['year'] ?? $thisYear;
    $dateFrom  = $yearParam . '-01-01';
    $dateTo    = $yearParam . '-12-31';
    $labelRange = "ปี " . ($yearParam + 543);
}

// Helper: build WHERE clause
function dateWhere(string $col, string $from, string $to): string {
    return "DATE($col) BETWEEN '$from' AND '$to'";
}

function addFilters(string $base, string $bkStatus, string $payStatus, bool $hasPayStatus = true): string {
    if ($bkStatus)  $base .= " AND booking_status = '$bkStatus'";
    if ($payStatus && $hasPayStatus) $base .= " AND payment_status = '$payStatus'";
    return $base;
}

// ── Boat bookings ──
$boatWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0";
$boatWhere = addFilters($boatWhere, $bkStatus, $payStatus);
$boatData  = $conn->query("SELECT COUNT(*) total,
    SUM(payment_status='paid') paid,
    SUM(payment_status IN('unpaid','pending','waiting_verify')) waiting,
    SUM(booking_status='cancelled') cancelled,
    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) revenue
    FROM boat_bookings $boatWhere")->fetch_assoc();

// ── Room bookings ──
$roomWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0";
if ($bkStatus) $roomWhere .= " AND booking_status = '$bkStatus'";
$roomData  = $conn->query("SELECT COUNT(*) total,
    SUM(status='approved') paid,
    SUM(status='pending') waiting,
    SUM(booking_status='cancelled') cancelled,
    0 revenue
    FROM room_bookings $roomWhere")->fetch_assoc();

// ── Tent bookings ──
$tentWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0";
if ($bkStatus) $tentWhere .= " AND booking_status = '$bkStatus'";
$tentData  = $conn->query("SELECT COUNT(*) total,
    SUM(booking_status='approved') paid,
    SUM(booking_status='pending') waiting,
    SUM(booking_status='cancelled') cancelled,
    0 revenue
    FROM tent_bookings $tentWhere")->fetch_assoc();

// ── Visitors ──
$visitorData = $conn->query("SELECT COUNT(*) total FROM tourists
    WHERE " . dateWhere('visit_date', $dateFrom, $dateTo) . " AND archived = 0")->fetch_assoc();

// ── Finance (boat only has payment) ──
$finData = $conn->query("SELECT
    COALESCE(SUM(total_amount),0) grand_total,
    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) paid_amt,
    COALESCE(SUM(CASE WHEN payment_status IN('unpaid','pending') THEN total_amount END),0) unpaid_amt,
    COALESCE(SUM(CASE WHEN payment_status='waiting_verify' THEN total_amount END),0) waiting_amt,
    COALESCE(SUM(CASE WHEN payment_status='failed' THEN total_amount END),0) failed_amt
    FROM boat_bookings WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0")->fetch_assoc();

// ── Grand totals ──
$totalAll     = ($boatData['total'] ?? 0) + ($roomData['total'] ?? 0) + ($tentData['total'] ?? 0);
$totalPaid    = ($boatData['paid'] ?? 0) + ($roomData['paid'] ?? 0) + ($tentData['paid'] ?? 0);
$totalWaiting = ($boatData['waiting'] ?? 0) + ($roomData['waiting'] ?? 0) + ($tentData['waiting'] ?? 0);
$totalCancel  = ($boatData['cancelled'] ?? 0) + ($roomData['cancelled'] ?? 0) + ($tentData['cancelled'] ?? 0);
$totalRevenue = (float)($boatData['revenue'] ?? 0);

// ── Chart data: bookings per day/month ──
if ($reportType === 'daily') {
    $chartLabels = ["'" . date('d/m', strtotime($dateFrom)) . "'"];
    $chartBoat   = [(int)$boatData['total']];
    $chartRoom   = [(int)$roomData['total']];
    $chartTent   = [(int)$tentData['total']];
    $chartVisit  = [(int)$visitorData['total']];
} elseif ($reportType === 'monthly') {
    $days = (int)date('t', strtotime($dateFrom));
    $chartLabels = $chartBoat = $chartRoom = $chartTent = $chartVisit = [];
    for ($d = 1; $d <= $days; $d++) {
        $dt = date('Y-m-', strtotime($dateFrom)) . str_pad($d, 2, '0', STR_PAD_LEFT);
        $chartLabels[] = "'$d'";
        $chartBoat[]   = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at)='$dt' AND archived=0")->fetch_assoc()['c'];
        $chartRoom[]   = (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at)='$dt' AND archived=0")->fetch_assoc()['c'];
        $chartTent[]   = (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at)='$dt' AND archived=0")->fetch_assoc()['c'];
        $chartVisit[]  = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE visit_date='$dt' AND archived=0")->fetch_assoc()['c'];
    }
} else {
    $chartLabels = $chartBoat = $chartRoom = $chartTent = $chartVisit = [];
    for ($m = 1; $m <= 12; $m++) {
        $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
        $mStart = "{$yearParam}-{$mm}-01";
        $mEnd   = date('Y-m-t', strtotime($mStart));
        $chartLabels[] = "'" . date('M', strtotime($mStart)) . "'";
        $chartBoat[]   = (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND archived=0")->fetch_assoc()['c'];
        $chartRoom[]   = (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND archived=0")->fetch_assoc()['c'];
        $chartTent[]   = (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND archived=0")->fetch_assoc()['c'];
        $chartVisit[]  = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE visit_date BETWEEN '$mStart' AND '$mEnd' AND archived=0")->fetch_assoc()['c'];
    }
}

// Revenue chart (boat only)
$chartRevenue = [];
if ($reportType === 'monthly') {
    $days = (int)date('t', strtotime($dateFrom));
    for ($d = 1; $d <= $days; $d++) {
        $dt = date('Y-m-', strtotime($dateFrom)) . str_pad($d, 2, '0', STR_PAD_LEFT);
        $chartRevenue[] = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at)='$dt' AND payment_status='paid' AND archived=0")->fetch_assoc()['s'];
    }
} elseif ($reportType === 'yearly') {
    for ($m = 1; $m <= 12; $m++) {
        $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
        $mStart = "{$yearParam}-{$mm}-01";
        $mEnd   = date('Y-m-t', strtotime($mStart));
        $chartRevenue[] = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND payment_status='paid' AND archived=0")->fetch_assoc()['s'];
    }
} else {
    $chartRevenue[] = $totalRevenue;
}

// ── Booking list ──
$listSQL = "SELECT 'boat' svc, booking_ref ref, full_name, boat_type subtype, created_at, boat_date use_date,
    total_amount, payment_status, booking_status FROM boat_bookings
    WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0";
if ($serviceType === 'all' || $serviceType === 'boat') {
    $rows = $conn->query($listSQL . " ORDER BY created_at DESC LIMIT 50");
    $bookingRows = [];
    while ($r = $rows->fetch_assoc()) $bookingRows[] = $r;
}
if ($serviceType === 'all' || $serviceType === 'room') {
    $rRows = $conn->query("SELECT 'room' svc, CONCAT('RM',id) ref, full_name, room_type subtype, created_at, checkin_date use_date,
        0 total_amount, status payment_status, booking_status FROM room_bookings
        WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0 ORDER BY created_at DESC LIMIT 50");
    while ($r = $rRows->fetch_assoc()) $bookingRows[] = $r;
}
if ($serviceType === 'all' || $serviceType === 'tent') {
    $tRows = $conn->query("SELECT 'tent' svc, CONCAT('TN',id) ref, full_name, tent_type subtype, created_at, checkin_date use_date,
        0 total_amount, booking_status payment_status, booking_status FROM tent_bookings
        WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0 ORDER BY created_at DESC LIMIT 50");
    while ($r = $tRows->fetch_assoc()) $bookingRows[] = $r;
}
usort($bookingRows ?? [], fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));

// ── Comparison ──
$prevDay   = date('Y-m-d', strtotime('-1 day', strtotime($today)));
$prevMonth = date('Y-m', strtotime('-1 month'));
$prevYear  = (int)$thisYear - 1;

function getCount(mysqli $c, string $tbl, string $from, string $to, string $col='created_at'): int {
    return (int)$c->query("SELECT COUNT(*) n FROM $tbl WHERE DATE($col) BETWEEN '$from' AND '$to' AND archived=0")->fetch_assoc()['n'];
}
$cmp = [
    'today'      => getCount($conn,'boat_bookings',$today,$today) + getCount($conn,'room_bookings',$today,$today) + getCount($conn,'tent_bookings',$today,$today),
    'yesterday'  => getCount($conn,'boat_bookings',$prevDay,$prevDay) + getCount($conn,'room_bookings',$prevDay,$prevDay) + getCount($conn,'tent_bookings',$prevDay,$prevDay),
    'thisMonth'  => getCount($conn,'boat_bookings',date('Y-m-01'),date('Y-m-t')) + getCount($conn,'room_bookings',date('Y-m-01'),date('Y-m-t')) + getCount($conn,'tent_bookings',date('Y-m-01'),date('Y-m-t')),
    'lastMonth'  => getCount($conn,'boat_bookings',$prevMonth.'-01',date('Y-m-t',strtotime($prevMonth.'-01'))) + getCount($conn,'room_bookings',$prevMonth.'-01',date('Y-m-t',strtotime($prevMonth.'-01'))) + getCount($conn,'tent_bookings',$prevMonth.'-01',date('Y-m-t',strtotime($prevMonth.'-01'))),
    'thisYear'   => getCount($conn,'boat_bookings',$thisYear.'-01-01',$thisYear.'-12-31') + getCount($conn,'room_bookings',$thisYear.'-01-01',$thisYear.'-12-31') + getCount($conn,'tent_bookings',$thisYear.'-01-01',$thisYear.'-12-31'),
    'lastYear'   => getCount($conn,'boat_bookings',$prevYear.'-01-01',$prevYear.'-12-31') + getCount($conn,'room_bookings',$prevYear.'-01-01',$prevYear.'-12-31') + getCount($conn,'tent_bookings',$prevYear.'-01-01',$prevYear.'-12-31'),
];
function pct(int $cur, int $prev): string {
    if ($prev == 0) return $cur > 0 ? '+100%' : '0%';
    $p = round(($cur - $prev) / $prev * 100, 1);
    return ($p >= 0 ? '+' : '') . $p . '%';
}

// Most booked service
$mostBooked = 'เรือพาย';
$maxSvc = max((int)$boatData['total'], (int)$roomData['total'], (int)$tentData['total']);
if ($maxSvc == (int)$roomData['total'] && $maxSvc > 0) $mostBooked = 'ห้องพัก';
elseif ($maxSvc == (int)$tentData['total'] && $maxSvc > 0) $mostBooked = 'เต็นท์';

$jsLabels   = implode(',', $chartLabels);
$jsBoat     = implode(',', $chartBoat);
$jsRoom     = implode(',', $chartRoom);
$jsTent     = implode(',', $chartTent);
$jsVisit    = implode(',', $chartVisit);
$jsRevenue  = implode(',', $chartRevenue);

// ── Period navigation URLs ──
$yesterday = date('Y-m-d', strtotime('-1 day'));
if ($reportType === 'daily') {
    $prevNavUrl  = '?type=daily&date=' . date('Y-m-d', strtotime('-1 day', strtotime($dateParam)));
    $nextNavUrl  = '?type=daily&date=' . date('Y-m-d', strtotime('+1 day', strtotime($dateParam)));
    $todayNavUrl = '?type=daily&date=' . $today;
    $isCurrentPeriod = ($dateParam === $today);
} elseif ($reportType === 'monthly') {
    $prevNavUrl  = '?type=monthly&month=' . date('Y-m', strtotime('-1 month', strtotime(($monthParam ?? $thisMonth) . '-01')));
    $nextNavUrl  = '?type=monthly&month=' . date('Y-m', strtotime('+1 month', strtotime(($monthParam ?? $thisMonth) . '-01')));
    $todayNavUrl = '?type=monthly&month=' . $thisMonth;
    $isCurrentPeriod = (($monthParam ?? $thisMonth) === $thisMonth);
} else {
    $yp = (int)($yearParam ?? $thisYear);
    $prevNavUrl  = '?type=yearly&year=' . ($yp - 1);
    $nextNavUrl  = '?type=yearly&year=' . ($yp + 1);
    $todayNavUrl = '?type=yearly&year=' . $thisYear;
    $isCurrentPeriod = ($yp === (int)$thisYear);
}

// Quick nav shortcuts
$qnavLinks = [
    ['วันนี้',        '?type=daily&date='    . $today,                                        $reportType==='daily'   && ($dateParam??$today)===$today],
    ['เมื่อวาน',     '?type=daily&date='    . $yesterday,                                     $reportType==='daily'   && ($dateParam??$today)===$yesterday],
    ['เดือนนี้',     '?type=monthly&month=' . $thisMonth,                                    $reportType==='monthly' && ($monthParam??$thisMonth)===$thisMonth],
    ['เดือนที่แล้ว', '?type=monthly&month=' . date('Y-m', strtotime('-1 month')),            $reportType==='monthly' && ($monthParam??$thisMonth)===date('Y-m', strtotime('-1 month'))],
    ['ปีนี้',         '?type=yearly&year='   . $thisYear,                                    $reportType==='yearly'  && (int)($yearParam??$thisYear)===(int)$thisYear],
    ['ปีที่แล้ว',    '?type=yearly&year='   . ((int)$thisYear - 1),                          $reportType==='yearly'  && (int)($yearParam??$thisYear)===(int)$thisYear - 1],
];
?>

<style>
/* ── Quick nav bar ── */
.qnav{background:#fff;border-radius:12px;padding:14px 18px;margin-bottom:14px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
.qnav-label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;
  letter-spacing:.1em;margin-right:4px;}
.qnav-btns{display:flex;flex-wrap:wrap;gap:6px;}
.qbtn{padding:6px 14px;border-radius:20px;border:1.5px solid var(--border);
  background:#fafaf8;color:var(--muted);font-family:'Sarabun',sans-serif;
  font-size:.78rem;font-weight:600;cursor:pointer;text-decoration:none;
  transition:.15s;white-space:nowrap;}
.qbtn:hover{border-color:var(--accent);color:var(--accent);background:#fffbf5;}
.qbtn.active{background:var(--ink);color:#fff;border-color:var(--ink);}
.qnav-sep{width:1px;height:24px;background:var(--border);margin:0 4px;}

/* ── Period navigator ── */
.period-nav{background:#fff;border-radius:12px;padding:14px 20px;margin-bottom:14px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);display:flex;align-items:center;gap:14px;}
.pnav-arrow{width:36px;height:36px;border-radius:50%;border:1.5px solid var(--border);
  background:#fafaf8;display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1rem;text-decoration:none;color:var(--ink);
  transition:.15s;flex-shrink:0;}
.pnav-arrow:hover{border-color:var(--accent);color:var(--accent);}
.pnav-center{flex:1;text-align:center;}
.pnav-main{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:600;color:var(--ink);}
.pnav-sub{font-size:.72rem;color:var(--muted);margin-top:2px;}
.pnav-today{padding:6px 16px;border-radius:20px;border:1.5px solid var(--accent);
  background:#fffbf5;color:var(--accent);font-family:'Sarabun',sans-serif;
  font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;transition:.15s;}
.pnav-today:hover{background:var(--accent);color:var(--ink);}

/* ── Filter (collapsed) ── */
.rpt-filter{background:#fff;border-radius:12px;padding:14px 18px;margin-bottom:14px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;}
.rpt-filter label{font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;
  letter-spacing:.08em;display:block;margin-bottom:3px;}
.rpt-filter select,.rpt-filter input{padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.82rem;color:var(--ink);background:#fafaf8;outline:none;
  transition:.2s;}
.rpt-filter select:focus,.rpt-filter input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.rpt-filter .btn-group{display:flex;gap:8px;}

.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;}
.kpi-card{background:#fff;border-radius:12px;padding:18px 20px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);position:relative;overflow:hidden;
  border-left:4px solid var(--accent);}
.kpi-card.blue{border-left-color:#1d6fad;}
.kpi-card.green{border-left-color:#2e7d32;}
.kpi-card.yellow{border-left-color:#f59e0b;}
.kpi-card.red{border-left-color:#dc2626;}
.kpi-card.purple{border-left-color:#7c3aed;}
.kpi-card.teal{border-left-color:#0891b2;}
.kpi-lbl{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-bottom:6px;}
.kpi-val{font-size:1.7rem;font-weight:800;color:var(--ink);line-height:1;}
.kpi-sub{font-size:.72rem;color:var(--muted);margin-top:4px;}
.kpi-icon{position:absolute;right:14px;top:14px;font-size:1.6rem;opacity:.15;}

.svc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:20px;}
.svc-card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 12px rgba(26,26,46,.06);}
.svc-title{font-size:.88rem;font-weight:800;color:var(--ink);margin-bottom:14px;
  display:flex;align-items:center;gap:8px;}
.svc-row{display:flex;justify-content:space-between;align-items:center;
  padding:7px 0;border-bottom:1px solid var(--border);font-size:.82rem;}
.svc-row:last-child{border-bottom:none;}
.svc-row .lbl{color:var(--muted);}
.svc-row .val{font-weight:700;color:var(--ink);}

.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;}
.chart-grid.single{grid-template-columns:1fr;}
.chart-box{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 12px rgba(26,26,46,.06);}
.chart-title{font-size:.82rem;font-weight:700;color:var(--ink);margin-bottom:14px;}
.chart-wrap{position:relative;height:200px;}

.fin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:14px;}
.fin-card{background:#fafaf8;border-radius:10px;padding:14px 16px;border:1px solid var(--border);}
.fin-lbl{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;}
.fin-val{font-size:1.2rem;font-weight:800;color:var(--ink);}

.cmp-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}
.cmp-card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 12px rgba(26,26,46,.06);}
.cmp-period{font-size:.72rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:10px;}
.cmp-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.cmp-cur{font-size:1.5rem;font-weight:800;color:var(--ink);}
.cmp-pct{font-size:.82rem;font-weight:700;padding:3px 8px;border-radius:20px;}
.cmp-pct.up{background:#e8f5e9;color:#2e7d32;}
.cmp-pct.down{background:#fef2f2;color:#dc2626;}
.cmp-pct.flat{background:#f5f5f5;color:var(--muted);}

.export-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}

.pay-badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:.7rem;font-weight:700;}
.pay-paid{background:#e8f5e9;color:#2e7d32;}
.pay-wait{background:#fff3e0;color:#e65100;}
.pay-fail{background:#fef2f2;color:#dc2626;}
.pay-pend{background:#e3f2fd;color:#1565c0;}
.bk-approved{background:#e8f5e9;color:#2e7d32;}
.bk-pending{background:#e3f2fd;color:#1565c0;}
.bk-cancelled{background:#fef2f2;color:#dc2626;}
.bk-rejected{background:#fff3e0;color:#e65100;}

.svc-boat{background:rgba(29,111,173,.1);color:#1d6fad;}
.svc-room{background:rgba(201,169,110,.15);color:#a07c3a;}
.svc-tent{background:rgba(46,125,50,.12);color:#2e7d32;}

@media print{
  .rpt-filter,.export-bar,.sidebar,.topbar-actions{display:none!important;}
  .main{margin-left:0!important;width:100%!important;}
  .chart-wrap{height:180px;}
}
@media(max-width:900px){.chart-grid{grid-template-columns:1fr;}.cmp-grid{grid-template-columns:1fr;}}
</style>

<!-- Export bar -->
<div class="export-bar no-print">
  <button onclick="window.print()" class="btn btn-accent"><span>🖨</span> พิมพ์รายงาน</button>
  <button onclick="exportCSV()" class="btn btn-ghost"><span>📥</span> ดาวน์โหลด CSV</button>
  <span style="font-size:.78rem;color:var(--muted);align-self:center;">รายงาน<?= $labelRange ?> · ออกเมื่อ <?= date('d/m/Y H:i') ?> น.</span>
</div>

<!-- Quick navigation shortcuts -->
<div class="qnav no-print">
  <span class="qnav-label">ดูรายงาน</span>
  <div class="qnav-btns">
    <?php foreach ($qnavLinks as [$qlabel, $qurl, $qactive]): ?>
    <a href="<?= htmlspecialchars($qurl) ?>" class="qbtn<?= $qactive ? ' active' : '' ?>"><?= $qlabel ?></a>
    <?php endforeach; ?>
  </div>
  <div class="qnav-sep"></div>
  <div class="qnav-btns">
    <a href="?type=daily"   class="qbtn<?= $reportType==='daily'  ?' active':'' ?>">รายวัน</a>
    <a href="?type=monthly" class="qbtn<?= $reportType==='monthly'?' active':'' ?>">รายเดือน</a>
    <a href="?type=yearly"  class="qbtn<?= $reportType==='yearly' ?' active':'' ?>">รายปี</a>
  </div>
</div>

<!-- Period navigator -->
<div class="period-nav no-print">
  <a href="<?= htmlspecialchars($prevNavUrl) ?>" class="pnav-arrow" title="ก่อนหน้า">&#8249;</a>
  <div class="pnav-center">
    <div class="pnav-main"><?= $labelRange ?></div>
    <div class="pnav-sub">
      <?php if ($reportType==='daily'): ?>กดลูกศรเพื่อเปลี่ยนวัน
      <?php elseif ($reportType==='monthly'): ?>กดลูกศรเพื่อเปลี่ยนเดือน
      <?php else: ?>กดลูกศรเพื่อเปลี่ยนปี<?php endif; ?>
    </div>
  </div>
  <?php if (!$isCurrentPeriod): ?>
  <a href="<?= htmlspecialchars($todayNavUrl) ?>" class="pnav-today">↩ ปัจจุบัน</a>
  <?php endif; ?>
  <a href="<?= htmlspecialchars($nextNavUrl) ?>" class="pnav-arrow" title="ถัดไป">&#8250;</a>
</div>

<!-- Filter -->
<form method="GET" class="rpt-filter no-print">
  <div>
    <label>ประเภทรายงาน</label>
    <select name="type" onchange="this.form.submit()">
      <option value="daily"   <?= $reportType==='daily'  ?'selected':'' ?>>รายวัน</option>
      <option value="monthly" <?= $reportType==='monthly'?'selected':'' ?>>รายเดือน</option>
      <option value="yearly"  <?= $reportType==='yearly' ?'selected':'' ?>>รายปี</option>
    </select>
  </div>
  <?php if ($reportType === 'daily'): ?>
  <div><label>วันที่</label><input type="date" name="date" value="<?= htmlspecialchars($dateParam ?? $today) ?>"></div>
  <?php elseif ($reportType === 'monthly'): ?>
  <div><label>เดือน</label><input type="month" name="month" value="<?= htmlspecialchars($monthParam ?? $thisMonth) ?>"></div>
  <?php else: ?>
  <div><label>ปี</label>
    <select name="year">
      <?php for($y=2025;$y<=2030;$y++): ?>
      <option value="<?=$y?>" <?= ($yearParam??$thisYear)==$y?'selected':'' ?>><?= $y+543 ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <?php endif; ?>
  <div>
    <label>ประเภทบริการ</label>
    <select name="service">
      <option value="all"  <?= $serviceType==='all' ?'selected':'' ?>>ทั้งหมด</option>
      <option value="boat" <?= $serviceType==='boat'?'selected':'' ?>>เรือพาย</option>
      <option value="room" <?= $serviceType==='room'?'selected':'' ?>>ห้องพัก</option>
      <option value="tent" <?= $serviceType==='tent'?'selected':'' ?>>เต็นท์</option>
    </select>
  </div>
  <div>
    <label>สถานะการจอง</label>
    <select name="bk_status">
      <option value="">ทั้งหมด</option>
      <option value="pending"   <?= $bkStatus==='pending'  ?'selected':'' ?>>รอดำเนินการ</option>
      <option value="approved"  <?= $bkStatus==='approved' ?'selected':'' ?>>อนุมัติแล้ว</option>
      <option value="cancelled" <?= $bkStatus==='cancelled'?'selected':'' ?>>ยกเลิก</option>
    </select>
  </div>
  <div>
    <label>สถานะการชำระ</label>
    <select name="pay_status">
      <option value="">ทั้งหมด</option>
      <option value="paid"           <?= $payStatus==='paid'          ?'selected':'' ?>>ชำระแล้ว</option>
      <option value="waiting_verify" <?= $payStatus==='waiting_verify'?'selected':'' ?>>รอตรวจสอบ</option>
      <option value="unpaid"         <?= $payStatus==='unpaid'        ?'selected':'' ?>>ยังไม่ชำระ</option>
      <option value="failed"         <?= $payStatus==='failed'        ?'selected':'' ?>>ไม่ผ่าน</option>
    </select>
  </div>
  <div class="btn-group">
    <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
    <a href="admin_report.php" class="btn btn-ghost">รีเซ็ต</a>
  </div>
</form>

<!-- KPI Cards -->
<div class="kpi-grid">
  <div class="kpi-card blue">
    <div class="kpi-icon">📋</div>
    <div class="kpi-lbl">การจองทั้งหมด</div>
    <div class="kpi-val"><?= number_format($totalAll) ?></div>
    <div class="kpi-sub">รายการ</div>
  </div>
  <div class="kpi-card green">
    <div class="kpi-icon">✅</div>
    <div class="kpi-lbl">ชำระ/อนุมัติแล้ว</div>
    <div class="kpi-val"><?= number_format($totalPaid) ?></div>
    <div class="kpi-sub">รายการ</div>
  </div>
  <div class="kpi-card yellow">
    <div class="kpi-icon">⏳</div>
    <div class="kpi-lbl">รอชำระ</div>
    <div class="kpi-val"><?= number_format($totalWaiting) ?></div>
    <div class="kpi-sub">รายการ</div>
  </div>
  <div class="kpi-card red">
    <div class="kpi-icon">❌</div>
    <div class="kpi-lbl">ยกเลิก</div>
    <div class="kpi-val"><?= number_format($totalCancel) ?></div>
    <div class="kpi-sub">รายการ</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon">💰</div>
    <div class="kpi-lbl">รายได้รวม</div>
    <div class="kpi-val" style="font-size:1.3rem;">฿<?= number_format($totalRevenue, 0) ?></div>
    <div class="kpi-sub">บาท (เฉพาะเรือ)</div>
  </div>
  <div class="kpi-card teal">
    <div class="kpi-icon">👥</div>
    <div class="kpi-lbl">ผู้เข้าใช้งาน</div>
    <div class="kpi-val"><?= number_format((int)$visitorData['total']) ?></div>
    <div class="kpi-sub">คน</div>
  </div>
  <div class="kpi-card purple">
    <div class="kpi-icon">🏆</div>
    <div class="kpi-lbl">บริการยอดนิยม</div>
    <div class="kpi-val" style="font-size:1rem;margin-top:6px;"><?= $mostBooked ?></div>
    <div class="kpi-sub"><?= $maxSvc ?> รายการ</div>
  </div>
</div>

<!-- Charts row 1 -->
<div class="chart-grid">
  <div class="chart-box">
    <div class="chart-title">📊 จำนวนการจองตามช่วงเวลา</div>
    <div class="chart-wrap"><canvas id="chartBooking"></canvas></div>
  </div>
  <div class="chart-box">
    <div class="chart-title">💵 รายได้ตามช่วงเวลา (เรือพาย)</div>
    <div class="chart-wrap"><canvas id="chartRevenue"></canvas></div>
  </div>
</div>

<!-- Charts row 2 -->
<div class="chart-grid">
  <div class="chart-box">
    <div class="chart-title">🥧 สัดส่วนประเภทบริการ</div>
    <div class="chart-wrap"><canvas id="chartPie"></canvas></div>
  </div>
  <div class="chart-box">
    <div class="chart-title">👥 จำนวนผู้เข้าใช้งาน</div>
    <div class="chart-wrap"><canvas id="chartVisitor"></canvas></div>
  </div>
</div>

<!-- Service breakdown -->
<div class="svc-grid" style="margin-bottom:20px;">
  <div class="svc-card">
    <div class="svc-title"><span class="pay-badge svc-boat">🚣</span> เรือพาย</div>
    <div class="svc-row"><span class="lbl">การจองทั้งหมด</span><span class="val"><?= $boatData['total'] ?></span></div>
    <div class="svc-row"><span class="lbl">ชำระแล้ว</span><span class="val" style="color:#2e7d32;"><?= $boatData['paid'] ?></span></div>
    <div class="svc-row"><span class="lbl">รอชำระ</span><span class="val" style="color:#e65100;"><?= $boatData['waiting'] ?></span></div>
    <div class="svc-row"><span class="lbl">ยกเลิก</span><span class="val" style="color:#dc2626;"><?= $boatData['cancelled'] ?></span></div>
    <div class="svc-row"><span class="lbl">รายได้</span><span class="val">฿<?= number_format((float)$boatData['revenue'], 0) ?></span></div>
  </div>
  <div class="svc-card">
    <div class="svc-title"><span class="pay-badge svc-room">🏨</span> ห้องพัก</div>
    <div class="svc-row"><span class="lbl">การจองทั้งหมด</span><span class="val"><?= $roomData['total'] ?></span></div>
    <div class="svc-row"><span class="lbl">อนุมัติแล้ว</span><span class="val" style="color:#2e7d32;"><?= $roomData['paid'] ?></span></div>
    <div class="svc-row"><span class="lbl">รอดำเนินการ</span><span class="val" style="color:#e65100;"><?= $roomData['waiting'] ?></span></div>
    <div class="svc-row"><span class="lbl">ยกเลิก</span><span class="val" style="color:#dc2626;"><?= $roomData['cancelled'] ?></span></div>
    <div class="svc-row"><span class="lbl">รายได้</span><span class="val">—</span></div>
  </div>
  <div class="svc-card">
    <div class="svc-title"><span class="pay-badge svc-tent">⛺</span> เต็นท์</div>
    <div class="svc-row"><span class="lbl">การจองทั้งหมด</span><span class="val"><?= $tentData['total'] ?></span></div>
    <div class="svc-row"><span class="lbl">อนุมัติแล้ว</span><span class="val" style="color:#2e7d32;"><?= $tentData['paid'] ?></span></div>
    <div class="svc-row"><span class="lbl">รอดำเนินการ</span><span class="val" style="color:#e65100;"><?= $tentData['waiting'] ?></span></div>
    <div class="svc-row"><span class="lbl">ยกเลิก</span><span class="val" style="color:#dc2626;"><?= $tentData['cancelled'] ?></span></div>
    <div class="svc-row"><span class="lbl">รายได้</span><span class="val">—</span></div>
  </div>
</div>

<!-- Finance -->
<div class="lm-card" style="margin-bottom:20px;">
  <div class="lm-card-header">
    <span class="lm-card-title">💳 รายงานด้านการเงิน (เรือพาย)</span>
  </div>
  <div class="lm-card-body">
    <div class="fin-grid">
      <div class="fin-card"><div class="fin-lbl">ยอดรวมทั้งหมด</div><div class="fin-val">฿<?= number_format((float)$finData['grand_total'], 2) ?></div></div>
      <div class="fin-card"><div class="fin-lbl">ชำระแล้ว</div><div class="fin-val" style="color:#2e7d32;">฿<?= number_format((float)$finData['paid_amt'], 2) ?></div></div>
      <div class="fin-card"><div class="fin-lbl">ยังไม่ชำระ</div><div class="fin-val" style="color:#dc2626;">฿<?= number_format((float)$finData['unpaid_amt'], 2) ?></div></div>
      <div class="fin-card"><div class="fin-lbl">รอตรวจสอบ</div><div class="fin-val" style="color:#f59e0b;">฿<?= number_format((float)$finData['waiting_amt'], 2) ?></div></div>
      <div class="fin-card"><div class="fin-lbl">ตรวจสอบไม่ผ่าน</div><div class="fin-val" style="color:#e65100;">฿<?= number_format((float)$finData['failed_amt'], 2) ?></div></div>
    </div>
  </div>
</div>

<!-- Booking table -->
<div class="lm-card" style="margin-bottom:20px;">
  <div class="lm-card-header">
    <span class="lm-card-title">📋 รายละเอียดรายการจอง</span>
    <span style="font-size:.75rem;color:var(--muted);">แสดงสูงสุด 50 รายการ</span>
  </div>
  <div style="overflow-x:auto;">
    <table class="lm-table">
      <thead>
        <tr>
          <th>เลขที่</th>
          <th>ชื่อลูกค้า</th>
          <th>บริการ</th>
          <th>ประเภท</th>
          <th>วันที่จอง</th>
          <th>วันใช้งาน</th>
          <th>ยอดเงิน</th>
          <th>สถานะชำระ</th>
          <th>สถานะจอง</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($bookingRows ?? []) as $r): ?>
        <tr>
          <td style="font-size:.75rem;font-family:monospace;"><?= htmlspecialchars($r['ref']) ?></td>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td>
            <?php if($r['svc']==='boat'): ?><span class="pay-badge svc-boat">🚣 เรือ</span>
            <?php elseif($r['svc']==='room'): ?><span class="pay-badge svc-room">🏨 ห้อง</span>
            <?php else: ?><span class="pay-badge svc-tent">⛺ เต็นท์</span><?php endif; ?>
          </td>
          <td style="font-size:.8rem;"><?= htmlspecialchars($r['subtype'] ?? '') ?></td>
          <td style="font-size:.78rem;"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
          <td style="font-size:.78rem;"><?= $r['use_date'] ? date('d/m/Y', strtotime($r['use_date'])) : '—' ?></td>
          <td><?= (float)$r['total_amount'] > 0 ? '฿'.number_format((float)$r['total_amount'], 0) : '—' ?></td>
          <td>
            <?php
            $ps = $r['payment_status'];
            $cls = match($ps) { 'paid'=>'pay-paid','waiting_verify'=>'pay-wait','failed'=>'pay-fail', default=>'pay-pend' };
            $lbl = match($ps) { 'paid'=>'ชำระแล้ว','waiting_verify'=>'รอตรวจสอบ','failed'=>'ไม่ผ่าน','approved'=>'อนุมัติ','cancelled'=>'ยกเลิก', default=>'รอดำเนินการ' };
            ?>
            <span class="pay-badge <?= $cls ?>"><?= $lbl ?></span>
          </td>
          <td>
            <?php
            $bs = $r['booking_status'];
            $bcls = match($bs) { 'approved'=>'bk-approved','cancelled','rejected'=>'bk-cancelled', default=>'bk-pending' };
            $blbl = match($bs) { 'approved'=>'อนุมัติ','cancelled'=>'ยกเลิก','rejected'=>'ปฏิเสธ', default=>'รอดำเนินการ' };
            ?>
            <span class="pay-badge <?= $bcls ?>"><?= $blbl ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($bookingRows)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:24px;">ไม่พบข้อมูล</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Comparison -->
<div class="cmp-grid" style="margin-bottom:20px;">
  <div class="cmp-card">
    <div class="cmp-period">วันนี้ vs เมื่อวาน</div>
    <div class="cmp-row">
      <div class="cmp-cur"><?= $cmp['today'] ?></div>
      <?php $p=pct($cmp['today'],$cmp['yesterday']); $cls=str_starts_with($p,'+')&&$p!=='+0%'?'up':(str_starts_with($p,'-')?'down':'flat'); ?>
      <span class="cmp-pct <?= $cls ?>"><?= $p ?></span>
    </div>
    <div style="font-size:.75rem;color:var(--muted);">วันนี้ <?= $cmp['today'] ?> / เมื่อวาน <?= $cmp['yesterday'] ?> รายการ</div>
  </div>
  <div class="cmp-card">
    <div class="cmp-period">เดือนนี้ vs เดือนก่อน</div>
    <div class="cmp-row">
      <div class="cmp-cur"><?= $cmp['thisMonth'] ?></div>
      <?php $p=pct($cmp['thisMonth'],$cmp['lastMonth']); $cls=str_starts_with($p,'+')&&$p!=='+0%'?'up':(str_starts_with($p,'-')?'down':'flat'); ?>
      <span class="cmp-pct <?= $cls ?>"><?= $p ?></span>
    </div>
    <div style="font-size:.75rem;color:var(--muted);">เดือนนี้ <?= $cmp['thisMonth'] ?> / เดือนก่อน <?= $cmp['lastMonth'] ?> รายการ</div>
  </div>
  <div class="cmp-card">
    <div class="cmp-period">ปีนี้ vs ปีก่อน</div>
    <div class="cmp-row">
      <div class="cmp-cur"><?= $cmp['thisYear'] ?></div>
      <?php $p=pct($cmp['thisYear'],$cmp['lastYear']); $cls=str_starts_with($p,'+')&&$p!=='+0%'?'up':(str_starts_with($p,'-')?'down':'flat'); ?>
      <span class="cmp-pct <?= $cls ?>"><?= $p ?></span>
    </div>
    <div style="font-size:.75rem;color:var(--muted);">ปีนี้ <?= $cmp['thisYear'] ?> / ปีก่อน <?= $cmp['lastYear'] ?> รายการ</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels  = [<?= $jsLabels ?>];
const dBoat   = [<?= $jsBoat ?>];
const dRoom   = [<?= $jsRoom ?>];
const dTent   = [<?= $jsTent ?>];
const dVisit  = [<?= $jsVisit ?>];
const dRev    = [<?= $jsRevenue ?>];

const opt = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}};

// Booking chart
new Chart(document.getElementById('chartBooking'),{type:'bar',data:{labels,datasets:[
  {label:'เรือพาย',data:dBoat,backgroundColor:'rgba(29,111,173,.7)',borderRadius:4},
  {label:'ห้องพัก',data:dRoom,backgroundColor:'rgba(201,169,110,.7)',borderRadius:4},
  {label:'เต็นท์', data:dTent,backgroundColor:'rgba(46,125,50,.7)',borderRadius:4},
]},options:{...opt,scales:{x:{ticks:{font:{size:10}}},y:{ticks:{font:{size:10}},beginAtZero:true}}}});

// Revenue chart
new Chart(document.getElementById('chartRevenue'),{type:'line',data:{labels,datasets:[
  {label:'รายได้ (฿)',data:dRev,borderColor:'#c9a96e',backgroundColor:'rgba(201,169,110,.12)',fill:true,tension:.3,pointRadius:3}
]},options:{...opt,scales:{x:{ticks:{font:{size:10}}},y:{ticks:{font:{size:10},callback:v=>'฿'+v.toLocaleString()},beginAtZero:true}}}});

// Pie chart
new Chart(document.getElementById('chartPie'),{type:'doughnut',data:{
  labels:['เรือพาย','ห้องพัก','เต็นท์'],
  datasets:[{data:[<?= (int)$boatData['total'] ?>,<?= (int)$roomData['total'] ?>,<?= (int)$tentData['total'] ?>],
    backgroundColor:['rgba(29,111,173,.8)','rgba(201,169,110,.8)','rgba(46,125,50,.8)'],borderWidth:2}]
},options:{...opt}});

// Visitor chart
new Chart(document.getElementById('chartVisitor'),{type:'bar',data:{labels,datasets:[
  {label:'ผู้เข้าใช้งาน',data:dVisit,backgroundColor:'rgba(8,145,178,.6)',borderRadius:4}
]},options:{...opt,scales:{x:{ticks:{font:{size:10}}},y:{ticks:{font:{size:10}},beginAtZero:true}}}});

// CSV Export
function exportCSV(){
  const rows=[['เลขที่','ชื่อ','บริการ','ประเภท','วันที่จอง','วันใช้งาน','ยอดเงิน','สถานะชำระ','สถานะจอง']];
  document.querySelectorAll('.lm-table tbody tr').forEach(tr=>{
    const cells=[...tr.querySelectorAll('td')].map(td=>'"'+td.innerText.trim().replace(/"/g,'""')+'"');
    if(cells.length>1) rows.push(cells);
  });
  const csv=rows.map(r=>r.join(',')).join('\n');
  const a=document.createElement('a');
  a.href='data:text/csv;charset=utf-8,\uFEFF'+encodeURIComponent(csv);
  a.download='report_<?= date('Ymd') ?>.csv';
  a.click();
}
</script>

<?php
require_once 'admin_layout_bottom.php';
$conn->close();
?>
