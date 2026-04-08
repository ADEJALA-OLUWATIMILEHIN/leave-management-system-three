<?php
/**
 * Process Payment - Finance
 * Mark payment as paid and send notifications
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_notifications.php';

if (!is_logged_in() || $_SESSION['role'] !== 'finance') {
    redirect('login.php');
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    set_message('Invalid payment ID', 'error');
    redirect('index.php');
}

// Get payment details
$sql = "SELECT 
            lp.*,
            emp.FirstName + ' ' + emp.LastName as EmployeeName,
            emp.Email as EmployeeEmail,
            emp.Department,
            emp.EmployeeNumber,
            lt.TypeName as LeaveType,
            lr.TotalDays,
            lr.StartDate,
            lr.EndDate
         FROM LeavePayments lp
         JOIN Users emp ON lp.EmployeeID = emp.UserID
         JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
         JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
         WHERE lp.PaymentID = ?";

$stmt = sqlsrv_query($conn, $sql, array($payment_id));
$payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$payment) {
    set_message('Payment not found', 'error');
    redirect('index.php');
}

// Check if already paid
if ($payment['Status'] === 'Paid') {
    set_message('This payment has already been processed', 'info');
    redirect('index.php');
}

$message = '';
$message_type = '';

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = sanitize_input($_POST['payment_method']);
    $finance_notes = sanitize_input($_POST['finance_notes']);
    
    // Generate payment reference
    $reference = 'PAY-' . date('Ymd') . '-' . str_pad($payment_id, 5, '0', STR_PAD_LEFT);
    
    // Update payment status
    $update_sql = "UPDATE LeavePayments 
                   SET Status = 'Paid',
                       ProcessedBy = ?,
                       ProcessedDate = GETDATE(),
                       PaymentReference = ?,
                       PaymentMethod = ?,
                       FinanceNotes = ?,
                       UpdatedAt = GETDATE()
                   WHERE PaymentID = ?";
    
    $params = array($_SESSION['user_id'], $reference, $payment_method, $finance_notes, $payment_id);
    $update_stmt = sqlsrv_query($conn, $update_sql, $params);
    
    if ($update_stmt) {
        // Send confirmation email to employee
        if (function_exists('send_finance_to_employee_confirmation')) {
            send_finance_to_employee_confirmation($payment_id, $conn);
        }
        
        // Send notification to HR
        if (function_exists('send_finance_payment_notification_to_hr')) {
            send_finance_payment_notification_to_hr($payment_id, $conn);
        }
        
        set_message('Payment processed successfully! Reference: ' . $reference, 'success');
        redirect('index.php');
    } else {
        $message = 'Error processing payment. Please try again.';
        $message_type = 'error';
    }
    
    sqlsrv_free_stmt($update_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - Finance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 28px; }
        
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .info-item .value {
            color: #333;
            font-size: 15px;
            font-weight: 500;
        }
        
        .amount-display {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .amount-display .label {
            color: #1b5e20;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .amount-display .amount {
            font-size: 48px;
            font-weight: 700;
            color: #28a745;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            transition: 0.3s;
        }
        
        .btn-process {
            background: #28a745;
            color: white;
            flex: 1;
        }
        
        .btn-process:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Process Payment</h1>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Employee Information -->
        <div class="card">
            <h2>Employee Details</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Employee Name</label>
                    <div class="value"><?php echo htmlspecialchars($payment['EmployeeName']); ?></div>
                </div>
                <div class="info-item">
                    <label>Employee Number</label>
                    <div class="value"><?php echo htmlspecialchars($payment['EmployeeNumber']); ?></div>
                </div>
                <div class="info-item">
                    <label>Department</label>
                    <div class="value"><?php echo htmlspecialchars($payment['Department']); ?></div>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <div class="value"><?php echo htmlspecialchars($payment['EmployeeEmail']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Leave Information -->
        <div class="card">
            <h2>Leave Details</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Leave Type</label>
                    <div class="value"><?php echo htmlspecialchars($payment['LeaveType']); ?></div>
                </div>
                <div class="info-item">
                    <label>Total Days</label>
                    <div class="value"><?php echo $payment['TotalDays']; ?> days</div>
                </div>
                <div class="info-item">
                    <label>Leave Period</label>
                    <div class="value">
                        <?php echo $payment['StartDate']->format('M d, Y'); ?> - 
                        <?php echo $payment['EndDate']->format('M d, Y'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Payment ID</label>
                    <div class="value">#<?php echo $payment['PaymentID']; ?></div>
                </div>
            </div>
            
            <div class="amount-display">
                <div class="label">Payment Amount</div>
                <div class="amount">₦<?php echo number_format($payment['Amount'], 2); ?></div>
            </div>
        </div>
        
        <!-- Payment Form -->
        <div class="card">
            <h2>Process Payment</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">-- Select Payment Method --</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cash">Cash</option>
                        <option value="Direct Deposit">Direct Deposit</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="finance_notes">Finance Notes (Optional)</label>
                    <textarea 
                        id="finance_notes" 
                        name="finance_notes" 
                        rows="4" 
                        placeholder="Add any additional notes about this payment..."
                    ></textarea>
                </div>
                
                <div class="btn-group">
                    <button 
                        type="submit" 
                        class="btn btn-process"
                        onclick="return confirm('Confirm payment of ₦<?php echo number_format($payment['Amount'], 2); ?> to <?php echo htmlspecialchars($payment['EmployeeName']); ?>?\n\nThis action cannot be undone.');"
                    >
                        ✅ Process Payment
                    </button>
                    <a href="index.php" class="btn btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
if ($stmt) sqlsrv_free_stmt($stmt);
?>