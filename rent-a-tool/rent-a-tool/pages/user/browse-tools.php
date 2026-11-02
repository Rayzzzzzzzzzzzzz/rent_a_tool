<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get filter parameters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';

// Build query
$query = "
    SELECT t.*, c.name as category_name, u.fullname as owner_name 
    FROM tools t 
    LEFT JOIN categories c ON t.category_id = c.id 
    LEFT JOIN users u ON t.owner_id = u.id 
    WHERE 1=1
";
$params = [];
$types = '';

if ($category_filter > 0) {
    $query .= " AND t.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if ($search) {
    $query .= " AND (t.name LIKE ? OR t.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if ($status_filter) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tools = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

$page_title = 'Browse Tools';
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
                        <a href="browse-tools.php" class="active">
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
            <div class="page-header">
                <h1>Browse Tools</h1>
                <p>Find the perfect tool for your project</p>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 1; min-width: 200px;">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search tools..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div style="min-width: 150px;">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div style="min-width: 150px;">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="rented" <?php echo $status_filter === 'rented' ? 'selected' : ''; ?>>Rented</option>
                                <option value="under_repair" <?php echo $status_filter === 'under_repair' ? 'selected' : ''; ?>>Under Repair</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="browse-tools.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    </form>
                </div>
            </div>

            <!-- Tools Grid -->
            <?php if ($tools->num_rows > 0): ?>
                <div class="tools-grid">
                    <?php while ($tool = $tools->fetch_assoc()): ?>
                        <div class="tool-card">
                            <div class="tool-image">
                                <img src="../../assets/images/<?php echo $tool['image']; ?>" alt="<?php echo htmlspecialchars($tool['name']); ?>">
                                <div class="tool-status">
                                    <span class="badge badge-<?php echo getStatusColor($tool['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $tool['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="tool-content">
                                <div class="tool-category"><?php echo htmlspecialchars($tool['category_name']); ?></div>
                                <h4 class="tool-title"><?php echo htmlspecialchars($tool['name']); ?></h4>
                                <p class="tool-description"><?php echo htmlspecialchars($tool['description']); ?></p>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; color: var(--gray-500);">
                                    <i class="bi bi-geo-alt"></i>
                                    <?php echo htmlspecialchars($tool['location']); ?>
                                </div>
                                <div class="tool-meta">
                                    <div class="tool-price">
                                        <span class="daily"><?php echo formatCurrency($tool['daily_rate']); ?>/day</span>
                                        <span class="deposit">Deposit: <?php echo formatCurrency($tool['security_deposit']); ?></span>
                                    </div>
                                    <?php if ($tool['status'] === 'available'): ?>
                                        <a href="rent-tool.php?id=<?php echo $tool['id']; ?>" class="btn btn-primary btn-sm">
                                            Rent Now
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            Unavailable
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-search"></i>
                    </div>
                    <h3>No Tools Found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
