<?php
session_start();

// ถ้าล็อกอินแล้ว → ไป dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}
?>