<?php
register_shutdown_function(function(){
    $err = error_get_last();
    $msg = $err ? print_r($err,true) : "clean exit";
    file_put_contents(__DIR__.'/layout_crash.log', date('H:i:s')." $msg\n", FILE_APPEND);
});
$pageTitle  = "Test";
$activeMenu = "admin_report";
$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");
$conn->set_charset("utf8mb4");
require_once 'admin_layout_top.php';
echo "<p style='color:green;font-size:2rem'>Layout OK!</p>";
require_once 'admin_layout_bottom.php';
$conn->close();
