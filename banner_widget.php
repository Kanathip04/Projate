<?php
// banner_widget.php
// ต้องมี $conn พร้อมแล้วก่อน include ไฟล์นี้

$msg = "";

// ดึงรูปปัจจุบัน
$currentBanner = null;
$res = $conn->query("SELECT * FROM site_banners ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows > 0) $currentBanner = $res->fetch_assoc();

// อัปโหลดเมื่อกดบันทึก (มาจากฟอร์มในแดชบอร์ด)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["upload_banner"])) {

    if (!isset($_FILES["banner"]) || $_FILES["banner"]["error"] !== UPLOAD_ERR_OK) {
        $msg = "อัปโหลดไม่สำเร็จ";
    } else {
        $file = $_FILES["banner"];
        $allowedExt = ["jpg","jpeg","png","webp"];
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt)) {
            $msg = "ไฟล์ต้องเป็น JPG/PNG/WEBP เท่านั้น";
        } elseif ($file["size"] > 3 * 1024 * 1024) {
            $msg = "ไฟล์ใหญ่เกิน 3MB";
        } elseif (@getimagesize($file["tmp_name"]) === false) {
            $msg = "ไฟล์ไม่ใช่รูปภาพ";
        } else {

            $uploadDir = __DIR__ . "/uploads/banners/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $newName = "banner_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $targetPath = $uploadDir . $newName;

            if (move_uploaded_file($file["tmp_name"], $targetPath)) {
                $dbPath = "uploads/banners/" . $newName;

                // บันทึก DB
                $stmt = $conn->prepare("INSERT INTO site_banners (image_path) VALUES (?)");
                $stmt->bind_param("s", $dbPath);
                $stmt->execute();
                $stmt->close();

                // ลบรูปเก่า (กันโฟลเดอร์รก)
                if ($currentBanner && !empty($currentBanner["image_path"])) {
                    $oldFile = __DIR__ . "/" . $currentBanner["image_path"];
                    if (file_exists($oldFile)) @unlink($oldFile);
                }

                // รีเฟรชค่ารูปล่าสุด
                $res = $conn->query("SELECT * FROM site_banners ORDER BY id DESC LIMIT 1");
                if ($res && $res->num_rows > 0) $currentBanner = $res->fetch_assoc();

                $msg = "บันทึกรูปใหม่เรียบร้อย";
            } else {
                $msg = "ย้ายไฟล์ไม่สำเร็จ (เช็คสิทธิ์ uploads/banners)";
            }
        }
    }
}
?>

<div class="banner-card">
  <div class="banner-card-head">
    <div class="banner-title">เปลี่ยนรูปหน้าเว็บ (แบนเนอร์)</div>
    <div class="banner-sub">แก้ไขได้ในหน้าแดชบอร์ด</div>
  </div>

  <?php if ($msg): ?>
    <div class="banner-msg <?php echo (strpos($msg,'ไม่')!==false || strpos($msg,'เกิน')!==false) ? 'err' : ''; ?>">
      <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endif; ?>

  <div class="banner-grid">
    <div>
      <div class="label">ตัวอย่างรูปปัจจุบัน</div>
      <div class="preview">
        <?php if ($currentBanner): ?>
          <img src="<?php echo htmlspecialchars($currentBanner["image_path"]); ?>?v=<?php echo time(); ?>" alt="banner">
        <?php else: ?>
          <div class="noimg">ยังไม่มีรูป</div>
        <?php endif; ?>
      </div>
      <div class="hint">รองรับ JPG/PNG/WEBP ขนาดไม่เกิน 3MB</div>
    </div>

    <div>
      <div class="label">อัปโหลดรูปใหม่</div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="upload_banner" value="1">
        <input type="file" name="banner" accept=".jpg,.jpeg,.png,.webp" required>
        <button class="btn-save" type="submit">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<style>
  .banner-card{background:#fff;border-radius:14px;padding:16px;box-shadow:0 8px 18px rgba(0,0,0,.06);margin-top:16px;}
  .banner-card-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;}
  .banner-title{font-weight:800;font-size:16px;}
  .banner-sub{color:#666;font-size:12px;}
  .banner-grid{display:flex;gap:16px;flex-wrap:wrap;}
  .banner-grid > div{flex:1;min-width:280px;}
  .label{font-size:13px;color:#666;margin-bottom:8px;}
  .preview{border:1px dashed #ccc;border-radius:12px;height:200px;background:#fafafa;display:flex;align-items:center;justify-content:center;overflow:hidden;}
  .preview img{width:100%;height:100%;object-fit:cover;}
  .noimg{color:#888;}
  .hint{font-size:12px;color:#777;margin-top:8px;}
  .btn-save{background:#2e7d32;color:#fff;border:0;padding:10px 14px;border-radius:10px;cursor:pointer;}
  .banner-msg{margin:10px 0;padding:10px 12px;border-radius:10px;border:1px solid #cfe9cf;background:#eef7ee;}
  .banner-msg.err{border-color:#ffd0d0;background:#fff0f0;}
</style>