<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($conn, $_POST['fullname']);
    $phone = sanitize($conn, $_POST['phone']);
    $address = sanitize($conn, $_POST['address']);
    
    // Handle password change
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($new_password) {
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, address = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $fullname, $phone, $address, $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Profile and password updated successfully!";
                $_SESSION['fullname'] = $fullname;
            } else {
                $error = "Failed to update profile.";
            }
        }
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $fullname, $phone, $address, $user_id);
        
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            $_SESSION['fullname'] = $fullname;
        } else {
            $error = "Failed to update profile.";
        }
    }
    
    // Refresh user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

$page_title = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Rent-A-Tool</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="../../index.php" class="logo">
                    <i class="bi bi-tools"></i>
                    <span>Rent-A-Tool</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="browse-tools.php">
                            <i class="bi bi-search"></i>
                            <span>Browse Tools</span>
                        </a>
                    </li>
                    <li>
                        <a href="my-rentals.php">
                            <i class="bi bi-calendar-check"></i>
                            <span>My Rentals</span>
                        </a>
                    </li>
                    <li>
                        <a href="deposits.php">
                            <i class="bi bi-shield-lock"></i>
                            <span>My Deposits</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="active">
                            <i class="bi bi-person"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li style="margin-top: 2rem;">
                        <a href="../../logout.php" style="color: var(--danger);">
                            <i class="bi bi-box-arrow-left"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>My Profile</h1>
                <p>Manage your account settings</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                    <i class="bi bi-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 300px 1fr; gap: 2rem;">
                <!-- Profile Card -->
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <img src="../../assets/images/<?php echo $user['profile_image']; ?>" alt="" 
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem;">
                        <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($user['fullname']); ?></h3>
                        <p style="color: var(--gray-500); margin-bottom: 1rem;"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="badge badge-success">Active Member</span>
                        
                        <div style="margin-top: 2rem; text-align: left;">
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Member Since</span>
                                <span><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Account Status</span>
                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0;">
                                <span style="color: var(--gray-500);">Login Penalties</span>
                                <span><?php echo $user['penalty_count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-pencil"></i> Edit Profile</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <h4 style="margin-bottom: 1rem;">Personal Information</h4>
                            
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small style="color: var(--gray-500);">Email cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>

                            <hr style="margin: 2rem 0;">
                            
                            <h4 style="margin-bottom: 1rem;">Change Password</h4>
                            <p style="color: var(--gray-500); margin-bottom: 1rem;">Leave blank if you don't want to change your password</p>
                            
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
