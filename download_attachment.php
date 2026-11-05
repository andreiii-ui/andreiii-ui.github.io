<?php
session_start();
include 'connect.php';

// Only logged in users can download attachments
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Support either attachment_id (new) or record_id (legacy single attachment)
$attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
$record_id = isset($_GET['record_id']) ? intval($_GET['record_id']) : 0;

$path = '';
$origName = '';

if ($attachment_id) {
    $aStmt = $conn->prepare("SELECT file_path, original_name FROM record_attachments WHERE id = ? LIMIT 1");
    $aStmt->bind_param('i', $attachment_id);
    $aStmt->execute();
    $aRes = $aStmt->get_result();
    if (!$aRes || $aRes->num_rows === 0) {
        http_response_code(404);
        echo 'Attachment not found';
        exit;
    }
    $aRow = $aRes->fetch_assoc();
    $path = $aRow['file_path'];
    $origName = $aRow['original_name'];
    $aStmt->close();
} elseif ($record_id) {
    $stmt = $conn->prepare("SELECT attachment, attachment_name FROM document_records WHERE record_id = ? LIMIT 1");
    $stmt->bind_param('i', $record_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        http_response_code(404);
        echo 'Record not found';
        exit;
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    $path = $row['attachment'] ?? '';
    $origName = $row['attachment_name'] ?? '';
    if (empty($path)) {
        http_response_code(404);
        echo 'No attachment for this record';
        exit;
    }
} else {
    http_response_code(400);
    echo 'Missing attachment_id or record_id';
    exit;
}

// Only serve files in uploads/ for safety
if (stripos($path, 'uploads/') !== 0) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$fullPath = __DIR__ . '/' . $path;
if (!file_exists($fullPath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';
finfo_close($finfo);

// Use original filename if available, otherwise use stored filename
$downloadName = $origName ?: basename($fullPath);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;

?>