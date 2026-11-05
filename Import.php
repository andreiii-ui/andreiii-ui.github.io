<?php
session_start();
include("connect.php");
include_once('rolePermissionHandler.php');
include_once('file_utils.php');

// ✅ Only admins may import
require_admin();

// ✅ Define $role to prevent undefined variable warnings
$role = $_SESSION['role'] ?? '';

// --- All possible database fields ---
$all_fields = [
  'access_code',
  'library',
  'location_code',
  'author',
  'department',
  'title',
  'type',
  'record_status',
  'retention_period',
  'description',
  'document_number',
  'supplier',
  'amount_due',
  'notes_keywords',
  'addressee',
  'accountable_officer',
  'sender',
  'source_funds',
  'property_number',
  'total_cost',
  'attachment',
  'status',
  'date_created',
  'date_encoded',
  'date_modified'
];

$message = '';
$imported_data = []; // Store imported records for display

if (isset($_POST['import'])) {
  if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');

    if ($handle !== false) {
      $csv_header = fgetcsv($handle, 2000, ","); // Read CSV header
      if (!$csv_header) {
        $message = "CSV file is empty or invalid.";
      } else {
        $inserted = 0;
        $skipped = 0;

        // Map CSV header to database fields
        $header_map = [];
        foreach ($csv_header as $i => $col) {
          $col = strtolower(trim(str_replace(' ', '_', $col)));
          if (in_array($col, $all_fields))
            $header_map[$i] = $col;
        }

        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
          $row = [];
          foreach ($header_map as $i => $field) {
            $raw = isset($data[$i]) ? $data[$i] : '';
            // Only normalize author column per request
            if ($field === 'author') {
              $raw = format_author($raw);
            }
            $row[$field] = $conn->real_escape_string($raw);
          }

          // Skip if access_code is missing
          if (empty($row['access_code'])) {
            $skipped++;
            continue;
          }

          // Check if access_code already exists
          $access_code = $row['access_code'];
          $stmt_exists = $conn->prepare("SELECT 1 FROM document_records WHERE access_code = ? LIMIT 1");
          $stmt_exists->bind_param('s', $access_code);
          $stmt_exists->execute();
          $stmt_exists->store_result();
          if ($stmt_exists->num_rows > 0) {
            $skipped++;
            $stmt_exists->close();
            continue;
          }
          $stmt_exists->close();

          // Ensure date_created has value
          if (empty($row['date_created']))
            $row['date_created'] = date('Y-m-d H:i:s');

          // Insert using prepared statement with dynamic placeholders
          $columns = array_keys($row);
          $placeholders = implode(',', array_fill(0, count($columns), '?'));
          $insert_sql = "INSERT INTO document_records (" . implode(',', $columns) . ") VALUES ($placeholders)";
          $stmt_ins = $conn->prepare($insert_sql);
          if ($stmt_ins === false) {
            $skipped++;
            continue;
          }

          $types = str_repeat('s', count($columns));
          $bind_values = array_values($row);
          $bind_names = array_merge([$types], $bind_values);
          $refs = [];
          foreach ($bind_names as $k => $v) {
            $refs[$k] = &$bind_names[$k];
          }
          call_user_func_array([$stmt_ins, 'bind_param'], $refs);
          if ($stmt_ins->execute()) {
            $inserted++;
            $imported_data[] = $row; // Store successfully imported row
          } else {
            $skipped++;
          }
          $stmt_ins->close();
        }

        fclose($handle);
        $message = "✅ Import completed — $inserted records added, $skipped skipped (duplicates or missing data).";
      }
    } else {
      $message = "Unable to open file.";
    }
  } else {
    $message = "No file selected or upload error.";
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Import Files — Library System</title>
  <link rel="icon" href="z_Images\A_WebIcon.png" type="image/x-icon">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="Import_style.css">
</head>

<body>
  <!-- === NAVIGATION BAR === -->
  <nav class="menu-bar">
    <div class="logo">
      <a href="index.php">
        <img src="z_Images/logoNavUi.png" alt="Library Logo" style="height:54px; vertical-align:middle;">
      </a>
    </div>
    <ul class="menu-items">
      <li class="dropdown">
        <a href="javascript:void(0)"><i class='bx bx-menu-alt-right'></i></a>
        <ul class="dropdown-menu">
          <li><a href="index.php">Home</a></li>
          <li><a href="Searchpage.php">Search</a></li>

          <?php if ($role === 'admin'): ?>
            <li><a href="manage_users.php">Manage Users</a></li>
            <li><a href="Import.php">Import Files</a></li>
          <?php endif; ?>

          <li><a href="Export.php">Export Files</a></li>

          <?php if ($role === 'admin' || $role === 'encoder'): ?>
            <li><a href="history.php">History</a></li>
            <li><a href="Trash_bin.php">Trash Bin</a></li>
          <?php endif; ?>

          <?php if (isset($_SESSION['username'])): ?>
            <li><a href="logout.php">Log Out</a></li>
          <?php else: ?>
            <li><a href="LogInpage.php">Log In</a></li>
          <?php endif; ?>

        </ul>
      </li>
    </ul>
  </nav>

  <!-- === MAIN CONTENT === -->
  <div class="container">
    <h2>📥 Import CSV File</h2>
    <p class="subtitle">Upload your CSV file to import records into the Library Database.<br>Duplicate Access Codes will
      be skipped automatically.</p>

    <form method="post" enctype="multipart/form-data">
      <div class="upload-area">
        <i class='bx bx-cloud-upload'></i>
        <p><b>Drag and drop</b> your CSV file here<br>or click below to browse</p>
        <input type="file" name="csv_file" accept=".csv" required style="margin-top: 10px;">
      </div>
      <button type="submit" name="import"><i class='bx bx-upload' style="vertical-align:middle;"></i> Upload &
        Import</button>
    </form>

    <?php if ($message): ?>
      <div class="message success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($imported_data)): ?>
      <div class="table-container">
        <h3 style="margin:15px 0;color:#e65100;">✅ Imported Records</h3>
        <table>
          <thead>
            <tr>
              <?php foreach (array_keys($imported_data[0]) as $col): ?>
                <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($imported_data as $row): ?>
              <tr>
                <?php foreach ($row as $val): ?>
                  <td><?= htmlspecialchars($val) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>