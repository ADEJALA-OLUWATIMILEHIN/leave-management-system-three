<?php
/**
 * HR Login Page
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// If already logged in, check role
if (is_logged_in()) {
    if (is_hr()) {
        redirect('index.php'); // Already HR, go to dashboard
    } else {
        // Logged in as different role, force logout first
        session_destroy();
        session_start();
    }
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Query to check HR credentials - ONLY hr role (ADDED MustChangePassword)
        $sql = "SELECT UserID, Email, Password, FirstName, LastName, Role, Department, IsActive, MustChangePassword 
                FROM Users 
                WHERE Email = ? AND Role = 'hr' AND IsActive = 1";
        
        $params = array($email);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $error = 'Database error. Please try again.';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($user) {
                // Verify password
                if (password_verify($password, $user['Password'])) {
                    // Create HR session
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['name'] = $user['FirstName'] . ' ' . $user['LastName'];
                    $_SESSION['role'] = 'hr';
                    $_SESSION['department'] = $user['Department'];
                    
                    // CHECK IF USER MUST CHANGE PASSWORD
                    if ($user['MustChangePassword'] == 1) {
                        $_SESSION['must_change_password'] = true;
                        
                        // Log the login
                        $log_sql = "INSERT INTO ActivityLog (UserID, Action, Description, IPAddress) 
                                    VALUES (?, 'HR Login', 'HR logged in - Password change required', ?)";
                        $log_params = array($user['UserID'], $_SERVER['REMOTE_ADDR']);
                        sqlsrv_query($conn, $log_sql, $log_params);
                        
                        sqlsrv_free_stmt($stmt);
                        redirect('../change_password.php');
                    }
                    
                    // Log the login
                    $log_sql = "INSERT INTO ActivityLog (UserID, Action, Description, IPAddress) 
                                VALUES (?, 'HR Login', 'HR logged in', ?)";
                    $log_params = array($user['UserID'], $_SERVER['REMOTE_ADDR']);
                    sqlsrv_query($conn, $log_sql, $log_params);
                    
                    // Redirect to HR dashboard
                    redirect('index.php');
                } else {
                    $error = 'Invalid HR credentials.';
                }
            } else {
                $error = 'Invalid HR credentials.';
            }
            
            sqlsrv_free_stmt($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Login - Leave Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .hr-badge {
            display: inline-block;
            background: #00f2fe;
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4facfe;
        }
        
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .other-links {
            margin-top: 25px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .other-links p {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .other-links a {
            color: #4facfe;
            text-decoration: none;
            font-weight: 600;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="hr-badge">HR Portal</div>
            <h1>Leave Management</h1>
            <p>Human Resources Access</p>
        </div>
        <div class="other-links">
    <p>Other Portals:</p>
    <a href="../index.php">← Back to Main Portal</a>
</div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">HR Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="hr@company.com"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter password"
                    required
                >
            </div>
            
            <button type="submit" class="btn-login">HR Sign In</button>
        </form>
        
        <div class="other-links">
            <p>Other Portals:</p>
            <a href="../login.php">Employee</a> | 
            <a href="../hod/login.php">HOD</a>
        </div>
    </div>
</body>
</html>