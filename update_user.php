<?php
include_once 'rolePermissionHandler.php';
require_admin();
include_once 'connect.php';
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? null; // optional
$role = $_POST['role'] ?? 'viewer';

if ($id <= 0 || $username === ''){ echo json_encode(['success'=>false,'message'=>'Missing parameters']); exit(); }

// ensure username not used by another
$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
$stmt->bind_param('si',$username,$id); $stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0){ echo json_encode(['success'=>false,'message'=>'Username already taken']); exit(); }

if ($password !== null && $password !== ''){
    $password_hash = md5($password);
    $upd = $conn->prepare('UPDATE users SET username=?, password=?, role=? WHERE id=?');
    $upd->bind_param('sssi',$username,$password_hash,$role,$id);
} else {
    $upd = $conn->prepare('UPDATE users SET username=?, role=? WHERE id=?');
    $upd->bind_param('ssi',$username,$role,$id);
}

if ($upd->execute()) echo json_encode(['success'=>true,'message'=>'User updated']);
else echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]);
