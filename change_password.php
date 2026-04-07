<?php
/**
 * Change Password - First Login
 * Leave Management System
 */

require_once __DIR__ . '/config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

$error = '';
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error = 'Password must contain at least one special character (!@#$%^&*(),.?":{}|<>).';
    } else {
        // Verify current password
        $verify_sql = "SELECT Password FROM Users WHERE UserID = ?";
        $verify_stmt = sqlsrv_query($conn, $verify_sql, array($user_id));
        $user = sqlsrv_fetch_array($verify_stmt, SQLSRV_FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['Password'])) {
            $error = 'Current password is incorrect.';
        } else {
            // Update password and set MustChangePassword to 0
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE Users SET Password = ?, MustChangePassword = 0 WHERE UserID = ?";
            $update_stmt = sqlsrv_query($conn, $update_sql, array($hashed_password, $user_id));
            
            if ($update_stmt) {
                $_SESSION['must_change_password'] = false;
                $success = 'Password changed successfully! Redirecting...';
                
                // Redirect to appropriate dashboard after 2 seconds
                if ($user_role === 'employee') {
                    header("refresh:2;url=employee/index.php");
                } elseif ($user_role === 'hod') {
                    header("refresh:2;url=hod/index.php");
                } elseif ($user_role === 'hr') {
                    header("refresh:2;url=hr/index.php");
                }
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Leave Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 10px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
            width: 100%;
            max-width: 500px;
        }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #333; font-size: 28px; margin-bottom: 10px; }
        .header p { color: #666; font-size: 14px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px; }
        .form-group input { 
            width: 100%; 
            padding: 12px 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 14px;
        }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .password-requirements { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px;
            font-size: 13px;
        }
        .password-requirements h4 { color: #333; margin-bottom: 10px; font-size: 14px; }
        .password-requirements ul { margin-left: 20px; color: #666; }
        .password-requirements li { margin: 5px 0; }
        .requirement-met { color: #28a745; }
        .requirement-unmet { color: #dc3545; }
        .btn { 
            width: 100%; 
            padding: 12px; 
            background: #667eea; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer;
        }
        .btn:hover { background: #5568d3; }
        .user-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .user-info strong { color: #004085; font-size: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Change Your Password</h1>
            <p>For security reasons, you must change your password before continuing</p>
        </div>
        
        <div class="user-info">
            <strong>Welcome, <?php echo htmlspecialchars($user_name); ?>!</strong>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">Current Password (Default Password) *</label>
                <input type="password" id="current_password" name="current_password" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password *</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul id="requirements">
                    <li id="req-length" class="requirement-unmet">✗ At least 8 characters</li>
                    <li id="req-uppercase" class="requirement-unmet">✗ At least one uppercase letter (A-Z)</li>
                    <li id="req-lowercase" class="requirement-unmet">✗ At least one lowercase letter (a-z)</li>
                    <li id="req-number" class="requirement-unmet">✗ At least one number (0-9)</li>
                    <li id="req-special" class="requirement-unmet">✗ At least one special character (!@#$%^&*)</li>
                </ul>
            </div>
            
            <button type="submit" class="btn">Change Password</button>
        </form>
    </div>
    
    <script>
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        // Real-time password validation
        newPassword.addEventListener('input', function() {
            const value = this.value;
            
            // Length check
            updateRequirement('req-length', value.length >= 8);
            
            // Uppercase check
            updateRequirement('req-uppercase', /[A-Z]/.test(value));
            
            // Lowercase check
            updateRequirement('req-lowercase', /[a-z]/.test(value));
            
            // Number check
            updateRequirement('req-number', /[0-9]/.test(value));
            
            // Special character check
            updateRequirement('req-special', /[!@#$%^&*(),.?":{}|<>]/.test(value));
        });
        
        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            if (met) {
                element.classList.remove('requirement-unmet');
                element.classList.add('requirement-met');
                element.innerHTML = element.innerHTML.replace('✗', '✓');
            } else {
                element.classList.remove('requirement-met');
                element.classList.add('requirement-unmet');
                element.innerHTML = element.innerHTML.replace('✓', '✗');
            }
        }
        
        // Match validation
        confirmPassword.addEventListener('input', function() {
            if (this.value !== newPassword.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
<?php
if (isset($verify_stmt)) sqlsrv_free_stmt($verify_stmt);
if (isset($update_stmt)) sqlsrv_free_stmt($update_stmt);
?>