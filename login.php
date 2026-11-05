<?php
include 'connect.php';
include 'log_activity.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'];
    $password = $_POST['password'];

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND password=?");
    $stmt->bind_param("ss", $username, $password); // currently plain text password check
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Successful login: set session variables
        $_SESSION['user_id'] = $result->fetch_assoc()['id']; // store user id
        $_SESSION['username'] = $username;

        // Redirect to Searchpage.php
        echo "<script>alert('Login successful'); window.location='Searchpage.php';</script>";
        exit();
    } else {
        echo "<script>alert('Invalid credentials'); window.location='LogInpage.php';</script>";
        exit();
    }
}
?>