<?php
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    die("Connection failed: " . $conn->connect_error);
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE visit_date = CURDATE()";

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $where .= " AND (nickname LIKE '%$safe%' OR user_type LIKE '%$safe%')";
}

$sql = "SELECT id, nickname, user_type, created_at, visit_time
        FROM tourists
        $where
        ORDER BY id DESC";

$result = $conn->query($sql);
if (!$result) {
    http_response_code(500);
    die("SQL Error: " . $conn->error);
}

if ($result->num_rows > 0) {
    $i = 1;
    while ($row = $result->fetch_assoc()) {

        $type = $row['user_type'];
        $badge = ($type == 'นักศึกษา') ? 'bg-stu'
               : (($type == 'บุคลากร') ? 'bg-staff' : 'bg-tour');

        $timeText = '-';
        if (!empty($row['created_at'])) {
            $ts = strtotime($row['created_at']);
            if ($ts !== false) $timeText = date('H:i', $ts);
        } elseif (!empty($row['visit_time'])) {
            $ts2 = strtotime($row['visit_time']);
            if ($ts2 !== false) $timeText = date('H:i', $ts2);
        }

        echo "<tr>";
        echo "<td>" . $i++ . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['nickname']) . "</strong></td>";
        echo "<td><span class='badge {$badge}'>" . htmlspecialchars($type) . "</span></td>";
        echo "<td>" . $timeText . "</td>";
        echo "<td>
                <a class='delete'
                   href='delete_tourist.php?id=" . (int)$row['id'] . "'
                   onclick=\"return confirm('ยืนยันการลบ?')\">ลบ</a>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center;color:#999;'>ไม่มีข้อมูลวันนี้</td></tr>";
}

$conn->close();