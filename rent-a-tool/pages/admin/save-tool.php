<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tool_id = isset($_POST['tool_id']) ? intval($_POST['tool_id']) : 0;
    $name = sanitize($conn, $_POST['name']);
    $category_id = intval($_POST['category_id']);
    $description = sanitize($conn, $_POST['description']);
    $daily_rate = floatval($_POST['daily_rate']);
    $security_deposit = floatval($_POST['security_deposit']);
    $late_fee_per_day = floatval($_POST['late_fee_per_day']);
    $owner_id = intval($_POST['owner_id']);
    $location = sanitize($conn, $_POST['location']);
    $condition_notes = sanitize($conn, $_POST['condition_notes']);
    
    if ($tool_id > 0) {
        // Update existing tool
        $stmt = $conn->prepare("
            UPDATE tools SET 
                name = ?, category_id = ?, description = ?, daily_rate = ?, 
                security_deposit = ?, late_fee_per_day = ?, owner_id = ?, 
                location = ?, condition_notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "sisddddssi",
            $name, $category_id, $description, $daily_rate,
            $security_deposit, $late_fee_per_day, $owner_id,
            $location, $condition_notes, $tool_id
        );
        
        if ($stmt->execute()) {
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $admin_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Tool Updated', 'Tool #$tool_id updated', ?)
            ");
            $stmt->bind_param("is", $admin_id, $ip);
            $stmt->execute();
            
            alert("Tool updated successfully!", "tools.php");
        } else {
            alert("Failed to update tool.", "tools.php");
        }
    } else {
        // Add new tool
        $stmt = $conn->prepare("
            INSERT INTO tools (
                name, category_id, description, daily_rate, security_deposit, 
                late_fee_per_day, owner_id, location, condition_notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
        ");
        $stmt->bind_param(
            "sisddddss",
            $name, $category_id, $description, $daily_rate, $security_deposit,
            $late_fee_per_day, $owner_id, $location, $condition_notes
        );
        
        if ($stmt->execute()) {
            $new_tool_id = $stmt->insert_id;
            
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $admin_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Tool Created', 'Tool #$new_tool_id created', ?)
            ");
            $stmt->bind_param("is", $admin_id, $ip);
            $stmt->execute();
            
            alert("Tool added successfully!", "tools.php");
        } else {
            alert("Failed to add tool.", "tools.php");
        }
    }
} else {
    redirect('tools.php');
}
?>
