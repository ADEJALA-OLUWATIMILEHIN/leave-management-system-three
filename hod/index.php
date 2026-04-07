<?php
/**
 * HOD Dashboard
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HOD
if (!is_logged_in() || !is_hod()) {
    redirect('login.php');
}
// Force password change if required
if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true) {
    redirect('../change_password.php');
}

$user_name = $_SESSION['name'];
$user_department = $_SESSION['department'];
$current_year = date('Y');

// Get statistics for HOD's department only
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM LeaveRequests lr 
                 JOIN Users u ON lr.UserID = u.UserID 
                 WHERE lr.HODApprovalStatus = 'pending' AND u.Department = ?) as PendingHOD,
                (SELECT COUNT(*) FROM LeaveRequests lr 
                 JOIN Users u ON lr.UserID = u.UserID 
                 WHERE lr.HODApprovalStatus = 'approved' AND YEAR(lr.StartDate) = ? AND u.Department = ?) as ApprovedByHOD,
                (SELECT COUNT(*) FROM LeaveRequests lr 
                 JOIN Users u ON lr.UserID = u.UserID 
                 WHERE lr.HODApprovalStatus = 'rejected' AND u.Department = ?) as RejectedByHOD,
                (SELECT COUNT(*) FROM LeaveRequests lr 
                 JOIN Users u ON lr.UserID = u.UserID 
                 WHERE CAST(lr.CreatedAt AS DATE) = CAST(GETDATE() AS DATE) AND u.Department = ?) as TodayRequests";
$stats_params = array($user_department, $current_year, $user_department, $user_department, $user_department);
$stats_stmt = sqlsrv_query($conn, $stats_sql, $stats_params);
$stats = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC);

// Get pending requests from HOD's department only
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
                    lr.HRApprovalStatus
                FROM LeaveRequests lr
                JOIN Users u ON lr.UserID = u.UserID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.HODApprovalStatus = 'pending' AND u.Department = ?
                ORDER BY lr.CreatedAt ASC";
$pending_params = array($user_department);
$pending_stmt = sqlsrv_query($conn, $pending_sql, $pending_params);

// Get recent activity from HOD's department only
$activity_sql = "SELECT TOP 10
                    lr.RequestID,
                    u.FirstName + ' ' + u.LastName as EmployeeName,
                    lt.TypeName,
                    lr.HODApprovalStatus,
                    lr.HRApprovalStatus,
                    lr.HODApprovedDate
                FROM LeaveRequests lr
                JOIN Users u ON lr.UserID = u.UserID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.HODApprovalStatus IN ('approved', 'rejected') AND u.Department = ?
                ORDER BY lr.HODApprovedDate DESC";
$activity_params = array($user_department);
$activity_stmt = sqlsrv_query($conn, $activity_sql, $activity_params);

// Get any messages
$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - Leave Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .user-info { color: white; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 5px; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; font-weight: 500; }
        .stat-card .number { font-size: 36px; font-weight: 700; }
        .stat-card.pending .number { color: #ffc107; }
        .stat-card.approved .number { color: #28a745; }
        .stat-card.rejected .number { color: #dc3545; }
        .stat-card.today .number { color: #f5576c; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section-header { margin-bottom: 20px; }
        .section-header h2 { color: #333; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; padding: 12px; background: #f8f9fa; color: #666; font-weight: 600; font-size: 14px; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #333; font-size: 14px; }
        table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; color: white; }
        .btn-review { background: #f5576c; }
        .no-data { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HOD Dashboard - Leave Management (<?php echo htmlspecialchars($user_department); ?>)</h1>
        <div class="header-right">
            <span class="user-info">Welcome, <strong><?php echo $user_name; ?></strong> (<?php echo $user_department; ?>)</span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card pending">
                <h3>Pending (HOD Review)</h3>
                <div class="number"><?php echo $stats['PendingHOD']; ?></div>
            </div>
            <div class="stat-card approved">
                <h3>Approved by HOD</h3>
                <div class="number"><?php echo $stats['ApprovedByHOD']; ?></div>
            </div>
            <div class="stat-card rejected">
                <h3>Rejected by HOD</h3>
                <div class="number"><?php echo $stats['RejectedByHOD']; ?></div>
            </div>
            <div class="stat-card today">
                <h3>Requests Today</h3>
                <div class="number"><?php echo $stats['TodayRequests']; ?></div>
            </div>
        </div>
        
        <!-- Pending Requests -->
        <div class="section">
            <div class="section-header">
                <h2>Pending Leave Requests (<?php echo htmlspecialchars($user_department); ?> Department - Awaiting HOD Approval)</h2>
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
                <h2>Recent HOD Activity</h2>
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
                                    // FIX: Check that HODApprovedDate is not null before calling ->format()
                                    if (!empty($activity['HODApprovedDate']) && $activity['HODApprovedDate'] instanceof DateTime) {
                                        echo $activity['HODApprovedDate']->format('M d, Y g:i A');
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
