<?php
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$pageTitle  = "จัดเก็บ Walk-in";
$activeMenu = "walkin_archive";

// ── ensure archived column exists ──
$conn->query("ALTER TABLE tourists ADD COLUMN IF NOT EXISTS archived TINYINT(1) DEFAULT 0");

// ── filter by date ──
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

// ── restore single record ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $rid = (int)$_POST['restore_id'];
    $conn->query("UPDATE tourists SET archived=0 WHERE id=$rid");
    header("Location: admin_walkin_archive.php" . (http_build_query(array_filter(['date'=>$filterDate,'search'=>$search])) ? '?'.http_build_query(array_filter(['date'=>$filterDate,'search'=>$search])) : ''));
    exit;
}

// ── delete single record ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    $conn->query("DELETE FROM tourists WHERE id=$did AND archived=1");
    header("Location: admin_walkin_archive.php" . (http_build_query(array_filter(['date'=>$filterDate,'search'=>$search])) ? '?'.http_build_query(array_filter(['date'=>$filterDate,'search'=>$search])) : ''));
    exit;
}

// ── stats ──
$totalArchived  = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE archived=1")->fetch_assoc()['c'];
$totalDays      = (int)$conn->query("SELECT COUNT(DISTINCT visit_date) c FROM tourists WHERE archived=1")->fetch_assoc()['c'];
$studentCount   = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE archived=1 AND user_type='นักศึกษา'")->fetch_assoc()['c'];
$staffCount     = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE archived=1 AND user_type='บุคลากร'")->fetch_assoc()['c'];
$touristCount   = (int)$conn->query("SELECT COUNT(*) c FROM tourists WHERE archived=1 AND user_type='นักท่องเที่ยว'")->fetch_assoc()['c'];

// ── list of archived dates for filter dropdown ──
$datesRes = $conn->query("SELECT DISTINCT visit_date FROM tourists WHERE archived=1 ORDER BY visit_date DESC");
$dateOptions = [];
while ($dr = $datesRes->fetch_assoc()) $dateOptions[] = $dr['visit_date'];

// ── query rows ──
$where = "WHERE archived=1";
if ($filterDate) {
    $safe = $conn->real_escape_string($filterDate);
    $where .= " AND visit_date='$safe'";
}
if ($search) {
    $safe = $conn->real_escape_string($search);
    $where .= " AND (nickname LIKE '%$safe%' OR user_type LIKE '%$safe%' OR gender LIKE '%$safe%')";
}

$rows = $conn->query("SELECT id, nickname, gender, age, user_type, visit_date, visit_time FROM tourists $where ORDER BY visit_date DESC, visit_time ASC");
$totalRows = $rows ? $rows->num_rows : 0;

include "admin_layout_top.php";
?>

<style>
.ar-page { padding: 4px 0; }
.ar-topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:18px; margin-bottom:22px; flex-wrap:wrap; }
.ar-title { font-size:1.6rem; font-weight:800; color:var(--ink); margin:0 0 4px; }
.ar-sub   { font-size:.82rem; color:var(--muted); }

/* stat cards */
.ar-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:22px; }
.ar-card  {
  background:#fff; border-radius:14px; padding:18px 16px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
  border-top:3px solid var(--accent);
}
.ar-card.c-student { border-top-color:#f26a00; }
.ar-card.c-staff   { border-top-color:#3da35a; }
.ar-card.c-tourist { border-top-color:#1f6fd1; }
.ar-card.c-days    { border-top-color:#7c3aed; }
.ar-card h4 { font-size:.7rem; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin:0 0 8px; }
.ar-card .num { font-size:2rem; font-weight:800; color:var(--ink); line-height:1; }
.ar-card .unit { font-size:.75rem; color:var(--muted); margin-top:4px; }

/* filter box */
.ar-filter {
  background:#fff; border:1px solid #eee7dc; border-radius:16px;
  padding:18px 20px; margin-bottom:20px;
  box-shadow:0 2px 8px rgba(26,26,46,.05);
}
.ar-filter form { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.ar-filter label { display:block; font-size:.75rem; font-weight:700; color:#5d6478; margin-bottom:6px; }
.ar-filter select, .ar-filter input[type=text] {
  height:40px; padding:0 12px; border:1px solid #ded9cf; border-radius:10px;
  font-size:.85rem; color:var(--ink); background:#fff; outline:none;
  font-family:'Sarabun',sans-serif;
}
.ar-filter select { min-width:180px; }
.ar-filter input[type=text] { min-width:220px; }

/* table box */
.ar-table-box {
  background:#fff; border:1px solid #eee7dc; border-radius:20px;
  box-shadow:0 4px 20px rgba(26,26,46,.07); overflow:hidden;
}
.ar-table-head {
  padding:16px 20px; border-bottom:1px solid #efe8dc;
  display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;
  background:linear-gradient(180deg,#fffdf9,#fbf8f2);
}
.ar-table-head h2 { font-size:1rem; font-weight:800; color:var(--ink); margin:0 0 3px; }
.ar-table-head p  { font-size:.78rem; color:var(--muted); margin:0; }
.chip {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 14px; border-radius:999px;
  background:#f5efe4; color:#6f5a33;
  font-size:.8rem; font-weight:700;
}
.ar-table { width:100%; border-collapse:separate; border-spacing:0; }
.ar-table th, .ar-table td { padding:13px 14px; border-bottom:1px solid #f0ebe3; text-align:left; font-size:.82rem; vertical-align:middle; }
.ar-table th { background:#f8f5ef; color:#5f6579; font-weight:800; white-space:nowrap; }
.ar-table tbody tr:hover { background:#fcfaf6; }
.ar-table td { color:#1f2437; }
.name-cell { font-weight:700; color:#1d2238; }
.time-cell { font-weight:700; color:#4f566d; }
.badge.student { background:#fff1e6; color:#e56a00; border:1px solid #ffd4b3; padding:5px 10px; border-radius:999px; font-size:.75rem; font-weight:800; }
.badge.staff   { background:#edf8ee; color:#3e8e49; border:1px solid #cdebd1; padding:5px 10px; border-radius:999px; font-size:.75rem; font-weight:800; }
.badge.tourist { background:#eaf2ff; color:#1f6fd1; border:1px solid #cfe1ff; padding:5px 10px; border-radius:999px; font-size:.75rem; font-weight:800; }
.no-data { text-align:center; color:var(--muted); padding:36px; font-size:.85rem; }

.action-row { display:flex; gap:6px; align-items:center; }
</style>

<div class="ar-page">
  <div class="ar-topbar">
    <div>
      <div class="ar-title">📦 จัดเก็บ Walk-in</div>
      <div class="ar-sub">ข้อมูลผู้เข้าชมที่ปิดวันแล้ว — สามารถค้นหา กู้คืน หรือลบถาวรได้</div>
    </div>
  </div>

  <!-- stat cards -->
  <div class="ar-cards">
    <div class="ar-card">
      <h4>ทั้งหมด</h4>
      <div class="num"><?= number_format($totalArchived) ?></div>
      <div class="unit">รายการ</div>
    </div>
    <div class="ar-card c-days">
      <h4>จำนวนวัน</h4>
      <div class="num"><?= number_format($totalDays) ?></div>
      <div class="unit">วัน</div>
    </div>
    <div class="ar-card c-student">
      <h4>นักศึกษา</h4>
      <div class="num"><?= number_format($studentCount) ?></div>
      <div class="unit">คน</div>
    </div>
    <div class="ar-card c-staff">
      <h4>บุคลากร</h4>
      <div class="num"><?= number_format($staffCount) ?></div>
      <div class="unit">คน</div>
    </div>
    <div class="ar-card c-tourist">
      <h4>นักท่องเที่ยว</h4>
      <div class="num"><?= number_format($touristCount) ?></div>
      <div class="unit">คน</div>
    </div>
  </div>

  <!-- filter -->
  <div class="ar-filter">
    <form method="GET">
      <div>
        <label>กรองตามวันที่</label>
        <select name="date">
          <option value="">— ทั้งหมด —</option>
          <?php foreach ($dateOptions as $d): ?>
            <option value="<?= $d ?>" <?= $filterDate===$d?'selected':'' ?>>
              <?= date('d/m/Y', strtotime($d)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>ค้นหาชื่อ / ประเภท</label>
        <input type="text" name="search" placeholder="ค้นหา..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="height:40px;">ค้นหา</button>
      <?php if ($filterDate || $search): ?>
        <a href="admin_walkin_archive.php" class="btn btn-ghost btn-sm" style="height:40px;">ล้าง</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- table -->
  <div class="ar-table-box">
    <div class="ar-table-head">
      <div>
        <h2>รายการที่จัดเก็บแล้ว<?= $filterDate ? ' — ' . date('d/m/Y', strtotime($filterDate)) : '' ?></h2>
        <p>กดปุ่ม "กู้คืน" เพื่อนำกลับมาแสดงในแดชบอร์ด หรือ "ลบถาวร" เพื่อลบออกจากระบบ</p>
      </div>
      <div class="chip">ทั้งหมด <?= $totalRows ?> รายการ</div>
    </div>
    <div style="overflow-x:auto;">
      <table class="ar-table">
        <thead>
          <tr>
            <th style="width:52px;">#</th>
            <th>ชื่อเล่น</th>
            <th>เพศ</th>
            <th>อายุ</th>
            <th>ประเภท</th>
            <th>วันที่เข้าชม</th>
            <th>เวลา</th>
            <th style="width:160px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows && $totalRows > 0): ?>
            <?php $n = 1; while ($row = $rows->fetch_assoc()): ?>
              <?php
              $bType = $row['user_type'];
              $badgeClass = ($bType === 'นักศึกษา') ? 'student' : (($bType === 'บุคลากร') ? 'staff' : 'tourist');
              $timeStr = !empty($row['visit_time']) ? substr($row['visit_time'], 0, 5) : '-';
              $age = ($row['age'] !== null && $row['age'] !== '') ? (int)$row['age'] . ' ปี' : '-';
              ?>
              <tr>
                <td style="color:#7b8091;font-weight:700;"><?= $n++ ?></td>
                <td class="name-cell"><?= htmlspecialchars($row['nickname']) ?></td>
                <td><?= htmlspecialchars($row['gender'] ?? '-') ?></td>
                <td><?= $age ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($bType) ?></span></td>
                <td><?= date('d/m/Y', strtotime($row['visit_date'])) ?></td>
                <td class="time-cell"><?= $timeStr ?></td>
                <td>
                  <div class="action-row">
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="restore_id" value="<?= (int)$row['id'] ?>">
                      <button type="submit" class="btn btn-sm" style="background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9;" onclick="return confirm('กู้คืนรายการนี้?')">↩ กู้คืน</button>
                    </form>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('ลบถาวร? ไม่สามารถกู้คืนได้')">🗑 ลบ</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="no-data">ไม่มีข้อมูลที่จัดเก็บ<?= $filterDate || $search ? 'ตามเงื่อนไขที่เลือก' : '' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$conn->close();
include "admin_layout_bottom.php";
?>
