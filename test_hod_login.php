<?php
require_once __DIR__ . '/config/database.php';

// Test credentials
$test_email = 'ayomide@company.com';
$test_password = 'password123';

echo "<h2>HOD Login Debug</h2>";
echo "<pre>";

// Check if user exists
$sql = "SELECT UserID, Email, Password, FirstName, LastName, Role, Department, IsActive 
        FROM Users 
        WHERE Email = ?";
$params = array($test_email);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo "❌ DATABASE ERROR:\n";
    print_r(sqlsrv_errors());
    exit;
}

$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$user) {
    echo "❌ USER NOT FOUND: $test_email\n";
    exit;
}

echo "✓ User found:\n";
echo "  Email: " . $user['Email'] . "\n";
echo "  Name: " . $user['FirstName'] . " " . $user['LastName'] . "\n";
echo "  Role: " . $user['Role'] . "\n";
echo "  Department: " . $user['Department'] . "\n";
echo "  IsActive: " . $user['IsActive'] . "\n\n";

echo "Password Hash: " . $user['Password'] . "\n\n";

// Test password verification
if (password_verify($test_password, $user['Password'])) {
    echo "✓✓✓ PASSWORD WORKS!\n\n";
} else {
    echo "❌ PASSWORD FAILED!\n\n";
    
    // Generate new hash
    $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "Run this SQL:\n\n";
    echo "UPDATE Users SET Password = '$new_hash' WHERE Email = '$test_email';\n";
}

// Test the HOD login query
echo "\n" . str_repeat("-", 50) . "\n";
echo "Testing HOD Login Query:\n\n";

$hod_sql = "SELECT UserID, Email, Password, FirstName, LastName, Role, Department, IsActive 
            FROM Users 
            WHERE Email = ? AND Role = 'hod' AND IsActive = 1";
$hod_params = array($test_email);
$hod_stmt = sqlsrv_query($conn, $hod_sql, $hod_params);

if ($hod_stmt && sqlsrv_has_rows($hod_stmt)) {
    $hod_user = sqlsrv_fetch_array($hod_stmt, SQLSRV_FETCH_ASSOC);
    echo "✓ HOD query found user\n";
    echo "  Role check: " . $hod_user['Role'] . " === 'hod' ? " . ($hod_user['Role'] === 'hod' ? 'YES' : 'NO') . "\n";
    
    if (password_verify($test_password, $hod_user['Password'])) {
        echo "✓ Password verified in HOD query\n";
        echo "\n>>> LOGIN SHOULD WORK! <<<\n";
    } else {
        echo "❌ Password verification failed\n";
    }
} else {
    echo "❌ HOD query found NO user\n";
    echo "Possible reasons:\n";
    echo "  - Role is not 'hod'\n";
    echo "  - IsActive is not 1\n";
}

echo "</pre>";
?>