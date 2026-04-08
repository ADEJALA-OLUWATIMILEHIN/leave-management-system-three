<?php
/**
 * Employee Login Page
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// If already logged in as employee, redirect to dashboard
if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
    redirect('index.php');
}

// Also redirect HOD/HR/Finance to their own portals
if (is_logged_in()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'hr')      redirect('../hr/index.php');
    if ($role === 'hod')     redirect('../hod/index.php');
    if ($role === 'finance') redirect('../finance/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $sql  = "SELECT UserID, Email, Password, FirstName, LastName, Role, IsActive, MustChangePassword
                 FROM Users
                 WHERE Email = ? AND IsActive = 1";
        $stmt = sqlsrv_query($conn, $sql, array($email));

        if ($stmt === false) {
            $error = 'Database error. Please try again.';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

            if ($user && password_verify($password, $user['Password'])) {

                // Set session
                $_SESSION['user_id']    = $user['UserID'];
                $_SESSION['email']      = $user['Email'];
                $_SESSION['name']       = $user['FirstName'] . ' ' . $user['LastName'];
                $_SESSION['role']       = $user['Role'];

                // Force password change if required
                if ($user['MustChangePassword']) {
                    $_SESSION['must_change_password'] = true;
                    redirect('../change_password.php');
                }

                // Route to correct portal based on role
                switch ($user['Role']) {
                    case 'hr':
                        redirect('../hr/index.php');
                        break;
                    case 'hod':
                        redirect('../hod/index.php');
                        break;
                    case 'finance':
                        redirect('../finance/index.php');
                        break;
                    case 'employee':
                    default:
                        redirect('index.php');
                        break;
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
    <title>Employee Login - Leave Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .portal-badge {
            display: inline-block;
            background: rgba(255,255,255,0.25);
            padding: 7px 18px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .login-header h2 { font-size: 26px; margin-bottom: 6px; }
        .login-header p  { opacity: 0.85; font-size: 14px; }
        .login-body { padding: 36px 30px; }
        .error-alert {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 22px;
            font-size: 14px;
        }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; color: #333; font-weight: 600; margin-bottom: 7px; font-size: 14px; }
        .form-group input {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 9px;
            font-size: 15px;
            transition: .25s;
        }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        .btn-signin {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 9px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .5px;
            transition: .25s;
        }
        .btn-signin:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,.4); }
        .other-portals {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #999;
        }
        .other-portals a { color: #667eea; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="portal-badge">&#128100; Employee Portal</div>
        <h2>Leave Management</h2>
        <p>Sterling Assurance Nigeria Limited</p>
    </div>
    <div class="login-body">
        <?php if ($error): ?>
        <div class="error-alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="your.email@company.com"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn-signin">SIGN IN</button>
        </form>

        <div class="other-portals">
            <a href="../index.php">&#8592; Back to Main Portal</a>
        </div>
    </div>
</div>
</body>
</html>
