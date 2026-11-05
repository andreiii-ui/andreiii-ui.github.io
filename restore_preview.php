<?php
session_start();
include 'connect.php';
include_once 'rolePermissionHandler.php';
// Only admins may view restore previews
require_admin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM document_history WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$deleted = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$deleted) {
    echo json_encode(['error' => 'Deleted entry not found']);
    exit;
}

// Try to find a concurrent active record. Prefer matching by record_id (if present),
// otherwise fall back to matching by access_code.
$existing = null;
if (!empty($deleted['record_id'])) {
    $rid = intval($deleted['record_id']);
    $chk = $conn->prepare("SELECT * FROM document_records WHERE record_id = ? LIMIT 1");
    $chk->bind_param('i', $rid);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
}

if (!$existing && !empty($deleted['access_code'])) {
    $acc = $deleted['access_code'];
    $chk = $conn->prepare("SELECT * FROM document_records WHERE access_code = ? LIMIT 1");
    $chk->bind_param('s', $acc);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
}

echo json_encode(['deleted' => $deleted, 'existing' => $existing]);
exit;

?>