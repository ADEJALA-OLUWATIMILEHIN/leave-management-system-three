<?php
/**
 * Employee Dashboard
 */

require_once __DIR__ . '/../config/database.php';

// Check if logged in as employee
if (!is_logged_in()) {
    redirect('../login.php');
}

// Redirect to correct portal based on role
$role = $_SESSION['role'] ?? '';
if ($role === 'hr')      redirect('../hr/index.php');
if ($role === 'hod')     redirect('../hod/index.php');
if ($role === 'finance') redirect('../finance/index.php');
if ($role !== 'employee') redirect('../login.php');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get user details including annual leave allowance
$user_sql = "SELECT UserID, FirstName, LastName, Email, Department, Salary, AnnualLeaveAllowance 
             FROM Users 
             WHERE UserID = ?";
$user_stmt = sqlsrv_query($conn, $user_sql, array($user_id));
$user = sqlsrv_fetch_array($user_stmt, SQLSRV_FETCH_ASSOC);

// Get employee's leave balance
$balance_sql = "SELECT
                    lt.TypeName,
                    lb.TotalEntitlement,
                    lb.DaysUsed,
                    lb.DaysRemaining
                FROM LeaveBalances lb
                JOIN LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
                WHERE lb.UserID = ? AND lb.Year = YEAR(GETDATE())";
$balance_stmt = sqlsrv_query($conn, $balance_sql, array($user_id));

// Get employee's recent leave requests
$requests_sql = "SELECT TOP 5
                    lr.RequestID,
                    lt.TypeName,
                    lr.StartDate,
                    lr.EndDate,
                    lr.TotalDays,
                    lr.HODApprovalStatus,
                    lr.HRApprovalStatus,
                    lr.Status,
                    lr.CreatedAt
                FROM LeaveRequests lr
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.UserID = ?
                ORDER BY lr.CreatedAt DESC";
$requests_stmt = sqlsrv_query($conn, $requests_sql, array($user_id));

// Get payment info
$payment_sql = "SELECT COUNT(*) as TotalPayments,
                       SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as PendingPayments,
                       SUM(CASE WHEN Status = 'Paid' THEN Amount ELSE 0 END) as TotalPaid
                FROM LeavePayments 
                WHERE EmployeeID = ?";
$payment_stmt = sqlsrv_query($conn, $payment_sql, array($user_id));
$payment_stats = sqlsrv_fetch_array($payment_stmt, SQLSRV_FETCH_ASSOC);

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
            align-items: center;
        }
        .nav-menu a {
            padding: 18px 25px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            border-bottom: 3px solid transparent;
        }
        .nav-menu a.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f8f9fa;
        }
        .nav-menu a:hover { background: #f8f9fa; }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #17a2b8; }
        
        .stat-card h3 {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-card.success .number { color: #28a745; }
        .stat-card.warning .number { color: #ffc107; }
        .stat-card.info .number { color: #17a2b8; }
        
        .stat-card .sub-number {
            font-size: 14px;
            color: #999;
            margin-top: 5px;
        }
        
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
        
        .section-header h2 { font-size: 20px; color: #333; }
        
        .btn-primary {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        
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
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👤 Employee Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="index.php" class="active">Dashboard</a>
        <a href="submit_leave.php">Apply for Leave</a>
        <a href="my_requests.php">My Requests</a>
        <a href="../index.php" style="margin-left: auto; color: #667eea;">← Back to Main Portal</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- Annual Leave Allowance -->
            <div class="stat-card success">
                <h3>💰 Annual Leave Allowance</h3>
                <div class="number" style="font-size: 28px;">
                    ₦<?php echo number_format($user['AnnualLeaveAllowance'] ?? 0, 2); ?>
                </div>
                <div class="sub-number">Per Year</div>
            </div>
            
            <!-- Leave Balance Stats -->
            <?php if ($balance_stmt && sqlsrv_has_rows($balance_stmt)): ?>
                <?php while ($bal = sqlsrv_fetch_array($balance_stmt, SQLSRV_FETCH_ASSOC)): ?>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($bal['TypeName']); ?></h3>
                    <div class="number"><?php echo $bal['DaysRemaining'] ?? 0; ?></div>
                    <div class="sub-number">of <?php echo $bal['TotalEntitlement'] ?? 0; ?> days remaining</div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
            
            <!-- Payment Stats -->
            <?php if ($payment_stats): ?>
            <div class="stat-card info">
                <h3>💳 Total Payments</h3>
                <div class="number">₦<?php echo number_format($payment_stats['TotalPaid'] ?? 0, 2); ?></div>
                <div class="sub-number"><?php echo $payment_stats['PendingPayments'] ?? 0; ?> pending</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Leave Requests -->
        <div class="section">
            <div class="section-header">
                <h2>📋 Recent Leave Requests</h2>
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
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($req = sqlsrv_fetch_array($requests_stmt, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req['TypeName']); ?></strong></td>
                        <td>
                            <?php echo $req['StartDate']->format('M d'); ?> - 
                            <?php echo $req['EndDate']->format('M d, Y'); ?>
                        </td>
                        <td><?php echo $req['TotalDays']; ?> days</td>
                        <td>
                            <span class="status-badge status-<?php echo $req['HODApprovalStatus']; ?>">
                                <?php echo ucfirst($req['HODApprovalStatus']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $req['HRApprovalStatus']; ?>">
                                <?php echo ucfirst($req['HRApprovalStatus']); ?>
                            </span>
                        </td>
                        <td><?php echo $req['CreatedAt']->format('M d, Y'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No leave requests yet.</p>
                    <a href="submit_leave.php" class="btn-primary" style="display: inline-block; margin-top: 15px;">Apply for Leave</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if ($user_stmt) sqlsrv_free_stmt($user_stmt);
if ($balance_stmt) sqlsrv_free_stmt($balance_stmt);
if ($requests_stmt) sqlsrv_free_stmt($requests_stmt);
if ($payment_stmt) sqlsrv_free_stmt($payment_stmt);
?>