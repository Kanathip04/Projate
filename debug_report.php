<?php
// Diagnostic — ลบทิ้งหลังแก้เสร็จ
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
if ($conn->connect_error) { die("DB error: ".$conn->connect_error); }

$today = date('Y-m-d');
$dateFrom = $dateTo = $today;

function q(mysqli $c, string $sql, string $label): void {
    $r = $c->query($sql);
    if ($r === false) {
        echo "<b style='color:red'>FAIL [$label]</b>: ".$c->error."<br>SQL: <code>".htmlspecialchars($sql)."</code><br><br>";
    } else {
        echo "<span style='color:green'>OK [$label]</span><br>";
    }
}

echo "<h3>Testing queries for admin_report.php</h3>";

q($conn,"SELECT COUNT(*) total, SUM(payment_status='paid') paid, SUM(payment_status IN('unpaid','pending','waiting_verify')) waiting, SUM(booking_status='cancelled') cancelled, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) revenue FROM boat_bookings WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND archived=0","boatData");

q($conn,"SELECT COUNT(*) total, SUM(booking_status='approved') paid, SUM(booking_status='pending') waiting, SUM(booking_status='cancelled') cancelled, 0 revenue FROM room_bookings WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND archived=0","roomData");

q($conn,"SELECT COUNT(*) total, SUM(booking_status='approved') paid, SUM(booking_status='pending') waiting, SUM(booking_status='cancelled') cancelled, 0 revenue FROM tent_bookings WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND archived=0","tentData");

q($conn,"SELECT COUNT(*) total FROM tourists WHERE DATE(visit_date) BETWEEN '$dateFrom' AND '$dateTo'","visitorData");

q($conn,"SELECT COALESCE(SUM(total_amount),0) grand_total, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount END),0) paid_amt FROM boat_bookings WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND archived=0","finData");

q($conn,"SELECT 'boat' svc, booking_ref ref, full_name, boat_type subtype, created_at, boat_date use_date, total_amount, payment_status, booking_status FROM boat_bookings WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND archived=0 ORDER BY created_at DESC LIMIT 5","boatList");

q($conn,"SELECT 'room' svc, CONCAT('RM',id) ref, full_name, room_type subtype, created_at, checkin_date use_date, 0 total_amount, booking_status payment_status, booking_status FROM room_bookings WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND archived=0 ORDER BY created_at DESC LIMIT 5","roomList");

q($conn,"SELECT 'tent' svc, CONCAT('TN',id) ref, full_name, tent_type subtype, created_at, checkin_date use_date, 0 total_amount, booking_status payment_status, booking_status FROM tent_bookings WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo' AND archived=0 ORDER BY created_at DESC LIMIT 5","tentList");

q($conn,"SELECT COUNT(*) n FROM boat_bookings WHERE DATE(created_at) BETWEEN '$today' AND '$today' AND archived=0","getCount-boat");
q($conn,"SELECT COUNT(*) n FROM room_bookings WHERE DATE(created_at) BETWEEN '$today' AND '$today' AND archived=0","getCount-room");
q($conn,"SELECT COUNT(*) n FROM tent_bookings WHERE DATE(created_at) BETWEEN '$today' AND '$today' AND archived=0","getCount-tent");

echo "<h4>SHOW COLUMNS</h4>";
foreach (['boat_bookings','room_bookings','tent_bookings','tourists'] as $tbl) {
    $r = $conn->query("SHOW COLUMNS FROM $tbl");
    $cols = [];
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    echo "<b>$tbl</b>: ".implode(', ',$cols)."<br>";
}
