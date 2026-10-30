<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Handle rental status update
if (isset($_GET['action']) && isset($_GET['rental_id'])) {
    $rental_id = intval($_GET['rental_id']);
    $action = sanitize($conn, $_GET['action']);
    
    if ($action === 'approve') {
        // Approve pending rental
        $stmt = $conn->prepare("
            UPDATE rentals SET rental_status = 'active' WHERE id = ? AND rental_status = 'pending'
        ");
        $stmt->bind_param("i", $rental_id);
        
        if ($stmt->execute()) {
            // Update tool status to rented
            $stmt = $conn->prepare("
                UPDATE tools SET status = 'rented' WHERE id = (SELECT tool_id FROM rentals WHERE id = ?)
            ");
            $stmt->bind_param("i", $rental_id);
            $stmt->execute();
            
            $success = "Rental approved successfully!";
        }
    } elseif ($action === 'cancel') {
        // Cancel rental and release deposit
        $stmt = $conn->prepare("
            UPDATE rentals SET rental_status = 'cancelled', deposit_status = 'released' WHERE id = ?
        ");
        $stmt->bind_param("i", $rental_id);
        
        if ($stmt->execute()) {
            // Update tool status back to available
            $stmt = $conn->prepare("
                UPDATE tools SET status = 'available' WHERE id = (SELECT tool_id FROM rentals WHERE id = ?)
            ");
            $stmt->bind_param("i", $rental_id);
            $stmt->execute();
            
            $success = "Rental cancelled and deposit released!";
        }
    } elseif ($action === 'release_deposit') {
        // Manually release deposit
        $stmt = $conn->prepare("
            UPDATE rentals SET deposit_status = 'released' WHERE id = ?
        ");
        $stmt->bind_param("i", $rental_id);
        
        if ($stmt->execute()) {
            $success = "Deposit released successfully!";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';

// Build query
$query = "
    SELECT r.*, t.name as tool_name, t.image as tool_image, t.late_fee_per_day,
           u.fullname as renter_name, u.email as renter_email
    FROM rentals r 
    JOIN tools t ON r.tool_id = t.id 
    JOIN users u ON r.renter_id = u.id 
    WHERE 1=1
";
$params = [];
$types = '';

if ($status_filter) {
    $query .= " AND r.rental_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $query .= " AND (t.name LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rentals = $stmt->get_result();

$page_title = 'All Rentals';
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
                        <a href="rentals.php" class="active">
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
                <h1>All Rentals</h1>
                <p>Manage all rental transactions</p>
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

            <!-- Filters -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 1; min-width: 200px;">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search by tool, renter..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div style="min-width: 150px;">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="rentals.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    </form>
                </div>
            </div>

            <!-- Rentals Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tool</th>
                                    <th>Renter</th>
                                    <th>Period</th>
                                    <th>Costs</th>
                                    <th>Status</th>
                                    <th>Deposit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rental = $rentals->fetch_assoc()): 
                                    $is_overdue = strtotime($rental['end_date']) < time() && in_array($rental['rental_status'], ['active', 'pending']);
                                    $days_overdue = $is_overdue ? floor((time() - strtotime($rental['end_date'])) / (60 * 60 * 24)) : 0;
                                    $current_late_fee = $is_overdue ? $days_overdue * $rental['late_fee_per_day'] : 0;
                                ?>
                                    <tr>
                                        <td>#<?php echo $rental['id']; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <img src="../../assets/images/<?php echo $rental['tool_image']; ?>" alt="" 
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm);">
                                                <?php echo htmlspecialchars($rental['tool_name']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($rental['renter_name']); ?></div>
                                            <small style="color: var(--gray-500);"><?php echo htmlspecialchars($rental['renter_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('M d', strtotime($rental['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($rental['end_date'])); ?>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                <?php echo $rental['total_days']; ?> days
                                            </div>
                                        </td>
                                        <td>
                                            <div>Rental: <?php echo formatCurrency($rental['rental_fee']); ?></div>
                                            <div>Deposit: <?php echo formatCurrency($rental['security_deposit']); ?></div>
                                            <?php if ($rental['late_fee'] > 0): ?>
                                                <div style="color: var(--danger);">Late: <?php echo formatCurrency($rental['late_fee']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($current_late_fee > 0): ?>
                                                <div style="color: var(--danger);">Est. Late: <?php echo formatCurrency($current_late_fee); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo getStatusColor($rental['rental_status']); ?>">
                                                <?php echo ucfirst($rental['rental_status']); ?>
                                            </span>
                                            <?php if ($is_overdue): ?>
                                                <div style="font-size: 0.75rem; color: var(--danger); margin-top: 0.25rem;">
                                                    <?php echo $days_overdue; ?> days overdue
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $rental['deposit_status'] === 'held' ? 'warning' : ($rental['deposit_status'] === 'released' ? 'success' : 'danger'); ?>">
                                                <?php echo ucfirst($rental['deposit_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if ($rental['rental_status'] === 'pending'): ?>
                                                    <a href="?action=approve&rental_id=<?php echo $rental['id']; ?>" 
                                                       class="btn btn-sm btn-success" title="Approve">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                    <a href="?action=cancel&rental_id=<?php echo $rental['id']; ?>" 
                                                       class="btn btn-sm btn-danger" title="Cancel"
                                                       onclick="return confirm('Cancel this rental and release deposit?')">
                                                        <i class="bi bi-x-lg"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($rental['deposit_status'] === 'held' && $rental['rental_status'] === 'completed'): ?>
                                                    <a href="?action=release_deposit&rental_id=<?php echo $rental['id']; ?>" 
                                                       class="btn btn-sm btn-warning" title="Release Deposit"
                                                       onclick="return confirm('Release deposit to user?')">
                                                        <i class="bi bi-unlock"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="rental-details.php?id=<?php echo $rental['id']; ?>" 
                                                   class="btn btn-sm btn-outline" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
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
