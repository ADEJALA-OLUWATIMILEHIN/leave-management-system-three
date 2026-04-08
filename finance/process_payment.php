<?php
/**
 * Process Payment - Finance Portal
 * Finance reviews and marks leave payment as paid
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_notifications.php';

if (!is_logged_in() || !is_finance()) {
    redirect('login.php');
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$payment_id) redirect('index.php');

// Fetch full payment details
$sql = "SELECT 
            lp.*,
            emp.FirstName + ' ' + emp.LastName as EmployeeName,
            emp.Email  as EmployeeEmail,
            emp.Department,
            emp.EmployeeNumber,
            emp.Salary,
            lt.TypeName  as LeaveType,
            lr.StartDate,
            lr.EndDate,
            lr.TotalDays,
            lr.Reason,
            hr_user.FirstName + ' ' + hr_user.LastName as ApprovedByHRName
        FROM LeavePayments lp
        JOIN Users emp       ON lp.EmployeeID   = emp.UserID
        JOIN LeaveRequests lr ON lp.RequestID    = lr.RequestID
        JOIN LeaveTypes lt    ON lr.LeaveTypeID  = lt.LeaveTypeID
        LEFT JOIN Users hr_user ON lp.ApprovedByHR = hr_user.UserID
        WHERE lp.PaymentID = ?";

$stmt = sqlsrv_query($conn, $sql, array($payment_id));
if ($stmt === false) {
    die('<p style="color:red;padding:20px;">DB Error: ' . print_r(sqlsrv_errors(), true) . '</p>');
}
$payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$payment) redirect('index.php');

$message      = '';
$message_type = '';

// ── Handle POST (mark as paid) ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method  = sanitize_input($_POST['payment_method']);
    $finance_notes   = sanitize_input($_POST['finance_notes']);
    $reference       = 'PAY-' . date('Ymd') . '-' . str_pad($payment_id, 5, '0', STR_PAD_LEFT);

    $update_sql = "UPDATE LeavePayments
                   SET Status           = 'Paid',
                       ProcessedBy      = ?,
                       ProcessedDate    = GETDATE(),
                       PaymentReference = ?,
                       PaymentMethod    = ?,
                       FinanceNotes     = ?,
                       UpdatedAt        = GETDATE()
                   WHERE PaymentID = ?";

    $update_stmt = sqlsrv_query($conn, $update_sql,
        array($_SESSION['user_id'], $reference, $payment_method, $finance_notes, $payment_id));

    if ($update_stmt) {
        // Send confirmation emails
        if (function_exists('send_finance_to_employee_confirmation')) {
            send_finance_to_employee_confirmation($payment_id, $conn);
        }
        if (function_exists('send_finance_payment_notification_to_hr')) {
            send_finance_payment_notification_to_hr($payment_id, $conn);
        }

        set_message('Payment of ₦' . number_format($payment['Amount'], 2) . ' marked as PAID. Confirmation emails sent.', 'success');
        sqlsrv_free_stmt($update_stmt);
        redirect('index.php');
    } else {
        $err          = sqlsrv_errors();
        $message      = 'Error processing payment: ' . ($err[0]['message'] ?? 'Unknown error');
        $message_type = 'error';
    }
}

// Formatted dates
$start_date     = ($payment['StartDate']  instanceof DateTime) ? $payment['StartDate']->format('M d, Y')  : 'N/A';
$end_date       = ($payment['EndDate']    instanceof DateTime) ? $payment['EndDate']->format('M d, Y')    : 'N/A';
$created_date   = ($payment['CreatedAt'] instanceof DateTime) ? $payment['CreatedAt']->format('M d, Y H:i') : 'N/A';
$monthly_salary = $payment['Salary'] ?? 100000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment #<?php echo $payment_id; ?> - Finance</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0faf4;}

        .header{background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:white;padding:20px 30px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.12);}
        .header h1{font-size:24px;}
        .btn-back{padding:9px 20px;background:rgba(255,255,255,.2);color:white;text-decoration:none;border-radius:6px;font-weight:600;border:1px solid rgba(255,255,255,.3);}

        .container{max-width:900px;margin:32px auto;padding:0 20px;display:flex;flex-direction:column;gap:22px;}

        .card{background:white;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;}
        .card-header{padding:18px 26px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:12px;}
        .card-header h2{font-size:17px;color:#333;}
        .card-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0;}
        .icon-green{background:#d4edda;}
        .icon-blue{background:#d1ecf1;}
        .icon-yellow{background:#fff3cd;}
        .card-body{padding:26px;}

        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
        .info-item{padding:14px 16px;background:#f8f9fa;border-radius:9px;}
        .info-item label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#888;font-weight:600;margin-bottom:5px;}
        .info-item .val{font-size:15px;color:#222;font-weight:500;}

        /* Amount hero */
        .amount-hero{background:linear-gradient(135deg,#28a745,#20c997);border-radius:12px;padding:28px;text-align:center;color:white;margin-bottom:20px;}
        .amount-hero .label{font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.85;margin-bottom:8px;}
        .amount-hero .figure{font-size:52px;font-weight:800;line-height:1;}
        .amount-hero .sub{font-size:13px;opacity:.75;margin-top:8px;}

        /* Alert */
        .alert{padding:14px 18px;border-radius:8px;font-weight:500;font-size:14px;}
        .alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

        /* Form */
        .form-group{margin-bottom:20px;}
        .form-group label{display:block;margin-bottom:7px;font-weight:600;color:#333;font-size:14px;}
        .form-group select,
        .form-group textarea{width:100%;padding:12px 14px;border:2px solid #e0e0e0;border-radius:9px;font-family:inherit;font-size:14px;transition:.25s;}
        .form-group select:focus,
        .form-group textarea:focus{outline:none;border-color:#28a745;box-shadow:0 0 0 3px rgba(40,167,69,.12);}

        .ref-box{background:#e9f7ef;border:1px solid #b7dfca;border-radius:8px;padding:12px 16px;font-size:13px;color:#155724;margin-bottom:20px;}
        .ref-box strong{font-family:monospace;font-size:15px;}

        .btn-submit{width:100%;padding:16px;background:linear-gradient(135deg,#28a745,#20c997);color:white;border:none;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:.25s;}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(40,167,69,.35);}

        .status-paid{background:#d4edda;color:#155724;padding:5px 13px;border-radius:20px;font-size:12px;font-weight:700;}
        .status-pending{background:#fff3cd;color:#856404;padding:5px 13px;border-radius:20px;font-size:12px;font-weight:700;}

        @media(max-width:600px){.amount-hero .figure{font-size:36px;}}
    </style>
</head>
<body>

<div class="header">
    <h1>&#128176; Process Payment #<?php echo $payment_id; ?></h1>
    <a href="index.php" class="btn-back">&#8592; Back</a>
</div>

<div class="container">

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Amount hero -->
    <div class="amount-hero" style="border-radius:14px;">
        <div class="label">Leave Allowance to Process</div>
        <div class="figure">&#8358;<?php echo number_format($payment['Amount'], 2); ?></div>
        <div class="sub">
            &#8358;<?php echo number_format($monthly_salary, 2); ?> &divide; 30 &times; <?php echo $payment['TotalDays']; ?> days
        </div>
    </div>

    <!-- Employee info -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon icon-blue">&#128100;</div>
            <h2>Employee Information</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item"><label>Full Name</label><div class="val"><?php echo htmlspecialchars($payment['EmployeeName']); ?></div></div>
                <div class="info-item"><label>Employee No.</label><div class="val"><?php echo htmlspecialchars($payment['EmployeeNumber']); ?></div></div>
                <div class="info-item"><label>Department</label><div class="val"><?php echo htmlspecialchars($payment['Department']); ?></div></div>
                <div class="info-item"><label>Email</label><div class="val"><?php echo htmlspecialchars($payment['EmployeeEmail']); ?></div></div>
            </div>
        </div>
    </div>

    <!-- Leave details -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon icon-blue">&#128197;</div>
            <h2>Leave Details</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item"><label>Leave Type</label><div class="val"><?php echo htmlspecialchars($payment['LeaveType']); ?></div></div>
                <div class="info-item"><label>Total Days</label><div class="val"><?php echo $payment['TotalDays']; ?> days</div></div>
                <div class="info-item"><label>Start Date</label><div class="val"><?php echo $start_date; ?></div></div>
                <div class="info-item"><label>End Date</label><div class="val"><?php echo $end_date; ?></div></div>
                <div class="info-item"><label>HR Approved By</label><div class="val"><?php echo htmlspecialchars($payment['ApprovedByHRName'] ?? 'N/A'); ?></div></div>
                <div class="info-item"><label>Payment Created</label><div class="val"><?php echo $created_date; ?></div></div>
                <div class="info-item"><label>Current Status</label><div class="val"><span class="status-<?php echo strtolower($payment['Status']); ?>"><?php echo $payment['Status']; ?></span></div></div>
            </div>
        </div>
    </div>

    <!-- Process form (only if pending) -->
    <?php if ($payment['Status'] === 'Pending'): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-icon icon-green">&#9989;</div>
            <h2>Mark as Paid</h2>
        </div>
        <div class="card-body">
            <div class="ref-box">
                Auto-generated reference: <strong>PAY-<?php echo date('Ymd') . '-' . str_pad($payment_id, 5, '0', STR_PAD_LEFT); ?></strong>
            </div>

            <form method="POST" action=""
                  onsubmit="return confirm('Confirm payment of ₦<?php echo number_format($payment['Amount'], 2); ?> to <?php echo htmlspecialchars($payment['EmployeeName']); ?>?')">

                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" required>
                        <option value="">-- Select method --</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cash">Cash</option>
                        <option value="NEFT">NEFT</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Finance Notes (optional)</label>
                    <textarea name="finance_notes" rows="3"
                        placeholder="Any notes about this payment (bank details used, batch number, etc.)..."></textarea>
                </div>

                <button type="submit" class="btn-submit">&#9989; &nbsp;Confirm &amp; Mark as PAID</button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:40px;">
            <div style="font-size:48px;margin-bottom:12px;">&#9989;</div>
            <h3 style="color:#28a745;margin-bottom:8px;">Payment Already Processed</h3>
            <p style="color:#666;">This payment was marked as <strong>Paid</strong> on
                <?php echo ($payment['ProcessedDate'] instanceof DateTime) ? $payment['ProcessedDate']->format('M d, Y H:i') : 'N/A'; ?>.
            </p>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
<?php sqlsrv_free_stmt($stmt); ?>
