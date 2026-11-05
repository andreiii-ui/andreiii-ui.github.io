<?php
include_once 'rolePermissionHandler.php';
require_admin();
include_once 'connect.php';
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0){ echo json_encode(['success'=>false,'message'=>'Missing id']); exit(); }

// prevent deleting currently logged-in admin
session_start();
$current_username = $_SESSION['username'] ?? null;
if ($current_username){
    $q = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $q->bind_param('s',$current_username); $q->execute(); $res = $q->get_result(); $row = $res->fetch_assoc();
    if ($row && intval($row['id']) === $id){ echo json_encode(['success'=>false,'message'=>'Cannot delete currently logged-in user']); exit(); }
}

$del = $conn->prepare('DELETE FROM users WHERE id = ?');
$del->bind_param('i',$id);
if ($del->execute()) echo json_encode(['success'=>true,'message'=>'User deleted']);
else echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]);
