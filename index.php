<?php
session_start();
include("connect.php");

// ✅ Check if user is logged in
$isLoggedIn = isset($_SESSION['username']) && !empty($_SESSION['username']);

// ✅ Handle search parameters (if redirected)
$field = isset($_GET['field']) ? $_GET['field'] : 'all';
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Role from session (set at login)
$role = strtolower($_SESSION['role'] ?? 'viewer');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records Database</title>
    <link rel="icon" href="z_Images\A_WebIcon.png" type="image/x-icon">
    <link rel="stylesheet" href="home_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        .search-preview {
            position: absolute;
            top: 105%;
            left: 0;
            width: 100%;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            max-height: 250px;
            overflow-y: auto;
            z-index: 10;
            display: none;
        }

        .search-preview div {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
            font-size: 14px;
            color: #333;
            text-align: left;
        }

        .search-preview div:hover {
            background-color: #fff3e0;
        }

        .search-bar {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .search-bar input[type="text"] {
            width: 350px;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .search-bar input[type="text"]:focus {
            border-color: #ff9800;
            box-shadow: 0 0 4px rgba(255, 152, 0, 0.4);
        }

        .search-bar button {
            background-color: #ff9800;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: background-color 0.3s ease, transform 0.2s;
        }

        .search-bar button:hover {
            background-color: #e68900;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <header class="navbar">
        <div class="container nav-content">
            <h1 class="logo"><span>Records</span> Library</h1>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="Searchpage.php">Search</a></li>

                    <?php if ($role === 'admin'): ?>
                        <li><a href="history.php">History</a></li>
                        <li><a href="manage_users.php">Manage Users</a></li>
                    <?php endif; ?>

                    <li><a href="Export.php">Export Files</a></li>

                    <?php if ($role === 'admin'): ?>
                        <li><a href="Import.php">Import Files</a></li>
                    <?php endif; ?>

                    <?php if ($role === 'admin' || $role === 'encoder'): ?>
                        <li><a href="Trash_bin.php">Trash Bin</a></li>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['username'])): ?>
                        <li><a href="logout.php">Log Out</a></li>
                    <?php else: ?>
                        <li><a href="LogInpage.php">Log In</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>

        <section class="hero">
            <div class="overlay"></div>
            <div class="hero-content">
                <h2>Explore Records Securely</h2>
                <p>Search, manage, and review institutional records with confidence.</p>

                <form action="Searchpage.php" method="GET" class="search-bar" id="homeSearchForm" autocomplete="off">
                    <select name="field" id="searchField">
                        <option value="all" <?= $field == 'all' ? 'selected' : '' ?>>All Fields</option>
                        <option value="title" <?= $field == 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="author" <?= $field == 'author' ? 'selected' : '' ?>>Author</option>
                        <option value="department" <?= $field == 'department' ? 'selected' : '' ?>>Department</option>
                        <option value="access_code" <?= $field == 'access_code' ? 'selected' : '' ?>>Access Code</option>
                    </select>

                    <div style="position: relative;">
                        <input type="text" id="searchQuery" name="query" placeholder="🔍 Search records..."
                            value="<?= htmlspecialchars($query) ?>" required>
                        <div class="search-preview" id="searchPreview"></div>
                    </div>

                    <button type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="categories">
            <a href="Searchpage.php" class="category-box">
                <h3>Search</h3>
            </a>

            <?php if ($role === 'admin'): ?>
                <a href="history.php" class="category-box">
                    <h3>History</h3>
                </a>
            <?php endif; ?>

            <a href="Export.php" class="category-box">
                <h3>Export</h3>
            </a>

            <?php if ($role === 'admin' || $role === 'encoder'): ?>
                <a href="Import.php" class="category-box">
                    <h3>Import</h3>
                </a>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <a href="manage_users.php" class="category-box">
                    <h3>Manage Users</h3>
                </a>
            <?php endif; ?>

            <?php if ($role === 'admin' || $role === 'encoder'): ?>
                <a href="Trash_bin.php" class="category-box">
                    <h3>Trash Bin</h3>
                </a>
            <?php endif; ?>
        </section>

        <section class="about">
            <h2>Discover Archived Knowledge with <span>Records Database</span></h2>
            <p>
                Records Database provides access to documented meetings, conferences, and academic events from Library 1
                and
                Library 2.
                This resource is intended for authorized students, researchers, and institutional personnel to explore
                institutional reports, discussions, and records.
            </p>
            <p class="disclaimer">
                <em>Note: Access to Records Database is restricted to authorized users. All content is confidential and
                    intended
                    for internal use only.</em>
            </p>
        </section>

        <footer>
            <p>© 2025 Records Database. All Rights Reserved.</p>
        </footer>

        <script src="home_search.js"></script>
</body>

</html>