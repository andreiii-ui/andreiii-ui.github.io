<?php
session_start();
include 'connect.php';
include_once 'rolePermissionHandler.php';
// Only admins may delete history entries
require_admin();

// Support both legacy form (id via POST/GET) and JSON bulk ({ids: [...]}))
$input = null;
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
}

$ids = [];
if ($input && isset($input['ids']) && is_array($input['ids'])) {
    $ids = array_values(array_map('intval', $input['ids']));
} elseif (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $ids = [intval($_POST['id'])];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $ids = [intval($_GET['id'])];
}

if (empty($ids)) {
    // If this was a JSON request, return JSON error; otherwise redirect
    if ($input) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No ids provided']);
        exit;
    }
    header('Location: trash_bin.php');
    exit;
}

$results = ['deleted' => 0, 'failed' => []];
$delStmt = $conn->prepare("DELETE FROM document_history WHERE id = ?");
foreach ($ids as $idVal) {
    if (!$delStmt) {
        $results['failed'][] = $idVal;
        continue;
    }
    $delStmt->bind_param('i', $idVal);
    if ($delStmt->execute()) {
        $results['deleted']++;
    } else {
        $results['failed'][] = $idVal;
    }
}
if ($delStmt)
    $delStmt->close();

if ($input) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'deleted' => $results['deleted'], 'failed' => $results['failed']]);
    exit;
}

header('Location: trash_bin.php');
exit;



/* ----------------------legacy code below---------------------- 
session_start();
include 'connect.php';
include_once 'rolePermissionHandler.php';
// Only admins may delete history entries
require_admin();

// Support both legacy form (id via POST/GET) and JSON bulk ({ids: [...]})
$input = null;
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
}

$ids = [];
if ($input && isset($input['ids']) && is_array($input['ids'])) {
$ids = array_values(array_map('intval', $input['ids']));
} elseif (isset($_POST['id']) && is_numeric($_POST['id'])) {
$ids = [intval($_POST['id'])];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
$ids = [intval($_GET['id'])];
}

if (empty($ids)) {
// If this was a JSON request, return JSON error; otherwise redirect
if ($input) {
   header('Content-Type: application/json');
   echo json_encode(['success' => false, 'error' => 'No ids provided']);
   exit;
}
header('Location: trash_bin.php');
exit;
}

$results = ['deleted' => 0, 'failed' => []];
$delStmt = $conn->prepare("DELETE FROM document_history WHERE id = ?");
foreach ($ids as $idVal) {
if (!$delStmt) {
   $results['failed'][] = $idVal;
   continue;
}
$delStmt->bind_param('i', $idVal);
if ($delStmt->execute()) {
   $results['deleted']++;
} else {
   $results['failed'][] = $idVal;
}
}
if ($delStmt)
$delStmt->close();

if ($input) {
header('Content-Type: application/json');
echo json_encode(['success' => true, 'deleted' => $results['deleted'], 'failed' => $results['failed']]);
exit;
}

header('Location: trash_bin.php');
exit;

?>