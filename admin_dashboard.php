<?php
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =============================
   ตั้งค่า layout กลาง
============================= */
$pageTitle  = "Admin Dashboard";
$activeMenu = "dashboard";

/* =============================
   ค้นหา + เงื่อนไขวันนี้
============================= */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE visit_date = CURDATE()";

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $where .= " AND (nickname LIKE '%$safe%' OR user_type LIKE '%$safe%')";
}

/* =============================
   ตารางวันนี้
============================= */
$sql = "SELECT id, nickname, user_type, visit_date, visit_time, created_at
        FROM tourists
        $where
        ORDER BY id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

/* =============================
   สถิติวันนี้
============================= */
$count_sql = "
    SELECT user_type, COUNT(*) as total
    FROM tourists
    WHERE visit_date = CURDATE()
    GROUP BY user_type
";
$count_result = $conn->query($count_sql);

$type_counts = [];
$chart_labels = [];
$chart_data   = [];
$total_today  = 0;

if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        $type = $row['user_type'];
        $cnt  = (int)$row['total'];

        $type_counts[$type] = $cnt;
        $chart_labels[] = $type;
        $chart_data[]   = $cnt;
        $total_today   += $cnt;
    }
} else {
    $total_today = 0;
}

include "admin_layout_top.php";
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.top-section{
    display:grid;
    grid-template-columns:420px 1fr;
    gap:16px;
    align-items:stretch;
    margin-bottom:25px;
}

.chart-box{
    background:#fff;
    border-radius:10px;
    box-shadow:0 3px 8px rgba(0,0,0,.05);
    padding:18px;
    height:260px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.stats-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(180px, 1fr));
    gap:12px;
}

.card{
    background:#fff;
    padding:18px;
    border-radius:10px;
    box-shadow:0 3px 8px rgba(0,0,0,.05);
    border-left:4px solid var(--brand);
}

.card h3{
    margin:0;
    font-size:13px;
    color:#777;
}

.card p{
    margin:5px 0 0;
    font-size:22px;
    font-weight:bold;
}

.table-box{
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 3px 8px rgba(0,0,0,.05);
}

.search{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
    gap:12px;
    flex-wrap:wrap;
}

.search form{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}

.search input{
    padding:7px 12px;
    border-radius:6px;
    border:1px solid #ccc;
    width:240px;
}

.search button{
    padding:7px 14px;
    border:none;
    border-radius:6px;
    background:var(--brand);
    color:#fff;
    cursor:pointer;
    font-weight:700;
}

.search button:hover{
    background:var(--brand2);
}

.admin-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    background:#fff;
    border:1px solid #eef1f5;
    border-radius:12px;
    overflow:hidden;
}

.admin-table thead th{
    background:linear-gradient(180deg,#f8fafc,#f1f5f9);
    color:#111827;
    font-weight:700;
    font-size:13px;
    padding:12px 14px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
    white-space:nowrap;
}

.admin-table tbody td{
    padding:12px 14px;
    border-bottom:1px solid #eef2f7;
    color:#111827;
    font-size:13px;
    vertical-align:middle;
}

.admin-table tbody tr:hover{
    background:#f9fafb;
}

.admin-table th:nth-child(1),
.admin-table td:nth-child(1){
    width:60px;
    text-align:center;
    color:#374151;
}

.admin-table th:nth-child(4),
.admin-table td:nth-child(4){
    width:110px;
    text-align:center;
    color:#374151;
}

.admin-table th:nth-child(5),
.admin-table td:nth-child(5){
    width:120px;
    text-align:center;
}

.badge{
    padding:3px 8px;
    border-radius:5px;
    font-size:12px;
    font-weight:bold;
}

.bg-stu{ background:#e3f2fd; color:#1976d2; }
.bg-staff{ background:#fff3e0; color:#f57c00; }
.bg-tour{ background:#f1f8e9; color:#388e3c; }

.admin-table .delete{
    display:inline-block;
    padding:6px 10px;
    border-radius:8px;
    border:1px solid #fecaca;
    background:#fff;
    color:#b91c1c;
    font-weight:700;
    text-decoration:none;
    transition:all .15s ease;
}

.admin-table .delete:hover{
    background:#fee2e2;
    transform:translateY(-1px);
}

.close-day-btn{
    display:inline-block;
    padding:7px 14px;
    border:none;
    border-radius:6px;
    background:var(--brand);
    color:#fff;
    font-size:13px;
    font-weight:600;
    text-decoration:none;
    cursor:pointer;
    transition:all .15s ease;
    white-space:nowrap;
}

.close-day-btn:hover{
    background:var(--brand2);
}

@media (max-width: 1050px){
    .top-section{
        grid-template-columns:1fr;
    }

    .chart-box{
        height:320px;
    }

    .stats-grid{
        grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
    }
}
</style>

<div class="top-section">
    <div class="chart-box">
        <canvas id="donutChart"></canvas>
    </div>

    <div class="stats-grid">
        <div class="card">
            <h3>ผู้ลงทะเบียนวันนี้</h3>
            <p><?php echo (int)$total_today; ?> คน</p>
        </div>

        <?php if (!empty($type_counts)): ?>
            <?php foreach ($type_counts as $type => $cnt): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($type); ?></h3>
                    <p><?php echo (int)$cnt; ?> คน</p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="table-box">
    <div class="search">
        <form method="GET">
            <input type="text" name="search" placeholder="ค้นหา..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">ค้นหา</button>
        </form>

        <a href="archive_today.php"
           class="close-day-btn"
           onclick="return confirm('ต้องการปิดวันหรือไม่?')">ปิดวัน</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>#</th>
                <th>ชื่อเล่น</th>
                <th>ประเภท</th>
                <th>เวลา</th>
                <th>จัดการ</th>
            </tr>
        </thead>

        <tbody id="liveTable">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php $i = 1; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $badge = ($row['user_type'] == 'นักศึกษา') ? 'bg-stu'
                           : (($row['user_type'] == 'บุคลากร') ? 'bg-staff' : 'bg-tour');

                    $timeText = '-';
                    if (!empty($row['created_at'])) {
                        $ts = strtotime($row['created_at']);
                        if ($ts !== false) $timeText = date('H:i', $ts);
                    } elseif (!empty($row['visit_time'])) {
                        $ts2 = strtotime($row['visit_time']);
                        if ($ts2 !== false) $timeText = date('H:i', $ts2);
                    }
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nickname']); ?></strong></td>
                        <td>
                            <span class="badge <?php echo $badge; ?>">
                                <?php echo htmlspecialchars($row['user_type']); ?>
                            </span>
                        </td>
                        <td><?php echo $timeText; ?></td>
                        <td>
                            <a class="delete"
                               href="delete_tourist.php?id=<?php echo (int)$row['id']; ?>"
                               onclick="return confirm('ยืนยันการลบ?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:#999;">ไม่มีข้อมูลวันนี้</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($chart_data); ?>,
            backgroundColor: ['#1976d2', '#f57c00', '#388e3c'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

setInterval(function () {
    const params = new URLSearchParams(window.location.search);
    const url = 'fetch_today.php' + (params.toString() ? ('?' + params.toString()) : '');

    fetch(url)
        .then(res => res.text())
        .then(html => {
            const el = document.getElementById('liveTable');
            if (el) el.innerHTML = html;
        });
}, 5000);
</script>

<?php
$conn->close();
include "admin_layout_bottom.php";
?>