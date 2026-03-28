<?php
// get_calendar_events.php
header('Content-Type: application/json');

// 1. เชื่อมต่อฐานข้อมูล
$conn = new mysqli("localhost", "root", "", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed"]);
    exit;
}

/**
 * 2. คิวรีข้อมูลสรุปรายวันและแยกประเภทกลุ่ม
 * เราจะดึงข้อมูลสรุปว่าในแต่ละวัน (visit_date) มี user_type แต่ละประเภทกี่คน
 */
$sql = "SELECT 
            visit_date, 
            user_type, 
            COUNT(*) as amount 
        FROM tourists 
        WHERE visit_date IS NOT NULL 
          AND visit_date != '' 
          AND visit_date != '0000-00-00'
        GROUP BY visit_date, user_type 
        ORDER BY visit_date ASC";

$result = $conn->query($sql);

$events = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        $events[] = [
            'date' => $row['visit_date'],  // วันที่ (YYYY-MM-DD)
            'type' => $row['user_type'],   // ประเภท (นักศึกษา, บุคลากร, ฯลฯ)
            'count' => (int)$row['amount'] // จำนวนคนในกลุ่มนั้น
        ];
    }
}

/**
 * 3. ส่งออกข้อมูลเป็น JSON
 * ข้อมูลจะส่งออกไปในรูปแบบ List ของ Object เพื่อให้ JavaScript นำไป Loop แสดงผลต่อ
 */
echo json_encode($events);

$conn->close();
?>