<?php
session_start();
if (empty($_SESSION['user_id'])) { exit('กรุณา login ก่อน'); }

$url  = 'https://script.google.com/macros/s/AKfycbygu-OAkt8DnHiHmlw8fblIv3ZRUnSeGYWf2lYNJ_NkqoB0idnungpT-gSca4UqKQ5qww/exec';
$json = json_encode([
    'created_at' => '28/03/2569 14:00:00',
    'user_name'  => 'TestUser',
    'role'       => 'admin',
    'message'    => 'TEST MESSAGE FROM PHP',
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_VERBOSE        => false,
]);

$result = curl_exec($ch);
$err    = curl_error($ch);
$info   = curl_getinfo($ch);
curl_close($ch);

echo '<pre>';
echo "HTTP Status: " . $info['http_code'] . "\n";
echo "URL สุดท้าย: " . $info['url'] . "\n";
echo "เวลา: " . $info['total_time'] . "s\n";
echo "cURL Error: " . ($err ?: 'ไม่มี') . "\n";
echo "Response: " . ($result ?: '(ว่าง)') . "\n";
echo '</pre>';
