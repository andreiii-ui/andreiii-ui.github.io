<?php
include 'connect.php';
include_once 'rolePermissionHandler.php';
// Only admins may view activity history
require_admin();
$result = $conn->query("SELECT * FROM activity_history ORDER BY timestamp DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Activity History</title>
  <style>
    body {
      font-family: Arial;
      background: #f8f9fa;
      padding: 20px;
    }

    table {
      border-collapse: collapse;
      width: 100%;
      margin-top: 20px;
    }

    th,
    td {
      border: 1px solid #ccc;
      padding: 8px;
      text-align: left;
    }

    th {
      background: #1b5e20;
      color: white;
    }
  </style>
</head>

<body>
  <h2>Activity History</h2>
  <table>
    <tr>
      <th>ID</th>
      <th>Access Code</th>
      <th>Library</th>
      <th>Location Code</th>
      <th>Author</th>
      <th>Department</th>
      <th>Title</th>
      <th>Type</th>
      <th>Record Status</th>
      <th>Retention Period</th>
      <th>Action</th>
      <th>Timestamp</th>
    </tr>

    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['access_code']) ?></td>
          <td><?= htmlspecialchars($row['library']) ?></td>
          <td><?= htmlspecialchars($row['location_code']) ?></td>
          <td><?= htmlspecialchars($row['author']) ?></td>
          <td><?= htmlspecialchars($row['department']) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['type']) ?></td>
          <td><?= htmlspecialchars($row['record_status']) ?></td>
          <td><?= htmlspecialchars($row['retention_period']) ?></td>
          <td><?= htmlspecialchars($row['action']) ?></td>
          <td><?= $row['timestamp'] ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="12">No activity found</td>
      </tr>
    <?php endif; ?>
  </table>
</body>

</html>