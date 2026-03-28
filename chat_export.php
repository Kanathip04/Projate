<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) { exit('DB Error'); }

// ── ตัวกรอง (optional) ──────────────────────────────────
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to']   ?? '';
$search   = $_GET['search'] ?? '';

$where = [];
if ($dateFrom) $where[] = "DATE(created_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo)   $where[] = "DATE(created_at) <= '" . $conn->real_escape_string($dateTo) . "'";
if ($search)   $where[] = "message LIKE '%" . $conn->real_escape_string($search) . "%'";
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$res = $conn->query("
    SELECT
        id,
        created_at,
        user_name,
        role,
        message,
        reactions
    FROM chat_messages
    $whereSQL
    ORDER BY id ASC
");

// ── สร้าง Excel (XML SpreadsheetML — เปิดได้ใน Excel ทุกเวอร์ชัน) ──
$filename = 'chat_history_' . date('Y-m-d_H-i-s') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// BOM สำหรับ UTF-8
echo "\xEF\xBB\xBF";

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]>
<xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
<x:Name>ประวัติแชท</x:Name>
<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml>
<![endif]-->
<style>
  table { border-collapse: collapse; font-family: TH SarabunPSK, Sarabun, Arial; font-size: 13pt; }
  th {
    background-color: #1a1a2e;
    color: #c9a96e;
    padding: 8px 12px;
    text-align: center;
    border: 1px solid #444;
    font-weight: bold;
    mso-pattern: auto none;
  }
  td { padding: 6px 12px; border: 1px solid #ddd; vertical-align: top; }
  tr:nth-child(even) td { background-color: #f9f7f4; }
  .col-id    { text-align: center; width: 50px; }
  .col-date  { text-align: center; width: 160px; white-space: nowrap; }
  .col-name  { width: 160px; }
  .col-role  { text-align: center; width: 90px; }
  .col-msg   { width: 420px; }
  .col-react { text-align: center; width: 140px; }
  .role-admin { color: #b45309; font-weight: bold; }
  .role-user  { color: #1e40af; }
  .title-row td {
    background-color: #0f0f1a;
    color: #f5d89a;
    font-size: 16pt;
    font-weight: bold;
    text-align: center;
    padding: 14px;
    border: none;
  }
  .sub-row td {
    background-color: #22223b;
    color: #aaa;
    font-size: 10pt;
    text-align: center;
    padding: 4px;
    border: none;
  }
</style>
</head>
<body>
<table>
  <!-- Title -->
  <tr class="title-row">
    <td colspan="6">ประวัติการแชท — Global Chat</td>
  </tr>
  <tr class="sub-row">
    <td colspan="6">Export เมื่อ: <?= date('d/m/Y H:i:s') ?> | ระบบ WRBRI</td>
  </tr>
  <tr><td colspan="6" style="height:6px;border:none;background:#fff"></td></tr>

  <!-- Header -->
  <tr>
    <th class="col-id">#</th>
    <th class="col-date">วันที่ / เวลา</th>
    <th class="col-name">ชื่อผู้ส่ง</th>
    <th class="col-role">บทบาท</th>
    <th class="col-msg">ข้อความ</th>
    <th class="col-react">Reactions</th>
  </tr>

<?php
$row_num = 1;
while ($row = $res->fetch_assoc()):
    // แปลง datetime เป็นภาษาไทย
    $dt = new DateTime($row['created_at']);
    $thYear = (int)$dt->format('Y') + 543;
    $dateStr = $dt->format('d/m/') . $thYear . ' ' . $dt->format('H:i:s');

    // แปลง reactions JSON เป็นข้อความ
    $reactText = '';
    if (!empty($row['reactions'])) {
        $reacts = json_decode($row['reactions'], true);
        if ($reacts) {
            $parts = [];
            foreach ($reacts as $emoji => $uids) {
                $parts[] = $emoji . ' ' . count($uids);
            }
            $reactText = implode('  ', $parts);
        }
    }

    $roleLabel = $row['role'] === 'admin' ? 'Admin' : 'Member';
    $roleClass = $row['role'] === 'admin' ? 'role-admin' : 'role-user';
    $msg = htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8');
?>
  <tr>
    <td class="col-id"><?= $row_num++ ?></td>
    <td class="col-date"><?= $dateStr ?></td>
    <td class="col-name"><?= $name ?></td>
    <td class="col-role <?= $roleClass ?>"><?= $roleLabel ?></td>
    <td class="col-msg"><?= $msg ?></td>
    <td class="col-react"><?= htmlspecialchars($reactText) ?></td>
  </tr>
<?php endwhile; ?>

  <!-- Summary -->
  <tr><td colspan="6" style="height:6px;border:none;background:#fff"></td></tr>
  <tr>
    <td colspan="6" style="text-align:right;color:#888;font-size:10pt;border:none;padding:4px 12px">
      ทั้งหมด <?= $row_num - 1 ?> ข้อความ
    </td>
  </tr>
</table>
</body>
</html>
<?php $conn->close(); ?>
