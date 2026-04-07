<?php
/**
 * Manage Employees - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];

// Handle Add/Edit/Deactivate Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $department = sanitize_input($_POST['department']);
    $role = $_POST['role'];
    $salary = floatval($_POST['salary']);
    $annual_leave_allowance = floatval($_POST['annual_leave_allowance']); // NEW
    
    // Generate employee number
    $employee_number = 'EMP' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Hash default password
    $default_password = 'password123';
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO Users 
            (Email, Password, FirstName, LastName, PhoneNumber, Department, Role, 
             EmployeeNumber, Salary, AnnualLeaveAllowance, IsActive, MustChangePassword, CreatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, GETDATE())";
    
    $params = array(
        $email, 
        $hashed_password, 
        $first_name, 
        $last_name, 
        $phone, 
        $department, 
        $role, 
        $employee_number, 
        $salary,
        $annual_leave_allowance  // NEW
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        set_message('Employee added successfully! Default password: password123', 'success');
    } else {
        set_message('Error adding employee: ' . print_r(sqlsrv_errors(), true), 'error');
    }
    
    redirect('manage_employees.php');
}
            
            if ($insert_stmt) {
                // Get the newly created UserID
                $new_user_id = null;
                $id_sql = "SELECT UserID FROM Users WHERE Email = ?";
                $id_stmt = sqlsrv_query($conn, $id_sql, array($email));
                if ($id_row = sqlsrv_fetch_array($id_stmt, SQLSRV_FETCH_ASSOC)) {
                    $new_user_id = $id_row['UserID'];
                }
                
                // If this is an employee (not HOD), assign leave balances
                if ($role === 'employee' && $new_user_id) {
                    $current_year = date('Y');
                    
                    $balance_sql = "
                        INSERT INTO LeaveBalances (UserID, LeaveTypeID, Year, TotalDays, UsedDays, RemainingDays)
                        SELECT ?, LeaveTypeID, ?, MaxDaysPerYear, 0, MaxDaysPerYear
                        FROM LeaveTypes
                        WHERE IsActive = 1
                    ";
                    
                    sqlsrv_query($conn, $balance_sql, array($new_user_id, $current_year));
                }
                
                set_message('Employee added successfully! Default password: password123 - User will be required to change password on first login.', 'success');
            } else {
                set_message('Failed to add employee.', 'error');
            }
            redirect('manage_employees.php');
        }
        
        if ($action === 'deactivate') {
            $user_id = (int)$_POST['user_id'];
            $sql = "UPDATE Users SET IsActive = 0 WHERE UserID = ?";
            $params = array($user_id);
            
            if (sqlsrv_query($conn, $sql, $params)) {
                set_message('Employee deactivated successfully!', 'success');
            }
            redirect('manage_employees.php');
        }
        
        if ($action === 'activate') {
            $user_id = (int)$_POST['user_id'];
            $sql = "UPDATE Users SET IsActive = 1 WHERE UserID = ?";
            $params = array($user_id);
            
            if (sqlsrv_query($conn, $sql, $params)) {
                set_message('Employee activated successfully!', 'success');
            }
            redirect('manage_employees.php');
        }
    }
}

// Fetch all users
$users_sql = "SELECT UserID, Email, FirstName, LastName, Department, PhoneNumber, Role, DateJoined, IsActive 
              FROM Users 
              ORDER BY Role, FirstName";
$users_stmt = sqlsrv_query($conn, $users_sql);

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - HR Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 5px; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); }
        .nav-menu { background: white; padding: 15px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; gap: 20px; }
        .nav-menu a { padding: 8px 16px; text-decoration: none; color: #333; border-radius: 5px; font-weight: 500; }
        .nav-menu a:hover { background: #f0f0f0; }
        .nav-menu a.active { background: #4facfe; color: white; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section-header { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .section-header h2 { color: #333; font-size: 20px; }
        .btn-primary { padding: 10px 20px; background: #4facfe; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; display: inline-block; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; padding: 12px; background: #f8f9fa; color: #666; font-weight: 600; font-size: 14px; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #333; font-size: 14px; }
        table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .badge-employee { background: #e7f3ff; color: #004085; }
        .badge-hod { background: #fff3cd; color: #856404; }
        .badge-hr { background: #d1ecf1; color: #0c5460; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background: white; margin: 50px auto; padding: 30px; width: 90%; max-width: 500px; border-radius: 10px; max-height: 85vh; overflow-y: auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; margin-right: 5px; color: white; }
        .btn-deactivate { background: #dc3545; }
        .btn-activate { background: #28a745; }
        .btn-close { background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Admin - Manage Employees</h1>
        <div>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="nav-menu">
    <a href="index.php">Dashboard</a>
    <a href="all_requests.php">All Requests</a>
    <a href="manage_employees.php">Manage Employees</a>
    <a href="calendar.php">Calendar</a>
    <a href="manage_leave_types.php">Leave Types</a>
    <a href="reports.php">Reports</a>
    <a href="reports_export.php">Export</a>  
    <a href="settings.php">Settings</a>
</div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <div class="section-header">
                <h2>All Users</h2>
                <button class="btn-primary" onclick="document.getElementById('addModal').style.display='block'">+ Add New Employee</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Date Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = sqlsrv_fetch_array($users_stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></td>
                            <td><?php echo htmlspecialchars($user['Email']); ?></td>
                            <td><?php echo htmlspecialchars($user['PhoneNumber'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['Department']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['Role']; ?>">
                                    <?php echo ucfirst($user['Role']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['DateJoined']->format('M d, Y'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['IsActive'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $user['IsActive'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['IsActive']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                        <button type="submit" name="action" value="deactivate" class="btn-action btn-deactivate" onclick="return confirm('Deactivate this user?')">Deactivate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                        <button type="submit" name="action" value="activate" class="btn-action btn-activate">Activate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Employee Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Add New Employee</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone_number" placeholder="e.g., +234 800 000 0000">
                </div>
                <div class="form-group">
                    <label>Department *</label>
                    <input type="text" name="department" required>
                </div>
                <div class="form-group">
    <label for="salary">Monthly Salary *</label>
    <input type="number" id="salary" name="salary" step="0.01" min="0" required>
                </div>
                <div class="form-group">
    <label for="annual_leave_allowance">💰 Annual Leave Allowance *</label>
    <input 
        type="number" 
        id="annual_leave_allowance" 
        name="annual_leave_allowance" 
        step="0.01" 
        min="0" 
        placeholder="e.g., 70000.00"
        required
    >
    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
        Total amount employee receives for annual leave per year
    </small>
</div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="employee">Employee</option>
                        <option value="hod">HOD (Head of Department)</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary">Add Employee</button>
                    <button type="button" class="btn-close" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script>
// Auto-calculate annual leave allowance based on salary
document.getElementById('salary').addEventListener('input', function() {
    const salary = parseFloat(this.value) || 0;
    const dailyRate = salary / 30;
    const annualLeaveAllowance = dailyRate * 21; // Assuming 21 days annual leave
    
    document.getElementById('annual_leave_allowance').value = annualLeaveAllowance.toFixed(2);
});
</script>
</body>
</html>
<?php
if ($users_stmt) sqlsrv_free_stmt($users_stmt);
?>