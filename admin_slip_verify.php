<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php"); exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$currentPage = basename($_SERVER['PHP_SELF']);
$message = ''; $message_type = 'success';

// เพิ่มคอลัมน์ที่จำเป็น
foreach ([
    "payment_status ENUM('unpaid','waiting_verify','paid','failed','manual_review') DEFAULT 'unpaid'",
    "payment_slip VARCHAR(500) DEFAULT NULL",
    "paid_at DATETIME DEFAULT NULL",
] as $colDef) {
    $colName = strtok($colDef, ' ');
    $chk = $conn->query("SHOW COLUMNS FROM room_bookings LIKE '$colName'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE room_bookings ADD COLUMN $colDef");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'approve_slip' && $id > 0) {
        $st = $conn->prepare("UPDATE room_bookings SET payment_status='paid', booking_status='approved', paid_at=NOW() WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        // อัปเดตสถานะใน payment_slips ด้วย
        $conn->query("UPDATE payment_slips SET verification_status='paid', reviewed_by=".(int)$_SESSION['user_id'].", reviewed_at=NOW() WHERE booking_id=$id AND booking_type='room'");
        $message = "อนุมัติสลิปเรียบร้อยแล้ว";
    } elseif ($action === 'reject_slip' && $id > 0) {
        $reason = trim($_POST['reason'] ?? 'สลิปไม่ถูกต้อง');
        $st = $conn->prepare("UPDATE room_bookings SET payment_status='failed', payment_slip=NULL WHERE id=?");
        $st->bind_param("i", $id); $st->execute(); $st->close();
        $conn->query("UPDATE payment_slips SET verification_status='rejected', verification_reason='".($conn->real_escape_string($reason))."', reviewed_by=".(int)$_SESSION['user_id'].", reviewed_at=NOW() WHERE booking_id=$id AND booking_type='room'");
        $message = "ปฏิเสธสลิปเรียบร้อยแล้ว";
    }
    header("Location: {$currentPage}?msg=".urlencode($message)."&type=".urlencode($message_type)); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $message_type = $_GET['type'] ?? 'success'; }

$filter  = $_GET['filter'] ?? 'waiting_verify';
$search  = trim($_GET['search'] ?? '');

$validFilters = ['waiting_verify','all','paid','failed'];
if (!in_array($filter, $validFilters)) $filter = 'waiting_verify';

$where = $filter === 'all'
    ? "WHERE payment_slip IS NOT NULL"
    : "WHERE payment_status = ?";

if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
}

$stmt = $conn->prepare("SELECT * FROM room_bookings {$where} ORDER BY id DESC");

if ($filter === 'all' && $search !== '') {
    $like = "%{$search}%";
    $stmt->bind_param("sss", $like, $like, $like);
} elseif ($filter !== 'all' && $search === '') {
    $stmt->bind_param("s", $filter);
} elseif ($filter !== 'all' && $search !== '') {
    $like = "%{$search}%";
    $stmt->bind_param("ssss", $filter, $like, $like, $like);
}

$stmt->execute(); $result = $stmt->get_result();
$total = $result->num_rows;

// สถิติ
$stats = $conn->query("SELECT
    SUM(payment_status='waiting_verify') w,
    SUM(payment_status='paid') p,
    SUM(payment_status='failed') f
    FROM room_bookings WHERE payment_slip IS NOT NULL")->fetch_assoc();

$pageTitle = "ตรวจสอบสลิปการชำระเงิน"; $activeMenu = "booking";
include 'admin_layout_top.php';
?>
<style>
:root{--gold:#c9a96e;--gold-dim:rgba(201,169,110,.12);--ink:#1a1a2e;--bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;--danger:#dc2626;--success:#16a34a;--warning:#d97706;}

.sv-banner{border-radius:18px;padding:24px 30px;margin-bottom:24px;
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
  position:relative;overflow:hidden;
  background:linear-gradient(135deg,#1a1a2e 0%,#1e3a5f 60%,#1d4ed8 100%);}
.sv-banner::before{content:'';position:absolute;width:280px;height:280px;border-radius:50%;
  background:rgba(255,255,255,.06);top:-80px;right:-60px;pointer-events:none;}
.sv-banner h1{font-size:1.3rem;font-weight:800;color:#fff;margin:0 0 4px;}
.sv-banner p{font-size:.78rem;color:rgba(255,255,255,.7);margin:0;}
.sv-banner-links{display:flex;gap:9px;flex-wrap:wrap;position:relative;z-index:1;}
.sv-link{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;border-radius:8px;
  font-size:.75rem;font-weight:700;text-decoration:none;color:#fff;
  border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);transition:.2s;}
.sv-link:hover{background:rgba(255,255,255,.2);transform:translateY(-1px);}

.sv-alert{display:flex;align-items:center;gap:10px;padding:12px 18px;
  border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:20px;}
.sv-alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:var(--success);}
.sv-alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:var(--danger);}

.sv-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px;}
.sv-stat{background:var(--card);border-radius:14px;padding:18px 20px;
  box-shadow:0 2px 12px rgba(26,26,46,.06);border-top:3px solid var(--border);}
.sv-stat:nth-child(1){border-top-color:#f59e0b;}
.sv-stat:nth-child(2){border-top-color:var(--success);}
.sv-stat:nth-child(3){border-top-color:var(--danger);}
.sv-stat-label{font-size:.67rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;font-weight:700;}
.sv-stat-val{font-size:1.8rem;font-weight:800;line-height:1;}

.sv-card{background:var(--card);border-radius:18px;box-shadow:0 2px 16px rgba(26,26,46,.07);overflow:hidden;}
.sv-card-head{padding:15px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.sv-title{font-size:.9rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px;}
.sv-title::before{content:'';display:inline-block;width:3px;height:14px;
  background:#1d4ed8;border-radius:2px;}
.sv-cnt{background:#eff6ff;color:#1d4ed8;font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}

/* Filter tabs */
.sv-tabs{display:flex;gap:6px;padding:12px 22px;border-bottom:1px solid var(--border);background:#fdfcfa;flex-wrap:wrap;}
.sv-tab{padding:6px 14px;border-radius:8px;font-size:.77rem;font-weight:700;text-decoration:none;
  color:var(--muted);background:transparent;border:1.5px solid transparent;transition:.18s;}
.sv-tab:hover{color:var(--ink);}
.sv-tab.active{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}

/* Search */
.sv-search{padding:12px 22px;border-bottom:1px solid var(--border);display:flex;gap:9px;flex-wrap:wrap;}
.sv-sw{position:relative;flex:1;min-width:180px;}
.sv-sw::before{content:'🔍';position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:.72rem;pointer-events:none;}
.sv-sw input{width:100%;padding:8px 12px 8px 34px;border:1.5px solid var(--border);
  border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.84rem;color:var(--ink);
  background:#fff;outline:none;}
.sv-sw input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.sv-btn{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;border:none;
  border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:700;
  cursor:pointer;text-decoration:none;transition:.18s;white-space:nowrap;}
.sv-btn:hover{transform:translateY(-1px);}
.sv-btn-primary{background:var(--ink);color:#fff;}
.sv-btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
.sv-btn-ghost:hover{border-color:var(--gold);color:var(--gold);}
.sv-btn-approve{background:var(--success);color:#fff;}
.sv-btn-approve:hover{background:#15803d;box-shadow:0 4px 10px rgba(22,163,74,.3);}
.sv-btn-reject{background:#fef2f2;color:var(--danger);border:1.5px solid #fca5a5;}
.sv-btn-reject:hover{background:#fee2e2;}

/* Cards grid */
.sv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:18px;padding:20px;}
.sv-booking-card{border:1.5px solid var(--border);border-radius:14px;overflow:hidden;transition:.2s;}
.sv-booking-card:hover{box-shadow:0 6px 20px rgba(26,26,46,.1);transform:translateY(-2px);}
.sv-bc-head{padding:12px 16px;background:#fdfcfa;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:8px;}
.sv-bc-name{font-weight:800;font-size:.9rem;color:var(--ink);}
.sv-bc-meta{font-size:.72rem;color:var(--muted);}
.sv-bc-body{padding:14px 16px;}
.sv-bc-info{display:grid;grid-template-columns:1fr 1fr;gap:6px 10px;margin-bottom:12px;}
.sv-bc-row{font-size:.77rem;}
.sv-bc-row span:first-child{color:var(--muted);}
.sv-bc-row span:last-child{font-weight:700;color:var(--ink);}
.sv-slip-wrap{border-radius:10px;overflow:hidden;background:#f3f4f6;
  display:flex;align-items:center;justify-content:center;min-height:200px;margin-bottom:12px;cursor:pointer;}
.sv-slip-wrap img{width:100%;max-height:300px;object-fit:contain;display:block;}
.sv-slip-none{color:var(--muted);font-size:.8rem;padding:40px;text-align:center;}
.sv-bc-actions{display:flex;gap:8px;}
.sv-bc-actions form{flex:1;}
.sv-bc-actions button{width:100%;}
.sv-inline{display:inline;margin:0;}

/* Status badge */
.sv-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.68rem;font-weight:700;}
.sv-badge-wait{background:#fffbeb;color:#92400e;}
.sv-badge-paid{background:#f0fdf4;color:#166534;}
.sv-badge-fail{background:#fef2f2;color:#991b1b;}
.sv-badge-manual{background:#eff6ff;color:#1e40af;}

.sv-empty{padding:60px 24px;text-align:center;color:var(--muted);}
.sv-empty-ico{font-size:2.8rem;opacity:.2;margin-bottom:12px;}

/* Modal lightbox */
#sv-lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;
  align-items:center;justify-content:center;padding:20px;}
#sv-lightbox.open{display:flex;}
#sv-lightbox img{max-width:90vw;max-height:90vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,.5);}
#sv-lightbox-close{position:fixed;top:20px;right:24px;font-size:2rem;color:#fff;cursor:pointer;
  background:none;border:none;line-height:1;}

@media(max-width:760px){
  .sv-stats{grid-template-columns:1fr 1fr;}
  .sv-grid{grid-template-columns:1fr;padding:12px;}
}
</style>

<!-- Lightbox -->
<div id="sv-lightbox">
  <button id="sv-lightbox-close" onclick="closeLightbox()">✕</button>
  <img id="sv-lightbox-img" src="" alt="สลิป">
</div>

<?php if ($message !== ''): ?>
<div class="sv-alert <?= $message_type==='error'?'sv-alert-err':'sv-alert-ok' ?>">
  <?= $message_type==='error'?'⚠':'✓' ?> <?= h($message) ?>
</div>
<?php endif; ?>

<div class="sv-banner">
  <div>
    <h1>🧾 ตรวจสอบสลิปการชำระเงิน</h1>
    <p>ตรวจสอบและอนุมัติสลิปที่ลูกค้าอัปโหลดสำหรับการจองห้องพัก</p>
  </div>
  <div class="sv-banner-links">
    <a href="admin_booking_list.php"     class="sv-link">← รายการจอง</a>
    <a href="admin_booking_approved.php" class="sv-link">✅ อนุมัติแล้ว</a>
  </div>
</div>

<!-- Stats -->
<div class="sv-stats">
  <div class="sv-stat">
    <div class="sv-stat-label">รอตรวจสอบ</div>
    <div class="sv-stat-val" style="color:#d97706;"><?= (int)($stats['w']??0) ?></div>
  </div>
  <div class="sv-stat">
    <div class="sv-stat-label">ผ่านการอนุมัติ</div>
    <div class="sv-stat-val" style="color:var(--success);"><?= (int)($stats['p']??0) ?></div>
  </div>
  <div class="sv-stat">
    <div class="sv-stat-label">ปฏิเสธ</div>
    <div class="sv-stat-val" style="color:var(--danger);"><?= (int)($stats['f']??0) ?></div>
  </div>
</div>

<div class="sv-card">
  <div class="sv-card-head">
    <div class="sv-title">รายการสลิป</div>
    <span class="sv-cnt"><?= $total ?> รายการ</span>
  </div>

  <!-- Filter tabs -->
  <div class="sv-tabs">
    <?php
    $tabs = [
      'waiting_verify' => '⏳ รอตรวจสอบ',
      'paid'           => '✅ อนุมัติแล้ว',
      'failed'         => '✗ ปฏิเสธ',
      'all'            => '📋 ทั้งหมด',
    ];
    foreach ($tabs as $val => $label):
    ?>
      <a href="?filter=<?= $val ?><?= $search?'&search='.urlencode($search):'' ?>"
         class="sv-tab <?= $filter===$val?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Search -->
  <form method="GET">
    <input type="hidden" name="filter" value="<?= h($filter) ?>">
    <div class="sv-search">
      <div class="sv-sw">
        <input type="text" name="search" placeholder="ค้นหาชื่อ, เบอร์โทร, อีเมล..."
               value="<?= h($search) ?>">
      </div>
      <button type="submit" class="sv-btn sv-btn-primary">ค้นหา</button>
      <?php if ($search): ?>
        <a href="?filter=<?= h($filter) ?>" class="sv-btn sv-btn-ghost">ล้าง</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($total > 0): ?>
  <div class="sv-grid">
    <?php while ($row = $result->fetch_assoc()):
      $slipPath = $row['payment_slip'] ?? '';
      $slipUrl  = $slipPath ? h($slipPath) : '';
      $pStatus  = $row['payment_status'] ?? 'unpaid';
      // badge
      $badgeClass = match($pStatus) {
        'waiting_verify' => 'sv-badge-wait',
        'paid'           => 'sv-badge-paid',
        'failed'         => 'sv-badge-fail',
        'manual_review'  => 'sv-badge-manual',
        default          => 'sv-badge-wait',
      };
      $badgeLabel = match($pStatus) {
        'waiting_verify' => '⏳ รอตรวจสอบ',
        'paid'           => '✅ ชำระแล้ว',
        'failed'         => '✗ ปฏิเสธ',
        'manual_review'  => '🔍 ตรวจมือ',
        default          => $pStatus,
      };
      $nights = 1;
      if (!empty($row['checkin_date']) && !empty($row['checkout_date'])) {
          $d1 = new DateTime($row['checkin_date']);
          $d2 = new DateTime($row['checkout_date']);
          $nights = max(1, (int)$d1->diff($d2)->days);
      }
    ?>
    <div class="sv-booking-card">
      <div class="sv-bc-head">
        <div>
          <div class="sv-bc-name"><?= h($row['full_name']) ?></div>
          <div class="sv-bc-meta">#<?= (int)$row['id'] ?> · <?= h(substr($row['created_at']??'',0,16)) ?></div>
        </div>
        <span class="sv-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
      </div>
      <div class="sv-bc-body">
        <div class="sv-bc-info">
          <div class="sv-bc-row"><span>📞 เบอร์โทร</span><br><span><?= h($row['phone']??'—') ?></span></div>
          <div class="sv-bc-row"><span>✉️ อีเมล</span><br><span style="font-size:.7rem;"><?= h($row['email']??'—') ?></span></div>
          <div class="sv-bc-row"><span>🏨 ห้องพัก</span><br><span><?= h($row['room_type']??'—') ?></span></div>
          <div class="sv-bc-row"><span>👥 จำนวน</span><br><span><?= (int)($row['guests']??1) ?> คน</span></div>
          <div class="sv-bc-row"><span>📅 วันเข้า</span><br><span><?= h($row['checkin_date']??'—') ?></span></div>
          <div class="sv-bc-row"><span>📅 วันออก</span><br><span><?= h($row['checkout_date']??'—') ?> (<?= $nights ?> คืน)</span></div>
          <?php if (!empty($row['total_price'])): ?>
          <div class="sv-bc-row" style="grid-column:span 2;"><span>💰 ยอดชำระ</span><br>
            <span style="color:#16a34a;font-size:1rem;">฿<?= number_format((float)$row['total_price'],2) ?></span></div>
          <?php endif; ?>
        </div>

        <!-- Slip image -->
        <?php if ($slipUrl): ?>
          <div class="sv-slip-wrap" onclick="openLightbox('<?= $slipUrl ?>')">
            <img src="<?= $slipUrl ?>" alt="สลิปการชำระเงิน" loading="lazy">
          </div>
          <p style="font-size:.72rem;color:var(--muted);text-align:center;margin:-8px 0 12px;">
            คลิกรูปเพื่อขยาย
          </p>
        <?php else: ?>
          <div class="sv-slip-none">📎 ยังไม่มีสลิปที่อัปโหลด</div>
        <?php endif; ?>

        <!-- Actions -->
        <?php if ($pStatus === 'waiting_verify'): ?>
        <div class="sv-bc-actions">
          <form method="POST" class="sv-inline" onsubmit="return confirm('ยืนยันอนุมัติสลิปนี้?')">
            <input type="hidden" name="action" value="approve_slip">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="sv-btn sv-btn-approve">✓ อนุมัติ</button>
          </form>
          <form method="POST" class="sv-inline" onsubmit="return confirm('ยืนยันปฏิเสธสลิปนี้?')">
            <input type="hidden" name="action" value="reject_slip">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="reason" value="สลิปไม่ถูกต้องหรือข้อมูลไม่ตรง">
            <button class="sv-btn sv-btn-reject">✗ ปฏิเสธ</button>
          </form>
        </div>
        <?php elseif ($pStatus === 'paid'): ?>
          <div style="text-align:center;font-size:.8rem;color:var(--success);font-weight:700;padding:8px 0;">
            ✅ อนุมัติแล้ว<?= !empty($row['paid_at']) ? ' · '.h(substr($row['paid_at'],0,16)) : '' ?>
          </div>
        <?php elseif ($pStatus === 'failed'): ?>
          <div style="text-align:center;font-size:.8rem;color:var(--danger);font-weight:700;padding:8px 0;">
            ✗ ถูกปฏิเสธ — ลูกค้าสามารถส่งสลิปใหม่ได้
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
  <?php else: ?>
  <div class="sv-empty">
    <div class="sv-empty-ico">🧾</div>
    <div><?= $search ? 'ไม่พบรายการที่ตรงกับ "'.h($search).'"' : 'ไม่มีสลิปในหมวดนี้' ?></div>
  </div>
  <?php endif; ?>
</div>

<script>
function openLightbox(src) {
  document.getElementById('sv-lightbox-img').src = src;
  document.getElementById('sv-lightbox').classList.add('open');
}
function closeLightbox() {
  document.getElementById('sv-lightbox').classList.remove('open');
  document.getElementById('sv-lightbox-img').src = '';
}
document.getElementById('sv-lightbox').addEventListener('click', function(e) {
  if (e.target === this) closeLightbox();
});
</script>

<?php $stmt->close(); $conn->close(); include 'admin_layout_bottom.php'; ?>
