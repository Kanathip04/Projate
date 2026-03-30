<?php
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

/* === สร้างตาราง === */
$conn->query("CREATE TABLE IF NOT EXISTS `tent_equipment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `price` DECIMAL(10,2) DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT 'ชิ้น',
    `note` TEXT,
    `sort_order` INT DEFAULT 0,
    `is_available` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `equipment_bookings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(200) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `email` VARCHAR(200) DEFAULT '',
    `checkin_date` DATE DEFAULT NULL,
    `checkout_date` DATE DEFAULT NULL,
    `items_json` TEXT,
    `total_price` DECIMAL(10,2) DEFAULT 0,
    `note` TEXT,
    `booking_status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = ''; $message_type = 'success';

/* === บันทึกการจอง === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $checkin      = trim($_POST['checkin_date'] ?? '');
    $checkout     = trim($_POST['checkout_date'] ?? '');
    $items_json   = trim($_POST['items_json'] ?? '');
    $total_price  = (float)($_POST['total_price'] ?? 0);
    $note         = trim($_POST['note'] ?? '');

    if ($full_name && $phone && $checkin && $checkout && $items_json) {
        $st = $conn->prepare("INSERT INTO equipment_bookings (full_name,phone,email,checkin_date,checkout_date,items_json,total_price,note) VALUES (?,?,?,?,?,?,?,?)");
        $st->bind_param("ssssssds", $full_name, $phone, $email, $checkin, $checkout, $items_json, $total_price, $note);
        $st->execute();
        $newId = $conn->insert_id;
        $st->close();
        header("Location: equipment_bill.php?id=" . $newId); exit;
    } else {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $message_type = 'error';
    }
}

/* === ดึงข้อมูล user จาก session + DB === */
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = '';
if (!empty($_SESSION['user_id'])) {
    $uid   = (int)$_SESSION['user_id'];
    $uStmt = $conn->prepare("SELECT fullname, email, phone FROM users WHERE id=? LIMIT 1");
    $uStmt->bind_param("i", $uid); $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc(); $uStmt->close();
    if ($uRow) {
        if (!empty($uRow['fullname'])) $user_name  = $uRow['fullname'];
        if (!empty($uRow['email']))    $user_email = $uRow['email'];
        if (!empty($uRow['phone']))    $user_phone = $uRow['phone'];
    }
}

/* === ดึงอุปกรณ์ === */
$equipResult = $conn->query("SELECT * FROM tent_equipment WHERE is_available=1 ORDER BY sort_order, id");
$equipList = [];
while ($eq = $equipResult->fetch_assoc()) $equipList[] = $eq;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เช่าอุปกรณ์เต็นท์ | สถาบันวิจัยวลัยรุกขเวช</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#1a1a2e;--gold:#c9a96e;--gold-dark:#a8864d;
  --bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;
  --success:#15803d;--success-bg:#ecfdf3;
  --danger:#d92d20;--danger-bg:#fff1f1;
  --shadow:0 8px 30px rgba(26,26,46,.09);
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);}
a{text-decoration:none;}

/* HERO */
.hero{
  background:linear-gradient(145deg,#0d1f0d 0%,#1a2e1a 40%,#1a1a2e 100%);
  padding:56px 20px 100px;position:relative;overflow:hidden;
}
.hero::after{content:"";position:absolute;inset:0;
  background:linear-gradient(to bottom,transparent 60%,var(--bg) 100%);pointer-events:none;}
.hero-inner{width:min(1200px,94%);margin:0 auto;position:relative;z-index:2;}
.top-nav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.nav-btn{padding:9px 16px;border-radius:999px;font-size:13px;font-weight:600;color:#fff;
  background:rgba(201,169,110,.18);border:1px solid rgba(201,169,110,.4);transition:.2s;}
.nav-btn:hover{background:rgba(201,169,110,.32);color:var(--gold);}
.hero h1{font-size:40px;font-weight:800;color:#fff;margin-bottom:10px;}
.hero h1 span{color:var(--gold);}
.hero p{font-size:16px;color:rgba(255,255,255,.8);line-height:1.7;}

/* LAYOUT */
.page{width:min(1200px,94%);margin:-44px auto 60px;position:relative;z-index:5;}
.layout{display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;}

/* ALERT */
.alert{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:14px;
  font-size:.88rem;font-weight:600;margin-bottom:20px;}
.alert-ok{background:var(--success-bg);border:1.5px solid #86efac;color:var(--success);}
.alert-err{background:var(--danger-bg);border:1.5px solid #fca5a5;color:var(--danger);}

/* EQUIPMENT LIST */
.equip-card-grid{display:flex;flex-direction:column;gap:10px;}
.equip-item{background:var(--card);border-radius:14px;border:1.5px solid var(--border);
  padding:16px 18px;display:flex;align-items:center;gap:14px;
  box-shadow:0 2px 10px rgba(26,26,46,.05);transition:.2s;}
.equip-item.in-cart{border-color:var(--gold);background:#fffdf7;}
.equip-info{flex:1;}
.equip-name{font-size:16px;font-weight:700;color:var(--ink);}
.equip-note-txt{font-size:12px;color:var(--muted);margin-top:2px;}
.equip-price-badge{font-size:17px;font-weight:800;color:var(--gold-dark);white-space:nowrap;}
.equip-unit-txt{font-size:12px;color:var(--muted);font-weight:500;}

/* QTY CONTROL */
.qty-wrap{display:flex;align-items:center;gap:0;border:1.5px solid var(--border);
  border-radius:10px;overflow:hidden;background:#fff;}
.qty-btn{width:34px;height:34px;border:none;background:transparent;font-size:18px;
  cursor:pointer;color:var(--ink);display:flex;align-items:center;justify-content:center;
  transition:.15s;font-family:'Sarabun',sans-serif;}
.qty-btn:hover{background:#f3f0ea;}
.qty-num{width:38px;text-align:center;font-size:15px;font-weight:700;color:var(--ink);
  border:none;outline:none;font-family:'Sarabun',sans-serif;background:transparent;}
.add-cart-btn{padding:8px 16px;border-radius:10px;border:none;cursor:pointer;
  font-family:'Sarabun',sans-serif;font-size:13px;font-weight:700;transition:.2s;
  white-space:nowrap;}
.add-cart-btn.idle{background:var(--ink);color:#fff;}
.add-cart-btn.idle:hover{background:#2d3748;}
.add-cart-btn.added{background:#ecfdf3;color:var(--success);border:1.5px solid #86efac;}

/* CARD HEAD */
.card-head{background:var(--card);border-radius:16px 16px 0 0;border:1.5px solid var(--border);
  border-bottom:none;padding:16px 20px;display:flex;align-items:center;gap:10px;}
.card-head h3{font-size:16px;font-weight:800;color:var(--ink);}
.cart-count{background:#1d4ed8;color:#fff;font-size:11px;font-weight:700;
  padding:2px 9px;border-radius:20px;}

/* CART STICKY */
.cart-sticky{position:sticky;top:20px;}
.cart-box{background:var(--card);border-radius:16px;border:1.5px solid var(--border);
  overflow:hidden;box-shadow:var(--shadow);}
.cart-head{padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;}
.cart-head h3{font-size:15px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px;}
.cart-empty-state{padding:32px 20px;text-align:center;color:var(--muted);font-size:14px;}
.cart-empty-ico{font-size:2rem;opacity:.3;margin-bottom:8px;}
.cart-items{padding:12px 16px;display:flex;flex-direction:column;gap:8px;max-height:260px;overflow-y:auto;}
.cart-row{display:flex;align-items:center;gap:10px;padding:8px 10px;
  background:#fdfcfa;border-radius:10px;border:1px solid var(--border);}
.cart-row-name{flex:1;font-size:13px;font-weight:600;}
.cart-row-qty{font-size:12px;color:var(--muted);}
.cart-row-price{font-size:13px;font-weight:700;color:var(--gold-dark);}
.cart-remove{background:none;border:none;cursor:pointer;font-size:16px;color:#ccc;
  line-height:1;padding:2px;transition:.15s;}
.cart-remove:hover{color:var(--danger);}
.cart-total{padding:12px 20px;border-top:1px solid var(--border);
  display:flex;justify-content:space-between;align-items:center;}
.cart-total-label{font-size:13px;color:var(--muted);}
.cart-total-price{font-size:22px;font-weight:800;color:var(--gold-dark);}

/* FORM */
.form-section{padding:16px 20px;border-top:1px solid var(--border);}
.form-section h4{font-size:13px;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;}
.form-row.full{grid-template-columns:1fr;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group label{font-size:12px;font-weight:700;color:var(--muted);}
.form-group input,.form-group textarea{
  padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:14px;color:var(--ink);
  background:#fff;outline:none;transition:.2s;}
.form-group input:focus,.form-group textarea:focus{border-color:var(--gold);
  box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.form-group textarea{resize:vertical;min-height:64px;}
.submit-btn{width:100%;padding:14px;border:none;border-radius:12px;
  background:var(--ink);color:#fff;font-family:'Sarabun',sans-serif;
  font-size:16px;font-weight:800;cursor:pointer;transition:.2s;margin-top:4px;}
.submit-btn:hover{background:var(--gold);color:var(--ink);}
.submit-btn:disabled{background:#9ca3af;cursor:not-allowed;}

@media(max-width:860px){
  .layout{grid-template-columns:1fr;}
  .cart-sticky{position:static;}
  .hero h1{font-size:30px;}
}
</style>
</head>
<body>

<section class="hero">
  <div class="hero-inner">
    <div class="top-nav">
      <a href="index.php" class="nav-btn">← กลับหน้าหลัก</a>
      <a href="booking_tent_status.php" class="nav-btn">ติดตามสถานะการจอง</a>
      <a href="booking_room.php" class="nav-btn">จองห้องพัก</a>
      <a href="booking_boat.php" class="nav-btn">จองคิวพายเรือ</a>
    </div>
    <h1>เช่า<span>อุปกรณ์เต็นท์</span></h1>
    <p>เลือกรายการอุปกรณ์ที่ต้องการ ใส่ตะกร้า แล้วกรอกข้อมูลเพื่อส่งคำขอเช่า</p>
  </div>
</section>

<div class="page">
  <?php if ($message): ?>
  <div class="alert <?= $message_type==='error'?'alert-err':'alert-ok' ?>">
    <?= $message_type==='error'?'⚠️':'✅' ?> <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <div class="layout">
    <!-- LEFT: รายการอุปกรณ์ -->
    <div>
      <div style="margin-bottom:14px;">
        <div style="font-size:20px;font-weight:800;color:var(--ink);margin-bottom:4px;">🏕️ รายการอุปกรณ์ให้เช่า</div>
        <div style="font-size:14px;color:var(--muted);">เลือกจำนวนและกด "เพิ่มตะกร้า" ได้เลย</div>
      </div>
      <div class="equip-card-grid" id="equipGrid">
        <?php foreach ($equipList as $eq): ?>
        <div class="equip-item" id="item-<?= $eq['id'] ?>" data-id="<?= $eq['id'] ?>" data-name="<?= htmlspecialchars($eq['name'],ENT_QUOTES) ?>" data-price="<?= (float)$eq['price'] ?>" data-unit="<?= htmlspecialchars($eq['unit'],ENT_QUOTES) ?>">
          <div class="equip-info">
            <div class="equip-name"><?= htmlspecialchars($eq['name']) ?></div>
            <?php if (!empty($eq['note'])): ?>
            <div class="equip-note-txt"><?= htmlspecialchars($eq['note']) ?></div>
            <?php endif; ?>
          </div>
          <div style="text-align:right;min-width:72px;">
            <div class="equip-price-badge">฿<?= number_format((float)$eq['price']) ?></div>
            <div class="equip-unit-txt">/ <?= htmlspecialchars($eq['unit']) ?></div>
          </div>
          <div class="qty-wrap">
            <button class="qty-btn" onclick="changeQty(<?= $eq['id'] ?>,-1)">−</button>
            <input type="text" class="qty-num" id="qty-<?= $eq['id'] ?>" value="1" readonly>
            <button class="qty-btn" onclick="changeQty(<?= $eq['id'] ?>,1)">+</button>
          </div>
          <button class="add-cart-btn idle" id="btn-<?= $eq['id'] ?>" onclick="addToCart(<?= $eq['id'] ?>)">+ ตะกร้า</button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($equipList)): ?>
        <div style="padding:40px;text-align:center;color:var(--muted);background:var(--card);border-radius:14px;border:1.5px solid var(--border);">ยังไม่มีรายการอุปกรณ์</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: ตะกร้า + ฟอร์ม -->
    <div class="cart-sticky">
      <div class="cart-box">
        <div class="cart-head">
          <h3>🛒 ตะกร้าของฉัน <span class="cart-count" id="cartCount">0</span></h3>
          <button onclick="clearCart()" style="font-size:12px;color:var(--muted);background:none;border:none;cursor:pointer;font-family:'Sarabun',sans-serif;">ล้างทั้งหมด</button>
        </div>

        <div id="cartEmptyState" class="cart-empty-state">
          <div class="cart-empty-ico">🛒</div>
          <div>ยังไม่มีรายการในตะกร้า</div>
          <div style="font-size:12px;margin-top:4px;">เลือกอุปกรณ์จากรายการทางซ้าย</div>
        </div>

        <div id="cartItems" class="cart-items" style="display:none;"></div>

        <div id="cartTotal" class="cart-total" style="display:none;">
          <span class="cart-total-label">รวมทั้งหมด</span>
          <span class="cart-total-price" id="totalPrice">฿0</span>
        </div>

        <form method="POST" id="bookingForm" onsubmit="return prepareSubmit()">
          <input type="hidden" name="action" value="book">
          <input type="hidden" name="items_json" id="itemsJson">
          <input type="hidden" name="total_price" id="totalPriceInput">

          <div class="form-section">
            <h4>ข้อมูลผู้เช่า</h4>
            <div class="form-row">
              <div class="form-group">
                <label>ชื่อ-นามสกุล *</label>
                <input type="text" name="full_name" placeholder="กรอกชื่อ" required value="<?= htmlspecialchars($user_name) ?>">
              </div>
              <div class="form-group">
                <label>เบอร์โทร *</label>
                <input type="text" name="phone" placeholder="08x-xxx-xxxx" required value="<?= htmlspecialchars($user_phone) ?>">
              </div>
            </div>
            <div class="form-row full">
              <div class="form-group">
                <label>อีเมล</label>
                <input type="email" name="email" placeholder="อีเมล (ไม่บังคับ)" value="<?= htmlspecialchars($user_email) ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>วันเข้าพัก *</label>
                <input type="date" name="checkin_date" required>
              </div>
              <div class="form-group">
                <label>วันออก *</label>
                <input type="date" name="checkout_date" required>
              </div>
            </div>
            <div class="form-row full">
              <div class="form-group">
                <label>หมายเหตุเพิ่มเติม</label>
                <textarea name="note" placeholder="หมายเหตุ..."></textarea>
              </div>
            </div>
            <button type="submit" class="submit-btn" id="submitBtn" disabled>เลือกอุปกรณ์ก่อนจอง</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const cart = {}; // id -> {name, price, unit, qty}

function changeQty(id, delta) {
  const input = document.getElementById('qty-' + id);
  let v = parseInt(input.value) + delta;
  if (v < 1) v = 1;
  if (v > 99) v = 99;
  input.value = v;
  // if already in cart, update live
  if (cart[id]) { cart[id].qty = v; renderCart(); }
}

function addToCart(id) {
  const el = document.getElementById('item-' + id);
  const name = el.dataset.name;
  const price = parseFloat(el.dataset.price);
  const unit = el.dataset.unit;
  const qty = parseInt(document.getElementById('qty-' + id).value);
  cart[id] = { name, price, unit, qty };
  renderCart();
  // visual feedback
  const btn = document.getElementById('btn-' + id);
  btn.className = 'add-cart-btn added';
  btn.textContent = '✓ เพิ่มแล้ว';
  el.classList.add('in-cart');
}

function removeFromCart(id) {
  delete cart[id];
  const btn = document.getElementById('btn-' + id);
  if (btn) { btn.className = 'add-cart-btn idle'; btn.textContent = '+ ตะกร้า'; }
  const el = document.getElementById('item-' + id);
  if (el) el.classList.remove('in-cart');
  renderCart();
}

function clearCart() {
  Object.keys(cart).forEach(id => removeFromCart(id));
}

function renderCart() {
  const ids = Object.keys(cart);
  const countEl = document.getElementById('cartCount');
  const emptyEl = document.getElementById('cartEmptyState');
  const itemsEl = document.getElementById('cartItems');
  const totalEl = document.getElementById('cartTotal');
  const totalPriceEl = document.getElementById('totalPrice');
  const submitBtn = document.getElementById('submitBtn');

  countEl.textContent = ids.length;

  if (ids.length === 0) {
    emptyEl.style.display = '';
    itemsEl.style.display = 'none';
    totalEl.style.display = 'none';
    submitBtn.disabled = true;
    submitBtn.textContent = 'เลือกอุปกรณ์ก่อนจอง';
    return;
  }

  emptyEl.style.display = 'none';
  itemsEl.style.display = '';
  totalEl.style.display = '';
  submitBtn.disabled = false;
  submitBtn.textContent = '📋 ส่งคำขอเช่า';

  let html = '';
  let total = 0;
  ids.forEach(id => {
    const it = cart[id];
    const sub = it.price * it.qty;
    total += sub;
    html += `<div class="cart-row">
      <div class="cart-row-name">${it.name}</div>
      <div class="cart-row-qty">${it.qty} ${it.unit}</div>
      <div class="cart-row-price">฿${sub.toLocaleString()}</div>
      <button class="cart-remove" onclick="removeFromCart(${id})" title="ลบ">✕</button>
    </div>`;
  });
  itemsEl.innerHTML = html;
  totalPriceEl.textContent = '฿' + total.toLocaleString();
}

function prepareSubmit() {
  const ids = Object.keys(cart);
  if (ids.length === 0) { alert('กรุณาเลือกอุปกรณ์อย่างน้อย 1 รายการ'); return false; }
  const items = ids.map(id => ({ id: parseInt(id), name: cart[id].name, price: cart[id].price, unit: cart[id].unit, qty: cart[id].qty }));
  const total = items.reduce((s, it) => s + it.price * it.qty, 0);
  document.getElementById('itemsJson').value = JSON.stringify(items);
  document.getElementById('totalPriceInput').value = total;
  return true;
}

// Set min date to today
const today = new Date().toISOString().split('T')[0];
document.querySelectorAll('input[type="date"]').forEach(el => el.min = today);
document.querySelector('[name="checkin_date"]').addEventListener('change', function(){
  document.querySelector('[name="checkout_date"]').min = this.value;
});
</script>

<?php $conn->close(); ?>
</body>
</html>
