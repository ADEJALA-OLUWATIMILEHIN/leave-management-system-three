<?php
/**
 * Login Page
 * Leave Management System
 */

require_once __DIR__ . '/config/database.php';

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/index.php');
    } else {
        redirect('employee/index.php');
    }
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password']; // Don't sanitize password
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Query to check user credentials
        $sql = "SELECT UserID, Email, Password, FirstName, LastName, Role, IsActive, MustChangePassword 
                FROM Users 
                WHERE Email = ? AND IsActive = 1";
        
        $params = array($email);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $error = 'Database error. Please try again.';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($user) {
                // Verify password (using PHP's password_verify for hashed passwords)
                if (password_verify($password, $user['Password'])) {
                    // Password is correct, create session
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['name'] = $user['FirstName'] . ' ' . $user['LastName'];
                    $_SESSION['role'] = $user['Role'];
                    
                    // CHECK IF USER MUST CHANGE PASSWORD
                    if ($user['MustChangePassword'] == 1) {
                        $_SESSION['must_change_password'] = true;
                        
                        // Log the login activity
                        $log_sql = "INSERT INTO ActivityLog (UserID, Action, Description, IPAddress) 
                                    VALUES (?, 'Login', 'User logged in - Password change required', ?)";
                        $log_params = array($user['UserID'], $_SERVER['REMOTE_ADDR']);
                        sqlsrv_query($conn, $log_sql, $log_params);
                        
                        sqlsrv_free_stmt($stmt);
                        redirect('change_password.php');
                    }
                    
                    // Log the login activity
                    $log_sql = "INSERT INTO ActivityLog (UserID, Action, Description, IPAddress) 
                                VALUES (?, 'Login', 'User logged in', ?)";
                    $log_params = array($user['UserID'], $_SERVER['REMOTE_ADDR']);
                    sqlsrv_query($conn, $log_sql, $log_params);
                    
                    // Redirect based on role
                    if ($user['Role'] === 'admin') {
                        redirect('admin/index.php');
                    } else {
                        redirect('employee/index.php');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
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
    <title>Login - Leave Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #4facfe);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -2;
        }
        
        /* Floating circles animation */
        .circle {
            position: fixed;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
            z-index: -1;
        }
        
        .circle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .circle:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 70%;
            left: 80%;
            animation-delay: 2s;
        }
        
        .circle:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 40%;
            left: 70%;
            animation-delay: 4s;
        }
        
        .circle:nth-child(4) {
            width: 120px;
            height: 120px;
            top: 80%;
            left: 20%;
            animation-delay: 6s;
        }
        
        .circle:nth-child(5) {
            width: 90px;
            height: 90px;
            top: 20%;
            left: 60%;
            animation-delay: 8s;
        }
        
        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            33% {
                transform: translateY(-30px) translateX(30px);
            }
            66% {
                transform: translateY(30px) translateX(-30px);
            }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-header .logo {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .error-message {
            background: #fee;
            border: 2px solid #fcc;
            color: #c33;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .company-footer {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .company-footer p {
            color: #999;
            font-size: 13px;
            margin: 5px 0;
        }
        
        .company-footer strong {
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Floating circles -->
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
    
    <div class="login-container">
        <div class="login-header">
            <div class="logo">🏢</div>
            <h1>Sterling Assurance Nigeria Limited Leave Management</h1>
            <p>Sign in to your account</p>
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
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="your.email@company.com"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter your password"
                    required
                >
            </div>
            
            <button type="submit" class="btn-login">Sign In</button>
        </form>
        
        <div class="company-footer">
            <p><strong>Sterling Assurance Nigeria Limited</strong></p>
            <p>Leave Management System</p>
        </div>
    </div>
</body>
</html>