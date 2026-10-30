<?php
require_once '../../includes/db.php';
require_once '../../includes/pdf-report.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Get report type
$type = isset($_GET['type']) ? sanitize($conn, $_GET['type']) : 'summary';

// Validate report type
$validTypes = ['summary', 'revenue', 'users'];
if (!in_array($type, $validTypes)) {
    $type = 'summary';
}

// Generate appropriate report
generateAdminReport($conn, $type);
?>
