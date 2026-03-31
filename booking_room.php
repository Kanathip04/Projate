<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
require_once 'auth_guard.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function tableExists($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '$tableName'");
    return ($res && $res->num_rows > 0);
}

function getTableColumns($conn, $tableName) {
    $columns = [];
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $res = $conn->query("SHOW COLUMNS FROM `$safeTable`");
    if ($res) {
        while ($row = $res->fetch_assoc()) $columns[] = $row['Field'];
    }
    return $columns;
}

if (!tableExists($conn, 'rooms')) die("ไม่พบตาราง rooms ในฐานข้อมูล");

$roomColumns    = getTableColumns($conn, 'rooms');
$hasId          = in_array('id',          $roomColumns, true);
$hasRoomName    = in_array('room_name',   $roomColumns, true);
$hasRoomType    = in_array('room_type',   $roomColumns, true);
$hasPrice       = in_array('price',       $roomColumns, true);
$hasBedType     = in_array('bed_type',    $roomColumns, true);
$hasMaxGuests   = in_array('max_guests',  $roomColumns, true);
$hasImagePath   = in_array('image_path',  $roomColumns, true);
$hasDescription = in_array('description', $roomColumns, true);
$hasStatus      = in_array('status',      $roomColumns, true);
$hasTotalRooms  = in_array('total_rooms', $roomColumns, true);
$hasAmenities   = in_array('amenities',   $roomColumns, true);

if (!$hasId || !$hasRoomName) die("ตาราง rooms ต้องมีคอลัมน์ id และ room_name");

$approvedMap = [];
if (tableExists($conn, 'room_bookings')) {
    $bookingColumns   = getTableColumns($conn, 'room_bookings');
    $hasBookingRoomId = in_array('room_id',        $bookingColumns, true);
    $hasBookingStatus = in_array('booking_status', $bookingColumns, true);
    if ($hasBookingRoomId && $hasBookingStatus) {
        $hasRoomUnitsCol = in_array('room_units', $bookingColumns, true);
        if ($hasRoomUnitsCol) {
            $sqlApproved = "SELECT room_id, SUM(CASE WHEN room_units IS NOT NULL AND room_units != '' THEN JSON_LENGTH(room_units) ELSE 1 END) AS approved_total FROM room_bookings WHERE booking_status = 'approved' GROUP BY room_id";
        } else {
            $sqlApproved = "SELECT room_id, COUNT(*) AS approved_total FROM room_bookings WHERE booking_status = 'approved' GROUP BY room_id";
        }
        $resApproved = $conn->query($sqlApproved);
        if ($resApproved) {
            while ($row = $resApproved->fetch_assoc()) $approvedMap[(int)$row['room_id']] = (int)$row['approved_total'];
        }
    }
}

$selectFields = ['id', 'room_name'];
if ($hasRoomType)    $selectFields[] = 'room_type';
if ($hasPrice)       $selectFields[] = 'price';
if ($hasBedType)     $selectFields[] = 'bed_type';
if ($hasMaxGuests)   $selectFields[] = 'max_guests';
if ($hasImagePath)   $selectFields[] = 'image_path';
if ($hasDescription) $selectFields[] = 'description';
if ($hasTotalRooms)  $selectFields[] = 'total_rooms';
if ($hasStatus)      $selectFields[] = 'status';
if ($hasAmenities)   $selectFields[] = 'amenities';

$sql = "SELECT " . implode(", ", $selectFields) . " FROM rooms WHERE 1=1";
if ($hasStatus) $sql .= " AND status = 'show'";
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->execute();
$result = $stmt->get_result();

$checkin_q  = trim($_GET['checkin']  ?? '');
$checkout_q = trim($_GET['checkout'] ?? '');
$guests_q   = (int)($_GET['guests']  ?? 1);
if ($checkin_q  === '') $checkin_q  = date('Y-m-d');
if ($checkout_q === '' || $checkout_q <= $checkin_q) $checkout_q = date('Y-m-d', strtotime($checkin_q . ' +1 day'));
if ($guests_q < 1) $guests_q = 1;
$ci_ts   = strtotime($checkin_q);
$co_ts   = strtotime($checkout_q);
$nights_q = max(1, (int)(($co_ts - $ci_ts) / 86400));

function thDate2($s) {
    $m  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($s);
    return date('j', $ts) . ' ' . $m[(int)date('m', $ts)] . ' ' . (date('Y', $ts) + 543);
}

/* ---- count stats ---- */
$totalCount = $result ? $result->num_rows : 0;
$availCount = 0;
if ($result && $totalCount > 0) {
    $result->data_seek(0);
    while ($r = $result->fetch_assoc()) {
        $rid = (int)$r['id'];
        $tot = $hasTotalRooms ? max(1, (int)$r['total_rooms']) : 5;
        $app = $approvedMap[$rid] ?? 0;
        if (max(0, $tot - $app) > 0) $availCount++;
    }
    $result->data_seek(0);
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จองห้องพัก | สถาบันวิจัยวลัยรุกขเวช</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #f5f1eb; color: #1a1a2e; }
a { text-decoration: none; color: inherit; }
img { display: block; max-width: 100%; }
input, button, select { font-family: Arial, Helvetica, sans-serif; font-size: 14px; }
</style>
</head>
<body style="background:#f5f1eb;margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#1a1a2e;">

<!-- TOP BAR -->
<div style="background:#1a1a2e;padding:22px 0 28px;">
  <div style="max-width:1200px;margin:0 auto;padding:0 20px;">

    <!-- nav links -->
    <div style="margin-bottom:18px;">
      <a href="index.php" style="display:inline-block;padding:6px 14px;border-radius:99px;font-size:13px;font-weight:700;color:rgba(255,255,255,.75);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);margin-right:6px;">← หน้าหลัก</a>
      <a href="booking_status.php" style="display:inline-block;padding:6px 14px;border-radius:99px;font-size:13px;font-weight:700;color:rgba(255,255,255,.75);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);margin-right:6px;">📋 สถานะการจอง</a>
      <a href="booking_tent.php" style="display:inline-block;padding:6px 14px;border-radius:99px;font-size:13px;font-weight:700;color:rgba(255,255,255,.75);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);margin-right:6px;">⛺ จองเต็นท์</a>
      <a href="booking_boat.php" style="display:inline-block;padding:6px 14px;border-radius:99px;font-size:13px;font-weight:700;color:rgba(255,255,255,.75);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);">🚣 จองพายเรือ</a>
    </div>

    <!-- hero row -->
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="vertical-align:bottom;">
          <div style="display:inline-block;padding:4px 12px;border-radius:99px;background:rgba(201,169,110,.15);border:1px solid rgba(201,169,110,.35);color:#c9a96e;font-size:12px;font-weight:700;margin-bottom:10px;">🏨 ที่พักภายในสถาบัน</div>
          <div style="font-size:28px;font-weight:900;color:#fff;line-height:1.2;margin-bottom:6px;">จองห้องพัก <em style="font-style:italic;color:#c9a96e;">วลัยรุกขเวช</em></div>
          <div style="font-size:14px;color:rgba(255,255,255,.55);line-height:1.65;">ห้องพักมาตรฐาน บรรยากาศสงบร่มรื่น พร้อมสิ่งอำนวยความสะดวกครบครัน</div>
        </td>
        <td style="vertical-align:bottom;text-align:right;width:120px;">
          <div style="display:inline-block;background:rgba(201,169,110,.12);border:1px solid rgba(201,169,110,.3);border-radius:12px;padding:12px 20px;color:#fff;text-align:center;">
            <div style="font-size:28px;font-weight:900;color:#c9a96e;line-height:1;">🌙 <?= $nights_q ?></div>
            <div style="font-size:11px;color:rgba(255,255,255,.5);font-weight:600;">คืน</div>
          </div>
        </td>
      </tr>
    </table>

  </div>
</div>

<!-- MAIN -->
<div style="max-width:1200px;margin:0 auto;padding:24px 20px;">

  <!-- Search Card -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 4px 20px rgba(26,26,46,.1);overflow:hidden;margin-bottom:24px;">
    <div style="background:#1a1a2e;padding:13px 20px;">
      <p style="font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.5);margin:0;">🔍 ค้นหาห้องพัก</p>
    </div>
    <div style="padding:18px 20px;background:#fff;">
      <form method="GET" action="booking_room.php">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="padding-right:12px;vertical-align:bottom;">
              <label style="display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#7a7a8c;margin-bottom:5px;">วันเช็คอิน</label>
              <input type="date" name="checkin" value="<?= htmlspecialchars($checkin_q) ?>" required
                style="width:100%;padding:10px 12px;font-size:14px;color:#1a1a2e;background:#fafaf8;border:1.5px solid #e8e4de;border-radius:10px;outline:none;">
            </td>
            <td style="padding-right:12px;vertical-align:bottom;">
              <label style="display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#7a7a8c;margin-bottom:5px;">วันเช็คเอาท์</label>
              <input type="date" name="checkout" value="<?= htmlspecialchars($checkout_q) ?>" required
                style="width:100%;padding:10px 12px;font-size:14px;color:#1a1a2e;background:#fafaf8;border:1.5px solid #e8e4de;border-radius:10px;outline:none;">
            </td>
            <td style="padding-right:12px;vertical-align:bottom;width:140px;">
              <label style="display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#7a7a8c;margin-bottom:5px;">ผู้เข้าพัก</label>
              <input type="number" name="guests" min="1" max="20" value="<?= $guests_q ?>" required
                style="width:100%;padding:10px 12px;font-size:14px;color:#1a1a2e;background:#fafaf8;border:1.5px solid #e8e4de;border-radius:10px;outline:none;">
            </td>
            <td style="vertical-align:bottom;width:140px;">
              <button type="submit"
                style="width:100%;padding:11px;border:none;border-radius:10px;background:#1a1a2e;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">🔍 ค้นหา</button>
            </td>
          </tr>
        </table>
      </form>
    </div>
  </div>

  <!-- Stats Row -->
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
    <tr>
      <td style="padding-right:12px;">
        <div style="background:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 2px 10px rgba(26,26,46,.06);border-left:3px solid #1d6fad;">
          <div style="font-size:22px;margin-bottom:4px;">🏨</div>
          <div style="font-size:22px;font-weight:900;color:#1a1a2e;line-height:1;margin-bottom:2px;"><?= $totalCount ?></div>
          <div style="font-size:11px;color:#7a7a8c;font-weight:600;text-transform:uppercase;letter-spacing:.07em;">ประเภทห้องทั้งหมด</div>
        </div>
      </td>
      <td style="padding-right:12px;">
        <div style="background:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 2px 10px rgba(26,26,46,.06);border-left:3px solid #16a34a;">
          <div style="font-size:22px;margin-bottom:4px;">✅</div>
          <div style="font-size:22px;font-weight:900;color:#1a1a2e;line-height:1;margin-bottom:2px;"><?= $availCount ?></div>
          <div style="font-size:11px;color:#7a7a8c;font-weight:600;text-transform:uppercase;letter-spacing:.07em;">ประเภทที่ว่าง</div>
        </div>
      </td>
      <td>
        <div style="background:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 2px 10px rgba(26,26,46,.06);border-left:3px solid #c9a96e;">
          <div style="font-size:22px;margin-bottom:4px;">🌙</div>
          <div style="font-size:22px;font-weight:900;color:#1a1a2e;line-height:1;margin-bottom:2px;"><?= $nights_q ?></div>
          <div style="font-size:11px;color:#7a7a8c;font-weight:600;text-transform:uppercase;letter-spacing:.07em;">จำนวนคืน</div>
        </div>
      </td>
    </tr>
  </table>

  <!-- List Header -->
  <div style="background:#fff;border-radius:14px 14px 0 0;padding:14px 18px;border-bottom:1px solid #e8e4de;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td>
          <span style="font-size:15px;font-weight:800;color:#1a1a2e;border-left:3px solid #c9a96e;padding-left:10px;">ห้องพักที่ว่าง &middot; <?= $guests_q ?> ผู้เข้าพัก</span>
        </td>
        <td style="text-align:right;">
          <span style="font-size:13px;color:#7a7a8c;font-weight:600;"><?= thDate2($checkin_q) ?> — <?= thDate2($checkout_q) ?></span>
        </td>
      </tr>
    </table>
  </div>

  <!-- Room Cards -->
  <div style="background:#fff;border-radius:0 0 14px 14px;padding:16px;box-shadow:0 4px 20px rgba(26,26,46,.07);">

  <?php if ($result && $result->num_rows > 0):
    $amIcons = ['แอร์'=>'❄️','TV'=>'📺','Wi-Fi'=>'📶','ตู้เย็น'=>'🧊','ห้องน้ำในตัว'=>'🚿','เครื่องทำน้ำอุ่น'=>'🔥','ระเบียง'=>'🌅','เตียงคู่'=>'🛏','เตียงเดี่ยว'=>'🛌'];
    $rooms = [];
    while ($r = $result->fetch_assoc()) $rooms[] = $r;
    $cols = 3;
    $chunks = array_chunk($rooms, $cols);
  ?>

  <?php foreach ($chunks as $rowRooms): ?>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
    <tr style="vertical-align:top;">
    <?php
    $colWidth = round(100 / $cols);
    foreach ($rowRooms as $idx => $room):
      $roomId         = (int)$room['id'];
      $roomImg        = !empty($room['image_path']) ? $room['image_path'] : '';
      $roomDesc       = !empty($room['description']) ? $room['description'] : '';
      $roomPrice      = (float)($room['price'] ?? 0);
      $totalRooms     = $hasTotalRooms ? max(1, (int)$room['total_rooms']) : 5;
      $approvedCount  = $approvedMap[$roomId] ?? 0;
      $availableRooms = max(0, $totalRooms - $approvedCount);
      $isFull         = ($availableRooms <= 0);
      $fillPct        = $totalRooms > 0 ? round($approvedCount / $totalRooms * 100) : 0;
      $fillColor      = $fillPct >= 100 ? '#dc2626' : ($fillPct >= 60 ? '#f59e0b' : '#16a34a');
      $totalEst       = $roomPrice * $nights_q;
      $tdPad          = ($idx < count($rowRooms) - 1) ? 'padding-right:12px;' : '';
    ?>
    <td style="width:<?= $colWidth ?>%;<?= $tdPad ?>vertical-align:top;">
      <div style="border-radius:14px;border:1.5px solid #e8e4de;overflow:hidden;background:#fff;height:100%;display:block;<?= $isFull ? 'opacity:.72;' : '' ?>">

        <!-- image -->
        <div style="position:relative;overflow:hidden;">
          <?php if ($roomImg): ?>
          <img src="<?= htmlspecialchars($roomImg) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>"
            style="width:100%;height:160px;object-fit:cover;display:block;"
            onerror="this.style.display='none';this.nextSibling.style.display='flex'">
          <div style="width:100%;height:160px;background:linear-gradient(135deg,#f1ede8,#e8e4de);display:none;align-items:center;justify-content:center;font-size:40px;color:#ccc;">🏨</div>
          <?php else: ?>
          <div style="width:100%;height:160px;background:linear-gradient(135deg,#f1ede8,#e8e4de);display:flex;align-items:center;justify-content:center;font-size:40px;color:#ccc;">🏨</div>
          <?php endif; ?>

          <!-- availability badge -->
          <div style="position:absolute;top:10px;left:10px;display:inline-block;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:800;border:1px solid rgba(255,255,255,.2);background:<?= $isFull ? 'rgba(220,38,38,.85)' : 'rgba(22,163,74,.85)' ?>;color:#fff;">
            <?= $isFull ? 'ห้องเต็ม' : ('● ว่าง ' . $availableRooms . '/' . $totalRooms) ?>
          </div>

          <!-- price badge -->
          <div style="position:absolute;bottom:10px;right:10px;background:rgba(26,26,46,.85);color:#c9a96e;padding:5px 11px;border-radius:99px;font-size:13px;font-weight:800;border:1px solid rgba(201,169,110,.35);">฿<?= number_format($roomPrice) ?>/คืน</div>
        </div>

        <!-- card body -->
        <div style="padding:13px 15px;">
          <?php if (!empty($room['room_type'])): ?>
          <div style="font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#a07c3a;margin-bottom:3px;"><?= htmlspecialchars($room['room_type']) ?></div>
          <?php endif; ?>
          <div style="font-weight:800;color:#1a1a2e;font-size:15px;margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($room['room_name']) ?></div>
          <?php if ($roomDesc): ?>
          <div style="font-size:13px;color:#7a7a8c;line-height:1.6;margin-bottom:9px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= htmlspecialchars($roomDesc) ?></div>
          <?php endif; ?>

          <!-- chips -->
          <div style="margin-bottom:8px;">
            <span style="display:inline-block;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:600;background:rgba(201,169,110,.15);color:#a07c3a;border:1px solid rgba(201,169,110,.2);margin:2px;">฿<?= number_format($roomPrice) ?>/คืน</span>
            <span style="display:inline-block;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:600;background:#f1ede8;color:#7a7a8c;margin:2px;">🏠 <?= $totalRooms ?> ห้อง</span>
            <?php $cap = (int)($room['max_guests'] ?? 0); if ($cap > 0): ?>
            <span style="display:inline-block;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:600;background:#f1ede8;color:#7a7a8c;margin:2px;">👥 <?= $cap ?> คน</span>
            <?php endif; ?>
            <?php if (!empty($room['bed_type'])): ?>
            <span style="display:inline-block;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:600;background:#f1ede8;color:#7a7a8c;margin:2px;">🛏 <?= htmlspecialchars($room['bed_type']) ?></span>
            <?php endif; ?>
          </div>

          <?php if ($hasAmenities && !empty($room['amenities'])):
            $amItems = array_filter(array_map('trim', explode('|', $room['amenities']))); ?>
          <div style="margin-bottom:8px;">
            <?php foreach ($amItems as $am): ?>
            <span style="display:inline-block;padding:2px 8px;border-radius:7px;font-size:11px;font-weight:600;background:rgba(201,169,110,.1);color:#a07c3a;border:1px solid rgba(201,169,110,.2);margin:2px;"><?= ($amIcons[$am] ?? '') ?> <?= htmlspecialchars($am) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- stock bar -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:4px;">
            <tr>
              <td style="font-size:11px;font-weight:700;color:#7a7a8c;">จองแล้ว <?= $approvedCount ?>/<?= $totalRooms ?> ห้อง</td>
              <td style="text-align:right;font-size:11px;font-weight:700;color:#7a7a8c;"><?= $isFull ? 'เต็ม' : ('เหลือ ' . $availableRooms) ?></td>
            </tr>
          </table>
          <div style="height:4px;background:#e8e4de;border-radius:99px;overflow:hidden;margin-bottom:4px;">
            <div style="height:100%;border-radius:99px;background:<?= $fillColor ?>;width:<?= min(100,$fillPct) ?>%;"></div>
          </div>
        </div>

        <!-- card footer -->
        <div style="padding:10px 15px;border-top:1px solid #e8e4de;background:#fdfcfa;display:flex;align-items:center;justify-content:space-between;gap:8px;">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td style="vertical-align:middle;">
                <div style="font-size:18px;font-weight:900;color:#a07c3a;">฿<?= number_format($roomPrice) ?></div>
                <div style="font-size:10px;color:#7a7a8c;font-weight:600;">ต่อห้อง / คืน</div>
                <?php if ($nights_q > 1 && !$isFull): ?>
                <div style="font-size:11px;color:#16a34a;font-weight:700;margin-top:1px;">~฿<?= number_format($totalEst) ?> (<?= $nights_q ?> คืน)</div>
                <?php endif; ?>
              </td>
              <td style="text-align:right;vertical-align:middle;white-space:nowrap;">
                <?php if ($isFull): ?>
                <span style="display:inline-block;padding:8px 15px;border-radius:8px;font-size:13px;font-weight:700;background:#b8b0a8;color:#fff;cursor:not-allowed;">ห้องเต็ม</span>
                <?php else: ?>
                <a href="booking_form.php?room_id=<?= $roomId ?>&checkin=<?= urlencode($checkin_q) ?>&checkout=<?= urlencode($checkout_q) ?>&guests=<?= $guests_q ?>"
                  style="display:inline-block;padding:8px 15px;border-radius:8px;font-size:13px;font-weight:700;background:#1a1a2e;color:#fff;">จองเลย →</a>
                <?php endif; ?>
              </td>
            </tr>
          </table>
        </div>

      </div><!-- /card -->
    </td>
    <?php endforeach; ?>

    <!-- fill empty cells if last row has fewer than 3 -->
    <?php for ($e = count($rowRooms); $e < $cols; $e++): ?>
    <td style="width:<?= $colWidth ?>%;padding-left:12px;"></td>
    <?php endfor; ?>

    </tr>
  </table>
  <?php endforeach; ?>

  <?php else: ?>
  <div style="padding:48px;text-align:center;color:#7a7a8c;">
    <div style="font-size:40px;opacity:.3;margin-bottom:10px;">🏨</div>
    <div style="font-size:16px;font-weight:800;margin-bottom:6px;">ไม่พบห้องพักในช่วงเวลานี้</div>
    <div style="font-size:13px;">กรุณาเลือกวันที่อื่น หรือติดต่อเจ้าหน้าที่</div>
  </div>
  <?php endif; ?>

  </div><!-- /room-wrap -->

</div><!-- /main -->

<script>
(function(){
  var ci = document.querySelector('input[name=checkin]');
  var co = document.querySelector('input[name=checkout]');
  if (ci && co) {
    ci.addEventListener('change', function() {
      if (co.value <= ci.value) {
        var d = new Date(ci.value);
        d.setDate(d.getDate() + 1);
        co.value = d.toISOString().slice(0, 10);
      }
    });
  }
})();
</script>
</body>
</html>
