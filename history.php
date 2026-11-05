<?php
session_start();
include("connect.php");
include_once 'rolePermissionHandler.php';

// Only admins may view history
is_modifier();

// ✅ Define $role to prevent "undefined variable" warnings
$role = $_SESSION['role'] ?? '';

// Define available fields and default visible columns (matches history table layout)
$fields = [
  'record_id' => 'Record ID',
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
  'attachments' => 'Attachments',
  'status' => 'Status',
  'date_created' => 'Date Created',
  'action' => 'Action',
  'encoder' => 'Encoder',
  'timestamp' => 'Timestamp'
];


// sensible defaults: show the most important fields by default
$default_columns = ['record_id', 'access_code', 'title', 'type', 'action', 'encoder', 'timestamp'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Activity History</title>
  <link rel="icon" href="z_Images\A_WebIcon.png" type="image/x-icon">
  <link rel="stylesheet" href="history_style.css">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>

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

  <h2 class="page-title">Activity History</h2>

  <div class="field-dropdown" id="fieldDropdown">
    <button type="button">Select Fields to Display</button>
    <div class="field-dropdown-content">
      <div class="field-controls" style="padding:6px 8px; border-bottom:1px solid #eee; display:flex; gap:8px;">
        <button type="button" id="selectAllFields" class="small-btn">Select All</button>
        <button type="button" id="resetDefaults" class="small-btn">Reset Defaults</button>
      </div>
      <?php foreach ($fields as $key => $label): ?>
        <label>
          <input type="checkbox" class="display-field" data-field="<?php echo $key; ?>"
            data-default="<?php echo in_array($key, $default_columns) ? '1' : '0'; ?>" <?php echo in_array($key, $default_columns) ? 'checked' : ''; ?>> <?php echo $label; ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="table-container">
    <table id="historyTable">
      <thead>
        <tr>
          <?php foreach ($fields as $key => $label): ?>
            <th data-field="<?php echo $key; ?>" <?php echo in_array($key, $default_columns) ? '' : 'style="display:none;"'; ?>><?php echo $label; ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $result = $conn->query("SELECT * FROM activity_history WHERE action NOT IN ('Login', 'Logout') ORDER BY timestamp DESC");
        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            // Build attachments cell: support legacy single-attachment fields, JSON arrays, and the new record_attachments table
            $attachments_html = '';

            // 1) If the history row contains a JSON-encoded 'attachments' column, prefer that
            if (!empty($row['attachments'])) {
              $decoded = json_decode($row['attachments'], true);
              if (is_array($decoded)) {
                $parts = [];
                foreach ($decoded as $att) {
                  if (is_array($att)) {
                    // expected keys: id, file_path, original_name
                    $href = '';
                    $text = htmlspecialchars($att['original_name'] ?? $att['file_path'] ?? '');
                    if (!empty($att['id'])) {
                      $href = 'download_attachment.php?attachment_id=' . intval($att['id']);
                    } elseif (!empty($att['file_path'])) {
                      $href = htmlspecialchars($att['file_path']);
                    }
                    if ($href) {
                      $parts[] = '<a href="' . $href . '" target="_blank">' . $text . '</a>';
                    } else {
                      $parts[] = $text;
                    }
                  } else {
                    // string entry
                    $text = htmlspecialchars(basename((string) $att));
                    $parts[] = $text;
                  }
                }
                $attachments_html = implode('<br>', $parts);
              }
            }

            // 2) Fall back to legacy single attachment fields if nothing found yet
            if ($attachments_html === '') {
              if (!empty($row['attachment_name'])) {
                $attachments_html = htmlspecialchars($row['attachment_name']);
              } elseif (!empty($row['attachment'])) {
                $attachments_html = '<a href="download_attachment.php?record_id=' . intval($row['record_id']) . '" target="_blank">' . htmlspecialchars(basename($row['attachment'])) . '</a>';
              }
            }

            // Live `record_attachments` lookups intentionally disabled.
            // History should display only what is stored on the activity_history row
            // (the `attachments` JSON snapshot) or the legacy `attachment` / `attachment_name` fields.
        
            // final fallback
            if ($attachments_html === '')
              $attachments_html = '';

            // Output each column in the canonical fields order. Special-case attachments.
            echo "<tr>\n";
            foreach ($fields as $col_key => $col_label) {
              // add data-field attribute so JS can target the td directly (keeps th/td alignment robust)
              echo "<td data-field=\"" . htmlspecialchars($col_key, ENT_QUOTES) . "\">";
              if ($col_key === 'attachments') {
                echo ($attachments_html !== '' ? $attachments_html : '');
              } else {
                echo htmlspecialchars($row[$col_key] ?? '');
              }
              echo "</td>";
            }
            echo "\n</tr>";
          }
        } else {
          // Use dynamic colspan equal to number of configured fields
          $colspan = count($fields);
          echo "<tr><td colspan='" . intval($colspan) . "' style='text-align:center;'>No activity found</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>


  <script src="history_script.js"></script>
</body>

</html>