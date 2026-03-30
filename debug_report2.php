<?php
// Step-by-step debug — ลบทิ้งหลังแก้เสร็จ
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

echo "Step 1: PHP running OK<br>";

$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
echo "Step 2: DB connected OK<br>";

// Simulate session
session_start();
echo "Step 3: Session started, user_id=".($_SESSION['user_id'] ?? 'NOT SET')."<br>";

if (empty($_SESSION['user_id'])) {
    echo "<b style='color:red'>SESSION user_id missing — would redirect to login</b><br>";
    exit;
}

$_chk = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$_chk->bind_param("i", $_SESSION['user_id']);
$_chk->execute();
$_chkRow = $_chk->get_result()->fetch_assoc();
$_chk->close();
echo "Step 4: User role = ".($_chkRow['role'] ?? 'NOT FOUND')."<br>";

if (!$_chkRow || $_chkRow['role'] !== 'admin') {
    echo "<b style='color:red'>Role is not admin — would redirect to login</b><br>";
    exit;
}
echo "Step 5: Admin check passed OK<br>";

// Now simulate the PHP block of admin_report.php
$reportType  = $_GET['type']    ?? 'daily';
$serviceType = $_GET['service'] ?? 'all';
$today = date('Y-m-d');
$thisMonth = date('Y-m');
$thisYear  = date('Y');

if ($reportType === 'daily') {
    $dateParam = $_GET['date'] ?? $today;
    $dateFrom = $dateTo = $dateParam;
} elseif ($reportType === 'monthly') {
    $monthParam = $_GET['month'] ?? $thisMonth;
    $dateFrom = $monthParam . '-01';
    $dateTo   = date('Y-m-t', strtotime($dateFrom));
} else {
    $yearParam = $_GET['year'] ?? $thisYear;
    $dateFrom  = $yearParam . '-01-01';
    $dateTo    = $yearParam . '-12-31';
}
echo "Step 6: Parameters OK — type=$reportType, from=$dateFrom, to=$dateTo<br>";

function dateWhere(string $col, string $from, string $to): string {
    return "DATE($col) BETWEEN '$from' AND '$to'";
}
function addFilters(string $base, string $bkStatus, string $payStatus, bool $hasPayStatus = true): string {
    if ($bkStatus)  $base .= " AND booking_status = '$bkStatus'";
    if ($payStatus && $hasPayStatus) $base .= " AND payment_status = '$payStatus'";
    return $base;
}

$boatWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0";
$boatData  = $conn->query("SELECT COUNT(*) total, SUM(payment_status='paid') paid, SUM(payment_status IN('unpaid','pending','waiting_verify')) waiting, SUM(booking_status='cancelled') cancelled, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) revenue FROM boat_bookings $boatWhere")->fetch_assoc();
echo "Step 7: boatData OK — total=".$boatData['total']."<br>";

$roomWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0";
$roomData  = $conn->query("SELECT COUNT(*) total, SUM(booking_status='approved') paid, SUM(booking_status='pending') waiting, SUM(booking_status='cancelled') cancelled, 0 revenue FROM room_bookings $roomWhere")->fetch_assoc();
echo "Step 8: roomData OK — total=".$roomData['total']."<br>";

$tentWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0";
$tentData  = $conn->query("SELECT COUNT(*) total, SUM(booking_status='approved') paid, SUM(booking_status='pending') waiting, SUM(booking_status='cancelled') cancelled, 0 revenue FROM tent_bookings $tentWhere")->fetch_assoc();
echo "Step 9: tentData OK — total=".$tentData['total']."<br>";

$visitorData = $conn->query("SELECT COUNT(*) total FROM tourists WHERE " . dateWhere('visit_date', $dateFrom, $dateTo) . " AND archived = 0")->fetch_assoc();
echo "Step 10: visitorData OK — total=".$visitorData['total']."<br>";

$finData = $conn->query("SELECT COALESCE(SUM(total_amount),0) grand_total, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) paid_amt, COALESCE(SUM(CASE WHEN payment_status IN('unpaid','pending') THEN total_amount END),0) unpaid_amt, COALESCE(SUM(CASE WHEN payment_status='waiting_verify' THEN total_amount END),0) waiting_amt, COALESCE(SUM(CASE WHEN payment_status='failed' THEN total_amount END),0) failed_amt FROM boat_bookings WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0")->fetch_assoc();
echo "Step 11: finData OK<br>";

$totalAll = ($boatData['total'] ?? 0) + ($roomData['total'] ?? 0) + ($tentData['total'] ?? 0);
$totalRevenue = (float)($boatData['revenue'] ?? 0);
echo "Step 12: Totals OK — all=$totalAll, rev=$totalRevenue<br>";

// Chart data
if ($reportType === 'daily') {
    $chartLabels = ["'" . date('d/m', strtotime($dateFrom)) . "'"];
    $chartBoat   = [(int)$boatData['total']];
    $chartRoom   = [(int)$roomData['total']];
    $chartTent   = [(int)$tentData['total']];
    $chartVisit  = [(int)$visitorData['total']];
    $chartRevenue = [$totalRevenue];
}
echo "Step 13: Chart data OK<br>";

function getCount(mysqli $c, string $tbl, string $from, string $to, string $col='created_at'): int {
    return (int)$c->query("SELECT COUNT(*) n FROM $tbl WHERE DATE($col) BETWEEN '$from' AND '$to' AND archived=0")->fetch_assoc()['n'];
}
$prevDay   = date('Y-m-d', strtotime('-1 day', strtotime($today)));
$prevMonth = date('Y-m', strtotime('-1 month'));
$prevYear  = (int)$thisYear - 1;
$cmp = [
    'today'     => getCount($conn,'boat_bookings',$today,$today) + getCount($conn,'room_bookings',$today,$today) + getCount($conn,'tent_bookings',$today,$today),
    'yesterday' => getCount($conn,'boat_bookings',$prevDay,$prevDay) + getCount($conn,'room_bookings',$prevDay,$prevDay) + getCount($conn,'tent_bookings',$prevDay,$prevDay),
];
echo "Step 14: Comparison OK — today=".$cmp['today']."<br>";

$jsLabels = implode(',', $chartLabels);
$jsBoat   = implode(',', $chartBoat);
echo "Step 15: JS vars OK<br>";

// Nav URLs
$yesterday2 = date('Y-m-d', strtotime('-1 day'));
$prevNavUrl = '?type=daily&date=' . date('Y-m-d', strtotime('-1 day', strtotime($dateParam ?? $today)));
$nextNavUrl = '?type=daily&date=' . date('Y-m-d', strtotime('+1 day', strtotime($dateParam ?? $today)));
$qnavLinks  = [
    ['วันนี้', '?type=daily&date='.$today, $reportType==='daily' && ($dateParam??$today)===$today],
];
echo "Step 16: Nav URLs OK<br>";

echo "<br><b style='color:green'>ALL STEPS PASSED — problem is elsewhere in admin_report.php</b>";
