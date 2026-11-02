<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get rental ID
$rental_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get rental details
$stmt = $conn->prepare("
    SELECT r.*, t.name as tool_name, t.image as tool_image, t.description as tool_description,
           t.daily_rate as tool_daily_rate, t.security_deposit as tool_deposit, t.late_fee_per_day,
           c.name as category_name, u.fullname as owner_name
    FROM rentals r 
    JOIN tools t ON r.tool_id = t.id 
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN users u ON t.owner_id = u.id
    WHERE r.id = ? AND r.renter_id = ?
");
$stmt->bind_param("ii", $rental_id, $user_id);
$stmt->execute();
$rental = $stmt->get_result()->fetch_assoc();

if (!$rental) {
    redirect('my-rentals.php');
}

// Calculate late fee if overdue
$is_overdue = strtotime($rental['end_date']) < time() && in_array($rental['rental_status'], ['active', 'pending']);
$days_overdue = $is_overdue ? floor((time() - strtotime($rental['end_date'])) / (60 * 60 * 24)) : 0;
$current_late_fee = $is_overdue ? $days_overdue * $rental['late_fee_per_day'] : 0;

$page_title = 'Rental Details #' . $rental_id;
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
                        <a href="my-rentals.php" class="active">
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
                <a href="my-rentals.php" style="color: var(--gray-500);">
                    <i class="bi bi-arrow-left"></i> Back to My Rentals
                </a>
            </div>

            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Rental #<?php echo $rental_id; ?></h1>
                    <p>View rental details and status</p>
                </div>
                <a href="download-report.php?type=rental&rental_id=<?php echo $rental_id; ?>" class="btn btn-primary" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> Download Receipt
                </a>
            </div>

            <?php if ($is_overdue): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>This rental is overdue!</strong> 
                    Please return the tool immediately to avoid additional late fees.
                    Current late fee: <?php echo formatCurrency($current_late_fee); ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Tool Info -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-tools"></i> Tool Information</h3>
                    </div>
                    <div class="card-body">
                        <img src="../../assets/images/<?php echo $rental['tool_image']; ?>" alt="" 
                             style="width: 100%; height: 250px; object-fit: cover; border-radius: var(--radius); margin-bottom: 1rem;">
                        <h4><?php echo htmlspecialchars($rental['tool_name']); ?></h4>
                        <p style="color: var(--gray-600);"><?php echo htmlspecialchars($rental['tool_description']); ?></p>
                        <div style="margin-top: 1rem;">
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Category</span>
                                <span><?php echo htmlspecialchars($rental['category_name']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Owner</span>
                                <span><?php echo htmlspecialchars($rental['owner_name']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Daily Rate</span>
                                <span><?php echo formatCurrency($rental['tool_daily_rate']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Late Fee/Day</span>
                                <span><?php echo formatCurrency($rental['late_fee_per_day']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rental Details -->
                <div>
                    <!-- Status Card -->
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <div class="card-header">
                            <h3><i class="bi bi-info-circle"></i> Rental Status</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span style="color: var(--gray-500);">Rental Status</span>
                                <span class="badge badge-<?php echo getStatusColor($rental['rental_status']); ?>">
                                    <?php echo ucfirst($rental['rental_status']); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span style="color: var(--gray-500);">Deposit Status</span>
                                <span class="badge badge-<?php echo $rental['deposit_status'] === 'held' ? 'warning' : ($rental['deposit_status'] === 'released' ? 'success' : 'danger'); ?>">
                                    <?php echo ucfirst($rental['deposit_status']); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Rental Period</span>
                                <span><?php echo date('M d, Y', strtotime($rental['start_date'])); ?> - <?php echo date('M d, Y', strtotime($rental['end_date'])); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Total Days</span>
                                <span><?php echo $rental['total_days']; ?> days</span>
                            </div>
                            <?php if ($rental['return_date']): ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                    <span style="color: var(--gray-500);">Returned On</span>
                                    <span><?php echo date('M d, Y', strtotime($rental['return_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cost Breakdown -->
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <div class="card-header">
                            <h3><i class="bi bi-cash-stack"></i> Cost Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Rental Fee</span>
                                <span><?php echo formatCurrency($rental['rental_fee']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-500);">Security Deposit</span>
                                <span><?php echo formatCurrency($rental['security_deposit']); ?></span>
                            </div>
                            <?php if ($rental['late_fee'] > 0): ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                    <span style="color: var(--danger);">Late Fee</span>
                                    <span style="color: var(--danger);"><?php echo formatCurrency($rental['late_fee']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($current_late_fee > 0): ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                    <span style="color: var(--danger);">Estimated Late Fee</span>
                                    <span style="color: var(--danger);"><?php echo formatCurrency($current_late_fee); ?></span>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; margin-top: 0.5rem; border-top: 2px solid var(--gray-300); font-weight: 600;">
                                <span>Total Amount</span>
                                <span><?php echo formatCurrency($rental['total_amount'] + $current_late_fee); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Condition Reports -->
            <?php if ($rental['pre_rental_condition'] || $rental['post_rental_condition']): ?>
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3><i class="bi bi-clipboard-check"></i> Condition Reports</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <?php if ($rental['pre_rental_condition']): ?>
                                <div>
                                    <h4 style="margin-bottom: 0.5rem; color: var(--gray-600);">Pre-Rental Condition</h4>
                                    <div style="background: var(--gray-50); padding: 1rem; border-radius: var(--radius);">
                                        <?php echo nl2br(htmlspecialchars($rental['pre_rental_condition'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($rental['post_rental_condition']): ?>
                                <div>
                                    <h4 style="margin-bottom: 0.5rem; color: var(--gray-600);">Post-Rental Condition</h4>
                                    <div style="background: var(--gray-50); padding: 1rem; border-radius: var(--radius);">
                                        <?php echo nl2br(htmlspecialchars($rental['post_rental_condition'])); ?>
                                    </div>
                                    <div style="margin-top: 0.5rem;">
                                        <span class="badge badge-<?php echo $rental['condition_verified'] ? 'success' : 'warning'; ?>">
                                            <?php echo $rental['condition_verified'] ? 'Verified' : 'Pending Verification'; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
