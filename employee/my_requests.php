<?php
/**
 * My Leave Requests - Employee
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'employee') {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get all leave requests with payment info
$sql = "SELECT 
            lr.RequestID,
            lr.StartDate,
            lr.EndDate,
            lr.TotalDays,
            lr.Reason,
            lr.Status,
            lr.HODApprovalStatus,
            lr.HRApprovalStatus,
            lr.HODNotes,
            lr.HRNotes,
            lr.CreatedAt,
            lt.TypeName as LeaveType,
            lp.PaymentID,
            lp.Amount,
            lp.Status as PaymentStatus,
            lp.PaymentReference,
            lp.RequestedByEmployee
        FROM LeaveRequests lr
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        LEFT JOIN LeavePayments lp ON lr.RequestID = lp.RequestID
        WHERE lr.UserID = ?
        ORDER BY lr.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql, array($user_id));

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leave Requests</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 28px; }
        
        .nav-menu {
            background: white;
            padding: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
        }
        .nav-menu a {
            padding: 18px 25px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            border-bottom: 3px solid transparent;
        }
        .nav-menu a.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f8f9fa;
        }
        .nav-menu a:hover { background: #f8f9fa; }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-header h2 { font-size: 20px; color: #333; }
        
        .request-item {
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: 0.3s;
        }
        
        .request-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .request-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-paid { background: #d4edda; color: #155724; }
        
        .payment-section {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }
        
        .payment-section h4 {
            color: #155724;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .btn-request-payment {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-new {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 My Leave Requests</h1>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="submit_leave.php">Apply for Leave</a>
        <a href="my_requests.php" class="active">My Requests</a>
        <a href="../index.php" style="margin-left: auto; color: #667eea;">← Back to Main Portal</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>All Leave Requests</h2>
                <a href="submit_leave.php" class="btn-new">+ New Request</a>
            </div>
            
            <?php if ($stmt !== false && sqlsrv_has_rows($stmt)): ?>
                <?php while ($req = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                <div class="request-item">
                    <div class="request-header">
                        <div class="request-title">
                            <?php echo htmlspecialchars($req['LeaveType']); ?>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $req['Status']; ?>">
                                <?php echo ucfirst($req['Status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="request-details">
                        <div class="detail-item">
                            <div class="detail-label">Period</div>
                            <div class="detail-value">
                                <?php echo $req['StartDate']->format('M d'); ?> - <?php echo $req['EndDate']->format('M d, Y'); ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Days</div>
                            <div class="detail-value"><?php echo $req['TotalDays']; ?> days</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">HOD Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-<?php echo $req['HODApprovalStatus']; ?>">
                                    <?php echo ucfirst($req['HODApprovalStatus']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">HR Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-<?php echo $req['HRApprovalStatus']; ?>">
                                    <?php echo ucfirst($req['HRApprovalStatus']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Submitted</div>
                            <div class="detail-value"><?php echo $req['CreatedAt']->format('M d, Y'); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($req['Reason']): ?>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <div class="detail-label">Reason</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($req['Reason'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($req['PaymentID']): ?>
                    <div class="payment-section">
                        <h4>💰 Payment Information</h4>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 20px; color: #28a745;">
                                    ₦<?php echo number_format($req['Amount'], 2); ?>
                                </strong>
                                <br>
                                <small>Status: <?php echo $req['PaymentStatus']; ?></small>
                                <?php if ($req['PaymentReference']): ?>
                                    <br><small>Ref: <?php echo htmlspecialchars($req['PaymentReference']); ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($req['PaymentStatus'] === 'Pending' && !$req['RequestedByEmployee']): ?>
                                <a href="request_payment.php?id=<?php echo $req['PaymentID']; ?>" class="btn-request-payment">
                                    Request Payment
                                </a>
                            <?php elseif ($req['RequestedByEmployee']): ?>
                                <span style="color: #28a745; font-weight: 600;">✓ Requested</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No leave requests found. Click "New Request" to apply for leave.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if ($stmt) sqlsrv_free_stmt($stmt);
?>