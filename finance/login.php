<?php
/**
 * Finance Login Page
 */

require_once __DIR__ . '/../config/database.php';

// If already logged in as finance, redirect to dashboard
if (is_logged_in() && $_SESSION['role'] === 'finance') {
    redirect('index.php');
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $sql = "SELECT UserID, Email, Password, FirstName, LastName, Role, IsActive 
                FROM Users 
                WHERE Email = ? AND Role = 'finance' AND IsActive = 1";
        
        $stmt = sqlsrv_query($conn, $sql, array($email));
        
        if ($stmt === false) {
            $error = 'Database error. Please try again.';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['Password'])) {
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['name'] = $user['FirstName'] . ' ' . $user['LastName'];
                $_SESSION['role'] = 'finance';
                
                redirect('index.php');
            } else {
                $error = 'Invalid finance credentials.';
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
    <title>Finance Login - Leave Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            padding: 0;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .portal-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.3);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .login-header h2 {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 15px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .error-alert {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.1);
        }
        
        .btn-signin {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        
        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
        }
        
        .other-portals {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .other-portals p {
            color: #999;
            font-size: 13px;
            margin-bottom: 12px;
        }
        
        .other-portals a {
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        
        .other-portals a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="portal-badge">FINANCE PORTAL</div>
            <h2>Leave Management</h2>
            <p>Finance Department Access</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Finance Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="finance@company.com"
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
                        placeholder="Enter password"
                        required
                    >
                </div>
                
                <button type="submit" class="btn-signin">
                    FINANCE SIGN IN
                </button>
            </form>
            
            <div class="other-portals">
                <p>Other Portals:</p>
                <a href="../index.php">← Back to Main Portal</a>
            </div>
        </div>
    </div>
</body>
</html>