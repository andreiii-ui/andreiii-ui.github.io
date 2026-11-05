<?php
session_start();
include 'connect.php';
session_destroy(); // destroy all session data
header("Location: index.php");
exit();
?>