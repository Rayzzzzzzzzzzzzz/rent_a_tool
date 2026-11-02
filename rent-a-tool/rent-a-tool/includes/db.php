<?php

$host = 'localhost';
$username = 'root';      
$password = '';          
$database = 'rentatool';

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");

// Session configuration
session_start();

// Base URL 
$base_url = 'http://localhost/rent-a-tool';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to redirect
function redirect($path) {
    global $base_url;
    header("Location: $base_url/$path");
    exit();
}

// Function to sanitize input
function sanitize($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

// Function to show alert and redirect
function alert($message, $redirect = null) {
    echo "<script>alert('$message');</script>";
    if ($redirect) {
        echo "<script>window.location.href='$redirect';</script>";
        exit();
    }
}

// Function to calculate late fee
function calculateLateFee($returnDate, $dailyFine, $conn) {
    $currentDate = date('Y-m-d');
    $return = new DateTime($returnDate);
    $current = new DateTime($currentDate);
    
    if ($current > $return) {
        $diff = $current->diff($return);
        $daysLate = $diff->days;
        return $daysLate * $dailyFine;
    }
    return 0;
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to get status badge color
function getStatusColor($status) {
    switch ($status) {
        case 'available': return 'success';
        case 'rented': return 'warning';
        case 'under_repair': return 'danger';
        case 'pending': return 'info';
        case 'completed': return 'success';
        case 'overdue': return 'danger';
        default: return 'secondary';
    }
}
?>
