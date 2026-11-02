<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Get filter parameters
$action_filter = isset($_GET['action']) ? sanitize($conn, $_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($conn, $_GET['date_to']) : '';

// Build query
$query = "
    SELECT al.*, u.fullname, u.email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE 1=1
";

if ($action_filter) {
    $query .= " AND al.action LIKE '%$action_filter%'";
}

if ($date_from) {
    $query .= " AND DATE(al.created_at) >= '$date_from'";
}

if ($date_to) {
    $query .= " AND DATE(al.created_at) <= '$date_to'";
}

$query .= " ORDER BY al.created_at DESC LIMIT 100";

$logs = $conn->query($query);

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");

$page_title = 'Activity Logs';
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
                        <a href="reports.php">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="activity-logs.php" class="active">
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
            <div class="page-header">
                <h1>Activity Logs</h1>
                <p>Track all user and system activities</p>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <div style="min-width: 150px;">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-control">
                                <option value="">All Actions</option>
                                <?php while ($action = $actions->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                            <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action['action']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div>
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="activity-logs.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card">
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
                                <?php if ($logs->num_rows > 0): ?>
                                    <?php while ($log = $logs->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                                <small style="color: var(--gray-500);"><?php echo date('g:i:s A', strtotime($log['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['fullname']): ?>
                                                    <div><?php echo htmlspecialchars($log['fullname']); ?></div>
                                                    <small style="color: var(--gray-500);"><?php echo htmlspecialchars($log['email']); ?></small>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-500);">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($log['action']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                                            <td><code style="font-size: 0.75rem;"><?php echo $log['ip_address']; ?></code></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 2rem;">
                                            <div class="empty-state">
                                                <i class="bi bi-inbox" style="font-size: 2rem; color: var(--gray-400);"></i>
                                                <p style="color: var(--gray-500); margin-top: 0.5rem;">No activity logs found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
