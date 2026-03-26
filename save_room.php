<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$room_name   = trim($_POST['room_name'] ?? '');
$room_type   = trim($_POST['room_type'] ?? '');
$description = trim($_POST['description'] ?? '');
$price       = (float)($_POST['price'] ?? 0);
$total_rooms = (int)($_POST['total_rooms'] ?? 5);
$max_guests  = (int)($_POST['max_guests'] ?? 2);
$room_size   = trim($_POST['room_size'] ?? '');
$bed_type    = trim($_POST['bed_type'] ?? '');
$status      = ($_POST['status'] ?? 'show') === 'hide' ? 'hide' : 'show';

$image_path = null;

if (!is_dir('uploads/rooms')) {
    mkdir('uploads/rooms', 0777, true);
}

if (!empty($_FILES['room_image']['name'])) {
    $tmpName = $_FILES['room_image']['tmp_name'];
    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['room_image']['name']);
    $targetPath = 'uploads/rooms/' . $fileName;

    if (move_uploaded_file($tmpName, $targetPath)) {
        $image_path = $targetPath;
    }
}

if ($id > 0) {
    if ($image_path) {
        $stmt = $conn->prepare("UPDATE rooms SET room_name=?, room_type=?, description=?, price=?, total_rooms=?, max_guests=?, room_size=?, bed_type=?, image_path=?, status=? WHERE id=?");
        $stmt->bind_param("sssdiissssi", $room_name, $room_type, $description, $price, $total_rooms, $max_guests, $room_size, $bed_type, $image_path, $status, $id);
    } else {
        $stmt = $conn->prepare("UPDATE rooms SET room_name=?, room_type=?, description=?, price=?, total_rooms=?, max_guests=?, room_size=?, bed_type=?, status=? WHERE id=?");
        $stmt->bind_param("sssdiisssi", $room_name, $room_type, $description, $price, $total_rooms, $max_guests, $room_size, $bed_type, $status, $id);
    }
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO rooms (room_name, room_type, description, price, total_rooms, max_guests, room_size, bed_type, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdiissss", $room_name, $room_type, $description, $price, $total_rooms, $max_guests, $room_size, $bed_type, $image_path, $status);
    $stmt->execute();
    $stmt->close();
}

header("Location: manage_rooms.php");
exit;