<?php
include 'connect.php';
include_once 'rolePermissionHandler.php';
// Only admins can clear history
require_admin();

$conn->query("TRUNCATE TABLE activity_history");
header('Location: history.php');
exit;
?>