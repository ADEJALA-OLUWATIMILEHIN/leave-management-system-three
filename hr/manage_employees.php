<?php
/**
 * Manage Employees - HR Portal
 * Add / Edit employees including Finance role & Salary
 */

require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || !is_hr()) {
    redirect('../login.php');
}

$message      = '';
$message_type = '';

// ── DELETE employee ──────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id   = (int)$_GET['delete'];
    $del_sql  = "UPDATE Users SET IsActive = 0 WHERE UserID = ? AND Role = 'employee'";
    $del_stmt = sqlsrv_query($conn, $del_sql, array($del_id));
    if ($del_stmt) {
        set_message('Employee deactivated successfully.', 'success');
    }
    redirect('manage_employees.php');
}

// ── ADD / EDIT employee ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action          = $_POST['action'] ?? 'add';
    $first_name      = sanitize_input($_POST['first_name']);
    $last_name       = sanitize_input($_POST['last_name']);
    $email           = sanitize_input($_POST['email']);
    $department      = sanitize_input($_POST['department']);
    $role            = sanitize_input($_POST['role']);
    $employee_number = sanitize_input($_POST['employee_number']);
    $salary          = (float)($_POST['salary'] ?? 0);

    if ($action === 'add') {
        // Default password = Sterling123?
        $hashed = password_hash('Sterling123?', PASSWORD_DEFAULT);

        $ins_sql = "INSERT INTO Users
                        (FirstName, LastName, Email, Password, Department, Role,
                         EmployeeNumber, Salary, IsActive, MustChangePassword, CreatedAt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, GETDATE())";

        $ins_stmt = sqlsrv_query($conn, $ins_sql,
            array($first_name, $last_name, $email, $hashed, $department, $role, $employee_number, $salary));

        if ($ins_stmt) {
            $message      = "Employee <strong>$first_name $last_name</strong> added successfully! Default password: <code>Sterling123?</code>";
            $message_type = 'success';
            sqlsrv_free_stmt($ins_stmt);
        } else {
            $err          = sqlsrv_errors();
            $message      = 'Error adding employee: ' . ($err[0]['message'] ?? 'Unknown');
            $message_type = 'error';
        }

    } elseif ($action === 'edit') {
        $user_id = (int)$_POST['user_id'];

        $upd_sql = "UPDATE Users
                    SET FirstName = ?, LastName = ?, Email = ?,
                        Department = ?, Role = ?, EmployeeNumber = ?,
                        Salary = ?, UpdatedAt = GETDATE()
                    WHERE UserID = ?";

        $upd_stmt = sqlsrv_query($conn, $upd_sql,
            array($first_name, $last_name, $email, $department, $role, $employee_number, $salary, $user_id));

        if ($upd_stmt) {
            $message      = "Employee updated successfully.";
            $message_type = 'success';
            sqlsrv_free_stmt($upd_stmt);
        } else {
            $err          = sqlsrv_errors();
            $message      = 'Error updating employee: ' . ($err[0]['message'] ?? 'Unknown');
            $message_type = 'error';
        }
    }
}

// ── Fetch all users ──────────────────────────────────────────────────────────
$users_sql  = "SELECT UserID, FirstName, LastName, Email, Department, Role,
                      EmployeeNumber, Salary, IsActive, CreatedAt
               FROM Users
               WHERE IsActive = 1
               ORDER BY Role, FirstName";
$users_stmt = sqlsrv_query($conn, $users_sql);

// Edit mode?
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id   = (int)$_GET['edit'];
    $edit_sql  = "SELECT * FROM Users WHERE UserID = ?";
    $edit_stmt = sqlsrv_query($conn, $edit_sql, array($edit_id));
    $edit_user = sqlsrv_fetch_array($edit_stmt, SQLSRV_FETCH_ASSOC);
}

$departments = ['Technical','Finance','HR','Operations','Sales','Marketing','Admin','Legal','Compliance','IT'];
$roles       = ['employee' => 'Employee', 'hod' => 'Head of Department', 'hr' => 'HR Admin', 'finance' => 'Finance', 'admin' => 'System Admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - HR</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f7fa;}
        .header{background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);color:white;padding:15px 30px;display:flex;justify-content:space-between;align-items:center;}
        .header h1{font-size:22px;}
        .btn-logout{padding:8px 16px;background:rgba(255,255,255,.2);color:white;text-decoration:none;border-radius:5px;}
        .nav-menu{background:white;padding:0 20px;box-shadow:0 2px 4px rgba(0,0,0,.05);display:flex;gap:4px;flex-wrap:wrap;}
        .nav-menu a{padding:15px 16px;text-decoration:none;color:#333;font-weight:500;font-size:14px;border-bottom:3px solid transparent;}
        .nav-menu a.active{color:#4facfe;border-bottom-color:#4facfe;}
        .container{max-width:1300px;margin:28px auto;padding:0 20px;display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start;}
        @media(max-width:900px){.container{grid-template-columns:1fr;}}

        .alert{padding:13px 18px;border-radius:8px;font-size:14px;margin-bottom:18px;}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
        .alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

        /* Form card */
        .form-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;position:sticky;top:20px;}
        .form-card-header{background:linear-gradient(135deg,#4facfe,#00f2fe);color:white;padding:18px 22px;}
        .form-card-header h2{font-size:17px;font-weight:700;}
        .form-card-body{padding:22px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;}
        .form-group input,
        .form-group select{width:100%;padding:11px 13px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;transition:.2s;}
        .form-group input:focus,
        .form-group select:focus{outline:none;border-color:#4facfe;box-shadow:0 0 0 3px rgba(79,172,254,.12);}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        .btn-submit{width:100%;padding:13px;background:linear-gradient(135deg,#4facfe,#00f2fe);color:white;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;}
        .btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(79,172,254,.35);}
        .btn-cancel{display:block;text-align:center;margin-top:10px;color:#666;text-decoration:none;font-size:13px;}

        /* Table card */
        .table-card{background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden;}
        .table-header{padding:18px 22px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;}
        .table-header h2{font-size:17px;color:#333;}
        table{width:100%;border-collapse:collapse;}
        thead{background:#f8f9fa;}
        th{padding:12px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#666;font-weight:600;}
        td{padding:13px 14px;border-bottom:1px solid #f0f0f0;font-size:13px;}
        tr:last-child td{border-bottom:none;}
        .role-badge{padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;}
        .role-employee{background:#e3f2fd;color:#1565c0;}
        .role-hr{background:#e8f5e9;color:#2e7d32;}
        .role-hod{background:#fce4ec;color:#c62828;}
        .role-finance{background:#fff8e1;color:#e65100;}
        .role-admin{background:#f3e5f5;color:#6a1b9a;}
        .actions-cell{display:flex;flex-direction:column;gap:5px;align-items:flex-start;}
        .btn-edit{padding:5px 14px;background:#4facfe;color:white;text-decoration:none;border-radius:4px;font-size:11px;font-weight:600;white-space:nowrap;display:inline-block;}
        .btn-del{padding:5px 14px;background:#dc3545;color:white;text-decoration:none;border-radius:4px;font-size:11px;font-weight:600;white-space:nowrap;display:inline-block;}
    </style>
</head>
<body>

<div class="header">
    <h1>&#128084; Manage Employees</h1>
    <a href="../logout.php" class="btn-logout">Logout</a>
</div>

<div class="nav-menu">
    <a href="index.php">Dashboard</a>
    <a href="all_requests.php">All Requests</a>
    <a href="manage_employees.php" class="active">Employees</a>
    <a href="payment_tracking.php">&#128176; Payments</a>
    <a href="calendar.php">Calendar</a>
    <a href="manage_leave_types.php">Leave Types</a>
    <a href="reports.php">Reports</a>
    <a href="reports_export.php">Export</a>
    <a href="settings.php">Settings</a>
</div>

<div style="max-width:1300px;margin:28px auto;padding:0 20px;">
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
</div>

<div class="container">

    <!-- ── Add / Edit Form ── -->
    <div class="form-card">
        <div class="form-card-header">
            <h2><?php echo $edit_user ? '&#9998; Edit Employee' : '&#43; Add New Employee'; ?></h2>
        </div>
        <div class="form-card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'add'; ?>">
                <?php if ($edit_user): ?>
                <input type="hidden" name="user_id" value="<?php echo $edit_user['UserID']; ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required
                            value="<?php echo htmlspecialchars($edit_user['FirstName'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required
                            value="<?php echo htmlspecialchars($edit_user['LastName'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required
                        value="<?php echo htmlspecialchars($edit_user['Email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Employee Number *</label>
                    <input type="text" name="employee_number" required
                        value="<?php echo htmlspecialchars($edit_user['EmployeeNumber'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Department *</label>
                    <select name="department" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept; ?>"
                            <?php echo (($edit_user['Department'] ?? '') === $dept) ? 'selected' : ''; ?>>
                            <?php echo $dept; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <?php foreach ($roles as $val => $label): ?>
                        <option value="<?php echo $val; ?>"
                            <?php echo (($edit_user['Role'] ?? 'employee') === $val) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Annual Leave Allowance (&#8358;) *</label>
                    <input type="number" name="salary" min="0" step="0.01" required
                        placeholder="e.g. 300000 (total annual leave allowance)"
                        value="<?php echo htmlspecialchars($edit_user['Salary'] ?? ''); ?>">
                </div>

                <?php if (!$edit_user): ?>
                <p style="font-size:12px;color:#888;margin-bottom:14px;background:#f8f9fa;padding:10px;border-radius:6px;">
                    &#128274; Default password: <strong>Sterling123?</strong><br>
                    Employee will be prompted to change it on first login.
                </p>
                <?php endif; ?>

                <button type="submit" class="btn-submit">
                    <?php echo $edit_user ? '&#9998; Update Employee' : '&#43; Add Employee'; ?>
                </button>

                <?php if ($edit_user): ?>
                <a href="manage_employees.php" class="btn-cancel">&#8592; Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ── Employee Table ── -->
    <div class="table-card">
        <div class="table-header">
            <h2>&#128100; All Active Users</h2>
            <span style="font-size:13px;color:#888;">
                <?php
                // count rows
                $cnt_stmt = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM Users WHERE IsActive=1");
                $cnt_row  = sqlsrv_fetch_array($cnt_stmt, SQLSRV_FETCH_ASSOC);
                echo ($cnt_row['c'] ?? 0) . ' users';
                ?>
            </span>
        </div>

        <?php if ($users_stmt && sqlsrv_has_rows($users_stmt)): ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Emp No.</th>
                    <th>Dept</th>
                    <th>Role</th>
                    <th>Leave Allowance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($u = sqlsrv_fetch_array($users_stmt, SQLSRV_FETCH_ASSOC)): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($u['FirstName'] . ' ' . $u['LastName']); ?></strong></td>
                <td style="font-size:12px;"><?php echo htmlspecialchars($u['Email']); ?></td>
                <td style="font-family:monospace;"><?php echo htmlspecialchars($u['EmployeeNumber'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($u['Department']); ?></td>
                <td>
                    <span class="role-badge role-<?php echo $u['Role']; ?>">
                        <?php echo $roles[$u['Role']] ?? ucfirst($u['Role']); ?>
                    </span>
                </td>
                <td>&#8358;<?php echo number_format($u['Salary'] ?? 0, 2); ?></td>
                <td>
                    <div class="actions-cell">
                        <a href="?edit=<?php echo $u['UserID']; ?>" class="btn-edit">&#9998; Edit</a>
                        <?php if ($u['Role'] === 'employee'): ?>
                        <a href="?delete=<?php echo $u['UserID']; ?>" class="btn-del"
                           onclick="return confirm('Deactivate this employee?')">&#10006; Remove</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align:center;padding:40px;color:#999;">No active employees found.</p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
<?php
if ($users_stmt) sqlsrv_free_stmt($users_stmt);
?>
