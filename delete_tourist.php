<?php
$conn = new mysqli("localhost", "root", "", "backoffice_db");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM tourists WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        header("Location: admin_dashboard.php"); // ลบเสร็จให้เด้งกลับหน้าเดิม
    } else {
        echo "Error deleting record: " . $conn->error;
    }
}
$conn->close();
?>