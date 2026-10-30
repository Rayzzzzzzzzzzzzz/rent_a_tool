<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get rental statistics
$stats = [];

// Total rentals by status
$stmt = $conn->prepare("
    SELECT rental_status, COUNT(*) as count, SUM(total_amount) as total
    FROM rentals 
    WHERE renter_id = ?
    GROUP BY rental_status
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$status_stats = $stmt->get_result();

// Monthly spending
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as rentals, SUM(total_amount) as spent
    FROM rentals 
    WHERE renter_id = ? AND rental_status = 'completed'
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_stats = $stmt->get_result();

// Category breakdown
$stmt = $conn->prepare("
    SELECT c.name as category, COUNT(*) as rentals, SUM(r.total_amount) as spent
    FROM rentals r
    JOIN tools t ON r.tool_id = t.id
    JOIN categories c ON t.category_id = c.id
    WHERE r.renter_id = ? AND r.rental_status = 'completed'
    GROUP BY c.id, c.name
    ORDER BY spent DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$category_stats = $stmt->get_result();

// Recent activity
$stmt = $conn->prepare("
    SELECT * FROM activity_logs 
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activities = $stmt->get_result();

$page_title = 'My Reports';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="reports.php" class="active">
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
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>My Reports</h1>
                    <p>View your rental statistics and activity</p>
                </div>
                <a href="download-report.php" class="btn btn-primary" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> Download PDF Report
                </a>
            </div>

            <!-- Rental Status Stats -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3><i class="bi bi-pie-chart"></i> Rental Status Overview</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <?php 
                        $status_data = [];
                        while ($stat = $status_stats->fetch_assoc()): 
                            $status_data[$stat['rental_status']] = $stat;
                        ?>
                            <div style="text-align: center; padding: 1.5rem; background: var(--gray-50); border-radius: var(--radius-lg);">
                                <div style="font-size: 2.5rem; font-weight: 700; color: var(--primary);">
                                    <?php echo $stat['count']; ?>
                                </div>
                                <div style="color: var(--gray-600); text-transform: capitalize;">
                                    <?php echo $stat['rental_status']; ?> Rentals
                                </div>
                                <div style="color: var(--gray-500); font-size: 0.875rem; margin-top: 0.5rem;">
                                    Total: <?php echo formatCurrency($stat['total'] ?? 0); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Monthly Spending -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-graph-up"></i> Monthly Spending</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($monthly_stats->num_rows > 0): ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Rentals</th>
                                            <th>Amount Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($month = $monthly_stats->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                                <td><?php echo $month['rentals']; ?></td>
                                                <td><?php echo formatCurrency($month['spent']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 2rem;">
                                <p style="color: var(--gray-500);">No completed rentals yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category Breakdown -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-folder"></i> By Category</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($category_stats->num_rows > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php while ($cat = $category_stats->fetch_assoc()): ?>
                                    <div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                            <span style="font-size: 0.875rem;"><?php echo htmlspecialchars($cat['category']); ?></span>
                                            <span style="font-size: 0.875rem; font-weight: 500;"><?php echo formatCurrency($cat['spent']); ?></span>
                                        </div>
                                        <div class="progress-bar">
                                            <?php 
                                            $max_spent = $category_stats->data_seek(0);
                                            $first = $category_stats->fetch_assoc();
                                            $max = $first['spent'] ?? 1;
                                            $category_stats->data_seek(0);
                                            while ($c = $category_stats->fetch_assoc()) {
                                                if ($c['category'] === $cat['category']) {
                                                    $percentage = ($cat['spent'] / $max) * 100;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                        </div>
                                        <small style="color: var(--gray-500);"><?php echo $cat['rentals']; ?> rentals</small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 2rem;">
                                <p style="color: var(--gray-500);">No category data available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-activity"></i> Recent Activity</h3>
                </div>
                <div class="card-body">
                    <?php if ($activities->num_rows > 0): ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php while ($activity = $activities->fetch_assoc()): ?>
                                <div style="display: flex; align-items: flex-start; gap: 1rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius);">
                                    <div style="width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($activity['action']); ?></div>
                                        <div style="color: var(--gray-600); font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </div>
                                        <div style="color: var(--gray-500); font-size: 0.75rem; margin-top: 0.25rem;">
                                            <?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 2rem;">
                            <p style="color: var(--gray-500);">No recent activity.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
