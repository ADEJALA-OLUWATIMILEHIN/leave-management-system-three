<?php
/**
 * Submit Leave Request Form
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_notifications.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../login.php');
}

// Check if user is employee (not admin)
if (is_admin()) {
    redirect('../admin/index.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$current_year = date('Y');

$success = '';
$error = '';

// Fetch leave types
$leave_types_sql = "SELECT LeaveTypeID, TypeName, MaxDaysPerYear FROM LeaveTypes WHERE IsActive = 1";
$leave_types_stmt = sqlsrv_query($conn, $leave_types_sql);

// Fetch leave balances for current year
$balance_sql = "SELECT lt.LeaveTypeID, lt.TypeName, lb.RemainingDays
                FROM LeaveTypes lt
                LEFT JOIN LeaveBalances lb ON lt.LeaveTypeID = lb.LeaveTypeID AND lb.UserID = ? AND lb.Year = ?
                WHERE lt.IsActive = 1";
$balance_params = array($user_id, $current_year);
$balance_stmt = sqlsrv_query($conn, $balance_sql, $balance_params);

$balances = array();
while ($row = sqlsrv_fetch_array($balance_stmt, SQLSRV_FETCH_ASSOC)) {
    $balances[$row['LeaveTypeID']] = $row['RemainingDays'] ?? 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = (int)$_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = sanitize_input($_POST['reason']);
    
    // Validation
    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        $error = 'All fields are required.';
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error = 'End date must be after start date.';
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = 'Start date cannot be in the past.';
    } else {
        // Calculate working days using database function (excludes weekends and public holidays)
        $calc_sql = "SELECT dbo.CalculateWorkingDays(?, ?) as WorkingDays";
        $calc_params = array($start_date, $end_date);
        $calc_stmt = sqlsrv_query($conn, $calc_sql, $calc_params);
        
        if ($calc_stmt) {
            $calc_result = sqlsrv_fetch_array($calc_stmt, SQLSRV_FETCH_ASSOC);
            $total_days = $calc_result['WorkingDays'];
        } else {
            $error = 'Failed to calculate working days. Please try again.';
            $total_days = 0;
        }
        
        if ($total_days <= 0) {
            $error = 'No working days selected (weekends and public holidays excluded).';
        } else {
            // Check remaining balance
            $remaining = $balances[$leave_type] ?? 0;
            
            if ($total_days > $remaining) {
                $error = "Insufficient leave balance. You have $remaining days remaining.";
            } else {
                // Insert leave request with initial approval statuses
                $insert_sql = "INSERT INTO LeaveRequests (UserID, LeaveTypeID, StartDate, EndDate, TotalDays, Reason, Status, HODApprovalStatus, HRApprovalStatus) 
                               VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', 'pending')";
                $insert_params = array($user_id, $leave_type, $start_date, $end_date, $total_days, $reason);
                $insert_stmt = sqlsrv_query($conn, $insert_sql, $insert_params);
                
                if ($insert_stmt) {
                    // Get HOD email for this employee's department
                    $user_dept_sql = "SELECT Department FROM Users WHERE UserID = ?";
                    $user_dept_stmt = sqlsrv_query($conn, $user_dept_sql, array($user_id));
                    $user_dept_row = sqlsrv_fetch_array($user_dept_stmt, SQLSRV_FETCH_ASSOC);
                    $user_department = $user_dept_row['Department'];
                    
                    $hod_sql = "SELECT Email, FirstName + ' ' + LastName as HODName FROM Users WHERE Department = ? AND Role = 'hod' AND IsActive = 1";
                    $hod_stmt = sqlsrv_query($conn, $hod_sql, array($user_department));
                    $hod_row = sqlsrv_fetch_array($hod_stmt, SQLSRV_FETCH_ASSOC);
                    
                    // Get leave type name
                    $leave_type_sql = "SELECT TypeName FROM LeaveTypes WHERE LeaveTypeID = ?";
                    $leave_type_stmt = sqlsrv_query($conn, $leave_type_sql, array($leave_type));
                    $leave_type_row = sqlsrv_fetch_array($leave_type_stmt, SQLSRV_FETCH_ASSOC);
                    
                    // Send email to HOD if found
                    if ($hod_row) {
                        notify_hod_new_request(
                            $hod_row['Email'],
                            $hod_row['HODName'],
                            $user_name,
                            $leave_type_row['TypeName'],
                            date('F d, Y', strtotime($start_date)),
                            date('F d, Y', strtotime($end_date)),
                            $total_days,
                            $reason
                        );
                    }
                    
                    set_message('Leave request submitted successfully! Awaiting HOD approval.', 'success');
                    redirect('index.php');
                } else {
                    $error = 'Failed to submit leave request. Please try again.';
                }
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
    <title>Submit Leave Request - Leave Management</title>
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
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            color: #666;
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
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-header {
            margin-bottom: 25px;
        }
        
        .section-header h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .section-header p {
            color: #666;
            font-size: 14px;
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
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }
        
        .balance-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 8px;
            font-size: 14px;
            color: #004085;
        }
        
        .form-actions {
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
            text-align: center;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        #balance-display {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Leave Management System</h1>
        <div class="header-right">
            <span class="user-info">Welcome, <strong><?php echo $user_name; ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="section">
            <div class="section-header">
                <h2>Submit Leave Request</h2>
                <p>Fill in the details below to request time off</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="leave_type">Leave Type *</label>
                    <select id="leave_type" name="leave_type" required>
                        <option value="">-- Select Leave Type --</option>
                        <?php 
                        sqlsrv_fetch_array($leave_types_stmt, SQLSRV_FETCH_ASSOC); // Reset pointer
                        $leave_types_stmt = sqlsrv_query($conn, $leave_types_sql); // Re-query
                        while ($type = sqlsrv_fetch_array($leave_types_stmt, SQLSRV_FETCH_ASSOC)): 
                            $remaining = $balances[$type['LeaveTypeID']] ?? 0;
                        ?>
                            <option value="<?php echo $type['LeaveTypeID']; ?>" 
                                    data-balance="<?php echo $remaining; ?>"
                                    <?php echo (isset($_POST['leave_type']) && $_POST['leave_type'] == $type['LeaveTypeID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['TypeName']); ?> (<?php echo $remaining; ?> days remaining)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="balance-display" class="balance-info" style="display: none;">
                        Remaining balance: <span id="balance-amount">0</span> days
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date *</label>
                    <input 
                        type="date" 
                        id="start_date" 
                        name="start_date" 
                        min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : ''; ?>"
                        required
                    >
                    <small>Leave start date (cannot be in the past)</small>
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date *</label>
                    <input 
                        type="date" 
                        id="end_date" 
                        name="end_date" 
                        min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>"
                        required
                    >
                    <small>Leave end date (weekends and public holidays automatically excluded)</small>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason *</label>
                    <textarea 
                        id="reason" 
                        name="reason" 
                        placeholder="Please provide a reason for your leave request"
                        required
                    ><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                    <small>Provide a brief explanation for your leave request</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show remaining balance when leave type is selected
        document.getElementById('leave_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const balance = selectedOption.getAttribute('data-balance');
            const balanceDisplay = document.getElementById('balance-display');
            const balanceAmount = document.getElementById('balance-amount');
            
            if (balance !== null && this.value !== '') {
                balanceAmount.textContent = balance;
                balanceDisplay.style.display = 'block';
            } else {
                balanceDisplay.style.display = 'none';
            }
        });
        
        // Update end date minimum when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
        });
    </script>
</body>
</html>
<?php
if ($leave_types_stmt) sqlsrv_free_stmt($leave_types_stmt);
if ($balance_stmt) sqlsrv_free_stmt($balance_stmt);
?>