<?php
/**
 * All Payments - Finance Portal
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || !is_finance()) {
    redirect('login.php');
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where = '1=1';
if ($filter === 'pending') $where = "lp.Status = 'Pending'";
if ($filter === 'paid')    $where = "lp.Status = 'Paid'";

$sql = "SELECT
            lp.PaymentID,
            lp.Amount,
            lp.Status,
            lp.PaymentReference,
            lp.PaymentMethod,
            lp.ProcessedDate,
            lp.CreatedAt,
            emp.FirstName + ' ' + emp.LastName as EmployeeName,
            emp.Department,
            emp.EmployeeNumber,
            lt.TypeName as LeaveType,
            lr.TotalDays,
            lr.StartDate,
            lr.EndDate,
            fin.FirstName + ' ' + fin.LastName as ProcessedByName
        FROM LeavePayments lp
        JOIN Users emp       ON lp.EmployeeID  = emp.UserID
        JOIN LeaveRequests lr ON lp.RequestID   = lr.RequestID
        JOIN LeaveTypes lt    ON lr.LeaveTypeID = lt.LeaveTypeID
        LEFT JOIN Users fin   ON lp.ProcessedBy = fin.UserID
        WHERE $where
        ORDER BY lp.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die('<p style="color:red;padding:20px;">DB Error: ' . print_r(sqlsrv_errors(), true) . '</p>');
}

// Stats
$stats_sql  = "SELECT
                COUNT(*) as Total,
                SUM(CASE WHEN Status='Pending' THEN 1 ELSE 0 END) as Pending,
                SUM(CASE WHEN Status='Paid'    THEN 1 ELSE 0 END) as Paid,
                SUM(CASE WHEN Status='Pending' THEN Amount ELSE 0 END) as PendingAmt,
                SUM(CASE WHEN Status='Paid'    THEN Amount ELSE 0 END) as PaidAmt
               FROM LeavePayments";
$stats_stmt = sqlsrv_query($conn, $stats_sql);
$stats      = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Payments - Finance</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0faf4;}
        .header{background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:white;padding:20px 30px;display:flex;justify-content:space-between;align-items:center;}
        .header h1{font-size:24px;}
        .btn-logout{padding:9px 18px;background:rgba(255,255,255,.2);color:white;text-decoration:none;border-radius:6px;font-weight:600;}
        .nav-menu{background:white;padding:0;box-shadow:0 2px 4px rgba(0,0,0,.05);display:flex;}
        .nav-menu a{padding:17px 24px;text-decoration:none;color:#333;font-weight:500;border-bottom:3px solid transparent;}
        .nav-menu a.active{color:#28a745;border-bottom-color:#28a745;background:#f8f9fa;}
        .container{max-width:1400px;margin:30px auto;padding:0 20px;}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:24px;}
        .stat-card{background:white;padding:22px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);}
        .stat-card h3{color:#888;font-size:12px;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;}
        .stat-card .num{font-size:34px;font-weight:700;}
        .stat-card .amt{font-size:15px;color:#666;margin-top:4px;}
        .stat-card.total{border-left:4px solid #17a2b8;} .stat-card.total .num{color:#17a2b8;}
        .stat-card.pending{border-left:4px solid #ffc107;} .stat-card.pending .num{color:#ffc107;}
        .stat-card.paid{border-left:4px solid #28a745;} .stat-card.paid .num{color:#28a745;}
        .filters{background:white;padding:16px 20px;border-radius:12px;margin-bottom:20px;display:flex;gap:10px;box-shadow:0 2px 8px rgba(0,0,0,.07);}
        .filter-btn{padding:9px 20px;border:2px solid #e0e0e0;background:white;border-radius:6px;text-decoration:none;color:#333;font-weight:600;font-size:13px;}
        .filter-btn.active{background:#28a745;color:white;border-color:#28a745;}
        .table-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        thead{background:#f8f9fa;}
        th{padding:14px 15px;text-align:left;font-size:12px;text-transform:uppercase;color:#555;font-weight:600;}
        td{padding:15px;border-bottom:1px solid #f0f0f0;font-size:14px;}
        tr:last-child td{border-bottom:none;}
        .badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;}
        .badge-pending{background:#fff3cd;color:#856404;}
        .badge-paid{background:#d4edda;color:#155724;}
        .btn-process{padding:6px 14px;background:#28a745;color:white;text-decoration:none;border-radius:5px;font-size:12px;font-weight:600;}
        .empty{text-align:center;padding:50px;color:#999;}
    </style>
</head>
<body>

<div class="header">
    <h1>&#128176; All Payments</h1>
    <a href="../logout.php" class="btn-logout">Logout</a>
</div>

<div class="nav-menu">
    <a href="index.php">Dashboard</a>
    <a href="all_payments.php" class="active">All Payments</a>
    <a href="payment_history.php">Payment History</a>
</div>

<div class="container">

    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Total</h3>
            <div class="num"><?php echo $stats['Total'] ?? 0; ?></div>
        </div>
        <div class="stat-card pending">
            <h3>Pending</h3>
            <div class="num"><?php echo $stats['Pending'] ?? 0; ?></div>
            <div class="amt">&#8358;<?php echo number_format($stats['PendingAmt'] ?? 0, 2); ?></div>
        </div>
        <div class="stat-card paid">
            <h3>Paid</h3>
            <div class="num"><?php echo $stats['Paid'] ?? 0; ?></div>
            <div class="amt">&#8358;<?php echo number_format($stats['PaidAmt'] ?? 0, 2); ?></div>
        </div>
    </div>

    <div class="filters">
        <a href="?filter=all"     class="filter-btn <?php echo $filter==='all'     ? 'active' : ''; ?>">All</a>
        <a href="?filter=pending" class="filter-btn <?php echo $filter==='pending' ? 'active' : ''; ?>">&#9203; Pending</a>
        <a href="?filter=paid"    class="filter-btn <?php echo $filter==='paid'    ? 'active' : ''; ?>">&#9989; Paid</a>
    </div>

    <div class="table-card">
        <?php if (sqlsrv_has_rows($stmt)): ?>
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Employee</th>
                    <th>Dept</th>
                    <th>Leave Type</th>
                    <th>Period</th>
                    <th>Days</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Processed By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($p = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <td><strong>#<?php echo $p['PaymentID']; ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($p['EmployeeName']); ?></strong><br>
                        <small style="color:#999;"><?php echo htmlspecialchars($p['EmployeeNumber']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($p['Department']); ?></td>
                    <td><?php echo htmlspecialchars($p['LeaveType']); ?></td>
                    <td>
                        <?php echo ($p['StartDate'] instanceof DateTime) ? $p['StartDate']->format('M d') : 'N/A'; ?> &ndash;
                        <?php echo ($p['EndDate']   instanceof DateTime) ? $p['EndDate']->format('M d, Y') : 'N/A'; ?>
                    </td>
                    <td><?php echo $p['TotalDays']; ?>d</td>
                    <td><strong style="color:#28a745;">&#8358;<?php echo number_format($p['Amount'], 2); ?></strong></td>
                    <td><span class="badge badge-<?php echo strtolower($p['Status']); ?>"><?php echo $p['Status']; ?></span></td>
                    <td style="font-family:monospace;font-size:12px;"><?php echo $p['PaymentReference'] ?? '—'; ?></td>
                    <td><?php echo htmlspecialchars($p['ProcessedByName'] ?? '—'); ?></td>
                    <td>
                        <?php if ($p['Status'] === 'Pending'): ?>
                            <a href="process_payment.php?id=<?php echo $p['PaymentID']; ?>" class="btn-process">Process</a>
                        <?php else: ?>
                            <span style="color:#28a745;font-size:18px;">&#10003;</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty">
            <div style="font-size:40px;margin-bottom:12px;">&#128176;</div>
            <p>No payments found for this filter.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
<?php
sqlsrv_free_stmt($stmt);
sqlsrv_free_stmt($stats_stmt);
?>
