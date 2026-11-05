<?php
session_start();
include("connect.php");

// Check if the user is logged in
if (!isset($_SESSION["username"])) {
  echo "<script type='text/javascript'>
        alert('You must log in first to access this page!');
        window.location.href='LogInpage.php';
    </script>";
  exit();
}

// Get logged-in user ID
$username = $_SESSION['username'];
$stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt_user->bind_param('s', $username);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user = $res_user->fetch_assoc();
$user_id = $user['id'];
$stmt_user->close();
// Role from session (set at login)
$role = strtolower($_SESSION['role'] ?? 'viewer');

// (Undo/Redo feature removed per client request)
// ✅ Handle search parameters
$search_field = $_GET['field'] ?? 'all';
$search_query = trim($_GET['query'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Library Database Records</title>
  <link rel="stylesheet" href="Searchpage_style.css?v=2">

  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

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


  <h2 class="page-title">Library Database Records</h2>

  <div class="top-bar">
    <div class="controls">
      <select id="searchField">
        <option value="all" <?= ($search_field == 'all') ? 'selected' : '' ?>>All Fields</option>
        <option value="access_code" <?= ($search_field == 'access_code') ? 'selected' : '' ?>>Access Code</option>
        <option value="author" <?= ($search_field == 'author') ? 'selected' : '' ?>>Author</option>
        <option value="department" <?= ($search_field == 'department') ? 'selected' : '' ?>>Department</option>
        <option value="title" <?= ($search_field == 'title') ? 'selected' : '' ?>>Title</option>
        <option value="type" <?= ($search_field == 'type') ? 'selected' : '' ?>>Type</option>
        <optgroup label="Other Fields">
          <option value="addressee" <?= ($search_field == 'addressee') ? 'selected' : '' ?>>Addressee</option>
          <option value="accountable_officer" <?= ($search_field == 'accountable_officer') ? 'selected' : '' ?>>Accountable
            Officer</option>
          <option value="amount_due" <?= ($search_field == 'amount_due') ? 'selected' : '' ?>>Amount Due</option>
          <option value="attachment" <?= ($search_field == 'attachment') ? 'selected' : '' ?>>Attachment</option>
          <option value="date_created" <?= ($search_field == 'date_created') ? 'selected' : '' ?>>Date Created</option>
          <option value="description" <?= ($search_field == 'description') ? 'selected' : '' ?>>Description</option>
          <option value="document_number" <?= ($search_field == 'document_number') ? 'selected' : '' ?>>Document Number
          </option>
          <option value="notes_keywords" <?= ($search_field == 'notes_keywords') ? 'selected' : '' ?>>Notes/Keywords
          </option>
          <option value="property_number" <?= ($search_field == 'property_number') ? 'selected' : '' ?>>Property Number
          </option>
          <option value="record_status" <?= ($search_field == 'record_status') ? 'selected' : '' ?>>Record Status</option>
          <option value="retention_period" <?= ($search_field == 'retention_period') ? 'selected' : '' ?>>Retention Period
          </option>
          <option value="sender" <?= ($search_field == 'sender') ? 'selected' : '' ?>>Sender</option>
          <option value="source_funds" <?= ($search_field == 'source_funds') ? 'selected' : '' ?>>Source of Funds
          </option>
          <option value="status" <?= ($search_field == 'status') ? 'selected' : '' ?>>Status</option>
          <option value="supplier" <?= ($search_field == 'supplier') ? 'selected' : '' ?>>Supplier</option>
          <option value="total_cost" <?= ($search_field == 'total_cost') ? 'selected' : '' ?>>Total Cost</option>
        </optgroup>
      </select>

      <input type="text" id="searchInput" placeholder="🔍 Search records..." onkeyup="filterTable()"
        value="<?= htmlspecialchars($search_query) ?>">
      <select id="sortOrder" onchange="sortTable()">
        <option value="asc">Sort by Date ↑</option>
        <option value="desc">Sort by Date ↓</option>
      </select>
      <select id="sortOrder2" onchange="sortTable2()">
        <option value="">All Libraries</option> <!-- default -->
        <option value="lib1">Library 1</option>
        <option value="lib2">Library 2</option>
      </select>

      <div class="rows-container">
        <label class="Rows">Rows per page:</label>
        <select id="rowsPerPage" onchange="updateRowsPerPage()">
          <option value="5">5</option>
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>

      <!-- Allow all logged-in users (including viewers) to toggle multiple-select -->
      <label id="multipleSelect"><input type="checkbox" id="selectionModeCheckbox" onchange="toggleSelectionMode()">
        Multiple Select</label>
    </div>

    <?php if ($role === 'admin' || $role === 'encoder'): ?>
      <button class="add-btn" onclick="openAddModal()">➕ Add Record</button>
    <?php endif; ?>
    <!-- Save selected should be available to viewers as well; delete selected remains restricted -->
    <button id="savedSelectedBtn" class="saved-btn" onclick="savedSelected()" style="display:none;">Save
      Selected</button>
    <?php if ($role === 'admin' || $role === 'encoder'): ?>
      <button id="deleteSelectedBtn" class="delete-btn" onclick="deleteSelected()" style="display:none;">Delete
        Selected</button>
    <?php endif; ?>



  </div>

  <div class="table-container">
    <table id="recordsTable">
      <thead id="tableHeader">
        <tr>
          <th>Actions <input type="checkbox" id="selectAllCheckbox" style="display:none;" onchange="selectAll()"></th>
          <th>Access Code</th>
          <th>Location Code</th>
          <th>Author</th>
          <th>Department</th>
          <th>Title</th>
          <th>Type</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <!-- Records will be loaded via AJAX -->
      </tbody>
    </table>
  </div>
  <div id="pagination" class="pagination"></div>

  <!-- Modal -->
  <div id="recordModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Record Details</h3>
        <button type="button" class="modal-close" onclick="closeModal()">✖</button>
      </div>

      <form id="recordForm" action="save_edit_record.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="record_id" id="record_id">
        <input type="hidden" name="action" id="action" value="add">
        <input type="hidden" name="existing_attachment" id="existing_attachment" value="">

        <div class="form-scroll">
          <div class="form-group">
            <label>Access Code:</label>
            <input type="text" id="access_code" name="access_code" required>

            <label>Library:</label>
            <select id="library" name="library">
              <option value="">Select Library</option>
              <option value="E1">E1</option>
              <option value="E2">E2</option>
            </select>

            <label>Location Code:</label>
            <input type="text" id="location_code" name="location_code">

            <label>Author:</label>
            <input type="text" id="author" name="author">

            <label>Department:</label>
            <select id="department" name="department">
              <option value="">Select Department</option>
              <option value="Acquisition Section">Acquisition Section</option>
              <option value="Admin Section">Admin Section</option>
              <option value="Archives and Records Center">Archives and Records Center</option>
              <option value="Cataloging Section">Cataloging Section</option>
              <option value="Circulation Section">Circulation Section</option>
              <option value="Dean Office">Dean Office</option>
              <option value="Information Services">Information Services</option>
              <option value="ISNS">ISNS</option>
              <option value="OCS">OCS</option>
              <option value="OHL">OHL</option>
              <option value="Serials Section">Serials Section</option>
              <option value="The Learning Commons">The Learning Commons</option>
              <option value="Theses Section">Theses Section</option>
              <option value="Others">Others</option>
            </select>

            <label>Title:</label>
            <textarea id="title" name="title" rows="2"></textarea>

            <label>Type:</label>
            <input type="text" id="type" name="type">

            <label>Record Status:</label>
            <select id="record_status" name="record_status">
              <option value="">Select Status</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>

            <label>Retention Period:</label>
            <input type="text" id="retention_period" name="retention_period">

            <label>Description:</label>
            <textarea id="description" name="description" rows="3"></textarea>

            <label>Status:</label>
            <input type="text" id="status" name="status">

            <label>Document Number:</label>
            <input type="text" id="document_number" name="document_number">

            <label>Supplier:</label>
            <input type="text" id="supplier" name="supplier">

            <label>Amount Due:</label>
            <input type="text" id="amount_due" name="amount_due">

            <label>Notes/Keywords:</label>
            <textarea id="notes_keywords" name="notes_keywords" rows="2"></textarea>

            <label>Addressee:</label>
            <input type="text" id="addressee" name="addressee">

            <label>Accountable Officer:</label>
            <input type="text" id="accountable_officer" name="accountable_officer">

            <label>Sender:</label>
            <input type="text" id="sender" name="sender">

            <label>Source of Funds:</label>
            <input type="text" id="source_funds" name="source_funds">

            <label>Property Number:</label>
            <input type="text" id="property_number" name="property_number">

            <label>Total Cost:</label>
            <input type="text" id="total_cost" name="total_cost">

            <label>Attachments:</label>
            <!-- Allow selecting multiple files -->
            <?php if ($role === 'admin' || $role === 'encoder'): ?>
              <div class="attachment-control">
                <input type="file" id="attachment_file" name="attachment_files[]" accept="*/*" multiple
                  style="display:none;">
                <span id="selectedFiles" style="margin-left:8px; font-size:0.95em; color:#333;"></span>
              </div>
            <?php endif; ?>
            <!-- Display current attachments as a table when viewing/editing existing record -->
            <div id="attachmentDisplay" style="margin-top:6px;">
              <table id="attachmentsTable" class="attachments-table" style="display:none; width:100%;">
                <thead>
                  <tr>
                    <th style="width:60%">Filename</th>
                    <th style="width:25%">Uploaded</th>
                    <th style="width:15%">Actions</th>
                  </tr>
                </thead>
                <tbody id="attachmentsTableBody"></tbody>
              </table>
              <div id="attachmentMessage" class="attachment-msg" style="display:none; margin-top:8px;"></div>
            </div>

            <label>Date Created:</label>
            <input type="date" id="date_created" name="date_created">
          </div>
        </div>

        <div class="modal-buttons">
          <?php if ($role === 'admin' || $role === 'encoder'): ?>
            <button id="enableEditBtn" type="button" onclick="enableEditing()" style="display:none;">Enable Edit</button>
            <button id="saveModalBtn" type="submit" class="save-btn" style="display:none;">Save</button>
          <?php endif; ?>

          <button type="button" class="cancel-btn" onclick="closeModal()">Close</button>
        </div>

      </form>
    </div>
  </div>
  <script>
    // expose current user's role to front-end
    window.userRole = '<?php echo $role; ?>';
  </script>
  <script src="Searchpage_script.js"></script>

</body>

</html>