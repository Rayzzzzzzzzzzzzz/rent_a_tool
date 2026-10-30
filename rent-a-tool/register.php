<?php
require_once 'includes/db.php';

$error = '';
$success = '';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($conn, $_POST['fullname']);
    $email = sanitize($conn, $_POST['email']);
    $phone = sanitize($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = sanitize($conn, $_POST['address']);
    
    // Validation
    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already registered. Please use a different email or login.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, password, address, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->bind_param("sssss", $fullname, $email, $phone, $hashed_password, $address);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'Registration', 'New user registered', ?)");
                $stmt->bind_param("is", $user_id, $ip);
                $stmt->execute();
                
                $success = "Registration successful! Please login with your credentials.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Rent-A-Tool</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box fade-in" style="max-width: 500px;">
            <div class="login-header">
                <div class="logo" style="justify-content: center; color: white; margin-bottom: 1rem;">
                    <i class="bi bi-tools"></i>
                    <span>Rent-A-Tool</span>
                </div>
                <h1>Create Account</h1>
                <p>Join our community of tool renters</p>
            </div>
            
            <div class="login-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Login
                        </a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="fullname" class="form-control" placeholder="Enter your full name" required
                                       value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number"
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Enter your address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Create a password (min 6 chars)" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-weight: normal; font-size: 0.875rem; color: var(--gray-600);">
                                <input type="checkbox" required style="width: auto; margin-top: 0.25rem;">
                                <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            <i class="bi bi-person-plus"></i>
                            Create Account
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="login-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>
</body>
</html>
