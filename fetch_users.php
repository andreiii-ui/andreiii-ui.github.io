<?php
include_once 'rolePermissionHandler.php';
require_admin();
include_once 'connect.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare('SELECT id, username, role FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows);
    exit();
}

$result = $conn->query('SELECT id, username, role FROM users ORDER BY id DESC');
$rows = [];
while($r = $result->fetch_assoc()) $rows[] = $r;
echo json_encode($rows);
