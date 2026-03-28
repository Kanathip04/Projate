<?php
// Background worker — called via exec(), no HTTP session needed
if (php_sapi_name() !== 'cli') exit;

$data = json_decode($argv[1] ?? '{}', true);
if (!$data) exit;

$url  = 'https://script.google.com/macros/s/AKfycbygu-OAkt8DnHiHmlw8fblIv3ZRUnSeGYWf2lYNJ_NkqoB0idnungpT-gSca4UqKQ5qww/exec';
$json = json_encode($data);

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
]);
curl_exec($ch);
curl_close($ch);
