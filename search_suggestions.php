<?php
include("connect.php");

$field = $_GET['field'] ?? 'all';
$query = trim($_GET['query'] ?? '');

if ($query === '') {
  echo json_encode([]);
  exit;
}


// Whitelist fields to avoid SQL injection via column name
$allowed = ['title', 'author', 'department', 'access_code'];
if ($field !== 'all' && !in_array($field, $allowed, true)) {
  $field = 'title';
}

$like = '%' . $query . '%';
if ($field === 'all') {
  $sql = "SELECT title AS result FROM document_records WHERE title LIKE ? OR author LIKE ? OR department LIKE ? OR access_code LIKE ? LIMIT 10";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssss', $like, $like, $like, $like);
} else {
  $sql = "SELECT $field AS result FROM document_records WHERE $field LIKE ? LIMIT 10";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $like);
}

$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $suggestions[] = $row['result'] ?? $row['title'];
  }
}

header('Content-Type: application/json');
echo json_encode($suggestions);
$conn->close();
?>