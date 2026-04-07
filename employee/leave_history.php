<?php
/**
 * Leave History - Employee
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in()) {
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
            lp.Status as PaymentStatus
        FROM LeaveRequests lr
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        LEFT JOIN LeavePayments lp ON lr.RequestID = lp.RequestID
        WHERE lr.UserID = ?
        ORDER BY lr.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql, array($user_id));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave History</title>
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
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
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
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
        }
        td {
            padding: 18px 15px;
            border-bottom: 1px solid #f0f0f0;
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
        
        .btn-back {
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
        <h1>Leave History</h1>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="submit_leave.php">Apply for Leave</a>
        <a href="leave_history.php" class="active">Leave History</a>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>All Leave Requests</h2>
                <a href="submit_leave.php" class="btn-back">+ New Request</a>
            </div>
            
            <?php if ($stmt !== false && sqlsrv_has_rows($stmt)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>HOD Status</th>
                        <th>HR Status</th>
                        <th>Payment</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($req = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req['LeaveType']); ?></strong></td>
                        <td>
                            <?php echo $req['StartDate']->format('M d, Y'); ?> - 
                            <?php echo $req['EndDate']->format('M d, Y'); ?>
                        </td>
                        <td><?php echo $req['TotalDays']; ?> days</td>
                        <td>
                            <span class="status-badge status-<?php echo $req['HODApprovalStatus']; ?>">
                                <?php echo ucfirst($req['HODApprovalStatus']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $req['HRApprovalStatus']; ?>">
                                <?php echo ucfirst($req['HRApprovalStatus']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($req['PaymentID']): ?>
                                <strong style="color: #28a745;">₦<?php echo number_format($req['Amount'], 2); ?></strong><br>
                                <small><?php echo $req['PaymentStatus']; ?></small>
                            <?php else: ?>
                                <span style="color: #999;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $req['CreatedAt']->format('M d, Y'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No leave history found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if ($stmt) sqlsrv_free_stmt($stmt);
?>