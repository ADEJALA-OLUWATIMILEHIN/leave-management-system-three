<?php
/**
 * Recall Employee from Leave - HR Portal
 * Marks employee as recalled, calculates days actually taken,
 * deducts only used days, refunds unused leave balance
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || !is_hr()) {
    redirect('../login.php');
}

$message      = '';
$message_type = '';

// ── Handle Recall POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id    = (int)$_POST['request_id'];
    $recall_date   = $_POST['recall_date'];   // actual date employee returned
    $recall_reason = sanitize_input($_POST['recall_reason']);

    // Fetch the leave request
    $fetch_sql  = "SELECT lr.*, u.UserID as EmpUserID, lt.TypeName
                   FROM LeaveRequests lr
                   JOIN Users u  ON lr.UserID      = u.UserID
                   JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                   WHERE lr.RequestID = ? AND lr.HRApprovalStatus = 'approved'";
    $fetch_stmt = sqlsrv_query($conn, $fetch_sql, array($request_id));
    $leave      = sqlsrv_fetch_array($fetch_stmt, SQLSRV_FETCH_ASSOC);

    if (!$leave) {
        $message      = 'Leave request not found or not approved.';
        $message_type = 'error';
    } else {
        // Calculate days actually taken (start → recall date)
        $start      = new DateTime($leave['StartDate']->format('Y-m-d'));
        $recall     = new DateTime($recall_date);
        $days_taken = max(1, $start->diff($recall)->days); // at least 1

        $days_original = (int)$leave['TotalDays'];
        $days_refund   = max(0, $days_original - $days_taken);

        // 1. Mark leave request as recalled
        $upd_sql  = "UPDATE LeaveRequests
                     SET Status            = 'recalled',
                         RecallDate        = ?,
                         RecallReason      = ?,
                         DaysTaken         = ?,
                         DaysRefunded      = ?,
                         UpdatedAt         = GETDATE()
                     WHERE RequestID = ?";
        $upd_stmt = sqlsrv_query($conn, $upd_sql,
            array($recall_date, $recall_reason, $days_taken, $days_refund, $request_id));

        if ($upd_stmt) {
            // 2. Restore unused days back to leave balance
            if ($days_refund > 0) {
                $bal_sql  = "UPDATE LeaveBalances
                             SET DaysUsed      = DaysUsed - ?,
                                 DaysRemaining = DaysRemaining + ?
                             WHERE UserID = ? AND LeaveTypeID = ? AND Year = YEAR(GETDATE())";
                sqlsrv_query($conn, $bal_sql,
                    array($days_refund, $days_refund, $leave['EmpUserID'], $leave['LeaveTypeID']));
            }

            // 3. If Annual Leave — cancel/reverse the payment if still Pending
            $pay_sql  = "UPDATE LeavePayments
                         SET Status      = 'Cancelled',
                             FinanceNotes = 'Payment cancelled — employee recalled after ' . CAST(? AS NVARCHAR) + ' days. ' + CAST(? AS NVARCHAR) + ' days refunded.',
                             UpdatedAt   = GETDATE()
                         WHERE RequestID = ? AND Status = 'Pending'";
            sqlsrv_query($conn, $pay_sql,
                array($days_taken, $days_refund, $request_id));

            $message      = "Employee recalled successfully. Days taken: <strong>$days_taken</strong>. Days refunded to balance: <strong>$days_refund</strong>.";
            $message_type = 'success';

            sqlsrv_free_stmt($upd_stmt);
        } else {
            $err          = sqlsrv_errors();
            $message      = 'Error processing recall: ' . ($err[0]['message'] ?? 'Unknown');
            $message_type = 'error';
        }
        sqlsrv_free_stmt($fetch_stmt);
    }
}

// ── Fetch all currently active (approved, not yet recalled) leaves ────────────
$active_sql = "SELECT
                   lr.RequestID,
                   lr.StartDate,
                   lr.EndDate,
                   lr.TotalDays,
                   lr.Status,
                   u.FirstName + ' ' + u.LastName as EmployeeName,
                   u.Department,
                   u.EmployeeNumber,
                   lt.TypeName
               FROM LeaveRequests lr
               JOIN Users u       ON lr.UserID      = u.UserID
               JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
               WHERE lr.HRApprovalStatus = 'approved'
                 AND lr.Status IN ('approved', 'active')
               ORDER BY lr.StartDate ASC";
$active_stmt = sqlsrv_query($conn, $active_sql);

// ── Recall history ────────────────────────────────────────────────────────────
$history_sql = "SELECT TOP 20
                    lr.RequestID,
                    lr.RecallDate,
                    lr.RecallReason,
                    lr.DaysTaken,
                    lr.DaysRefunded,
                    lr.TotalDays,
                    u.FirstName + ' ' + u.LastName as EmployeeName,
                    u.Department,
                    lt.TypeName
                FROM LeaveRequests lr
                JOIN Users u       ON lr.UserID      = u.UserID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.Status = 'recalled'
                ORDER BY lr.RecallDate DESC";
$history_stmt = sqlsrv_query($conn, $history_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recall Employee - HR</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f7fa;}
        .header{background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);color:white;padding:16px 30px;display:flex;justify-content:space-between;align-items:center;}
        .header h1{font-size:22px;}
        .btn-logout{padding:8px 16px;background:rgba(255,255,255,.2);color:white;text-decoration:none;border-radius:5px;}
        .nav-menu{background:white;padding:0 20px;box-shadow:0 2px 4px rgba(0,0,0,.05);display:flex;gap:4px;flex-wrap:wrap;}
        .nav-menu a{padding:15px 16px;text-decoration:none;color:#333;font-weight:500;font-size:14px;border-bottom:3px solid transparent;}
        .nav-menu a.active{color:#4facfe;border-bottom-color:#4facfe;}
        .container{max-width:1200px;margin:28px auto;padding:0 20px;display:flex;flex-direction:column;gap:22px;}

        .alert{padding:13px 18px;border-radius:8px;font-size:14px;}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
        .alert-error  {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

        .card{background:white;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow:hidden;}
        .card-header{padding:18px 24px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;}
        .card-header h2{font-size:17px;color:#333;font-weight:700;}
        .card-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0;background:#fff3cd;}
        .card-body{padding:22px;}

        /* Active leaves table */
        table{width:100%;border-collapse:collapse;}
        th{padding:12px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#666;font-weight:600;background:#f8f9fa;}
        td{padding:13px 14px;border-bottom:1px solid #f5f5f5;font-size:13px;vertical-align:middle;}
        tr:last-child td{border-bottom:none;}

        /* Recall modal */
        .recall-btn{padding:7px 16px;background:#dc3545;color:white;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;}
        .recall-btn:hover{background:#c82333;}

        /* Modal overlay */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal{background:white;border-radius:16px;padding:32px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);}
        .modal h3{font-size:20px;margin-bottom:8px;color:#333;}
        .modal p{font-size:14px;color:#666;margin-bottom:20px;}
        .modal-info{background:#fff3cd;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:13px;color:#856404;}
        .modal-info strong{display:block;font-size:15px;margin-bottom:4px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;}
        .form-group input,
        .form-group textarea{width:100%;padding:11px 13px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;font-family:inherit;transition:.2s;}
        .form-group input:focus,
        .form-group textarea:focus{outline:none;border-color:#dc3545;}
        .days-preview{background:#f8d7da;border-radius:8px;padding:12px 16px;font-size:13px;color:#721c24;margin-bottom:16px;display:none;}
        .btn-confirm{width:100%;padding:13px;background:#dc3545;color:white;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;margin-bottom:8px;}
        .btn-confirm:hover{background:#c82333;}
        .btn-cancel-modal{width:100%;padding:11px;background:#f0f0f0;color:#333;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;}

        /* History table */
        .badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;}
        .badge-recalled{background:#f8d7da;color:#721c24;}
        .empty{text-align:center;padding:40px;color:#999;font-size:14px;}
    </style>
</head>
<body>

<div class="header">
    <h1> Recall Employee from Leave</h1>
    <a href="../logout.php" class="btn-logout">Logout</a>
</div>

<div class="nav-menu">
    <a href="index.php">Dashboard</a>
    <a href="all_requests.php">All Requests</a>
    <a href="manage_employees.php">Employees</a>
    <a href="recall_employee.php"> Recall</a>
    <a href="payment_tracking.php"> Payments</a>
    <a href="calendar.php">Calendar</a>
    <a href="reports.php">Reports</a>
</div>

<div class="container">

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Currently On Leave -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">&#127968;</div>
            <h2>Employees Currently On Leave</h2>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($active_stmt && sqlsrv_has_rows($active_stmt)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Dept</th>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Total Days</th>
                        <th>Days Left</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($a = sqlsrv_fetch_array($active_stmt, SQLSRV_FETCH_ASSOC)):
                    $today     = new DateTime();
                    $end_dt    = $a['EndDate'] instanceof DateTime ? $a['EndDate'] : new DateTime($a['EndDate']);
                    $days_left = max(0, (int)$today->diff($end_dt)->days);
                    $start_fmt = ($a['StartDate'] instanceof DateTime) ? $a['StartDate']->format('M d, Y') : 'N/A';
                    $end_fmt   = ($a['EndDate']   instanceof DateTime) ? $a['EndDate']->format('M d, Y')   : 'N/A';
                    $start_val = ($a['StartDate'] instanceof DateTime) ? $a['StartDate']->format('Y-m-d')  : '';
                    $end_val   = ($a['EndDate']   instanceof DateTime) ? $a['EndDate']->format('Y-m-d')    : '';
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($a['EmployeeName']); ?></strong><br>
                        <small style="color:#999;"><?php echo htmlspecialchars($a['EmployeeNumber']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($a['Department']); ?></td>
                    <td><?php echo htmlspecialchars($a['TypeName']); ?></td>
                    <td><?php echo $start_fmt; ?></td>
                    <td><?php echo $end_fmt; ?></td>
                    <td><?php echo $a['TotalDays']; ?>d</td>
                    <td><strong style="color:#dc3545;"><?php echo $days_left; ?>d</strong></td>
                    <td>
                        <button class="recall-btn"
                            onclick="openRecall(
                                <?php echo $a['RequestID']; ?>,
                                '<?php echo htmlspecialchars($a['EmployeeName']); ?>',
                                '<?php echo $start_val; ?>',
                                '<?php echo $a['TotalDays']; ?>',
                                '<?php echo $end_val; ?>'
                            )">
                            &#128222; Recall
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <div style="font-size:40px;margin-bottom:10px;">&#127774;</div>
                <p>No employees are currently on leave.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recall History -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon" style="background:#f8d7da;">&#128203;</div>
            <h2>Recall History</h2>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if ($history_stmt && sqlsrv_has_rows($history_stmt)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Dept</th>
                        <th>Leave Type</th>
                        <th>Original Days</th>
                        <th>Days Taken</th>
                        <th>Days Refunded</th>
                        <th>Recall Date</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($h = sqlsrv_fetch_array($history_stmt, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($h['EmployeeName']); ?></strong></td>
                    <td><?php echo htmlspecialchars($h['Department']); ?></td>
                    <td><?php echo htmlspecialchars($h['TypeName']); ?></td>
                    <td><?php echo $h['TotalDays']; ?>d</td>
                    <td><strong style="color:#dc3545;"><?php echo $h['DaysTaken']; ?>d</strong></td>
                    <td><strong style="color:#28a745;">+<?php echo $h['DaysRefunded']; ?>d</strong></td>
                    <td>
                        <?php echo ($h['RecallDate'] instanceof DateTime) ? $h['RecallDate']->format('M d, Y') : ($h['RecallDate'] ?? 'N/A'); ?>
                    </td>
                    <td style="font-size:12px;color:#666;max-width:200px;"><?php echo htmlspecialchars($h['RecallReason'] ?? ''); ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty"><p>No recall history yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Recall Modal -->
<div class="modal-overlay" id="recall-modal">
    <div class="modal">
        <h3> Recall Employee</h3>
        <p>Enter the date the employee returned to work. Unused leave days will be automatically refunded to their balance.</p>

        <div class="modal-info">
            <strong id="modal-emp-name">Employee Name</strong>
            Leave started: <span id="modal-start-date">—</span> &nbsp;|&nbsp; Total days: <span id="modal-total-days">—</span>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="request_id" id="modal-request-id">

            <div class="form-group">
                <label>Date Employee Returned to Work *</label>
                <input type="date" name="recall_date" id="modal-recall-date" required>
            </div>

            <div class="days-preview" id="days-preview"></div>

            <div class="form-group">
                <label>Reason for Recall *</label>
                <textarea name="recall_reason" rows="3" required
                    placeholder="e.g. Urgent project requirement, staffing shortage..."></textarea>
            </div>

            <button type="submit" class="btn-confirm">&#9989; Confirm Recall & Refund Days</button>
            <button type="button" class="btn-cancel-modal" onclick="closeRecall()">Cancel</button>
        </form>
    </div>
</div>

<script>
let recallStartDate = '';
let recallTotalDays = 0;

function openRecall(requestId, empName, startDate, totalDays, endDate) {
    recallStartDate = startDate;
    recallTotalDays = parseInt(totalDays);
    document.getElementById('modal-request-id').value  = requestId;
    document.getElementById('modal-emp-name').textContent = empName;
    document.getElementById('modal-start-date').textContent = startDate;
    document.getElementById('modal-total-days').textContent = totalDays + ' days';
    // Allow HR to pick any date between leave start and leave end date
    const dateInput = document.getElementById('modal-recall-date');
    dateInput.min   = startDate;   // cannot be before leave started
    dateInput.max   = endDate;     // cannot be after leave ends
    dateInput.value = '';
    document.getElementById('days-preview').style.display = 'none';
    document.getElementById('modal-total-days').textContent = totalDays + ' days (ends ' + endDate + ')';
    document.getElementById('recall-modal').classList.add('open');
}

function closeRecall() {
    document.getElementById('recall-modal').classList.remove('open');
}

document.getElementById('modal-recall-date').addEventListener('change', function() {
    if (!recallStartDate || !this.value) return;
    const start   = new Date(recallStartDate);
    const recall  = new Date(this.value);
    const taken   = Math.max(1, Math.round((recall - start) / (1000*60*60*24)));
    const refund  = Math.max(0, recallTotalDays - taken);
    const preview = document.getElementById('days-preview');
    preview.style.display = 'block';
    preview.style.background = refund > 0 ? '#d4edda' : '#fff3cd';
    preview.style.color = refund > 0 ? '#155724' : '#856404';
    preview.style.padding = '12px 16px';
    preview.style.borderRadius = '8px';
    preview.innerHTML = `
        <strong>&#128203; Recall Summary:</strong><br><br>
        &#128197; Leave started: <strong>${recallStartDate}</strong><br>
        &#128222; Employee returns: <strong>${this.value}</strong><br><br>
        Days <strong>used</strong>: <strong>${taken} day${taken !== 1 ? 's' : ''}</strong><br>
        Days <strong>refunded</strong> back to balance: <strong>+${refund} day${refund !== 1 ? 's' : ''}</strong>
        ${refund === 0 ? '<br><br><em>No days to refund — the full leave period was used.</em>' : ''}
    `;
});

// Close modal on overlay click
document.getElementById('recall-modal').addEventListener('click', function(e) {
    if (e.target === this) closeRecall();
});
</script>

</body>
</html>
<?php
if ($active_stmt)  sqlsrv_free_stmt($active_stmt);
if ($history_stmt) sqlsrv_free_stmt($history_stmt);
?>
