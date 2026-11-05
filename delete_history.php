<?php
include 'connect.php';
include_once 'rolePermissionHandler.php';
// Only admins may delete history entries
require_admin();

if (isset($_GET['ids'])) {
  $ids = explode(',', $_GET['ids']);
  $ids = array_map('intval', $ids);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $sql = "DELETE FROM activity_history WHERE id IN ($placeholders)";
  $stmt = $conn->prepare($sql);
  $types = str_repeat('i', count($ids));
  $bind_names = array_merge([$types], $ids);
  $refs = [];
  foreach ($bind_names as $k => $v)
    $refs[$k] = &$bind_names[$k];
  call_user_func_array([$stmt, 'bind_param'], $refs);
  $stmt->execute();
  $stmt->close();
}
header('Location: history.php');
exit;
?>