<?php
/**
 * payment_slip.php
 * หน้าอัปโหลดสลิปของลูกค้า + แสดง QR PromptPay
 */
session_start();
require_once 'auth_guard.php';
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error");

$booking_ref = trim($_GET['ref'] ?? '');
if (!$booking_ref) { header("Location: booking_boat.php"); exit; }

// ดึงข้อมูล booking
$stmt = $conn->prepare("SELECT * FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
$stmt->bind_param("s", $booking_ref);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) { header("Location: booking_boat.php"); exit; }

// ถ้าจ่ายแล้ว → ไปหน้าบัตรคิว
if (in_array($booking['payment_status'], ['paid'])) {
    header("Location: queue_ticket.php?ref=" . urlencode($booking_ref));
    exit;
}

// ถ้าฟรี → อนุมัติทันที ไม่ต้องจ่าย
if ((float)$booking['total_amount'] <= 0) {
    $today = date('Y-m-d');
    $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM boat_bookings WHERE DATE(created_at) = '$today' AND booking_status = 'approved'");
    $qno = (int)($cntRes->fetch_assoc()['cnt'] ?? 0) + 1;
    $conn->query("UPDATE boat_bookings SET booking_status='approved', payment_status='paid', daily_queue_no=$qno, approved_at=NOW() WHERE id=" . (int)$booking['id']);
    header("Location: queue_ticket.php?ref=" . urlencode($booking_ref));
    exit;
}

/* ── Handle อัปโหลดสลิป ── */
$uploadError = '';
$uploadSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slip'])) {
    $file = $_FILES['slip'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed)) {
        $uploadError = 'รองรับเฉพาะ jpg, png, webp เท่านั้น';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $uploadError = 'ไฟล์ขนาดใหญ่เกินไป (สูงสุด 5MB)';
    } else {
        $dir = 'uploads/slips/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'slip_' . $booking['id'] . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
            // อัปเดต DB
            $slipPath = $dir . $fname;
            $updStmt = $conn->prepare("UPDATE boat_bookings SET payment_slip=?, payment_status='waiting_verify' WHERE id=?");
            $updStmt->bind_param("si", $slipPath, $booking['id']);
            $updStmt->execute();
            $updStmt->close();

            // ยิง webhook ไป n8n
            $webhookUrl = getenv('N8N_WEBHOOK_URL') ?: 'http://localhost:5678/webhook/boat-slip';
            $payload = json_encode([
                'booking_ref'   => $booking_ref,
                'booking_id'    => $booking['id'],
                'customer_name' => $booking['full_name'],
                'total_amount'  => $booking['total_amount'],
                'slip_path'     => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/' . $slipPath,
                'slip_url'      => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/' . $slipPath,
                'callback_url'  => 'http://' . $_SERVER['HTTP_HOST'] . '/Projate/payment_callback.php',
            ]);
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);

            $uploadSuccess = true;
        } else {
            $uploadError = 'อัปโหลดไม่สำเร็จ กรุณาลองใหม่';
        }
    }
    // Reload booking
    $stmt2 = $conn->prepare("SELECT * FROM boat_bookings WHERE booking_ref = ? LIMIT 1");
    $stmt2->bind_param("s", $booking_ref);
    $stmt2->execute();
    $booking = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
}

// ─── PromptPay config ───────────────────────────────────────────
// ใส่เบอร์ 10 หลัก (0XXXXXXXXX) หรือเลขนิติบุคคล 13 หลัก
define('PROMPTPAY_ID', '0622301236');

/**
 * สร้าง PromptPay EMV payload ตามมาตรฐาน BOT/EMVCo
 * รองรับทุก mobile banking ของไทย
 */
function promptpayPayload(string $target, float $amount): string
{
    // Normalize phone: 0XXXXXXXXX → 66XXXXXXXXX
    $target = preg_replace('/\D/', '', $target);
    if (strlen($target) === 10 && $target[0] === '0') {
        $target = '66' . substr($target, 1); // 11 digits
    }
    // National ID = 13 digits (no transform needed)

    $isPhone   = strlen($target) === 11;
    $subTag    = $isPhone ? '01' : '02'; // 01=mobile, 02=national-id
    $subLen    = str_pad(strlen($target), 2, '0', STR_PAD_LEFT);

    $guid      = 'A000000677010111';
    $guidTLV   = '00' . str_pad(strlen($guid), 2, '0', STR_PAD_LEFT) . $guid;  // 0016A000000677010111
    $phoneTLV  = $subTag . $subLen . $target;
    $merchant  = $guidTLV . $phoneTLV;
    $tag29     = '29' . str_pad(strlen($merchant), 2, '0', STR_PAD_LEFT) . $merchant;

    $amountStr = number_format($amount, 2, '.', '');
    $amtTLV    = '54' . str_pad(strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;

    $body = '000201'   // Payload format indicator
          . '010212'   // Point of initiation: 12 = multiple-use
          . $tag29     // Merchant account info (PromptPay)
          . '5303764'  // Currency: THB = 764
          . $amtTLV    // Amount
          . '5802TH'   // Country code
          . '6304';    // CRC placeholder (value appended below)

    // CRC-16/CCITT-FALSE
    $crc  = 0xFFFF;
    for ($i = 0; $i < strlen($body); $i++) {
        $crc ^= ord($body[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return $body . strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

$ppPayload = promptpayPayload(PROMPTPAY_ID, (float)$booking['total_amount']);
// ใช้ api.qrserver.com render payload เป็นรูป (ไม่ผ่านบุคคลที่สาม — payload สร้างเองทั้งหมด)
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=12&data=' . urlencode($ppPayload);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ชำระเงิน — จองคิวพายเรือ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&family=Kanit:wght@700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--ink:#1a1a2e;--blue:#1d6fad;--gold:#c9a96e;--bg:#f0f7ff;--card:#fff;
  --border:#dce8f5;--muted:#7a7a8c;--success:#15803d;--success-bg:#ecfdf3;
  --warning:#d97706;--warning-bg:#fffbeb;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;padding:32px 16px;}
.wrap{max-width:540px;margin:0 auto;}
.back{display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;color:var(--muted);font-size:.85rem;font-weight:600;text-decoration:none;}
.back:hover{color:var(--ink);}
.card{background:#fff;border-radius:20px;box-shadow:0 12px 40px rgba(29,111,173,.1);overflow:hidden;margin-bottom:20px;}
.card-head{background:linear-gradient(135deg,#0a1628,#1a3a5c);padding:22px 28px;color:#fff;}
.card-head h2{font-family:'Kanit',sans-serif;font-size:1.2rem;font-weight:700;}
.card-head p{font-size:.82rem;opacity:.7;margin-top:4px;}
.card-body{padding:24px 28px;}

/* booking summary */
.summary-row{display:flex;justify-content:space-between;align-items:center;
  padding:10px 0;border-bottom:1px solid var(--border);font-size:.88rem;}
.summary-row:last-child{border-bottom:none;}
.summary-row .lbl{color:var(--muted);font-weight:600;}
.summary-row .val{font-weight:700;color:var(--ink);}
.amount-row .val{font-size:1.4rem;font-weight:800;color:var(--blue);}

/* QR */
.qr-section{text-align:center;padding:24px 0;}
.qr-section img{width:200px;height:200px;border-radius:12px;border:2px solid var(--border);}
.qr-label{font-size:.75rem;color:var(--muted);margin-top:8px;line-height:1.5;}
.promptpay-id{font-size:1rem;font-weight:700;color:var(--ink);margin-top:6px;}

/* status pills */
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;
  border-radius:999px;font-size:.8rem;font-weight:700;}
.status-unpaid{background:var(--warning-bg);color:var(--warning);border:1px solid #fde68a;}
.status-waiting{background:#eff6ff;color:var(--blue);border:1px solid #bfdbfe;}
.status-paid{background:var(--success-bg);color:var(--success);border:1px solid #d1fadf;}
.status-dot{width:7px;height:7px;border-radius:50%;background:currentColor;animation:blink 1.4s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}

/* Upload */
.upload-zone{border:2px dashed var(--border);border-radius:14px;padding:28px;text-align:center;
  cursor:pointer;transition:.2s;background:#fafcff;}
.upload-zone:hover,.upload-zone.drag{border-color:var(--blue);background:var(--bg);}
.upload-zone input{display:none;}
.upload-zone .uz-icon{font-size:2.5rem;margin-bottom:8px;}
.upload-zone .uz-label{font-size:.9rem;font-weight:600;color:var(--ink);margin-bottom:4px;}
.upload-zone .uz-hint{font-size:.75rem;color:var(--muted);}
#previewImg{max-width:100%;border-radius:10px;margin-top:14px;display:none;border:1px solid var(--border);}
.error-box{background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;
  padding:10px 14px;color:#dc2626;font-size:.85rem;font-weight:600;margin-bottom:14px;}
.success-box{background:var(--success-bg);border:1px solid #d1fadf;border-radius:10px;
  padding:14px 16px;color:var(--success);font-size:.9rem;font-weight:600;text-align:center;}
.upload-btn{width:100%;padding:14px;border:none;border-radius:12px;
  background:linear-gradient(135deg,var(--ink),#1a3a5c);
  color:#fff;font-family:'Kanit',sans-serif;font-size:1rem;font-weight:700;
  cursor:pointer;margin-top:14px;transition:.2s;}
.upload-btn:hover{background:linear-gradient(135deg,var(--blue),#1a5a9c);}
.poll-note{text-align:center;font-size:.78rem;color:var(--muted);margin-top:12px;line-height:1.6;}

/* Steps */
.steps{display:flex;gap:0;margin-bottom:28px;}
.step{flex:1;text-align:center;position:relative;}
.step:not(:last-child)::after{content:'';position:absolute;top:14px;left:50%;width:100%;height:2px;background:var(--border);z-index:0;}
.step.done:not(:last-child)::after{background:var(--success);}
.step-circle{width:28px;height:28px;border-radius:50%;margin:0 auto 6px;
  display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;
  position:relative;z-index:1;}
.step.done .step-circle{background:var(--success);color:#fff;}
.step.active .step-circle{background:var(--blue);color:#fff;}
.step.wait .step-circle{background:var(--border);color:var(--muted);}
.step-text{font-size:.68rem;color:var(--muted);font-weight:600;}
.step.active .step-text,.step.done .step-text{color:var(--ink);}
</style>
</head>
<body>
<div class="wrap">
  <a href="booking_boat.php" class="back">← กลับหน้าจอง</a>

  <!-- Steps -->
  <div class="steps">
    <div class="step done">
      <div class="step-circle">✓</div>
      <div class="step-text">กรอกข้อมูล</div>
    </div>
    <div class="step <?= in_array($booking['payment_status'], ['waiting_verify','paid']) ? 'done' : 'active' ?>">
      <div class="step-circle">2</div>
      <div class="step-text">ชำระเงิน</div>
    </div>
    <div class="step <?= $booking['payment_status'] === 'paid' ? 'done' : 'wait' ?>">
      <div class="step-circle">3</div>
      <div class="step-text">รับบัตรคิว</div>
    </div>
  </div>

  <!-- Booking Summary -->
  <div class="card">
    <div class="card-head">
      <h2>🚣 สรุปการจอง</h2>
      <p><?= htmlspecialchars($booking['booking_ref']) ?></p>
    </div>
    <div class="card-body">
      <div class="summary-row"><span class="lbl">ชื่อผู้จอง</span><span class="val"><?= htmlspecialchars($booking['full_name']) ?></span></div>
      <div class="summary-row"><span class="lbl">คิว / ประเภทเรือ</span><span class="val"><?= htmlspecialchars($booking['queue_name']) ?> · <?= htmlspecialchars($booking['boat_type']) ?></span></div>
      <div class="summary-row"><span class="lbl">วันที่</span><span class="val"><?= date('d/m/Y', strtotime($booking['boat_date'])) ?></span></div>
      <div class="summary-row"><span class="lbl">จำนวนคน</span><span class="val"><?= (int)$booking['guests'] ?> คน</span></div>
      <div class="summary-row amount-row">
        <span class="lbl">ยอดที่ต้องชำระ</span>
        <span class="val">฿<?= number_format((float)$booking['total_amount'], 2) ?></span>
      </div>
      <div class="summary-row">
        <span class="lbl">สถานะการชำระ</span>
        <span>
          <?php if ($booking['payment_status'] === 'paid'): ?>
            <span class="status-pill status-paid">✓ ชำระแล้ว</span>
          <?php elseif ($booking['payment_status'] === 'waiting_verify'): ?>
            <span class="status-pill status-waiting"><span class="status-dot"></span> รอตรวจสอบสลิป</span>
          <?php else: ?>
            <span class="status-pill status-unpaid">⏳ ยังไม่ชำระ</span>
          <?php endif; ?>
        </span>
      </div>
    </div>
  </div>

  <?php if ($booking['payment_status'] === 'paid'): ?>
    <!-- จ่ายแล้ว → ไปบัตรคิว -->
    <div class="card">
      <div class="card-body" style="text-align:center;padding:32px;">
        <div style="font-size:3rem;margin-bottom:12px;">✅</div>
        <div style="font-size:1.1rem;font-weight:800;margin-bottom:8px;">ชำระเงินเรียบร้อยแล้ว!</div>
        <a href="queue_ticket.php?ref=<?= urlencode($booking_ref) ?>"
           style="display:inline-block;margin-top:14px;padding:13px 32px;background:#1a1a2e;
           color:#fff;border-radius:12px;font-weight:700;font-size:.95rem;text-decoration:none;">
          🎫 ดูบัตรคิวของคุณ
        </a>
      </div>
    </div>

  <?php elseif ($booking['payment_status'] === 'waiting_verify'): ?>
    <!-- รอตรวจสอบ -->
    <div class="card">
      <div class="card-body">
        <div class="success-box">
          <div style="font-size:1.8rem;margin-bottom:8px;">📤</div>
          ได้รับสลิปของคุณแล้ว กำลังตรวจสอบ...<br>
          <span style="font-size:.8rem;font-weight:400;">ระบบจะยืนยันอัตโนมัติใน 1-3 นาที</span>
        </div>
        <div class="poll-note">
          หน้านี้จะอัปเดตอัตโนมัติ · หรือกด Refresh เพื่อตรวจสอบสถานะ
        </div>
        <script>
          // Auto-refresh ทุก 5 วินาที
          setTimeout(() => location.reload(), 5000);
        </script>
      </div>
    </div>

  <?php else: ?>
    <!-- QR Code PromptPay -->
    <div class="card">
      <div class="card-head">
        <h2>📲 ชำระผ่าน PromptPay</h2>
        <p>สแกน QR แล้วอัปโหลดสลิปด้านล่าง</p>
      </div>
      <div class="card-body">
        <div class="qr-section">
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR PromptPay"
               onerror="this.src='';this.alt='ไม่สามารถโหลด QR ได้ กรุณาโอนตรงที่ '+document.getElementById('pid').textContent">
          <div class="qr-label">สแกนด้วยแอปธนาคารหรือ Mobile Banking</div>
          <div class="promptpay-id" id="pid">พร้อมเพย์: <?= PROMPTPAY_ID ?></div>
          <div style="font-size:1.3rem;font-weight:800;color:var(--blue);margin-top:8px;">
            ฿<?= number_format((float)$booking['total_amount'], 2) ?>
          </div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">
            อ้างอิง: <?= htmlspecialchars($booking['booking_ref']) ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Slip -->
    <div class="card">
      <div class="card-head">
        <h2>📎 แนบสลิปการโอนเงิน</h2>
        <p>อัปโหลดสลิปเพื่อยืนยันการชำระ</p>
      </div>
      <div class="card-body">
        <?php if ($uploadError): ?>
          <div class="error-box">⚠ <?= htmlspecialchars($uploadError) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="slipForm">
          <div class="upload-zone" id="uploadZone" onclick="document.getElementById('slipInput').click()">
            <input type="file" id="slipInput" name="slip" accept="image/*" required>
            <div class="uz-icon">📷</div>
            <div class="uz-label">แตะเพื่อเลือกรูปสลิป</div>
            <div class="uz-hint">JPG, PNG, WEBP · สูงสุด 5MB</div>
            <img id="previewImg" src="" alt="preview">
          </div>
          <button type="submit" class="upload-btn" id="uploadBtn">
            📤 ส่งสลิปเพื่อยืนยันการชำระ
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
const input = document.getElementById('slipInput');
const preview = document.getElementById('previewImg');
const zone = document.getElementById('uploadZone');
if (input) {
  input.addEventListener('change', function() {
    if (this.files[0]) {
      const reader = new FileReader();
      reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(this.files[0]);
    }
  });
}
// Drag & drop
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag');
    input.files = e.dataTransfer.files;
    input.dispatchEvent(new Event('change'));
  });
}
// Submit loading state
const form = document.getElementById('slipForm');
if (form) {
  form.addEventListener('submit', () => {
    const btn = document.getElementById('uploadBtn');
    btn.textContent = '⏳ กำลังส่งสลิป...';
    btn.disabled = true;
  });
}
</script>
</body>
</html>
<?php $conn->close(); ?>
