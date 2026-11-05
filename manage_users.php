<?php
include_once 'rolePermissionHandler.php';
require_admin();
include_once 'connect.php';

$role = $_SESSION['role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Users</title>
  <link rel="icon" href="z_Images\A_WebIcon.png" type="image/x-icon">
  <link rel="stylesheet" href="manage_users_style.css">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
  <!-- Top Menu Bar -->
  <nav class="menu-bar">
    <div class="logo">
      <a href="index.php">
        <img src="z_Images/logoNavUi.png" alt="Library Logo" style="height:44px; vertical-align:middle;">
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

  <main class="page-container">
    <header class="page-header">
      <h2>Manage Users</h2>
      <div class="actions">
        <button id="openCreate" class="btn primary">Create New User</button>
      </div>
    </header>

    <section class="content-card">
      <div class="table-wrap">
        <table id="usersTable" class="styled-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <!-- Create / Edit Modal -->
    <div id="userModal" class="modal" style="display:none;">
      <div class="modal-content card">
        <h3 id="modalTitle">Create User</h3>
        <form id="userForm" class="form-stack">
          <input type="hidden" name="id" id="userId">
          <label>Username</label>
          <input type="text" name="username" id="username" required>
          <label>Password</label>
          <input type="password" name="password" id="password" placeholder="Leave blank to keep current">
          <label>Role</label>
          <select name="role" id="roleSelect">
            <option value="viewer">Viewer</option>
            <option value="encoder">Encoder</option>
            <option value="admin">Admin</option>
          </select>
          <div class="modal-actions">
            <button type="submit" class="btn primary">Save</button>
            <button type="button" id="cancelBtn" class="btn">Cancel</button>
          </div>
        </form>
      </div>
    </div>

  </main>

  <script src="manage_users.js"></script>
</body>

</html>