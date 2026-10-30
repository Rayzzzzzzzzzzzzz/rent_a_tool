<?php
require_once 'includes/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($conn, $_POST['email']);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // In a real application, you would:
        // 1. Generate a unique reset token
        // 2. Store it in the database with an expiration time
        // 3. Send an email with a reset link
        
        // For this demo, we'll just show a message
        $message = "If an account exists with this email, password reset instructions have been sent. Please check your inbox.";
        
        // Log the password reset request
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'Password Reset Request', 'Password reset requested', ?)");
        $stmt->bind_param("is", $user['id'], $ip);
        $stmt->execute();
    } else {
        // Don't reveal if email exists or not for security
        $message = "If an account exists with this email, password reset instructions have been sent. Please check your inbox.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Rent-A-Tool</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box fade-in" style="max-width: 420px;">
            <div class="login-header">
                <div class="logo" style="justify-content: center; color: white; margin-bottom: 1rem;">
                    <i class="bi bi-tools"></i>
                    <span>Rent-A-Tool</span>
                </div>
                <h1>Forgot Password</h1>
                <p>Reset your password</p>
            </div>
            
            <div class="login-body">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        <?php echo $message; ?>
                    </div>
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <p style="color: var(--gray-600); margin-bottom: 1.5rem;">
                        Enter your email address and we'll send you instructions to reset your password.
                    </p>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            <i class="bi bi-send"></i> Send Reset Link
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="login-footer">
                <p>Remember your password? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>
</body>
</html>
