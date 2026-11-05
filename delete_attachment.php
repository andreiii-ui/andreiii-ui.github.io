<?php
// Start output buffering immediately to capture any accidental output (notices, warnings, etc.)
// so that JSON responses are not corrupted. Do this before includes.
if (ob_get_level() === 0)
    ob_start();

session_start();
include 'connect.php';
include_once 'rolePermissionHandler.php';
// shared file helpers
include_once 'file_utils.php';
// activity logging helper
include_once 'log_activity.php';
// Only modifiers (admins/encoders) can delete attachments
require_modifier();

// Helper to send clean JSON responses (clears any accidental output)
function send_json($data, $http_code = 200)
{
    // clear any previous output buffers
    while (ob_get_level() > 0)
        ob_end_clean();
    http_response_code($http_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
if (!$attachment_id) {
    send_json(['success' => false, 'error' => 'Missing attachment_id'], 400);
}

$stmt = $conn->prepare("SELECT record_id, file_path FROM record_attachments WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $attachment_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    send_json(['success' => false, 'error' => 'Attachment not found'], 404);
}
$row = $res->fetch_assoc();
$stmt->close();

$file_path = $row['file_path'] ?? '';
$record_id = intval($row['record_id']);

// Attempt to delete the file from disk using shared helper
// Use explicit array assignment for compatibility and to avoid emitting warnings
$unlink_res = try_unlink_upload($file_path);
$file_deleted = !empty($unlink_res[0]);
$deleted_path = isset($unlink_res[1]) ? $unlink_res[1] : null;

// Delete DB row
$d = $conn->prepare("DELETE FROM record_attachments WHERE id = ?");
$d->bind_param('i', $attachment_id);
$d->execute();
$d->close();

// After deletion, update legacy document_records.attachment fields to point to first remaining attachment (if any)
$firstQ = $conn->prepare("SELECT file_path, original_name FROM record_attachments WHERE record_id = ? ORDER BY id ASC LIMIT 1");
$firstQ->bind_param('i', $record_id);
$firstQ->execute();
$firstRes = $firstQ->get_result();
if ($firstRes && $firstRes->num_rows > 0) {
    $frow = $firstRes->fetch_assoc();
    $u = $conn->prepare("UPDATE document_records SET attachment = ?, attachment_name = ? WHERE record_id = ?");
    $u->bind_param('ssi', $frow['file_path'], $frow['original_name'], $record_id);
    $u->execute();
    $u->close();
} else {
    $u = $conn->prepare("UPDATE document_records SET attachment = '', attachment_name = '' WHERE record_id = ?");
    $u->bind_param('i', $record_id);
    $u->execute();
    $u->close();
}
$firstQ->close();

// --- Log this change into activity_history as an 'edit' so admins can see attachment timeline ---
// Fetch current record snapshot
$recStmt = $conn->prepare("SELECT record_id, access_code, library, location_code, author, department, title, type, record_status, retention_period, description, document_number, supplier, amount_due, notes_keywords, addressee, accountable_officer, sender, source_funds, property_number, total_cost, attachment, attachment_name, status, date_created FROM document_records WHERE record_id = ? LIMIT 1");
$recRow = null;
if ($recStmt) {
    $recStmt->bind_param('i', $record_id);
    $recStmt->execute();
    $resR = $recStmt->get_result();
    if ($resR && $resR->num_rows > 0) {
        $recRow = $resR->fetch_assoc();
    }
    $recStmt->close();
}

// gather remaining attachments metadata
$atts = [];
$raQ = $conn->prepare("SELECT id, file_path, original_name, uploaded_at FROM record_attachments WHERE record_id = ? ORDER BY uploaded_at DESC");
if ($raQ) {
    $raQ->bind_param('i', $record_id);
    $raQ->execute();
    $raRes = $raQ->get_result();
    while ($ar = $raRes->fetch_assoc()) {
        $atts[] = [
            'id' => (int) $ar['id'],
            'file_path' => $ar['file_path'],
            'original_name' => $ar['original_name'],
            'uploaded_at' => $ar['uploaded_at'] ?? null
        ];
    }
    $raQ->close();
}

// Log full record snapshot (with attachments JSON) via central logger so all record fields are captured
if ($recRow) {
    $attachments_json = count($atts) ? json_encode($atts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $data_for_log = $recRow;
    $data_for_log['attachments'] = $attachments_json;

    processActivityLog($conn, 'document_records', $record_id, $data_for_log, 'delete-attachment');
}

send_json(['success' => true, 'attachment_id' => $attachment_id, 'file_deleted' => $file_deleted ? 1 : 0, 'deleted_path' => $deleted_path], 200);
?>