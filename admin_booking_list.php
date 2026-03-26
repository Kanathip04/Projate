<?php
date_default_timezone_set('Asia/Bangkok');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
            'label' => 'รอยืนยัน',
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
        $booking_status = trim($_POST['booking_status'] ?? 'pending');

        if (!in_array($booking_status, $allowed_status, true)) {
            $booking_status = 'pending';
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
                    $message = "อัปเดตข้อมูลเรียบร้อยแล้ว";
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
            } else {
                $message = "Prepare DELETE ไม่สำเร็จ: " . $conn->error;
                $message_type = "error";
            }
        }
    }

    if ($action === 'change_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = trim($_POST['booking_status'] ?? 'pending');

        if ($id > 0 && in_array($new_status, $allowed_status, true)) {
            $stmt = $conn->prepare("UPDATE room_bookings SET booking_status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $new_status, $id);
                if ($stmt->execute()) {
                    $message = "เปลี่ยนสถานะเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "เปลี่ยนสถานะไม่สำเร็จ";
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "Prepare เปลี่ยนสถานะไม่สำเร็จ: " . $conn->error;
                $message_type = "error";
            }
        }
    }

    if ($action === 'archive_booking') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE room_bookings SET archived = 1 WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "จัดเก็บข้อมูลเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "จัดเก็บข้อมูลไม่สำเร็จ";
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'unarchive_booking') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE room_bookings SET archived = 0 WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "กู้คืนข้อมูลเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "กู้คืนข้อมูลไม่สำเร็จ";
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
$status_filter = trim($_GET['status'] ?? '');
$archive_filter = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$edit_data = null;
if ($edit_id > 0) {
    $stmtEdit = $conn->prepare("SELECT * FROM room_bookings WHERE id = ? LIMIT 1");
    if ($stmtEdit) {
        $stmtEdit->bind_param("i", $edit_id);
        $stmtEdit->execute();
        $resEdit = $stmtEdit->get_result();
        $edit_data = $resEdit->fetch_assoc();
        $stmtEdit->close();
    }
}

$stat_total = 0;
$stat_pending = 0;
$stat_approved = 0;
$stat_cancelled = 0;

$sqlStat = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN booking_status='pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN booking_status='approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN booking_status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM room_bookings
    WHERE archived = $archive_filter
";
$resStat = $conn->query($sqlStat);

if ($resStat && $rowStat = $resStat->fetch_assoc()) {
    $stat_total = (int)($rowStat['total'] ?? 0);
    $stat_pending = (int)($rowStat['pending_count'] ?? 0);
    $stat_approved = (int)($rowStat['approved_count'] ?? 0);
    $stat_cancelled = (int)($rowStat['cancelled_count'] ?? 0);
}

$where = "WHERE archived = ?";
$params = [$archive_filter];
$types = "i";

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

if ($status_filter !== '' && in_array($status_filter, ['pending', 'approved', 'cancelled'], true)) {
    $where .= " AND booking_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "SELECT * FROM room_bookings {$where} ORDER BY id DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare SELECT ไม่สำเร็จ: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$statusOptions = bookingStatusMap();

$pageTitle = "จัดการข้อมูลการจองห้องพัก";
$activeMenu = "booking";
include 'admin_layout_top.php';
?>

<style>
.booking-container{
    max-width:1500px;
    margin:0 auto;
}
.topbar-local{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:24px;
}
.page-heading{
    display:flex;
    flex-direction:column;
    gap:6px;
}
.page-heading h1{
    margin:0;
    font-size:36px;
    line-height:1.1;
    font-weight:800;
    letter-spacing:-0.5px;
}
.page-heading p{
    margin:0;
    color:#6b7280;
    font-size:15px;
}
.btn-local{
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
.btn-local:hover{transform:translateY(-1px)}
.btn-primary-local{ background:#638411; color:#fff; }
.btn-primary-local:hover{ background:#56740e; }
.btn-secondary-local{ background:#6b7280; color:#fff; }
.btn-secondary-local:hover{ background:#59616c; }
.btn-info-local{ background:#1d3557; color:#fff; }
.btn-info-local:hover{ background:#16304f; }
.btn-danger-local{ background:#dc4c64; color:#fff; }
.btn-danger-local:hover{ background:#c53a52; }
.btn-warning-local{ background:#f59e0b; color:#fff; }
.btn-warning-local:hover{ background:#dd8b07; }
.btn-sm-local{
    padding:8px 12px;
    border-radius:12px;
    font-size:13px;
}
.panel-local{
    background:rgba(255,255,255,0.92);
    backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,0.7);
    box-shadow:0 12px 30px rgba(15, 23, 42, 0.08);
    border-radius:22px;
}
.alert-local{
    margin-bottom:20px;
    padding:14px 18px;
    border-radius:16px;
    font-weight:700;
}
.alert-local.success{
    background:#edf9f0;
    color:#1d7d3f;
    border:1px solid #c8ebd2;
}
.alert-local.error{
    background:#fff1f2;
    color:#be123c;
    border:1px solid #fecdd3;
}
.stats-local{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:18px;
    margin-bottom:22px;
}
.stat-card-local{
    padding:22px;
}
.stat-card-local .label{
    font-size:14px;
    color:#6b7280;
    font-weight:700;
    margin-bottom:10px;
}
.stat-card-local .value{
    font-size:34px;
    font-weight:800;
    line-height:1;
    margin-bottom:8px;
}
.stat-card-local .sub{
    font-size:13px;
    color:#6b7280;
}
.layout-local{
    display:grid;
    grid-template-columns:360px minmax(0, 1fr);
    gap:22px;
    align-items:start;
}
.sidebar-card-local,
.content-card-local{
    padding:22px;
}
.section-title-local{
    margin:0 0 6px;
    font-size:24px;
    font-weight:800;
}
.section-desc-local{
    margin:0 0 18px;
    color:#6b7280;
    font-size:14px;
}
.form-grid-local{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}
.form-group-local{
    margin-bottom:14px;
}
.form-group-local label{
    display:block;
    margin-bottom:7px;
    font-size:14px;
    font-weight:700;
    color:#18212f;
}
.form-control-local,
textarea.form-control-local,
select.form-control-local{
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
.form-control-local:focus{
    border-color:#9ab85c;
    box-shadow:0 0 0 4px rgba(99,132,17,.10);
}
textarea.form-control-local{
    min-height:120px;
    resize:vertical;
}
.sticky-box-local{
    position:sticky;
    top:20px;
}
.empty-edit-local{
    background:#f8fafc;
    border:1px dashed #d7dee8;
    border-radius:18px;
    padding:18px;
    color:#6b7280;
    line-height:1.6;
}
.filter-wrap-local{
    padding:18px;
    background:#f8fafc;
    border:1px solid #edf1f6;
    border-radius:18px;
    margin-bottom:18px;
}
.filter-actions-local{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:end;
}
.table-toolbar-local{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:14px;
}
.table-meta-local{
    color:#6b7280;
    font-size:14px;
}
.table-wrap-local{
    overflow:auto;
    border:1px solid #edf1f6;
    border-radius:18px;
}
.table-local{
    width:100%;
    min-width:1180px;
    border-collapse:separate;
    border-spacing:0;
    background:#fff;
}
.table-local thead th{
    background:#f8fafc;
    color:#334155;
    font-size:14px;
    font-weight:800;
    padding:14px 12px;
    text-align:left;
    border-bottom:1px solid #e8edf4;
    position:sticky;
    top:0;
    z-index:1;
}
.table-local tbody td{
    padding:14px 12px;
    font-size:14px;
    vertical-align:top;
    border-bottom:1px solid #eef2f7;
}
.table-local tbody tr:hover{
    background:#fcfdff;
}
.name-cell-local strong{
    display:block;
    margin-bottom:4px;
    font-size:15px;
}
.muted-local{
    color:#6b7280;
    font-size:13px;
    line-height:1.5;
}
.status-badge-local{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:90px;
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
.action-group-local{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
}
.inline-form-local{
    display:inline;
    margin:0;
}
.status-select-local{
    min-width:135px;
    padding:8px 10px;
    border-radius:12px;
    font-size:13px;
}
.table-empty-local{
    text-align:center;
    padding:34px 18px;
    color:#6b7280;
    background:#fff;
}
@media (max-width: 1180px){
    .layout-local{grid-template-columns:1fr}
    .sticky-box-local{position:static}
}
@media (max-width: 860px){
    .stats-local{grid-template-columns:repeat(2, minmax(0, 1fr))}
    .form-grid-local{grid-template-columns:1fr}
    .page-heading h1{font-size:28px}
}
@media (max-width: 580px){
    .stats-local{grid-template-columns:1fr}
    .sidebar-card-local,
    .content-card-local,
    .stat-card-local{padding:18px}
}
</style>

<div class="booking-container">
    <div class="topbar-local">
        <div class="page-heading">
            <h1>จัดการข้อมูลการจองห้องพัก</h1>
            <p>ตรวจสอบ แก้ไข เปลี่ยนสถานะ และจัดเก็บรายการจองได้จากหน้าเดียว</p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert-local <?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <div class="stats-local">
        <div class="panel-local stat-card-local">
            <div class="label">รายการทั้งหมด</div>
            <div class="value"><?php echo $stat_total; ?></div>
            <div class="sub"><?php echo $archive_filter === 0 ? 'ข้อมูลที่กำลังใช้งาน' : 'ข้อมูลที่ถูกจัดเก็บแล้ว'; ?></div>
        </div>

        <div class="panel-local stat-card-local">
            <div class="label">รอยืนยัน</div>
            <div class="value"><?php echo $stat_pending; ?></div>
            <div class="sub">รายการที่ยังรอการตรวจสอบ</div>
        </div>

        <div class="panel-local stat-card-local">
            <div class="label">อนุมัติแล้ว</div>
            <div class="value"><?php echo $stat_approved; ?></div>
            <div class="sub">รายการที่พร้อมเข้าพัก</div>
        </div>

        <div class="panel-local stat-card-local">
            <div class="label">ยกเลิก</div>
            <div class="value"><?php echo $stat_cancelled; ?></div>
            <div class="sub">รายการที่ถูกยกเลิก</div>
        </div>
    </div>

    <div class="layout-local">
        <div class="sticky-box-local">
            <div class="panel-local sidebar-card-local">
                <h2 class="section-title-local">
                    <?php echo $edit_data ? 'แก้ไขข้อมูลการจอง' : 'แผงแก้ไขข้อมูล'; ?>
                </h2>
                <p class="section-desc-local">
                    <?php echo $edit_data ? 'ปรับข้อมูลรายการที่เลือก แล้วกดบันทึกการแก้ไข' : 'กดปุ่ม “แก้ไข” จากตารางด้านขวาเพื่อโหลดข้อมูลเข้าฟอร์ม'; ?>
                </p>

                <?php if ($edit_data): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_booking">
                        <input type="hidden" name="id" value="<?php echo (int)$edit_data['id']; ?>">

                        <div class="form-group-local">
                            <label>ชื่อผู้จอง</label>
                            <input type="text" name="full_name" class="form-control-local" value="<?php echo h($edit_data['full_name']); ?>" required>
                        </div>

                        <div class="form-grid-local">
                            <div class="form-group-local">
                                <label>เบอร์โทร</label>
                                <input type="text" name="phone" class="form-control-local" value="<?php echo h($edit_data['phone']); ?>" required>
                            </div>
                            <div class="form-group-local">
                                <label>อีเมล</label>
                                <input type="email" name="email" class="form-control-local" value="<?php echo h($edit_data['email']); ?>">
                            </div>
                        </div>

                        <div class="form-grid-local">
                            <div class="form-group-local">
                                <label>ประเภท / ชื่อห้อง</label>
                                <input type="text" name="room_type" class="form-control-local" value="<?php echo h($edit_data['room_type']); ?>" required>
                            </div>
                            <div class="form-group-local">
                                <label>จำนวนผู้เข้าพัก</label>
                                <input type="number" name="guests" class="form-control-local" min="1" value="<?php echo (int)$edit_data['guests']; ?>" required>
                            </div>
                        </div>

                        <div class="form-grid-local">
                            <div class="form-group-local">
                                <label>วันเช็คอิน</label>
                                <input type="date" name="checkin_date" class="form-control-local" value="<?php echo h($edit_data['checkin_date']); ?>" required>
                            </div>
                            <div class="form-group-local">
                                <label>วันเช็คเอาท์</label>
                                <input type="date" name="checkout_date" class="form-control-local" value="<?php echo h($edit_data['checkout_date']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group-local">
                            <label>สถานะการจอง</label>
                            <select name="booking_status" class="form-control-local">
                                <?php foreach ($statusOptions as $statusKey => $statusItem): ?>
                                    <option value="<?php echo h($statusKey); ?>" <?php echo $edit_data['booking_status'] === $statusKey ? 'selected' : ''; ?>>
                                        <?php echo h($statusItem['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-local">
                            <label>หมายเหตุ</label>
                            <textarea name="note" class="form-control-local"><?php echo h($edit_data['note']); ?></textarea>
                        </div>

                        <div class="filter-actions-local">
                            <button type="submit" class="btn-local btn-primary-local">บันทึกการแก้ไข</button>
                            <a href="<?php echo h($currentPage); ?>?archived=<?php echo $archive_filter; ?>" class="btn-local btn-secondary-local">ยกเลิก</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty-edit-local">
                        ยังไม่ได้เลือกรายการที่ต้องการแก้ไข<br>
                        ให้กดปุ่ม <strong>แก้ไข</strong> จากตารางรายการด้านขวา
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel-local content-card-local">
            <div class="table-toolbar-local">
                <div>
                    <h2 class="section-title-local" style="margin-bottom:4px;">รายการจองทั้งหมด</h2>
                    <div class="table-meta-local">ค้นหา กรองสถานะ และจัดการรายการได้จากส่วนนี้</div>
                </div>
            </div>

            <form method="GET" class="filter-wrap-local">
                <div class="form-grid-local">
                    <div class="form-group-local">
                        <label>ค้นหา</label>
                        <input
                            type="text"
                            name="search"
                            class="form-control-local"
                            placeholder="ชื่อ, เบอร์โทร, อีเมล, ห้อง, หมายเหตุ"
                            value="<?php echo h($search); ?>"
                        >
                    </div>

                    <div class="form-group-local">
                        <label>สถานะ</label>
                        <select name="status" class="form-control-local">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($statusOptions as $statusKey => $statusItem): ?>
                                <option value="<?php echo h($statusKey); ?>" <?php echo $status_filter === $statusKey ? 'selected' : ''; ?>>
                                    <?php echo h($statusItem['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-local">
                    <div class="form-group-local">
                        <label>ประเภทข้อมูล</label>
                        <select name="archived" class="form-control-local">
                            <option value="0" <?php echo $archive_filter === 0 ? 'selected' : ''; ?>>ข้อมูลปัจจุบัน</option>
                            <option value="1" <?php echo $archive_filter === 1 ? 'selected' : ''; ?>>ข้อมูลที่จัดเก็บแล้ว</option>
                        </select>
                    </div>

                    <div class="form-group-local">
                        <label>การทำงาน</label>
                        <div class="filter-actions-local">
                            <button type="submit" class="btn-local btn-primary-local">ค้นหา</button>
                            <a href="<?php echo h($currentPage); ?>" class="btn-local btn-secondary-local">รีเซ็ต</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="table-wrap-local">
                <table class="table-local">
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
                            <th style="width:270px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>

                                <td class="name-cell-local">
                                    <strong><?php echo h($row['full_name']); ?></strong>
                                    <div class="muted-local">room_id: <?php echo (int)$row['room_id']; ?></div>
                                </td>

                                <td>
                                    <?php echo h($row['phone']); ?><br>
                                    <span class="muted-local"><?php echo h($row['email']); ?></span>
                                </td>

                                <td><?php echo h($row['room_type']); ?></td>

                                <td><?php echo (int)$row['guests']; ?> คน</td>

                                <td>
                                    <div>เข้า: <?php echo h($row['checkin_date']); ?></div>
                                    <div>ออก: <?php echo h($row['checkout_date']); ?></div>
                                </td>

                                <td><?php echo $row['note'] !== '' ? nl2br(h($row['note'])) : '<span class="muted-local">-</span>'; ?></td>

                                <td>
                                    <span class="status-badge-local <?php echo h(bookingStatusClass($row['booking_status'])); ?>">
                                        <?php echo h(bookingStatusLabel($row['booking_status'])); ?>
                                    </span>
                                </td>

                                <td><?php echo h($row['created_at']); ?></td>

                                <td>
                                    <div class="action-group-local">
                                        <a
                                            class="btn-local btn-info-local btn-sm-local"
                                            href="<?php echo h($currentPage); ?>?edit=<?php echo (int)$row['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&archived=<?php echo $archive_filter; ?>"
                                        >
                                            แก้ไข
                                        </a>

                                        <form method="POST" class="inline-form-local" onsubmit="return confirm('ยืนยันการลบข้อมูลนี้?');">
                                            <input type="hidden" name="action" value="delete_booking">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn-local btn-danger-local btn-sm-local">ลบ</button>
                                        </form>

                                        <?php if ((int)$row['archived'] === 0): ?>
                                            <form method="POST" class="inline-form-local" onsubmit="return confirm('จัดเก็บรายการนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="action" value="archive_booking">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn-local btn-warning-local btn-sm-local">จัดเก็บ</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="inline-form-local" onsubmit="return confirm('กู้คืนรายการนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="action" value="unarchive_booking">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn-local btn-secondary-local btn-sm-local">กู้คืน</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" class="inline-form-local">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <select name="booking_status" onchange="this.form.submit()" class="form-control-local status-select-local">
                                                <?php foreach ($statusOptions as $statusKey => $statusItem): ?>
                                                    <option value="<?php echo h($statusKey); ?>" <?php echo $row['booking_status'] === $statusKey ? 'selected' : ''; ?>>
                                                        <?php echo h($statusItem['label']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="table-empty-local">ไม่พบข้อมูลการจอง</td>
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