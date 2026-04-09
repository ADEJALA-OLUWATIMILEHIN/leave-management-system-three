<?php
/**
 * All Leave Requests - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];

// Fetch all leave requests
$requests_sql = "SELECT 
                    lr.RequestID,
                    u.FirstName + ' ' + u.LastName as EmployeeName,
                    u.Department,
                    lt.TypeName,
                    lr.StartDate,
                    lr.EndDate,
                    lr.TotalDays,
                    lr.CreatedAt,
                    lr.HODApprovalStatus,
                    lr.HRApprovalStatus,
                    lr.Status
                FROM LeaveRequests lr
                JOIN Users u ON lr.UserID = u.UserID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                ORDER BY lr.CreatedAt DESC";
$requests_stmt = sqlsrv_query($conn, $requests_sql);

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Requests - HR Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 5px; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); }
        .nav-menu { background: white; padding: 15px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; gap: 20px; }
        .nav-menu a { padding: 8px 16px; text-decoration: none; color: #333; border-radius: 5px; font-weight: 500; }
        .nav-menu a:hover { background: #f0f0f0; }
        .nav-menu a.active { background: #4facfe; color: white; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
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
        .badge-recalled { background: #f8d7da; color: #721c24; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; text-decoration: none; display: inline-block; color: white; background: #00f2fe; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Admin - All Leave Requests</h1>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
    
    <div class="nav-menu">
    <a href="index.php">Dashboard</a>
    <a href="all_requests.php" class="active">All Requests</a>
    <a href="manage_employees.php">Manage Employees</a>
    <a href="calendar.php">Calendar</a>
    <a href="payment_tracking.php"> Payments</a>
    <a href="manage_leave_types.php">Leave Types</a>
    <a href="reports.php">Reports</a>
    <a href="recall_employee.php"> Recall</a>
    <a href="reports_export.php">Export</a>
    <a href="settings.php">Settings</a>
</div>
    
    <div class="container">
        <div class="section">
            <div class="section-header">
                <h2>All Leave Requests</h2>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Leave Type</th>
                        <th>Start - End</th>
                        <th>Days</th>
                        <th>HOD Status</th>
                        <th>HR Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($request = sqlsrv_fetch_array($requests_stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['EmployeeName']); ?></td>
                            <td><?php echo htmlspecialchars($request['Department']); ?></td>
                            <td><?php echo htmlspecialchars($request['TypeName']); ?></td>
                            <td>
                                <?php echo ($request['StartDate'] instanceof DateTime) ? $request['StartDate']->format('M d') : 'N/A'; ?> &ndash;
                                <?php echo ($request['EndDate'] instanceof DateTime) ? $request['EndDate']->format('M d, Y') : 'N/A'; ?>
                            </td>
                            <td><?php echo $request['TotalDays']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $request['HODApprovalStatus']; ?>">
                                    <?php echo ucfirst($request['HODApprovalStatus']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $request['HRApprovalStatus']; ?>">
                                    <?php echo ucfirst($request['HRApprovalStatus']); ?>
                                </span>
                            </td>
                            <td><?php echo ($request['CreatedAt'] instanceof DateTime) ? $request['CreatedAt']->format('M d, Y') : 'N/A'; ?></td>
                            <td>
                                <a href="review_request.php?id=<?php echo $request['RequestID']; ?>" class="btn-action">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
if ($requests_stmt) sqlsrv_free_stmt($requests_stmt);
?>
