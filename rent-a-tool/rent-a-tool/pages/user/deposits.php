<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get deposit summary
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN deposit_status = 'held' THEN security_deposit ELSE 0 END), 0) as held_deposits,
        COALESCE(SUM(CASE WHEN deposit_status = 'released' THEN security_deposit ELSE 0 END), 0) as released_deposits,
        COALESCE(SUM(CASE WHEN deposit_status = 'forfeited' THEN security_deposit ELSE 0 END), 0) as forfeited_deposits,
        COUNT(CASE WHEN deposit_status = 'held' THEN 1 END) as held_count,
        COUNT(CASE WHEN deposit_status = 'released' THEN 1 END) as released_count,
        COUNT(CASE WHEN deposit_status = 'forfeited' THEN 1 END) as forfeited_count
    FROM rentals 
    WHERE renter_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Get all deposits
$stmt = $conn->prepare("
    SELECT r.*, t.name as tool_name, t.image as tool_image
    FROM rentals r 
    JOIN tools t ON r.tool_id = t.id 
    WHERE r.renter_id = ? AND r.security_deposit > 0
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deposits = $stmt->get_result();

$page_title = 'My Deposits';
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
                        <a href="deposits.php" class="active">
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
            <div class="page-header">
                <h1>My Deposits</h1>
                <p>Track your security deposits</p>
            </div>

            <!-- Deposit Summary -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($summary['held_deposits']); ?></h3>
                        <p>Currently Held (<?php echo $summary['held_count']; ?>)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($summary['released_deposits']); ?></h3>
                        <p>Released (<?php echo $summary['released_count']; ?>)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="bi bi-shield-exclamation"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($summary['forfeited_deposits']); ?></h3>
                        <p>Forfeited (<?php echo $summary['forfeited_count']; ?>)</p>
                    </div>
                </div>
            </div>

            <!-- Deposits Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-list"></i> Deposit History</h3>
                </div>
                <div class="card-body">
                    <?php if ($deposits->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Rental Period</th>
                                        <th>Deposit Amount</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($deposit = $deposits->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <img src="../../assets/images/<?php echo $deposit['tool_image']; ?>" alt="" 
                                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm);">
                                                    <?php echo htmlspecialchars($deposit['tool_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo date('M d', strtotime($deposit['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($deposit['end_date'])); ?>
                                            </td>
                                            <td><?php echo formatCurrency($deposit['security_deposit']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $deposit['deposit_status'] === 'held' ? 'warning' : ($deposit['deposit_status'] === 'released' ? 'success' : 'danger'); ?>">
                                                    <?php echo ucfirst($deposit['deposit_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($deposit['deposit_status'] === 'forfeited' && $deposit['late_fee'] > 0): ?>
                                                    <small style="color: var(--danger);">Late fee applied</small>
                                                <?php elseif ($deposit['deposit_status'] === 'released'): ?>
                                                    <small style="color: var(--success);">Refunded</small>
                                                <?php elseif ($deposit['deposit_status'] === 'held'): ?>
                                                    <small style="color: var(--gray-500);">Will be released after return</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-shield"></i>
                            </div>
                            <h3>No Deposits Yet</h3>
                            <p>You haven't made any security deposits.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deposit Info -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h3><i class="bi bi-info-circle"></i> About Security Deposits</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div>
                            <h4 style="margin-bottom: 0.5rem;"><i class="bi bi-shield-lock" style="color: var(--warning);"></i> Held Deposits</h4>
                            <p style="color: var(--gray-600); margin: 0;">Security deposits are held when you rent a tool. They are kept safe until the tool is returned.</p>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 0.5rem;"><i class="bi bi-shield-check" style="color: var(--success);"></i> Released Deposits</h4>
                            <p style="color: var(--gray-600); margin: 0;">Deposits are released within 3-5 business days after successful tool return and condition verification.</p>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 0.5rem;"><i class="bi bi-shield-exclamation" style="color: var(--danger);"></i> Forfeited Deposits</h4>
                            <p style="color: var(--gray-600); margin: 0;">Deposits may be forfeited for late returns, damage, or failure to return the tool.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
