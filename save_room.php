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

/* =========================
   ตรวจสอบว่าตาราง rooms มีอยู่จริง
========================= */
$checkTable = $conn->query("SHOW TABLES LIKE 'rooms'");
if (!$checkTable || $checkTable->num_rows === 0) {
    die("ไม่พบตาราง rooms");
}

/* =========================
   รับค่าจากฟอร์ม
========================= */
$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$room_name   = trim($_POST['room_name'] ?? '');
$room_type   = trim($_POST['room_type'] ?? '');
$description = trim($_POST['description'] ?? '');
$price       = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$total_rooms = isset($_POST['total_rooms']) ? (int)$_POST['total_rooms'] : 5;
$max_guests  = isset($_POST['max_guests']) ? (int)$_POST['max_guests'] : 2;
$room_size   = trim($_POST['room_size'] ?? '');
$bed_type    = trim($_POST['bed_type'] ?? '');
$status      = trim($_POST['status'] ?? 'show');

if ($room_name === '' || $room_type === '') {
    die("กรุณากรอกชื่อห้องพักและประเภทห้อง");
}

if ($total_rooms <= 0) {
    $total_rooms = 5;
}

if ($max_guests <= 0) {
    $max_guests = 2;
}

if ($status !== 'show' && $status !== 'hide') {
    $status = 'show';
}

/* =========================
   อัปโหลดรูป
========================= */
$image_path = '';

if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

if (!is_dir('uploads/rooms')) {
    mkdir('uploads/rooms', 0777, true);
}

if (isset($_FILES['room_image']) && !empty($_FILES['room_image']['name'])) {
    if ($_FILES['room_image']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($ext, $allowed, true)) {
            die("รองรับเฉพาะไฟล์รูป jpg, jpeg, png, webp, gif");
        }

        $newName = 'room_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target  = 'uploads/rooms/' . $newName;

        if (move_uploaded_file($_FILES['room_image']['tmp_name'], $target)) {
            $image_path = $target;
        } else {
            die("อัปโหลดรูปไม่สำเร็จ");
        }
    } else {
        die("เกิดข้อผิดพลาดระหว่างอัปโหลดรูป");
    }
}

/* =========================
   เพิ่ม / แก้ไขข้อมูล
========================= */
if ($id > 0) {

    // ดึงรูปเดิมก่อน
    $oldImage = '';
    $stmtOld = $conn->prepare("SELECT image_path FROM rooms WHERE id = ?");
    if (!$stmtOld) {
        die("Prepare SELECT failed: " . $conn->error);
    }

    $stmtOld->bind_param("i", $id);
    $stmtOld->execute();
    $resOld = $stmtOld->get_result();
    if ($resOld && $resOld->num_rows > 0) {
        $oldRow = $resOld->fetch_assoc();
        $oldImage = $oldRow['image_path'] ?? '';
    }
    $stmtOld->close();

    // ถ้าไม่ได้อัปโหลดรูปใหม่ ให้ใช้รูปเดิม
    if ($image_path === '') {
        $image_path = $oldImage;
    } else {
        // ถ้ามีรูปใหม่ และมีรูปเก่า ให้ลบรูปเก่า
        if (!empty($oldImage) && file_exists($oldImage)) {
            @unlink($oldImage);
        }
    }

    $sql = "UPDATE rooms SET
                room_name   = ?,
                room_type   = ?,
                description = ?,
                price       = ?,
                total_rooms = ?,
                max_guests  = ?,
                room_size   = ?,
                bed_type    = ?,
                image_path  = ?,
                status      = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare UPDATE failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssdiissssi",
        $room_name,
        $room_type,
        $description,
        $price,
        $total_rooms,
        $max_guests,
        $room_size,
        $bed_type,
        $image_path,
        $status,
        $id
    );

    if (!$stmt->execute()) {
        die("Execute UPDATE failed: " . $stmt->error);
    }

    $stmt->close();

} else {

    $sql = "INSERT INTO rooms (
                room_name,
                room_type,
                description,
                price,
                total_rooms,
                max_guests,
                room_size,
                bed_type,
                image_path,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare INSERT failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssdiissss",
        $room_name,
        $room_type,
        $description,
        $price,
        $total_rooms,
        $max_guests,
        $room_size,
        $bed_type,
        $image_path,
        $status
    );

    if (!$stmt->execute()) {
        die("Execute INSERT failed: " . $stmt->error);
    }

    $stmt->close();
}

header("Location: manage_rooms.php?success=1");
exit;
?>