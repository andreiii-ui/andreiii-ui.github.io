<?php
include("connect.php"); // Connect to database
session_start();
include_once 'rolePermissionHandler.php';
require_login(); // Ensure logged-in user

header('Content-Type: application/json'); // Return JSON

// ✅ Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'You must log in first.']);
    exit;
}

// ✅ Check if record IDs are provided via POST
if (!isset($_POST['ids']) || empty($_POST['ids'])) {
    echo json_encode(['success' => false, 'error' => 'No records selected.']);
    exit;
}

// ✅ Sanitize and prepare the record IDs
$ids = explode(',', $_POST['ids']);
$ids = array_map('intval', $ids); // convert to integers
if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'Invalid records selected.']);
    exit;
}

// ✅ Get current user's ID
$username = $conn->real_escape_string($_SESSION['username']);
$user_q = $conn->query("SELECT id FROM users WHERE username='$username' LIMIT 1");
if (!$user_q || $user_q->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}
$user_id = (int) $user_q->fetch_assoc()['id'];

// ✅ Prepare placeholders for prepared statement
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "DELETE FROM saved_records WHERE user_id = ? AND record_id IN ($placeholders)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// ✅ Bind parameters dynamically
$types = str_repeat('i', count($ids) + 1); // user_id + record IDs
$params = array_merge([$user_id], $ids);
$bind_names = [];
foreach ($params as $key => $value) $bind_names[$key] = &$params[$key]; // bind by reference
array_unshift($bind_names, $types); // prepend types
call_user_func_array([$stmt, 'bind_param'], $bind_names);

// ✅ Execute and return JSON response
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Selected records removed successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error deleting records: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
