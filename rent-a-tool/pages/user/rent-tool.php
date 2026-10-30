<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get tool ID
$tool_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get tool details
$stmt = $conn->prepare("
    SELECT t.*, c.name as category_name, u.fullname as owner_name 
    FROM tools t 
    LEFT JOIN categories c ON t.category_id = c.id 
    LEFT JOIN users u ON t.owner_id = u.id 
    WHERE t.id = ? AND t.status = 'available'
");
$stmt->bind_param("i", $tool_id);
$stmt->execute();
$tool = $stmt->get_result()->fetch_assoc();

if (!$tool) {
    redirect('browse-tools.php');
}

// Handle rental submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = sanitize($conn, $_POST['start_date']);
    $end_date = sanitize($conn, $_POST['end_date']);
    $pre_condition = sanitize($conn, $_POST['pre_condition']);
    
    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($start < $today) {
        $error = "Start date cannot be in the past.";
    } elseif ($end <= $start) {
        $error = "End date must be after start date.";
    } else {
        // Calculate rental details
        $diff = $start->diff($end);
        $total_days = $diff->days;
        $daily_rate = $tool['daily_rate'];
        $security_deposit = $tool['security_deposit'];
        $rental_fee = $total_days * $daily_rate;
        $total_amount = $rental_fee + $security_deposit;
        
        // Create rental record
        $stmt = $conn->prepare("
            INSERT INTO rentals (
                tool_id, renter_id, start_date, end_date, daily_rate, 
                security_deposit, total_days, rental_fee, total_amount, 
                pre_rental_condition, rental_status, deposit_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'held')
        ");
        $stmt->bind_param(
            "iissddiids",
            $tool_id, $user_id, $start_date, $end_date, $daily_rate,
            $security_deposit, $total_days, $rental_fee, $total_amount,
            $pre_condition
        );
        
        if ($stmt->execute()) {
            $rental_id = $stmt->insert_id;
            
            // Update tool status
            $stmt = $conn->prepare("UPDATE tools SET status = 'pending' WHERE id = ?");
            $stmt->bind_param("i", $tool_id);
            $stmt->execute();
            
            // Create payment record for deposit
            $stmt = $conn->prepare("
                INSERT INTO payments (rental_id, user_id, amount, payment_type, status) 
                VALUES (?, ?, ?, 'deposit', 'completed')
            ");
            $stmt->bind_param("iid", $rental_id, $user_id, $security_deposit);
            $stmt->execute();
            
            // Create payment record for rental
            $stmt = $conn->prepare("
                INSERT INTO payments (rental_id, user_id, amount, payment_type, status) 
                VALUES (?, ?, ?, 'rental', 'completed')
            ");
            $stmt->bind_param("iid", $rental_id, $user_id, $rental_fee);
            $stmt->execute();
            
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Rental Created', 'Rental #$rental_id created for tool: {$tool['name']}', ?)
            ");
            $stmt->bind_param("is", $user_id, $ip);
            $stmt->execute();
            
            $success = "Rental request submitted successfully! Your security deposit of " . formatCurrency($security_deposit) . " has been held.";
        } else {
            $error = "Failed to create rental. Please try again.";
        }
    }
}

$page_title = 'Rent Tool - ' . $tool['name'];
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
                        <a href="browse-tools.php" class="active">
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
                        <a href="profile.php">
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
            <!-- Breadcrumb -->
            <div style="margin-bottom: 1rem;">
                <a href="browse-tools.php" style="color: var(--gray-500);">
                    <i class="bi bi-arrow-left"></i> Back to Browse
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 2rem;">
                    <i class="bi bi-check-circle"></i>
                    <?php echo $success; ?>
                    <div style="margin-top: 1rem;">
                        <a href="my-rentals.php" class="btn btn-primary">View My Rentals</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="tool-detail">
                    <!-- Tool Image -->
                    <div class="tool-detail-image">
                        <img src="../../assets/images/<?php echo $tool['image']; ?>" alt="<?php echo htmlspecialchars($tool['name']); ?>">
                    </div>

                    <!-- Tool Info & Rental Form -->
                    <div>
                        <div style="margin-bottom: 1rem;">
                            <span class="badge badge-success">Available</span>
                            <span style="color: var(--gray-500); margin-left: 0.5rem;">
                                <i class="bi bi-folder"></i> <?php echo htmlspecialchars($tool['category_name']); ?>
                            </span>
                        </div>
                        
                        <h1><?php echo htmlspecialchars($tool['name']); ?></h1>
                        <p style="color: var(--gray-600); margin: 1rem 0;">
                            <?php echo nl2br(htmlspecialchars($tool['description'])); ?>
                        </p>
                        
                        <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--gray-600);">
                                <i class="bi bi-person"></i>
                                Owner: <?php echo htmlspecialchars($tool['owner_name']); ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--gray-600);">
                                <i class="bi bi-geo-alt"></i>
                                <?php echo htmlspecialchars($tool['location']); ?>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" style="margin-bottom: 1rem;">
                                <i class="bi bi-exclamation-circle"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Pricing Box -->
                        <div class="pricing-box">
                            <h4 style="margin-bottom: 1rem;"><i class="bi bi-cash-stack"></i> Pricing</h4>
                            <div class="pricing-row">
                                <span>Daily Rate</span>
                                <strong><?php echo formatCurrency($tool['daily_rate']); ?></strong>
                            </div>
                            <div class="pricing-row">
                                <span>Security Deposit (Refundable)</span>
                                <strong><?php echo formatCurrency($tool['security_deposit']); ?></strong>
                            </div>
                            <div class="pricing-row">
                                <span>Late Fee (per day)</span>
                                <strong><?php echo formatCurrency($tool['late_fee_per_day']); ?></strong>
                            </div>
                        </div>

                        <!-- Rental Form -->
                        <form method="POST" action="">
                            <h4 style="margin-bottom: 1rem;"><i class="bi bi-calendar"></i> Rental Dates</h4>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" name="start_date" class="form-control" required 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           onchange="calculateTotal()" id="start_date">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" name="end_date" class="form-control" required 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           onchange="calculateTotal()" id="end_date">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Pre-Rental Condition Notes *</label>
                                <textarea name="pre_condition" class="form-control" rows="3" required 
                                          placeholder="Describe the current condition of the tool before rental (any scratches, damages, etc.)"></textarea>
                                <small style="color: var(--gray-500);">This will be used to verify the tool condition upon return.</small>
                            </div>

                            <!-- Cost Summary -->
                            <div class="pricing-box" style="background: var(--primary); color: white;">
                                <h4 style="margin-bottom: 1rem; color: white;"><i class="bi bi-calculator"></i> Cost Summary</h4>
                                <div class="pricing-row" style="border-color: rgba(255,255,255,0.3);">
                                    <span style="color: rgba(255,255,255,0.8);">Rental Days</span>
                                    <strong id="rental_days" style="color: white;">0 days</strong>
                                </div>
                                <div class="pricing-row" style="border-color: rgba(255,255,255,0.3);">
                                    <span style="color: rgba(255,255,255,0.8);">Rental Fee</span>
                                    <strong id="rental_fee" style="color: white;"><?php echo formatCurrency(0); ?></strong>
                                </div>
                                <div class="pricing-row" style="border-color: rgba(255,255,255,0.3);">
                                    <span style="color: rgba(255,255,255,0.8);">Security Deposit (Held)</span>
                                    <strong id="deposit_amount" style="color: white;"><?php echo formatCurrency($tool['security_deposit']); ?></strong>
                                </div>
                                <div class="pricing-row" style="border: none; font-size: 1.25rem;">
                                    <span style="color: rgba(255,255,255,0.9);">Total Amount</span>
                                    <strong id="total_amount" style="color: white;"><?php echo formatCurrency($tool['security_deposit']); ?></strong>
                                </div>
                            </div>

                            <div class="alert alert-info" style="margin-bottom: 1rem;">
                                <i class="bi bi-info-circle"></i>
                                <strong>Deposit Holding:</strong> Your security deposit of <?php echo formatCurrency($tool['security_deposit']); ?> will be held until the tool is returned in the same condition. The deposit will be refunded within 3-5 business days after successful return verification.
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                                <i class="bi bi-check-lg"></i> Confirm Rental
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const dailyRate = <?php echo $tool['daily_rate']; ?>;
        const securityDeposit = <?php echo $tool['security_deposit']; ?>;

        function calculateTotal() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffTime = end - start;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays > 0) {
                    const rentalFee = diffDays * dailyRate;
                    const totalAmount = rentalFee + securityDeposit;

                    document.getElementById('rental_days').textContent = diffDays + ' day' + (diffDays > 1 ? 's' : '');
                    document.getElementById('rental_fee').textContent = '₱' + rentalFee.toFixed(2);
                    document.getElementById('total_amount').textContent = '₱' + totalAmount.toFixed(2);
                }
            }
        }
    </script>
</body>
</html>
