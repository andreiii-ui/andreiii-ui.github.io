<?php
include 'connect.php';
header('Content-Type: application/json');

$id = $_GET['record_id'] ?? '';
if (!$id) {
  echo json_encode(['error' => 'Missing record_id']);
  exit;
}

$stmt = $conn->prepare("SELECT * FROM document_records WHERE record_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $row = $result->fetch_assoc();
  // fetch attachments if table exists
  $attachments = [];
  $aStmt = $conn->prepare("SELECT id, file_path, original_name, uploaded_at FROM record_attachments WHERE record_id = ? ORDER BY id ASC");
  if ($aStmt) {
    $aStmt->bind_param('i', $id);
    $aStmt->execute();
    $aRes = $aStmt->get_result();
    while ($aRow = $aRes->fetch_assoc()) {
      $attachments[] = [
        'id' => (int) $aRow['id'],
        'file_path' => $aRow['file_path'],
        'original_name' => $aRow['original_name'],
        'uploaded_at' => $aRow['uploaded_at']
      ];
    }
    $aStmt->close();
  }
  $row['attachments'] = $attachments;
  echo json_encode($row);
} else {
  echo json_encode(['error' => 'Record not found']);
}
?>