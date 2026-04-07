<?php
/**
 * HOD Review Leave Request
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_notifications.php';

// Check if user is logged in and is HOD
if (!is_logged_in() || $_SESSION['role'] !== 'hod') {
    redirect('../login.php');
}

$hod_id = $_SESSION['user_id'];
$hod_name = $_SESSION['name'];
$hod_department = $_SESSION['department'];

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Fetch request details
$sql = "SELECT 
            lr.RequestID,
            lr.UserID,
            lr.StartDate,
            lr.EndDate,
            lr.TotalDays,
            lr.Reason,
            lr.HODApprovalStatus,
            u.FirstName + ' ' + u.LastName as EmployeeName,
            u.Email as EmployeeEmail,
            u.Department,
            lt.TypeName as LeaveType,
            lb.RemainingDays
        FROM LeaveRequests lr
        JOIN Users u ON lr.UserID = u.UserID
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        LEFT JOIN LeaveBalances lb ON u.UserID = lb.UserID AND lr.LeaveTypeID = lb.LeaveTypeID AND lb.Year = YEAR(GETDATE())
        WHERE lr.RequestID = ? AND u.Department = ? AND lr.HODApprovalStatus = 'pending'";

$stmt = sqlsrv_query($conn, $sql, array($request_id, $hod_department));

if (!$stmt || !($request = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    set_message('Request not found or already processed.', 'error');
    redirect('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $hod_remarks = sanitize_input($_POST['hod_remarks'] ?? '');
    
    if ($action === 'approve') {
        // Update request - HOD approves
        $update_sql = "UPDATE LeaveRequests 
                       SET HODApprovalStatus = 'approved',
                           HODApprovedBy = ?,
                           HODApprovedDate = GETDATE(),
                           HODRemarks = ?,
                           Status = 'pending',
                           UpdatedAt = GETDATE()
                       WHERE RequestID = ?";
        
        $update_params = array($hod_id, $hod_remarks, $request_id);
        $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);
        
        if ($update_stmt) {
            // Get HR email
            $hr_sql = "SELECT Email, FirstName + ' ' + LastName as HRName FROM Users WHERE Role = 'hr' AND IsActive = 1";
            $hr_stmt = sqlsrv_query($conn, $hr_sql);
            $hr_row = sqlsrv_fetch_array($hr_stmt, SQLSRV_FETCH_ASSOC);
            
            // Send email to HR
            if ($hr_row) {
                notify_hr_hod_approved(
                    $hr_row['Email'],
                    $request['EmployeeName'],
                    $hod_name,
                    $request['LeaveType'],
                    date('F d, Y', strtotime($request['StartDate']->format('Y-m-d'))),
                    date('F d, Y', strtotime($request['EndDate']->format('Y-m-d'))),
                    $request['TotalDays'],
                    $request['Reason'],
                    $hod_remarks
                );
            }
            
            set_message('Leave request approved and forwarded to HR.', 'success');
            redirect('index.php');
        } else {
            $error = 'Failed to approve request. Please try again.';
        }
        
    } elseif ($action === 'reject') {
        if (empty($hod_remarks)) {
            $error = 'Please provide remarks for rejection.';
        } else {
            // Update request - HOD rejects
            $update_sql = "UPDATE LeaveRequests 
                           SET HODApprovalStatus = 'rejected',
                               HODApprovedBy = ?,
                               HODApprovedDate = GETDATE(),
                               HODRemarks = ?,
                               Status = 'rejected',
                               UpdatedAt = GETDATE()
                           WHERE RequestID = ?";
            
            $update_params = array($hod_id, $hod_remarks, $request_id);
            $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);
            
            if ($update_stmt) {
                // Send rejection email to employee
                notify_employee_hod_rejected(
                    $request['EmployeeEmail'],
                    $request['EmployeeName'],
                    $hod_name,
                    $request['LeaveType'],
                    date('F d, Y', strtotime($request['StartDate']->format('Y-m-d'))),
                    date('F d, Y', strtotime($request['EndDate']->format('Y-m-d'))),
                    $hod_remarks
                );
                
                set_message('Leave request rejected.', 'success');
                redirect('index.php');
            } else {
                $error = 'Failed to reject request. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Leave Request - Leave Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
        }
        
        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        
        .btn-logout {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Leave Management System - HOD</h1>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Review Leave Request</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="detail-row">
                <div class="detail-label">Employee:</div>
                <div class="detail-value"><strong><?php echo htmlspecialchars($request['EmployeeName']); ?></strong></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Department:</div>
                <div class="detail-value"><?php echo htmlspecialchars($request['Department']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Leave Type:</div>
                <div class="detail-value"><?php echo htmlspecialchars($request['LeaveType']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Start Date:</div>
                <div class="detail-value"><?php echo $request['StartDate']->format('F d, Y'); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">End Date:</div>
                <div class="detail-value"><?php echo $request['EndDate']->format('F d, Y'); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Total Working Days:</div>
                <div class="detail-value"><strong><?php echo $request['TotalDays']; ?> days</strong></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Current Balance:</div>
                <div class="detail-value"><?php echo $request['RemainingDays'] ?? 'N/A'; ?> days</div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Reason:</div>
                <div class="detail-value"><?php echo nl2br(htmlspecialchars($request['Reason'])); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Status:</div>
                <div class="detail-value">
                    <span class="status-badge status-pending">Pending Your Approval</span>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>Your Decision</h2>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="hod_remarks">Remarks (Optional for approval, Required for rejection):</label>
                    <textarea id="hod_remarks" name="hod_remarks" placeholder="Add your comments here..."></textarea>
                </div>
                
                <div class="actions">
                    <button type="submit" name="action" value="approve" class="btn btn-approve" 
                            onclick="return confirm('Are you sure you want to approve this leave request?');">
                        ✓ Approve
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-reject" 
                            onclick="return confirm('Are you sure you want to reject this leave request? Please add remarks.');">
                        ✗ Reject
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
if ($stmt) sqlsrv_free_stmt($stmt);
?>