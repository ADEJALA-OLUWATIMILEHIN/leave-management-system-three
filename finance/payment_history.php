<?php
/**
 * Payment History - Finance Portal
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || !is_finance()) {
    redirect('login.php');
}

// Month filter
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$sql = "SELECT
            lp.PaymentID,
            lp.Amount,
            lp.PaymentReference,
            lp.PaymentMethod,
            lp.ProcessedDate,
            lp.FinanceNotes,
            emp.FirstName + ' ' + emp.LastName as EmployeeName,
            emp.Department,
            emp.EmployeeNumber,
            lt.TypeName as LeaveType,
            lr.TotalDays,
            fin.FirstName + ' ' + fin.LastName as ProcessedByName
        FROM LeavePayments lp
        JOIN Users emp       ON lp.EmployeeID  = emp.UserID
        JOIN LeaveRequests lr ON lp.RequestID   = lr.RequestID
        JOIN LeaveTypes lt    ON lr.LeaveTypeID = lt.LeaveTypeID
        LEFT JOIN Users fin   ON lp.ProcessedBy = fin.UserID
        WHERE lp.Status = 'Paid'
          AND MONTH(lp.ProcessedDate) = ?
          AND YEAR(lp.ProcessedDate)  = ?
        ORDER BY lp.ProcessedDate DESC";

$stmt = sqlsrv_query($conn, $sql, array($month, $year));
if ($stmt === false) {
    die('<p style="color:red;padding:20px;">DB Error: ' . print_r(sqlsrv_errors(), true) . '</p>');
}

// Monthly total
$total_sql  = "SELECT SUM(Amount) as MonthTotal, COUNT(*) as MonthCount
               FROM LeavePayments
               WHERE Status='Paid' AND MONTH(ProcessedDate)=? AND YEAR(ProcessedDate)=?";
$total_stmt = sqlsrv_query($conn, $total_sql, array($month, $year));
$totals     = sqlsrv_fetch_array($total_stmt, SQLSRV_FETCH_ASSOC);

$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Finance</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0faf4;}
        .header{background:linear-gradient(135deg,#28a745 0%,#20c997 100%);color:white;padding:20px 30px;display:flex;justify-content:space-between;align-items:center;}
        .header h1{font-size:24px;}
        .btn-logout{padding:9px 18px;background:rgba(255,255,255,.2);color:white;text-decoration:none;border-radius:6px;font-weight:600;}
        .nav-menu{background:white;padding:0;box-shadow:0 2px 4px rgba(0,0,0,.05);display:flex;}
        .nav-menu a{padding:17px 24px;text-decoration:none;color:#333;font-weight:500;border-bottom:3px solid transparent;}
        .nav-menu a.active{color:#28a745;border-bottom-color:#28a745;background:#f8f9fa;}
        .container{max-width:1300px;margin:30px auto;padding:0 20px;}

        /* Month picker */
        .month-picker{background:white;padding:18px 22px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px rgba(0,0,0,.07);flex-wrap:wrap;}
        .month-picker label{font-weight:600;color:#333;font-size:14px;}
        .month-picker select{padding:9px 14px;border:2px solid #e0e0e0;border-radius:7px;font-size:14px;}
        .month-picker select:focus{outline:none;border-color:#28a745;}
        .btn-go{padding:10px 22px;background:#28a745;color:white;border:none;border-radius:7px;font-weight:600;cursor:pointer;}

        /* Summary bar */
        .summary-bar{background:linear-gradient(135deg,#28a745,#20c997);color:white;border-radius:12px;padding:22px 28px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;}
        .summary-bar h2{font-size:18px;font-weight:700;}
        .summary-bar .total{font-size:32px;font-weight:800;}
        .summary-bar .count{font-size:14px;opacity:.85;}

        /* Table */
        .table-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        thead{background:#f8f9fa;}
        th{padding:13px 15px;text-align:left;font-size:12px;text-transform:uppercase;color:#555;font-weight:600;}
        td{padding:14px 15px;border-bottom:1px solid #f0f0f0;font-size:14px;}
        tr:last-child td{border-bottom:none;}
        .empty{text-align:center;padding:50px;color:#999;}
    </style>
</head>
<body>

<div class="header">
    <h1>&#128203; Payment History</h1>
    <a href="../logout.php" class="btn-logout">Logout</a>
</div>

<div class="nav-menu">
    <a href="index.php">Dashboard</a>
    <a href="all_payments.php">All Payments</a>
    <a href="payment_history.php" class="active">Payment History</a>
</div>

<div class="container">

    <!-- Month Picker -->
    <form method="GET" action="">
        <div class="month-picker">
            <label>Filter by Month:</label>
            <select name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                    <?php echo $months[$m]; ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="year">
                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>>
                    <?php echo $y; ?>
                </option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn-go">View</button>
        </div>
    </form>

    <!-- Summary -->
    <div class="summary-bar">
        <div>
            <h2>&#128197; <?php echo $months[$month] . ' ' . $year; ?></h2>
            <div class="count"><?php echo $totals['MonthCount'] ?? 0; ?> payments processed</div>
        </div>
        <div class="total">&#8358;<?php echo number_format($totals['MonthTotal'] ?? 0, 2); ?></div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <?php if ($stmt !== false && sqlsrv_has_rows($stmt)): ?>
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Employee</th>
                    <th>Dept</th>
                    <th>Leave Type</th>
                    <th>Days</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Processed Date</th>
                    <th>Processed By</th>
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
                    <td><?php echo $p['TotalDays']; ?>d</td>
                    <td><strong style="color:#28a745;">&#8358;<?php echo number_format($p['Amount'], 2); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['PaymentMethod'] ?? '—'); ?></td>
                    <td style="font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($p['PaymentReference'] ?? '—'); ?></td>
                    <td><?php echo ($p['ProcessedDate'] instanceof DateTime) ? $p['ProcessedDate']->format('M d, Y H:i') : 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($p['ProcessedByName'] ?? '—'); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty">
            <div style="font-size:40px;margin-bottom:12px;">&#128203;</div>
            <p>No paid payments found for <?php echo $months[$month] . ' ' . $year; ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
<?php
sqlsrv_free_stmt($stmt);
sqlsrv_free_stmt($total_stmt);
?>
