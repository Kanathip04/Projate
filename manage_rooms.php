<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$pageTitle = "จัดการห้องพัก";
$activeMenu = "rooms";

$editData = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $conn->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    if ($stmtEdit) {
        $stmtEdit->bind_param("i", $editId);
        $stmtEdit->execute();
        $resultEdit = $stmtEdit->get_result();
        if ($resultEdit && $resultEdit->num_rows > 0) {
            $editData = $resultEdit->fetch_assoc();
        }
        $stmtEdit->close();
    }
}

$rooms = $conn->query("SELECT * FROM rooms ORDER BY id DESC");

include 'admin_layout_top.php';
?>

<style>
.page-wrap{padding:24px;}
.room-grid{display:grid;grid-template-columns:1.1fr 1.6fr;gap:24px;}
.card{background:#fff;border-radius:18px;box-shadow:0 8px 24px rgba(0,0,0,.08);padding:20px;}
.card h2{margin:0 0 16px;font-size:22px;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-weight:600;margin-bottom:6px;}
.form-group input,.form-group textarea,.form-group select{
    width:100%;padding:12px 14px;border:1px solid #dcdcdc;border-radius:12px;outline:none;font-size:15px;
}
.form-group textarea{min-height:110px;resize:vertical;}
.btn{display:inline-block;padding:12px 18px;border:none;border-radius:12px;cursor:pointer;font-weight:700;text-decoration:none;}
.btn-save{background:#638411;color:#fff;}
.btn-cancel{background:#e9ecef;color:#222;}
.table-wrap{overflow:auto;}
.room-table{width:100%;border-collapse:collapse;}
.room-table th,.room-table td{padding:12px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle;}
.room-thumb{width:90px;height:65px;object-fit:cover;border-radius:10px;background:#f1f1f1;}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:13px;font-weight:700;}
.badge-show{background:#e8f7e8;color:#1f7a1f;}
.badge-hide{background:#fdeaea;color:#b42318;}
.action-btn{padding:8px 12px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:700;display:inline-block;margin-right:6px;}
.edit-btn{background:#e8f1ff;color:#1d4ed8;}
.delete-btn{background:#ffeaea;color:#d11a2a;}
.preview-box{margin-top:10px;}
.preview-box img{width:100%;max-height:220px;object-fit:cover;border-radius:14px;border:1px solid #e5e5e5;}
.alert{margin-bottom:16px;padding:12px 14px;border-radius:12px;font-weight:600;}
.alert-success{background:#e8f7e8;color:#1f7a1f;border:1px solid #b7e0b7;}
.alert-error{background:#fdeaea;color:#b42318;border:1px solid #f5b5b5;}
@media (max-width:980px){.room-grid{grid-template-columns:1fr;}}
</style>

<div class="page-wrap">

    <?php if (!empty($_SESSION['room_msg'])): ?>
        <div class="alert <?= (!empty($_SESSION['room_msg_type']) && $_SESSION['room_msg_type'] === 'success') ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($_SESSION['room_msg']) ?>
        </div>
        <?php unset($_SESSION['room_msg'], $_SESSION['room_msg_type']); ?>
    <?php endif; ?>

    <div class="room-grid">

        <div class="card">
            <h2><?= $editData ? 'แก้ไขห้องพัก' : 'เพิ่มห้องพักใหม่' ?></h2>

            <form action="save_room.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id'] ?? '') ?>">

                <div class="form-group">
                    <label>ชื่อห้องพัก</label>
                    <input type="text" name="room_name" required value="<?= htmlspecialchars($editData['room_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>ประเภทห้อง</label>
                    <input type="text" name="room_type" required value="<?= htmlspecialchars($editData['room_type'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="description"><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>ราคา / คืน</label>
                    <input type="number" step="0.01" name="price" min="0" required value="<?= htmlspecialchars($editData['price'] ?? '0') ?>">
                </div>

                <div class="form-group">
                    <label>จำนวนห้องทั้งหมด</label>
                    <input type="number" name="total_rooms" min="1" required value="<?= htmlspecialchars($editData['total_rooms'] ?? '5') ?>">
                </div>

                <div class="form-group">
                    <label>จำนวนผู้เข้าพักสูงสุด</label>
                    <input type="number" name="max_guests" min="1" required value="<?= htmlspecialchars($editData['max_guests'] ?? '2') ?>">
                </div>

                <div class="form-group">
                    <label>ขนาดห้อง</label>
                    <input type="text" name="room_size" value="<?= htmlspecialchars($editData['room_size'] ?? '') ?>" placeholder="เช่น 28 ตร.ม.">
                </div>

                <div class="form-group">
                    <label>ประเภทเตียง</label>
                    <input type="text" name="bed_type" value="<?= htmlspecialchars($editData['bed_type'] ?? '') ?>" placeholder="เช่น เตียงเดี่ยว / เตียงคู่">
                </div>

                <div class="form-group">
                    <label>สถานะการแสดงผล</label>
                    <select name="status">
                        <option value="1" <?= ((string)($editData['status'] ?? '1') === '1') ? 'selected' : '' ?>>แสดง</option>
                        <option value="0" <?= ((string)($editData['status'] ?? '1') === '0') ? 'selected' : '' ?>>ซ่อน</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>รูปหน้าห้องพัก</label>
                    <input type="file" name="room_image" accept="image/*">
                </div>

                <?php if (!empty($editData['image_path'])): ?>
                    <div class="preview-box">
                        <img src="<?= htmlspecialchars($editData['image_path']) ?>" alt="room image">
                    </div>
                <?php endif; ?>

                <div style="margin-top:18px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-save"><?= $editData ? 'อัปเดตข้อมูล' : 'บันทึกห้องพัก' ?></button>
                    <?php if ($editData): ?>
                        <a href="manage_rooms.php" class="btn btn-cancel">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>รายการห้องพักทั้งหมด</h2>

            <div class="table-wrap">
                <table class="room-table">
                    <thead>
                        <tr>
                            <th>รูป</th>
                            <th>ชื่อห้อง</th>
                            <th>ประเภท</th>
                            <th>ราคา</th>
                            <th>จำนวนห้อง</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rooms && $rooms->num_rows > 0): ?>
                            <?php while ($row = $rooms->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($row['image_path']) ?>" class="room-thumb" alt="">
                                        <?php else: ?>
                                            <div class="room-thumb"></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['room_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['room_type'] ?? '') ?></td>
                                    <td>฿<?= number_format((float)($row['price'] ?? 0), 2) ?></td>
                                    <td><?= (int)($row['total_rooms'] ?? 0) ?> ห้อง</td>
                                    <td>
                                        <?php if ((string)($row['status'] ?? '1') === '1'): ?>
                                            <span class="badge badge-show">แสดง</span>
                                        <?php else: ?>
                                            <span class="badge badge-hide">ซ่อน</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="action-btn edit-btn" href="manage_rooms.php?edit=<?= (int)$row['id'] ?>">แก้ไข</a>
                                        <a class="action-btn delete-btn" href="delete_room.php?id=<?= (int)$row['id'] ?>" onclick="return confirm('ยืนยันการลบห้องพักนี้?')">ลบ</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">ยังไม่มีข้อมูลห้องพัก</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include 'admin_layout_bottom.php'; ?>