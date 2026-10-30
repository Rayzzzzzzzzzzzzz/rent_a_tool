<?php


function generatePDF($title, $content, $filename = 'report.pdf') {
    // Start output buffering
    ob_start();
    
    // Generate full HTML for PDF
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            font-size: 24pt;
            margin: 0 0 10px 0;
        }
        .header p {
            color: #666;
            font-size: 10pt;
            margin: 5px 0;
        }
        .logo {
            font-size: 18pt;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        h2 {
            color: #1e293b;
            font-size: 16pt;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
            margin-top: 25px;
        }
        h3 {
            color: #334155;
            font-size: 13pt;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10pt;
        }
        th {
            background: #f1f5f9;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
        }
        tr:nth-child(even) {
            background: #f8fafc;
        }
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: #f1f5f9;
            border-left: 4px solid #2563eb;
            padding: 15px;
            min-width: 150px;
            flex: 1;
        }
        .stat-value {
            font-size: 20pt;
            font-weight: bold;
            color: #2563eb;
        }
        .stat-label {
            font-size: 9pt;
            color: #64748b;
            text-transform: uppercase;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 9pt;
            color: #94a3b8;
        }
        .section {
            margin-bottom: 30px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background: #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Rent-A-Tool</div>
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>Generated on: ' . date('F d, Y \a\t g:i A') . '</p>
        <p>Report ID: RPT-' . date('Ymd') . '-' . rand(1000, 9999) . '</p>
    </div>
    
    ' . $content . '
    
    <div class="footer">
        <p>© ' . date('Y') . ' Rent-A-Tool. All rights reserved.</p>
        <p>This is a computer-generated report.</p>
    </div>
</body>
</html>';
    
    // Save HTML to temp file
    $tempFile = sys_get_temp_dir() . '/report_' . time() . '.html';
    file_put_contents($tempFile, $html);
    
    // Use wkhtmltopdf if available, otherwise return HTML
    $wkhtmltopdf = shell_exec('which wkhtmltopdf');
    
    if ($wkhtmltopdf) {
        $pdfFile = sys_get_temp_dir() . '/report_' . time() . '.pdf';
        $cmd = "wkhtmltopdf --page-size A4 --margin-top 15mm --margin-bottom 15mm --margin-left 15mm --margin-right 15mm \"$tempFile\" \"$pdfFile\" 2>&1";
        shell_exec($cmd);
        
        if (file_exists($pdfFile)) {
            // Send PDF to browser
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($pdfFile));
            readfile($pdfFile);
            
            // Cleanup
            unlink($tempFile);
            unlink($pdfFile);
            exit;
        }
    }
    
    // Fallback: output HTML with print
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    
    // Cleanup
    unlink($tempFile);
    exit;
}

//  function to generate user rental report
function generateUserRentalReport($conn, $user_id, $rental_id = null) {
    // Get user info
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    $content = '<div class="section">';
    $content .= '<h2>Customer Information</h2>';
    $content .= '<div class="info-row"><span class="info-label">Name:</span> <span>' . htmlspecialchars($user['fullname']) . '</span></div>';
    $content .= '<div class="info-row"><span class="info-label">Email:</span> <span>' . htmlspecialchars($user['email']) . '</span></div>';
    $content .= '<div class="info-row"><span class="info-label">Phone:</span> <span>' . htmlspecialchars($user['phone'] ?: 'N/A') . '</span></div>';
    $content .= '</div>';
    
    // Get rental details
    if ($rental_id) {
        $stmt = $conn->prepare("
            SELECT r.*, t.name as tool_name, t.description as tool_description,
                   c.name as category_name, u.fullname as owner_name
            FROM rentals r 
            JOIN tools t ON r.tool_id = t.id 
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN users u ON t.owner_id = u.id
            WHERE r.id = ? AND r.renter_id = ?
        ");
        $stmt->bind_param("ii", $rental_id, $user_id);
        $stmt->execute();
        $rental = $stmt->get_result()->fetch_assoc();
        
        if ($rental) {
            $content .= '<div class="section">';
            $content .= '<h2>Rental Details</h2>';
            $content .= '<div class="info-row"><span class="info-label">Rental ID:</span> <span>#' . $rental['id'] . '</span></div>';
            $content .= '<div class="info-row"><span class="info-label">Tool:</span> <span>' . htmlspecialchars($rental['tool_name']) . '</span></div>';
            $content .= '<div class="info-row"><span class="info-label">Category:</span> <span>' . htmlspecialchars($rental['category_name']) . '</span></div>';
            $content .= '<div class="info-row"><span class="info-label">Owner:</span> <span>' . htmlspecialchars($rental['owner_name']) . '</span></div>';
            $content .= '<div class="info-row"><span class="info-label">Rental Period:</span> <span>' . date('M d, Y', strtotime($rental['start_date'])) . ' - ' . date('M d, Y', strtotime($rental['end_date'])) . '</span></div>';
            $content .= '<div class="info-row"><span class="info-label">Total Days:</span> <span>' . $rental['total_days'] . ' days</span></div>';
            $content .= '<div class="info-row"><span class="info-label">Status:</span> <span class="badge badge-' . getStatusColor($rental['rental_status']) . '">' . ucfirst($rental['rental_status']) . '</span></div>';
            $content .= '</div>';
            
            $content .= '<div class="section">';
            $content .= '<h2>Cost Breakdown</h2>';
            $content .= '<table>';
            $content .= '<tr><td>Rental Fee</td><td class="text-right">' . formatCurrency($rental['rental_fee']) . '</td></tr>';
            $content .= '<tr><td>Security Deposit</td><td class="text-right">' . formatCurrency($rental['security_deposit']) . '</td></tr>';
            if ($rental['late_fee'] > 0) {
                $content .= '<tr><td>Late Fee</td><td class="text-right">' . formatCurrency($rental['late_fee']) . '</td></tr>';
            }
            $content .= '<tr class="total-row"><td>Total Amount</td><td class="text-right">' . formatCurrency($rental['total_amount']) . '</td></tr>';
            $content .= '</table>';
            $content .= '</div>';
            
            if ($rental['pre_rental_condition']) {
                $content .= '<div class="section">';
                $content .= '<h2>Condition Reports</h2>';
                $content .= '<h3>Pre-Rental Condition</h3>';
                $content .= '<p>' . nl2br(htmlspecialchars($rental['pre_rental_condition'])) . '</p>';
                if ($rental['post_rental_condition']) {
                    $content .= '<h3>Post-Rental Condition</h3>';
                    $content .= '<p>' . nl2br(htmlspecialchars($rental['post_rental_condition'])) . '</p>';
                }
                $content .= '</div>';
            }
            
            $title = 'Rental Receipt #' . $rental_id;
            $filename = 'rental_receipt_' . $rental_id . '.pdf';
        }
    } else {
        // Get all rentals summary
        $stmt = $conn->prepare("
            SELECT r.*, t.name as tool_name
            FROM rentals r 
            JOIN tools t ON r.tool_id = t.id 
            WHERE r.renter_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $rentals = $stmt->get_result();
        
        // Calculate totals
        $totalRentals = $rentals->num_rows;
        $totalSpent = 0;
        $activeRentals = 0;
        $heldDeposits = 0;
        
        $rentalsData = [];
        while ($r = $rentals->fetch_assoc()) {
            $rentalsData[] = $r;
            $totalSpent += $r['total_amount'];
            if (in_array($r['rental_status'], ['active', 'pending'])) {
                $activeRentals++;
            }
            if ($r['deposit_status'] === 'held') {
                $heldDeposits += $r['security_deposit'];
            }
        }
        
        $content .= '<div class="section">';
        $content .= '<h2>Rental Summary</h2>';
        $content .= '<div class="stats-grid">';
        $content .= '<div class="stat-box"><div class="stat-value">' . $totalRentals . '</div><div class="stat-label">Total Rentals</div></div>';
        $content .= '<div class="stat-box"><div class="stat-value">' . $activeRentals . '</div><div class="stat-label">Active</div></div>';
        $content .= '<div class="stat-box"><div class="stat-value">' . formatCurrency($totalSpent) . '</div><div class="stat-label">Total Spent</div></div>';
        $content .= '<div class="stat-box"><div class="stat-value">' . formatCurrency($heldDeposits) . '</div><div class="stat-label">Held Deposits</div></div>';
        $content .= '</div>';
        $content .= '</div>';
        
        $content .= '<div class="section">';
        $content .= '<h2>Rental History</h2>';
        $content .= '<table>';
        $content .= '<thead><tr><th>ID</th><th>Tool</th><th>Period</th><th>Amount</th><th>Status</th></tr></thead>';
        $content .= '<tbody>';
        foreach ($rentalsData as $r) {
            $content .= '<tr>';
            $content .= '<td>#' . $r['id'] . '</td>';
            $content .= '<td>' . htmlspecialchars($r['tool_name']) . '</td>';
            $content .= '<td>' . date('M d', strtotime($r['start_date'])) . ' - ' . date('M d, Y', strtotime($r['end_date'])) . '</td>';
            $content .= '<td class="text-right">' . formatCurrency($r['total_amount']) . '</td>';
            $content .= '<td><span class="badge badge-' . getStatusColor($r['rental_status']) . '">' . ucfirst($r['rental_status']) . '</span></td>';
            $content .= '</tr>';
        }
        $content .= '</tbody>';
        $content .= '</table>';
        $content .= '</div>';
        
        $title = 'My Rental Report';
        $filename = 'my_rental_report_' . date('Ymd') . '.pdf';
    }
    
    generatePDF($title, $content, $filename);
}

// function to generate admin report
function generateAdminReport($conn, $reportType = 'summary') {
    $content = '';
    $title = '';
    $filename = '';
    
    switch ($reportType) {
        case 'summary':
            $title = 'Platform Summary Report';
            $filename = 'platform_summary_' . date('Ymd') . '.pdf';
            
            // Get statistics
            $stats = $conn->query("
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
                    (SELECT COUNT(*) FROM tools) as total_tools,
                    (SELECT COUNT(*) FROM rentals) as total_rentals,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM rentals WHERE rental_status = 'completed') as total_revenue,
                    (SELECT COUNT(*) FROM rentals WHERE rental_status IN ('active', 'pending')) as active_rentals,
                    (SELECT COALESCE(SUM(security_deposit), 0) FROM rentals WHERE deposit_status = 'held') as held_deposits
            ")->fetch_assoc();
            
            $content .= '<div class="section">';
            $content .= '<h2>Platform Overview</h2>';
            $content .= '<div class="stats-grid">';
            $content .= '<div class="stat-box"><div class="stat-value">' . $stats['total_users'] . '</div><div class="stat-label">Total Users</div></div>';
            $content .= '<div class="stat-box"><div class="stat-value">' . $stats['total_tools'] . '</div><div class="stat-label">Total Tools</div></div>';
            $content .= '<div class="stat-box"><div class="stat-value">' . $stats['total_rentals'] . '</div><div class="stat-label">Total Rentals</div></div>';
            $content .= '<div class="stat-box"><div class="stat-value">' . formatCurrency($stats['total_revenue']) . '</div><div class="stat-label">Total Revenue</div></div>';
            $content .= '<div class="stat-box"><div class="stat-value">' . $stats['active_rentals'] . '</div><div class="stat-label">Active Rentals</div></div>';
            $content .= '<div class="stat-box"><div class="stat-value">' . formatCurrency($stats['held_deposits']) . '</div><div class="stat-label">Held Deposits</div></div>';
            $content .= '</div>';
            $content .= '</div>';
            
            // Recent rentals
            $rentals = $conn->query("
                SELECT r.*, t.name as tool_name, u.fullname as renter_name
                FROM rentals r
                JOIN tools t ON r.tool_id = t.id
                JOIN users u ON r.renter_id = u.id
                ORDER BY r.created_at DESC
                LIMIT 20
            ");
            
            $content .= '<div class="section">';
            $content .= '<h2>Recent Rentals</h2>';
            $content .= '<table>';
            $content .= '<thead><tr><th>ID</th><th>Renter</th><th>Tool</th><th>Amount</th><th>Status</th></tr></thead>';
            $content .= '<tbody>';
            while ($r = $rentals->fetch_assoc()) {
                $content .= '<tr>';
                $content .= '<td>#' . $r['id'] . '</td>';
                $content .= '<td>' . htmlspecialchars($r['renter_name']) . '</td>';
                $content .= '<td>' . htmlspecialchars($r['tool_name']) . '</td>';
                $content .= '<td class="text-right">' . formatCurrency($r['total_amount']) . '</td>';
                $content .= '<td><span class="badge badge-' . getStatusColor($r['rental_status']) . '">' . ucfirst($r['rental_status']) . '</span></td>';
                $content .= '</tr>';
            }
            $content .= '</tbody>';
            $content .= '</table>';
            $content .= '</div>';
            break;
            
        case 'revenue':
            $title = 'Revenue Report';
            $filename = 'revenue_report_' . date('Ymd') . '.pdf';
            
            // Monthly revenue
            $monthly = $conn->query("
                SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                       COUNT(*) as rentals,
                       SUM(rental_fee) as rental_revenue,
                       SUM(late_fee) as late_fees,
                       SUM(total_amount) as total
                FROM rentals
                WHERE rental_status = 'completed'
                GROUP BY month
                ORDER BY month DESC
                LIMIT 12
            ");
            
            $content .= '<div class="section">';
            $content .= '<h2>Monthly Revenue</h2>';
            $content .= '<table>';
            $content .= '<thead><tr><th>Month</th><th>Rentals</th><th>Rental Revenue</th><th>Late Fees</th><th>Total</th></tr></thead>';
            $content .= '<tbody>';
            $totalRevenue = 0;
            while ($m = $monthly->fetch_assoc()) {
                $totalRevenue += $m['total'];
                $content .= '<tr>';
                $content .= '<td>' . date('F Y', strtotime($m['month'] . '-01')) . '</td>';
                $content .= '<td>' . $m['rentals'] . '</td>';
                $content .= '<td class="text-right">' . formatCurrency($m['rental_revenue']) . '</td>';
                $content .= '<td class="text-right">' . formatCurrency($m['late_fees']) . '</td>';
                $content .= '<td class="text-right"><strong>' . formatCurrency($m['total']) . '</strong></td>';
                $content .= '</tr>';
            }
            $content .= '<tr class="total-row"><td colspan="4">Total Revenue</td><td class="text-right">' . formatCurrency($totalRevenue) . '</td></tr>';
            $content .= '</tbody>';
            $content .= '</table>';
            $content .= '</div>';
            
            // Top tools
            $topTools = $conn->query("
                SELECT t.name, COUNT(r.id) as rental_count, SUM(r.total_amount) as revenue
                FROM rentals r
                JOIN tools t ON r.tool_id = t.id
                WHERE r.rental_status = 'completed'
                GROUP BY t.id, t.name
                ORDER BY revenue DESC
                LIMIT 10
            ");
            
            $content .= '<div class="section">';
            $content .= '<h2>Top Performing Tools</h2>';
            $content .= '<table>';
            $content .= '<thead><tr><th>Tool</th><th>Rentals</th><th>Revenue</th></tr></thead>';
            $content .= '<tbody>';
            while ($t = $topTools->fetch_assoc()) {
                $content .= '<tr>';
                $content .= '<td>' . htmlspecialchars($t['name']) . '</td>';
                $content .= '<td>' . $t['rental_count'] . '</td>';
                $content .= '<td class="text-right">' . formatCurrency($t['revenue']) . '</td>';
                $content .= '</tr>';
            }
            $content .= '</tbody>';
            $content .= '</table>';
            $content .= '</div>';
            break;
            
        case 'users':
            $title = 'User Activity Report';
            $filename = 'user_activity_' . date('Ymd') . '.pdf';
            
            $users = $conn->query("
                SELECT u.*, 
                       COUNT(r.id) as rental_count,
                       COALESCE(SUM(r.total_amount), 0) as total_spent
                FROM users u
                LEFT JOIN rentals r ON u.id = r.renter_id AND r.rental_status = 'completed'
                WHERE u.role = 'user'
                GROUP BY u.id
                ORDER BY total_spent DESC
            ");
            
            $content .= '<div class="section">';
            $content .= '<h2>User Activity</h2>';
            $content .= '<table>';
            $content .= '<thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Rentals</th><th>Total Spent</th></tr></thead>';
            $content .= '<tbody>';
            while ($u = $users->fetch_assoc()) {
                $content .= '<tr>';
                $content .= '<td>' . htmlspecialchars($u['fullname']) . '</td>';
                $content .= '<td>' . htmlspecialchars($u['email']) . '</td>';
                $content .= '<td><span class="badge badge-' . ($u['status'] === 'active' ? 'success' : 'danger') . '">' . ucfirst($u['status']) . '</span></td>';
                $content .= '<td>' . $u['rental_count'] . '</td>';
                $content .= '<td class="text-right">' . formatCurrency($u['total_spent']) . '</td>';
                $content .= '</tr>';
            }
            $content .= '</tbody>';
            $content .= '</table>';
            $content .= '</div>';
            break;
    }
    
    generatePDF($title, $content, $filename);
}
?>
