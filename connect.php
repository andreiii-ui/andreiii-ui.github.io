<?php
// db_connect.php

$servername = "localhost";
$username = "root";  // default for XAMPP
$password = "";      // leave blank for XAMPP default
$dbname = "library_records"; // make sure this matches your actual DB name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Ensure the connection uses UTF-8 (utf8mb4) so names and special characters are stored correctly
if (!@$conn->set_charset('utf8mb4')) {
    // Fallback: issue a SET NAMES query
    $conn->query("SET NAMES 'utf8mb4'");
}