<?php
session_start();
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
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
:root{
    --bg-main:#f6f2ea;
    --panel:#ffffff;
    --panel-soft:#fcfbf8;
    --text-main:#1d2238;
    --text-muted:#8b8fa3;
    --line:#ece7dd;
    --gold:#d3b16f;
    --navy:#171934;
    --navy-2:#23284b;
    --blue:#1f6fd1;
    --orange:#f26a00;
    --green:#7b9627;
    --shadow:0 10px 30px rgba(24, 30, 58, 0.08);
    --radius-xl:22px;
    --radius-lg:18px;
    --radius-md:14px;
}

body{
    background:
        linear-gradient(to right, rgba(255,255,255,0) 0, rgba(255,255,255,0) 13%, rgba(222,213,200,0.20) 13%, rgba(222,213,200,0.20) 13.15%, rgba(255,255,255,0) 13.15%, rgba(255,255,255,0) 26%, rgba(222,213,200,0.18) 26%, rgba(222,213,200,0.18) 26.15%, rgba(255,255,255,0) 26.15%, rgba(255,255,255,0) 39%, rgba(222,213,200,0.15) 39%, rgba(222,213,200,0.15) 39.15%, rgba(255,255,255,0) 39.15%);
    background-color: var(--bg-main);
}

.report-page{
    padding: 6px 0 4px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    margin-bottom:22px;
    flex-wrap:wrap;
}

.page-title-wrap .eyebrow{
    font-size:12px;
    font-weight:700;
    color:#b18c4e;
    letter-spacing:.08em;
    text-transform:uppercase;
    margin-bottom:10px;
}

.page-title-wrap h1{
    font-size:34px;
    line-height:1.15;
    margin:0 0 8px;
    font-weight:800;
    color:var(--text-main);
}

.page-title-wrap p{
    margin:0;
    color:var(--text-muted);
    font-size:14px;
    line-height:1.6;
}

.top-actions{
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}

.btn{
    border:none;
    height:46px;
    padding:0 18px;
    border-radius:12px;
    cursor:pointer;
    font-weight:700;
    font-size:14px;
    transition:.22s ease;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}

.btn:hover{
    transform:translateY(-1px);
}

.btn-search{
    background:linear-gradient(135deg, #1f2a44, #171934);
    color:#fff;
    box-shadow:0 8px 18px rgba(23,25,52,0.25);
}

.btn-search:hover{
    background:linear-gradient(135deg, #2a3558, #1c1f3f);
}

.btn-print{
    background:#111318;
    color:#fff;
    box-shadow:0 8px 18px rgba(17,19,24,.15);
}

.filter-box{
    background:rgba(255,255,255,0.82);
    backdrop-filter: blur(6px);
    border:1px solid rgba(230,223,212,.95);
    border-radius:24px;
    padding:22px;
    box-shadow:var(--shadow);
    margin-bottom:24px;
}

.filter-box-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:16px;
    flex-wrap:wrap;
}

.filter-box-head h3{
    margin:0;
    font-size:18px;
    font-weight:800;
    color:var(--text-main);
}

.filter-box-head p{
    margin:5px 0 0;
    font-size:13px;
    color:var(--text-muted);
}

.filter-form{
    display:flex;
    gap:14px;
    align-items:end;
    flex-wrap:wrap;
}

.filter-group{
    min-width:260px;
}

.filter-group label{
    display:block;
    font-size:13px;
    font-weight:700;
    color:#5d6478;
    margin-bottom:8px;
}

.filter-group input{
    width:100%;
    height:48px;
    padding:0 14px;
    border:1px solid #ded9cf;
    border-radius:14px;
    font-size:14px;
    color:var(--text-main);
    background:#fff;
    outline:none;
    transition:.2s ease;
}

.filter-group input:focus{
    border-color:#9ab64b;
    box-shadow:0 0 0 4px rgba(123, 150, 39, 0.10);
}

.cards{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:18px;
    margin-bottom:24px;
}

.card{
    position:relative;
    background:linear-gradient(180deg, #ffffff 0%, #fffdfa 100%);
    border:1px solid #eee7dc;
    border-radius:22px;
    padding:22px 20px;
    box-shadow:var(--shadow);
    overflow:hidden;
}

.card::before{
    content:"";
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:4px;
    background:var(--green);
}

.card.total::before{ background:linear-gradient(90deg, #c7a056, #e0c58c); }
.card.student::before{ background:linear-gradient(90deg, #f26a00, #ff8f3d); }
.card.staff::before{ background:linear-gradient(90deg, #3da35a, #76c76b); }
.card.tourist::before{ background:linear-gradient(90deg, #1f6fd1, #4c93ea); }

.card h3{
    font-size:13px;
    color:#8c90a2;
    margin:0 0 10px;
    font-weight:700;
}

.card .number{
    font-size:44px;
    line-height:1;
    font-weight:800;
    letter-spacing:-0.03em;
    color:var(--text-main);
    margin-bottom:6px;
}

.card .unit{
    color:#757b90;
    font-size:13px;
    font-weight:600;
}

.table-box{
    background:linear-gradient(180deg, #ffffff 0%, #fffdfa 100%);
    border:1px solid #eee7dc;
    border-radius:24px;
    box-shadow:var(--shadow);
    overflow:hidden;
}

.table-header{
    padding:20px 22px;
    border-bottom:1px solid #efe8dc;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    background:linear-gradient(180deg, #fffdf9 0%, #fbf8f2 100%);
}

.table-title h2{
    font-size:18px;
    font-weight:800;
    margin:0 0 4px;
    color:var(--text-main);
}

.table-title p{
    margin:0;
    font-size:13px;
    color:#8b8fa3;
}

.summary-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    background:#f5efe4;
    color:#6f5a33;
    font-size:13px;
    font-weight:700;
}

.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
}

th, td{
    padding:15px 14px;
    border-bottom:1px solid #f0ebe3;
    text-align:left;
    font-size:13px;
    vertical-align:middle;
}

th{
    background:#f8f5ef;
    color:#5f6579;
    font-weight:800;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:1;
}

tbody tr{
    transition:.18s ease;
}

tbody tr:hover{
    background:#fcfaf6;
}

td{
    color:#1f2437;
}

.row-index{
    width:64px;
    color:#7b8091;
    font-weight:700;
}

.name-cell{
    font-weight:700;
    color:#1d2238;
}

.time-cell{
    font-weight:700;
    color:#4f566d;
}

.badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:7px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    border:1px solid transparent;
    white-space:nowrap;
}

.badge.student{
    background:#fff1e6;
    color:#e56a00;
    border-color:#ffd4b3;
}

.badge.staff{
    background:#edf8ee;
    color:#3e8e49;
    border-color:#cdebd1;
}

.badge.tourist{
    background:#eaf2ff;
    color:#1f6fd1;
    border-color:#cfe1ff;
}

.no-data{
    padding:34px 20px;
    text-align:center;
    color:#8b8fa3;
    font-size:14px;
}

@media print{
    .sidebar,
    .filter-box,
    .btn-print,
    .sidebar-footer{
        display:none !important;
    }

    body{
        background:#fff !important;
    }

    .main{
        margin-left:0 !important;
        width:100% !important;
        padding:0 !important;
    }

    .table-box,
    .card{
        box-shadow:none !important;
        border:1px solid #ddd !important;
        background:#fff !important;
    }

    .card::before{
        display:none !important;
    }
}

@media (max-width: 1200px){
    .cards{
        grid-template-columns:repeat(2, 1fr);
    }
}

@media (max-width: 768px){
    .page-title-wrap h1{
        font-size:26px;
    }

    .cards{
        grid-template-columns:1fr;
    }

    .filter-group{
        min-width:100%;
    }

    .btn,
    .filter-form .btn{
        width:100%;
    }

    .table-header{
        align-items:flex-start;
    }
}
</style>

<div class="report-page">
    <div class="topbar">
        <div class="page-title-wrap">
            <div class="eyebrow">Daily Report</div>
            <h1>รายงานการเช็คอินรายวัน</h1>
            <p>สรุปข้อมูลผู้ลงทะเบียนและรายการเช็คอินตามวันที่เลือกในรูปแบบที่อ่านง่ายและพร้อมพิมพ์รายงาน</p>
        </div>

        <div class="top-actions">
            <button class="btn btn-print" onclick="window.print()">พิมพ์รายงาน</button>
        </div>
    </div>

    <div class="filter-box">
        <div class="filter-box-head">
            <div>
                <h3>เลือกวันที่สำหรับดูรายงาน</h3>
                <p>ระบบจะแสดงจำนวนรวมและรายการผู้เช็คอินทั้งหมดของวันที่ที่เลือก</p>
            </div>
        </div>

        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label for="report_date">วันที่รายงาน</label>
                <input type="date" id="report_date" name="report_date" value="<?php echo htmlspecialchars($selected_date); ?>">
            </div>
            <button type="submit" class="btn btn-search">ดูรายงาน</button>
        </form>
    </div>

    <div class="cards">
        <div class="card total">
            <h3>จำนวนทั้งหมด</h3>
            <div class="number"><?php echo $total; ?></div>
            <div class="unit">คน</div>
        </div>

        <div class="card student">
            <h3>นักศึกษา</h3>
            <div class="number"><?php echo $student_count; ?></div>
            <div class="unit">คน</div>
        </div>

        <div class="card staff">
            <h3>บุคลากร</h3>
            <div class="number"><?php echo $staff_count; ?></div>
            <div class="unit">คน</div>
        </div>

        <div class="card tourist">
            <h3>นักท่องเที่ยว</h3>
            <div class="number"><?php echo $tourist_count; ?></div>
            <div class="unit">คน</div>
        </div>
    </div>

    <div class="table-box">
        <div class="table-header">
            <div class="table-title">
                <h2>รายการเช็คอิน วันที่ <?php echo date('d/m/Y', strtotime($selected_date)); ?></h2>
                <p>แสดงข้อมูลผู้เช็คอินทั้งหมดของวันที่เลือก</p>
            </div>

            <div class="summary-chip">
                ทั้งหมด <?php echo $total; ?> รายการ
            </div>
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
                                <td class="row-index"><?php echo $i++; ?></td>
                                <td class="name-cell"><?php echo htmlspecialchars($row['nickname']); ?></td>
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
                                <td class="time-cell">
                                    <?php echo !empty($row['visit_time']) ? htmlspecialchars(substr($row['visit_time'], 0, 5)) : '-'; ?>
                                </td>
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
</div>

<?php
$stmtList->close();
$conn->close();
include "admin_layout_bottom.php";
?>