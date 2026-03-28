<?php
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nickname   = trim($_POST['nickname'] ?? '');
    $gender     = trim($_POST['gender'] ?? '');
    $age        = trim($_POST['age'] ?? '');
    $u_type     = trim($_POST['user_type'] ?? '');
    $visit_date = trim($_POST['visit_date'] ?? '');
    $visit_time = trim($_POST['visit_time'] ?? '');

    if ($nickname === '' || $gender === '' || $u_type === '' || $visit_date === '' || $visit_time === '') {
        echo "<script>alert('กรุณากรอกข้อมูลให้ครบ'); window.history.back();</script>";
        exit;
    }

    if ($age === '') {
        $age = null;
    } else {
        $age = (int)$age;
    }

    $stmt = $conn->prepare("INSERT INTO tourists (nickname, gender, age, user_type, visit_date, visit_time) VALUES (?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssisss", $nickname, $gender, $age, $u_type, $visit_date, $visit_time);

    if ($stmt->execute()) {
        echo "<script>alert('บันทึกข้อมูลเรียบร้อย'); window.location.href='view_data.php';</script>";
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>