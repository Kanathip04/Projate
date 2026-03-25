<?php
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$full_name = $_POST['full_name'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$room_type = $_POST['room_type'] ?? '';
$guests = $_POST['guests'] ?? 1;
$checkin = $_POST['checkin_date'] ?? '';
$checkout = $_POST['checkout_date'] ?? '';
$note = $_POST['note'] ?? '';

if ($full_name == "" || $phone == "" || $checkin == "" || $checkout == "") {
    die("กรอกข้อมูลไม่ครบ");
}

$stmt = $conn->prepare("
INSERT INTO room_bookings 
(full_name, phone, email, room_type, guests, checkin_date, checkout_date, note)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssisss",
    $full_name,
    $phone,
    $email,
    $room_type,
    $guests,
    $checkin,
    $checkout,
    $note
);

if ($stmt->execute()) {
    echo "<script>alert('จองสำเร็จ'); window.location='booking_room.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}