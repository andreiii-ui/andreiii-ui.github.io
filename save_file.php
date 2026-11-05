<?php
session_start();
include_once 'rolePermissionHandler.php';
// Allow any logged-in user to save file references in session
require_login();

// Ensure the "file" parameter exists
if (isset($_GET['file'])) {
  $file = htmlspecialchars($_GET['file']);

  // Initialize session storage
  if (!isset($_SESSION['saved_files'])) {
    $_SESSION['saved_files'] = [];
  }

  // Avoid duplicates
  if (!in_array($file, $_SESSION['saved_files'])) {
    $_SESSION['saved_files'][] = $file;
  }
}

// Redirect back to homepage
header("Location: Homepage.php");
exit();
?>