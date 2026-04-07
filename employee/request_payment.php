<?php
/**
 * Request Leave Payment
 * Employee chooses payment option (Full or Split)
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get payment details
$sql = "SELECT 
            lp.PaymentID,
            lp.Amount,
            lp.Status,
            lp.RequestedByEmployee,
            lr.RequestID,
            lr.StartDate,
            lr.EndDate,
            lr.TotalDays,
            lt.TypeName as LeaveType
        FROM LeavePayments lp
        JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        WHERE lp.PaymentID = ? AND lp.EmployeeID = ?";

$stmt = sqlsrv_query($conn, $sql, array($payment_id, $user_id));
$payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$payment) {
    set_message('Payment not found or access denied.', 'error');
    redirect('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_choice = $_POST['payment_choice'];
    
    if ($payment_choice === 'full') {
        // Request full payment
        $update_sql = "UPDATE LeavePayments 
                      SET PaymentSplitChoice = 'full',
                          RequestedByEmployee = 1,
                          EmployeeRequestDate = GETDATE(),
                          FirstPaymentAmount = Amount,
                          SecondPaymentAmount = 0
                      WHERE PaymentID = ?";
        
        sqlsrv_query($conn, $update_sql, array($payment_id));
        
        set_message('Full payment request submitted to HR!', 'success');
        
    } else {
        // Request split payment (50/50)
        $first_half = $payment['Amount'] / 2;
        $second_half = $payment['Amount'] - $first_half;
        
        $update_sql = "UPDATE LeavePayments 
                      SET PaymentSplitChoice = 'split',
                          RequestedByEmployee = 1,
                          EmployeeRequestDate = GETDATE(),
                          FirstPaymentAmount = ?,
                          SecondPaymentAmount = ?
                      WHERE PaymentID = ?";
        
        sqlsrv_query($conn, $update_sql, array($first_half, $second_half, $payment_id));
        
        set_message('Split payment request submitted to HR!', 'success');
    }
    
    // Send email to HR
    require_once __DIR__ . '/../config/email_notifications.php';
    send_employee_payment_request_to_hr($payment_id, $conn);
    
    redirect('index.php');
}

$user_name = $_SESSION['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Leave Payment</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 24px; }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 24px;
        }
        
        .leave-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .leave-info p {
            margin: 10px 0;
            color: #666;
            font-size: 15px;
        }
        
        .leave-info strong {
            color: #333;
        }
        
        .amount-display {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin: 25px 0;
        }
        
        .amount-display h3 {
            font-size: 16px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .amount-display .amount {
            font-size: 48px;
            font-weight: 700;
        }
        
        .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        
        .option-card {
            border: 3px solid #e0e0e0;
            padding: 25px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .option-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .option-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .option-card input[type="radio"]:checked + label {
            color: #667eea;
        }
        
        .option-card input[type="radio"]:checked ~ .card-content {
            border-color: #667eea;
        }
        
        .option-card label {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            display: block;
            margin-bottom: 15px;
        }
        
        .option-card .description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .option-card .highlight {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 6px;
            font-size: 15px;
            color: #004085;
            font-weight: 600;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            color: #667eea;
            text-decoration: none;
            border: 2px solid #667eea;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💰 Request Leave Payment</h1>
    </div>
    
    <div class="container">
        <a href="index.php" class="btn-back">← Back to Dashboard</a>
        
        <div class="card">
            <h2>Choose Payment Option</h2>
            
            <div class="leave-info">
                <p><strong>Leave Type:</strong> <?php echo htmlspecialchars($payment['LeaveType']); ?></p>
                <p><strong>Period:</strong> <?php echo $payment['StartDate']->format('M d, Y'); ?> - <?php echo $payment['EndDate']->format('M d, Y'); ?></p>
                <p><strong>Total Days:</strong> <?php echo $payment['TotalDays']; ?> days</p>
            </div>
            
            <div class="amount-display">
                <h3>Total Leave Allowance</h3>
                <div class="amount">₦<?php echo number_format($payment['Amount'], 2); ?></div>
            </div>
            
            <form method="POST" action="">
                <div class="payment-options">
                    <!-- Full Payment Option -->
                    <div class="option-card">
                        <input type="radio" name="payment_choice" value="full" id="full" required checked>
                        <label for="full">💵 Full Payment</label>
                        <div class="description">
                            Receive the entire allowance in one payment.
                        </div>
                        <div class="highlight">
                            ₦<?php echo number_format($payment['Amount'], 2); ?>
                        </div>
                    </div>
                    
                    <!-- Split Payment Option -->
                    <div class="option-card">
                        <input type="radio" name="payment_choice" value="split" id="split" required>
                        <label for="split">💳 Split Payment</label>
                        <div class="description">
                            Split into two equal payments (50% now, 50% later).
                        </div>
                        <div class="highlight">
                            ₦<?php echo number_format($payment['Amount'] / 2, 2); ?> × 2
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Submit Payment Request</button>
            </form>
        </div>
    </div>
</body>
</html>