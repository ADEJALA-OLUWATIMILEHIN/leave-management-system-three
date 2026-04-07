<?php
/**
 * Leave Payment Tracking - HR Portal
 * Track all leave payments in one place
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || !is_hr()) {
    redirect('../login.php');
}

$user_name = $_SESSION['name'];

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$where_clause = "1=1";
if ($filter === 'pending') {
    $where_clause = "lp.Status = 'Pending'";
} elseif ($filter === 'paid') {
    $where_clause = "lp.Status = 'Paid'";
}

// Fetch all leave payments (ONLY Annual Leave will have payments)
$sql = "SELECT 
            lp.PaymentID,
            lp.Amount,
            lp.Status,
            lp.PaymentReference,
            lp.PaymentMethod,
            lp.ProcessedDate,
            lp.CreatedAt,
            emp.FirstName + ' ' + emp.LastName as EmployeeName,
            emp.Department,
            emp.EmployeeNumber,
            emp.Email as EmployeeEmail,
            lt.TypeName as LeaveType,
            lr.StartDate,
            lr.EndDate,
            lr.TotalDays,
            lr.RequestID
        FROM LeavePayments lp
        JOIN Users emp ON lp.EmployeeID = emp.UserID
        JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        WHERE $where_clause
        ORDER BY lp.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

// Get statistics — MUST come AFTER $stmt check above
$stats_sql = "SELECT 
                COUNT(*) as Total,
                SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as Pending,
                SUM(CASE WHEN Status = 'Paid' THEN 1 ELSE 0 END) as Paid,
                SUM(CASE WHEN Status = 'Pending' THEN Amount ELSE 0 END) as PendingAmount,
                SUM(CASE WHEN Status = 'Paid' THEN Amount ELSE 0 END) as PaidAmount,
                SUM(Amount) as TotalAmount
              FROM LeavePayments";
$stats_stmt = sqlsrv_query($conn, $stats_sql);

if ($stats_stmt === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

$stats = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Tracking - HR</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 5px; }
        .nav-menu { background: white; padding: 15px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; gap: 20px; }
        .nav-menu a { padding: 8px 16px; text-decoration: none; color: #333; border-radius: 5px; font-weight: 500; }
        .nav-menu a:hover { background: #f0f0f0; }
        .nav-menu a.active { background: #4facfe; color: white; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 13px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .number { font-size: 36px; font-weight: 700; color: #333; margin: 8px 0; }
        .stat-card .amount { font-size: 18px; font-weight: 600; color: #666; }
        .stat-card.total .number { color: #17a2b8; }
        .stat-card.pending .number { color: #ffc107; }
        .stat-card.paid .number { color: #28a745; }
        .filters { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-btn { padding: 10px 20px; border: 2px solid #e0e0e0; background: white; border-radius: 5px; cursor: pointer; font-weight: 600; text-decoration: none; color: #333; }
        .filter-btn.active { background: #4facfe; color: white; border-color: #4facfe; }
        .table-container { background: white; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
        th { padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #e0e0e0; font-size: 13px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
        .btn-mark-paid { padding: 6px 14px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <h1>&#128176; Leave Payment Tracking</h1>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="all_requests.php">All Requests</a>
        <a href="manage_employees.php">Employees</a>
        <a href="payment_tracking.php" class="active">&#128176; Payments</a>
        <a href="calendar.php">Calendar</a>
        <a href="reports_export.php">Export</a>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h3>Total Payments</h3>
                <div class="number"><?php echo $stats['Total'] ?? 0; ?></div>
                <div class="amount">&#8358;<?php echo number_format($stats['TotalAmount'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card pending">
                <h3>&#9203; Pending Payments</h3>
                <div class="number"><?php echo $stats['Pending'] ?? 0; ?></div>
                <div class="amount">&#8358;<?php echo number_format($stats['PendingAmount'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card paid">
                <h3>&#9989; Paid</h3>
                <div class="number"><?php echo $stats['Paid'] ?? 0; ?></div>
                <div class="amount">&#8358;<?php echo number_format($stats['PaidAmount'] ?? 0, 2); ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Payments</a>
            <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">&#9203; Pending</a>
            <a href="?filter=paid" class="filter-btn <?php echo $filter === 'paid' ? 'active' : ''; ?>">&#9989; Paid</a>
        </div>
        
        <!-- Payment Table -->
        <div class="table-container">
            <div class="table-header" style="padding: 20px 25px; border-bottom: 1px solid #f0f0f0;">
                <h2>Leave Payments</h2>
            </div>
            
            <?php if ($stmt !== false): ?>
                <?php if (sqlsrv_has_rows($stmt)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th>Days</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><strong>#<?php echo $payment['PaymentID']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['EmployeeName']); ?></strong><br>
                                <small style="color: #999;"><?php echo htmlspecialchars($payment['EmployeeNumber']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($payment['Department']); ?></td>
                            <td><?php echo htmlspecialchars($payment['LeaveType']); ?></td>
                            <td><?php echo $payment['TotalDays']; ?> days</td>
                            <td><strong style="color: #28a745;">&#8358;<?php echo number_format($payment['Amount'], 2); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                    <?php echo $payment['Status']; ?>
                                </span>
                            </td>
                            <td><?php echo $payment['PaymentReference'] ?? '-'; ?></td>
                            <td>
                                <?php
                                if (!empty($payment['ProcessedDate']) && $payment['ProcessedDate'] instanceof DateTime) {
                                    echo $payment['ProcessedDate']->format('M d, Y');
                                } elseif (!empty($payment['CreatedAt']) && $payment['CreatedAt'] instanceof DateTime) {
                                    echo $payment['CreatedAt']->format('M d, Y');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($payment['Status'] === 'Pending'): ?>
                                    <a href="mark_payment_paid.php?id=<?php echo $payment['PaymentID']; ?>" 
                                       class="btn-mark-paid"
                                       onclick="return confirm('Mark this payment as PAID?\n\nAmount: &#8358;<?php echo number_format($payment['Amount'], 2); ?>\nEmployee: <?php echo htmlspecialchars($payment['EmployeeName']); ?>');">
                                        Mark as Paid
                                    </a>
                                <?php else: ?>
                                    <span style="color: #28a745; font-weight: 600;">&#10003; Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">&#128176;</div>
                    <h3>No Payments Found</h3>
                    <p>No leave payments match your current filter.</p>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">&#10060;</div>
                    <h3>Database Error</h3>
                    <p>Unable to fetch payment records. Please try again.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
