<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Handle user status update
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $action = sanitize($conn, $_GET['action']);
    
    if ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active', login_attempts = 0, lockout_until = NULL, penalty_count = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success = "User activated successfully!";
        }
    } elseif ($action === 'suspend') {
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success = "User suspended successfully!";
        }
    } elseif ($action === 'ban') {
        $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success = "User banned successfully!";
        }
    } elseif ($action === 'unban') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active', login_attempts = 0, lockout_until = NULL, penalty_count = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success = "User unbanned successfully!";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';

// Build query
$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM rentals WHERE renter_id = u.id) as rental_count,
           (SELECT COALESCE(SUM(total_amount), 0) FROM rentals WHERE renter_id = u.id AND rental_status = 'completed') as total_spent
    FROM users u 
    WHERE u.role = 'user'
";

if ($status_filter) {
    $query .= " AND u.status = '$status_filter'";
}

if ($search) {
    $search_term = "%$search%";
    $query .= " AND (u.fullname LIKE '$search_term' OR u.email LIKE '$search_term' OR u.phone LIKE '$search_term')";
}

$query .= " ORDER BY u.created_at DESC";

$users = $conn->query($query);

// Get statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
        COUNT(CASE WHEN status = 'suspended' THEN 1 END) as suspended_users,
        COUNT(CASE WHEN status = 'banned' THEN 1 END) as banned_users
    FROM users 
    WHERE role = 'user'
")->fetch_assoc();

$page_title = 'Manage Users';
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
                        <a href="users.php" class="active">
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
            <div class="page-header">
                <h1>Manage Users</h1>
                <p>View and manage registered users</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                    <i class="bi bi-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
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
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['active_users']; ?></h3>
                        <p>Active</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-person-dash"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['suspended_users']; ?></h3>
                        <p>Suspended</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="bi bi-person-x"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['banned_users']; ?></h3>
                        <p>Banned</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 1; min-width: 200px;">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search by name, email..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div style="min-width: 150px;">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Penalties</th>
                                    <th>Stats</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <img src="../../assets/images/<?php echo $user['profile_image']; ?>" alt="" 
                                                     style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                <div>
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                                    <small style="color: var(--gray-500);">ID: #<?php echo $user['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                            <?php if ($user['phone']): ?>
                                                <small style="color: var(--gray-500);"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : ($user['status'] === 'banned' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                            <?php if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()): ?>
                                                <div style="font-size: 0.75rem; color: var(--danger); margin-top: 0.25rem;">
                                                    <i class="bi bi-lock"></i> Locked until <?php echo date('g:i A', strtotime($user['lockout_until'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['penalty_count'] > 0): ?>
                                                <span style="color: var(--danger);">
                                                    <i class="bi bi-exclamation-triangle"></i> 
                                                    <?php echo $user['penalty_count']; ?> penalty(s)
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500);">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo $user['rental_count']; ?> rentals</div>
                                            <small style="color: var(--gray-500);"><?php echo formatCurrency($user['total_spent']); ?> spent</small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if ($user['status'] !== 'active'): ?>
                                                    <a href="?action=activate&user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-success" title="Activate">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <a href="?action=suspend&user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-warning" title="Suspend"
                                                       onclick="return confirm('Suspend this user?')">
                                                        <i class="bi bi-pause"></i>
                                                    </a>
                                                    <a href="?action=ban&user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-danger" title="Ban"
                                                       onclick="return confirm('Ban this user?')">
                                                        <i class="bi bi-ban"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($user['status'] === 'banned'): ?>
                                                    <a href="?action=unban&user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Unban"
                                                       onclick="return confirm('Unban this user?')">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
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
