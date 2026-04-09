<?php
/**
 * All Payments - Finance
 * View all payments with filters
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'finance') {
    redirect('login.php');
}

$user_name = $_SESSION['name'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = array("1=1");
$params = array();

if ($status_filter !== 'all') {
    $where_conditions[] = "lp.Status = ?";
    $params[] = ucfirst($status_filter);
}

if (!empty($date_from)) {
    $where_conditions[] = "CAST(lp.CreatedAt AS DATE) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "CAST(lp.CreatedAt AS DATE) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// Get all payments with filters
$sql = "SELECT 
            lp.PaymentID,
            lp.Amount,
            lp.Status,
            lp.PaymentReference,
            lp.PaymentMethod,
            lp.ProcessedDate,
            lp.CreatedAt,
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
         WHERE $where_clause
         ORDER BY lp.CreatedAt DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

// Calculate totals
$total_amount = 0;
$total_pending = 0;
$total_paid = 0;
$payment_count = 0;

if ($stmt !== false) {
    $payments = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $payments[] = $row;
        $payment_count++;
        $total_amount += $row['Amount'];
        if ($row['Status'] === 'Pending') {
            $total_pending += $row['Amount'];
        } else {
            $total_paid += $row['Amount'];
        }
    }
}

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Payments - Finance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 28px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .btn-logout {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        
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
            color: #28a745;
            border-bottom-color: #28a745;
            background: #f8f9fa;
        }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .btn-filter {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-reset {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-export {
            padding: 10px 20px;
            background: #17a2b8;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-export.excel { background: #217346; }
        .btn-export.pdf { background: #dc3545; }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .stat-box h4 {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-box .value {
            font-size: 28px;
            font-weight: 700;
            color: #28a745;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
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
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="header">
        <h1> All Payments</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="all_payments.php" class="active">All Payments</a>
        <a href="payment_history.php">Payment History</a>
    </div>
    
    <div class="container">
        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-filter">🔍 Filter</button>
                </div>
                
                <div class="form-group">
                    <a href="all_payments.php" class="btn-reset">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Export Buttons -->
        <div class="export-buttons">
            <a href="export_excel.php?status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-export excel">
                📊 Export to Excel
            </a>
            <a href="export_pdf.php?status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-export pdf">
                📄 Export to PDF
            </a>
        </div>
        
        <!-- Summary Stats -->
        <div class="stats-summary">
            <div class="stat-box">
                <h4>Total Payments</h4>
                <div class="value"><?php echo $payment_count; ?></div>
            </div>
            <div class="stat-box">
                <h4>Total Amount</h4>
                <div class="value">₦<?php echo number_format($total_amount, 2); ?></div>
            </div>
            <div class="stat-box">
                <h4>Pending</h4>
                <div class="value" style="color: #ffc107;">₦<?php echo number_format($total_pending, 2); ?></div>
            </div>
            <div class="stat-box">
                <h4>Paid</h4>
                <div class="value" style="color: #28a745;">₦<?php echo number_format($total_paid, 2); ?></div>
            </div>
        </div>
        
        <!-- Payments Table -->
        <div class="section">
            <?php if (!empty($payments)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Leave Type</th>
                        <th>Days</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><strong>#<?php echo $payment['PaymentID']; ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['EmployeeName']); ?></strong><br>
                            <small style="color: #999;"><?php echo htmlspecialchars($payment['EmployeeNumber']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($payment['Department']); ?></td>
                        <td><?php echo htmlspecialchars($payment['LeaveType']); ?></td>
                        <td><?php echo $payment['TotalDays']; ?> days</td>
                        <td><strong style="color: #28a745;">₦<?php echo number_format($payment['Amount'], 2); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                <?php echo $payment['Status']; ?>
                            </span>
                        </td>
                        <td><?php echo $payment['PaymentReference'] ?? '-'; ?></td>
                        <td>
                            <?php 
                            if ($payment['ProcessedDate']) {
                                echo $payment['ProcessedDate']->format('M d, Y');
                            } else {
                                echo $payment['CreatedAt']->format('M d, Y');
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #999;">No payments found matching your filters.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
if ($stmt) sqlsrv_free_stmt($stmt);
?>