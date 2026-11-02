<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Date range filter
$start_date = isset($_GET['start_date']) ? sanitize($conn, $_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize($conn, $_GET['end_date']) : date('Y-m-d');

// Overall statistics
$stats = [];

// Total revenue
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total FROM rentals 
    WHERE rental_status = 'completed' AND created_at BETWEEN ? AND ?
");
$end_date_plus = $end_date . ' 23:59:59';
$stmt->bind_param("ss", $start_date, $end_date_plus);
$stmt->execute();
$stats['total_revenue'] = $stmt->get_result()->fetch_assoc()['total'];

// Total rentals
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM rentals 
    WHERE created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date_plus);
$stmt->execute();
$stats['total_rentals'] = $stmt->get_result()->fetch_assoc()['count'];

// Active rentals
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM rentals 
    WHERE rental_status IN ('active', 'pending') AND created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date_plus);
$stmt->execute();
$stats['active_rentals'] = $stmt->get_result()->fetch_assoc()['count'];

// Total deposits held
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(security_deposit), 0) as total FROM rentals 
    WHERE deposit_status = 'held'
");
$stmt->execute();
$stats['held_deposits'] = $stmt->get_result()->fetch_assoc()['total'];

// Late fees collected
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(late_fee), 0) as total FROM rentals 
    WHERE late_fee > 0 AND created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date_plus);
$stmt->execute();
$stats['late_fees'] = $stmt->get_result()->fetch_assoc()['total'];

// New users
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM users 
    WHERE role = 'user' AND created_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date_plus);
$stmt->execute();
$stats['new_users'] = $stmt->get_result()->fetch_assoc()['count'];

// Revenue by month
$monthly_revenue = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
           COUNT(*) as rentals, 
           SUM(rental_fee) as rental_revenue,
           SUM(late_fee) as late_fees,
           SUM(total_amount) as total
    FROM rentals 
    WHERE rental_status = 'completed'
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");

// Top rented tools
$top_tools = $conn->query("
    SELECT t.name, COUNT(r.id) as rental_count, SUM(r.total_amount) as revenue
    FROM rentals r
    JOIN tools t ON r.tool_id = t.id
    WHERE r.rental_status = 'completed'
    GROUP BY t.id, t.name
    ORDER BY rental_count DESC
    LIMIT 10
");

// Top users
$top_users = $conn->query("
    SELECT u.fullname, u.email, COUNT(r.id) as rental_count, SUM(r.total_amount) as spent
    FROM rentals r
    JOIN users u ON r.renter_id = u.id
    WHERE r.rental_status = 'completed'
    GROUP BY u.id, u.fullname, u.email
    ORDER BY spent DESC
    LIMIT 10
");

// Category performance
$category_stats = $conn->query("
    SELECT c.name as category, COUNT(r.id) as rentals, SUM(r.total_amount) as revenue
    FROM rentals r
    JOIN tools t ON r.tool_id = t.id
    JOIN categories c ON t.category_id = c.id
    WHERE r.rental_status = 'completed'
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
");

// Recent activity logs
$activity_logs = $conn->query("
    SELECT al.*, u.fullname 
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 30
");

$page_title = 'Admin Reports';
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
                        <a href="users.php">
                            <i class="bi bi-people"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="tools.php">
                            <i class="bi bi-tools"></i>
                            <span>Tools</span>
                        </a>
                    </li>
                    <li>
                        <a href="rentals.php">
                            <i class="bi bi-calendar-check"></i>
                            <span>All Rentals</span>
                        </a>
                    </li>
                    <li>
                        <a href="deposits.php">
                            <i class="bi bi-shield-lock"></i>
                            <span>Deposits</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="active">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="activity-logs.php">
                            <i class="bi bi-activity"></i>
                            <span>Activity Logs</span>
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
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Reports & Analytics</h1>
                    <p>Platform performance and statistics</p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="download-report.php?type=summary" class="btn btn-primary" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Summary Report
                    </a>
                    <a href="download-report.php?type=revenue" class="btn btn-success" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Revenue Report
                    </a>
                    <a href="download-report.php?type=users" class="btn btn-info" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Users Report
                    </a>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end;">
                        <div>
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        <div>
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    </form>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_rentals']; ?></h3>
                        <p>Total Rentals</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['held_deposits']); ?></h3>
                        <p>Held Deposits</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['late_fees']); ?></h3>
                        <p>Late Fees</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['new_users']; ?></h3>
                        <p>New Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="bi bi-activity"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['active_rentals']; ?></h3>
                        <p>Active Rentals</p>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Monthly Revenue -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-graph-up"></i> Monthly Revenue</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Rentals</th>
                                        <th>Rental Revenue</th>
                                        <th>Late Fees</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($month = $monthly_revenue->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                            <td><?php echo $month['rentals']; ?></td>
                                            <td><?php echo formatCurrency($month['rental_revenue']); ?></td>
                                            <td><?php echo formatCurrency($month['late_fees']); ?></td>
                                            <td><strong><?php echo formatCurrency($month['total']); ?></strong></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-folder"></i> By Category</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php while ($cat = $category_stats->fetch_assoc()): ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                        <span style="font-size: 0.875rem;"><?php echo htmlspecialchars($cat['category']); ?></span>
                                        <span style="font-size: 0.875rem; font-weight: 500;"><?php echo formatCurrency($cat['revenue']); ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: 100%;"></div>
                                    </div>
                                    <small style="color: var(--gray-500);"><?php echo $cat['rentals']; ?> rentals</small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Top Tools -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-tools"></i> Top Tools</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Rentals</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($tool = $top_tools->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tool['name']); ?></td>
                                            <td><?php echo $tool['rental_count']; ?></td>
                                            <td><?php echo formatCurrency($tool['revenue']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Users -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-people"></i> Top Users</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Rentals</th>
                                        <th>Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $top_users->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div><?php echo htmlspecialchars($user['fullname']); ?></div>
                                                <small style="color: var(--gray-500);"><?php echo htmlspecialchars($user['email']); ?></small>
                                            </td>
                                            <td><?php echo $user['rental_count']; ?></td>
                                            <td><?php echo formatCurrency($user['spent']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Logs -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-activity"></i> Recent Activity Logs</h3>
                    <a href="activity-logs.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $activity_logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['fullname'] ?? 'System'); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($log['action']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td><code style="font-size: 0.75rem;"><?php echo $log['ip_address']; ?></code></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
