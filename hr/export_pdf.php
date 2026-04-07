<?php
/**
 * PDF Export - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

// Include TCPDF library
require_once(__DIR__ . '/../vendor/tcpdf/tcpdf.php');

// Get export type
$export_type = isset($_GET['type']) ? $_GET['type'] : 'summary';
$current_year = date('Y');

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Leave Management System');
$pdf->SetAuthor('Sterling Assurance Nigeria Limited');
$pdf->SetTitle('Leave Management Report');
$pdf->SetSubject('Leave Report');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Generate report based on type
switch ($export_type) {
    case 'summary':
        generate_summary_report($pdf, $conn);
        break;
    case 'detailed':
        generate_detailed_report($pdf, $conn);
        break;
    case 'department':
        generate_department_report($pdf, $conn);
        break;
    case 'employee':
        generate_employee_report($pdf, $conn);
        break;
    default:
        generate_summary_report($pdf, $conn);
}

// Close and output PDF document
$pdf->Output('Leave_Report_' . $export_type . '_' . date('Y-m-d') . '.pdf', 'D');

/**
 * Generate Summary Report
 */
function generate_summary_report($pdf, $conn) {
    // Company Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sterling Assurance Nigeria Limited', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Leave Management Summary Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('F d, Y'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Statistics
    $stats_sql = "SELECT 
                    COUNT(DISTINCT u.UserID) as TotalEmployees,
                    COUNT(lr.RequestID) as TotalRequests,
                    SUM(CASE WHEN lr.Status = 'approved' THEN 1 ELSE 0 END) as ApprovedRequests,
                    SUM(CASE WHEN lr.Status = 'pending' THEN 1 ELSE 0 END) as PendingRequests,
                    SUM(CASE WHEN lr.Status = 'rejected' THEN 1 ELSE 0 END) as RejectedRequests,
                    SUM(CASE WHEN lr.Status = 'approved' THEN lr.TotalDays ELSE 0 END) as TotalDaysUsed
                  FROM Users u
                  LEFT JOIN LeaveRequests lr ON u.UserID = lr.UserID
                  WHERE u.Role = 'employee' AND u.IsActive = 1";
    
    $stats_stmt = sqlsrv_query($conn, $stats_sql);
    $stats = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC);
    
    // Draw statistics boxes
    $pdf->SetFillColor(79, 172, 254);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    
    $box_width = 60;
    $box_height = 15;
    $x_start = 15;
    $y = $pdf->GetY();
    
    // Total Employees
    $pdf->SetXY($x_start, $y);
    $pdf->Cell($box_width, $box_height, 'Total Employees', 1, 0, 'C', true);
    $pdf->SetXY($x_start, $y + $box_height);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell($box_width, $box_height, $stats['TotalEmployees'], 1, 0, 'C', true);
    
    // Total Requests
    $pdf->SetXY($x_start + $box_width + 5, $y);
    $pdf->SetFillColor(79, 172, 254);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($box_width, $box_height, 'Total Requests', 1, 0, 'C', true);
    $pdf->SetXY($x_start + $box_width + 5, $y + $box_height);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell($box_width, $box_height, $stats['TotalRequests'] ?? 0, 1, 0, 'C', true);
    
    // Approved
    $pdf->SetXY($x_start + ($box_width + 5) * 2, $y);
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($box_width, $box_height, 'Approved', 1, 0, 'C', true);
    $pdf->SetXY($x_start + ($box_width + 5) * 2, $y + $box_height);
    $pdf->SetFillColor(200, 230, 201);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell($box_width, $box_height, $stats['ApprovedRequests'] ?? 0, 1, 0, 'C', true);
    
    $pdf->Ln(35);
    
    // Recent Requests Table
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'Recent Leave Requests', 0, 1);
    $pdf->Ln(2);
    
    $recent_sql = "SELECT TOP 10
                    u.FirstName + ' ' + u.LastName as EmployeeName,
                    u.Department,
                    lt.TypeName as LeaveType,
                    lr.StartDate,
                    lr.EndDate,
                    lr.TotalDays,
                    lr.Status
                   FROM LeaveRequests lr
                   JOIN Users u ON lr.UserID = u.UserID
                   JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                   ORDER BY lr.CreatedAt DESC";
    
    $recent_stmt = sqlsrv_query($conn, $recent_sql);
    
    // Table header
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 8, 'Employee', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Department', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Leave Type', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Start Date', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'End Date', 1, 0, 'C', true);
    $pdf->Cell(15, 8, 'Days', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Status', 1, 1, 'C', true);
    
    // Table rows
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    
    while ($row = sqlsrv_fetch_array($recent_stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['Status'] == 'approved') {
            $pdf->SetFillColor(198, 239, 206);
        } elseif ($row['Status'] == 'pending') {
            $pdf->SetFillColor(255, 235, 156);
        } else {
            $pdf->SetFillColor(255, 199, 206);
        }
        
        $pdf->Cell(40, 7, substr($row['EmployeeName'], 0, 20), 1, 0, 'L', true);
        $pdf->Cell(30, 7, substr($row['Department'], 0, 15), 1, 0, 'L', true);
        $pdf->Cell(30, 7, substr($row['LeaveType'], 0, 15), 1, 0, 'L', true);
        $pdf->Cell(25, 7, $row['StartDate']->format('Y-m-d'), 1, 0, 'C', true);
        $pdf->Cell(25, 7, $row['EndDate']->format('Y-m-d'), 1, 0, 'C', true);
        $pdf->Cell(15, 7, $row['TotalDays'], 1, 0, 'C', true);
        $pdf->Cell(20, 7, ucfirst($row['Status']), 1, 1, 'C', true);
    }
}

/**
 * Generate Detailed Report
 */
function generate_detailed_report($pdf, $conn) {
    // Company Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sterling Assurance Nigeria Limited', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Detailed Leave Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('F d, Y'), 0, 1, 'C');
    $pdf->Ln(5);
    
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
                lr.CreatedAt
            FROM LeaveRequests lr
            JOIN Users u ON lr.UserID = u.UserID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            ORDER BY lr.CreatedAt DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    // Table header
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Employee', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Department', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Type', 1, 0, 'C', true);
    $pdf->Cell(22, 8, 'Start', 1, 0, 'C', true);
    $pdf->Cell(22, 8, 'End', 1, 0, 'C', true);
    $pdf->Cell(12, 8, 'Days', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Status', 1, 1, 'C', true);
    
    // Table rows
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(0, 0, 0);
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['Status'] == 'approved') {
            $pdf->SetFillColor(198, 239, 206);
        } elseif ($row['Status'] == 'pending') {
            $pdf->SetFillColor(255, 235, 156);
        } else {
            $pdf->SetFillColor(255, 199, 206);
        }
        
        $pdf->Cell(15, 6, $row['RequestID'], 1, 0, 'C', true);
        $pdf->Cell(35, 6, substr($row['EmployeeName'], 0, 18), 1, 0, 'L', true);
        $pdf->Cell(25, 6, substr($row['Department'], 0, 12), 1, 0, 'L', true);
        $pdf->Cell(25, 6, substr($row['LeaveType'], 0, 12), 1, 0, 'L', true);
        $pdf->Cell(22, 6, $row['StartDate']->format('Y-m-d'), 1, 0, 'C', true);
        $pdf->Cell(22, 6, $row['EndDate']->format('Y-m-d'), 1, 0, 'C', true);
        $pdf->Cell(12, 6, $row['TotalDays'], 1, 0, 'C', true);
        $pdf->Cell(20, 6, ucfirst($row['Status']), 1, 1, 'C', true);
    }
}

/**
 * Generate Department Report
 */
function generate_department_report($pdf, $conn) {
    // Company Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sterling Assurance Nigeria Limited', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Department-wise Leave Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('F d, Y'), 0, 1, 'C');
    $pdf->Ln(5);
    
    $sql = "SELECT 
                u.Department,
                COUNT(DISTINCT u.UserID) as TotalEmployees,
                COUNT(lr.RequestID) as TotalRequests,
                SUM(CASE WHEN lr.Status = 'approved' THEN 1 ELSE 0 END) as Approved,
                SUM(CASE WHEN lr.Status = 'pending' THEN 1 ELSE 0 END) as Pending,
                SUM(CASE WHEN lr.Status = 'approved' THEN lr.TotalDays ELSE 0 END) as DaysUsed
            FROM Users u
            LEFT JOIN LeaveRequests lr ON u.UserID = lr.UserID
            WHERE u.Role = 'employee' AND u.IsActive = 1
            GROUP BY u.Department
            ORDER BY u.Department";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    // Table header
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 8, 'Department', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Employees', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Requests', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Approved', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Pending', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Days Used', 1, 1, 'C', true);
    
    // Table rows
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(240, 240, 240);
    $fill = false;
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $pdf->Cell(40, 7, $row['Department'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 7, $row['TotalEmployees'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 7, $row['TotalRequests'] ?? 0, 1, 0, 'C', $fill);
        $pdf->Cell(25, 7, $row['Approved'] ?? 0, 1, 0, 'C', $fill);
        $pdf->Cell(25, 7, $row['Pending'] ?? 0, 1, 0, 'C', $fill);
        $pdf->Cell(25, 7, $row['DaysUsed'] ?? 0, 1, 1, 'C', $fill);
        $fill = !$fill;
    }
}

/**
 * Generate Employee Balances Report
 */
function generate_employee_report($pdf, $conn) {
    $current_year = date('Y');
    
    // Company Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sterling Assurance Nigeria Limited', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Employee Leave Balances - ' . $current_year, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('F d, Y'), 0, 1, 'C');
    $pdf->Ln(5);
    
    $sql = "SELECT 
                u.FirstName + ' ' + u.LastName as EmployeeName,
                u.Department,
                lt.TypeName as LeaveType,
                lb.TotalDays,
                lb.UsedDays,
                lb.RemainingDays
            FROM LeaveBalances lb
            JOIN Users u ON lb.UserID = u.UserID
            JOIN LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
            WHERE lb.Year = ? AND u.IsActive = 1
            ORDER BY u.Department, u.FirstName";
    
    $stmt = sqlsrv_query($conn, $sql, array($current_year));
    
    // Table header
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(45, 8, 'Employee', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Department', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Leave Type', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Total', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Used', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Remaining', 1, 1, 'C', true);
    
    // Table rows
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(240, 240, 240);
    $fill = false;
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $pdf->Cell(45, 6, substr($row['EmployeeName'], 0, 22), 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, substr($row['Department'], 0, 15), 1, 0, 'L', $fill);
        $pdf->Cell(35, 6, substr($row['LeaveType'], 0, 18), 1, 0, 'L', $fill);
        $pdf->Cell(20, 6, $row['TotalDays'], 1, 0, 'C', $fill);
        $pdf->Cell(20, 6, $row['UsedDays'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['RemainingDays'], 1, 1, 'C', $fill);
        $fill = !$fill;
    }
}
?>