<?php
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
if ($conn->connect_error) die("Connect error: " . $conn->connect_error);
$conn->set_charset("utf8mb4");
echo "<pre>";

// แสดง tables ทั้งหมดใน backoffice_db
echo "=== Tables in backoffice_db ===\n";
$res = $conn->query("SHOW TABLES");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_row()) echo "  " . $r[0] . "\n";
} else {
    echo "  (ไม่มี table เลย)\n";
}

// แสดง databases ทั้งหมด
echo "\n=== All Databases ===\n";
$res2 = $conn->query("SHOW DATABASES");
while ($r = $res2->fetch_row()) echo "  " . $r[0] . "\n";

echo "</pre>";
