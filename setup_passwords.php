<?php
require_once __DIR__ . '/config/database.php';

$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Update ALL users
$sql = "UPDATE Users SET Password = ?";
$params = array($hash);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo "<h2>✓ All passwords updated successfully!</h2>";
    echo "<p>All users can now login with password: <strong>password123</strong></p>";
    
    // Show all users
    $users_sql = "SELECT Email, FirstName, LastName, Role FROM Users";
    $users_stmt = sqlsrv_query($conn, $users_sql);
    
    echo "<h3>User Accounts:</h3>";
    echo "<ul>";
    while ($user = sqlsrv_fetch_array($users_stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<li>" . $user['Email'] . " (" . $user['Role'] . ") - Password: password123</li>";
    }
    echo "</ul>";
    
    echo "<p><a href='login.php'>Go to Employee Login</a> | <a href='admin/login.php'>Go to Admin Login</a></p>";
} else {
    echo "Error: " . print_r(sqlsrv_errors(), true);
}
?>