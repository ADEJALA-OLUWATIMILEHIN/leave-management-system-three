<?php
/**
 * ONE-TIME Finance Password Reset Tool
 * 
 * INSTRUCTIONS:
 * 1. Place this file in your leave-management ROOT folder
 * 2. Open: http://localhost/leave-management/reset_finance.php
 * 3. It will hash the password and update the DB automatically
 * 4. DELETE THIS FILE immediately after use
 */

require_once __DIR__ . '/config/database.php';

$new_password = 'Finance@123';
$hashed       = password_hash($new_password, PASSWORD_DEFAULT);

// Update or insert finance user
$check_sql  = "SELECT UserID FROM Users WHERE Email = 'finance@sanl.com'";
$check_stmt = sqlsrv_query($conn, $check_sql);
$exists     = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);

if ($exists) {
    // Update existing user's password
    $sql  = "UPDATE Users SET Password = ?, IsActive = 1 WHERE Email = 'finance@sanl.com'";
    $stmt = sqlsrv_query($conn, $sql, array($hashed));
    $action = 'Password UPDATED for existing finance user.';
} else {
    // Insert brand new finance user
    $sql  = "INSERT INTO Users
                (FirstName, LastName, Email, Password, Department, Role,
                 EmployeeNumber, Salary, IsActive, MustChangePassword, CreatedAt)
             VALUES
                ('Finance', 'Admin', 'finance@sanl.com', ?, 'Finance', 'finance',
                 'FIN001', 0, 1, 0, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql, array($hashed));
    $action = 'NEW finance user created.';
}

$success = ($stmt !== false);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Finance Reset</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center;
               min-height: 100vh; background: #f0faf4; margin: 0; }
        .box { background: white; border-radius: 14px; padding: 40px; max-width: 480px;
               width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,.1); text-align: center; }
        .ok  { color: #155724; background: #d4edda; padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .err { color: #721c24; background: #f8d7da; padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .cred { background: #f8f9fa; border: 1px dashed #aaa; border-radius: 8px;
                padding: 16px; margin: 20px 0; font-family: monospace; font-size: 15px; }
        .warn { color: #856404; background: #fff3cd; border-radius: 8px; padding: 12px;
                font-size: 13px; margin-top: 18px; }
    </style>
</head>
<body>
<div class="box">
    <h2>&#128176; Finance Portal Setup</h2>

    <?php if ($success): ?>
    <div class="ok">&#9989; <?php echo $action; ?></div>
    <div class="cred">
        Email: <strong>finance@sanl.com</strong><br>
        Password: <strong>Finance@123</strong>
    </div>
    <p>You can now <a href="finance/login.php"><strong>login to the Finance Portal</strong></a>.</p>
    <div class="warn">&#9888; <strong>Delete this file immediately after logging in!</strong><br>
        File: <code>leave-management/reset_finance.php</code>
    </div>
    <?php else: ?>
    <div class="err">
        &#10060; Database error:<br>
        <pre style="text-align:left;font-size:12px;"><?php print_r(sqlsrv_errors()); ?></pre>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
