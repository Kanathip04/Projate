<?php
session_start();
$conn = new mysqli("localhost", "root", "", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$pageTitle = "รายงานการเช็คอินรายวัน";
$activeMenu = "report";

/* =========================
   วันที่ที่ต้องการดูรายงาน
========================= */
$selected_date = $_GET['report_date'] ?? date('Y-m-d');

/* =========================
   สรุปจำนวนรวมของวันนั้น
========================= */
$total = 0;
$student_count = 0;
$staff_count = 0;
$tourist_count = 0;

$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM tourists WHERE visit_date = ?");
$stmtTotal->bind_param("s", $selected_date);
$stmtTotal->execute();
$resTotal = $stmtTotal->get_result();
if ($row = $resTotal->fetch_assoc()) {
    $total = (int)$row['total'];
}
$stmtTotal->close();

$stmtType = $conn->prepare("
    SELECT user_type, COUNT(*) AS total
    FROM tourists
    WHERE visit_date = ?
    GROUP BY user_type
");
$stmtType->bind_param("s", $selected_date);
$stmtType->execute();
$resType = $stmtType->get_result();

while ($row = $resType->fetch_assoc()) {
    if ($row['user_type'] === 'นักศึกษา') {
        $student_count = (int)$row['total'];
    } elseif ($row['user_type'] === 'บุคลากร') {
        $staff_count = (int)$row['total'];
    } elseif ($row['user_type'] === 'นักท่องเที่ยว') {
        $tourist_count = (int)$row['total'];
    }
}
$stmtType->close();

/* =========================
   ดึงข้อมูลรายการเช็คอิน
========================= */
$stmtList = $conn->prepare("
    SELECT id, nickname, gender, age, user_type, visit_date, visit_time
    FROM tourists
    WHERE visit_date = ?
    ORDER BY visit_time ASC, id ASC
");
$stmtList->bind_param("s", $selected_date);
$stmtList->execute();
$resultList = $stmtList->get_result();

include "admin_layout_top.php";
?>

<style>
.filter-box{
    background:#fff;
    border-radius:16px;
    padding:18px;
    box-shadow:0 8px 24px rgba(0,0,0,0.06);
    margin-bottom:24px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:24px;
    gap:15px;
    flex-wrap:wrap;
}

.page-title-wrap h1{
    font-size:22px;
    margin-bottom:6px;
    font-weight:700;
}

.page-title-wrap p{
    color:#666;
    font-size:14px;
}

.filter-form{
    display:flex;
    gap:12px;
    align-items:end;
    flex-wrap:wrap;
}

.filter-group{
    min-width:240px;
}

.filter-group label{
    display:block;
    font-size:13px;
    font-weight:600;
    color:#555;
    margin-bottom:8px;
}

.filter-group input{
    width:100%;
    height:40px;
    padding:0 12px;
    border:1px solid #d8d8d8;
    border-radius:10px;
    font-size:14px;
}

.btn{
    border:none;
    height:40px;
    padding:0 16px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
    font-size:13px;
}

.btn-search{
    background:#6f9220;
    color:#fff;
}

.btn-print{
    background:#111;
    color:#fff;
}

.cards{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:18px;
    margin-bottom:24px;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:20px;
    box-shadow:0 8px 24px rgba(0,0,0,0.06);
    border-left:5px solid #6f9220;
}

.card h3{
    font-size:13px;
    color:#777;
    margin-bottom:8px;
    font-weight:600;
}

.card .number{
    font-size:22px;
    font-weight:700;
    color:#111;
}

.table-box{
    background:#fff;
    border-radius:18px;
    box-shadow:0 8px 24px rgba(0,0,0,0.06);
    overflow:hidden;
}

.table-header{
    padding:18px 20px;
    border-bottom:1px solid #eee;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.table-header h2{
    font-size:16px;
    font-weight:700;
}

.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th, td{
    padding:12px 14px;
    border-bottom:1px solid #eee;
    text-align:left;
    font-size:13px;
}

th{
    background:#f8f9fb;
    color:#333;
    font-weight:700;
}

.badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}

.badge.student{
    background:#e6f1ff;
    color:#2b79d1;
}

.badge.staff{
    background:#eaf7e8;
    color:#4b8d35;
}

.badge.tourist{
    background:#fff2df;
    color:#d9821f;
}

.no-data{
    padding:28px;
    text-align:center;
    color:#777;
}

@media print{
    .sidebar,
    .filter-box,
    .btn-print,
    .sidebar-footer{
        display:none !important;
    }

    body{
        background:#fff;
    }

    .main{
        margin-left:0 !important;
        width:100% !important;
        padding:0 !important;
    }

    .table-box,
    .card{
        box-shadow:none;
        border:1px solid #ddd;
    }
}

@media (max-width: 1100px){
    .cards{
        grid-template-columns:repeat(2, 1fr);
    }
}

@media (max-width: 700px){
    .cards{
        grid-template-columns:1fr;
    }

    .filter-group{
        min-width:100%;
    }
}
</style>

<div class="topbar">
    <div class="page-title-wrap">
        <h1>รายงานข้อมูลคนเข้าเช็คอินแต่ละวัน</h1>
        <p>สรุปจำนวนและแสดงรายการผู้ลงทะเบียนตามวันที่เลือก</p>
    </div>
    <button class="btn btn-print" onclick="window.print()">พิมพ์รายงาน</button>
</div>

<div class="filter-box">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label for="report_date">เลือกวันที่</label>
            <input type="date" id="report_date" name="report_date" value="<?php echo htmlspecialchars($selected_date); ?>">
        </div>
        <button type="submit" class="btn btn-search">ดูรายงาน</button>
    </form>
</div>

<div class="cards">
    <div class="card">
        <h3>จำนวนทั้งหมด</h3>
        <div class="number"><?php echo $total; ?> คน</div>
    </div>
    <div class="card">
        <h3>นักศึกษา</h3>
        <div class="number"><?php echo $student_count; ?> คน</div>
    </div>
    <div class="card">
        <h3>บุคลากร</h3>
        <div class="number"><?php echo $staff_count; ?> คน</div>
    </div>
    <div class="card">
        <h3>นักท่องเที่ยว</h3>
        <div class="number"><?php echo $tourist_count; ?> คน</div>
    </div>
</div>

<div class="table-box">
    <div class="table-header">
        <h2>รายการเช็คอิน วันที่ <?php echo date('d/m/Y', strtotime($selected_date)); ?></h2>
        <div>ทั้งหมด <?php echo $total; ?> รายการ</div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:80px;">#</th>
                    <th>ชื่อเล่น</th>
                    <th>เพศ</th>
                    <th>อายุ</th>
                    <th>ประเภท</th>
                    <th>วันที่</th>
                    <th>เวลา</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultList->num_rows > 0): ?>
                    <?php $i = 1; ?>
                    <?php while ($row = $resultList->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($row['nickname']); ?></td>
                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td>
                                <?php
                                    echo ($row['age'] !== null && $row['age'] !== '')
                                        ? htmlspecialchars($row['age']) . ' ปี'
                                        : '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                    $type = $row['user_type'];
                                    $class = 'tourist';

                                    if ($type === 'นักศึกษา') {
                                        $class = 'student';
                                    } elseif ($type === 'บุคลากร') {
                                        $class = 'staff';
                                    }
                                ?>
                                <span class="badge <?php echo $class; ?>">
                                    <?php echo htmlspecialchars($type); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['visit_date'])); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['visit_time'], 0, 5)); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">ไม่พบข้อมูลการเช็คอินในวันที่เลือก</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$stmtList->close();
$conn->close();
include "admin_layout_bottom.php";
?>