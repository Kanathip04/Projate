<?php
date_default_timezone_set('Asia/Bangkok');
session_start();

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ให้แน่ใจว่า ENUM มีค่า cash_pending, cash_paid
$conn->query("ALTER TABLE boat_bookings MODIFY COLUMN `payment_status` ENUM('pending','waiting_verify','checking','paid','failed','expired','duplicate','suspicious','manual_review','cash_pending','cash_paid') DEFAULT 'pending'");

$currentPage = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // ── [ชั่วคราว] ลบข้อมูลทั้งหมด ──
    if ($action === 'delete_all_temp') {
        $conn->query("DELETE FROM payment_slips");
        $conn->query("DELETE FROM boat_bookings");
        $conn->query("ALTER TABLE boat_bookings AUTO_INCREMENT = 1");
        header("Location: {$currentPage}?msg=" . urlencode("ลบข้อมูลทั้งหมดเรียบร้อยแล้ว") . "&type=success");
        exit;
    }

    if ($id > 0) {
        if ($action === 'approve_payment') {
            // คำนวณเลขคิววันนี้
            $today  = date('Y-m-d');
            $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(approved_at) = '$today' AND booking_status = 'approved'");
            $qno    = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;

            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status  = 'paid',
                    booking_status  = 'approved',
                    daily_queue_no  = ?,
                    paid_at         = IFNULL(paid_at, NOW()),
                    approved_at     = NOW()
                WHERE id = ?
            ");
            $st->bind_param("ii", $qno, $id);
            $st->execute();
            $st->close();

            header("Location: {$currentPage}?tab=approved&msg=" . urlencode("อนุมัติการชำระเงินเรียบร้อยแล้ว (คิว Q" . str_pad($qno,4,'0',STR_PAD_LEFT) . ")") . "&type=success");
            exit;
        }

        if ($action === 'reject_payment') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status='failed',
                    booking_status='rejected'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "ปฏิเสธการชำระเงินเรียบร้อยแล้ว";
            $message_type = "danger";
        }

        if ($action === 'approve_booking') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET booking_status='approved'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            header("Location: {$currentPage}?tab=approved&msg=" . urlencode("อนุมัติรายการเรียบร้อยแล้ว") . "&type=success");
            exit;
        }

        if ($action === 'reject_booking') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET booking_status='rejected'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "ปฏิเสธรายการเรียบร้อยแล้ว";
            $message_type = "danger";
        }

        if ($action === 'mark_pending_payment') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status='waiting_verify'
                WHERE id=?
            ");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "เปลี่ยนสถานะเป็นรอตรวจสอบแล้ว";
        }

        if ($action === 'accept_cash') {
            // คำนวณเลขคิววันนี้
            $today  = date('Y-m-d');
            $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(approved_at) = '$today' AND booking_status = 'approved'");
            $qno    = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;

            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status  = 'cash_paid',
                    booking_status  = 'approved',
                    daily_queue_no  = ?,
                    paid_at         = NOW(),
                    approved_at     = NOW()
                WHERE id = ?
            ");
            $st->bind_param("ii", $qno, $id);
            $st->execute();
            $st->close();

            header("Location: {$currentPage}?tab=approved&msg=" . urlencode("รับเงินสดแล้ว ออกบัตรคิว Q" . str_pad($qno,4,'0',STR_PAD_LEFT)) . "&type=success");
            exit;
        }

        if ($action === 'reset_slip') {
            $st = $conn->prepare("
                UPDATE boat_bookings
                SET payment_status='pending', payment_slip=NULL,
                    note=CONCAT(COALESCE(note,''), ?)
                WHERE id=?
            ");
            $resetNote = "\n[ADMIN] รีเซ็ตสลิป รอผู้จองอัปโหลดใหม่ [" . date('Y-m-d H:i') . "]";
            $st->bind_param("si", $resetNote, $id);
            $st->execute();
            $st->close();
            // ล้างสลิปใน payment_slips ด้วย
            $conn->query("UPDATE payment_slips SET verification_status='voided' WHERE booking_id=$id ORDER BY id DESC LIMIT 1");
            $message = "รีเซ็ตสลิปแล้ว ผู้จองสามารถอัปโหลดสลิปใหม่ได้";
            $message_type = "success";
        }

        if ($action === 'archive') {
            $st = $conn->prepare("UPDATE boat_bookings SET archived=1 WHERE id=?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "จัดเก็บรายการเรียบร้อยแล้ว";
        }

        if ($action === 'delete') {
            $st = $conn->prepare("DELETE FROM boat_bookings WHERE id=?");
            $st->bind_param("i", $id);
            $st->execute();
            $st->close();

            $message = "ลบรายการเรียบร้อยแล้ว";
        }
    }

    header("Location: {$currentPage}?tab=" . urlencode($_GET['tab'] ?? ($_POST['tab'] ?? 'pending')) . "&msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

$tab    = $_GET['tab'] ?? 'pending';
$search = trim($_GET['search'] ?? '');

// ── ซ่อนรายการที่ยังไม่ชำระ (แสดงเฉพาะที่ออกบัตรคิวแล้ว หรืออยู่ระหว่างตรวจสลิป) ──
// payment_status IN ('unpaid','pending','failed','expired') = ยังไม่ถึงหลังบ้าน
$VISIBLE = "archived=0 AND payment_status NOT IN ('unpaid','pending','failed','expired')";

$rs = $conn->query("
    SELECT
        COUNT(*) t,
        SUM(booking_status='pending') p,
        SUM(booking_status='approved') a,
        SUM(booking_status='rejected') r,
        SUM(payment_status='waiting_verify') pw,
        SUM(payment_status IN ('paid','cash_paid')) pp,
        SUM(payment_status='manual_review') pm,
        SUM(payment_status='cash_pending') pc
    FROM boat_bookings
    WHERE $VISIBLE
");
$st_row = $rs->fetch_assoc();

$stat_total            = (int)($st_row['t'] ?? 0);
$stat_pending          = (int)($st_row['p'] ?? 0);
$stat_approved         = (int)($st_row['a'] ?? 0);
$stat_rejected         = (int)($st_row['r'] ?? 0);
$stat_payment_waiting  = (int)($st_row['pw'] ?? 0);
$stat_payment_paid     = (int)($st_row['pp'] ?? 0);
$stat_payment_manual   = (int)($st_row['pm'] ?? 0);
$stat_payment_cash     = (int)($st_row['pc'] ?? 0);

$where = "WHERE $VISIBLE";

if ($tab === 'approved') {
    $where .= " AND booking_status='approved'";
} elseif ($tab === 'waiting_payment') {
    $where .= " AND payment_status='waiting_verify'";
} elseif ($tab === 'paid') {
    $where .= " AND payment_status IN ('paid','cash_paid')";
} elseif ($tab === 'cash_pending') {
    $where .= " AND payment_status='cash_pending'";
} elseif ($tab === 'manual') {
    $where .= " AND payment_status='manual_review'";
} else {
    // default 'pending' tab = รอตรวจสลิป + รอ admin (ที่มีสลิปแล้ว)
    $where .= " AND payment_status IN ('waiting_verify','manual_review','cash_pending') AND booking_status != 'approved'";
}

$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (
        full_name LIKE ? 
        OR phone LIKE ? 
        OR email LIKE ? 
        OR queue_name LIKE ?
        OR booking_ref LIKE ?
    )";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like, $like];
    $types = "sssss";
}

$sql = "
    SELECT *
    FROM boat_bookings
    {$where}
    ORDER BY id DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = "จัดการการจองคิวพายเรือ";
$activeMenu = "boat_booking";
include 'admin_layout_top.php';
?>
<style>
/* ── badges ── */
.bk-badge-pending,.bk-badge-approved,.bk-badge-rejected,.bk-badge-cancelled,
.pay-unpaid,.pay-waiting,.pay-paid,.pay-failed{
    padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;display:inline-block;white-space:nowrap;
}
.bk-badge-pending{background:#fef3c7;color:#92400e;}
.bk-badge-approved{background:#dcfce7;color:#166534;}
.bk-badge-rejected,.bk-badge-cancelled{background:#fee2e2;color:#991b1b;}
.pay-unpaid{background:#f1f5f9;color:#475569;}
.pay-waiting{background:#fff3e0;color:#9a6700;}
.pay-paid{background:#dcfce7;color:#166534;}
.pay-failed{background:#fee2e2;color:#991b1b;}
.pay-duplicate{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;}
.pay-suspicious{background:#fce7f3;color:#9d174d;border:1px solid #f9a8d4;}
.pay-manual{background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;}
.pay-cash-pending{background:#fff7ed;color:#c2410c;border:1px solid #fdba74;}
.pay-cash-paid{background:#dcfce7;color:#166534;border:1px solid #86efac;}

/* ── stat bar ── */
.stat-bar{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:16px;}
.stat-item{background:#fff;border-radius:10px;padding:14px 16px;
  box-shadow:0 1px 8px rgba(26,26,46,.06);border-left:3px solid #e0ddd7;text-align:center;}
.stat-item.s-warn{border-left-color:#d97706;}
.stat-item.s-ok{border-left-color:#16a34a;}
.stat-item.s-err{border-left-color:#dc2626;}
.stat-item.s-blue{border-left-color:#1d6fad;}
.stat-item .sn{font-size:1.5rem;font-weight:800;color:var(--ink);line-height:1;}
.stat-item .sl{font-size:.65rem;color:var(--muted);font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;margin-top:4px;}

/* ── tab strip ── */
.tab-strip{display:flex;background:#fff;border-radius:10px;padding:4px;gap:4px;
  box-shadow:0 1px 8px rgba(26,26,46,.06);margin-bottom:16px;width:fit-content;}
.ts-btn{padding:7px 18px;border-radius:7px;font-size:.8rem;font-weight:700;
  text-decoration:none;color:var(--muted);transition:.15s;white-space:nowrap;border:none;
  background:transparent;cursor:pointer;}
.ts-btn:hover{color:var(--ink);}
.ts-btn.active{background:var(--ink);color:#fff;}
.ts-badge{display:inline-block;background:rgba(255,255,255,.25);color:inherit;
  border-radius:10px;font-size:.65rem;padding:1px 6px;margin-left:4px;font-weight:800;}
.ts-btn:not(.active) .ts-badge{background:#f0efec;color:var(--muted);}

/* ── page header ── */
.pg-hd{display:flex;align-items:center;justify-content:space-between;
  margin-bottom:16px;flex-wrap:wrap;gap:10px;}
.pg-title{font-size:1.15rem;font-weight:800;color:var(--ink);}
.pg-sub{font-size:.75rem;color:var(--muted);margin-top:2px;}

/* ── table ── */
.bk-td-name{font-weight:700;font-size:.86rem;color:var(--ink);}
.bk-td-sub{font-size:.72rem;color:var(--muted);margin-top:2px;}
.boat-pills{display:flex;flex-wrap:wrap;gap:3px;}
.boat-pill{padding:2px 7px;border-radius:999px;background:rgba(29,111,173,.09);
  border:1px solid rgba(29,111,173,.2);color:#1d6fad;font-size:.68rem;font-weight:700;}
.slip-thumb{width:52px;height:52px;object-fit:cover;border-radius:8px;
  border:1px solid #e0ddd7;display:block;}
.act-group{display:flex;gap:5px;flex-wrap:wrap;align-items:center;}

/* ── search bar ── */
.search-bar{display:flex;gap:8px;align-items:center;}
.search-bar input{height:36px;border:1.5px solid var(--border);border-radius:8px;
  padding:0 12px;font-family:'Sarabun',sans-serif;font-size:.84rem;
  color:var(--ink);background:#fafaf8;outline:none;width:260px;transition:.15s;}
.search-bar input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,169,110,.1);}

@media(max-width:1000px){.stat-bar{grid-template-columns:repeat(3,1fr);}}
@media(max-width:600px){.stat-bar{grid-template-columns:repeat(2,1fr);}}
</style>

<div class="pg-hd">
  <div>
    <div class="pg-title">🚣 การจองคิวพายเรือ</div>
    <div class="pg-sub">จัดการรายการจอง การชำระเงิน และสลิป</div>
  </div>
  <a href="admin_boat_queues.php" class="btn btn-accent" style="height:38px;font-size:.82rem;">+ จัดการคิว</a>
  <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ลบข้อมูลการจองทั้งหมด?\nการกระทำนี้ไม่สามารถย้อนกลับได้!')">
    <input type="hidden" name="action" value="delete_all_temp">
    <button type="submit" class="btn" style="height:38px;font-size:.82rem;background:#dc3545;color:#fff;border:none;">🗑 ลบทั้งหมด (ชั่วคราว)</button>
  </form>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:14px;"><?= h($message) ?></div>
<?php endif; ?>

<!-- Stat bar: 6 ตัวเลข -->
<div class="stat-bar">
  <div class="stat-item"><div class="sn"><?= $stat_total ?></div><div class="sl">ทั้งหมด</div></div>
  <div class="stat-item s-warn"><div class="sn"><?= $stat_payment_waiting ?></div><div class="sl">รอตรวจสลิป</div></div>
  <div class="stat-item s-ok"><div class="sn"><?= $stat_payment_paid ?></div><div class="sl">ชำระแล้ว</div></div>
  <div class="stat-item s-ok"><div class="sn"><?= $stat_approved ?></div><div class="sl">อนุมัติแล้ว</div></div>
  <div class="stat-item s-warn"><div class="sn"><?= $stat_payment_manual ?></div><div class="sl">รอตรวจสอบ</div></div>
  <div class="stat-item s-err"><div class="sn"><?= $stat_rejected ?></div><div class="sl">ปฏิเสธ</div></div>
</div>

<!-- Tab strip -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
  <div class="tab-strip">
    <a href="?tab=pending" class="ts-btn<?= $tab==='pending'?' active':'' ?>">รอดำเนินการ<span class="ts-badge"><?= $stat_payment_waiting + $stat_payment_manual + $stat_payment_cash ?></span></a>
    <a href="?tab=waiting_payment" class="ts-btn<?= $tab==='waiting_payment'?' active':'' ?>">รอตรวจสลิป<span class="ts-badge"><?= $stat_payment_waiting ?></span></a>
    <a href="?tab=manual" class="ts-btn<?= $tab==='manual'?' active':'' ?>">รอตรวจสอบ<span class="ts-badge"><?= $stat_payment_manual ?></span></a>
    <a href="?tab=paid" class="ts-btn<?= $tab==='paid'?' active':'' ?>">ชำระแล้ว</a>
    <a href="?tab=cash_pending" class="ts-btn<?= $tab==='cash_pending'?' active':'' ?>">💵 รอจ่ายสด</a>
    <a href="?tab=approved" class="ts-btn<?= $tab==='approved'?' active':'' ?>">อนุมัติแล้ว</a>
  </div>
  <form method="GET" class="search-bar">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <input type="text" name="search" placeholder="ค้นหาชื่อ / เบอร์ / booking ref..." value="<?= h($search) ?>">
    <button class="btn btn-ghost" type="submit" style="height:36px;font-size:.8rem;">ค้นหา</button>
    <?php if ($search): ?><a href="?tab=<?= h($tab) ?>" class="btn btn-ghost" style="height:36px;font-size:.8rem;">ล้าง</a><?php endif; ?>
  </form>
</div>

<div class="lm-card">
  <div class="lm-card-header">
    <span class="lm-card-title">รายการ (<?= $result->num_rows ?>)</span>
  </div>
  <div style="overflow-x:auto;">
  <table class="lm-table">
    <thead>
      <tr>
        <th>ผู้จอง</th>
        <th>Ref / คิว</th>
        <th>วันที่ / เวลา</th>
        <th>เรือ</th>
        <th>คน</th>
        <th>ยอด</th>
        <th>สลิป</th>
        <th>สถานะ</th>
        <th>จัดการ</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows === 0): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:36px;">ไม่มีรายการที่ตรงกัน</td></tr>
    <?php endif; ?>

    <?php while ($row = $result->fetch_assoc()):
      $units = json_decode($row['boat_units'] ?? '[]', true) ?: [];
      $bCls = ['pending'=>'bk-badge-pending','approved'=>'bk-badge-approved','rejected'=>'bk-badge-rejected','cancelled'=>'bk-badge-cancelled'];
      $bLbl = ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิก'];
      $pCls = ['unpaid'=>'pay-unpaid','pending'=>'pay-waiting','waiting_verify'=>'pay-waiting','paid'=>'pay-paid','failed'=>'pay-failed','expired'=>'pay-failed','duplicate'=>'pay-duplicate','suspicious'=>'pay-suspicious','manual_review'=>'pay-manual','checking'=>'pay-waiting','cash_pending'=>'pay-cash-pending','cash_paid'=>'pay-cash-paid'];
      $pLbl = ['unpaid'=>'ยังไม่ชำระ','pending'=>'ดำเนินการ','waiting_verify'=>'รอตรวจสลิป','paid'=>'ชำระแล้ว','failed'=>'สลิปไม่ผ่าน','expired'=>'หมดอายุ','duplicate'=>'⚠ สลิปซ้ำ','suspicious'=>'⚠ น่าสงสัย','manual_review'=>'รอตรวจด้วยมือ','checking'=>'กำลังตรวจ','cash_pending'=>'💵 รอจ่ายสด','cash_paid'=>'💵 จ่ายสดแล้ว'];
      $bs = $row['booking_status'] ?? 'pending';
      $ps = $row['payment_status'] ?? 'unpaid';
    ?>
    <tr>
      <!-- ผู้จอง -->
      <td>
        <div class="bk-td-name"><?= h($row['full_name']) ?></div>
        <div class="bk-td-sub"><?= h($row['phone']) ?></div>
        <?php if (!empty($row['email'])): ?>
        <div class="bk-td-sub"><?= h($row['email']) ?></div>
        <?php endif; ?>
      </td>

      <!-- Ref / คิว -->
      <td>
        <div style="font-size:.78rem;font-weight:700;font-family:monospace;"><?= h($row['booking_ref'] ?? '-') ?></div>
        <div class="bk-td-sub" style="margin-top:3px;"><?= h($row['queue_name'] ?? '-') ?></div>
        <?php if (!empty($row['daily_queue_no'])): ?>
        <div class="bk-td-sub">Q<?= str_pad((int)$row['daily_queue_no'],4,'0',STR_PAD_LEFT) ?></div>
        <?php endif; ?>
      </td>

      <!-- วันที่ / เวลา -->
      <td>
        <div style="font-size:.83rem;font-weight:600;">
          <?= !empty($row['boat_date']) ? date('d/m/Y', strtotime($row['boat_date'])) : '-' ?>
        </div>
        <div class="bk-td-sub">
          <?= !empty($row['time_start']) ? substr($row['time_start'],0,5).'–'.substr($row['time_end'],0,5) : '-' ?>
        </div>
      </td>

      <!-- เรือ -->
      <td>
        <?php if (!empty($units)): ?>
          <div class="boat-pills">
            <?php foreach ($units as $u): ?>
            <span class="boat-pill">🚣 <?= (int)$u ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <span style="font-size:.82rem;"><?= h($row['boat_type'] ?? '-') ?></span>
        <?php endif; ?>
      </td>

      <!-- คน -->
      <td style="text-align:center;font-weight:700;"><?= (int)($row['guests'] ?? 0) ?></td>

      <!-- ยอด -->
      <td>
        <div style="font-weight:800;font-size:.9rem;">฿<?= number_format((float)($row['total_amount'] ?? 0), 0) ?></div>
        <?php if (!empty($row['price_per_boat'])): ?>
        <div class="bk-td-sub">฿<?= number_format((float)$row['price_per_boat'], 0) ?>/ลำ</div>
        <?php endif; ?>
      </td>

      <!-- สลิป -->
      <td style="text-align:center;">
        <?php if (!empty($row['payment_slip'])): ?>
          <a href="<?= h($row['payment_slip']) ?>" target="_blank">
            <img src="<?= h($row['payment_slip']) ?>" alt="slip" class="slip-thumb">
          </a>
        <?php else: ?>
          <span style="font-size:.7rem;color:var(--muted);">—</span>
        <?php endif; ?>
      </td>

      <!-- สถานะ (รวม 2 บรรทัด) -->
      <td>
        <div><span class="<?= isset($bCls[$bs]) ? $bCls[$bs] : 'bk-badge-pending' ?>"><?= isset($bLbl[$bs]) ? $bLbl[$bs] : $bs ?></span></div>
        <div style="margin-top:5px;">
          <span class="<?= isset($pCls[$ps]) ? $pCls[$ps] : 'pay-unpaid' ?>"
            <?php
              $tips = [
                'duplicate'     => 'สลิปไฟล์นี้เคยถูกใช้กับการจองอื่นแล้ว',
                'suspicious'    => 'AI ตรวจพบว่าสลิปอาจไม่ถูกต้อง',
                'manual_review' => 'AI อ่านสลิปไม่ชัด ต้องตรวจด้วยตัวเอง',
                'failed'        => 'สลิปไม่ผ่านการตรวจ (ยอดไม่ตรง/วันที่เก่า)',
              ];
              if (isset($tips[$ps])) echo 'title="'.$tips[$ps].'"';
            ?>
          ><?= isset($pLbl[$ps]) ? $pLbl[$ps] : $ps ?></span>
        </div>
        <?php if (!empty($row['note'])): ?>
        <div class="bk-td-sub" style="margin-top:3px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
             title="<?= h($row['note']) ?>">
          📋 <?= h(mb_substr(trim(str_replace(["\n","[AUTO]"],' ',$row['note'])),0,40)) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($row['paid_at'])): ?>
        <div class="bk-td-sub" style="margin-top:2px;"><?= date('d/m H:i', strtotime($row['paid_at'])) ?></div>
        <?php endif; ?>
      </td>

      <!-- จัดการ -->
      <td>
        <div class="act-group">
          <?php if ($ps === 'cash_pending'): ?>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="hidden" name="action" value="accept_cash">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-sm" style="background:#15803d;color:#fff;border:none;white-space:nowrap;"
                      type="submit" onclick="return confirm('ยืนยันว่าลูกค้าชำระเงินสดแล้ว?\nระบบจะออกบัตรคิวให้อัตโนมัติ')">
                ✓ รับเงินสด + ออกบัตรคิว
              </button>
            </form>
          <?php endif; ?>

          <?php if ($ps === 'waiting_verify'): ?>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="hidden" name="action" value="approve_payment">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-accent btn-sm" type="submit" onclick="return confirm('ยืนยันการชำระเงิน?')" title="อนุมัติชำระ">✓ อนุมัติชำระ</button>
            </form>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="hidden" name="action" value="reject_payment">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('ปฏิเสธสลิปนี้?')" title="ปฏิเสธ">✗ ปฏิเสธ</button>
            </form>
          <?php endif; ?>

          <?php if ($bs === 'pending' && $ps !== 'paid'): ?>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="hidden" name="action" value="approve_booking">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('อนุมัติการจอง?')">อนุมัติจอง</button>
            </form>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="hidden" name="action" value="reject_booking">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('ปฏิเสธการจอง?')">ปฏิเสธ</button>
            </form>
          <?php endif; ?>

          <?php if (in_array($ps, ['duplicate','suspicious','failed','manual_review'])): ?>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="hidden" name="action" value="reset_slip">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-sm" style="background:#fff3e0;color:#9a6700;border:1px solid #fcd34d;" type="submit"
                      onclick="return confirm('รีเซ็ตสถานะสลิป ให้ผู้จองอัปโหลดสลิปใหม่ได้?')"
                      title="ล้างสลิปเก่า ให้ผู้ใช้อัปโหลดใหม่">🔄 รีเซ็ตสลิป</button>
            </form>
          <?php endif; ?>

          <?php if ($ps === 'unpaid' && !empty($row['payment_slip'])): ?>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <input type="hidden" name="action" value="mark_pending_payment">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('ตั้งเป็นรอตรวจสอบ?')">รอตรวจ</button>
            </form>
          <?php endif; ?>

          <form method="POST" style="display:contents;">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('จัดเก็บรายการนี้?')" title="เก็บ">🗂</button>
          </form>
          <form method="POST" style="display:contents;">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('ลบถาวร?')" title="ลบ">🗑</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
</div>

<?php include 'admin_layout_bottom.php'; ?>
<?php
$stmt->close();
$conn->close();
?>