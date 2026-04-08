<?php
/**
 * Finance Dashboard
 * View and process leave payments
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'finance') {
    redirect('login.php');
}

$user_name = $_SESSION['name'];

// Get payment statistics
$stats_sql = "SELECT 
                COUNT(*) as TotalPayments,
                SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as PendingCount,
                SUM(CASE WHEN Status = 'Paid' THEN 1 ELSE 0 END) as PaidCount,
                SUM(CASE WHEN Status = 'Pending' THEN Amount ELSE 0 END) as PendingAmount,
                SUM(CASE WHEN Status = 'Paid' THEN Amount ELSE 0 END) as PaidAmount,
                SUM(Amount) as TotalAmount
              FROM LeavePayments";
$stats_stmt = sqlsrv_query($conn, $stats_sql);
$stats = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC);

// Get pending payments
$payments_sql = "SELECT 
                    lp.PaymentID,
                    lp.Amount,
                    lp.Status,
                    lp.CreatedAt,
                    emp.FirstName + ' ' + emp.LastName as EmployeeName,
                    emp.Email as EmployeeEmail,
                    emp.Department,
                    emp.EmployeeNumber,
                    lt.TypeName as LeaveType,
                    lr.TotalDays,
                    lr.StartDate,
                    lr.EndDate
                 FROM LeavePayments lp
                 JOIN Users emp ON lp.EmployeeID = emp.UserID
                 JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
                 JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                 WHERE lp.Status = 'Pending'
                 ORDER BY lp.CreatedAt DESC";
$payments_stmt = sqlsrv_query($conn, $payments_sql);

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        }
        .nav-menu a.active {
            color: #28a745;
            border-bottom-color: #28a745;
            background: #f8f9fa;
        }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #d4edda; color: #155724; }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }
        .stat-card .amount {
            font-size: 18px;
            font-weight: 600;
            color: #666;
            margin-top: 5px;
        }
        .stat-card.total { border-left: 4px solid #17a2b8; }
        .stat-card.total .number { color: #17a2b8; }
        .stat-card.pending { border-left: 4px solid #ffc107; }
        .stat-card.pending .number { color: #ffc107; }
        .stat-card.paid { border-left: 4px solid #28a745; }
        .stat-card.paid .number { color: #28a745; }
        
        /* Section */
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .section-header h2 { font-size: 20px; color: #333; }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
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
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
        
        .btn-pay {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        .btn-pay:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1> Finance Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="index.php" class="active">Dashboard</a>
        <a href="all_payments.php">All Payments</a>
        <a href="payment_history.php">Payment History</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type'] === 'success' ? 'success' : 'error'; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h3>Total Payments</h3>
                <div class="number"><?php echo $stats['TotalPayments'] ?? 0; ?></div>
                <div class="amount">₦<?php echo number_format($stats['TotalAmount'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card pending">
                <h3>⏳ Pending</h3>
                <div class="number"><?php echo $stats['PendingCount'] ?? 0; ?></div>
                <div class="amount">₦<?php echo number_format($stats['PendingAmount'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card paid">
                <h3>✅ Paid</h3>
                <div class="number"><?php echo $stats['PaidCount'] ?? 0; ?></div>
                <div class="amount">₦<?php echo number_format($stats['PaidAmount'] ?? 0, 2); ?></div>
            </div>
        </div>
        
        <!-- Pending Payments -->
        <div class="section">
            <div class="section-header">
                <h2>⏳ Pending Payments</h2>
            </div>
            
            <?php if ($payments_stmt !== false && sqlsrv_has_rows($payments_stmt)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Leave Period</th>
                        <th>Days</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($payment = sqlsrv_fetch_array($payments_stmt, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><strong>#<?php echo $payment['PaymentID']; ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['EmployeeName']); ?></strong><br>
                            <small style="color: #999;"><?php echo htmlspecialchars($payment['EmployeeNumber']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($payment['Department']); ?></td>
                        <td>
                            <?php echo $payment['StartDate']->format('M d'); ?> - 
                            <?php echo $payment['EndDate']->format('M d, Y'); ?>
                        </td>
                        <td><?php echo $payment['TotalDays']; ?> days</td>
                        <td><strong style="color: #28a745;">₦<?php echo number_format($payment['Amount'], 2); ?></strong></td>
                        <td>
                            <span class="status-badge status-pending">Pending</span>
                        </td>
                        <td>
                            <a href="process_payment.php?id=<?php echo $payment['PaymentID']; ?>" class="btn-pay">
                                💰 Process Payment
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">
                    ✅ No pending payments at this time
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if ($stats_stmt) sqlsrv_free_stmt($stats_stmt);
if ($payments_stmt) sqlsrv_free_stmt($payments_stmt);
?>