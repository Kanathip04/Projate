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
   ตรวจสอบตาราง
========================= */
$checkTable = $conn->query("SHOW TABLES LIKE 'rooms'");
if (!$checkTable || $checkTable->num_rows === 0) {
    die("ไม่พบตาราง rooms");
}

/* =========================
   รับค่าจากฟอร์ม
========================= */
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$room_name = trim($_POST['room_name'] ?? '');
$room_type = trim($_POST['room_type'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$total_rooms = isset($_POST['total_rooms']) ? (int)$_POST['total_rooms'] : 5;
$max_guests = isset($_POST['max_guests']) ? (int)$_POST['max_guests'] : 2;
$room_size = trim($_POST['room_size'] ?? '');
$bed_type = trim($_POST['bed_type'] ?? '');

$status_raw = trim($_POST['status'] ?? 'show');
if ($status_raw === 'show' || $status_raw === 'แสดง' || $status_raw === '1') {
    $status = 'show';
} elseif ($status_raw === 'hide' || $status_raw === 'ซ่อน' || $status_raw === '0') {
    $status = 'hide';
} else {
    $status = 'show';
}

if ($room_name === '' || $room_type === '') {
    redirect_back("กรุณากรอกชื่อห้องพักและประเภทห้อง", $id);
}

if ($total_rooms <= 0) $total_rooms = 5;
if ($max_guests <= 0) $max_guests = 2;

/* =========================
   เตรียมโฟลเดอร์อัปโหลด
========================= */
$uploadDir = __DIR__ . '/uploads/rooms/';
$dbUploadPathPrefix = 'uploads/rooms/';

if (!is_dir(__DIR__ . '/uploads')) {
    if (!mkdir(__DIR__ . '/uploads', 0777, true)) {
        redirect_back("สร้างโฟลเดอร์ uploads ไม่สำเร็จ", $id);
    }
}

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        redirect_back("สร้างโฟลเดอร์ uploads/rooms ไม่สำเร็จ", $id);
    }
}

if (!is_writable($uploadDir)) {
    redirect_back("โฟลเดอร์ uploads/rooms เขียนไฟล์ไม่ได้ กรุณาตั้ง permission ของโฟลเดอร์", $id);
}

/* =========================
   อัปโหลดรูป
========================= */
$image_path = '';

if (isset($_FILES['room_image']) && is_array($_FILES['room_image'])) {

    if ($_FILES['room_image']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['room_image']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกินค่า upload_max_filesize ในเซิร์ฟเวอร์',
                UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกินค่าที่ฟอร์มกำหนด',
                UPLOAD_ERR_PARTIAL    => 'ไฟล์ถูกอัปโหลดมาไม่ครบ',
                UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มี temp folder',
                UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
                UPLOAD_ERR_EXTENSION  => 'มี extension ของ PHP ขัดขวางการอัปโหลดไฟล์',
            ];
            $msg = $uploadErrors[$_FILES['room_image']['error']] ?? 'เกิดข้อผิดพลาดระหว่างอัปโหลดรูป';
            redirect_back($msg, $id);
        }

        $tmpName = $_FILES['room_image']['tmp_name'];
        $originalName = $_FILES['room_image']['name'];
        $fileSize = (int)$_FILES['room_image']['size'];

        if (!is_uploaded_file($tmpName)) {
            redirect_back("ไม่พบไฟล์อัปโหลดที่ถูกต้อง", $id);
        }

        if ($fileSize <= 0) {
            redirect_back("ไฟล์รูปไม่ถูกต้อง", $id);
        }

        if ($fileSize > 5 * 1024 * 1024) {
            redirect_back("ไฟล์รูปต้องมีขนาดไม่เกิน 5MB", $id);
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($ext, $allowed, true)) {
            redirect_back("รองรับเฉพาะไฟล์ jpg, jpeg, png, webp, gif", $id);
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            redirect_back("ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ", $id);
        }

        $newName = 'room_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $targetFullPath = $uploadDir . $newName;
        $dbPath = $dbUploadPathPrefix . $newName;

        if (!move_uploaded_file($tmpName, $targetFullPath)) {
            redirect_back("ย้ายไฟล์รูปไม่สำเร็จ กรุณาตรวจสอบ permission ของ uploads/rooms", $id);
        }

        $image_path = $dbPath;
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
        if (!empty($oldImage)) {
            $oldFullPath = __DIR__ . '/' . ltrim($oldImage, '/');
            if (file_exists($oldFullPath)) {
                @unlink($oldFullPath);
            }
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