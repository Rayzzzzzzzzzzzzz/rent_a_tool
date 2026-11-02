<?php
require_once '../../includes/db.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['tool_id'])) {
    $tool_id = intval($_GET['tool_id']);
    $new_status = sanitize($conn, $_GET['toggle_status']);
    
    $valid_statuses = ['available', 'under_repair', 'pending'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE tools SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $tool_id);
        
        if ($stmt->execute()) {
            $success = "Tool status updated successfully!";
            
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $admin_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Tool Status Updated', 'Tool #$tool_id status changed to $new_status', ?)
            ");
            $stmt->bind_param("is", $admin_id, $ip);
            $stmt->execute();
        } else {
            $error = "Failed to update tool status.";
        }
    }
}

// Handle tool deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tool_id = intval($_GET['delete']);
    
    // Check if tool has active rentals
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rentals WHERE tool_id = ? AND rental_status IN ('active', 'pending')");
    $stmt->bind_param("i", $tool_id);
    $stmt->execute();
    $active_rentals = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($active_rentals > 0) {
        $error = "Cannot delete tool with active rentals.";
    } else {
        $stmt = $conn->prepare("DELETE FROM tools WHERE id = ?");
        $stmt->bind_param("i", $tool_id);
        
        if ($stmt->execute()) {
            $success = "Tool deleted successfully!";
        } else {
            $error = "Failed to delete tool.";
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status_filter = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';

// Build query
$query = "
    SELECT t.*, c.name as category_name, u.fullname as owner_name,
           (SELECT COUNT(*) FROM rentals WHERE tool_id = t.id AND rental_status IN ('active', 'pending')) as active_rentals
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

if ($status_filter) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $query .= " AND (t.name LIKE ? OR t.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
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

// Get all users for owner dropdown
$users = $conn->query("SELECT id, fullname FROM users WHERE role = 'user' ORDER BY fullname");

$page_title = 'Manage Tools';
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
                        <a href="tools.php" class="active">
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
                <h1>Manage Tools</h1>
                <p>Manage tool inventory and availability</p>
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

            <!-- Filters & Add Button -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; flex: 1;">
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
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <a href="tools.php" class="btn btn-secondary">
                                <i class="bi bi-x-lg"></i> Clear
                            </a>
                        </form>
                        <button onclick="openAddModal()" class="btn btn-success">
                            <i class="bi bi-plus-lg"></i> Add Tool
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tools Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tool</th>
                                    <th>Category</th>
                                    <th>Owner</th>
                                    <th>Daily Rate</th>
                                    <th>Deposit</th>
                                    <th>Status</th>
                                    <th>Active Rentals</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tool = $tools->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <img src="../../assets/images/<?php echo $tool['image']; ?>" alt="" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);">
                                                <div>
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($tool['name']); ?></div>
                                                    <small style="color: var(--gray-500);">
                                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($tool['location']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($tool['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($tool['owner_name']); ?></td>
                                        <td><?php echo formatCurrency($tool['daily_rate']); ?></td>
                                        <td><?php echo formatCurrency($tool['security_deposit']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo getStatusColor($tool['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $tool['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $tool['active_rentals']; ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if ($tool['status'] === 'available'): ?>
                                                    <a href="?tool_id=<?php echo $tool['id']; ?>&toggle_status=under_repair" 
                                                       class="btn btn-sm btn-warning" title="Mark Under Repair">
                                                        <i class="bi bi-wrench"></i>
                                                    </a>
                                                <?php elseif ($tool['status'] === 'under_repair'): ?>
                                                    <a href="?tool_id=<?php echo $tool['id']; ?>&toggle_status=available" 
                                                       class="btn btn-sm btn-success" title="Mark Available">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button onclick="editTool(<?php echo htmlspecialchars(json_encode($tool)); ?>)" 
                                                        class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($tool['active_rentals'] == 0): ?>
                                                    <a href="?delete=<?php echo $tool['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this tool?')"
                                                       title="Delete">
                                                        <i class="bi bi-trash"></i>
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

    <!-- Add/Edit Tool Modal -->
    <div id="toolModal" class="modal-overlay">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="bi bi-tools"></i> Add New Tool</h3>
                <button onclick="closeModal()" class="close-btn">&times;</button>
            </div>
            <form method="POST" action="save-tool.php" id="toolForm">
                <input type="hidden" name="tool_id" id="tool_id">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Tool Name *</label>
                            <input type="text" name="name" id="tool_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select name="category_id" id="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php 
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Daily Rate (₱) *</label>
                            <input type="number" name="daily_rate" id="daily_rate" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Security Deposit (₱) *</label>
                            <input type="number" name="security_deposit" id="security_deposit" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Late Fee/Day (₱)</label>
                            <input type="number" name="late_fee_per_day" id="late_fee_per_day" class="form-control" step="0.01" min="0" value="10.00">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Owner</label>
                            <select name="owner_id" id="owner_id" class="form-control">
                                <option value="">Select Owner</option>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['fullname']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="location" class="form-control" placeholder="e.g., Downtown">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Condition Notes</label>
                        <textarea name="condition_notes" id="condition_notes" class="form-control" rows="2" 
                                  placeholder="Current condition of the tool"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Tool
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-tools"></i> Add New Tool';
            document.getElementById('toolForm').reset();
            document.getElementById('tool_id').value = '';
            document.getElementById('toolModal').classList.add('active');
        }

        function editTool(tool) {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Tool';
            document.getElementById('tool_id').value = tool.id;
            document.getElementById('tool_name').value = tool.name;
            document.getElementById('category_id').value = tool.category_id;
            document.getElementById('description').value = tool.description;
            document.getElementById('daily_rate').value = tool.daily_rate;
            document.getElementById('security_deposit').value = tool.security_deposit;
            document.getElementById('late_fee_per_day').value = tool.late_fee_per_day;
            document.getElementById('owner_id').value = tool.owner_id;
            document.getElementById('location').value = tool.location;
            document.getElementById('condition_notes').value = tool.condition_notes;
            document.getElementById('toolModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('toolModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('toolModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
