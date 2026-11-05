<?php
include_once 'rolePermissionHandler.php';
require_admin();
include_once 'connect.php';
header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'viewer';

if ($username === '' || $password === '') {
    echo json_encode(['success'=>false,'message'=>'Username and password are required']); exit();
}

// check uniqueness
$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s',$username); $stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Username already exists']); exit(); }

$password_hash = md5($password); // keep consistent with existing codebase; recommend upgrade to password_hash
$insert = $conn->prepare('INSERT INTO users (username,password,role) VALUES (?,?,?)');
$insert->bind_param('sss',$username,$password_hash,$role);
if ($insert->execute()){
    echo json_encode(['success'=>true,'message'=>'User created']);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error: '. $conn->error]);
}
