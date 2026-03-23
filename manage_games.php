<?php
session_start();

// กันเข้าถ้าไม่ได้ล็อกอินแอดมิน
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// โฟลเดอร์เก็บรูปปกเกม
$uploadDir = __DIR__ . "/uploads/games/";
$uploadUrl = "uploads/games/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* =========================
   อัปเดตข้อมูลเกม + อัปโหลดรูป
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_game"])) {
    $id         = (int)($_POST["id"] ?? 0);
    $title      = trim($_POST["title"] ?? "");
    $game_url   = trim($_POST["game_url"] ?? "");
    $sort_order = (int)($_POST["sort_order"] ?? 0);
    $is_active  = isset($_POST["is_active"]) ? 1 : 0;

    if ($id <= 0 || $title === "" || $game_url === "") {
        $message = "<div class='alert error'>❌ กรุณากรอกข้อมูลให้ครบ</div>";
    } else {
        $newImagePath = null;

        // อัปโหลดรูป (ถ้ามี)
        if (isset($_FILES["cover_image"]) && $_FILES["cover_image"]["error"] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES["cover_image"]["tmp_name"];
            $name = $_FILES["cover_image"]["name"];
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $allow = ["jpg", "jpeg", "png", "webp"];

            if (!in_array($ext, $allow)) {
                $message = "<div class='alert error'>❌ รองรับเฉพาะไฟล์ JPG, JPEG, PNG, WEBP</div>";
            } else {
                $filename = "game_" . $id . "_" . time() . "." . $ext;
                $destPath = $uploadDir . $filename;

                if (move_uploaded_file($tmp, $destPath)) {
                    $newImagePath = $uploadUrl . $filename;
                } else {
                    $message = "<div class='alert error'>❌ อัปโหลดรูปไม่สำเร็จ</div>";
                }
            }
        }

        if ($message === "") {
            if ($newImagePath !== null) {
                $stmt = $conn->prepare("UPDATE games SET title=?, game_url=?, cover_image=?, sort_order=?, is_active=? WHERE id=?");

                if ($stmt) {
                    $stmt->bind_param("sssiii", $title, $game_url, $newImagePath, $sort_order, $is_active, $id);

                    if ($stmt->execute()) {
                        $message = "<div class='alert ok'>✅ บันทึกเรียบร้อย</div>";
                    } else {
                        $message = "<div class='alert error'>❌ บันทึกไม่สำเร็จ: " . htmlspecialchars($stmt->error) . "</div>";
                    }

                    $stmt->close();
                } else {
                    $message = "<div class='alert error'>❌ เตรียมคำสั่ง SQL ไม่สำเร็จ: " . htmlspecialchars($conn->error) . "</div>";
                }
            } else {
                $stmt = $conn->prepare("UPDATE games SET title=?, game_url=?, sort_order=?, is_active=? WHERE id=?");

                if ($stmt) {
                    $stmt->bind_param("ssiii", $title, $game_url, $sort_order, $is_active, $id);

                    if ($stmt->execute()) {
                        $message = "<div class='alert ok'>✅ บันทึกเรียบร้อย</div>";
                    } else {
                        $message = "<div class='alert error'>❌ บันทึกไม่สำเร็จ: " . htmlspecialchars($stmt->error) . "</div>";
                    }

                    $stmt->close();
                } else {
                    $message = "<div class='alert error'>❌ เตรียมคำสั่ง SQL ไม่สำเร็จ: " . htmlspecialchars($conn->error) . "</div>";
                }
            }
        }
    }
}

/* =========================
   ดึงรายการเกม
========================= */
$games = [];

$res = $conn->query("SELECT * FROM games ORDER BY sort_order ASC, id ASC");

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $games[] = $r;
    }
} else {
    $message .= "<div class='alert error'>❌ ไม่สามารถดึงข้อมูลเกมได้: " . htmlspecialchars($conn->error) . "</div>";
}

// ใช้ layout เดิมของแอดมิน
$pageTitle = "จัดการเกม";
$activeMenu = "games";
include "admin_layout_top.php";
?>

<div class="content-card">
    <div class="page-title">🎮 จัดการเกม (อัปโหลดรูปปก)</div>

    <?php echo $message; ?>

    <div style="overflow:auto;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th style="padding:10px; text-align:left;">รูป</th>
                    <th style="padding:10px; text-align:left;">ชื่อเกม</th>
                    <th style="padding:10px; text-align:left;">ไฟล์เกม (URL)</th>
                    <th style="padding:10px; text-align:center;">เรียง</th>
                    <th style="padding:10px; text-align:center;">เปิดใช้งาน</th>
                    <th style="padding:10px; text-align:left;">อัปโหลดรูปใหม่</th>
                    <th style="padding:10px; text-align:center;">บันทึก</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($games)): ?>
                    <?php foreach ($games as $g): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <form method="post" enctype="multipart/form-data" style="display:contents;">
                                <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">

                                <td style="padding:10px; width:140px;">
                                    <?php if (!empty($g['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($g['cover_image']); ?>" style="width:120px; height:70px; object-fit:cover; border-radius:10px; border:1px solid #ddd;">
                                    <?php else: ?>
                                        <div style="width:120px;height:70px;border-radius:10px;border:1px dashed #bbb;display:flex;align-items:center;justify-content:center;color:#888;">
                                            ไม่มีรูป
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td style="padding:10px;">
                                    <input type="text" name="title" value="<?php echo htmlspecialchars($g['title']); ?>" style="width:220px;padding:10px;border:1px solid #ddd;border-radius:10px;">
                                </td>

                                <td style="padding:10px;">
                                    <input type="text" name="game_url" value="<?php echo htmlspecialchars($g['game_url']); ?>" style="width:260px;padding:10px;border:1px solid #ddd;border-radius:10px;">
                                </td>

                                <td style="padding:10px;text-align:center;">
                                    <input type="number" name="sort_order" value="<?php echo (int)$g['sort_order']; ?>" style="width:80px;padding:10px;border:1px solid #ddd;border-radius:10px;text-align:center;">
                                </td>

                                <td style="padding:10px;text-align:center;">
                                    <input type="checkbox" name="is_active" <?php echo ((int)$g['is_active'] === 1 ? 'checked' : ''); ?>>
                                </td>

                                <td style="padding:10px;">
                                    <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp">
                                </td>

                                <td style="padding:10px;text-align:center;">
                                    <button type="submit" name="save_game" class="btn-save">บันทึก</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="padding:20px; text-align:center; color:#666;">
                            ยังไม่มีข้อมูลเกม หรือยังไม่มีตาราง games ในฐานข้อมูล
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .alert {
        padding: 12px 14px;
        border-radius: 10px;
        margin: 10px 0;
        font-weight: 700;
    }

    .alert.ok {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert.error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .btn-save {
        padding: 10px 14px;
        border: none;
        border-radius: 10px;
        background: #638411;
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }

    .btn-save:hover {
        filter: brightness(1.05);
    }
</style>

<?php include "admin_layout_bottom.php"; ?>