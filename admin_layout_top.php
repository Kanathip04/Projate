<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

/* =========================
   AUTH (ถ้ามีระบบล็อกอินแอดมิน)
========================= */
// ถ้าคุณมีระบบล็อกอินอยู่แล้ว ให้เปิดใช้บรรทัดนี้
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: login.php");
//     exit;
// }

/* =========================
   DB CONNECT
========================= */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =========================
   AUTO CREATE archived COLUMN (กันพลาด)
========================= */
$checkArchived = $conn->query("SHOW COLUMNS FROM room_bookings LIKE 'archived'");
if ($checkArchived && $checkArchived->num_rows === 0) {
    $conn->query("ALTER TABLE room_bookings ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 AFTER booking_status");
}

/* =========================
   HELPER
========================= */
function e($str){
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$message = "";
$messageType = "success";

/* =========================
   DELETE
========================= */
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    $stmt = $conn->prepare("DELETE FROM room_bookings WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "ลบข้อมูลเรียบร้อยแล้ว";
    } else {
        $message = "ลบข้อมูลไม่สำเร็จ";
        $messageType = "error";
    }
    $stmt->close();
}

/* =========================
   ARCHIVE / UNARCHIVE
========================= */
if (isset($_GET['archive_id'])) {
    $archive_id = (int)$_GET['archive_id'];

    $stmt = $conn->prepare("UPDATE room_bookings SET archived = 1 WHERE id = ?");
    $stmt->bind_param("i", $archive_id);

    if ($stmt->execute()) {
        $message = "จัดเก็บข้อมูลเรียบร้อยแล้ว";
    } else {
        $message = "จัดเก็บข้อมูลไม่สำเร็จ";
        $messageType = "error";
    }
    $stmt->close();
}

if (isset($_GET['unarchive_id'])) {
    $unarchive_id = (int)$_GET['unarchive_id'];

    $stmt = $conn->prepare("UPDATE room_bookings SET archived = 0 WHERE id = ?");
    $stmt->bind_param("i", $unarchive_id);

    if ($stmt->execute()) {
        $message = "นำข้อมูลกลับมาเรียบร้อยแล้ว";
    } else {
        $message = "นำข้อมูลกลับมาไม่สำเร็จ";
        $messageType = "error";
    }
    $stmt->close();
}

/* =========================
   SAVE (ADD / EDIT)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_booking'])) {
    $id            = (int)($_POST['id'] ?? 0);
    $full_name     = trim($_POST['full_name'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $room_type     = trim($_POST['room_type'] ?? '');
    $guests        = (int)($_POST['guests'] ?? 1);
    $checkin_date  = trim($_POST['checkin_date'] ?? '');
    $checkout_date = trim($_POST['checkout_date'] ?? '');
    $note          = trim($_POST['note'] ?? '');
    $booking_status = trim($_POST['booking_status'] ?? 'pending');
    $archived      = isset($_POST['archived']) ? 1 : 0;

    if (
        $full_name === '' ||
        $phone === '' ||
        $room_type === '' ||
        $checkin_date === '' ||
        $checkout_date === ''
    ) {
        $message = "กรุณากรอกข้อมูลที่จำเป็นให้ครบ";
        $messageType = "error";
    } elseif ($checkout_date < $checkin_date) {
        $message = "วันที่ออกต้องไม่น้อยกว่าวันที่เข้าพัก";
        $messageType = "error";
    } else {

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE room_bookings
                SET full_name = ?, phone = ?, email = ?, room_type = ?, guests = ?, 
                    checkin_date = ?, checkout_date = ?, note = ?, booking_status = ?, archived = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssssissssii",
                $full_name,
                $phone,
                $email,
                $room_type,
                $guests,
                $checkin_date,
                $checkout_date,
                $note,
                $booking_status,
                $archived,
                $id
            );

            if ($stmt->execute()) {
                $message = "แก้ไขข้อมูลเรียบร้อยแล้ว";
            } else {
                $message = "แก้ไขข้อมูลไม่สำเร็จ : " . $stmt->error;
                $messageType = "error";
            }
            $stmt->close();

        } else {
            $stmt = $conn->prepare("
                INSERT INTO room_bookings
                (full_name, phone, email, room_type, guests, checkin_date, checkout_date, note, booking_status, archived)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
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
                $archived
            );

            if ($stmt->execute()) {
                $message = "เพิ่มข้อมูลเรียบร้อยแล้ว";
            } else {
                $message = "เพิ่มข้อมูลไม่สำเร็จ : " . $stmt->error;
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

/* =========================
   EDIT LOAD
========================= */
$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];

    $stmt = $conn->prepare("SELECT * FROM room_bookings WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $resultEdit = $stmt->get_result();
    if ($resultEdit && $resultEdit->num_rows > 0) {
        $editData = $resultEdit->fetch_assoc();
    }
    $stmt->close();
}

/* =========================
   FILTER
========================= */
$search      = trim($_GET['search'] ?? '');
$status      = trim($_GET['status'] ?? '');
$archiveView = trim($_GET['view'] ?? 'active'); // active / archived / all

$where = "WHERE 1=1";

if ($archiveView === 'active') {
    $where .= " AND archived = 0";
} elseif ($archiveView === 'archived') {
    $where .= " AND archived = 1";
}

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $where .= " AND (
        full_name LIKE '%$safe%' OR
        phone LIKE '%$safe%' OR
        email LIKE '%$safe%' OR
        room_type LIKE '%$safe%'
    )";
}

if ($status !== '') {
    $safeStatus = $conn->real_escape_string($status);
    $where .= " AND booking_status = '$safeStatus'";
}

/* =========================
   STATS
========================= */
$today = date('Y-m-d');

$q1 = $conn->query("SELECT COUNT(*) AS total FROM room_bookings WHERE archived = 0");
$totalActive = ($q1 && $row = $q1->fetch_assoc()) ? (int)$row['total'] : 0;

$q2 = $conn->query("SELECT COUNT(*) AS total FROM room_bookings WHERE archived = 1");
$totalArchived = ($q2 && $row = $q2->fetch_assoc()) ? (int)$row['total'] : 0;

$q3 = $conn->query("SELECT COUNT(*) AS total FROM room_bookings WHERE booking_status = 'pending' AND archived = 0");
$totalPending = ($q3 && $row = $q3->fetch_assoc()) ? (int)$row['total'] : 0;

$q4 = $conn->query("SELECT COUNT(*) AS total FROM room_bookings WHERE booking_status = 'approved' AND archived = 0");
$totalApproved = ($q4 && $row = $q4->fetch_assoc()) ? (int)$row['total'] : 0;

$q5 = $conn->query("SELECT COUNT(*) AS total FROM room_bookings WHERE checkin_date = '$today' AND archived = 0");
$totalTodayCheckin = ($q5 && $row = $q5->fetch_assoc()) ? (int)$row['total'] : 0;

/* =========================
   LIST DATA
========================= */
$sql = "SELECT * FROM room_bookings $where ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการข้อมูลการเข้าพัก</title>
<style>
:root{
    --dark:#1f1f1f;
    --bg:#f4f6f9;
    --brand:#638411;
    --brand2:#7aa51a;
    --white:#fff;
    --muted:#666;
    --border:#e5e7eb;
    --danger:#d9363e;
    --danger2:#b91c1c;
    --warning:#f59e0b;
    --info:#2563eb;
    --shadow:0 10px 24px rgba(0,0,0,.08);
    --radius:18px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Segoe UI',Tahoma,sans-serif;
    background:var(--bg);
    color:#222;
}
.container{
    width:min(1400px, 96%);
    margin:28px auto;
}
.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    margin-bottom:20px;
    flex-wrap:wrap;
}
.title-box h1{
    font-size:30px;
    color:var(--dark);
    margin-bottom:6px;
}
.title-box p{
    color:var(--muted);
}
.top-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn{
    border:none;
    border-radius:12px;
    padding:12px 18px;
    cursor:pointer;
    font-weight:700;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    transition:.2s ease;
}
.btn:hover{transform:translateY(-1px)}
.btn-brand{
    background:linear-gradient(135deg,var(--brand),var(--brand2));
    color:#fff;
}
.btn-light{
    background:#fff;
    color:#333;
    border:1px solid var(--border);
}
.btn-danger{
    background:linear-gradient(135deg,var(--danger),var(--danger2));
    color:#fff;
}
.cards{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:16px;
    margin-bottom:22px;
}
.card{
    background:#fff;
    border-radius:18px;
    padding:20px;
    box-shadow:var(--shadow);
    border:1px solid #eef0f3;
}
.card h3{
    font-size:14px;
    color:#666;
    margin-bottom:10px;
    font-weight:700;
}
.card .num{
    font-size:28px;
    font-weight:800;
    color:var(--dark);
}
.panel{
    background:#fff;
    border-radius:20px;
    box-shadow:var(--shadow);
    border:1px solid #eef0f3;
    padding:22px;
    margin-bottom:22px;
}
.panel h2{
    font-size:22px;
    margin-bottom:16px;
}
.alert{
    padding:14px 16px;
    border-radius:12px;
    margin-bottom:18px;
    font-weight:600;
}
.alert.success{
    background:#ecfdf3;
    color:#166534;
    border:1px solid #bbf7d0;
}
.alert.error{
    background:#fef2f2;
    color:#991b1b;
    border:1px solid #fecaca;
}
.form-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
}
.form-group{
    display:flex;
    flex-direction:column;
    gap:8px;
}
.form-group label{
    font-size:14px;
    font-weight:700;
    color:#444;
}
.form-group input,
.form-group select,
.form-group textarea{
    border:1px solid #d9dce2;
    border-radius:12px;
    padding:12px 14px;
    font-size:15px;
    outline:none;
    width:100%;
    background:#fff;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{
    border-color:var(--brand2);
    box-shadow:0 0 0 4px rgba(122,165,26,.12);
}
.form-group textarea{
    min-height:92px;
    resize:vertical;
}
.span-2{grid-column:span 2;}
.span-4{grid-column:span 4;}
.inline-check{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:34px;
}
.filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr 1fr auto;
    gap:14px;
    align-items:end;
}
.table-wrap{
    overflow:auto;
}
table{
    width:100%;
    border-collapse:collapse;
    min-width:1100px;
}
th,td{
    padding:14px 12px;
    border-bottom:1px solid #edf0f2;
    text-align:left;
    vertical-align:top;
    font-size:14px;
}
th{
    background:#f8fafc;
    color:#333;
    font-size:13px;
    font-weight:800;
}
.badge{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.badge.pending{background:#fff7ed;color:#c2410c}
.badge.approved{background:#ecfdf3;color:#166534}
.badge.cancelled{background:#fef2f2;color:#991b1b}
.badge.archived{background:#eff6ff;color:#1d4ed8}
.actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.actions a{
    padding:8px 11px;
    border-radius:10px;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    border:1px solid #dbe1e7;
    color:#333;
    background:#fff;
}
.actions a:hover{
    background:#f8fafc;
}
.small{
    font-size:12px;
    color:#777;
}
@media (max-width:1200px){
    .cards{grid-template-columns:repeat(3,1fr)}
    .form-grid{grid-template-columns:repeat(2,1fr)}
}
@media (max-width:768px){
    .cards{grid-template-columns:repeat(2,1fr)}
    .form-grid,.filter-grid{grid-template-columns:1fr}
    .span-2,.span-4{grid-column:span 1}
    .inline-check{margin-top:0}
}
</style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div class="title-box">
            <h1>จัดการข้อมูลการเข้าพัก</h1>
            <p>เพิ่ม แก้ไข ลบ จัดเก็บ และตรวจสอบรายการจองห้องพักจากหน้าเดียว</p>
        </div>

        <div class="top-actions">
            <a href="admin_dashboard.php" class="btn btn-light">← กลับหน้า Dashboard</a>
            <a href="admin_booking_manage.php" class="btn btn-brand">รีเฟรชหน้า</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert <?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <div class="cards">
        <div class="card">
            <h3>รายการปัจจุบัน</h3>
            <div class="num"><?php echo $totalActive; ?></div>
        </div>
        <div class="card">
            <h3>รออนุมัติ</h3>
            <div class="num"><?php echo $totalPending; ?></div>
        </div>
        <div class="card">
            <h3>อนุมัติแล้ว</h3>
            <div class="num"><?php echo $totalApproved; ?></div>
        </div>
        <div class="card">
            <h3>เช็คอินวันนี้</h3>
            <div class="num"><?php echo $totalTodayCheckin; ?></div>
        </div>
        <div class="card">
            <h3>ข้อมูลที่จัดเก็บแล้ว</h3>
            <div class="num"><?php echo $totalArchived; ?></div>
        </div>
    </div>

    <div class="panel">
        <h2><?php echo $editData ? 'แก้ไขข้อมูลการเข้าพัก' : 'เพิ่มข้อมูลการเข้าพัก'; ?></h2>

        <form method="POST" action="">
            <input type="hidden" name="id" value="<?php echo (int)($editData['id'] ?? 0); ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" required value="<?php echo e($editData['full_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>เบอร์โทร</label>
                    <input type="text" name="phone" required value="<?php echo e($editData['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>อีเมล</label>
                    <input type="email" name="email" value="<?php echo e($editData['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>ประเภทห้อง</label>
                    <select name="room_type" required>
                        <?php $selectedRoom = $editData['room_type'] ?? ''; ?>
                        <option value="">-- เลือกประเภทห้อง --</option>
                        <option value="standard" <?php echo $selectedRoom === 'standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="deluxe" <?php echo $selectedRoom === 'deluxe' ? 'selected' : ''; ?>>Deluxe</option>
                        <option value="family" <?php echo $selectedRoom === 'family' ? 'selected' : ''; ?>>Family</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>จำนวนผู้เข้าพัก</label>
                    <input type="number" name="guests" min="1" required value="<?php echo e($editData['guests'] ?? '1'); ?>">
                </div>

                <div class="form-group">
                    <label>วันที่เข้าพัก</label>
                    <input type="date" name="checkin_date" required value="<?php echo e($editData['checkin_date'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>วันที่ออก</label>
                    <input type="date" name="checkout_date" required value="<?php echo e($editData['checkout_date'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>สถานะ</label>
                    <?php $selectedStatus = $editData['booking_status'] ?? 'pending'; ?>
                    <select name="booking_status">
                        <option value="pending" <?php echo $selectedStatus === 'pending' ? 'selected' : ''; ?>>pending</option>
                        <option value="approved" <?php echo $selectedStatus === 'approved' ? 'selected' : ''; ?>>approved</option>
                        <option value="cancelled" <?php echo $selectedStatus === 'cancelled' ? 'selected' : ''; ?>>cancelled</option>
                    </select>
                </div>

                <div class="form-group span-2">
                    <label>หมายเหตุ</label>
                    <textarea name="note"><?php echo e($editData['note'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="inline-check">
                        <input type="checkbox" id="archived" name="archived" value="1" <?php echo !empty($editData['archived']) ? 'checked' : ''; ?>>
                        <label for="archived">จัดเก็บข้อมูลรายการนี้</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="save_booking" class="btn btn-brand">
                        <?php echo $editData ? 'บันทึกการแก้ไข' : 'เพิ่มข้อมูล'; ?>
                    </button>
                </div>

                <?php if ($editData): ?>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <a href="admin_booking_manage.php" class="btn btn-light">ยกเลิกการแก้ไข</a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>ค้นหาและกรองข้อมูล</h2>

        <form method="GET" action="">
            <div class="filter-grid">
                <div class="form-group">
                    <label>ค้นหา</label>
                    <input type="text" name="search" placeholder="ค้นหาชื่อ เบอร์โทร อีเมล หรือประเภทห้อง" value="<?php echo e($search); ?>">
                </div>

                <div class="form-group">
                    <label>สถานะ</label>
                    <select name="status">
                        <option value="">ทั้งหมด</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>approved</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>มุมมอง</label>
                    <select name="view">
                        <option value="active" <?php echo $archiveView === 'active' ? 'selected' : ''; ?>>รายการปัจจุบัน</option>
                        <option value="archived" <?php echo $archiveView === 'archived' ? 'selected' : ''; ?>>ข้อมูลที่จัดเก็บแล้ว</option>
                        <option value="all" <?php echo $archiveView === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-brand">ค้นหา</button>
                </div>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>รายการข้อมูลการเข้าพัก</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ผู้จอง</th>
                        <th>ติดต่อ</th>
                        <th>ห้อง</th>
                        <th>ผู้พัก</th>
                        <th>วันที่เข้าพัก</th>
                        <th>วันที่ออก</th>
                        <th>สถานะ</th>
                        <th>หมายเหตุ</th>
                        <th>จัดเก็บ</th>
                        <th>สร้างเมื่อ</th>
                        <th>การทำงาน</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td>
                                <strong><?php echo e($row['full_name']); ?></strong>
                            </td>
                            <td>
                                <?php echo e($row['phone']); ?><br>
                                <span class="small"><?php echo e($row['email']); ?></span>
                            </td>
                            <td><?php echo e($row['room_type']); ?></td>
                            <td><?php echo (int)$row['guests']; ?> คน</td>
                            <td><?php echo e($row['checkin_date']); ?></td>
                            <td><?php echo e($row['checkout_date']); ?></td>
                            <td>
                                <span class="badge <?php echo e($row['booking_status']); ?>">
                                    <?php echo e($row['booking_status']); ?>
                                </span>
                            </td>
                            <td><?php echo nl2br(e($row['note'])); ?></td>
                            <td>
                                <?php if ((int)$row['archived'] === 1): ?>
                                    <span class="badge archived">archived</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($row['created_at']); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?edit_id=<?php echo (int)$row['id']; ?>">แก้ไข</a>

                                    <?php if ((int)$row['archived'] === 0): ?>
                                        <a href="?archive_id=<?php echo (int)$row['id']; ?>" onclick="return confirm('ต้องการจัดเก็บข้อมูลนี้ใช่หรือไม่?')">จัดเก็บ</a>
                                    <?php else: ?>
                                        <a href="?unarchive_id=<?php echo (int)$row['id']; ?>" onclick="return confirm('ต้องการนำข้อมูลนี้กลับมาหรือไม่?')">กู้คืน</a>
                                    <?php endif; ?>

                                    <a href="?delete_id=<?php echo (int)$row['id']; ?>" onclick="return confirm('ยืนยันการลบข้อมูลนี้?')">ลบ</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" style="text-align:center;color:#777;padding:28px;">
                            ไม่พบข้อมูล
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>