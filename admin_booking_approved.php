<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function bookingStatusMap() {
    return [
        'pending' => [
            'label' => 'รออนุมัติ',
            'class' => 'status-pending'
        ],
        'approved' => [
            'label' => 'อนุมัติแล้ว',
            'class' => 'status-approved'
        ],
        'cancelled' => [
            'label' => 'ยกเลิก',
            'class' => 'status-cancelled'
        ],
    ];
}

function bookingStatusLabel($status) {
    $map = bookingStatusMap();
    return $map[$status]['label'] ?? $status;
}

function bookingStatusClass($status) {
    $map = bookingStatusMap();
    return $map[$status]['class'] ?? 'status-default';
}

$message = "";
$message_type = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $allowed_status = ['pending', 'approved', 'cancelled'];

    if ($action === 'update_booking') {
        $id             = (int)($_POST['id'] ?? 0);
        $full_name      = trim($_POST['full_name'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $room_type      = trim($_POST['room_type'] ?? '');
        $guests         = (int)($_POST['guests'] ?? 1);
        $checkin_date   = trim($_POST['checkin_date'] ?? '');
        $checkout_date  = trim($_POST['checkout_date'] ?? '');
        $note           = trim($_POST['note'] ?? '');
        $booking_status = trim($_POST['booking_status'] ?? 'approved');

        if (!in_array($booking_status, $allowed_status, true)) {
            $booking_status = 'approved';
        }

        if (
            $id <= 0 ||
            $full_name === '' ||
            $phone === '' ||
            $room_type === '' ||
            $checkin_date === '' ||
            $checkout_date === ''
        ) {
            $message = "กรอกข้อมูลไม่ครบ";
            $message_type = "error";
        } elseif (strtotime($checkout_date) <= strtotime($checkin_date)) {
            $message = "วันเช็คเอาท์ต้องมากกว่าวันเช็คอิน";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("
                UPDATE room_bookings
                SET full_name = ?, phone = ?, email = ?, room_type = ?, guests = ?, checkin_date = ?, checkout_date = ?, note = ?, booking_status = ?
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "ssssissssi",
                    $full_name,
                    $phone,
                    $email,
                    $room_type,
                    $guests,
                    $checkin_date,
                    $checkout_date,
                    $note,
                    $booking_status,
                    $id
                );

                if ($stmt->execute()) {
                    $message = "อัปเดตข้อมูลอนุมัติเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "อัปเดตไม่สำเร็จ: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "Prepare UPDATE ไม่สำเร็จ: " . $conn->error;
                $message_type = "error";
            }
        }
    }

    if ($action === 'set_pending') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE room_bookings SET booking_status = 'pending' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    header("Location: admin_booking_list.php?msg=" . urlencode("ย้ายรายการกลับไปหน้ารออนุมัติแล้ว") . "&type=success");
                    exit;
                } else {
                    $message = "เปลี่ยนสถานะไม่สำเร็จ";
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'set_cancelled') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE room_bookings SET booking_status = 'cancelled' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    header("Location: admin_booking_list.php?msg=" . urlencode("ย้ายรายการไปสถานะยกเลิกแล้ว") . "&type=success");
                    exit;
                } else {
                    $message = "เปลี่ยนสถานะไม่สำเร็จ";
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'delete_booking') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM room_bookings WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "ลบข้อมูลเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "ลบข้อมูลไม่สำเร็จ: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }

    header("Location: {$currentPage}?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

$search = trim($_GET['search'] ?? '');
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$edit_data = null;
if ($edit_id > 0) {
    $stmtEdit = $conn->prepare("SELECT * FROM room_bookings WHERE id = ? AND archived = 0 AND booking_status = 'approved' LIMIT 1");
    if ($stmtEdit) {
        $stmtEdit->bind_param("i", $edit_id);
        $stmtEdit->execute();
        $resEdit = $stmtEdit->get_result();
        $edit_data = $resEdit->fetch_assoc();
        $stmtEdit->close();
    }
}

$stat_approved = 0;
$sqlStat = "SELECT COUNT(*) AS total FROM room_bookings WHERE archived = 0 AND booking_status = 'approved'";
$resStat = $conn->query($sqlStat);
if ($resStat && $rowStat = $resStat->fetch_assoc()) {
    $stat_approved = (int)$rowStat['total'];
}

$where = "WHERE archived = 0 AND booking_status = 'approved'";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (
        full_name LIKE ?
        OR phone LIKE ?
        OR email LIKE ?
        OR room_type LIKE ?
        OR note LIKE ?
    )";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sssss";
}

$sql = "SELECT * FROM room_bookings {$where} ORDER BY id DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare SELECT ไม่สำเร็จ: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = "รายการอนุมัติแล้ว";
$activeMenu = "booking_approved";
include 'admin_layout_top.php';
?>

<style>
.booking-page{
    max-width: 1500px;
    margin: 0 auto;
}
.hero-approved{
    background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
    color: #fff;
    border-radius: 28px;
    padding: 28px;
    margin-bottom: 24px;
    box-shadow: 0 18px 40px rgba(20,184,166,.18);
}
.hero-approved h1{
    margin: 0 0 8px;
    font-size: 34px;
    font-weight: 800;
}
.hero-approved p{
    margin: 0;
    opacity: .95;
    font-size: 15px;
}
.hero-actions{
    margin-top: 18px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.quick-link{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    border: 1px solid rgba(255,255,255,.25);
    background: rgba(255,255,255,.14);
    color: #fff;
    transition: .2s ease;
}
.quick-link:hover{
    transform: translateY(-1px);
    background: rgba(255,255,255,.2);
}
.alert-box{
    margin-bottom: 20px;
    padding: 14px 18px;
    border-radius: 16px;
    font-weight: 700;
}
.alert-box.success{
    background:#edf9f0;
    color:#1d7d3f;
    border:1px solid #c8ebd2;
}
.alert-box.error{
    background:#fff1f2;
    color:#be123c;
    border:1px solid #fecdd3;
}
.stat-row{
    display:grid;
    grid-template-columns:repeat(1, minmax(0, 1fr));
    gap:18px;
    margin-bottom:22px;
}
.stat-card{
    background:#fff;
    border-radius:22px;
    padding:22px;
    box-shadow:0 12px 30px rgba(15,23,42,.06);
    border:1px solid #edf1f6;
}
.stat-card .label{
    font-size:14px;
    color:#6b7280;
    font-weight:700;
    margin-bottom:10px;
}
.stat-card .value{
    font-size:34px;
    font-weight:800;
    line-height:1;
    margin-bottom:8px;
}
.stat-card .sub{
    font-size:13px;
    color:#6b7280;
}
.main-grid{
    display:block;
}
.section-title{
    margin:0 0 6px;
    font-size:24px;
    font-weight:800;
    color:#101828;
}
.section-desc{
    margin:0 0 18px;
    color:#6b7280;
    font-size:14px;
}
.empty-box{
    background:#f8fafc;
    border:1px dashed #d7dee8;
    border-radius:18px;
    padding:18px;
    color:#6b7280;
    line-height:1.6;
}
.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}
.form-group{
    margin-bottom:14px;
}
.form-group label{
    display:block;
    margin-bottom:7px;
    font-size:14px;
    font-weight:700;
    color:#18212f;
}
.form-control{
    width:100%;
    background:#fff;
    border:1px solid #e5eaf1;
    border-radius:14px;
    padding:12px 14px;
    font-size:15px;
    color:#18212f;
    outline:none;
    transition:.18s ease;
}
.form-control:focus{
    border-color:#14b8a6;
    box-shadow:0 0 0 4px rgba(20,184,166,.10);
}
textarea.form-control{
    min-height:120px;
    resize:vertical;
}
.filter-box{
    background:#f8fafc;
    border:1px solid #edf1f6;
    border-radius:18px;
    padding:18px;
    margin-bottom:18px;
}
.filter-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:end;
}
.btn{
    border:none;
    border-radius:14px;
    padding:11px 16px;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition:.18s ease;
    white-space:nowrap;
}
.btn:hover{ transform:translateY(-1px); }
.btn-primary{ background:#14b8a6; color:#fff; }
.btn-secondary{ background:#6b7280; color:#fff; }
.btn-info{ background:#1d3557; color:#fff; }
.btn-danger{ background:#dc4c64; color:#fff; }
.btn-warning{ background:#f59e0b; color:#fff; }
.btn-sm{
    padding:9px 12px;
    border-radius:12px;
    font-size:13px;
}
.table-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:14px;
}
.table-meta{
    color:#6b7280;
    font-size:14px;
}
.table-wrap{
    overflow:auto;
    border:1px solid #edf1f6;
    border-radius:18px;
}
.table{
    width:100%;
    min-width:1200px;
    border-collapse:separate;
    border-spacing:0;
    background:#fff;
}
.table thead th{
    background:#f8fafc;
    color:#334155;
    font-size:14px;
    font-weight:800;
    padding:14px 12px;
    text-align:left;
    border-bottom:1px solid #e8edf4;
}
.table tbody td{
    padding:14px 12px;
    font-size:14px;
    vertical-align:top;
    border-bottom:1px solid #eef2f7;
}
.table tbody tr:hover{
    background:#fcfdff;
}
.name-cell strong{
    display:block;
    margin-bottom:4px;
    font-size:15px;
}
.muted{
    color:#6b7280;
    font-size:13px;
    line-height:1.5;
}
.status-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:95px;
    padding:7px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.status-pending{
    background:#fff4cc;
    color:#9a6700;
}
.status-approved{
    background:#dcfce7;
    color:#166534;
}
.status-cancelled{
    background:#fee2e2;
    color:#991b1b;
}
.status-default{
    background:#e5e7eb;
    color:#374151;
}
.action-group{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
}
.inline-form{
    display:inline;
    margin:0;
}
.table-empty{
    text-align:center;
    padding:34px 18px;
    color:#6b7280;
    background:#fff;
}
@media (max-width: 1180px){
    .main-grid{ grid-template-columns:1fr; }
    .sticky-box{ position:static; }
}
@media (max-width: 860px){
    .form-grid{ grid-template-columns:1fr; }
    .hero-approved h1{ font-size:28px; }
}
</style>

<div class="booking-page">

    <div class="hero-approved">
        <h1>รายการอนุมัติแล้ว</h1>
        <p>หน้านี้แสดงเฉพาะรายการที่ผ่านการอนุมัติแล้ว และใช้สำหรับแก้ไขข้อมูลการอนุมัติเท่านั้น</p>

        <div class="hero-actions">
            <a href="admin_booking_list.php" class="quick-link">กลับไปหน้ารออนุมัติ</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert-box <?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <div class="stat-row">
        <div class="stat-card">
            <div class="label">รายการอนุมัติแล้วทั้งหมด</div>
            <div class="value"><?php echo $stat_approved; ?></div>
            <div class="sub">แสดงเฉพาะข้อมูลที่สถานะเป็นอนุมัติแล้ว</div>
        </div>
    </div>
        <div class="panel">
            <div class="table-head">
                <div>
                    <h2 class="section-title" style="margin-bottom:4px;">รายการอนุมัติแล้วทั้งหมด</h2>
                    <div class="table-meta">แสดงเฉพาะรายการสถานะอนุมัติแล้ว และใช้จัดการข้อมูลอนุมัติ</div>
                </div>
            </div>

            <form method="GET" class="filter-box">
                <div class="form-grid">
                    <div class="form-group">
                        <label>ค้นหา</label>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="ชื่อ, เบอร์โทร, อีเมล, ห้อง, หมายเหตุ"
                            value="<?php echo h($search); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>การทำงาน</label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">ค้นหา</button>
                            <a href="<?php echo h($currentPage); ?>" class="btn btn-secondary">รีเซ็ต</a>
                            <a href="admin_booking_list.php" class="btn btn-info">กลับหน้ารออนุมัติ</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th style="width:220px;">ผู้จอง</th>
                            <th style="width:220px;">ติดต่อ</th>
                            <th style="width:140px;">ห้อง</th>
                            <th style="width:110px;">ผู้เข้าพัก</th>
                            <th style="width:170px;">วันเข้าพัก</th>
                            <th style="width:170px;">หมายเหตุ</th>
                            <th style="width:120px;">สถานะ</th>
                            <th style="width:170px;">สร้างเมื่อ</th>
                            <th style="width:360px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>

                                <td class="name-cell">
                                    <strong><?php echo h($row['full_name']); ?></strong>
                                    <div class="muted">room_id: <?php echo (int)$row['room_id']; ?></div>
                                </td>

                                <td>
                                    <?php echo h($row['phone']); ?><br>
                                    <span class="muted"><?php echo h($row['email']); ?></span>
                                </td>

                                <td><?php echo h($row['room_type']); ?></td>

                                <td><?php echo (int)$row['guests']; ?> คน</td>

                                <td>
                                    <div>เข้า: <?php echo h($row['checkin_date']); ?></div>
                                    <div>ออก: <?php echo h($row['checkout_date']); ?></div>
                                </td>

                                <td>
                                    <?php echo $row['note'] !== '' ? nl2br(h($row['note'])) : '<span class="muted">-</span>'; ?>
                                </td>

                                <td>
                                    <span class="status-badge <?php echo h(bookingStatusClass($row['booking_status'])); ?>">
                                        <?php echo h(bookingStatusLabel($row['booking_status'])); ?>
                                    </span>
                                </td>

                                <td><?php echo h($row['created_at']); ?></td>

                                <td>
                                    <div class="action-group">
                                        <form method="POST" class="inline-form" onsubmit="return confirm('ย้ายรายการนี้กลับไปหน้ารออนุมัติใช่หรือไม่?');">
                                            <input type="hidden" name="action" value="set_pending">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">ย้ายกลับไปรออนุมัติ</button>
                                        </form>

                                        <form method="POST" class="inline-form" onsubmit="return confirm('เปลี่ยนรายการนี้เป็นยกเลิกใช่หรือไม่?');">
                                            <input type="hidden" name="action" value="set_cancelled">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">ยกเลิกการอนุมัติ</button>
                                        </form>

                                        <form method="POST" class="inline-form" onsubmit="return confirm('ยืนยันการลบข้อมูลนี้?');">
                                            <input type="hidden" name="action" value="delete_booking">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="table-empty">ยังไม่มีรายการที่อนุมัติแล้ว</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
include 'admin_layout_bottom.php';
?>