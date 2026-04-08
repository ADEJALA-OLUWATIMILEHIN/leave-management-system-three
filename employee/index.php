<?php
/**
 * Employee Portal - Entry Guard
 * Fixes incorrect redirect to XAMPP default page
 */

// Must be the FIRST thing - before any output
require_once __DIR__ . '/../config/database.php';

// Not logged in → go to employee login
if (!is_logged_in()) {
    redirect('login.php');
}

// Wrong role → redirect to correct portal
$role = $_SESSION['role'] ?? '';
if ($role === 'hr')      { redirect('../hr/index.php');      }
if ($role === 'hod')     { redirect('../hod/index.php');     }
if ($role === 'finance') { redirect('../finance/index.php'); }
if ($role !== 'employee') { redirect('login.php');           }

// ── Employee is valid — load dashboard ──────────────────────────────────────
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get employee's leave balance
$balance_sql = "SELECT
                    lt.TypeName,
                    lb.TotalEntitlement,
                    lb.DaysUsed,
                    lb.DaysRemaining
                FROM LeaveBalances lb
                JOIN LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
                WHERE lb.UserID = ? AND lb.Year = YEAR(GETDATE())";
$balance_stmt = sqlsrv_query($conn, $balance_sql, array($user_id));

// Get employee's recent leave requests
$requests_sql = "SELECT TOP 10
                    lr.RequestID,
                    lt.TypeName,
                    lr.StartDate,
                    lr.EndDate,
                    lr.TotalDays,
                    lr.HODApprovalStatus,
                    lr.HRApprovalStatus,
                    lr.Status,
                    lr.CreatedAt
                FROM LeaveRequests lr
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.UserID = ?
                ORDER BY lr.CreatedAt DESC";
$requests_stmt = sqlsrv_query($conn, $requests_sql, array($user_id));

// Get pending payments for employee
$payment_sql  = "SELECT lp.PaymentID, lp.Amount, lp.Status, lp.CreatedAt
                 FROM LeavePayments lp
                 WHERE lp.EmployeeID = ?
                 ORDER BY lp.CreatedAt DESC";
$payment_stmt = sqlsrv_query($conn, $payment_sql, array($user_id));

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Leave Management</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f7fa;}
        .header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:16px 30px;display:flex;justify-content:space-between;align-items:center;}
        .header h1{font-size:22px;}
        .header-right{display:flex;align-items:center;gap:18px;}
        .btn-logout{padding:8px 18px;background:rgba(255,255,255,.2);color:white;text-decoration:none;border-radius:6px;font-size:14px;border:1px solid rgba(255,255,255,.3);}
        .nav-menu{background:white;padding:0 24px;box-shadow:0 2px 4px rgba(0,0,0,.05);display:flex;gap:4px;flex-wrap:wrap;}
        .nav-menu a{padding:15px 16px;text-decoration:none;color:#333;font-weight:500;font-size:14px;border-bottom:3px solid transparent;}
        .nav-menu a.active{color:#667eea;border-bottom-color:#667eea;}
        .container{max-width:1200px;margin:28px auto;padding:0 20px;}
        .alert{padding:13px 18px;border-radius:8px;font-size:14px;margin-bottom:20px;}
        .alert-success{background:#d4edda;color:#155724;}
        .alert-error  {background:#f8d7da;color:#721c24;}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;margin-bottom:28px;}
        .stat-card{background:white;padding:22px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);}
        .stat-card h3{color:#888;font-size:12px;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;}
        .stat-card .num{font-size:32px;font-weight:700;color:#667eea;}
        .stat-card .sub{font-size:12px;color:#aaa;margin-top:4px;}
        .section{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:22px;overflow:hidden;}
        .section-header{padding:18px 22px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;}
        .section-header h2{font-size:17px;color:#333;}
        .btn-primary{padding:9px 20px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;text-decoration:none;border-radius:7px;font-size:13px;font-weight:600;}
        table{width:100%;border-collapse:collapse;}
        th{padding:12px 16px;text-align:left;font-size:11px;text-transform:uppercase;color:#666;font-weight:600;background:#f8f9fa;}
        td{padding:13px 16px;border-bottom:1px solid #f5f5f5;font-size:13px;}
        tr:last-child td{border-bottom:none;}
        .badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;}
        .badge-pending {background:#fff3cd;color:#856404;}
        .badge-approved{background:#d4edda;color:#155724;}
        .badge-rejected{background:#f8d7da;color:#721c24;}
        .badge-paid    {background:#d4edda;color:#155724;}
        .empty{text-align:center;padding:40px;color:#999;font-size:14px;}
    </style>
</head>
<body>

<div class="header">
    <h1>&#128100; Employee Dashboard</h1>
    <div class="header-right">
        <span>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="nav-menu">
    <a href="index.php" class="active">Dashboard</a>
    <a href="apply_leave.php">Apply for Leave</a>
    <a href="my_requests.php">My Requests</a>
    <a href="my_payments.php">My Payments</a>
</div>

<div class="container">

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message['type']; ?>"><?php echo $message['message']; ?></div>
    <?php endif; ?>

    <!-- Leave Balance Cards -->
    <?php if ($balance_stmt && sqlsrv_has_rows($balance_stmt)): ?>
    <div class="stats-grid">
        <?php while ($bal = sqlsrv_fetch_array($balance_stmt, SQLSRV_FETCH_ASSOC)): ?>
        <div class="stat-card">
            <h3><?php echo htmlspecialchars($bal['TypeName']); ?></h3>
            <div class="num"><?php echo $bal['DaysRemaining'] ?? 0; ?></div>
            <div class="sub">days remaining of <?php echo $bal['TotalEntitlement'] ?? 0; ?></div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <!-- Recent Requests -->
    <div class="section">
        <div class="section-header">
            <h2>&#128197; My Leave Requests</h2>
            <a href="apply_leave.php" class="btn-primary">+ Apply for Leave</a>
        </div>
        <?php if ($requests_stmt && sqlsrv_has_rows($requests_stmt)): ?>
        <table>
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>HOD Status</th>
                    <th>HR Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($req = sqlsrv_fetch_array($requests_stmt, SQLSRV_FETCH_ASSOC)): ?>
            <tr>
                <td><?php echo htmlspecialchars($req['TypeName']); ?></td>
                <td><?php echo ($req['StartDate'] instanceof DateTime) ? $req['StartDate']->format('M d, Y') : 'N/A'; ?></td>
                <td><?php echo ($req['EndDate']   instanceof DateTime) ? $req['EndDate']->format('M d, Y')   : 'N/A'; ?></td>
                <td><?php echo $req['TotalDays']; ?>d</td>
                <td><span class="badge badge-<?php echo $req['HODApprovalStatus']; ?>"><?php echo ucfirst($req['HODApprovalStatus']); ?></span></td>
                <td><span class="badge badge-<?php echo $req['HRApprovalStatus']; ?>"><?php echo ucfirst($req['HRApprovalStatus']); ?></span></td>
                <td><?php echo ($req['CreatedAt'] instanceof DateTime) ? $req['CreatedAt']->format('M d, Y') : 'N/A'; ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty">No leave requests yet. <a href="apply_leave.php">Apply for leave</a>.</div>
        <?php endif; ?>
    </div>

    <!-- Payments -->
    <div class="section">
        <div class="section-header">
            <h2> My Payments</h2>
        </div>
        <?php if ($payment_stmt && sqlsrv_has_rows($payment_stmt)): ?>
        <table>
            <thead>
                <tr><th>#</th><th>Amount</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php while ($pay = sqlsrv_fetch_array($payment_stmt, SQLSRV_FETCH_ASSOC)): ?>
            <tr>
                <td>#<?php echo $pay['PaymentID']; ?></td>
                <td><strong style="color:#28a745;">&#8358;<?php echo number_format($pay['Amount'], 2); ?></strong></td>
                <td><span class="badge badge-<?php echo strtolower($pay['Status']); ?>"><?php echo $pay['Status']; ?></span></td>
                <td><?php echo ($pay['CreatedAt'] instanceof DateTime) ? $pay['CreatedAt']->format('M d, Y') : 'N/A'; ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty">No payment records yet.</div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
<?php
if ($balance_stmt)  sqlsrv_free_stmt($balance_stmt);
if ($requests_stmt) sqlsrv_free_stmt($requests_stmt);
if ($payment_stmt)  sqlsrv_free_stmt($payment_stmt);
?>
