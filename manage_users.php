<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Bangkok');

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DB Connection
|--------------------------------------------------------------------------
*/
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli("localhost", "root", "", "backoffice_db");
    $conn->set_charset("utf8mb4");
    if ($conn->connect_error) {
        die("DB Error: " . $conn->connect_error);
    }
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

/*
|--------------------------------------------------------------------------
| เช็กคอลัมน์ที่มีจริงในตาราง users
|--------------------------------------------------------------------------
*/
$hasAvatar     = hasColumn($conn, 'users', 'avatar');
$hasPhone      = hasColumn($conn, 'users', 'phone');
$hasRole       = hasColumn($conn, 'users', 'role');
$hasVerified   = hasColumn($conn, 'users', 'is_verified');
$hasCreatedAt  = hasColumn($conn, 'users', 'created_at');

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$where = '';
$params = [];
$types  = '';

if ($search !== '') {
    $searchLike = "%{$search}%";
    $searchFields = ["fullname", "email"];
    if ($hasPhone) {
        $searchFields[] = "phone";
    }

    $conditions = [];
    foreach ($searchFields as $field) {
        $conditions[] = "$field LIKE ?";
        $params[] = $searchLike;
        $types .= 's';
    }

    $where = "WHERE " . implode(" OR ", $conditions);
}

/*
|--------------------------------------------------------------------------
| Summary cards
|--------------------------------------------------------------------------
*/
$totalUsers = 0;
$totalAdmins = 0;
$totalMembers = 0;
$totalVerified = 0;

$resTotal = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($resTotal) {
    $totalUsers = (int)($resTotal->fetch_assoc()['total'] ?? 0);
}

if ($hasRole) {
    $resAdmins = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
    if ($resAdmins) {
        $totalAdmins = (int)($resAdmins->fetch_assoc()['total'] ?? 0);
    }

    $resMembers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role <> 'admin' OR role IS NULL OR role = ''");
    if ($resMembers) {
        $totalMembers = (int)($resMembers->fetch_assoc()['total'] ?? 0);
    }
} else {
    $totalMembers = $totalUsers;
}

if ($hasVerified) {
    $resVerified = $conn->query("SELECT COUNT(*) AS total FROM users WHERE is_verified = 1");
    if ($resVerified) {
        $totalVerified = (int)($resVerified->fetch_assoc()['total'] ?? 0);
    }
}

/*
|--------------------------------------------------------------------------
| Users list
|--------------------------------------------------------------------------
*/
$selectFields = ["id", "fullname", "email"];
if ($hasPhone)     $selectFields[] = "phone";
if ($hasAvatar)    $selectFields[] = "avatar";
if ($hasRole)      $selectFields[] = "role";
if ($hasVerified)  $selectFields[] = "is_verified";
if ($hasCreatedAt) $selectFields[] = "created_at";

$sql = "SELECT " . implode(", ", $selectFields) . " FROM users $where ORDER BY id DESC";

if ($search !== '') {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $usersResult = $stmt->get_result();
} else {
    $usersResult = $conn->query($sql);
    $stmt = null;
}

$pageTitle  = "จัดการผู้ใช้งาน";
$activeMenu = "users";
include 'admin_layout_top.php';
?>

<style>
:root{
  --accent:#c9a96e;
  --accent-dim:rgba(201,169,110,0.12);
  --ink:#1a1a2e;
  --card:#fff;
  --muted:#7a7a8c;
  --border:#e8e4de;
  --success:#16a34a;
  --danger:#dc2626;
  --info:#2563eb;
}

.mu-wrap{
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 0 48px;
}

.mu-hero{
  background: var(--ink);
  border-radius: 20px;
  padding: 28px 32px;
  color: #fff;
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: center;
  margin-bottom: 22px;
  position: relative;
  overflow: hidden;
}
.mu-hero::before{
  content:'';
  position:absolute;
  width:320px;
  height:320px;
  border-radius:50%;
  background: radial-gradient(circle, rgba(201,169,110,.14) 0%, transparent 70%);
  top:-120px;
  right:-80px;
  pointer-events:none;
}
.mu-hero h1{
  font-size: 1.7rem;
  margin: 0 0 6px;
  font-weight: 800;
}
.mu-hero p{
  margin: 0;
  color: rgba(255,255,255,.65);
  font-size: .92rem;
}

.mu-stats{
  display:grid;
  grid-template-columns: repeat(4, 1fr);
  gap:16px;
  margin-bottom:22px;
}
.mu-stat{
  background:var(--card);
  border-radius:16px;
  padding:18px 20px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
  border:1px solid var(--border);
}
.mu-stat .label{
  font-size:.78rem;
  color:var(--muted);
  margin-bottom:8px;
}
.mu-stat .value{
  font-size:1.6rem;
  font-weight:800;
  color:var(--ink);
}

.mu-card{
  background:var(--card);
  border-radius:18px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);
  overflow:hidden;
}
.mu-card-header{
  padding:18px 22px;
  border-bottom:1px solid var(--border);
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
}
.mu-card-title{
  font-size:.95rem;
  font-weight:700;
  color:var(--ink);
}

.search-form{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.search-wrap{
  position:relative;
}
.search-wrap::before{
  content:'🔍';
  position:absolute;
  left:11px;
  top:50%;
  transform:translateY(-50%);
  font-size:.78rem;
  pointer-events:none;
}
.search-input{
  width:260px;
  padding:10px 12px 10px 36px;
  border:1.5px solid var(--border);
  border-radius:10px;
  background:#fafaf8;
  outline:none;
  font-family:'Sarabun',sans-serif;
}
.search-input:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(201,169,110,.12);
}
.btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  padding:10px 14px;
  border:none;
  border-radius:10px;
  font-family:'Sarabun',sans-serif;
  font-size:.84rem;
  font-weight:700;
  text-decoration:none;
  cursor:pointer;
  transition:.2s ease;
}
.btn:hover{ transform:translateY(-1px); }
.btn-primary{
  background:var(--ink);
  color:#fff;
}
.btn-ghost{
  background:transparent;
  color:var(--muted);
  border:1.5px solid var(--border);
}
.btn-ghost:hover{
  border-color:var(--accent);
  color:var(--accent);
}

.table-wrap{
  width:100%;
  overflow:auto;
}
.mu-table{
  width:100%;
  border-collapse:collapse;
  min-width:980px;
}
.mu-table th,
.mu-table td{
  padding:14px 16px;
  border-bottom:1px solid var(--border);
  text-align:left;
  vertical-align:middle;
}
.mu-table th{
  background:#faf7f2;
  color:var(--muted);
  font-size:.74rem;
  text-transform:uppercase;
  letter-spacing:.08em;
}
.mu-table tr:hover td{
  background:#fcfbf9;
}

.user-cell{
  display:flex;
  align-items:center;
  gap:12px;
}
.user-avatar{
  width:44px;
  height:44px;
  border-radius:50%;
  overflow:hidden;
  background:var(--accent-dim);
  border:2px solid rgba(201,169,110,.3);
  display:flex;
  align-items:center;
  justify-content:center;
  color:var(--accent);
  font-weight:800;
  flex-shrink:0;
}
.user-avatar img{
  width:100%;
  height:100%;
  object-fit:cover;
}
.user-name{
  font-weight:700;
  color:var(--ink);
}
.user-sub{
  font-size:.8rem;
  color:var(--muted);
  margin-top:2px;
}

.badge{
  display:inline-flex;
  align-items:center;
  gap:5px;
  padding:5px 10px;
  border-radius:999px;
  font-size:.74rem;
  font-weight:700;
  white-space:nowrap;
}
.badge-admin{
  background:rgba(201,169,110,.16);
  color:#9a6d1f;
  border:1px solid rgba(201,169,110,.3);
}
.badge-user{
  background:rgba(255,255,255,.4);
  color:#5f6472;
  border:1px solid var(--border);
}
.badge-success{
  background:rgba(22,163,74,.1);
  color:var(--success);
  border:1px solid rgba(22,163,74,.22);
}
.badge-muted{
  background:rgba(148,163,184,.12);
  color:#64748b;
  border:1px solid rgba(148,163,184,.18);
}

.empty-box{
  padding:36px 20px;
  text-align:center;
  color:var(--muted);
}

.col-actions{
  white-space:nowrap;
}
.action-link{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:8px 12px;
  border-radius:8px;
  font-size:.78rem;
  font-weight:700;
  text-decoration:none;
  border:1px solid var(--border);
  color:var(--ink);
  background:#fff;
  cursor:pointer;
}
.action-link:hover{
  border-color:var(--accent);
  color:var(--accent);
}

@media (max-width: 1000px){
  .mu-stats{
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 640px){
  .mu-stats{
    grid-template-columns: 1fr;
  }
  .mu-hero{
    flex-direction:column;
    align-items:flex-start;
  }
  .search-input{
    width:100%;
  }
}
</style>

<div class="mu-wrap">
  <div class="mu-hero">
    <div>
      <h1>จัดการผู้ใช้งาน</h1>
      <p>ดูข้อมูลสมาชิกทั้งหมดจากระบบหลังบ้าน ค้นหาและตรวจสอบข้อมูลได้จากหน้านี้</p>
    </div>
    <div style="font-size:3rem; opacity:.18; line-height:1;">👥</div>
  </div>

  <div class="mu-stats">
    <div class="mu-stat">
      <div class="label">ผู้ใช้งานทั้งหมด</div>
      <div class="value"><?= number_format($totalUsers) ?></div>
    </div>
    <div class="mu-stat">
      <div class="label">ผู้ดูแลระบบ</div>
      <div class="value"><?= number_format($totalAdmins) ?></div>
    </div>
    <div class="mu-stat">
      <div class="label">สมาชิกทั่วไป</div>
      <div class="value"><?= number_format($totalMembers) ?></div>
    </div>
    <div class="mu-stat">
      <div class="label">ยืนยันอีเมลแล้ว</div>
      <div class="value"><?= number_format($totalVerified) ?></div>
    </div>
  </div>

  <div class="mu-card">
    <div class="mu-card-header">
      <div class="mu-card-title">รายการผู้ใช้งานในระบบ</div>

      <form method="GET" class="search-form">
        <div class="search-wrap">
          <input
            type="text"
            name="search"
            class="search-input"
            placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร"
            value="<?= h($search) ?>"
          >
        </div>
        <button type="submit" class="btn btn-primary">ค้นหา</button>
        <?php if ($search !== ''): ?>
          <a href="manage_users.php" class="btn btn-ghost">ล้างค้นหา</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="table-wrap">
      <?php if ($usersResult && $usersResult->num_rows > 0): ?>
        <table class="mu-table">
          <thead>
            <tr>
              <th style="width: 280px;">ผู้ใช้งาน</th>
              <th>เบอร์โทร</th>
              <th>สิทธิ์</th>
              <th>ยืนยันอีเมล</th>
              <th>วันที่สมัคร</th>
              <th style="width: 150px;">การจัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($user = $usersResult->fetch_assoc()): ?>
              <?php
                $name = trim((string)($user['fullname'] ?? 'ไม่ระบุชื่อ'));
                $firstChar = function_exists('mb_substr')
                    ? mb_strtoupper(mb_substr($name, 0, 1), 'UTF-8')
                    : strtoupper(substr($name, 0, 1));

                $avatar = $hasAvatar ? trim((string)($user['avatar'] ?? '')) : '';
                $role   = $hasRole ? trim((string)($user['role'] ?? 'user')) : 'user';
                $phone  = $hasPhone ? trim((string)($user['phone'] ?? '')) : '';
                $isVerified = $hasVerified ? (int)($user['is_verified'] ?? 0) : 0;
                $createdAt = $hasCreatedAt ? trim((string)($user['created_at'] ?? '')) : '';
              ?>
              <tr>
                <td>
                  <div class="user-cell">
                    <div class="user-avatar">
                      <?php if ($avatar !== '' && file_exists(__DIR__ . '/' . $avatar)): ?>
                        <img src="<?= h($avatar) ?>?v=<?= time() ?>" alt="avatar">
                      <?php else: ?>
                        <?= h($firstChar) ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <div class="user-name"><?= h($name) ?></div>
                      <div class="user-sub"><?= h($user['email'] ?? '-') ?></div>
                    </div>
                  </div>
                </td>

                <td><?= $phone !== '' ? h($phone) : '-' ?></td>

                <td>
                  <?php if ($role === 'admin'): ?>
                    <span class="badge badge-admin">⚡ Administrator</span>
                  <?php else: ?>
                    <span class="badge badge-user">👤 Member</span>
                  <?php endif; ?>
                </td>

                <td>
                  <?php if ($hasVerified): ?>
                    <?php if ($isVerified === 1): ?>
                      <span class="badge badge-success">✅ ยืนยันแล้ว</span>
                    <?php else: ?>
                      <span class="badge badge-muted">⏳ ยังไม่ยืนยัน</span>
                    <?php endif; ?>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>

                <td>
                  <?= $createdAt !== '' ? h(date('d/m/Y H:i', strtotime($createdAt))) : '-' ?>
                </td>

                <td class="col-actions">
                  <?php if ((int)$user['id'] === (int)$_SESSION['user_id']): ?>
                    <a href="admin_profile.php" class="action-link">👁 โปรไฟล์ฉัน</a>
                  <?php else: ?>
                    <a href="user_view.php?id=<?= (int)$user['id'] ?>" class="action-link">👁 ดูข้อมูล</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-box">
          ไม่พบข้อมูลผู้ใช้งาน
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
include 'admin_layout_bottom.php';
$conn->close();
?>