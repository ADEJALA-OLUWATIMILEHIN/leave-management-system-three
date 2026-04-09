<?php
/**
 * Review Leave Request - HR
 * With integrated payment creation (Annual Leave only)
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'hr') {
    redirect('../login.php');
}

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get request details
$sql = "SELECT 
            lr.*,
            u.FirstName,
            u.LastName,
            u.Email,
            u.Department,
            u.EmployeeNumber,
            u.Salary,
            u.BankName,
            u.AccountNumber,
            u.AccountName,
            lt.TypeName
        FROM LeaveRequests lr
        JOIN Users u ON lr.UserID = u.UserID
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        WHERE lr.RequestID = ?";

$stmt = sqlsrv_query($conn, $sql, array($request_id));

// If query itself failed, show DB error instead of crashing
if ($stmt === false) {
    $errors = sqlsrv_errors();
    die('<pre style="color:red;padding:20px;"><strong>Database Query Error:</strong>' . "\n" . print_r($errors, true) . '</pre>');
}

$request = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$request) {
    redirect('all_requests.php');
}

$message = '';
$message_type = '';

// Handle HR approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $hr_notes = sanitize_input($_POST['hr_notes']);

    if ($action === 'approve') {

        // Update request status to approved
        $update_sql = "UPDATE LeaveRequests 
                       SET HRApprovalStatus = 'approved',
                           HRApprovedDate = GETDATE(),
                           HRApprovedBy = ?,
                           HRRemarks = ?,
                           Status = 'approved'
                       WHERE RequestID = ?";
        $update_stmt = sqlsrv_query($conn, $update_sql, array($_SESSION['user_id'], $hr_notes, $request_id));

        if ($update_stmt) {

            // Only create payment for Annual Leave
            $leave_type_check = strtolower(trim($request['TypeName']));

            if ($leave_type_check === 'annual leave' || $leave_type_check === 'annual') {

                // Leave allowance = full Salary amount (paid once, not per day)
                $leave_allowance = round($request['Salary'] ?? 100000, 2);

                // Check if payment already exists
                $check_payment_sql  = "SELECT PaymentID FROM LeavePayments WHERE RequestID = ?";
                $check_payment_stmt = sqlsrv_query($conn, $check_payment_sql, array($request_id));

                if (!sqlsrv_has_rows($check_payment_stmt)) {
                    // Create payment record
                    $payment_sql = "INSERT INTO LeavePayments 
                                    (RequestID, EmployeeID, Amount, TotalAmount, Status, ApprovedByHR, ApprovedDate, HRNotes, PaymentType, CreatedAt)
                                    VALUES (?, ?, ?, ?, 'Pending', ?, GETDATE(), ?, 'Leave Allowance', GETDATE())";

                    $payment_params = array(
                        $request_id,
                        $request['UserID'],
                        $leave_allowance,
                        $leave_allowance,
                        $_SESSION['user_id'],
                        'Annual Leave allowance paid in full: ' . number_format($leave_allowance, 2)
                    );

                    $payment_stmt = sqlsrv_query($conn, $payment_sql, $payment_params);

                    if ($payment_stmt) {
                        // Get new payment ID
                        $id_sql  = "SELECT SCOPE_IDENTITY() as PaymentID";
                        $id_stmt = sqlsrv_query($conn, $id_sql);
                        $id_row  = sqlsrv_fetch_array($id_stmt, SQLSRV_FETCH_ASSOC);
                        $payment_id = $id_row['PaymentID'];

                        // Send email notification if available
                        if (file_exists(__DIR__ . '/../config/email_notifications.php')) {
                            require_once __DIR__ . '/../config/email_notifications.php';
                            if (function_exists('send_finance_payment_notification')) {
                                send_finance_payment_notification($payment_id, $conn);
                            }
                        }

                        $message      = 'Annual Leave approved! Payment record created: ₦' . number_format($leave_allowance, 2);
                        $message_type = 'success';

                        sqlsrv_free_stmt($id_stmt);
                        sqlsrv_free_stmt($payment_stmt);
                    } else {
                        $message      = 'Leave approved but payment creation failed.';
                        $message_type = 'warning';
                    }
                } else {
                    $message      = 'Annual Leave approved! (Payment record already exists)';
                    $message_type = 'success';
                }

                sqlsrv_free_stmt($check_payment_stmt);

            } else {
                // Non-Annual Leave — no payment created
                $message      = ucfirst($request['TypeName']) . ' approved successfully! (No payment — only Annual Leave is paid)';
                $message_type = 'success';
            }

            sqlsrv_free_stmt($update_stmt);

        } else {
            $err = sqlsrv_errors();
            $message      = 'Error approving leave request: ' . (isset($err[0]['message']) ? $err[0]['message'] : 'Unknown error');
            $message_type = 'error';
        }

    } elseif ($action === 'reject') {

        $update_sql = "UPDATE LeaveRequests 
                       SET HRApprovalStatus = 'rejected',
                           HRApprovedDate = GETDATE(),
                           HRApprovedBy = ?,
                           HRRemarks = ?,
                           Status = 'rejected'
                       WHERE RequestID = ?";
        $update_stmt = sqlsrv_query($conn, $update_sql, array($_SESSION['user_id'], $hr_notes, $request_id));

        if ($update_stmt) {
            $message      = 'Leave request rejected successfully.';
            $message_type = 'info';
            sqlsrv_free_stmt($update_stmt);
        } else {
            $message      = 'Error rejecting leave request.';
            $message_type = 'error';
        }
    }

    // Refresh request data after update
    sqlsrv_free_stmt($stmt);
    $stmt    = sqlsrv_query($conn, $sql, array($request_id));
    $request = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// Check if payment exists for this request
$payment_sql  = "SELECT 
                    lp.*,
                    u.FirstName + ' ' + u.LastName as ProcessedByName
                FROM LeavePayments lp
                LEFT JOIN Users u ON lp.ProcessedBy = u.UserID
                WHERE lp.RequestID = ?";
$payment_stmt = sqlsrv_query($conn, $payment_sql, array($request_id));
$payment      = sqlsrv_fetch_array($payment_stmt, SQLSRV_FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Request - HR</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #00c9ff 0%, #92fe9d 100%); color: white; padding: 20px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { font-size: 28px; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card h2 { color: #333; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; font-size: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .info-item label { font-weight: 600; color: #666; font-size: 13px; display: block; margin-bottom: 5px; text-transform: uppercase; }
        .info-item .value { color: #333; font-size: 15px; font-weight: 500; }
        .status-badge { padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        .status-pending  { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-paid     { background: #d4edda; color: #155724; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .message.info    { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .message.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: inherit; font-size: 14px; }
        .form-group textarea:focus { outline: none; border-color: #00c9ff; }
        .btn-group { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .btn { padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 15px; transition: 0.3s; }
        .btn-approve { background: #28a745; color: white; }
        .btn-approve:hover { background: #218838; transform: translateY(-2px); }
        .btn-reject  { background: #dc3545; color: white; }
        .btn-reject:hover  { background: #c82333; transform: translateY(-2px); }
        .btn-back { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        .btn-back:hover { background: #5a6268; }
        .payment-section { background: linear-gradient(135deg, #e8f5e9, #c8e6c9); padding: 25px; border-radius: 10px; border-left: 4px solid #28a745; }
        .payment-section h3 { color: #1b5e20; margin-bottom: 15px; font-size: 18px; }
        .payment-amount { font-size: 36px; font-weight: 700; color: #28a745; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>&#128203; Review Leave Request</h1>
    </div>

    <div class="container">

        <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Employee Information -->
        <div class="card">
            <h2>&#128100; Employee Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Employee Name</label>
                    <div class="value"><?php echo htmlspecialchars($request['FirstName'] . ' ' . $request['LastName']); ?></div>
                </div>
                <div class="info-item">
                    <label>Employee Number</label>
                    <div class="value"><?php echo htmlspecialchars($request['EmployeeNumber']); ?></div>
                </div>
                <div class="info-item">
                    <label>Department</label>
                    <div class="value"><?php echo htmlspecialchars($request['Department']); ?></div>
                </div>
                <div class="info-item">
                    <label>Email Address</label>
                    <div class="value"><?php echo htmlspecialchars($request['Email']); ?></div>
                </div>
                <?php
                $leave_type_lower = strtolower(trim($request['TypeName']));
                if ($leave_type_lower === 'annual leave' || $leave_type_lower === 'annual'):
                    $leave_allowance_display = $request['Salary'] ?? 100000;
                ?>
                <div class="info-item" style="background: #e8f5e9; border-left: 4px solid #28a745;">
                    <label>Leave Allowance (Full — Paid Once)</label>
                    <div class="value" style="font-size: 22px; color: #28a745; font-weight: 700;">
                        &#8358;<?php echo number_format($leave_allowance_display, 2); ?>
                    </div>
                    <small style="color: #666;">Full annual leave allowance paid in one lump sum</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leave Details -->
        <div class="card">
            <h2>&#128197; Leave Details</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Leave Type</label>
                    <div class="value"><?php echo htmlspecialchars($request['TypeName']); ?></div>
                </div>
                <div class="info-item">
                    <label>Total Days</label>
                    <div class="value"><?php echo $request['TotalDays']; ?> days</div>
                </div>
                <div class="info-item">
                    <label>Start Date</label>
                    <div class="value">
                        <?php echo ($request['StartDate'] instanceof DateTime) ? $request['StartDate']->format('M d, Y') : 'N/A'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>End Date</label>
                    <div class="value">
                        <?php echo ($request['EndDate'] instanceof DateTime) ? $request['EndDate']->format('M d, Y') : 'N/A'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Submitted On</label>
                    <div class="value">
                        <?php echo ($request['CreatedAt'] instanceof DateTime) ? $request['CreatedAt']->format('M d, Y H:i') : 'N/A'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>HOD Approval</label>
                    <div class="value">
                        <span class="status-badge status-<?php echo $request['HODApprovalStatus']; ?>">
                            <?php echo ucfirst($request['HODApprovalStatus']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="info-item" style="margin-top: 15px;">
                <label>Reason for Leave</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($request['Reason'])); ?></div>
            </div>

            <?php if (!empty($request['HODNotes'])): ?>
            <div class="info-item" style="margin-top: 15px;">
                <label>HOD Notes</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($request['HODNotes'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Payment Information (if exists) -->
        <?php if ($payment): ?>
        <div class="card">
            <div class="payment-section">
                <h3>&#128176; Payment Information</h3>
                <div class="payment-amount">&#8358;<?php echo number_format($payment['Amount'], 2); ?></div>

                <div class="info-grid" style="margin-top: 20px;">
                    <div class="info-item" style="background: white;">
                        <label>Payment Status</label>
                        <div class="value">
                            <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                <?php echo $payment['Status']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item" style="background: white;">
                        <label>Created On</label>
                        <div class="value">
                            <?php echo ($payment['CreatedAt'] instanceof DateTime) ? $payment['CreatedAt']->format('M d, Y H:i') : 'N/A'; ?>
                        </div>
                    </div>

                    <?php if (!empty($payment['PaymentReference'])): ?>
                    <div class="info-item" style="background: white;">
                        <label>Payment Reference</label>
                        <div class="value"><?php echo htmlspecialchars($payment['PaymentReference']); ?></div>
                    </div>
                    <div class="info-item" style="background: white;">
                        <label>Payment Method</label>
                        <div class="value"><?php echo htmlspecialchars($payment['PaymentMethod']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($payment['ProcessedDate'])): ?>
                    <div class="info-item" style="background: white;">
                        <label>Processed Date</label>
                        <div class="value">
                            <?php echo ($payment['ProcessedDate'] instanceof DateTime) ? $payment['ProcessedDate']->format('M d, Y H:i') : 'N/A'; ?>
                        </div>
                    </div>
                    <div class="info-item" style="background: white;">
                        <label>Processed By</label>
                        <div class="value"><?php echo htmlspecialchars($payment['ProcessedByName'] ?? 'N/A'); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($payment['HRNotes'])): ?>
                <div class="info-item" style="margin-top: 15px; background: white;">
                    <label>Payment Notes</label>
                    <div class="value"><?php echo nl2br(htmlspecialchars($payment['HRNotes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>


        <!-- Bank Details (for HR and Finance visibility) -->
        <?php
        $has_bank = !empty($request['AccountNumber']);
        $leave_type_for_bank = strtolower(trim($request['TypeName']));
        ?>
        <?php if ($has_bank || ($leave_type_for_bank === 'annual leave' || $leave_type_for_bank === 'annual')): ?>
        <div class="card" style="border: 2px solid <?php echo $has_bank ? '#28a745' : '#ffc107'; ?>;">
            <div style="padding:18px 26px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;background:<?php echo $has_bank ? '#f0fff4' : '#fffbf0'; ?>;">
                <div style="width:36px;height:36px;border-radius:9px;background:<?php echo $has_bank ? '#d4edda' : '#fff3cd'; ?>;display:flex;align-items:center;justify-content:center;font-size:18px;">&#127981;</div>
                <h2 style="font-size:17px;color:#333;">Employee Bank Details (Payment Destination)</h2>
                <?php if (!$has_bank): ?>
                <span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">&#9888; Not Provided</span>
                <?php else: ?>
                <span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">&#9989; On File</span>
                <?php endif; ?>
            </div>
            <div style="padding:22px 26px;">
                <?php if ($has_bank): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
                    <div style="padding:14px 16px;background:#f8f9fa;border-radius:9px;">
                        <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#888;font-weight:600;margin-bottom:5px;">Bank Name</label>
                        <div style="font-size:15px;color:#222;font-weight:600;"><?php echo htmlspecialchars($request['BankName'] ?? '—'); ?></div>
                    </div>
                    <div style="padding:14px 16px;background:#f8f9fa;border-radius:9px;">
                        <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#888;font-weight:600;margin-bottom:5px;">Account Number</label>
                        <div style="font-size:18px;color:#222;font-weight:700;font-family:monospace;letter-spacing:2px;"><?php echo htmlspecialchars($request['AccountNumber'] ?? '—'); ?></div>
                    </div>
                    <div style="padding:14px 16px;background:#f8f9fa;border-radius:9px;">
                        <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#888;font-weight:600;margin-bottom:5px;">Account Name</label>
                        <div style="font-size:15px;color:#222;font-weight:600;"><?php echo htmlspecialchars($request['AccountName'] ?? '—'); ?></div>
                    </div>
                </div>
                <p style="margin-top:14px;font-size:12px;color:#888;background:#f8f9fa;padding:10px 14px;border-radius:7px;">
                    &#128276; This bank account will be used by Finance to process the leave allowance payment. Please verify it is correct before approving.
                </p>
                <?php else: ?>
                <div style="text-align:center;padding:20px;color:#856404;background:#fffbf0;border-radius:9px;">
                    <div style="font-size:32px;margin-bottom:10px;">&#9888;</div>
                    <p><strong>Employee has not provided bank details.</strong></p>
                    <p style="font-size:13px;margin-top:6px;">Please ask the employee to update their bank details in their portal before processing the leave allowance payment.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- HR Review Form or Decision -->
        <?php if ($request['HRApprovalStatus'] === 'pending'): ?>
        <div class="card">
            <h2>&#9989; HR Review &amp; Decision</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="hr_notes">HR Notes/Comments</label>
                    <textarea id="hr_notes" name="hr_notes" rows="4" placeholder="Enter your notes or comments about this leave request..."></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" name="action" value="approve" class="btn btn-approve"
                        onclick="return confirm('Approve this leave request?\n\n<?php echo (strtolower(trim($request['TypeName'])) === 'annual leave' || strtolower(trim($request['TypeName'])) === 'annual') ? 'A payment record will be created:\n• Amount: ₦' . number_format(($request['Salary'] ?? 100000) / 30 * $request['TotalDays'], 2) : 'No payment will be created (Annual Leave only).'; ?>')">
                        &#9989; Approve Leave
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-reject"
                        onclick="return confirm('Are you sure you want to reject this leave request?')">
                        &#10060; Reject Leave
                    </button>
                    <a href="all_requests.php" class="btn btn-back">&#8592; Back to All Requests</a>
                </div>
            </form>
        </div>

        <?php else: ?>
        <div class="card">
            <h2>HR Decision</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>HR Status</label>
                    <div class="value">
                        <span class="status-badge status-<?php echo $request['HRApprovalStatus']; ?>">
                            <?php echo ucfirst($request['HRApprovalStatus']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <label>Decision Date</label>
                    <div class="value">
                        <?php echo ($request['HRApprovedDate'] instanceof DateTime) ? $request['HRApprovedDate']->format('M d, Y H:i') : 'N/A'; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($request['HRRemarks'])): ?>
            <div class="info-item" style="margin-top: 15px;">
                <label>HR Remarks</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($request['HRRemarks'])); ?></div>
            </div>
            <?php endif; ?>

            <div class="btn-group" style="margin-top: 20px;">
                <a href="all_requests.php" class="btn btn-back">&#8592; Back to All Requests</a>
                <a href="payment_tracking.php" class="btn" style="background: #28a745; color: white; text-decoration: none;">
                    &#128176; View Payment Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
<?php
if ($stmt) sqlsrv_free_stmt($stmt);
if ($payment_stmt) sqlsrv_free_stmt($payment_stmt);
?>
