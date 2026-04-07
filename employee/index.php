<?php
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in()) {
    redirect('../login.php');
}
/**
 * Employee Dashboard
 * Leave Management System with Payment Tracking
 */

require_once __DIR__ . '/../config/database.php';

// Force password change if required
if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true) {
    redirect('../change_password.php');
}

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../login.php');
}

// Check if user is admin and redirect
if (function_exists('is_admin') && is_admin()) {
    redirect('../admin/index.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get current year
$current_year = date('Y');

// Fetch leave balances
$balance_sql = "SELECT lt.TypeName, lb.TotalDays, lb.UsedDays, lb.RemainingDays
                FROM LeaveBalances lb
                JOIN LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
                WHERE lb.UserID = ? AND lb.Year = ?
                ORDER BY lt.TypeName";
$balance_params = array($user_id, $current_year);
$balance_stmt = sqlsrv_query($conn, $balance_sql, $balance_params);

// Get leave statistics
$stats_sql = "SELECT 
                COUNT(*) as TotalRequests,
                SUM(CASE WHEN HODApprovalStatus = 'pending' THEN 1 ELSE 0 END) as PendingHOD,
                SUM(CASE WHEN HODApprovalStatus = 'approved' AND HRApprovalStatus = 'pending' THEN 1 ELSE 0 END) as PendingHR,
                SUM(CASE WHEN HRApprovalStatus = 'approved' THEN 1 ELSE 0 END) as Approved,
                SUM(CASE WHEN HODApprovalStatus = 'rejected' OR HRApprovalStatus = 'rejected' THEN 1 ELSE 0 END) as Rejected
              FROM LeaveRequests
              WHERE UserID = ?";
$stats_stmt = sqlsrv_query($conn, $stats_sql, array($user_id));
$stats = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC);

// Get recent leave requests with payment info
$requests_sql = "SELECT 
                    lr.RequestID,
                    lr.StartDate,
                    lr.EndDate,
                    lr.TotalDays,
                    lr.Status,
                    lr.HODApprovalStatus,
                    lr.HRApprovalStatus,
                    lr.CreatedAt as SubmittedAt,
                    lt.TypeName as LeaveType,
                    lp.PaymentID,
                    lp.Amount,
                    lp.Status as PaymentStatus,
                    lp.RequestedByEmployee,
                    lp.PaymentSplitChoice,
                    lp.FirstPaymentAmount,
                    lp.SecondPaymentAmount,
                    lp.FirstPaymentStatus,
                    lp.SecondPaymentStatus,
                    lp.PaymentReference
                 FROM LeaveRequests lr
                 JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                 LEFT JOIN LeavePayments lp ON lr.RequestID = lp.RequestID
                 WHERE lr.UserID = ?
                 ORDER BY lr.CreatedAt DESC";
$requests_stmt = sqlsrv_query($conn, $requests_sql, array($user_id));

// Get payment statistics
$payment_stats_sql = "SELECT 
                        COUNT(*) as TotalPayments,
                        SUM(CASE WHEN Status = 'Pending' THEN Amount ELSE 0 END) as PendingAmount,
                        SUM(CASE WHEN Status = 'Paid' THEN Amount ELSE 0 END) as PaidAmount,
                        SUM(Amount) as TotalAmount
                      FROM LeavePayments
                      WHERE EmployeeID = ?";
$payment_stats_stmt = sqlsrv_query($conn, $payment_stats_sql, array($user_id));
$payment_stats = sqlsrv_fetch_array($payment_stats_stmt, SQLSRV_FETCH_ASSOC);

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 28px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .btn-logout {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .nav-menu {
            background: white;
            padding: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
        }
        .nav-menu a {
            padding: 18px 25px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: 0.3s;
        }
        .nav-menu a:hover { background: #f8f9fa; }
        .nav-menu a.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f8f9fa;
        }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stat-card h3 {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #333;
        }
        .stat-card .sub-number {
            font-size: 18px;
            font-weight: 600;
            color: #666;
            margin-top: 5px;
        }
        .stat-card.primary { border-left: 4px solid #667eea; }
        .stat-card.primary .number { color: #667eea; }
        .stat-card.success { border-left: 4px solid #28a745; }
        .stat-card.success .number { color: #28a745; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.warning .number { color: #ffc107; }
        .stat-card.danger { border-left: 4px solid #dc3545; }
        .stat-card.danger .number { color: #dc3545; }
        
        /* Section */
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .section-header h2 {
            font-size: 20px;
            color: #333;
        }
        .btn-primary {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f8f9fa;
        }
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
        }
        td {
            padding: 18px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-paid { background: #d4edda; color: #155724; }
        
        .payment-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 13px;
        }
        .payment-info strong {
            color: #28a745;
        }
        
        .btn-request-payment {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        .btn-request-payment:hover {
            background: #218838;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Employee Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="index.php" class="active">Dashboard</a>
        <a href="submit_leave.php">Apply for Leave</a>
        <a href="leave_history.php">Leave History</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type'] === 'success' ? 'success' : 'error'; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <h3>Total Requests</h3>
                <div class="number"><?php echo $stats['TotalRequests'] ?? 0; ?></div>
            </div>
            <div class="stat-card warning">
                <h3>Pending HOD</h3>
                <div class="number"><?php echo $stats['PendingHOD'] ?? 0; ?></div>
            </div>
            <div class="stat-card warning">
                <h3>Pending HR</h3>
                <div class="number"><?php echo $stats['PendingHR'] ?? 0; ?></div>
            </div>
            <div class="stat-card success">
                <h3>Approved</h3>
                <div class="number"><?php echo $stats['Approved'] ?? 0; ?></div>
            </div>
            <div class="stat-card danger">
                <h3>Rejected</h3>
                <div class="number"><?php echo $stats['Rejected'] ?? 0; ?></div>
            </div>
            <div class="stat-card success">
                <h3>💰 Total Allowance</h3>
                <div class="number" style="font-size: 24px;">₦<?php echo number_format($payment_stats['TotalAmount'] ?? 0, 2); ?></div>
            </div>
        </div>
        
        <!-- Leave Balance -->
        <div class="section">
            <div class="section-header">
                <h2>Leave Balance (<?php echo $current_year; ?>)</h2>
            </div>
            
            <?php if ($balance_stmt && sqlsrv_has_rows($balance_stmt)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Total Days</th>
                            <th>Used Days</th>
                            <th>Remaining Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($balance = sqlsrv_fetch_array($balance_stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($balance['TypeName']); ?></td>
                                <td><?php echo $balance['TotalDays']; ?></td>
                                <td><?php echo $balance['UsedDays']; ?></td>
                                <td><strong><?php echo $balance['RemainingDays']; ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No leave balance data available.</div>
            <?php endif; ?>
        </div>
        
        <!-- Leave Requests & Payments -->
        <div class="section">
            <div class="section-header">
                <h2>My Leave Requests & Payments</h2>
                <a href="submit_leave.php" class="btn-primary">+ Apply for Leave</a>
            </div>
            
            <?php if ($requests_stmt && sqlsrv_has_rows($requests_stmt)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>HOD Status</th>
                        <th>HR Status</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($req = sqlsrv_fetch_array($requests_stmt, SQLSRV_FETCH_ASSOC)): 
                        $hodStatus = $req['HODApprovalStatus'] ?? 'pending';
                        $hrStatus = $req['HRApprovalStatus'] ?? 'pending';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req['LeaveType']); ?></strong></td>
                        <td>
                            <?php echo $req['StartDate']->format('M d, Y'); ?> - 
                            <?php echo $req['EndDate']->format('M d, Y'); ?>
                        </td>
                        <td><?php echo $req['TotalDays']; ?> days</td>
                        <td>
                            <span class="status-badge status-<?php echo $hodStatus; ?>">
                                <?php echo ucfirst($hodStatus); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($hodStatus === 'approved'): ?>
                                <span class="status-badge status-<?php echo $hrStatus; ?>">
                                    <?php echo ucfirst($hrStatus); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($req['PaymentID']): ?>
                                <span class="status-badge status-<?php echo strtolower($req['PaymentStatus']); ?>">
                                    <?php echo $req['PaymentStatus']; ?>
                                </span>
                                
                                <div class="payment-info">
                                    <strong>Amount: ₦<?php echo number_format($req['Amount'], 2); ?></strong>
                                    
                                    <?php if ($req['PaymentSplitChoice'] === 'split'): ?>
                                        <br>Split: ₦<?php echo number_format($req['FirstPaymentAmount'], 2); ?> + 
                                        ₦<?php echo number_format($req['SecondPaymentAmount'], 2); ?>
                                        <br>
                                        <small>
                                            First: <?php echo $req['FirstPaymentStatus']; ?> | 
                                            Second: <?php echo $req['SecondPaymentStatus']; ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['PaymentReference']): ?>
                                        <br><small>Ref: <?php echo $req['PaymentReference']; ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hrStatus === 'approved' && $req['PaymentID'] && !$req['RequestedByEmployee']): ?>
                                <a href="request_payment.php?id=<?php echo $req['PaymentID']; ?>" 
                                   class="btn-request-payment">
                                     Request Payment
                                </a>
                            <?php elseif ($hrStatus === 'approved' && $req['PaymentID'] && $req['RequestedByEmployee']): ?>
                                <span style="color: #28a745; font-weight: 600;">✓ Requested</span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="no-data">
                    <p>No leave requests yet.</p>
                    <a href="submit_leave.php" class="btn-primary" style="margin-top: 15px;">Submit Your First Request</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if ($balance_stmt) sqlsrv_free_stmt($balance_stmt);
if ($requests_stmt) sqlsrv_free_stmt($requests_stmt);
if ($stats_stmt) sqlsrv_free_stmt($stats_stmt);
if ($payment_stats_stmt) sqlsrv_free_stmt($payment_stats_stmt);
?>