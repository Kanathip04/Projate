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

$sql = "SELECT id, nickname, gender, age, user_type, created_at, visit_time
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
        $badgeClass = ($type === 'นักศึกษา') ? 'student'
                    : (($type === 'บุคลากร') ? 'staff' : 'tourist');

        $timeText = '-';
        if (!empty($row['visit_time'])) {
            $ts = strtotime($row['visit_time']);
            if ($ts !== false) $timeText = date('H:i', $ts);
        } elseif (!empty($row['created_at'])) {
            $ts2 = strtotime($row['created_at']);
            if ($ts2 !== false) $timeText = date('H:i', $ts2);
        }

        $gender = htmlspecialchars($row['gender'] ?? '-');
        $age    = ($row['age'] !== null && $row['age'] !== '') ? (int)$row['age'] . ' ปี' : '-';

        echo "<tr>";
        echo "<td style='color:#7b8091;font-weight:700;'>" . $i++ . "</td>";
        echo "<td class='name-cell'>" . htmlspecialchars($row['nickname']) . "</td>";
        echo "<td>" . $gender . "</td>";
        echo "<td>" . $age . "</td>";
        echo "<td><span class='badge {$badgeClass}'>" . htmlspecialchars($type) . "</span></td>";
        echo "<td class='time-cell'>" . $timeText . "</td>";
        echo "<td>
                <a class='btn btn-danger btn-sm'
                   href='delete_tourist.php?id=" . (int)$row['id'] . "'
                   onclick=\"return confirm('ยืนยันการลบ?')\">ลบ</a>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7' style='text-align:center;color:#999;padding:28px;'>ไม่มีข้อมูลวันนี้</td></tr>";
}

$conn->close();