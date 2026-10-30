<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Handle deposit release
if (isset($_GET['action']) && isset($_GET['rental_id'])) {
    $rental_id = intval($_GET['rental_id']);
    $action = sanitize($conn, $_GET['action']);
    
    if ($action === 'release') {
        $stmt = $conn->prepare("UPDATE rentals SET deposit_status = 'released' WHERE id = ? AND deposit_status = 'held'");
        $stmt->bind_param("i", $rental_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = "Deposit released successfully!";
            
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $admin_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Deposit Released', 'Deposit for rental #$rental_id released', ?)
            ");
            $stmt->bind_param("is", $admin_id, $ip);
            $stmt->execute();
        } else {
            $error = "Failed to release deposit. It may already be released or forfeited.";
        }
    } elseif ($action === 'forfeit') {
        $stmt = $conn->prepare("UPDATE rentals SET deposit_status = 'forfeited' WHERE id = ? AND deposit_status = 'held'");
        $stmt->bind_param("i", $rental_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = "Deposit forfeited successfully!";
            
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $admin_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Deposit Forfeited', 'Deposit for rental #$rental_id forfeited', ?)
            ");
            $stmt->bind_param("is", $admin_id, $ip);
            $stmt->execute();
        } else {
            $error = "Failed to forfeit deposit.";
        }
    }
}

// Get deposit summary
$summary = $conn->query("
    SELECT 
        COALESCE(SUM(CASE WHEN deposit_status = 'held' THEN security_deposit ELSE 0 END), 0) as held_deposits,
        COALESCE(SUM(CASE WHEN deposit_status = 'released' THEN security_deposit ELSE 0 END), 0) as released_deposits,
        COALESCE(SUM(CASE WHEN deposit_status = 'forfeited' THEN security_deposit ELSE 0 END), 0) as forfeited_deposits,
        COUNT(CASE WHEN deposit_status = 'held' THEN 1 END) as held_count,
        COUNT(CASE WHEN deposit_status = 'released' THEN 1 END) as released_count,
        COUNT(CASE WHEN deposit_status = 'forfeited' THEN 1 END) as forfeited_count
    FROM rentals 
    WHERE security_deposit > 0
")->fetch_assoc();

// Get filter
$status_filter = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';

// Build query
$query = "
    SELECT r.*, t.name as tool_name, t.image as tool_image, u.fullname as renter_name, u.email as renter_email
    FROM rentals r 
    JOIN tools t ON r.tool_id = t.id 
    JOIN users u ON r.renter_id = u.id 
    WHERE r.security_deposit > 0
";

if ($status_filter) {
    $query .= " AND r.deposit_status = '$status_filter'";
}

$query .= " ORDER BY r.created_at DESC";

$deposits = $conn->query($query);

$page_title = 'Manage Deposits';
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
                        <a href="deposits.php" class="active">
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
                <h1>Manage Deposits</h1>
                <p>Track and manage all security deposits</p>
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

            <!-- Filter -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end;">
                        <div style="min-width: 200px;">
                            <label class="form-label">Filter by Status</label>
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="held" <?php echo $status_filter === 'held' ? 'selected' : ''; ?>>Held</option>
                                <option value="released" <?php echo $status_filter === 'released' ? 'selected' : ''; ?>>Released</option>
                                <option value="forfeited" <?php echo $status_filter === 'forfeited' ? 'selected' : ''; ?>>Forfeited</option>
                            </select>
                        </div>
                        <a href="deposits.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    </form>
                </div>
            </div>

            <!-- Deposits Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Renter</th>
                                    <th>Tool</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Rental Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($deposit = $deposits->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $deposit['id']; ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($deposit['renter_name']); ?></div>
                                            <small style="color: var(--gray-500);"><?php echo htmlspecialchars($deposit['renter_email']); ?></small>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <img src="../../assets/images/<?php echo $deposit['tool_image']; ?>" alt="" 
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm);">
                                                <?php echo htmlspecialchars($deposit['tool_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo formatCurrency($deposit['security_deposit']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $deposit['deposit_status'] === 'held' ? 'warning' : ($deposit['deposit_status'] === 'released' ? 'success' : 'danger'); ?>">
                                                <?php echo ucfirst($deposit['deposit_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo getStatusColor($deposit['rental_status']); ?>">
                                                <?php echo ucfirst($deposit['rental_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($deposit['deposit_status'] === 'held'): ?>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <a href="?action=release&rental_id=<?php echo $deposit['id']; ?>" 
                                                       class="btn btn-sm btn-success" 
                                                       onclick="return confirm('Release deposit to user?')"
                                                       title="Release Deposit">
                                                        <i class="bi bi-check-lg"></i> Release
                                                    </a>
                                                    <a href="?action=forfeit&rental_id=<?php echo $deposit['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Forfeit this deposit?')"
                                                       title="Forfeit Deposit">
                                                        <i class="bi bi-x-lg"></i>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500);">No actions</span>
                                            <?php endif; ?>
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
