<?php
require_once 'includes/db.php';

// Log the logout activity if user was logged in
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'Logout', 'User logged out', ?)");
    $stmt->bind_param("is", $user_id, $ip);
    $stmt->execute();
}

// Clear all session data
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page
redirect('login.php');
?>
