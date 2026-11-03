<?php
require_once '../../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle return submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_rental'])) {
    $rental_id = intval($_POST['rental_id']);
    $post_condition = sanitize($conn, $_POST['post_condition']);
    $condition_ok = isset($_POST['condition_ok']) ? 1 : 0;
    
    // Get rental details
    $stmt = $conn->prepare("
        SELECT r.*, t.late_fee_per_day, t.id as tool_id 
        FROM rentals r 
        JOIN tools t ON r.tool_id = t.id 
        WHERE r.id = ? AND r.renter_id = ? AND r.rental_status = 'active'
    ");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    
    if ($rental) {
        $return_date = date('Y-m-d');
        $late_fee = calculateLateFee($rental['end_date'], $rental['late_fee_per_day'], $conn);
        
        // Determine deposit status
        if ($condition_ok && $late_fee == 0) {
            $deposit_status = 'released';
            $deposit_note = 'Deposit will be refunded in 3-5 business days.';
        } elseif ($late_fee > 0) {
            $deposit_status = 'forfeited';
            $deposit_note = 'Deposit forfeited due to late return.';
        } else {
            $deposit_status = 'forfeited';
            $deposit_note = 'Deposit forfeited due to damage.';
        }
        
        // Update rental
        $stmt = $conn->prepare("
            UPDATE rentals 
            SET return_date = ?, post_rental_condition = ?, condition_verified = ?, 
                late_fee = ?, total_amount = total_amount + ?, 
                rental_status = 'completed', deposit_status = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
         "ssiisi",
         $return_date,
         $post_condition,
         $condition_ok,
         $late_fee,
         $deposit_status,
         $rental_id
);
        if ($stmt->execute()) {
            // Update tool status back to available
            $stmt = $conn->prepare("UPDATE tools SET status = 'available' WHERE id = ?");
            $stmt->bind_param("i", $rental['tool_id']);
            $stmt->execute();
            
            // Create late fee payment if applicable
            if ($late_fee > 0) {
                $stmt = $conn->prepare("
                    INSERT INTO payments (rental_id, user_id, amount, payment_type, status) 
                    VALUES (?, ?, ?, 'late_fee', 'completed')
                ");
                $stmt->bind_param("iid", $rental_id, $user_id, $late_fee);
                $stmt->execute();
            }
            
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Tool Returned', 'Rental #$rental_id returned', ?)
            ");
            $stmt->bind_param("is", $user_id, $ip);
            $stmt->execute();
            
            $success = "Tool returned successfully! " . ($late_fee > 0 ? "Late fee of " . formatCurrency($late_fee) . " applied. " : "") . $deposit_note;
        } else {
            $error = "Failed to process return. Please try again.";
        }
    }
}

// Get filter
$status_filter = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';

// Build query for rentals
$query = "
    SELECT r.*, t.name as tool_name, t.image as tool_image, t.late_fee_per_day,
           DATEDIFF(CURDATE(), r.end_date) as days_overdue
    FROM rentals r 
    JOIN tools t ON r.tool_id = t.id 
    WHERE r.renter_id = ?
";
$params = [$user_id];
$types = 'i';

if ($status_filter) {
    $query .= " AND r.rental_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rentals = $stmt->get_result();

$page_title = 'My Rentals';
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
                        <a href="browse-tools.php">
                            <i class="bi bi-search"></i>
                            <span>Browse Tools</span>
                        </a>
                    </li>
                    <li>
                        <a href="my-rentals.php" class="active">
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
            <div class="page-header">
                <h1>My Rentals</h1>
                <p>Manage your tool rentals and returns</p>
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
                    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end;">
                        <div style="min-width: 200px;">
                            <label class="form-label">Filter by Status</label>
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <a href="my-rentals.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Clear
                        </a>
                    </form>
                </div>
            </div>

            <!-- Rentals List -->
            <?php if ($rentals->num_rows > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <?php while ($rental = $rentals->fetch_assoc()): 
                        $is_overdue = strtotime($rental['end_date']) < time() && $rental['rental_status'] === 'active';
                        $days_overdue = $is_overdue ? floor((time() - strtotime($rental['end_date'])) / (60 * 60 * 24)) : 0;
                        $late_fee = $is_overdue ? $days_overdue * $rental['late_fee_per_day'] : 0;
                    ?>
                        <div class="card">
                            <div class="card-body">
                                <div style="display: grid; grid-template-columns: 150px 1fr auto; gap: 1.5rem; align-items: start;">
                                    <!-- Tool Image -->
                                    <img src="../../assets/images/<?php echo $rental['tool_image']; ?>" 
                                         alt="" 
                                         style="width: 150px; height: 150px; object-fit: cover; border-radius: var(--radius);">
                                    
                                    <!-- Rental Details -->
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                            <h3 style="margin: 0;"><?php echo htmlspecialchars($rental['tool_name']); ?></h3>
                                            <span class="badge badge-<?php echo getStatusColor($rental['rental_status']); ?>">
                                                <?php echo ucfirst($rental['rental_status']); ?>
                                            </span>
                                            <?php if ($is_overdue): ?>
                                                <span class="badge badge-danger">Overdue <?php echo $days_overdue; ?> days</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0;">
                                            <div>
                                                <small style="color: var(--gray-500);">Rental Period</small>
                                                <div>
                                                    <i class="bi bi-calendar"></i>
                                                    <?php echo date('M d, Y', strtotime($rental['start_date'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($rental['end_date'])); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <small style="color: var(--gray-500);">Total Days</small>
                                                <div><i class="bi bi-clock"></i> <?php echo $rental['total_days']; ?> days</div>
                                            </div>
                                            <div>
                                                <small style="color: var(--gray-500);">Rental Fee</small>
                                                <div><i class="bi bi-cash"></i> <?php echo formatCurrency($rental['rental_fee']); ?></div>
                                            </div>
                                            <div>
                                                <small style="color: var(--gray-500);">Security Deposit</small>
                                                <div>
                                                    <i class="bi bi-shield-lock"></i> 
                                                    <?php echo formatCurrency($rental['security_deposit']); ?>
                                                    <span class="badge badge-<?php echo $rental['deposit_status'] === 'held' ? 'warning' : ($rental['deposit_status'] === 'released' ? 'success' : 'danger'); ?>" style="margin-left: 0.5rem;">
                                                        <?php echo ucfirst($rental['deposit_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($is_overdue): ?>
                                            <div class="alert alert-danger" style="margin: 1rem 0;">
                                                <i class="bi bi-exclamation-triangle"></i>
                                                <strong>Late Fee:</strong> Your rental is overdue by <?php echo $days_overdue; ?> day(s). 
                                                Late fee of <?php echo formatCurrency($late_fee); ?> will be applied upon return.
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($rental['rental_status'] === 'completed'): ?>
                                            <div style="margin-top: 1rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius);">
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                                    <div>
                                                        <small style="color: var(--gray-500);">Pre-Rental Condition</small>
                                                        <p style="margin: 0.25rem 0 0; font-size: 0.875rem;">
                                                            <?php echo nl2br(htmlspecialchars($rental['pre_rental_condition'])); ?>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <small style="color: var(--gray-500);">Post-Rental Condition</small>
                                                        <p style="margin: 0.25rem 0 0; font-size: 0.875rem;">
                                                            <?php echo nl2br(htmlspecialchars($rental['post_rental_condition'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php if ($rental['late_fee'] > 0): ?>
                                                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--gray-200);">
                                                        <small style="color: var(--danger);">
                                                            <i class="bi bi-exclamation-circle"></i>
                                                            Late fee applied: <?php echo formatCurrency($rental['late_fee']); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <div style="text-align: right;">
                                        <?php if ($rental['rental_status'] === 'active' || $rental['rental_status'] === 'overdue'): ?>
                                            <button onclick="openReturnModal(<?php echo $rental['id']; ?>, '<?php echo htmlspecialchars($rental['tool_name']); ?>', <?php echo $late_fee; ?>)" 
                                                    class="btn btn-primary" style="margin-bottom: 0.5rem;">
                                                <i class="bi bi-arrow-return-left"></i> Return Tool
                                            </button>
                                        <?php endif; ?>
                                        <div>
                                            <a href="rental-details.php?id=<?php echo $rental['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="bi bi-eye"></i> Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h3>No Rentals Found</h3>
                    <p>You don't have any rentals matching your criteria.</p>
                    <a href="browse-tools.php" class="btn btn-primary">Browse Tools</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Return Modal -->
    <div id="returnModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="bi bi-arrow-return-left"></i> Return Tool</h3>
                <button onclick="closeReturnModal()" class="close-btn">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="rental_id" id="return_rental_id">
                    
                    <div class="alert alert-info" style="margin-bottom: 1rem;">
                        <i class="bi bi-info-circle"></i>
                        Returning: <strong id="return_tool_name"></strong>
                    </div>

                    <div id="late_fee_notice" class="alert alert-warning" style="margin-bottom: 1rem; display: none;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Late Fee Notice:</strong> A late fee of <span id="late_fee_amount"></span> will be applied.
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Post-Rental Condition *</label>
                        <textarea name="post_condition" class="form-control" rows="4" required
                                  placeholder="Describe the condition of the tool upon return. Note any damages, wear, or issues."></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-weight: normal; cursor: pointer;">
                            <input type="checkbox" name="condition_ok" value="1" style="width: auto; margin-top: 0.25rem;">
                            <span>I confirm the tool is returned in the same condition as received (normal wear excepted)</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeReturnModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="return_rental" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Confirm Return
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReturnModal(rentalId, toolName, lateFee) {
            document.getElementById('return_rental_id').value = rentalId;
            document.getElementById('return_tool_name').textContent = toolName;
            
            if (lateFee > 0) {
                document.getElementById('late_fee_notice').style.display = 'block';
                document.getElementById('late_fee_amount').textContent = '₱' + lateFee.toFixed(2);
            } else {
                document.getElementById('late_fee_notice').style.display = 'none';
            }
            
            document.getElementById('returnModal').classList.add('active');
        }

        function closeReturnModal() {
            document.getElementById('returnModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('returnModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReturnModal();
            }
        });
    </script>
</body>
</html>
