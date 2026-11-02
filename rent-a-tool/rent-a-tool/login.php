<?php
require_once 'includes/db.php';

$error = '';
$lockout_message = '';
$penalty_warning = '';

// Check if user is already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('pages/admin/dashboard.php');
    } else {
        redirect('pages/user/dashboard.php');
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if account is locked
        if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
            $lockout_time = date('g:i A', strtotime($user['lockout_until']));
            $lockout_message = "Account temporarily locked. Please try again after $lockout_time.";
        } 
        // Check if account is banned
        elseif ($user['status'] === 'banned') {
            $error = "Your account has been banned due to multiple failed login attempts. Please contact support.";
        }
        // Verify password
        elseif (password_verify($password, $user['password'])) {
            // Successful login - reset attempts
            $stmt = $conn->prepare("UPDATE users SET login_attempts = 0, lockout_until = NULL WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            // Log successful login
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 1)");
            $stmt->bind_param("ss", $email, $ip);
            $stmt->execute();
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            // Log activity
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'Login', 'User logged in successfully', ?)");
            $stmt->bind_param("is", $user['id'], $ip);
            $stmt->execute();
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                redirect('pages/admin/dashboard.php');
            } else {
                redirect('pages/user/dashboard.php');
            }
        } else {
            // Failed login - increment attempts
            $new_attempts = $user['login_attempts'] + 1;
            $lockout_until = null;
            $status = $user['status'];
            
            // Apply penalties based on attempt count
            if ($new_attempts >= 3) {
                // Lock account for 30 minutes
                $lockout_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $new_attempts = 0; // Reset attempts after lockout
                
                // Increment penalty count
                $new_penalty = $user['penalty_count'] + 1;
                
                // Ban user after 3 penalties (9 failed attempts total)
                if ($new_penalty >= 3) {
                    $status = 'banned';
                }
                
                $stmt = $conn->prepare("UPDATE users SET login_attempts = ?, lockout_until = ?, penalty_count = ?, status = ? WHERE id = ?");
                $stmt->bind_param("issis", $new_attempts, $lockout_until, $new_penalty, $status, $user['id']);
                $stmt->execute();
                
                $lockout_message = "Too many failed attempts. Your account is locked for 30 minutes.";
                if ($status === 'banned') {
                    $error = "Your account has been permanently banned due to excessive failed login attempts.";
                    $lockout_message = '';
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_attempts, $user['id']);
                $stmt->execute();
                
                $remaining = 3 - $new_attempts;
                $error = "Invalid password. $remaining attempt(s) remaining before temporary lockout.";
                
                if ($user['penalty_count'] > 0) {
                    $penalty_warning = "Warning: You have {$user['penalty_count']} penalty(s). After 3 penalties, your account will be banned.";
                }
            }
            
            // Log failed attempt
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $email, $ip);
            $stmt->execute();
        }
    } else {
        $error = "Email not found. Please check your email or register.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rent-A-Tool</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box fade-in">
            <div class="login-header">
                <div class="logo" style="justify-content: center; color: white; margin-bottom: 1rem;">
                    <i class="bi bi-tools"></i>
                    <span>Rent-A-Tool</span>
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to access your account</p>
            </div>
            
            <div class="login-body">
                <?php if ($lockout_message): ?>
                    <div class="lockout-message">
                        <i class="bi bi-lock-fill"></i>
                        <strong>Account Locked</strong>
                        <p style="margin: 0.5rem 0 0; font-size: 0.875rem;"><?php echo $lockout_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($penalty_warning): ?>
                    <div class="penalty-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Account Penalty Warning</strong>
                            <p style="margin: 0; font-size: 0.875rem;"><?php echo $penalty_warning; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                        <label class="form-label" style="margin: 0; display: flex; align-items: center; gap: 0.5rem; font-weight: normal;">
                            <input type="checkbox" style="width: auto;"> Remember me
                        </label>
                        <a href="forgot-password.php" style="font-size: 0.875rem;">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Sign In
                    </button>
                </form> 
            <div class="login-footer">
                <p>Don't have an account? <a href="register.php">Create one now</a></p>
            </div>
        </div>
    </div>
</body>
</html>
