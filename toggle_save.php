<?php
session_start();
include("connect.php");

// ✅ Make sure user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// ✅ Get logged-in user ID
$username = $_SESSION['username'];
$user_query = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$user_query->bind_param("s", $username);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$user_id = $user['id'];
$record_id = intval($_POST['record_id'] ?? 0);

// ✅ Validate record_id
if ($record_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid record ID']);
    exit;
}

// ✅ Check if the record is already saved
$check_query = $conn->prepare("SELECT 1 FROM saved_records WHERE user_id = ? AND record_id = ?");
$check_query->bind_param("ii", $user_id, $record_id);
$check_query->execute();
$check_result = $check_query->get_result();

if ($check_result->num_rows > 0) {
    // 🔴 Remove saved record
    $delete_query = $conn->prepare("DELETE FROM saved_records WHERE user_id = ? AND record_id = ?");
    $delete_query->bind_param("ii", $user_id, $record_id);
    $delete_query->execute();
    echo json_encode(['status' => 'removed']);
} else {
    // 🟢 Add new saved record
    $insert_query = $conn->prepare("INSERT INTO saved_records (user_id, record_id) VALUES (?, ?)");
    $insert_query->bind_param("ii", $user_id, $record_id);
    $insert_query->execute();
    echo json_encode(['status' => 'saved']);
}

$conn->close();
?>
