<?php
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Leave_Report_' . date('Y-m-d') . '.xls"');

$sql = "SELECT 
            lr.RequestID,
            u.FirstName + ' ' + u.LastName as EmployeeName,
            u.Department,
            lt.TypeName as LeaveType,
            lr.StartDate,
            lr.EndDate,
            lr.TotalDays,
            lr.Reason,
            lr.Status,
            lr.HODApprovalStatus,
            lr.HRApprovalStatus,
            lr.CreatedAt
        FROM LeaveRequests lr
        JOIN Users u ON lr.UserID = u.UserID
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        ORDER BY lr.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql);
?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
<table border="1">
    <tr style="background-color: #4472C4; color: white; font-weight: bold;">
        <td colspan="12" style="text-align: center; font-size: 16px;">Sterling Assurance Nigeria Limited - Leave Requests Report</td>
    </tr>
    <tr></tr>
    <tr style="background-color: #4472C4; color: white; font-weight: bold;">
        <td>Request ID</td>
        <td>Employee Name</td>
        <td>Department</td>
        <td>Leave Type</td>
        <td>Start Date</td>
        <td>End Date</td>
        <td>Days</td>
        <td>Reason</td>
        <td>HOD Status</td>
        <td>HR Status</td>
        <td>Final Status</td>
        <td>Date Submitted</td>
    </tr>
    <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
        $bgcolor = $row['Status'] == 'approved' ? '#C6EFCE' : ($row['Status'] == 'pending' ? '#FFEB9C' : '#FFC7CE');
    ?>
    <tr style="background-color: <?php echo $bgcolor; ?>;">
        <td><?php echo $row['RequestID']; ?></td>
        <td><?php echo htmlspecialchars($row['EmployeeName']); ?></td>
        <td><?php echo htmlspecialchars($row['Department']); ?></td>
        <td><?php echo htmlspecialchars($row['LeaveType']); ?></td>
        <td><?php echo $row['StartDate']->format('Y-m-d'); ?></td>
        <td><?php echo $row['EndDate']->format('Y-m-d'); ?></td>
        <td><?php echo $row['TotalDays']; ?></td>
        <td><?php echo htmlspecialchars($row['Reason']); ?></td>
        <td><?php echo ucfirst($row['HODApprovalStatus']); ?></td>
        <td><?php echo ucfirst($row['HRApprovalStatus']); ?></td>
        <td><?php echo ucfirst($row['Status']); ?></td>
        <td><?php echo $row['CreatedAt']->format('Y-m-d H:i'); ?></td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>