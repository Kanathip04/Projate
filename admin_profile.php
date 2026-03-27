<?php
// check_files.php
$files = ['profile.php', 'admin_profile.php'];
foreach ($files as $f) {
    echo $f . ': ' . (file_exists(__DIR__.'/'.$f) ? '✅ มีไฟล์' : '❌ ไม่มีไฟล์') . '<br>';
}
?>