<?php
session_start();
require_once 'config.php';

$email = 'adminrukkhawet@gmail.com';

// เช็ค user มีในระบบไหม
$stmt = $conn->prepare("SELECT id, fullname, email, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "<h3>User ในระบบ:</h3>";
echo $user ? "<pre>" . print_r($user, true) . "</pre>" : "<p style='color:red'>❌ ไม่พบ email นี้ในตาราง users</p>";

// เช็คตาราง email_otps มีไหม
$res = $conn->query("SHOW TABLES LIKE 'email_otps'");
echo "<h3>ตาราง email_otps:</h3>";
echo $res->num_rows > 0 ? "<p style='color:green'>✅ มีตารางนี้อยู่</p>" : "<p style='color:red'>❌ ไม่มีตารางนี้</p>";

// เช็ค OTP ล่าสุดในตาราง
$res2 = $conn->query("SELECT * FROM email_otps ORDER BY id DESC LIMIT 5");
echo "<h3>OTP ล่าสุด 5 รายการ:</h3>";
echo "<pre>";
while($r = $res2->fetch_assoc()) print_r($r);
echo "</pre>";
?>