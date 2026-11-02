<?php
require_once '../../includes/db.php';
require_once '../../includes/pdf-report.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get report type
$type = isset($_GET['type']) ? sanitize($conn, $_GET['type']) : 'summary';
$rental_id = isset($_GET['rental_id']) ? intval($_GET['rental_id']) : null;

// Validate report type
$validTypes = ['summary', 'rental', 'deposits'];
if (!in_array($type, $validTypes)) {
    $type = 'summary';
}

// Generate appropriate report
switch ($type) {
    case 'rental':
        if ($rental_id) {
            generateUserRentalReport($conn, $user_id, $rental_id);
        } else {
            generateUserRentalReport($conn, $user_id);
        }
        break;
        
    case 'summary':
    default:
        generateUserRentalReport($conn, $user_id);
        break;
}
?>
