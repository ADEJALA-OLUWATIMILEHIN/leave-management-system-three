<?php
/**
 * Settings - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];
$hr_id = $_SESSION['user_id'];

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'reset_password') {
            $user_id = (int)$_POST['user_id'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                set_message('Passwords do not match!', 'error');
            } elseif (strlen($new_password) < 6) {
                set_message('Password must be at least 6 characters!', 'error');
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE Users SET Password = ? WHERE UserID = ?";
                $params = array($hash, $user_id);
                
                if (sqlsrv_query($conn, $sql, $params)) {
                    set_message('Password reset successfully!', 'success');
                } else {
                    set_message('Failed to reset password.', 'error');
                }
            }
            redirect('settings.php');
        }
        
        if ($action === 'change_my_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Get current password from DB
            $check_sql = "SELECT Password FROM Users WHERE UserID = ?";
            $check_params = array($hr_id);
            $check_stmt = sqlsrv_query($conn, $check_sql, $check_params);
            $user = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
            
            if (!password_verify($current_password, $user['Password'])) {
                set_message('Current password is incorrect!', 'error');
            } elseif ($new_password !== $confirm_password) {
                set_message('New passwords do not match!', 'error');
            } elseif (strlen($new_password) < 6) {
                set_message('Password must be at least 6 characters!', 'error');
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE Users SET Password = ? WHERE UserID = ?";
                $params = array($hash, $hr_id);
                
                if (sqlsrv_query($conn, $sql, $params)) {
                    set_message('Your password changed successfully!', 'success');
                } else {
                    set_message('Failed to change password.', 'error');
                }
            }
            redirect('settings.php');
        }
        
        if ($action === 'reset_all_passwords') {
            $new_password = 'password123';
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE Users SET Password = ? WHERE Role = 'employee'";
            $params = array($hash);
            
            if (sqlsrv_query($conn, $sql, $params)) {
                set_message('All employee passwords reset to: password123', 'success');
            } else {
                set_message('Failed to reset passwords.', 'error');
            }
            redirect('settings.php');
        }
    }
}

// Fetch all users for password reset
$users_sql = "SELECT UserID, Email, FirstName, LastName, Role FROM Users ORDER BY Role, FirstName";
$users_stmt = sqlsrv_query($conn, $users_sql);

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HR Admin</title>
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
        .section-header { margin-bottom: 20px; }
        .section-header h2 { color: #333; font-size: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-primary { padding: 10px 20px; background: #4facfe; color: white; border: none; border-radius: 5px; font-size: 14px; cursor: pointer; }
        .btn-danger { padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; font-size: 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; padding: 12px; background: #f8f9fa; color: #666; font-weight: 600; font-size: 14px; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #333; font-size: 14px; }
        table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-employee { background: #e7f3ff; color: #004085; }
        .badge-hod { background: #fff3cd; color: #856404; }
        .badge-hr { background: #d1ecf1; color: #0c5460; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; color: white; background: #ffc107; }
        .warning-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning-box h4 { color: #856404; margin-bottom: 10px; }
        .warning-box p { color: #856404; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; width: 90%; max-width: 500px; border-radius: 10px; }
        .btn-close { background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Admin - Settings</h1>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="all_requests.php">All Requests</a>
        <a href="manage_employees.php">Manage Employees</a>
        <a href="manage_leave_types.php">Leave Types</a>
        <a href="reports.php">Reports</a>
        <a href="settings.php" class="active">Settings</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <!-- Change My Password -->
        <div class="section">
            <div class="section-header">
                <h2>Change My Password</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_my_password">
                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" minlength="6" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" minlength="6" required>
                </div>
                <button type="submit" class="btn-primary">Change My Password</button>
            </form>
        </div>
        
        <!-- Reset User Passwords -->
        <div class="section">
            <div class="section-header">
                <h2>Reset User Passwords</h2>
            </div>
            
            <div class="warning-box">
                <h4>⚠️ Quick Reset All Employees</h4>
                <p>This will reset ALL employee passwords to: <strong>password123</strong></p>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="reset_all_passwords">
                    <button type="submit" class="btn-danger" onclick="return confirm('Reset all employee passwords to password123?')">
                        Reset All Employee Passwords
                    </button>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = sqlsrv_fetch_array($users_stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></td>
                            <td><?php echo htmlspecialchars($user['Email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['Role']; ?>">
                                    <?php echo ucfirst($user['Role']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-action" onclick='openResetModal(<?php echo json_encode($user); ?>)'>
                                    Reset Password
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- System Information -->
        <div class="section">
            <div class="section-header">
                <h2>System Information</h2>
            </div>
            <table style="max-width: 600px;">
                <tr>
                    <td><strong>System Version</strong></td>
                    <td>1.0.0</td>
                </tr>
                <tr>
                    <td><strong>Database</strong></td>
                    <td>SQL Server (LeaveManagementDB)</td>
                </tr>
                <tr>
                    <td><strong>PHP Version</strong></td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <td><strong>Current Year</strong></td>
                    <td><?php echo date('Y'); ?></td>
                </tr>
                <tr>
                    <td><strong>Server Time</strong></td>
                    <td><?php echo date('F d, Y g:i A'); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Reset User Password</h2>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="reset_user_name" disabled style="background: #f0f0f0;">
                </div>
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" minlength="6" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" minlength="6" required>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary">Reset Password</button>
                    <button type="button" class="btn-close" onclick="closeResetModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openResetModal(user) {
            document.getElementById('reset_user_id').value = user.UserID;
            document.getElementById('reset_user_name').value = user.FirstName + ' ' + user.LastName + ' (' + user.Email + ')';
            document.getElementById('resetModal').style.display = 'block';
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php
if ($users_stmt) sqlsrv_free_stmt($users_stmt);
?>
