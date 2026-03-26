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
   helper
========================= */
function redirect_back($message, $editId = 0) {
    $_SESSION['room_msg'] = $message;
    $url = "manage_rooms.php";
    if ($editId > 0) {
        $url .= "?edit=" . $editId;
    }
    header("Location: " . $url);
    exit;
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
   รองรับชื่อ field สำรองเผื่อไฟล์เดิมบางจุดยังใช้ชื่อเก่า
========================= */
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$room_name = trim(
    $_POST['room_name']
    ?? $_POST['name']
    ?? $_POST['room_title']
    ?? ''
);

$room_type = trim(
    $_POST['room_type']
    ?? $_POST['type']
    ?? ''
);

$description = trim($_POST['description'] ?? $_POST['detail'] ?? '');
$price       = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$total_rooms = isset($_POST['total_rooms']) ? (int)$_POST['total_rooms'] : 5;
$max_guests  = isset($_POST['max_guests']) ? (int)$_POST['max_guests'] : (isset($_POST['capacity']) ? (int)$_POST['capacity'] : 2);
$room_size   = trim($_POST['room_size'] ?? '');
$bed_type    = trim($_POST['bed_type'] ?? '');

/* =========================
   แปลงค่า status ให้ตรงกับฐานข้อมูล
========================= */
$status_raw = trim($_POST['status'] ?? 'show');

if ($status_raw === 'show' || $status_raw === 'แสดง' || $status_raw === '1') {
    $status = 'show';
} elseif ($status_raw === 'hide' || $status_raw === 'ซ่อน' || $status_raw === '0') {
    $status = 'hide';
} else {
    $status = 'show';
}

/* =========================
   validate
========================= */
if ($room_name === '' || $room_type === '') {
    redirect_back("กรุณากรอกชื่อห้องพักและประเภทห้อง", $id);
}

if ($total_rooms <= 0) {
    $total_rooms = 5;
}

if ($max_guests <= 0) {
    $max_guests = 2;
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
            redirect_back("รองรับเฉพาะไฟล์รูป jpg, jpeg, png, webp, gif", $id);
        }

        $newName = 'room_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target  = 'uploads/rooms/' . $newName;

        if (move_uploaded_file($_FILES['room_image']['tmp_name'], $target)) {
            $image_path = $target;
        } else {
            redirect_back("อัปโหลดรูปไม่สำเร็จ", $id);
        }
    } else {
        redirect_back("เกิดข้อผิดพลาดระหว่างอัปโหลดรูป", $id);
    }
}

/* =========================
   เพิ่ม / แก้ไขข้อมูล
========================= */
if ($id > 0) {

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

    if ($image_path === '') {
        $image_path = $oldImage;
    } else {
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
        redirect_back("อัปเดตข้อมูลไม่สำเร็จ: " . $stmt->error, $id);
    }

    $stmt->close();
    $_SESSION['room_msg'] = "อัปเดตข้อมูลห้องพักเรียบร้อยแล้ว";

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
        redirect_back("บันทึกข้อมูลไม่สำเร็จ: " . $stmt->error, 0);
    }

    $stmt->close();
    $_SESSION['room_msg'] = "บันทึกห้องพักเรียบร้อยแล้ว";
}

header("Location: manage_rooms.php");
exit;
?>