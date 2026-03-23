<?php
$conn = new mysqli("localhost","root","Kanathip04","backoffice_db");

$conn->query("
UPDATE tourists
SET archived = 1
WHERE DATE(created_at) = CURDATE()
");

header("Location: admin_dashboard.php");
?>