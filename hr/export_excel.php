<?php
/**
 * Excel Export - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

// Get export type
$export_type = isset($_GET['type']) ? $_GET['type'] : 'all_requests';
$current_year = date('Y');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Leave_Report_' . $export_type . '_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 
 <Styles>
  <Style ss:ID="header">
   <Font ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#4472C4" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  <Style ss:ID="title">
   <Font ss:Bold="1" ss:Size="16"/>
   <Alignment ss:Horizontal="Center"/>
  </Style>
  <Style ss:ID="approved">
   <Interior ss:Color="#C6EFCE" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="pending">
   <Interior ss:Color="#FFEB9C" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="rejected">
   <Interior ss:Color="#FFC7CE" ss:Pattern="Solid"/>
  </Style>
 </Styles>

<?php
// Export based on type
switch ($export_type) {
    case 'all_requests':
        export_all_requests($conn);
        break;
    case 'approved_requests':
        export_approved_requests($conn);
        break;
    case 'pending_requests':
        export_pending_requests($conn);
        break;
    case 'department_summary':
        export_department_summary($conn);
        break;
    case 'employee_balances':
        export_employee_balances($conn);
        break;
    case 'leave_usage':
        export_leave_usage($conn);
        break;
    default:
        export_all_requests($conn);
}

/**
 * Export All Leave Requests
 */
function export_all_requests($conn) {
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
    <Worksheet ss:Name="All Leave Requests">
     <Table>
      <Row>
       <Cell ss:StyleID="title" ss:MergeAcross="11">
        <Data ss:Type="String">Sterling Assurance Nigeria Limited - All Leave Requests</Data>
       </Cell>
      </Row>
      <Row></Row>
      <Row ss:StyleID="header">
       <Cell><Data ss:Type="String">Request ID</Data></Cell>
       <Cell><Data ss:Type="String">Employee Name</Data></Cell>
       <Cell><Data ss:Type="String">Department</Data></Cell>
       <Cell><Data ss:Type="String">Leave Type</Data></Cell>
       <Cell><Data ss:Type="String">Start Date</Data></Cell>
       <Cell><Data ss:Type="String">End Date</Data></Cell>
       <Cell><Data ss:Type="String">Days</Data></Cell>
       <Cell><Data ss:Type="String">Reason</Data></Cell>
       <Cell><Data ss:Type="String">HOD Status</Data></Cell>
       <Cell><Data ss:Type="String">HR Status</Data></Cell>
       <Cell><Data ss:Type="String">Final Status</Data></Cell>
       <Cell><Data ss:Type="String">Submitted Date</Data></Cell>
      </Row>
      <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
          $style = $row['Status'] == 'approved' ? 'approved' : ($row['Status'] == 'pending' ? 'pending' : 'rejected');
      ?>
      <Row ss:StyleID="<?php echo $style; ?>">
       <Cell><Data ss:Type="Number"><?php echo $row['RequestID']; ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['EmployeeName']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Department']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['LeaveType']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['StartDate']->format('Y-m-d'); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['EndDate']->format('Y-m-d'); ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalDays']; ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Reason']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo ucfirst($row['HODApprovalStatus']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo ucfirst($row['HRApprovalStatus']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo ucfirst($row['Status']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['CreatedAt']->format('Y-m-d H:i'); ?></Data></Cell>
      </Row>
      <?php endwhile; ?>
     </Table>
    </Worksheet>
    <?php
}

/**
 * Export Approved Requests
 */
function export_approved_requests($conn) {
    $sql = "SELECT 
                lr.RequestID,
                u.FirstName + ' ' + u.LastName as EmployeeName,
                u.Department,
                lt.TypeName as LeaveType,
                lr.StartDate,
                lr.EndDate,
                lr.TotalDays,
                lr.Reason,
                hod.FirstName + ' ' + hod.LastName as ApprovedByHOD,
                hr.FirstName + ' ' + hr.LastName as ApprovedByHR,
                lr.HRApprovedDate
            FROM LeaveRequests lr
            JOIN Users u ON lr.UserID = u.UserID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            LEFT JOIN Users hod ON lr.HODApprovedBy = hod.UserID
            LEFT JOIN Users hr ON lr.HRApprovedBy = hr.UserID
            WHERE lr.Status = 'approved'
            ORDER BY lr.HRApprovedDate DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    ?>
    <Worksheet ss:Name="Approved Requests">
     <Table>
      <Row>
       <Cell ss:StyleID="title" ss:MergeAcross="10">
        <Data ss:Type="String">Sterling Assurance Nigeria Limited - Approved Leave Requests</Data>
       </Cell>
      </Row>
      <Row></Row>
      <Row ss:StyleID="header">
       <Cell><Data ss:Type="String">Request ID</Data></Cell>
       <Cell><Data ss:Type="String">Employee</Data></Cell>
       <Cell><Data ss:Type="String">Department</Data></Cell>
       <Cell><Data ss:Type="String">Leave Type</Data></Cell>
       <Cell><Data ss:Type="String">Start Date</Data></Cell>
       <Cell><Data ss:Type="String">End Date</Data></Cell>
       <Cell><Data ss:Type="String">Days</Data></Cell>
       <Cell><Data ss:Type="String">Reason</Data></Cell>
       <Cell><Data ss:Type="String">Approved By HOD</Data></Cell>
       <Cell><Data ss:Type="String">Approved By HR</Data></Cell>
       <Cell><Data ss:Type="String">Approval Date</Data></Cell>
      </Row>
      <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
      <Row ss:StyleID="approved">
       <Cell><Data ss:Type="Number"><?php echo $row['RequestID']; ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['EmployeeName']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Department']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['LeaveType']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['StartDate']->format('Y-m-d'); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['EndDate']->format('Y-m-d'); ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalDays']; ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Reason']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['ApprovedByHOD'] ?? 'N/A'); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['ApprovedByHR'] ?? 'N/A'); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['HRApprovedDate'] ? $row['HRApprovedDate']->format('Y-m-d') : 'N/A'; ?></Data></Cell>
      </Row>
      <?php endwhile; ?>
     </Table>
    </Worksheet>
    <?php
}

/**
 * Export Pending Requests
 */
function export_pending_requests($conn) {
    $sql = "SELECT 
                lr.RequestID,
                u.FirstName + ' ' + u.LastName as EmployeeName,
                u.Department,
                lt.TypeName as LeaveType,
                lr.StartDate,
                lr.EndDate,
                lr.TotalDays,
                lr.Reason,
                lr.HODApprovalStatus,
                lr.HRApprovalStatus,
                lr.CreatedAt
            FROM LeaveRequests lr
            JOIN Users u ON lr.UserID = u.UserID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            WHERE lr.Status = 'pending'
            ORDER BY lr.CreatedAt DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    ?>
    <Worksheet ss:Name="Pending Requests">
     <Table>
      <Row>
       <Cell ss:StyleID="title" ss:MergeAcross="9">
        <Data ss:Type="String">Sterling Assurance Nigeria Limited - Pending Leave Requests</Data>
       </Cell>
      </Row>
      <Row></Row>
      <Row ss:StyleID="header">
       <Cell><Data ss:Type="String">Request ID</Data></Cell>
       <Cell><Data ss:Type="String">Employee</Data></Cell>
       <Cell><Data ss:Type="String">Department</Data></Cell>
       <Cell><Data ss:Type="String">Leave Type</Data></Cell>
       <Cell><Data ss:Type="String">Start Date</Data></Cell>
       <Cell><Data ss:Type="String">End Date</Data></Cell>
       <Cell><Data ss:Type="String">Days</Data></Cell>
       <Cell><Data ss:Type="String">Reason</Data></Cell>
       <Cell><Data ss:Type="String">HOD Status</Data></Cell>
       <Cell><Data ss:Type="String">HR Status</Data></Cell>
      </Row>
      <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
      <Row ss:StyleID="pending">
       <Cell><Data ss:Type="Number"><?php echo $row['RequestID']; ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['EmployeeName']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Department']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['LeaveType']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['StartDate']->format('Y-m-d'); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo $row['EndDate']->format('Y-m-d'); ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalDays']; ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Reason']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo ucfirst($row['HODApprovalStatus']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo ucfirst($row['HRApprovalStatus']); ?></Data></Cell>
      </Row>
      <?php endwhile; ?>
     </Table>
    </Worksheet>
    <?php
}

/**
 * Export Department Summary
 */
function export_department_summary($conn) {
    $sql = "SELECT 
                u.Department,
                COUNT(DISTINCT u.UserID) as TotalEmployees,
                COUNT(lr.RequestID) as TotalRequests,
                SUM(CASE WHEN lr.Status = 'approved' THEN 1 ELSE 0 END) as ApprovedRequests,
                SUM(CASE WHEN lr.Status = 'pending' THEN 1 ELSE 0 END) as PendingRequests,
                SUM(CASE WHEN lr.Status = 'rejected' THEN 1 ELSE 0 END) as RejectedRequests,
                SUM(CASE WHEN lr.Status = 'approved' THEN lr.TotalDays ELSE 0 END) as TotalDaysUsed
            FROM Users u
            LEFT JOIN LeaveRequests lr ON u.UserID = lr.UserID
            WHERE u.Role = 'employee' AND u.IsActive = 1
            GROUP BY u.Department
            ORDER BY u.Department";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    ?>
    <Worksheet ss:Name="Department Summary">
     <Table>
      <Row>
       <Cell ss:StyleID="title" ss:MergeAcross="6">
        <Data ss:Type="String">Sterling Assurance Nigeria Limited - Department Summary</Data>
       </Cell>
      </Row>
      <Row></Row>
      <Row ss:StyleID="header">
       <Cell><Data ss:Type="String">Department</Data></Cell>
       <Cell><Data ss:Type="String">Total Employees</Data></Cell>
       <Cell><Data ss:Type="String">Total Requests</Data></Cell>
       <Cell><Data ss:Type="String">Approved</Data></Cell>
       <Cell><Data ss:Type="String">Pending</Data></Cell>
       <Cell><Data ss:Type="String">Rejected</Data></Cell>
       <Cell><Data ss:Type="String">Total Days Used</Data></Cell>
      </Row>
      <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
      <Row>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Department']); ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalEmployees']; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalRequests'] ?? 0; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['ApprovedRequests'] ?? 0; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['PendingRequests'] ?? 0; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['RejectedRequests'] ?? 0; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalDaysUsed'] ?? 0; ?></Data></Cell>
      </Row>
      <?php endwhile; ?>
     </Table>
    </Worksheet>
    <?php
}

/**
 * Export Employee Leave Balances
 */
function export_employee_balances($conn) {
    $current_year = date('Y');
    $sql = "SELECT 
                u.FirstName + ' ' + u.LastName as EmployeeName,
                u.Department,
                u.Email,
                lt.TypeName as LeaveType,
                lb.TotalDays,
                lb.UsedDays,
                lb.RemainingDays
            FROM LeaveBalances lb
            JOIN Users u ON lb.UserID = u.UserID
            JOIN LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
            WHERE lb.Year = ? AND u.IsActive = 1
            ORDER BY u.Department, u.FirstName, lt.TypeName";
    
    $stmt = sqlsrv_query($conn, $sql, array($current_year));
    
    ?>
    <Worksheet ss:Name="Employee Balances">
     <Table>
      <Row>
       <Cell ss:StyleID="title" ss:MergeAcross="6">
        <Data ss:Type="String">Sterling Assurance Nigeria Limited - Employee Leave Balances <?php echo $current_year; ?></Data>
       </Cell>
      </Row>
      <Row></Row>
      <Row ss:StyleID="header">
       <Cell><Data ss:Type="String">Employee Name</Data></Cell>
       <Cell><Data ss:Type="String">Department</Data></Cell>
       <Cell><Data ss:Type="String">Email</Data></Cell>
       <Cell><Data ss:Type="String">Leave Type</Data></Cell>
       <Cell><Data ss:Type="String">Total Days</Data></Cell>
       <Cell><Data ss:Type="String">Used Days</Data></Cell>
       <Cell><Data ss:Type="String">Remaining Days</Data></Cell>
      </Row>
      <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
      <Row>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['EmployeeName']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Department']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['Email']); ?></Data></Cell>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['LeaveType']); ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalDays']; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['UsedDays']; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['RemainingDays']; ?></Data></Cell>
      </Row>
      <?php endwhile; ?>
     </Table>
    </Worksheet>
    <?php
}

/**
 * Export Leave Usage Report
 */
function export_leave_usage($conn) {
    $current_year = date('Y');
    $sql = "SELECT 
                lt.TypeName as LeaveType,
                COUNT(lr.RequestID) as TotalRequests,
                SUM(CASE WHEN lr.Status = 'approved' THEN 1 ELSE 0 END) as ApprovedRequests,
                SUM(CASE WHEN lr.Status = 'approved' THEN lr.TotalDays ELSE 0 END) as TotalDaysUsed,
                AVG(CASE WHEN lr.Status = 'approved' THEN CAST(lr.TotalDays as FLOAT) ELSE 0 END) as AvgDaysPerRequest
            FROM LeaveTypes lt
            LEFT JOIN LeaveRequests lr ON lt.LeaveTypeID = lr.LeaveTypeID AND YEAR(lr.CreatedAt) = ?
            GROUP BY lt.TypeName
            ORDER BY TotalDaysUsed DESC";
    
    $stmt = sqlsrv_query($conn, $sql, array($current_year));
    
    ?>
    <Worksheet ss:Name="Leave Usage">
     <Table>
      <Row>
       <Cell ss:StyleID="title" ss:MergeAcross="4">
        <Data ss:Type="String">Sterling Assurance Nigeria Limited - Leave Usage Report <?php echo $current_year; ?></Data>
       </Cell>
      </Row>
      <Row></Row>
      <Row ss:StyleID="header">
       <Cell><Data ss:Type="String">Leave Type</Data></Cell>
       <Cell><Data ss:Type="String">Total Requests</Data></Cell>
       <Cell><Data ss:Type="String">Approved Requests</Data></Cell>
       <Cell><Data ss:Type="String">Total Days Used</Data></Cell>
       <Cell><Data ss:Type="String">Avg Days/Request</Data></Cell>
      </Row>
      <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
      <Row>
       <Cell><Data ss:Type="String"><?php echo htmlspecialchars($row['LeaveType']); ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalRequests'] ?? 0; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['ApprovedRequests'] ?? 0; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo $row['TotalDaysUsed'] ?? 0; ?></Data></Cell>
       <Cell><Data ss:Type="Number"><?php echo number_format($row['AvgDaysPerRequest'] ?? 0, 1); ?></Data></Cell>
      </Row>
      <?php endwhile; ?>
     </Table>
    </Worksheet>
    <?php
}
?>
</Workbook>