<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user is not admin
if (isAdmin()) {
    redirect('pages/admin/dashboard.php');
}

$user_id = $_SESSION['user_id'];

// Get user statistics
$stats = [];

// Active rentals
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM rentals WHERE renter_id = ? AND rental_status IN ('active', 'pending')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['active_rentals'] = $stmt->get_result()->fetch_assoc()['count'];

// Total rentals
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM rentals WHERE renter_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_rentals'] = $stmt->get_result()->fetch_assoc()['count'];

// Total spent
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM rentals WHERE renter_id = ? AND rental_status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_spent'] = $stmt->get_result()->fetch_assoc()['total'];

// Pending deposits
$stmt = $conn->prepare("SELECT COALESCE(SUM(security_deposit), 0) as total FROM rentals WHERE renter_id = ? AND deposit_status = 'held'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['pending_deposits'] = $stmt->get_result()->fetch_assoc()['total'];

// Get recent rentals
$stmt = $conn->prepare("
    SELECT r.*, t.name as tool_name, t.image as tool_image 
    FROM rentals r 
    JOIN tools t ON r.tool_id = t.id 
    WHERE r.renter_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_rentals = $stmt->get_result();

// Get available tools for quick rent
$stmt = $conn->prepare("
    SELECT t.*, c.name as category_name, u.fullname as owner_name 
    FROM tools t 
    LEFT JOIN categories c ON t.category_id = c.id 
    LEFT JOIN users u ON t.owner_id = u.id 
    WHERE t.status = 'available' 
    ORDER BY t.created_at DESC 
    LIMIT 6
");
$stmt->execute();
$available_tools = $stmt->get_result();

$page_title = 'User Dashboard';
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
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</h1>
                    <p style="color: var(--gray-500);">Here's what's happening with your rentals</p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="browse-tools.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Rent a Tool
                    </a>
                    <img src="../../assets/images/<?php echo $_SESSION['profile_image']; ?>" alt="Profile" class="user-avatar">
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['active_rentals']; ?></h3>
                        <p>Active Rentals</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-tools"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_rentals']; ?></h3>
                        <p>Total Rentals</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['total_spent']); ?></h3>
                        <p>Total Spent</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['pending_deposits']); ?></h3>
                        <p>Held Deposits</p>
                    </div>
                </div>
            </div>

            <!-- Recent Rentals -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3><i class="bi bi-clock-history"></i> Recent Rentals</h3>
                    <a href="my-rentals.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-body">
                    <?php if ($recent_rentals->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Rental Period</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Deposit</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($rental = $recent_rentals->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <img src="../../assets/images/<?php echo $rental['tool_image']; ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm);">
                                                    <span><?php echo htmlspecialchars($rental['tool_name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo date('M d', strtotime($rental['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($rental['end_date'])); ?>
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
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <h3>No Rentals Yet</h3>
                            <p>You haven't rented any tools yet. Start browsing!</p>
                            <a href="browse-tools.php" class="btn btn-primary">Browse Tools</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Tools -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-stars"></i> Available Tools</h3>
                    <a href="browse-tools.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-body">
                    <div class="tools-grid">
                        <?php while ($tool = $available_tools->fetch_assoc()): ?>
                            <div class="tool-card">
                                <div class="tool-image">
                                    <img src="../../assets/images/<?php echo $tool['image']; ?>" alt="<?php echo htmlspecialchars($tool['name']); ?>">
                                    <div class="tool-status">
                                        <span class="badge badge-success">Available</span>
                                    </div>
                                </div>
                                <div class="tool-content">
                                    <div class="tool-category"><?php echo htmlspecialchars($tool['category_name']); ?></div>
                                    <h4 class="tool-title"><?php echo htmlspecialchars($tool['name']); ?></h4>
                                    <p class="tool-description"><?php echo htmlspecialchars($tool['description']); ?></p>
                                    <div class="tool-meta">
                                        <div class="tool-price">
                                            <span class="daily"><?php echo formatCurrency($tool['daily_rate']); ?>/day</span>
                                            <span class="deposit">Deposit: <?php echo formatCurrency($tool['security_deposit']); ?></span>
                                        </div>
                                        <a href="rent-tool.php?id=<?php echo $tool['id']; ?>" class="btn btn-primary btn-sm">
                                            Rent Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
