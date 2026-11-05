<?php

include("connect.php");

//------------------------------ Log In -----------------------------------//

if (isset($_POST["logInBtn"])) {
    $username = $_POST["username"];
    $password = $_POST["password"];
    $password = md5($password);

    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        session_start();
        $row = $result->fetch_assoc();
        $_SESSION["password"] = $row["password"];
        $_SESSION["username"] = $row["username"];
        // set role from database if available; normalize to lowercase; default to 'viewer'
        $_SESSION['role'] = isset($row['role']) && !empty($row['role']) ? strtolower($row['role']) : 'viewer';

        header("location: Searchpage.php");
        exit();
    } else {

        echo "<script type='text/javascript'> 
        window.alert('Either the username or password is incorrect!');
        window.location.href='LogInpage.php';
        </script>";
        exit();
    }
}
?>