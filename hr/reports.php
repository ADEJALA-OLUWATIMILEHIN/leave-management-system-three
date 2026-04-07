<?php
/**
 * Reports - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];
$current_year = date('Y');
$current_month = date('m');

// Overall Statistics
$overall_sql = "SELECT 
                (SELECT COUNT(*) FROM LeaveRequests WHERE YEAR(StartDate) = ?) as TotalRequests,
                (SELECT COUNT(*) FROM LeaveRequests WHERE HRApprovalStatus = 'approved' AND YEAR(StartDate) = ?) as Approved,
                (SELECT COUNT(*) FROM LeaveRequests WHERE HODApprovalStatus = 'rejected' OR HRApprovalStatus = 'rejected') as Rejected,
                (SELECT SUM(TotalDays) FROM LeaveRequests WHERE HRApprovalStatus = 'approved' AND YEAR(StartDate) = ?) as TotalDaysUsed";
$overall_params = array($current_year, $current_year, $current_year);
$overall_stmt = sqlsrv_query($conn, $overall_sql, $overall_params);
$overall = sqlsrv_fetch_array($overall_stmt, SQLSRV_FETCH_ASSOC);

// Leave by Department
$dept_sql = "SELECT 
                u.Department,
                COUNT(lr.RequestID) as RequestCount,
                SUM(CASE WHEN lr.HRApprovalStatus = 'approved' THEN lr.TotalDays ELSE 0 END) as ApprovedDays
             FROM Users u
             LEFT JOIN LeaveRequests lr ON u.UserID = lr.UserID AND YEAR(lr.StartDate) = ?
             WHERE u.Role = 'employee'
             GROUP BY u.Department
             ORDER BY ApprovedDays DESC";
$dept_params = array($current_year);
$dept_stmt = sqlsrv_query($conn, $dept_sql, $dept_params);

// Leave by Type
$type_sql = "SELECT 
                lt.TypeName,
                COUNT(lr.RequestID) as RequestCount,
                SUM(CASE WHEN lr.HRApprovalStatus = 'approved' THEN lr.TotalDays ELSE 0 END) as ApprovedDays
             FROM LeaveTypes lt
             LEFT JOIN LeaveRequests lr ON lt.LeaveTypeID = lr.LeaveTypeID AND YEAR(lr.StartDate) = ?
             GROUP BY lt.TypeName
             ORDER BY ApprovedDays DESC";
$type_params = array($current_year);
$type_stmt = sqlsrv_query($conn, $type_sql, $type_params);

// Monthly Trend
$monthly_sql = "SELECT 
                    MONTH(StartDate) as Month,
                    COUNT(*) as RequestCount,
                    SUM(TotalDays) as TotalDays
                FROM LeaveRequests
                WHERE YEAR(StartDate) = ? AND HRApprovalStatus = 'approved'
                GROUP BY MONTH(StartDate)
                ORDER BY MONTH(StartDate)";
$monthly_params = array($current_year);
$monthly_stmt = sqlsrv_query($conn, $monthly_sql, $monthly_params);

// Top Leave Takers
$top_users_sql = "SELECT TOP 10
                    u.FirstName + ' ' + u.LastName as EmployeeName,
                    u.Department,
                    COUNT(lr.RequestID) as RequestCount,
                    SUM(lr.TotalDays) as TotalDays
                  FROM Users u
                  JOIN LeaveRequests lr ON u.UserID = lr.UserID
                  WHERE lr.HRApprovalStatus = 'approved' AND YEAR(lr.StartDate) = ?
                  GROUP BY u.FirstName, u.LastName, u.Department
                  ORDER BY TotalDays DESC";
$top_users_params = array($current_year);
$top_users_stmt = sqlsrv_query($conn, $top_users_sql, $top_users_params);

$message = get_message();
// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Leave_Report_' . date('Y-m-d') . '.xls"');
    
    $sql = "SELECT lr.RequestID, u.FirstName + ' ' + u.LastName as Employee, u.Department, 
            lt.TypeName as LeaveType, lr.StartDate, lr.EndDate, lr.TotalDays, lr.Status
            FROM LeaveRequests lr
            JOIN Users u ON lr.UserID = u.UserID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            ORDER BY lr.CreatedAt DESC";
    $stmt = sqlsrv_query($conn, $sql);
    
    echo "<table border='1'>";
    echo "<tr><th colspan='8'>Sterling Assurance Nigeria Limited - Leave Report</th></tr>";
    echo "<tr><th>ID</th><th>Employee</th><th>Department</th><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Status</th></tr>";
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['RequestID'] . "</td>";
        echo "<td>" . htmlspecialchars($row['Employee']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Department']) . "</td>";
        echo "<td>" . htmlspecialchars($row['LeaveType']) . "</td>";
        echo "<td>" . $row['StartDate']->format('Y-m-d') . "</td>";
        echo "<td>" . $row['EndDate']->format('Y-m-d') . "</td>";
        echo "<td>" . $row['TotalDays'] . "</td>";
        echo "<td>" . ucfirst($row['Status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HR Admin</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 13px; margin-bottom: 10px; text-transform: uppercase; font-weight: 500; }
        .stat-card .number { font-size: 32px; font-weight: 700; color: #4facfe; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section-header { margin-bottom: 20px; }
        .section-header h2 { color: #333; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; padding: 12px; background: #f8f9fa; color: #666; font-weight: 600; font-size: 14px; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #333; font-size: 14px; }
        table tr:hover { background: #f8f9fa; }
        .chart-container { margin: 20px 0; }
        .bar { height: 30px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); margin: 10px 0; border-radius: 5px; position: relative; }
        .bar-label { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: white; font-weight: 600; font-size: 13px; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Admin - Reports & Analytics</h1>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="all_requests.php">All Requests</a>
        <a href="manage_employees.php">Manage Employees</a>
        <a href="manage_leave_types.php">Leave Types</a>
        <a href="reports.php" class="active">Reports</a>
        <a href="settings.php">Settings</a>
    </div>
    
    <a href="reports.php?export=excel" class="btn btn-primary" style="background: #28a745;">📥 Export to Excel</a>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Requests (<?php echo $current_year; ?>)</h3>
                <div class="number"><?php echo $overall['TotalRequests'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Approved Requests</h3>
                <div class="number"><?php echo $overall['Approved'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejected Requests</h3>
                <div class="number"><?php echo $overall['Rejected'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Days Used</h3>
                <div class="number"><?php echo $overall['TotalDaysUsed'] ?? 0; ?></div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="section">
                <div class="section-header">
                    <h2>Leave by Department</h2>
                </div>
                <div class="chart-container">
                    <?php 
                    $max_days = 1;
                    $dept_data = array();
                    while ($dept = sqlsrv_fetch_array($dept_stmt, SQLSRV_FETCH_ASSOC)) {
                        $dept_data[] = $dept;
                        if ($dept['ApprovedDays'] > $max_days) $max_days = $dept['ApprovedDays'];
                    }
                    
                    foreach ($dept_data as $dept): 
                        $width = ($dept['ApprovedDays'] / $max_days) * 100;
                    ?>
                        <div class="bar" style="width: <?php echo $width; ?>%">
                            <span class="bar-label"><?php echo htmlspecialchars($dept['Department']); ?>: <?php echo $dept['ApprovedDays']; ?> days</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h2>Leave by Type</h2>
                </div>
                <div class="chart-container">
                    <?php 
                    $max_days_type = 1;
                    $type_data = array();
                    while ($type = sqlsrv_fetch_array($type_stmt, SQLSRV_FETCH_ASSOC)) {
                        $type_data[] = $type;
                        if ($type['ApprovedDays'] > $max_days_type) $max_days_type = $type['ApprovedDays'];
                    }
                    
                    foreach ($type_data as $type): 
                        $width = ($type['ApprovedDays'] / $max_days_type) * 100;
                    ?>
                        <div class="bar" style="width: <?php echo $width; ?>%">
                            <span class="bar-label"><?php echo htmlspecialchars($type['TypeName']); ?>: <?php echo $type['ApprovedDays']; ?> days</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2>Monthly Trend (<?php echo $current_year; ?>)</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Requests</th>
                        <th>Total Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $months = array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
                    while ($month = sqlsrv_fetch_array($monthly_stmt, SQLSRV_FETCH_ASSOC)): 
                    ?>
                        <tr>
                            <td><?php echo $months[$month['Month']]; ?></td>
                            <td><?php echo $month['RequestCount']; ?></td>
                            <td><?php echo $month['TotalDays']; ?> days</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2>Top 10 Leave Takers</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Requests</th>
                        <th>Total Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = sqlsrv_fetch_array($top_users_stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['EmployeeName']); ?></td>
                            <td><?php echo htmlspecialchars($user['Department']); ?></td>
                            <td><?php echo $user['RequestCount']; ?></td>
                            <td><?php echo $user['TotalDays']; ?> days</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
if ($overall_stmt) sqlsrv_free_stmt($overall_stmt);
if ($dept_stmt) sqlsrv_free_stmt($dept_stmt);
if ($type_stmt) sqlsrv_free_stmt($type_stmt);
if ($monthly_stmt) sqlsrv_free_stmt($monthly_stmt);
if ($top_users_stmt) sqlsrv_free_stmt($top_users_stmt);
?>
