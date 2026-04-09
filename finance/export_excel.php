<?php
/**
 * Export Payments to Excel
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'finance') {
    redirect('login.php');
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = array("1=1");
$params = array();

if ($status_filter !== 'all') {
    $where_conditions[] = "lp.Status = ?";
    $params[] = ucfirst($status_filter);
}

if (!empty($date_from)) {
    $where_conditions[] = "CAST(lp.CreatedAt AS DATE) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "CAST(lp.CreatedAt AS DATE) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// Get payments data
$sql = "SELECT 
            lp.PaymentID,
            emp.EmployeeNumber,
            emp.FirstName + ' ' + emp.LastName as EmployeeName,
            emp.Department,
            lt.TypeName as LeaveType,
            lr.StartDate,
            lr.EndDate,
            lr.TotalDays,
            lp.Amount,
            lp.Status,
            lp.PaymentReference,
            lp.PaymentMethod,
            lp.ProcessedDate,
            lp.CreatedAt
         FROM LeavePayments lp
         JOIN Users emp ON lp.EmployeeID = emp.UserID
         JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
         JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
         WHERE $where_clause
         ORDER BY lp.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Leave_Payments_Report_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Start output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

echo '<Worksheet ss:Name="Payment Report">' . "\n";
echo '<Table>' . "\n";

// Title Row
echo '<Row>' . "\n";
echo '<Cell ss:MergeAcross="12" ss:StyleID="title"><Data ss:Type="String">STERLING ASSURANCE NIGERIA LIMITED</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '<Row>' . "\n";
echo '<Cell ss:MergeAcross="12" ss:StyleID="subtitle"><Data ss:Type="String">Leave Payment Report</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '<Row>' . "\n";
echo '<Cell ss:MergeAcross="12"><Data ss:Type="String">Generated: ' . date('F d, Y H:i:s') . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

// Empty row
echo '<Row></Row>' . "\n";

// Header row
echo '<Row>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Payment ID</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Employee Number</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Employee Name</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Department</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Leave Type</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Start Date</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">End Date</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Days</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Amount (₦)</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Status</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Payment Reference</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Payment Method</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="header"><Data ss:Type="String">Processed Date</Data></Cell>' . "\n";
echo '</Row>' . "\n";

// Data rows
$total_amount = 0;
$payment_count = 0;

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $payment_count++;
        $total_amount += $row['Amount'];
        
        echo '<Row>' . "\n";
        echo '<Cell><Data ss:Type="Number">' . $row['PaymentID'] . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['EmployeeNumber']) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['EmployeeName']) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Department']) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['LeaveType']) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . $row['StartDate']->format('Y-m-d') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . $row['EndDate']->format('Y-m-d') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="Number">' . $row['TotalDays'] . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="Number">' . number_format($row['Amount'], 2, '.', '') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Status']) . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['PaymentReference'] ?? '-') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['PaymentMethod'] ?? '-') . '</Data></Cell>' . "\n";
        echo '<Cell><Data ss:Type="String">' . ($row['ProcessedDate'] ? $row['ProcessedDate']->format('Y-m-d') : '-') . '</Data></Cell>' . "\n";
        echo '</Row>' . "\n";
    }
}

// Empty row
echo '<Row></Row>' . "\n";

// Summary row
echo '<Row>' . "\n";
echo '<Cell ss:MergeAcross="7" ss:StyleID="summary"><Data ss:Type="String">TOTAL PAYMENTS: ' . $payment_count . '</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">' . number_format($total_amount, 2, '.', '') . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '</Table>' . "\n";

// Styles
echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">' . "\n";
echo '<Print><ValidPrinterInfo/></Print>' . "\n";
echo '</WorksheetOptions>' . "\n";

echo '</Worksheet>' . "\n";
echo '</Workbook>' . "\n";

if ($stmt) sqlsrv_free_stmt($stmt);
exit();
?>