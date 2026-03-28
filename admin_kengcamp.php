<?php
$pageTitle  = "จัดการเก็งแคมป์";
$activeMenu = "kengcamp";

include 'config.php';
require_once 'admin_layout_top.php';

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS kengcamp_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entry_fee DECIMAL(10,2) DEFAULT 50.00,
    children_free_age INT DEFAULT 9,
    rooftop_fee DECIMAL(10,2) DEFAULT 300.00,
    checkin_time VARCHAR(30) DEFAULT '14.00 น.',
    checkout_time VARCHAR(30) DEFAULT '12.00 น.',
    early_checkin_note TEXT,
    equipment_json TEXT,
    activities_json TEXT,
    rules_json TEXT,
    contacts_json TEXT,
    qr_image VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$msg = '';
$msg_type = 'success';

// ── Handle POST save ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entry_fee          = (float)($_POST['entry_fee'] ?? 50);
    $children_free_age  = (int)($_POST['children_free_age'] ?? 9);
    $rooftop_fee        = (float)($_POST['rooftop_fee'] ?? 300);
    $checkin_time       = trim($_POST['checkin_time'] ?? '14.00 น.');
    $checkout_time      = trim($_POST['checkout_time'] ?? '12.00 น.');
    $early_checkin_note = trim($_POST['early_checkin_note'] ?? '');

    // Build JSON fields
    $equipment = [];
    $eq_names  = $_POST['eq_name']  ?? [];
    $eq_prices = $_POST['eq_price'] ?? [];
    $eq_units  = $_POST['eq_unit']  ?? [];
    foreach ($eq_names as $i => $name) {
        if (trim($name) === '') continue;
        $equipment[] = [
            'name'  => trim($name),
            'price' => trim($eq_prices[$i] ?? '0'),
            'unit'  => trim($eq_units[$i]  ?? ''),
        ];
    }

    $activities = [];
    $act_names  = $_POST['act_name']  ?? [];
    $act_prices = $_POST['act_price'] ?? [];
    $act_units  = $_POST['act_unit']  ?? [];
    foreach ($act_names as $i => $name) {
        if (trim($name) === '') continue;
        $activities[] = [
            'name'  => trim($name),
            'price' => trim($act_prices[$i] ?? '0'),
            'unit'  => trim($act_units[$i]  ?? ''),
        ];
    }

    $rules = [];
    foreach ($_POST['rules'] ?? [] as $r) {
        if (trim($r) !== '') $rules[] = trim($r);
    }

    $contacts = [];
    $c_names  = $_POST['c_name']  ?? [];
    $c_phones = $_POST['c_phone'] ?? [];
    foreach ($c_names as $i => $name) {
        if (trim($name) === '') continue;
        $contacts[] = [
            'name'  => trim($name),
            'phone' => trim($c_phones[$i] ?? ''),
        ];
    }

    $equipment_json  = json_encode($equipment,  JSON_UNESCAPED_UNICODE);
    $activities_json = json_encode($activities, JSON_UNESCAPED_UNICODE);
    $rules_json      = json_encode($rules,      JSON_UNESCAPED_UNICODE);
    $contacts_json   = json_encode($contacts,   JSON_UNESCAPED_UNICODE);

    // Handle QR image upload
    $existing = $conn->query("SELECT qr_image FROM kengcamp_info ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $qr_image = $existing['qr_image'] ?? '';

    if (!empty($_FILES['qr_image']['name'])) {
        $ext   = strtolower(pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allow) && $_FILES['qr_image']['error'] === 0) {
            $new_name = 'uploads/qr_kengcamp_' . time() . '.' . $ext;
            if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
            if (move_uploaded_file($_FILES['qr_image']['tmp_name'], __DIR__ . '/' . $new_name)) {
                // Delete old file
                if ($qr_image && file_exists(__DIR__ . '/' . $qr_image)) {
                    @unlink(__DIR__ . '/' . $qr_image);
                }
                $qr_image = $new_name;
            }
        }
    }

    // Check if row exists
    $cnt = $conn->query("SELECT COUNT(*) as c FROM kengcamp_info")->fetch_assoc()['c'];
    if ($cnt > 0) {
        $stmt = $conn->prepare("UPDATE kengcamp_info SET
            entry_fee=?, children_free_age=?, rooftop_fee=?,
            checkin_time=?, checkout_time=?, early_checkin_note=?,
            equipment_json=?, activities_json=?, rules_json=?, contacts_json=?,
            qr_image=?
            ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("diissssssss",
            $entry_fee, $children_free_age, $rooftop_fee,
            $checkin_time, $checkout_time, $early_checkin_note,
            $equipment_json, $activities_json, $rules_json, $contacts_json,
            $qr_image);
    } else {
        $stmt = $conn->prepare("INSERT INTO kengcamp_info
            (entry_fee, children_free_age, rooftop_fee, checkin_time, checkout_time,
             early_checkin_note, equipment_json, activities_json, rules_json, contacts_json, qr_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("diissssssss",
            $entry_fee, $children_free_age, $rooftop_fee,
            $checkin_time, $checkout_time, $early_checkin_note,
            $equipment_json, $activities_json, $rules_json, $contacts_json,
            $qr_image);
    }

    if ($stmt->execute()) {
        $msg = 'บันทึกข้อมูลเรียบร้อยแล้ว';
    } else {
        $msg = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $msg_type = 'danger';
    }
    $stmt->close();
}

// ── Load current data ─────────────────────────────────────────────────────────
$row = $conn->query("SELECT * FROM kengcamp_info ORDER BY id DESC LIMIT 1")->fetch_assoc();
if (!$row) {
    $row = [
        'entry_fee' => 50, 'children_free_age' => 9, 'rooftop_fee' => 300,
        'checkin_time' => '14.00 น.', 'checkout_time' => '12.00 น.',
        'early_checkin_note' => 'หากมาถึงก่อนเวลา แล้วมีพื้นที่ว่างสามารถกางได้เลย',
        'equipment_json' => '[]', 'activities_json' => '[]',
        'rules_json' => '[]', 'contacts_json' => '[]', 'qr_image' => '',
    ];
}

$equipment  = json_decode($row['equipment_json']  ?? '[]', true) ?: [];
$activities = json_decode($row['activities_json'] ?? '[]', true) ?: [];
$rules      = json_decode($row['rules_json']      ?? '[]', true) ?: [];
$contacts   = json_decode($row['contacts_json']   ?? '[]', true) ?: [];

// Defaults if empty
if (empty($equipment)) $equipment = [['name'=>'','price'=>'','unit'=>'']];
if (empty($activities)) $activities = [['name'=>'','price'=>'','unit'=>'']];
if (empty($rules)) $rules = [''];
if (empty($contacts)) $contacts = [['name'=>'','phone'=>'']];
?>

<style>
.kc-section { margin-bottom: 28px; }
.kc-card {
    background: var(--card); border-radius: 14px;
    box-shadow: 0 2px 12px rgba(26,26,46,.06);
    border: 1px solid var(--border); overflow: hidden;
}
.kc-card-header {
    padding: 14px 20px; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
    background: #fdfcfa;
}
.kc-card-title { font-size: 0.85rem; font-weight: 700; color: var(--ink); }
.kc-card-body  { padding: 22px; }
.kc-grid-2     { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.kc-grid-3     { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; }
.form-group    { display: flex; flex-direction: column; gap: 6px; }
.form-group label {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: var(--muted);
}
.form-group input,
.form-group textarea {
    font-family: 'Sarabun', sans-serif; font-size: 0.9rem;
    color: var(--ink); background: #faf9f7;
    border: 1.5px solid var(--border); border-radius: 8px;
    padding: 9px 12px; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.form-group input:focus,
.form-group textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(201,169,110,.14);
    background: #fff;
}
.form-group textarea { min-height: 72px; resize: vertical; }

/* Dynamic rows table */
.dyn-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.dyn-table thead th {
    padding: 8px 10px; font-size: 0.7rem; text-transform: uppercase;
    letter-spacing: .08em; color: var(--muted); font-weight: 700;
    border-bottom: 2px solid var(--border); text-align: left; background: #fdfcfa;
}
.dyn-table tbody td { padding: 6px 6px; vertical-align: middle; border-bottom: 1px solid var(--border); }
.dyn-table tbody tr:last-child td { border-bottom: none; }
.dyn-table input {
    font-family: 'Sarabun', sans-serif; font-size: 0.87rem;
    color: var(--ink); background: #faf9f7;
    border: 1.5px solid var(--border); border-radius: 6px;
    padding: 7px 10px; outline: none; width: 100%;
    transition: border-color .2s, box-shadow .2s;
}
.dyn-table input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(201,169,110,.14); }
.dyn-remove {
    background: none; border: 1.5px solid #fecaca; color: #dc2626;
    border-radius: 6px; padding: 6px 10px; cursor: pointer;
    font-size: 0.78rem; transition: background .2s; white-space: nowrap;
}
.dyn-remove:hover { background: #fef2f2; }
.btn-add-row {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 8px; cursor: pointer;
    background: rgba(201,169,110,.12); border: 1.5px dashed rgba(201,169,110,.5);
    color: var(--accent); font-family: 'Sarabun', sans-serif;
    font-size: 0.82rem; font-weight: 700; transition: background .2s;
}
.btn-add-row:hover { background: rgba(201,169,110,.2); }

.qr-preview { max-width: 140px; border-radius: 10px; margin-bottom: 8px; display: block; }
.qr-note { font-size: 0.75rem; color: var(--muted); }

.save-bar {
    position: sticky; bottom: 0; z-index: 50;
    background: var(--ink); border-top: 2px solid var(--accent);
    padding: 14px 28px; display: flex; justify-content: space-between;
    align-items: center; gap: 16px; margin: 0 -28px -40px;
}
.save-bar-label { font-size: 0.82rem; color: rgba(255,255,255,.6); }
.btn-save {
    background: var(--accent); color: var(--ink);
    border: none; border-radius: 8px;
    padding: 11px 28px; font-family: 'Sarabun', sans-serif;
    font-size: 0.9rem; font-weight: 800; cursor: pointer;
    transition: filter .2s, transform .15s; letter-spacing: .04em;
}
.btn-save:hover { filter: brightness(1.08); transform: translateY(-1px); }

@media (max-width: 700px) {
    .kc-grid-2, .kc-grid-3 { grid-template-columns: 1fr; }
}
</style>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>" style="margin-bottom:20px;">
    <?= htmlspecialchars($msg) ?>
    <?php if ($msg_type === 'success'): ?>
    &nbsp;— <a href="kengcamp.php" target="_blank" style="color:var(--success);font-weight:700;">ดูหน้าสาธารณะ ↗</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="breadcrumb">
    Admin <span>/</span> จัดการเนื้อหา <span>/</span> เก็งแคมป์
</div>

<form method="POST" enctype="multipart/form-data">

<!-- ── Pricing ── -->
<div class="kc-section">
    <div class="kc-card">
        <div class="kc-card-header">
            <div class="kc-card-title">⛺ อัตราค่าบริการ</div>
            <a href="kengcamp.php" target="_blank" class="btn btn-ghost btn-sm">ดูหน้าสาธารณะ ↗</a>
        </div>
        <div class="kc-card-body">
            <div class="kc-grid-3">
                <div class="form-group">
                    <label>ค่าเข้า (บาท/คน/คืน)</label>
                    <input type="number" name="entry_fee" min="0" step="1"
                           value="<?= (float)($row['entry_fee'] ?? 50) ?>">
                </div>
                <div class="form-group">
                    <label>เด็กอายุต่ำกว่า (ขวบ) เข้าฟรี</label>
                    <input type="number" name="children_free_age" min="0" max="18"
                           value="<?= (int)($row['children_free_age'] ?? 9) ?>">
                </div>
                <div class="form-group">
                    <label>เต็นท์หลังคารถ / นอนในรถ (บาท/คัน)</label>
                    <input type="number" name="rooftop_fee" min="0" step="1"
                           value="<?= (float)($row['rooftop_fee'] ?? 300) ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Check-in / out ── -->
<div class="kc-section">
    <div class="kc-card">
        <div class="kc-card-header"><div class="kc-card-title">🕑 เวลาเช็คอิน / เช็คเอาท์</div></div>
        <div class="kc-card-body">
            <div class="kc-grid-2" style="margin-bottom:16px;">
                <div class="form-group">
                    <label>เวลาเช็คอิน</label>
                    <input type="text" name="checkin_time"
                           value="<?= htmlspecialchars($row['checkin_time'] ?? '14.00 น.') ?>">
                </div>
                <div class="form-group">
                    <label>เวลาเช็คเอาท์</label>
                    <input type="text" name="checkout_time"
                           value="<?= htmlspecialchars($row['checkout_time'] ?? '12.00 น.') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>หมายเหตุ (เช่น การมาถึงก่อนเวลา)</label>
                <textarea name="early_checkin_note"><?= htmlspecialchars($row['early_checkin_note'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ── Equipment ── -->
<div class="kc-section">
    <div class="kc-card">
        <div class="kc-card-header"><div class="kc-card-title">🎒 อุปกรณ์ให้เช่า</div></div>
        <div class="kc-card-body">
            <table class="dyn-table" id="eq-table">
                <thead><tr>
                    <th style="width:50%">ชื่ออุปกรณ์</th>
                    <th style="width:20%">ราคา (บาท)</th>
                    <th style="width:18%">หน่วย</th>
                    <th style="width:12%"></th>
                </tr></thead>
                <tbody id="eq-body">
                <?php foreach ($equipment as $eq): ?>
                <tr>
                    <td><input type="text" name="eq_name[]" value="<?= htmlspecialchars($eq['name'] ?? '') ?>" placeholder="ชื่ออุปกรณ์"></td>
                    <td><input type="number" name="eq_price[]" value="<?= htmlspecialchars($eq['price'] ?? '0') ?>" min="0" placeholder="0"></td>
                    <td><input type="text" name="eq_unit[]" value="<?= htmlspecialchars($eq['unit'] ?? '') ?>" placeholder="หลัง/ตัว..."></td>
                    <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn-add-row" onclick="addRow('eq-body','eq')">+ เพิ่มอุปกรณ์</button>
        </div>
    </div>
</div>

<!-- ── Activities ── -->
<div class="kc-section">
    <div class="kc-card">
        <div class="kc-card-header"><div class="kc-card-title">🚣 กิจกรรมในพื้นที่</div></div>
        <div class="kc-card-body">
            <table class="dyn-table" id="act-table">
                <thead><tr>
                    <th style="width:50%">ชื่อกิจกรรม</th>
                    <th style="width:20%">ราคา (บาท)</th>
                    <th style="width:18%">หน่วย (เช่น คน)</th>
                    <th style="width:12%"></th>
                </tr></thead>
                <tbody id="act-body">
                <?php foreach ($activities as $act): ?>
                <tr>
                    <td><input type="text" name="act_name[]" value="<?= htmlspecialchars($act['name'] ?? '') ?>" placeholder="ชื่อกิจกรรม"></td>
                    <td><input type="number" name="act_price[]" value="<?= htmlspecialchars($act['price'] ?? '0') ?>" min="0" placeholder="0 = ฟรี"></td>
                    <td><input type="text" name="act_unit[]" value="<?= htmlspecialchars($act['unit'] ?? '') ?>" placeholder="คน"></td>
                    <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn-add-row" onclick="addRow('act-body','act')">+ เพิ่มกิจกรรม</button>
        </div>
    </div>
</div>

<!-- ── Rules ── -->
<div class="kc-section">
    <div class="kc-card">
        <div class="kc-card-header"><div class="kc-card-title">⚠️ ข้อปฏิบัติ</div></div>
        <div class="kc-card-body">
            <table class="dyn-table" id="rules-table">
                <thead><tr>
                    <th style="width:88%">ข้อปฏิบัติ</th>
                    <th style="width:12%"></th>
                </tr></thead>
                <tbody id="rules-body">
                <?php foreach ($rules as $rule): ?>
                <tr>
                    <td><input type="text" name="rules[]" value="<?= htmlspecialchars($rule) ?>" placeholder="ข้อปฏิบัติ..."></td>
                    <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn-add-row" onclick="addRow('rules-body','rules')">+ เพิ่มข้อปฏิบัติ</button>
        </div>
    </div>
</div>

<!-- ── Contacts + QR ── -->
<div class="kc-section">
    <div class="kc-card">
        <div class="kc-card-header"><div class="kc-card-title">📞 ติดต่อสอบถาม & QR Code</div></div>
        <div class="kc-card-body">
            <div class="kc-grid-2">
                <div>
                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">รายชื่อผู้ติดต่อ</div>
                    <table class="dyn-table" id="c-table">
                        <thead><tr>
                            <th style="width:42%">ชื่อ</th>
                            <th style="width:42%">เบอร์โทร</th>
                            <th style="width:16%"></th>
                        </tr></thead>
                        <tbody id="c-body">
                        <?php foreach ($contacts as $c): ?>
                        <tr>
                            <td><input type="text" name="c_name[]" value="<?= htmlspecialchars($c['name'] ?? '') ?>" placeholder="ชื่อ"></td>
                            <td><input type="text" name="c_phone[]" value="<?= htmlspecialchars($c['phone'] ?? '') ?>" placeholder="0XX-XXX-XXXX"></td>
                            <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn-add-row" onclick="addRow('c-body','c')">+ เพิ่มผู้ติดต่อ</button>
                </div>
                <div>
                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">QR Code ชำระเงิน</div>
                    <?php if (!empty($row['qr_image']) && file_exists(__DIR__ . '/' . $row['qr_image'])): ?>
                    <img src="<?= htmlspecialchars($row['qr_image']) ?>?t=<?= time() ?>"
                         alt="QR Code" class="qr-preview">
                    <div class="qr-note" style="margin-bottom:12px;">ภาพปัจจุบัน — อัพโหลดใหม่เพื่อเปลี่ยน</div>
                    <?php else: ?>
                    <div style="font-size:.82rem;color:var(--muted);margin-bottom:12px;">ยังไม่มี QR Code</div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>อัพโหลด QR Code (PNG/JPG)</label>
                        <input type="file" name="qr_image" accept="image/*"
                               style="background:#faf9f7;padding:8px;border:1.5px dashed var(--border);border-radius:8px;">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Save bar ── -->
<div class="save-bar">
    <div class="save-bar-label">บันทึกข้อมูลทั้งหมดของเก็งแคมป์</div>
    <button type="submit" class="btn-save">💾 บันทึกข้อมูล</button>
</div>

</form>

<script>
function removeRow(btn) {
    const tbody = btn.closest('tbody');
    const rows  = tbody.querySelectorAll('tr');
    if (rows.length > 1) btn.closest('tr').remove();
}

function addRow(bodyId, type) {
    const tbody = document.getElementById(bodyId);
    const tr = document.createElement('tr');
    if (type === 'eq') {
        tr.innerHTML = `
            <td><input type="text"   name="eq_name[]"  placeholder="ชื่ออุปกรณ์"></td>
            <td><input type="number" name="eq_price[]" min="0" value="0" placeholder="0"></td>
            <td><input type="text"   name="eq_unit[]"  placeholder="หน่วย"></td>
            <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>`;
    } else if (type === 'act') {
        tr.innerHTML = `
            <td><input type="text"   name="act_name[]"  placeholder="ชื่อกิจกรรม"></td>
            <td><input type="number" name="act_price[]" min="0" value="0" placeholder="0 = ฟรี"></td>
            <td><input type="text"   name="act_unit[]"  placeholder="คน"></td>
            <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>`;
    } else if (type === 'rules') {
        tr.innerHTML = `
            <td><input type="text" name="rules[]" placeholder="ข้อปฏิบัติ..."></td>
            <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>`;
    } else if (type === 'c') {
        tr.innerHTML = `
            <td><input type="text" name="c_name[]"  placeholder="ชื่อ"></td>
            <td><input type="text" name="c_phone[]" placeholder="0XX-XXX-XXXX"></td>
            <td><button type="button" class="dyn-remove" onclick="removeRow(this)">ลบ</button></td>`;
    }
    tbody.appendChild(tr);
    tr.querySelector('input')?.focus();
}
</script>

<?php require_once 'admin_layout_bottom.php'; ?>
