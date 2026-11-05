<?php
session_start();
include("connect.php");

// ✅ Redirect if user is already logged in
if (isset($_SESSION["username"])) {
    echo "<script type='text/javascript'>
        alert('You are currently logged in. Please log out first to proceed!');
        window.location.href='Searchpage.php';
    </script>";
    exit();
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Records Library - Log in</title>

    <!-- 🌐 Website Icon -->
    <link rel="icon" id="WebIcon" href="z_Images/A_WebIcon.png" type="image/png">

    <!-- 🎨 CSS Files -->
    <link rel="stylesheet" href="logInStyle.css">
</head>

<body>
    <!-- 🔶 Background Overlay -->
    <div id="backgroundOverlay">
        <div id="logInContainer">

            <!-- 🧭 Logo Section -->
            <div id="menuDiv">
                <a id="imgHomeLink" href="index.php">
                    <img id="logoNavUI" class="logo" src="z_Images/LogoNavUI.png" alt="Records Library Logo">
                </a>
            </div>

            <!-- 🔐 Login Form Section -->
            <div id="logInDiv">
                <div id="loginFormDiv">
                    <form id="logInForm" method="post" action="regstr_login.php">
                        <div class="inputGroup">
                            <label class="inputLabel" for="username">Username:</label>
                            <input type="text" id="username" name="username" placeholder="Username" required>
                        </div>

                        <div class="inputGroup">
                            <label class="inputLabel" for="password">Password:</label>
                            <input type="password" id="password" name="password" placeholder="Password" required>
                        </div>

                        <input type="submit" id="logInBtn" name="logInBtn" value="Log In">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="NavigationProfile_UI.js"></script>
</body>

</html>