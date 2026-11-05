<?php
session_start();
include("connect.php");
include_once 'rolePermissionHandler.php';

if (!isset($_SESSION['username'])) {
  echo "<script>alert('You must log in first!'); window.location='LogInpage.php';</script>";
  exit;
}

$username = $_SESSION['username'];
$stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt_user->bind_param('s', $username);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
if (!$res_user || $res_user->num_rows === 0)
  die("User not found.");
$user_id = (int) $res_user->fetch_assoc()['id'];
$stmt_user->close();

$role = $_SESSION['role'] ?? 'viewer';

$pk_col = 'record_id';

// Use prepared statement to fetch saved records for the user
$sql = "SELECT dr.*, s.id AS saved_row_id
  FROM `document_records` dr
  INNER JOIN `saved_records` s ON s.record_id = dr.`$pk_col`
  WHERE s.user_id = ?
  ORDER BY dr.date_created DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res)
  die("Query failed: " . $conn->error);
$stmt->close();

function e($v)
{
  return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$fields = [
  'access_code' => 'Access Code',
  'library' => 'Library',
  'location_code' => 'Location Code',
  'author' => 'Author',
  'department' => 'Department',
  'title' => 'Title',
  'type' => 'Type',
  'record_status' => 'Record Status',
  'retention_period' => 'Retention Period',
  'description' => 'Description',
  'document_number' => 'Document Number',
  'supplier' => 'Supplier',
  'amount_due' => 'Amount Due',
  'notes_keywords' => 'Notes/Keywords',
  'addressee' => 'Addressee',
  'accountable_officer' => 'Accountable Officer',
  'sender' => 'Sender',
  'source_funds' => 'Source of Funds',
  'property_number' => 'Property Number',
  'total_cost' => 'Total Cost',
  'attachment' => 'Attachment',
  'status' => 'Status',
  'date_created' => 'Date Created'
];

$default_columns = ['access_code', 'library', 'location_code', 'author', 'department', 'title', 'type'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Saved Files — Library</title>
  <link rel="icon" href="z_Images\A_WebIcon.png" type="image/x-icon">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
  <link rel="stylesheet" href="Export_style.css">

<body>

  <!-- Top Menu Bar -->
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
    </ul>
  </nav>

  <h2 class="page-title"></h2>

  <div class="top-bar no-print">
    <label><input type="checkbox" id="selectionModeCheckbox" onchange="toggleSelectionMode()"> Enable Selection
      Mode</label>
    <button id="deleteSelectedBtn" class="delete-btn" style="display:none;">Delete Selected</button>
    <button class="print-btn" onclick="window.print()">🖨️ Print</button>
    <div class="export-dropdown">
      <select id="exportFormat" onchange="exportData()">
        <option value="">Export as...</option>
        <option value="csv">CSV</option>
        <option value="excel">Excel</option>
        <option value="pdf">PDF</option>
      </select>
    </div>
  </div>

  <div class="field-dropdown no-print" id="fieldDropdown">
    <button type="button">Select Fields to Display</button>
    <div class="field-dropdown-content">
      <?php foreach ($fields as $key => $label): ?>
        <label>
          <input type="checkbox" class="display-field" data-field="<?php echo $key; ?>" <?php echo in_array($key, $default_columns) ? 'checked' : ''; ?>> <?php echo $label; ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="table-wrap">
    <table class="saved-table" id="savedTable">
      <thead>
        <tr>
          <th class="actions no-print">Action <input type="checkbox" id="selectAllCheckbox" style="display:none;"
              onchange="selectAll()"></th>
          <?php foreach ($fields as $f => $label): ?>
            <th data-field="<?php echo $f; ?>" <?php echo in_array($f, $default_columns) ? '' : 'style="display:none;"'; ?>>
              <?php echo $label; ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $res->fetch_assoc()):
          $record_pk = $row[$pk_col]; ?>
          <tr data-record-id="<?php echo e($record_pk); ?>">
            <td class="actions no-print">
              <input type="checkbox" class="select-checkbox" data-id="<?php echo (int) $record_pk; ?>"
                style="display:none;">
              <button class="bookmark-btn" onclick="toggleUnsave(<?php echo (int) $record_pk; ?>)"><i
                  class="bx bxs-bookmarks" title="Remove saved"></i></button>
            </td>
            <?php foreach ($fields as $f => $label): ?>
              <td data-field="<?php echo $f; ?>" <?php echo in_array($f, $default_columns) ? '' : 'style="display:none;"'; ?>>
                <p><?php echo e($row[$f] ?? '-'); ?></p>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <script src="Export_script.js"></script>
</body>

</html>