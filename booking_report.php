<?php
mysqli_report(MYSQLI_REPORT_OFF);
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
$boatWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0 AND booking_status NOT IN ('cancelled','rejected')";
$boatWhere = addFilters($boatWhere, $bkStatus, $payStatus);
$boatData  = $conn->query("SELECT COUNT(*) total,
    SUM(payment_status='paid') paid,
    SUM(payment_status IN('unpaid','pending','waiting_verify')) waiting,
    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) revenue
    FROM boat_bookings $boatWhere")->fetch_assoc();

// ── Room bookings ──
$roomWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0 AND booking_status NOT IN ('cancelled','rejected')";
if ($bkStatus) $roomWhere .= " AND booking_status = '$bkStatus'";
$roomData  = $conn->query("SELECT COUNT(*) total,
    SUM(booking_status='approved') paid,
    SUM(booking_status='pending') waiting,
    0 revenue
    FROM room_bookings $roomWhere")->fetch_assoc();

// ── Tent bookings ──
$tentWhere = "WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived = 0 AND booking_status NOT IN ('cancelled','rejected')";
if ($bkStatus) $tentWhere .= " AND booking_status = '$bkStatus'";
$tentData  = $conn->query("SELECT COUNT(*) total,
    SUM(booking_status='approved') paid,
    SUM(booking_status='pending') waiting,
    0 revenue
    FROM tent_bookings $tentWhere")->fetch_assoc();

// ── Visitors ──
$visitorData = $conn->query("SELECT COUNT(*) total FROM tourists
    WHERE " . dateWhere('visit_date', $dateFrom, $dateTo))->fetch_assoc();

// ── Tourist check-in breakdown (tourists table) ──
$touristBreakdown = [];
$tbRes = $conn->query("SELECT user_type, COUNT(*) AS cnt, GROUP_CONCAT(nickname ORDER BY visit_time ASC SEPARATOR '||') AS names
    FROM tourists WHERE " . dateWhere('visit_date', $dateFrom, $dateTo) . " GROUP BY user_type");
while ($tbRow = $tbRes->fetch_assoc()) $touristBreakdown[$tbRow['user_type']] = $tbRow;
$touristStudent = (int)($touristBreakdown['นักศึกษา']['cnt'] ?? 0);
$touristStaff   = (int)($touristBreakdown['บุคลากร']['cnt'] ?? 0);
$touristVisitor = (int)($touristBreakdown['นักท่องเที่ยว']['cnt'] ?? 0);
$touristTotal   = $touristStudent + $touristStaff + $touristVisitor;

// today's walk-in tourists
$touristTodayCount = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE visit_date='$today'")->fetch_assoc()['c'];

// รายชื่อผู้เช็คอิน (tourists) สำหรับช่วงที่เลือก
$touristListRes = $conn->query("SELECT nickname, gender, age, user_type, visit_date, visit_time
    FROM tourists WHERE " . dateWhere('visit_date', $dateFrom, $dateTo) . " ORDER BY visit_date ASC, visit_time ASC LIMIT 200");
$touristList = [];
while ($tlRow = $touristListRes->fetch_assoc()) $touristList[] = $tlRow;

// ── Finance (boat only has payment) ──
$finData = $conn->query("SELECT
    COALESCE(SUM(total_amount),0) grand_total,
    COALESCE(SUM(CASE WHEN payment_status IN('paid','cash_paid') THEN total_amount END),0) paid_amt,
    COALESCE(SUM(CASE WHEN payment_status='cash_paid' OR payment_provider='cash' THEN total_amount END),0) cash_amt,
    COALESCE(SUM(CASE WHEN payment_status='paid' AND (payment_provider IS NULL OR payment_provider!='cash') THEN total_amount END),0) transfer_amt,
    COALESCE(SUM(CASE WHEN payment_status IN('unpaid','pending') THEN total_amount END),0) unpaid_amt,
    COALESCE(SUM(CASE WHEN payment_status='waiting_verify' THEN total_amount END),0) waiting_amt,
    COALESCE(SUM(CASE WHEN payment_status='failed' THEN total_amount END),0) failed_amt
    FROM boat_bookings WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0")->fetch_assoc();

// ── Grand totals ──
$totalAll     = ($boatData['total'] ?? 0) + ($roomData['total'] ?? 0) + ($tentData['total'] ?? 0);
$totalPaid    = ($boatData['paid'] ?? 0) + ($roomData['paid'] ?? 0) + ($tentData['paid'] ?? 0);
$totalWaiting = ($boatData['waiting'] ?? 0) + ($roomData['waiting'] ?? 0) + ($tentData['waiting'] ?? 0);
$totalRevenue = (float)($boatData['revenue'] ?? 0);

// ── Guest counts for current period ──
$boatGuests = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE " . dateWhere('created_at',$dateFrom,$dateTo) . " AND archived=0")->fetch_assoc()['n'];
$roomGuests = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE " . dateWhere('created_at',$dateFrom,$dateTo) . " AND archived=0")->fetch_assoc()['n'];
$tentGuests = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE " . dateWhere('created_at',$dateFrom,$dateTo) . " AND archived=0")->fetch_assoc()['n'];
$stayGuests = $roomGuests + $tentGuests; // คนเข้าพักรวม

// ── Revenue breakdown: ห้องพัก (room_bookings JOIN rooms) ──
$_roomRevQ = function($conn, $where) {
    return (float)$conn->query("SELECT COALESCE(SUM(r.price * GREATEST(DATEDIFF(rb.checkout_date,rb.checkin_date),1)),0) s
        FROM room_bookings rb LEFT JOIN rooms r ON rb.room_id=r.id
        WHERE $where AND rb.booking_status='approved' AND rb.archived=0")->fetch_assoc()['s'];
};
$revRoomToday = $_roomRevQ($conn, "DATE(rb.created_at)='$today'");
$revRoomMonth = $_roomRevQ($conn, "DATE(rb.created_at) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "'");
$revRoomYear  = $_roomRevQ($conn, "DATE(rb.created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31'");

// ── Revenue breakdown: เต็นท์ (tent_bookings JOIN tents) ──
$_tentRevQ = function($conn, $where) {
    return (float)$conn->query("SELECT COALESCE(SUM(te.price_per_night * GREATEST(DATEDIFF(tb.checkout_date,tb.checkin_date),1)),0) s
        FROM tent_bookings tb LEFT JOIN tents te ON tb.tent_id=te.id
        WHERE $where AND tb.booking_status='approved' AND tb.archived=0")->fetch_assoc()['s'];
};
$revTentToday = $_tentRevQ($conn, "DATE(tb.created_at)='$today'");
$revTentMonth = $_tentRevQ($conn, "DATE(tb.created_at) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "'");
$revTentYear  = $_tentRevQ($conn, "DATE(tb.created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31'");

// ── Revenue breakdown: วันนี้ / เดือนนี้ / ปีนี้ (เรือ) ──
$_revQ = function($conn, $where) {
    return $conn->query("SELECT
        COALESCE(SUM(CASE WHEN payment_status IN('paid','cash_paid') THEN total_amount END),0) total,
        COALESCE(SUM(CASE WHEN payment_status='cash_paid' OR payment_provider='cash' THEN total_amount END),0) cash,
        COALESCE(SUM(CASE WHEN payment_status='paid' AND (payment_provider IS NULL OR payment_provider!='cash') THEN total_amount END),0) transfer
        FROM boat_bookings WHERE $where AND archived=0")->fetch_assoc();
};
$_revToday = $_revQ($conn, "DATE(created_at)='$today'");
$_revMonth = $_revQ($conn, "DATE(created_at) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "'");
$_revYear  = $_revQ($conn, "DATE(created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31'");
$revToday = (float)$_revToday['total'];
$revMonth = (float)$_revMonth['total'];
$revYear  = (float)$_revYear['total'];

// ── Guest counts: วันนี้ / เดือนนี้ / ปีนี้ ──
$boatGuestToday = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE DATE(created_at)='$today' AND archived=0")->fetch_assoc()['n'];
$boatGuestMonth = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE DATE(created_at) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "' AND archived=0")->fetch_assoc()['n'];
$boatGuestYear  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE DATE(created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND archived=0")->fetch_assoc()['n'];
$roomGuestToday = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE DATE(created_at)='$today' AND archived=0")->fetch_assoc()['n'];
$roomGuestMonth = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE DATE(created_at) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "' AND archived=0")->fetch_assoc()['n'];
$roomGuestYear  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE DATE(created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND archived=0")->fetch_assoc()['n'];
$tentGuestToday = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE DATE(created_at)='$today' AND archived=0")->fetch_assoc()['n'];
$tentGuestMonth = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE DATE(created_at) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "' AND archived=0")->fetch_assoc()['n'];
$tentGuestYear  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE DATE(created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND archived=0")->fetch_assoc()['n'];

// ── Check-in counts (นับจากวันเช็คอินจริง) ──
// เรือ: boat_date | ห้อง: checkin_date | เต็นท์: checkin_date
$boatCheckinToday  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE DATE(boat_date)='$today' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$boatCheckinMonth  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE DATE(boat_date) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$boatCheckinYear   = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE DATE(boat_date) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$boatCheckinPeriod = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM boat_bookings WHERE " . dateWhere('boat_date',$dateFrom,$dateTo) . " AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];

$roomCheckinToday  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE DATE(checkin_date)='$today' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$roomCheckinMonth  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE DATE(checkin_date) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$roomCheckinYear   = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE DATE(checkin_date) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$roomCheckinPeriod = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM room_bookings WHERE " . dateWhere('checkin_date',$dateFrom,$dateTo) . " AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];

$tentCheckinToday  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE DATE(checkin_date)='$today' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$tentCheckinMonth  = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE DATE(checkin_date) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$tentCheckinYear   = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE DATE(checkin_date) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];
$tentCheckinPeriod = (int)$conn->query("SELECT COALESCE(SUM(guests),0) n FROM tent_bookings WHERE " . dateWhere('checkin_date',$dateFrom,$dateTo) . " AND booking_status='approved' AND archived=0")->fetch_assoc()['n'];

$totalCheckinToday  = $boatCheckinToday  + $roomCheckinToday  + $tentCheckinToday;
$totalCheckinMonth  = $boatCheckinMonth  + $roomCheckinMonth  + $tentCheckinMonth;
$totalCheckinYear   = $boatCheckinYear   + $roomCheckinYear   + $tentCheckinYear;
$totalCheckinPeriod = $boatCheckinPeriod + $roomCheckinPeriod + $tentCheckinPeriod;

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
        $chartVisit[]  = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE visit_date='$dt'")->fetch_assoc()['c'];
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
        $chartVisit[]  = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE visit_date BETWEEN '$mStart' AND '$mEnd'")->fetch_assoc()['c'];
    }
}

// Revenue chart (boat + room + tent)
$chartRevenue = []; $chartRevenueRoom = []; $chartRevenueTent = [];
if ($reportType === 'monthly') {
    $days = (int)date('t', strtotime($dateFrom));
    for ($d = 1; $d <= $days; $d++) {
        $dt = date('Y-m-', strtotime($dateFrom)) . str_pad($d, 2, '0', STR_PAD_LEFT);
        $chartRevenue[]     = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at)='$dt' AND payment_status IN('paid','cash_paid') AND archived=0")->fetch_assoc()['s'];
        $chartRevenueRoom[] = (float)$conn->query("SELECT COALESCE(SUM(r.price*GREATEST(DATEDIFF(rb.checkout_date,rb.checkin_date),1)),0) s FROM room_bookings rb LEFT JOIN rooms r ON rb.room_id=r.id WHERE DATE(rb.created_at)='$dt' AND rb.booking_status='approved' AND rb.archived=0")->fetch_assoc()['s'];
        $chartRevenueTent[] = (float)$conn->query("SELECT COALESCE(SUM(te.price_per_night*GREATEST(DATEDIFF(tb.checkout_date,tb.checkin_date),1)),0) s FROM tent_bookings tb LEFT JOIN tents te ON tb.tent_id=te.id WHERE DATE(tb.created_at)='$dt' AND tb.booking_status='approved' AND tb.archived=0")->fetch_assoc()['s'];
    }
} elseif ($reportType === 'yearly') {
    for ($m = 1; $m <= 12; $m++) {
        $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
        $mStart = "{$yearParam}-{$mm}-01";
        $mEnd   = date('Y-m-t', strtotime($mStart));
        $chartRevenue[]     = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) s FROM boat_bookings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd' AND payment_status IN('paid','cash_paid') AND archived=0")->fetch_assoc()['s'];
        $chartRevenueRoom[] = (float)$conn->query("SELECT COALESCE(SUM(r.price*GREATEST(DATEDIFF(rb.checkout_date,rb.checkin_date),1)),0) s FROM room_bookings rb LEFT JOIN rooms r ON rb.room_id=r.id WHERE DATE(rb.created_at) BETWEEN '$mStart' AND '$mEnd' AND rb.booking_status='approved' AND rb.archived=0")->fetch_assoc()['s'];
        $chartRevenueTent[] = (float)$conn->query("SELECT COALESCE(SUM(te.price_per_night*GREATEST(DATEDIFF(tb.checkout_date,tb.checkin_date),1)),0) s FROM tent_bookings tb LEFT JOIN tents te ON tb.tent_id=te.id WHERE DATE(tb.created_at) BETWEEN '$mStart' AND '$mEnd' AND tb.booking_status='approved' AND tb.archived=0")->fetch_assoc()['s'];
    }
} else {
    $chartRevenue[]     = $totalRevenue;
    $chartRevenueRoom[] = (float)$conn->query("SELECT COALESCE(SUM(r.price*GREATEST(DATEDIFF(rb.checkout_date,rb.checkin_date),1)),0) s FROM room_bookings rb LEFT JOIN rooms r ON rb.room_id=r.id WHERE " . dateWhere('rb.created_at',$dateFrom,$dateTo) . " AND rb.booking_status='approved' AND rb.archived=0")->fetch_assoc()['s'];
    $chartRevenueTent[] = (float)$conn->query("SELECT COALESCE(SUM(te.price_per_night*GREATEST(DATEDIFF(tb.checkout_date,tb.checkin_date),1)),0) s FROM tent_bookings tb LEFT JOIN tents te ON tb.tent_id=te.id WHERE " . dateWhere('tb.created_at',$dateFrom,$dateTo) . " AND tb.booking_status='approved' AND tb.archived=0")->fetch_assoc()['s'];
}

// ── Booking list ──
$bookingRows = [];
if ($serviceType === 'all' || $serviceType === 'boat') {
    $listSQL = "SELECT 'boat' svc, booking_ref ref, full_name, boat_type subtype, created_at, boat_date use_date,
        total_amount, payment_status, booking_status FROM boat_bookings
        WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0 AND booking_status NOT IN ('cancelled','rejected') ORDER BY created_at DESC LIMIT 50";
    $rows = $conn->query($listSQL);
    while ($r = $rows->fetch_assoc()) $bookingRows[] = $r;
}
if ($serviceType === 'all' || $serviceType === 'room') {
    $rRows = $conn->query("SELECT 'room' svc, CONCAT('RM',id) ref, full_name, room_type subtype, created_at, checkin_date use_date,
        0 total_amount, booking_status payment_status, booking_status FROM room_bookings
        WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0 AND booking_status NOT IN ('cancelled','rejected') ORDER BY created_at DESC LIMIT 50");
    while ($r = $rRows->fetch_assoc()) $bookingRows[] = $r;
}
if ($serviceType === 'all' || $serviceType === 'tent') {
    $tRows = $conn->query("SELECT 'tent' svc, CONCAT('TN',id) ref, full_name, tent_type subtype, created_at, checkin_date use_date,
        0 total_amount, booking_status payment_status, booking_status FROM tent_bookings
        WHERE " . dateWhere('created_at', $dateFrom, $dateTo) . " AND archived=0 AND booking_status NOT IN ('cancelled','rejected') ORDER BY created_at DESC LIMIT 50");
    while ($r = $tRows->fetch_assoc()) $bookingRows[] = $r;
}
if (!empty($bookingRows)) {
    usort($bookingRows, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
}

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
$jsRevenue     = implode(',', $chartRevenue);
$jsRevenueRoom = implode(',', $chartRevenueRoom);
$jsRevenueTent = implode(',', $chartRevenueTent);

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

// ── Base URL (preserve period params for service tab links) ──
$baseUrl = '?type=' . $reportType;
if ($reportType === 'daily')        $baseUrl .= '&date='  . ($dateParam  ?? $today);
elseif ($reportType === 'monthly')  $baseUrl .= '&month=' . ($monthParam ?? $thisMonth);
else                                $baseUrl .= '&year='  . ($yearParam  ?? $thisYear);
if ($bkStatus)  $baseUrl .= '&bk_status='  . $bkStatus;
if ($payStatus) $baseUrl .= '&pay_status=' . $payStatus;

// ── Context KPIs (ขึ้นอยู่กับ serviceType) ──
if ($serviceType === 'boat') {
    $ctxTotal   = (int)$boatData['total'];
    $ctxPaid    = (int)$boatData['paid'];
    $ctxWaiting = (int)$boatData['waiting'];
    $ctxRevenue = (float)$boatData['revenue'];
    $ctxGuests  = $boatGuests;
    $ctxLabel   = 'เรือพาย';
} elseif ($serviceType === 'room') {
    $ctxTotal   = (int)$roomData['total'];
    $ctxPaid    = (int)$roomData['paid'];
    $ctxWaiting = (int)$roomData['waiting'];
    $ctxRevenue = 0;
    $ctxGuests  = $roomGuests;
    $ctxLabel   = 'ห้องพัก';
} elseif ($serviceType === 'tent') {
    $ctxTotal   = (int)$tentData['total'];
    $ctxPaid    = (int)$tentData['paid'];
    $ctxWaiting = (int)$tentData['waiting'];
    $ctxRevenue = 0;
    $ctxGuests  = $tentGuests;
    $ctxLabel   = 'เต็นท์';
} elseif ($serviceType === 'checkin') {
    $ctxTotal   = $totalCheckinPeriod;
    $ctxPaid    = $boatCheckinPeriod;
    $ctxWaiting = $roomCheckinPeriod;
    $ctxRevenue = 0;
    $ctxGuests  = $totalCheckinPeriod;
    $ctxLabel   = 'ข้อมูลเช็คอิน';
} else {
    $ctxTotal   = $totalAll;
    $ctxPaid    = $totalPaid;
    $ctxWaiting = $totalWaiting;
    $ctxRevenue = $totalRevenue;
    $ctxGuests  = $boatGuests + $roomGuests + $tentGuests;
    $ctxLabel   = 'ทุกบริการ';
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
/* ── Toolbar (export + quick nav รวมกัน) ── */
.rpt-toolbar{background:#fff;border-radius:12px;padding:12px 18px;margin-bottom:12px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);display:flex;align-items:center;
  gap:12px;flex-wrap:wrap;}
.rpt-toolbar-left{display:flex;gap:8px;align-items:center;flex-shrink:0;}
.toolbar-divider{width:1px;height:28px;background:var(--border);flex-shrink:0;}
.rpt-toolbar-nav{display:flex;flex-wrap:wrap;gap:6px;flex:1;}
.rpt-toolbar-type{display:flex;gap:6px;flex-shrink:0;}
.rpt-ts{font-size:.72rem;color:var(--muted);white-space:nowrap;margin-left:auto;}

/* ── Quick nav buttons ── */
.qbtn{padding:6px 14px;border-radius:20px;border:1.5px solid var(--border);
  background:#fafaf8;color:var(--muted);font-family:'Sarabun',sans-serif;
  font-size:.78rem;font-weight:600;cursor:pointer;text-decoration:none;
  transition:.15s;white-space:nowrap;}
.qbtn:hover{border-color:var(--accent);color:var(--accent);background:#fffbf5;}
.qbtn.active{background:var(--ink);color:#fff;border-color:var(--ink);}

/* ── Period navigator ── */
.period-nav{background:#fff;border-radius:12px;padding:14px 20px;margin-bottom:12px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);display:flex;align-items:center;gap:14px;}
.pnav-arrow{width:36px;height:36px;border-radius:50%;border:1.5px solid var(--border);
  background:#fafaf8;display:flex;align-items:center;justify-content:center;
  font-size:1rem;text-decoration:none;color:var(--ink);transition:.15s;flex-shrink:0;}
.pnav-arrow:hover{border-color:var(--accent);color:var(--accent);}
.pnav-center{flex:1;text-align:center;}
.pnav-main{font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:700;color:var(--ink);}
.pnav-sub{font-size:.7rem;color:var(--muted);margin-top:2px;}
.pnav-back{padding:6px 14px;border-radius:20px;border:1.5px solid var(--accent);
  background:#fffbf5;color:var(--accent);font-family:'Sarabun',sans-serif;
  font-size:.76rem;font-weight:700;text-decoration:none;transition:.15s;white-space:nowrap;}
.pnav-back:hover{background:var(--accent);color:var(--ink);}

/* ── Filter ── */
.rpt-filter{background:#fff;border-radius:12px;padding:14px 18px;margin-bottom:16px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;}
.rpt-filter label{font-size:.67rem;font-weight:700;color:var(--muted);text-transform:uppercase;
  letter-spacing:.08em;display:block;margin-bottom:3px;}
.rpt-filter select,.rpt-filter input{padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;
  font-family:'Sarabun',sans-serif;font-size:.82rem;color:var(--ink);background:#fafaf8;outline:none;transition:.2s;}
.rpt-filter select:focus,.rpt-filter input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.rpt-filter .btn-group{display:flex;gap:8px;}

/* ── Section heading ── */
.sec-hd{font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;
  letter-spacing:.12em;margin:20px 0 10px;display:flex;align-items:center;gap:8px;}
.sec-hd::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── Service tabs ── */
.svc-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
.svc-tab{display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;
  border:2px solid var(--border);background:#fff;text-decoration:none;
  color:var(--muted);font-size:.84rem;font-weight:700;transition:.15s;
  box-shadow:0 1px 6px rgba(26,26,46,.05);}
.svc-tab:hover{border-color:var(--ink);color:var(--ink);}
.svc-tab.active{border-color:var(--ink);background:var(--ink);color:#fff;}
.svc-tab.active.boat{background:#1d6fad;border-color:#1d6fad;}
.svc-tab.active.room{background:#a07c3a;border-color:#a07c3a;}
.svc-tab.active.tent{background:#2e7d32;border-color:#2e7d32;}
.svc-tab.active.checkin{background:#0d9488;border-color:#0d9488;}
.stab-cnt{background:rgba(255,255,255,.22);color:inherit;padding:1px 7px;
  border-radius:20px;font-size:.7rem;font-weight:800;}
.svc-tab:not(.active) .stab-cnt{background:#f0efec;color:var(--muted);}

/* ── Stat table card ── */
.stat-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(26,26,46,.06);
  overflow:hidden;margin-bottom:8px;}
.stat-card-hd{padding:13px 18px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:8px;}
.stat-card-title{font-size:.84rem;font-weight:800;color:var(--ink);}
.stat-table{width:100%;border-collapse:collapse;}
.stat-table th{background:#fafaf8;font-size:.67rem;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:.08em;padding:9px 16px;
  border-bottom:1px solid var(--border);text-align:left;}
.stat-table th.num{text-align:right;}
.stat-table td{padding:10px 16px;border-bottom:1px solid #f3f3f0;font-size:.84rem;color:var(--ink);}
.stat-table td.num{text-align:right;font-weight:700;font-variant-numeric:tabular-nums;}
.stat-table tr:last-child td{border-bottom:none;}
.stat-table tr:hover td{background:#fafaf8;}
.stat-period-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.68rem;
  font-weight:700;background:#f0f0ee;color:var(--muted);}
.stat-period-badge.today{background:#e8f5e9;color:#2e7d32;}
.stat-period-badge.month{background:#e3f2fd;color:#1565c0;}
.stat-period-badge.year{background:#fff3e0;color:#e65100;}

/* ── Revenue highlight ── */
.rev-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:8px;}
.rev-card{background:#fff;border-radius:12px;padding:16px 18px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);border-top:3px solid;}
.rev-card.today-card{border-top-color:#2e7d32;}
.rev-card.month-card{border-top-color:#1565c0;}
.rev-card.year-card{border-top-color:#e65100;}
.rev-period{font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
  color:var(--muted);margin-bottom:6px;}
.rev-amt{font-size:1.55rem;font-weight:900;color:var(--ink);line-height:1;}
.rev-sub{font-size:.7rem;color:var(--muted);margin-top:4px;}
.rev-detail{margin-top:10px;padding-top:10px;border-top:1px solid var(--border);
  display:flex;flex-direction:column;gap:4px;}
.rev-row{display:flex;justify-content:space-between;font-size:.76rem;}
.rev-row .rl{color:var(--muted);}
.rev-row .rv{font-weight:700;color:var(--ink);}
.pay-split{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;}
.ps-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:.72rem;font-weight:700;}
.ps-cash{background:#fff7ed;color:#c2410c;border:1px solid #fdba74;}
.ps-transfer{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}

@media(max-width:700px){.rev-grid{grid-template-columns:1fr;}}

/* ── KPI grid ── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin-bottom:8px;}
.kpi-card{background:#fff;border-radius:12px;padding:16px 18px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);position:relative;overflow:hidden;
  border-left:4px solid var(--accent);}
.kpi-card.blue{border-left-color:#1d6fad;}
.kpi-card.green{border-left-color:#2e7d32;}
.kpi-card.yellow{border-left-color:#f59e0b;}
.kpi-card.red{border-left-color:#dc2626;}
.kpi-card.purple{border-left-color:#7c3aed;}
.kpi-card.teal{border-left-color:#0891b2;}
.kpi-lbl{font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-bottom:5px;}
.kpi-val{font-size:1.65rem;font-weight:800;color:var(--ink);line-height:1;}
.kpi-sub{font-size:.7rem;color:var(--muted);margin-top:4px;}
.kpi-icon{position:absolute;right:12px;top:12px;font-size:1.5rem;opacity:.12;}

/* ── Service grid ── */
.svc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-bottom:8px;}
.svc-card{background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 2px 12px rgba(26,26,46,.06);}
.svc-title{font-size:.86rem;font-weight:800;color:var(--ink);margin-bottom:12px;
  display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--border);}
.svc-row{display:flex;justify-content:space-between;align-items:center;
  padding:6px 0;border-bottom:1px dashed var(--border);font-size:.8rem;}
.svc-row:last-child{border-bottom:none;}
.svc-row .lbl{color:var(--muted);}
.svc-row .val{font-weight:700;color:var(--ink);}

/* ── Chart grid ── */
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;}
.chart-box{background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 2px 12px rgba(26,26,46,.06);}
.chart-title{font-size:.8rem;font-weight:700;color:var(--ink);margin-bottom:12px;
  padding-bottom:10px;border-bottom:1px solid var(--border);}
.chart-wrap{position:relative;height:200px;}

/* ── Finance ── */
.fin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-bottom:4px;}
.fin-card{background:#fafaf8;border-radius:10px;padding:13px 15px;border:1px solid var(--border);}
.fin-lbl{font-size:.66rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;}
.fin-val{font-size:1.15rem;font-weight:800;color:var(--ink);}

/* ── Comparison grid ── */
.cmp-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:8px;}
.cmp-card{background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 2px 12px rgba(26,26,46,.06);}
.cmp-period{font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border);}
.cmp-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.cmp-cur{font-size:1.5rem;font-weight:800;color:var(--ink);}
.cmp-pct{font-size:.8rem;font-weight:700;padding:3px 8px;border-radius:20px;}
.cmp-pct.up{background:#e8f5e9;color:#2e7d32;}
.cmp-pct.down{background:#fef2f2;color:#dc2626;}
.cmp-pct.flat{background:#f5f5f5;color:var(--muted);}

/* ── Badges ── */
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
  /* ── ซ่อน UI ที่ไม่ต้องการ ── */
  .rpt-toolbar,.rpt-filter,.period-nav,.svc-tabs,
  .sidebar,.topbar-actions,.topbar,.no-print,
  .pnav-arrow,.pnav-back,
  button,a.arc-link,a.mn-link{ display:none!important; }

  /* ── Layout ── */
  *{ -webkit-print-color-adjust:exact; print-color-adjust:exact; box-sizing:border-box; }
  body{ background:#fff!important; font-family:'Sarabun',sans-serif; font-size:10pt; color:#000; }
  .main{ margin-left:0!important; width:100%!important; padding:0!important; }
  .content-wrapper,.page-content{ padding:0!important; margin:0!important; }

  /* ── Print header ── */
  body::before{
    content: 'รายงานภาพรวม — WRBRI · <?= $labelRange ?> · พิมพ์วันที่ <?= date('d/m/Y H:i') ?> น.';
    display:block;
    font-size:11pt; font-weight:800; color:#1a1a2e;
    padding:10px 0 8px; margin-bottom:14px;
    border-bottom:2.5px solid #1a1a2e;
    letter-spacing:.02em;
  }

  /* ── Section headings ── */
  .sec-hd{
    font-size:9pt!important; font-weight:800!important;
    color:#333!important; text-transform:uppercase;
    letter-spacing:.12em; margin:14px 0 7px!important;
    padding-bottom:4px; border-bottom:1.5px solid #ccc;
  }
  .sec-hd::after{ display:none!important; }

  /* ── KPI cards ── */
  .kpi-grid{ display:grid!important; grid-template-columns:repeat(4,1fr)!important; gap:7px!important; margin-bottom:10px!important; }
  .kpi-card{
    border:1px solid #ccc!important; border-radius:6px!important;
    padding:9px 10px!important; box-shadow:none!important;
    page-break-inside:avoid; break-inside:avoid;
  }
  .kpi-icon{ display:none!important; }
  .kpi-lbl{ font-size:7pt!important; }
  .kpi-val{ font-size:16pt!important; }
  .kpi-sub{ font-size:7pt!important; }

  /* ── Revenue cards ── */
  .rev-grid{ display:grid!important; grid-template-columns:repeat(3,1fr)!important; gap:7px!important; }
  .rev-card{ border:1px solid #ccc!important; border-radius:6px!important; box-shadow:none!important; padding:10px!important; page-break-inside:avoid; break-inside:avoid; }
  .rev-amt{ font-size:14pt!important; }

  /* ── Service breakdown ── */
  .svc-grid{ display:grid!important; grid-template-columns:repeat(3,1fr)!important; gap:7px!important; }
  .svc-card{ border:1px solid #ccc!important; border-radius:6px!important; box-shadow:none!important; padding:10px!important; page-break-inside:avoid; break-inside:avoid; }

  /* ── Stat cards / tables ── */
  .stat-card{ border:1px solid #ccc!important; border-radius:6px!important; box-shadow:none!important; margin-bottom:10px!important; page-break-inside:avoid; break-inside:avoid; }
  .stat-card-hd{ padding:7px 12px!important; background:#f5f5f5!important; }
  .stat-table th{ background:#f5f5f5!important; font-size:8pt!important; padding:6px 10px!important; border-bottom:1.5px solid #aaa!important; }
  .stat-table td{ font-size:9pt!important; padding:5px 10px!important; border-bottom:1px solid #e0e0e0!important; }

  /* ── Finance grid ── */
  .fin-grid{ display:grid!important; grid-template-columns:repeat(4,1fr)!important; gap:7px!important; }
  .fin-card{ border:1px solid #ccc!important; border-radius:6px!important; padding:9px!important; background:#fafafa!important; }
  .fin-val{ font-size:11pt!important; }

  /* ── Comparison grid ── */
  .cmp-grid{ display:grid!important; grid-template-columns:repeat(3,1fr)!important; gap:7px!important; }
  .cmp-card{ border:1px solid #ccc!important; border-radius:6px!important; box-shadow:none!important; padding:10px!important; page-break-inside:avoid; break-inside:avoid; }
  .cmp-cur{ font-size:16pt!important; }

  /* ── Detail table ── */
  .lm-card{ border:1px solid #ccc!important; border-radius:6px!important; box-shadow:none!important; margin-bottom:10px!important; }
  .lm-card-header{ background:#f5f5f5!important; padding:7px 12px!important; border-bottom:1.5px solid #aaa!important; }
  .lm-table{ width:100%!important; border-collapse:collapse!important; font-size:8pt!important; }
  .lm-table th{ background:#f5f5f5!important; font-size:7.5pt!important; padding:5px 8px!important; border:1px solid #ccc!important; }
  .lm-table td{ padding:5px 8px!important; border:1px solid #ddd!important; font-size:8pt!important; }

  /* ── Charts ── */
  .chart-grid{ display:grid!important; grid-template-columns:1fr 1fr!important; gap:10px!important; margin-bottom:10px!important; page-break-inside:avoid; break-inside:avoid; }
  .chart-box{ border:1px solid #ccc!important; border-radius:6px!important; box-shadow:none!important; padding:10px!important; page-break-inside:avoid; break-inside:avoid; }
  .chart-title{ font-size:8pt!important; font-weight:800!important; border-bottom:1px solid #ccc!important; padding-bottom:6px!important; margin-bottom:8px!important; }
  .chart-wrap{ height:160px!important; position:relative!important; }

  /* ── Badges ── */
  .pay-badge,.stat-period-badge,.kpi-card,.pay-pill{
    border:1px solid #aaa!important;
    background:#f5f5f5!important; color:#333!important;
  }

  /* ── Page breaks ── */
  .sec-hd{ page-break-before:auto; }
  .kpi-grid,.rev-grid,.svc-grid,.cmp-grid{ page-break-inside:avoid; break-inside:avoid; }

  /* ── Page size ── */
  @page{ size:A4 landscape; margin:12mm 14mm; }
}
@media(max-width:960px){
  .chart-grid{grid-template-columns:1fr;}
  .cmp-grid{grid-template-columns:1fr;}
  .rpt-toolbar{flex-direction:column;align-items:flex-start;}
  .rpt-ts{margin-left:0;}
}
</style>

<!-- Toolbar: export + quick nav รวมกัน -->
<div class="rpt-toolbar no-print">
  <div class="rpt-toolbar-left">
    <button onclick="window.print()" class="btn btn-accent" style="height:36px;font-size:.8rem;">🖨 พิมพ์</button>
    <button onclick="exportCSV()" class="btn btn-ghost" style="height:36px;font-size:.8rem;">📥 CSV</button>
  </div>
  <div class="toolbar-divider"></div>
  <div class="rpt-toolbar-nav">
    <?php foreach ($qnavLinks as $qitem): ?>
    <a href="<?= htmlspecialchars($qitem[1]) ?>" class="qbtn<?= $qitem[2] ? ' active' : '' ?>"><?= $qitem[0] ?></a>
    <?php endforeach; ?>
  </div>
  <div class="toolbar-divider"></div>
  <div class="rpt-toolbar-type">
    <a href="?type=daily"   class="qbtn<?= $reportType==='daily'  ?' active':'' ?>">รายวัน</a>
    <a href="?type=monthly" class="qbtn<?= $reportType==='monthly'?' active':'' ?>">รายเดือน</a>
    <a href="?type=yearly"  class="qbtn<?= $reportType==='yearly' ?' active':'' ?>">รายปี</a>
  </div>
  <span class="rpt-ts">รายงาน<?= $labelRange ?> · <?= date('H:i') ?> น.</span>
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
  <a href="<?= htmlspecialchars($todayNavUrl) ?>" class="pnav-back">↩ ปัจจุบัน</a>
  <?php endif; ?>
  <a href="<?= htmlspecialchars($nextNavUrl) ?>" class="pnav-arrow" title="ถัดไป">&#8250;</a>
</div>

<!-- Service tabs -->
<div class="svc-tabs no-print">
  <a href="<?= htmlspecialchars($baseUrl.'&service=all') ?>" class="svc-tab<?= $serviceType==='all'?' active':'' ?>">
    ทั้งหมด <span class="stab-cnt"><?= $totalAll ?></span>
  </a>
  <a href="<?= htmlspecialchars($baseUrl.'&service=boat') ?>" class="svc-tab boat<?= $serviceType==='boat'?' active boat':'' ?>">
    🚣 เรือพาย <span class="stab-cnt"><?= $boatData['total'] ?></span>
  </a>
  <a href="<?= htmlspecialchars($baseUrl.'&service=room') ?>" class="svc-tab room<?= $serviceType==='room'?' active room':'' ?>">
    🏨 ห้องพัก <span class="stab-cnt"><?= $roomData['total'] ?></span>
  </a>
  <a href="<?= htmlspecialchars($baseUrl.'&service=tent') ?>" class="svc-tab tent<?= $serviceType==='tent'?' active tent':'' ?>">
    ⛺ เต็นท์ <span class="stab-cnt"><?= $tentData['total'] ?></span>
  </a>
  <a href="<?= htmlspecialchars($baseUrl.'&service=checkin') ?>" class="svc-tab checkin<?= $serviceType==='checkin'?' active checkin':'' ?>">
    🚪 ข้อมูลเช็คอิน <span class="stab-cnt"><?= $totalCheckinPeriod ?></span>
  </a>
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
    <a href="booking_report.php" class="btn btn-ghost">รีเซ็ต</a>
  </div>
</form>

<?php if ($serviceType === 'checkin'): ?>
<div class="sec-hd">ข้อมูลเช็คอิน · <?= $labelRange ?></div>

<!-- Check-in KPI cards -->
<div class="kpi-grid" style="margin-bottom:12px;">
  <div class="kpi-card" style="border-left-color:#0d9488;">
    <div class="kpi-icon">🚪</div>
    <div class="kpi-lbl">เช็คอินรวม (ช่วงที่เลือก)</div>
    <div class="kpi-val" style="color:#0d9488;"><?= number_format($totalCheckinPeriod) ?></div>
    <div class="kpi-sub">คน ทุกบริการ</div>
  </div>
  <div class="kpi-card boat">
    <div class="kpi-icon">🚣</div>
    <div class="kpi-lbl">เรือพาย</div>
    <div class="kpi-val" style="color:#1d6fad;"><?= number_format($boatCheckinPeriod) ?></div>
    <div class="kpi-sub">คน</div>
  </div>
  <div class="kpi-card" style="border-left-color:#a07c3a;">
    <div class="kpi-icon">🏨</div>
    <div class="kpi-lbl">ห้องพัก</div>
    <div class="kpi-val" style="color:#a07c3a;"><?= number_format($roomCheckinPeriod) ?></div>
    <div class="kpi-sub">คน</div>
  </div>
  <div class="kpi-card green">
    <div class="kpi-icon">⛺</div>
    <div class="kpi-lbl">เต็นท์</div>
    <div class="kpi-val" style="color:#2e7d32;"><?= number_format($tentCheckinPeriod) ?></div>
    <div class="kpi-sub">คน</div>
  </div>
  <div class="kpi-card" style="border-left-color:#0d9488;">
    <div class="kpi-icon">📅</div>
    <div class="kpi-lbl">เช็คอินวันนี้</div>
    <div class="kpi-val" style="color:#0d9488;"><?= number_format($totalCheckinToday) ?></div>
    <div class="kpi-sub">คน (<?= $today ?>)</div>
  </div>
  <div class="kpi-card blue">
    <div class="kpi-icon">📆</div>
    <div class="kpi-lbl">เช็คอินเดือนนี้</div>
    <div class="kpi-val" style="color:#1d6fad;"><?= number_format($totalCheckinMonth) ?></div>
    <div class="kpi-sub">คน</div>
  </div>
</div>

<!-- Check-in detail table -->
<div class="stat-card" style="margin-bottom:12px;">
  <div class="stat-card-hd">
    <span style="font-size:1.1rem;">🚪</span>
    <span class="stat-card-title">จำนวนผู้เช็คอิน แยกตามบริการ</span>
    <span style="font-size:.72rem;color:var(--muted);margin-left:auto;">นับจากวันเช็คอินจริง เฉพาะที่อนุมัติแล้ว</span>
  </div>
  <div style="overflow-x:auto;">
    <table class="stat-table">
      <thead>
        <tr>
          <th>บริการ</th>
          <th class="num"><span class="stat-period-badge today">วันนี้</span></th>
          <th class="num"><span class="stat-period-badge month">เดือนนี้</span></th>
          <th class="num"><span class="stat-period-badge year">ปีนี้</span></th>
          <th class="num">ช่วงที่เลือก (<?= $labelRange ?>)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="pay-badge svc-boat">🚣 เรือพาย</span></td>
          <td class="num"><?= number_format($boatCheckinToday) ?> คน</td>
          <td class="num"><?= number_format($boatCheckinMonth) ?> คน</td>
          <td class="num"><?= number_format($boatCheckinYear) ?> คน</td>
          <td class="num"><?= number_format($boatCheckinPeriod) ?> คน</td>
        </tr>
        <tr>
          <td><span class="pay-badge svc-room">🏨 ห้องพัก</span></td>
          <td class="num"><?= number_format($roomCheckinToday) ?> คน</td>
          <td class="num"><?= number_format($roomCheckinMonth) ?> คน</td>
          <td class="num"><?= number_format($roomCheckinYear) ?> คน</td>
          <td class="num"><?= number_format($roomCheckinPeriod) ?> คน</td>
        </tr>
        <tr>
          <td><span class="pay-badge svc-tent">⛺ เต็นท์</span></td>
          <td class="num"><?= number_format($tentCheckinToday) ?> คน</td>
          <td class="num"><?= number_format($tentCheckinMonth) ?> คน</td>
          <td class="num"><?= number_format($tentCheckinYear) ?> คน</td>
          <td class="num"><?= number_format($tentCheckinPeriod) ?> คน</td>
        </tr>
        <tr style="background:#f0fdfa;font-weight:800;">
          <td><strong>รวมทั้งหมด</strong></td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinToday) ?> คน</td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinMonth) ?> คน</td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinYear) ?> คน</td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinPeriod) ?> คน</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══ ข้อมูลเช็คอินผู้เยี่ยมชม ═══ -->
<div class="sec-hd">ข้อมูลเช็คอินผู้เยี่ยมชม (<?= $labelRange ?>)</div>
<div class="kpi-grid" style="margin-bottom:12px;">
  <div class="kpi-card" style="border-left-color:#0d9488;"><div class="kpi-icon">🏛</div><div class="kpi-lbl">เช็คอินรวม</div><div class="kpi-val" style="color:#0d9488;"><?= number_format($touristTotal) ?></div><div class="kpi-sub">คน</div></div>
  <div class="kpi-card blue"><div class="kpi-icon">🎓</div><div class="kpi-lbl">นักศึกษา</div><div class="kpi-val" style="color:#1d6fad;"><?= number_format($touristStudent) ?></div><div class="kpi-sub">คน</div></div>
  <div class="kpi-card" style="border-left-color:#7c3aed;"><div class="kpi-icon">👔</div><div class="kpi-lbl">บุคลากร</div><div class="kpi-val" style="color:#7c3aed;"><?= number_format($touristStaff) ?></div><div class="kpi-sub">คน</div></div>
  <div class="kpi-card" style="border-left-color:#d97706;"><div class="kpi-icon">🌏</div><div class="kpi-lbl">นักท่องเที่ยว</div><div class="kpi-val" style="color:#d97706;"><?= number_format($touristVisitor) ?></div><div class="kpi-sub">คน</div></div>
</div>
<div class="stat-card" style="margin-bottom:16px;">
  <div class="stat-card-hd"><span style="font-size:1.1rem;">📋</span><span class="stat-card-title">รายชื่อผู้เช็คอิน</span><span style="font-size:.72rem;color:var(--muted);margin-left:auto;">แสดงสูงสุด 200 รายการ</span></div>
  <div style="overflow-x:auto;"><table class="stat-table">
    <thead><tr><th>#</th><th>ชื่อเล่น</th><th>เพศ</th><th>อายุ</th><th>ประเภท</th><th class="num">วันที่</th><th class="num">เวลา</th></tr></thead>
    <tbody>
      <?php if (empty($touristList)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px;">ไม่พบข้อมูลเช็คอิน</td></tr>
      <?php else: foreach ($touristList as $i2 => $tl2):
        if ($tl2['user_type'] === 'นักศึกษา') { $tc2 = 'background:#e3f2fd;color:#1565c0;'; }
        elseif ($tl2['user_type'] === 'บุคลากร') { $tc2 = 'background:#f3e8ff;color:#7c3aed;'; }
        else { $tc2 = 'background:#fff7ed;color:#d97706;'; }
      ?><tr>
        <td style="color:var(--muted);font-size:.76rem;"><?= $i2+1 ?></td>
        <td style="font-weight:700;"><?= htmlspecialchars($tl2['nickname']) ?></td>
        <td><?= htmlspecialchars($tl2['gender'] ?? '-') ?></td>
        <td><?= (int)$tl2['age'] ?> ปี</td>
        <td><span class="pay-badge" style="<?= $tc2 ?>"><?= htmlspecialchars($tl2['user_type']) ?></span></td>
        <td class="num"><?= htmlspecialchars($tl2['visit_date']) ?></td>
        <td class="num"><?= htmlspecialchars($tl2['visit_time'] ?? '-') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>

<?php endif; // end if checkin ?>
<?php if ($serviceType !== 'checkin'): ?>
<div class="sec-hd">ภาพรวม — <?= $ctxLabel ?> · <?= $labelRange ?></div>
<div class="kpi-grid">
  <div class="kpi-card blue">
    <div class="kpi-icon">📋</div>
    <div class="kpi-lbl">การจองทั้งหมด</div>
    <div class="kpi-val"><?= number_format($ctxTotal) ?></div>
    <div class="kpi-sub">รายการ</div>
  </div>
  <div class="kpi-card green">
    <div class="kpi-icon">✅</div>
    <div class="kpi-lbl"><?= $serviceType==='boat' ? 'ชำระแล้ว' : 'อนุมัติแล้ว' ?></div>
    <div class="kpi-val"><?= number_format($ctxPaid) ?></div>
    <div class="kpi-sub">รายการ</div>
  </div>
  <div class="kpi-card yellow">
    <div class="kpi-icon">⏳</div>
    <div class="kpi-lbl"><?= $serviceType==='boat' ? 'รอชำระ' : 'รอดำเนินการ' ?></div>
    <div class="kpi-val"><?= number_format($ctxWaiting) ?></div>
    <div class="kpi-sub">รายการ</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon">💰</div>
    <div class="kpi-lbl">รายได้รวม</div>
    <?php if ($ctxRevenue > 0): ?>
    <div class="kpi-val" style="font-size:1.3rem;">฿<?= number_format($ctxRevenue, 0) ?></div>
    <div class="kpi-sub">บาท (ชำระแล้ว)</div>
    <?php else: ?>
    <div class="kpi-val" style="font-size:1rem;color:var(--muted);margin-top:6px;">—</div>
    <div class="kpi-sub">ไม่มีข้อมูลรายได้</div>
    <?php endif; ?>
  </div>
  <div class="kpi-card teal">
    <div class="kpi-icon">👥</div>
    <div class="kpi-lbl">จำนวนคน (การจอง)</div>
    <div class="kpi-val"><?= number_format($ctxGuests) ?></div>
    <div class="kpi-sub">คน</div>
  </div>
  <div class="kpi-card" style="border-left-color:#0d9488;">
    <div class="kpi-icon">🚪</div>
    <div class="kpi-lbl">เช็คอินจริง (<?= $labelRange ?>)</div>
    <div class="kpi-val" style="color:#0d9488;"><?= number_format($totalCheckinPeriod) ?></div>
    <div class="kpi-sub">คน (อนุมัติแล้วเท่านั้น)</div>
  </div>
</div>

<?php endif; // end serviceType !== 'checkin' for KPI grid ?>

<!-- ═══ สถิติจำนวนผู้ใช้งาน ═══ -->
<?php if ($serviceType !== 'checkin'): ?>
<div class="sec-hd">สถิติจำนวนผู้ใช้งาน (จำนวนคน)</div>

<!-- Guest counts: วันนี้ / เดือนนี้ / ปีนี้ -->
<div class="stat-card" style="margin-bottom:12px;">
  <div class="stat-card-hd">
    <span style="font-size:1.1rem;">👥</span>
    <span class="stat-card-title">จำนวนผู้ใช้บริการ แยกตามบริการ</span>
    <span style="font-size:.72rem;color:var(--muted);margin-left:auto;">นับจากจำนวนคนในการจอง (guests)</span>
  </div>
  <div style="overflow-x:auto;">
    <table class="stat-table">
      <thead>
        <tr>
          <th>บริการ</th>
          <th class="num"><span class="stat-period-badge today">วันนี้</span></th>
          <th class="num"><span class="stat-period-badge month">เดือนนี้</span></th>
          <th class="num"><span class="stat-period-badge year">ปีนี้</span></th>
          <th class="num">ช่วงที่เลือก (<?= $labelRange ?>)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="pay-badge svc-boat">🚣 เรือพาย</span></td>
          <td class="num"><?= number_format($boatGuestToday) ?> คน</td>
          <td class="num"><?= number_format($boatGuestMonth) ?> คน</td>
          <td class="num"><?= number_format($boatGuestYear) ?> คน</td>
          <td class="num"><?= number_format($boatGuests) ?> คน</td>
        </tr>
        <tr>
          <td><span class="pay-badge svc-room">🏨 ห้องพัก</span></td>
          <td class="num"><?= number_format($roomGuestToday) ?> คน</td>
          <td class="num"><?= number_format($roomGuestMonth) ?> คน</td>
          <td class="num"><?= number_format($roomGuestYear) ?> คน</td>
          <td class="num"><?= number_format($roomGuests) ?> คน</td>
        </tr>
        <tr>
          <td><span class="pay-badge svc-tent">⛺ เต็นท์</span></td>
          <td class="num"><?= number_format($tentGuestToday) ?> คน</td>
          <td class="num"><?= number_format($tentGuestMonth) ?> คน</td>
          <td class="num"><?= number_format($tentGuestYear) ?> คน</td>
          <td class="num"><?= number_format($tentGuests) ?> คน</td>
        </tr>
        <tr style="background:#fafaf8;font-weight:800;">
          <td><strong>รวมทั้งหมด</strong></td>
          <td class="num"><?= number_format($boatGuestToday+$roomGuestToday+$tentGuestToday) ?> คน</td>
          <td class="num"><?= number_format($boatGuestMonth+$roomGuestMonth+$tentGuestMonth) ?> คน</td>
          <td class="num"><?= number_format($boatGuestYear+$roomGuestYear+$tentGuestYear) ?> คน</td>
          <td class="num"><?= number_format($boatGuests+$roomGuests+$tentGuests) ?> คน</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══ สถิติเช็คอิน ═══ -->
<div class="sec-hd">สถิติการเช็คอิน (จำนวนคนที่เข้าใช้บริการจริง)</div>
<div class="stat-card" style="margin-bottom:12px;">
  <div class="stat-card-hd">
    <span style="font-size:1.1rem;">🚪</span>
    <span class="stat-card-title">จำนวนผู้เช็คอิน แยกตามบริการ</span>
    <span style="font-size:.72rem;color:var(--muted);margin-left:auto;">นับจากวันเช็คอินจริง (boat_date / checkin_date) เฉพาะที่อนุมัติแล้ว</span>
  </div>
  <div style="overflow-x:auto;">
    <table class="stat-table">
      <thead>
        <tr>
          <th>บริการ</th>
          <th class="num"><span class="stat-period-badge today">วันนี้</span></th>
          <th class="num"><span class="stat-period-badge month">เดือนนี้</span></th>
          <th class="num"><span class="stat-period-badge year">ปีนี้</span></th>
          <th class="num">ช่วงที่เลือก (<?= $labelRange ?>)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="pay-badge svc-boat">🚣 เรือพาย</span></td>
          <td class="num"><?= number_format($boatCheckinToday) ?> คน</td>
          <td class="num"><?= number_format($boatCheckinMonth) ?> คน</td>
          <td class="num"><?= number_format($boatCheckinYear) ?> คน</td>
          <td class="num"><?= number_format($boatCheckinPeriod) ?> คน</td>
        </tr>
        <tr>
          <td><span class="pay-badge svc-room">🏨 ห้องพัก</span></td>
          <td class="num"><?= number_format($roomCheckinToday) ?> คน</td>
          <td class="num"><?= number_format($roomCheckinMonth) ?> คน</td>
          <td class="num"><?= number_format($roomCheckinYear) ?> คน</td>
          <td class="num"><?= number_format($roomCheckinPeriod) ?> คน</td>
        </tr>
        <tr>
          <td><span class="pay-badge svc-tent">⛺ เต็นท์</span></td>
          <td class="num"><?= number_format($tentCheckinToday) ?> คน</td>
          <td class="num"><?= number_format($tentCheckinMonth) ?> คน</td>
          <td class="num"><?= number_format($tentCheckinYear) ?> คน</td>
          <td class="num"><?= number_format($tentCheckinPeriod) ?> คน</td>
        </tr>
        <tr style="background:#f0fdfa;font-weight:800;">
          <td><strong>รวมทั้งหมด</strong></td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinToday) ?> คน</td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinMonth) ?> คน</td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinYear) ?> คน</td>
          <td class="num" style="color:#0d9488;"><?= number_format($totalCheckinPeriod) ?> คน</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // end serviceType !== 'checkin' for stats section ?>

<!-- ═══ รายได้จากเช่าเรือ ═══ -->
<?php if ($serviceType !== 'checkin'): ?>
<div class="sec-hd">รายได้จากเช่าเรือพาย</div>
<div class="rev-grid">
  <div class="rev-card today-card">
    <div class="rev-period">วันนี้</div>
    <div class="rev-amt">฿<?= number_format($revToday, 0) ?></div>
    <div class="rev-sub"><?= date('d/m/Y', strtotime($today)) ?></div>
    <div class="pay-split">
      <span class="ps-chip ps-cash">💵 สด ฿<?= number_format((float)$_revToday['cash'], 0) ?></span>
      <span class="ps-chip ps-transfer">📱 โอน ฿<?= number_format((float)$_revToday['transfer'], 0) ?></span>
    </div>
    <div class="rev-detail">
      <div class="rev-row">
        <span class="rl">จำนวนลูกค้า (วันนี้)</span>
        <span class="rv"><?= number_format($boatGuestToday) ?> คน</span>
      </div>
      <div class="rev-row">
        <span class="rl">รายการจอง (วันนี้)</span>
        <span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at)='$today' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span>
      </div>
    </div>
  </div>
  <div class="rev-card month-card">
    <div class="rev-period">เดือนนี้ (<?= date('m/Y') ?>)</div>
    <div class="rev-amt">฿<?= number_format($revMonth, 0) ?></div>
    <div class="rev-sub">ยอดชำระสำเร็จสะสม</div>
    <div class="pay-split">
      <span class="ps-chip ps-cash">💵 สด ฿<?= number_format((float)$_revMonth['cash'], 0) ?></span>
      <span class="ps-chip ps-transfer">📱 โอน ฿<?= number_format((float)$_revMonth['transfer'], 0) ?></span>
    </div>
    <div class="rev-detail">
      <div class="rev-row">
        <span class="rl">จำนวนลูกค้า</span>
        <span class="rv"><?= number_format($boatGuestMonth) ?> คน</span>
      </div>
      <div class="rev-row">
        <span class="rl">รายการจอง</span>
        <span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '".date('Y-m-01')."' AND '".date('Y-m-t')."' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span>
      </div>
    </div>
  </div>
  <div class="rev-card year-card">
    <div class="rev-period">ปีนี้ (พ.ศ. <?= $thisYear+543 ?>)</div>
    <div class="rev-amt">฿<?= number_format($revYear, 0) ?></div>
    <div class="rev-sub">ยอดชำระสำเร็จสะสมทั้งปี</div>
    <div class="pay-split">
      <span class="ps-chip ps-cash">💵 สด ฿<?= number_format((float)$_revYear['cash'], 0) ?></span>
      <span class="ps-chip ps-transfer">📱 โอน ฿<?= number_format((float)$_revYear['transfer'], 0) ?></span>
    </div>
    <div class="rev-detail">
      <div class="rev-row">
        <span class="rl">จำนวนลูกค้า</span>
        <span class="rv"><?= number_format($boatGuestYear) ?> คน</span>
      </div>
      <div class="rev-row">
        <span class="rl">รายการจอง</span>
        <span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM boat_bookings WHERE DATE(created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span>
      </div>
    </div>
  </div>
</div>

<!-- ── ห้องพัก ── -->
<?php if ($serviceType === 'all' || $serviceType === 'room'): ?>
<div class="sec-hd">รายได้จากห้องพัก</div>
<div class="rev-grid">
  <div class="rev-card today-card">
    <div class="rev-period">วันนี้</div>
    <div class="rev-amt">฿<?= number_format($revRoomToday, 0) ?></div>
    <div class="rev-sub"><?= date('d/m/Y', strtotime($today)) ?></div>
    <div class="rev-detail">
      <div class="rev-row"><span class="rl">จำนวนลูกค้า</span><span class="rv"><?= number_format($roomGuestToday) ?> คน</span></div>
      <div class="rev-row"><span class="rl">รายการจอง</span><span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at)='$today' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span></div>
    </div>
  </div>
  <div class="rev-card month-card">
    <div class="rev-period">เดือนนี้ (<?= date('m/Y') ?>)</div>
    <div class="rev-amt">฿<?= number_format($revRoomMonth, 0) ?></div>
    <div class="rev-sub">ยอดสะสมเดือนนี้</div>
    <div class="rev-detail">
      <div class="rev-row"><span class="rl">จำนวนลูกค้า</span><span class="rv"><?= number_format($roomGuestMonth) ?> คน</span></div>
      <div class="rev-row"><span class="rl">รายการจอง</span><span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at) BETWEEN '".date('Y-m-01')."' AND '".date('Y-m-t')."' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span></div>
    </div>
  </div>
  <div class="rev-card year-card">
    <div class="rev-period">ปีนี้ (พ.ศ. <?= $thisYear+543 ?>)</div>
    <div class="rev-amt">฿<?= number_format($revRoomYear, 0) ?></div>
    <div class="rev-sub">ยอดสะสมทั้งปี</div>
    <div class="rev-detail">
      <div class="rev-row"><span class="rl">จำนวนลูกค้า</span><span class="rv"><?= number_format($roomGuestYear) ?> คน</span></div>
      <div class="rev-row"><span class="rl">รายการจอง</span><span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM room_bookings WHERE DATE(created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── เต็นท์ ── -->
<?php if ($serviceType === 'all' || $serviceType === 'tent'): ?>
<div class="sec-hd">รายได้จากเต็นท์</div>
<div class="rev-grid">
  <div class="rev-card today-card">
    <div class="rev-period">วันนี้</div>
    <div class="rev-amt">฿<?= number_format($revTentToday, 0) ?></div>
    <div class="rev-sub"><?= date('d/m/Y', strtotime($today)) ?></div>
    <div class="rev-detail">
      <div class="rev-row"><span class="rl">จำนวนลูกค้า</span><span class="rv"><?= number_format($tentGuestToday) ?> คน</span></div>
      <div class="rev-row"><span class="rl">รายการจอง</span><span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at)='$today' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span></div>
    </div>
  </div>
  <div class="rev-card month-card">
    <div class="rev-period">เดือนนี้ (<?= date('m/Y') ?>)</div>
    <div class="rev-amt">฿<?= number_format($revTentMonth, 0) ?></div>
    <div class="rev-sub">ยอดสะสมเดือนนี้</div>
    <div class="rev-detail">
      <div class="rev-row"><span class="rl">จำนวนลูกค้า</span><span class="rv"><?= number_format($tentGuestMonth) ?> คน</span></div>
      <div class="rev-row"><span class="rl">รายการจอง</span><span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at) BETWEEN '".date('Y-m-01')."' AND '".date('Y-m-t')."' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span></div>
    </div>
  </div>
  <div class="rev-card year-card">
    <div class="rev-period">ปีนี้ (พ.ศ. <?= $thisYear+543 ?>)</div>
    <div class="rev-amt">฿<?= number_format($revTentYear, 0) ?></div>
    <div class="rev-sub">ยอดสะสมทั้งปี</div>
    <div class="rev-detail">
      <div class="rev-row"><span class="rl">จำนวนลูกค้า</span><span class="rv"><?= number_format($tentGuestYear) ?> คน</span></div>
      <div class="rev-row"><span class="rl">รายการจอง</span><span class="rv"><?= (int)$conn->query("SELECT COUNT(*) c FROM tent_bookings WHERE DATE(created_at) BETWEEN '{$thisYear}-01-01' AND '{$thisYear}-12-31' AND archived=0")->fetch_assoc()['c'] ?> รายการ</span></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; // end serviceType !== 'checkin' for revenue section ?>

<?php if ($serviceType !== 'checkin'): ?>
<div class="sec-hd">กราฟ</div>
<!-- Charts row 1 -->
<div class="chart-grid">
  <div class="chart-box" style="display:flex;flex-direction:column;justify-content:center;background:#f0fdf4;border:2px solid #bbf7d0;">
    <?php
      // ใช้ข้อมูลช่วงวันที่ที่เลือก (Period) ไม่ใช่ hardcode วันนี้
      $widgetBoat    = $boatCheckinPeriod;
      $widgetRoom    = $roomCheckinPeriod;
      $widgetTent    = $tentCheckinPeriod;
      $widgetWalkin  = $touristTotal; // จาก tourists table ช่วงที่เลือก
      $widgetTotal   = $widgetBoat + $widgetRoom + $widgetTent + $widgetWalkin;
      // label ช่วงเวลา
      if ($dateFrom === $dateTo) {
          $widgetLabel = date('d/m/Y', strtotime($dateFrom));
      } else {
          $widgetLabel = date('d/m/Y', strtotime($dateFrom)) . ' – ' . date('d/m/Y', strtotime($dateTo));
      }
    ?>
    <div class="chart-title" style="color:#15803d;">🚪 ข้อมูลเช็คอิน (<?= htmlspecialchars($widgetLabel) ?>)</div>
    <div style="text-align:center;padding:18px 0 10px;">
      <div style="font-size:3rem;font-weight:800;color:#16a34a;line-height:1;"><?= number_format($widgetTotal) ?></div>
      <div style="font-size:.95rem;color:#6b7280;margin-top:4px;">คนทั้งหมด</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 16px 16px;">
      <div style="background:#fff;border-radius:10px;padding:10px;text-align:center;border:1px solid #d1fae5;">
        <div style="font-size:1.5rem;font-weight:700;color:#0d9488;"><?= number_format($widgetBoat) ?></div>
        <div style="font-size:.8rem;color:#6b7280;">⛵ เรือพาย</div>
      </div>
      <div style="background:#fff;border-radius:10px;padding:10px;text-align:center;border:1px solid #d1fae5;">
        <div style="font-size:1.5rem;font-weight:700;color:#7c3aed;"><?= number_format($widgetRoom) ?></div>
        <div style="font-size:.8rem;color:#6b7280;">🏠 ห้องพัก</div>
      </div>
      <div style="background:#fff;border-radius:10px;padding:10px;text-align:center;border:1px solid #d1fae5;">
        <div style="font-size:1.5rem;font-weight:700;color:#d97706;"><?= number_format($widgetTent) ?></div>
        <div style="font-size:.8rem;color:#6b7280;">⛺ เต็นท์</div>
      </div>
      <div style="background:#fff;border-radius:10px;padding:10px;text-align:center;border:1px solid #d1fae5;">
        <div style="font-size:1.5rem;font-weight:700;color:#db2777;"><?= number_format($widgetWalkin) ?></div>
        <div style="font-size:.8rem;color:#6b7280;">🧍 check-in</div>
      </div>
    </div>
  </div>
  <div class="chart-box">
    <div class="chart-title">💵 รายได้ตามช่วงเวลา (เรือพาย)</div>
    <div class="chart-wrap"><canvas id="chartRevenue"></canvas></div>
  </div>
</div>

<!-- Charts row 1b: Room + Tent revenue -->
<div class="chart-grid">
  <div class="chart-box">
    <div class="chart-title">🏨 รายได้ตามช่วงเวลา (ห้องพัก)</div>
    <div class="chart-wrap"><canvas id="chartRevenueRoom"></canvas></div>
  </div>
  <div class="chart-box">
    <div class="chart-title">⛺ รายได้ตามช่วงเวลา (เต็นท์)</div>
    <div class="chart-wrap"><canvas id="chartRevenueTent"></canvas></div>
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

<?php if ($serviceType === 'all'): ?>
<div class="sec-hd">แยกตามบริการ</div>
<!-- Service breakdown -->
<div class="svc-grid" style="margin-bottom:20px;">
  <div class="svc-card">
    <div class="svc-title"><span class="pay-badge svc-boat">🚣</span> เรือพาย</div>
    <div class="svc-row"><span class="lbl">การจองทั้งหมด</span><span class="val"><?= $boatData['total'] ?></span></div>
    <div class="svc-row"><span class="lbl">ชำระแล้ว</span><span class="val" style="color:#2e7d32;"><?= $boatData['paid'] ?></span></div>
    <div class="svc-row"><span class="lbl">รอชำระ</span><span class="val" style="color:#e65100;"><?= $boatData['waiting'] ?></span></div>
    <div class="svc-row"><span class="lbl">รายได้</span><span class="val">฿<?= number_format((float)$boatData['revenue'], 0) ?></span></div>
  </div>
  <div class="svc-card">
    <div class="svc-title"><span class="pay-badge svc-room">🏨</span> ห้องพัก</div>
    <div class="svc-row"><span class="lbl">การจองทั้งหมด</span><span class="val"><?= $roomData['total'] ?></span></div>
    <div class="svc-row"><span class="lbl">อนุมัติแล้ว</span><span class="val" style="color:#2e7d32;"><?= $roomData['paid'] ?></span></div>
    <div class="svc-row"><span class="lbl">รอดำเนินการ</span><span class="val" style="color:#e65100;"><?= $roomData['waiting'] ?></span></div>
    <div class="svc-row"><span class="lbl">รายได้</span><span class="val">—</span></div>
  </div>
  <div class="svc-card">
    <div class="svc-title"><span class="pay-badge svc-tent">⛺</span> เต็นท์</div>
    <div class="svc-row"><span class="lbl">การจองทั้งหมด</span><span class="val"><?= $tentData['total'] ?></span></div>
    <div class="svc-row"><span class="lbl">อนุมัติแล้ว</span><span class="val" style="color:#2e7d32;"><?= $tentData['paid'] ?></span></div>
    <div class="svc-row"><span class="lbl">รอดำเนินการ</span><span class="val" style="color:#e65100;"><?= $tentData['waiting'] ?></span></div>
    <div class="svc-row"><span class="lbl">รายได้</span><span class="val">—</span></div>
  </div>
</div>
<?php endif; // end serviceType === 'all' for svc-grid ?>

<?php if ($serviceType === 'all' || $serviceType === 'boat'): ?>
<div class="sec-hd">การเงิน (เรือพาย)</div>
<!-- Finance -->
<div class="lm-card" style="margin-bottom:20px;">
  <div class="lm-card-header">
    <span class="lm-card-title">💳 รายงานด้านการเงิน (เรือพาย)</span>
  </div>
  <div class="lm-card-body">
    <div class="fin-grid">
      <div class="fin-card"><div class="fin-lbl">ยอดรวมทั้งหมด</div><div class="fin-val">฿<?= number_format((float)$finData['grand_total'], 2) ?></div></div>
      <div class="fin-card"><div class="fin-lbl">ชำระแล้ว (รวม)</div><div class="fin-val" style="color:#2e7d32;">฿<?= number_format((float)$finData['paid_amt'], 2) ?></div></div>
      <div class="fin-card" style="border-top:3px solid #c2410c;">
        <div class="fin-lbl">💵 เงินสด</div>
        <div class="fin-val" style="color:#c2410c;">฿<?= number_format((float)$finData['cash_amt'], 2) ?></div>
      </div>
      <div class="fin-card" style="border-top:3px solid #1d4ed8;">
        <div class="fin-lbl">📱 โอน / QR Code</div>
        <div class="fin-val" style="color:#1d4ed8;">฿<?= number_format((float)$finData['transfer_amt'], 2) ?></div>
      </div>
      <div class="fin-card"><div class="fin-lbl">ยังไม่ชำระ</div><div class="fin-val" style="color:#dc2626;">฿<?= number_format((float)$finData['unpaid_amt'], 2) ?></div></div>
      <div class="fin-card"><div class="fin-lbl">รอตรวจสอบ</div><div class="fin-val" style="color:#f59e0b;">฿<?= number_format((float)$finData['waiting_amt'], 2) ?></div></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; // end serviceType !== 'checkin' for charts + svc sections ?>

<div class="sec-hd">รายละเอียดการจอง</div>
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
            $clsMap = ['paid'=>'pay-paid','waiting_verify'=>'pay-wait','failed'=>'pay-fail'];
            $cls = isset($clsMap[$ps]) ? $clsMap[$ps] : 'pay-pend';
            $lblMap = ['paid'=>'ชำระแล้ว','waiting_verify'=>'รอตรวจสอบ','failed'=>'ไม่ผ่าน','approved'=>'อนุมัติ','cancelled'=>'ยกเลิก'];
            $lbl = isset($lblMap[$ps]) ? $lblMap[$ps] : 'รอดำเนินการ';
            ?>
            <span class="pay-badge <?= $cls ?>"><?= $lbl ?></span>
          </td>
          <td>
            <?php
            $bs = $r['booking_status'];
            $bclsMap = ['approved'=>'bk-approved','cancelled'=>'bk-cancelled','rejected'=>'bk-cancelled'];
            $bcls = isset($bclsMap[$bs]) ? $bclsMap[$bs] : 'bk-pending';
            $blblMap = ['approved'=>'อนุมัติ','cancelled'=>'ยกเลิก','rejected'=>'ปฏิเสธ'];
            $blbl = isset($blblMap[$bs]) ? $blblMap[$bs] : 'รอดำเนินการ';
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

<div class="sec-hd">เปรียบเทียบช่วงเวลา</div>
<!-- Comparison -->
<div class="cmp-grid" style="margin-bottom:20px;">
  <div class="cmp-card">
    <div class="cmp-period">วันนี้ vs เมื่อวาน</div>
    <div class="cmp-row">
      <div class="cmp-cur"><?= $cmp['today'] ?></div>
      <?php $p=pct($cmp['today'],$cmp['yesterday']); $cls=($p[0]==='+'&&$p!=='+0%')?'up':($p[0]==='-'?'down':'flat'); ?>
      <span class="cmp-pct <?= $cls ?>"><?= $p ?></span>
    </div>
    <div style="font-size:.75rem;color:var(--muted);">วันนี้ <?= $cmp['today'] ?> / เมื่อวาน <?= $cmp['yesterday'] ?> รายการ</div>
  </div>
  <div class="cmp-card">
    <div class="cmp-period">เดือนนี้ vs เดือนก่อน</div>
    <div class="cmp-row">
      <div class="cmp-cur"><?= $cmp['thisMonth'] ?></div>
      <?php $p=pct($cmp['thisMonth'],$cmp['lastMonth']); $cls=($p[0]==='+'&&$p!=='+0%')?'up':($p[0]==='-'?'down':'flat'); ?>
      <span class="cmp-pct <?= $cls ?>"><?= $p ?></span>
    </div>
    <div style="font-size:.75rem;color:var(--muted);">เดือนนี้ <?= $cmp['thisMonth'] ?> / เดือนก่อน <?= $cmp['lastMonth'] ?> รายการ</div>
  </div>
  <div class="cmp-card">
    <div class="cmp-period">ปีนี้ vs ปีก่อน</div>
    <div class="cmp-row">
      <div class="cmp-cur"><?= $cmp['thisYear'] ?></div>
      <?php $p=pct($cmp['thisYear'],$cmp['lastYear']); $cls=($p[0]==='+'&&$p!=='+0%')?'up':($p[0]==='-'?'down':'flat'); ?>
      <span class="cmp-pct <?= $cls ?>"><?= $p ?></span>
    </div>
    <div style="font-size:.75rem;color:var(--muted);">ปีนี้ <?= $cmp['thisYear'] ?> / ปีก่อน <?= $cmp['lastYear'] ?> รายการ</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels    = [<?= $jsLabels ?>];
const dBoat     = [<?= $jsBoat ?>];
const dRoom     = [<?= $jsRoom ?>];
const dTent     = [<?= $jsTent ?>];
const dVisit    = [<?= $jsVisit ?>];
const dRev      = [<?= $jsRevenue ?>];
const dRevRoom  = [<?= $jsRevenueRoom ?>];
const dRevTent  = [<?= $jsRevenueTent ?>];

const opt = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}};

// Booking chart
new Chart(document.getElementById('chartBooking'),{type:'bar',data:{labels,datasets:[
  {label:'เรือพาย',data:dBoat,backgroundColor:'rgba(29,111,173,.7)',borderRadius:4},
  {label:'ห้องพัก',data:dRoom,backgroundColor:'rgba(201,169,110,.7)',borderRadius:4},
  {label:'เต็นท์', data:dTent,backgroundColor:'rgba(46,125,50,.7)',borderRadius:4},
]},options:{...opt,scales:{x:{ticks:{font:{size:10}}},y:{ticks:{font:{size:10}},beginAtZero:true}}}});

const revOpt = {...opt,scales:{x:{ticks:{font:{size:10}}},y:{ticks:{font:{size:10},callback:v=>'฿'+v.toLocaleString()},beginAtZero:true}}};

// Revenue chart — เรือพาย
new Chart(document.getElementById('chartRevenue'),{type:'bar',data:{labels,datasets:[
  {label:'รายได้เรือพาย (฿)',data:dRev,backgroundColor:'rgba(29,111,173,.75)',borderRadius:4}
]},options:revOpt});

// Revenue chart — ห้องพัก
new Chart(document.getElementById('chartRevenueRoom'),{type:'bar',data:{labels,datasets:[
  {label:'รายได้ห้องพัก (฿)',data:dRevRoom,backgroundColor:'rgba(201,169,110,.75)',borderRadius:4}
]},options:revOpt});

// Revenue chart — เต็นท์
new Chart(document.getElementById('chartRevenueTent'),{type:'bar',data:{labels,datasets:[
  {label:'รายได้เต็นท์ (฿)',data:dRevTent,backgroundColor:'rgba(46,125,50,.75)',borderRadius:4}
]},options:revOpt});

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
