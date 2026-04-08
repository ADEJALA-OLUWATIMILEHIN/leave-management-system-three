<?php
/**
 * HR Admin Dashboard (Complete Admin System)
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];
$current_year = date('Y');

// Get comprehensive statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM LeaveRequests WHERE HODApprovalStatus = 'approved' AND HRApprovalStatus = 'pending') as PendingHR,
                (SELECT COUNT(*) FROM LeaveRequests WHERE HRApprovalStatus = 'approved' AND YEAR(StartDate) = ?) as ApprovedByHR,
                (SELECT COUNT(*) FROM LeaveRequests WHERE HRApprovalStatus = 'rejected') as RejectedByHR,
                (SELECT COUNT(*) FROM LeaveRequests WHERE CAST(CreatedAt AS DATE) = CAST(GETDATE() AS DATE)) as TodayRequests,
                (SELECT COUNT(*) FROM Users WHERE Role = 'employee' AND IsActive = 1) as TotalEmployees,
                (SELECT COUNT(*) FROM Users WHERE IsActive = 1) as TotalActiveUsers,
                (SELECT COUNT(*) FROM LeaveTypes WHERE IsActive = 1) as ActiveLeaveTypes";
$stats_params = array($current_year);
$stats_stmt = sqlsrv_query($conn, $stats_sql, $stats_params);
$stats = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC);

// Get pending requests (approved by HOD, waiting for HR)
$pending_sql = "SELECT TOP 20
                    lr.RequestID,
                    u.FirstName + ' ' + u.LastName as EmployeeName,
                    u.Email as EmployeeEmail,
                    u.Department,
                    lt.TypeName,
                    lr.StartDate,
                    lr.EndDate,
                    lr.TotalDays,
                    lr.Reason,
                    lr.CreatedAt,
                    lr.HODApprovalStatus,
                    lr.HODRemarks,
                    lr.HRApprovalStatus
                FROM LeaveRequests lr
                JOIN Users u ON lr.UserID = u.UserID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.HODApprovalStatus = 'approved' AND lr.HRApprovalStatus = 'pending'
                ORDER BY lr.CreatedAt ASC";
$pending_stmt = sqlsrv_query($conn, $pending_sql);

// Get recent activity
$activity_sql = "SELECT TOP 10
                    lr.RequestID,
                    u.FirstName + ' ' + u.LastName as EmployeeName,
                    lt.TypeName,
                    lr.HODApprovalStatus,
                    lr.HRApprovalStatus,
                    lr.HRApprovedDate
                FROM LeaveRequests lr
                JOIN Users u ON lr.UserID = u.UserID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.HRApprovalStatus IN ('approved', 'rejected')
                ORDER BY lr.HRApprovedDate DESC";
$activity_stmt = sqlsrv_query($conn, $activity_sql);

// Get any messages
$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Admin Dashboard - Leave Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .user-info { color: white; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 5px; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); }
        .nav-menu { background: white; padding: 15px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; gap: 20px; flex-wrap: wrap; }
        .nav-menu a { padding: 8px 16px; text-decoration: none; color: #333; border-radius: 5px; font-weight: 500; transition: background 0.2s; }
        .nav-menu a:hover { background: #f0f0f0; }
        .nav-menu a.active { background: #4facfe; color: white; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 13px; margin-bottom: 10px; text-transform: uppercase; font-weight: 500; }
        .stat-card .number { font-size: 32px; font-weight: 700; }
        .stat-card.pending .number { color: #ffc107; }
        .stat-card.approved .number { color: #28a745; }
        .stat-card.rejected .number { color: #dc3545; }
        .stat-card.today .number { color: #00f2fe; }
        .stat-card.employees .number { color: #667eea; }
        .stat-card.users .number { color: #f093fb; }
        .stat-card.leavetypes .number { color: #17a2b8; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section-header { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .section-header h2 { color: #333; font-size: 20px; }
        .btn-primary { padding: 10px 20px; background: #4facfe; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; padding: 12px; background: #f8f9fa; color: #666; font-weight: 600; font-size: 14px; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #333; font-size: 14px; }
        table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; color: white; }
        .btn-review { background: #00f2fe; }
        .no-data { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Admin Dashboard</h1>
        <div class="header-right">
            <span class="user-info">Welcome, <strong><?php echo $user_name; ?></strong> (HR Administrator)</span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="index.php" class="active">Dashboard</a>
        <a href="all_requests.php">All Requests</a>
        <a href="manage_employees.php">Manage Employees</a>
        <a href="calendar.php">Calendar</a>
        <a href="payment_tracking.php"> Payments</a>
        <a href="manage_leave_types.php">Leave Types</a>
        <a href="reports.php">Reports</a>
        <a href="reports_export.php">Export</a>
        <a href="settings.php">Settings</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card pending">
                <h3>Pending Review</h3>
                <div class="number"><?php echo $stats['PendingHR']; ?></div>
            </div>
            <div class="stat-card approved">
                <h3>Approved (<?php echo $current_year; ?>)</h3>
                <div class="number"><?php echo $stats['ApprovedByHR']; ?></div>
            </div>
            <div class="stat-card rejected">
                <h3>Rejected</h3>
                <div class="number"><?php echo $stats['RejectedByHR']; ?></div>
            </div>
            <div class="stat-card today">
                <h3>Today's Requests</h3>
                <div class="number"><?php echo $stats['TodayRequests']; ?></div>
            </div>
            <div class="stat-card employees">
                <h3>Total Employees</h3>
                <div class="number"><?php echo $stats['TotalEmployees']; ?></div>
            </div>
            <div class="stat-card users">
                <h3>Active Users</h3>
                <div class="number"><?php echo $stats['TotalActiveUsers']; ?></div>
            </div>
            <div class="stat-card leavetypes">
                <h3>Leave Types</h3>
                <div class="number"><?php echo $stats['ActiveLeaveTypes']; ?></div>
            </div>
        </div>
        
        <!-- Pending Requests -->
        <div class="section">
            <div class="section-header">
                <h2>Pending Leave Requests (Awaiting HR Final Approval)</h2>
                <a href="all_requests.php" class="btn-primary">View All Requests</a>
            </div>
            
            <?php if ($pending_stmt && sqlsrv_has_rows($pending_stmt)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>HOD Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = sqlsrv_fetch_array($pending_stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['EmployeeName']); ?></td>
                                <td><?php echo htmlspecialchars($request['Department']); ?></td>
                                <td><?php echo htmlspecialchars($request['TypeName']); ?></td>
                                <td><?php echo ($request['StartDate'] instanceof DateTime) ? $request['StartDate']->format('M d, Y') : 'N/A'; ?></td>
                                <td><?php echo ($request['EndDate'] instanceof DateTime) ? $request['EndDate']->format('M d, Y') : 'N/A'; ?></td>
                                <td><?php echo $request['TotalDays']; ?></td>
                                <td><span class="badge badge-approved">Approved</span></td>
                                <td><?php echo ($request['CreatedAt'] instanceof DateTime) ? $request['CreatedAt']->format('M d, Y') : 'N/A'; ?></td>
                                <td>
                                    <a href="review_request.php?id=<?php echo $request['RequestID']; ?>" class="btn-action btn-review">Review</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <p>No pending requests at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity -->
        <div class="section">
            <div class="section-header">
                <h2>Recent Activity</h2>
            </div>
            
            <?php if ($activity_stmt && sqlsrv_has_rows($activity_stmt)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>HOD Status</th>
                            <th>HR Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($activity = sqlsrv_fetch_array($activity_stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['EmployeeName']); ?></td>
                                <td><?php echo htmlspecialchars($activity['TypeName']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $activity['HODApprovalStatus']; ?>">
                                        <?php echo ucfirst($activity['HODApprovalStatus']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $activity['HRApprovalStatus']; ?>">
                                        <?php echo ucfirst($activity['HRApprovalStatus']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // FIX: Check that HRApprovedDate is not null before calling ->format()
                                    if (!empty($activity['HRApprovedDate']) && $activity['HRApprovedDate'] instanceof DateTime) {
                                        echo $activity['HRApprovedDate']->format('M d, Y g:i A');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <p>No recent activity.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if ($stats_stmt) sqlsrv_free_stmt($stats_stmt);
if ($pending_stmt) sqlsrv_free_stmt($pending_stmt);
if ($activity_stmt) sqlsrv_free_stmt($activity_stmt);
?>
