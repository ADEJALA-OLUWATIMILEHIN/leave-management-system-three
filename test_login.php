<?php
require_once __DIR__ . '/config/database.php';

// Test credentials
$test_email = 'emmanuel.okafor@company.com';
$test_password = 'password123';

// Get user from database
$sql = "SELECT UserID, Email, Password, FirstName, LastName, Role FROM Users WHERE Email = ?";
$params = array($test_email);
$stmt = sqlsrv_query($conn, $sql, $params);
$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

echo "<h2>Login Test Results</h2>";
echo "<pre>";

if ($user) {
    echo "✓ User found in database\n";
    echo "Email: " . $user['Email'] . "\n";
    echo "Name: " . $user['FirstName'] . " " . $user['LastName'] . "\n";
    echo "Role: " . $user['Role'] . "\n\n";
    
    echo "Password Hash in DB: " . $user['Password'] . "\n\n";
    
    // Test password verification
    if (password_verify($test_password, $user['Password'])) {
        echo "✓ PASSWORD VERIFICATION: SUCCESS\n";
        echo "The password 'password123' works!\n";
    } else {
        echo "✗ PASSWORD VERIFICATION: FAILED\n";
        echo "The password 'password123' does NOT match the hash\n\n";
        
        // Generate a new hash
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "Try this SQL command:\n";
        echo "UPDATE Users SET Password = '$new_hash' WHERE Email = '$test_email';\n";
    }
} else {
    echo "✗ User NOT found in database\n";
}

echo "</pre>";
?>