<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Get admin statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total tools
$result = $conn->query("SELECT COUNT(*) as count FROM tools");
$stats['total_tools'] = $result->fetch_assoc()['count'];

// Active rentals
$result = $conn->query("SELECT COUNT(*) as count FROM rentals WHERE rental_status IN ('active', 'pending')");
$stats['active_rentals'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM rentals WHERE rental_status = 'completed'");
$stats['total_revenue'] = $result->fetch_assoc()['total'];

// Held deposits
$result = $conn->query("SELECT COALESCE(SUM(security_deposit), 0) as total FROM rentals WHERE deposit_status = 'held'");
$stats['held_deposits'] = $result->fetch_assoc()['total'];

// Overdue rentals
$result = $conn->query("SELECT COUNT(*) as count FROM rentals WHERE rental_status = 'overdue'");
$stats['overdue_rentals'] = $result->fetch_assoc()['count'];

// Tools under repair
$result = $conn->query("SELECT COUNT(*) as count FROM tools WHERE status = 'under_repair'");
$stats['under_repair'] = $result->fetch_assoc()['count'];

// Recent rentals
$recent_rentals = $conn->query("
    SELECT r.*, t.name as tool_name, u.fullname as renter_name 
    FROM rentals r 
    JOIN tools t ON r.tool_id = t.id 
    JOIN users u ON r.renter_id = u.id 
    ORDER BY r.created_at DESC 
    LIMIT 10
");

// Recent users
$recent_users = $conn->query("
    SELECT * FROM users 
    WHERE role = 'user' 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Tools needing attention (under repair or overdue)
$attention_tools = $conn->query("
    SELECT t.*, c.name as category_name,
           r.end_date as return_date, r.rental_status
    FROM tools t 
    LEFT JOIN categories c ON t.category_id = c.id 
    LEFT JOIN rentals r ON t.id = r.tool_id AND r.rental_status IN ('active', 'overdue')
    WHERE t.status IN ('under_repair', 'pending') OR r.rental_status = 'overdue'
    ORDER BY t.updated_at DESC
    LIMIT 5
");

$page_title = 'Admin Dashboard';
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
                        <a href="dashboard.php" class="active">
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
                        <a href="reports.php">
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
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p style="color: var(--gray-500);">Manage your rental platform</p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span class="badge badge-success">Admin</span>
                    <img src="../../assets/images/<?php echo $_SESSION['profile_image']; ?>" alt="Profile" class="user-avatar">
                </div>
            </div>

            <!-- Statistics Row 1 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-tools"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_tools']; ?></h3>
                        <p>Total Tools</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['active_rentals']; ?></h3>
                        <p>Active Rentals</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Row 2 -->
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="stat-card">
                    <div class="stat-icon warning" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['held_deposits']); ?></h3>
                        <p>Held Deposits</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['overdue_rentals']; ?></h3>
                        <p>Overdue Rentals</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary" style="background: rgba(100, 116, 139, 0.1); color: var(--secondary);">
                        <i class="bi bi-wrench"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['under_repair']; ?></h3>
                        <p>Under Repair</p>
                    </div>
                </div>
            </div>

            <!-- Recent Rentals -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3><i class="bi bi-clock-history"></i> Recent Rentals</h3>
                    <a href="rentals.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Renter</th>
                                    <th>Tool</th>
                                    <th>Period</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Deposit</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rental = $recent_rentals->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $rental['id']; ?></td>
                                        <td><?php echo htmlspecialchars($rental['renter_name']); ?></td>
                                        <td><?php echo htmlspecialchars($rental['tool_name']); ?></td>
                                        <td>
                                            <?php echo date('M d', strtotime($rental['start_date'])); ?> - 
                                            <?php echo date('M d', strtotime($rental['end_date'])); ?>
                                        </td>
                                        <td><?php echo formatCurrency($rental['total_amount']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo getStatusColor($rental['rental_status']); ?>">
                                                <?php echo ucfirst($rental['rental_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $rental['deposit_status'] === 'held' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($rental['deposit_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="rental-details.php?id=<?php echo $rental['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Recent Users -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-people"></i> Recent Users</h3>
                        <a href="users.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-200);">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <img src="../../assets/images/<?php echo $user['profile_image']; ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Tools Needing Attention -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-exclamation-circle"></i> Needs Attention</h3>
                        <a href="tools.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($attention_tools->num_rows > 0): ?>
                            <?php while ($tool = $attention_tools->fetch_assoc()): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-200);">
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($tool['name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">
                                            <?php if ($tool['rental_status'] === 'overdue'): ?>
                                                Overdue since <?php echo date('M d', strtotime($tool['return_date'])); ?>
                                            <?php else: ?>
                                                <?php echo ucfirst(str_replace('_', ' ', $tool['status'])); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge badge-<?php echo $tool['rental_status'] === 'overdue' ? 'danger' : 'warning'; ?>">
                                        <?php echo $tool['rental_status'] === 'overdue' ? 'Overdue' : ucfirst(str_replace('_', ' ', $tool['status'])); ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                                <i class="bi bi-check-circle" style="font-size: 2rem; color: var(--success);"></i>
                                <p style="margin-top: 0.5rem;">All tools are in good standing!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
