<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Complete Login Debug</h2>";
echo "<pre>";

// Test credentials
$test_email = 'emmanuel.okafor@company.com';
$test_password = 'password123';

echo "Testing login for: $test_email\n";
echo "Using password: $test_password\n\n";
echo str_repeat("-", 50) . "\n\n";

// Step 1: Check if user exists
$sql = "SELECT UserID, Email, Password, FirstName, LastName, Role, IsActive FROM Users WHERE Email = ?";
$params = array($test_email);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo "❌ DATABASE ERROR:\n";
    print_r(sqlsrv_errors());
    exit;
}

$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$user) {
    echo "❌ USER NOT FOUND in database\n";
    exit;
}

echo "✓ User found:\n";
echo "  UserID: " . $user['UserID'] . "\n";
echo "  Email: " . $user['Email'] . "\n";
echo "  Name: " . $user['FirstName'] . " " . $user['LastName'] . "\n";
echo "  Role: " . $user['Role'] . "\n";
echo "  IsActive: " . $user['IsActive'] . "\n\n";

// Step 2: Check password hash
echo "Password Hash Analysis:\n";
echo "  Hash in DB: " . $user['Password'] . "\n";
echo "  Hash length: " . strlen($user['Password']) . " characters\n";
echo "  Hash starts with: " . substr($user['Password'], 0, 10) . "\n\n";

// Step 3: Test password_verify
echo "Password Verification Test:\n";
$verify_result = password_verify($test_password, $user['Password']);

if ($verify_result) {
    echo "  ✓✓✓ SUCCESS! Password matches!\n\n";
} else {
    echo "  ❌ FAILED! Password does not match\n\n";
    
    // Generate new hash
    $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "  Suggested fix - Run this in SSMS:\n\n";
    echo "  UPDATE Users \n";
    echo "  SET Password = '$new_hash' \n";
    echo "  WHERE Email = '$test_email';\n\n";
}

// Step 4: Show what the login page sees
echo str_repeat("-", 50) . "\n";
echo "What happens in login.php:\n\n";

$login_sql = "SELECT UserID, Email, Password, FirstName, LastName, Role, IsActive 
              FROM Users 
              WHERE Email = ? AND IsActive = 1";
$login_params = array($test_email);
$login_stmt = sqlsrv_query($conn, $login_sql, $login_params);

if ($login_stmt && sqlsrv_has_rows($login_stmt)) {
    $login_user = sqlsrv_fetch_array($login_stmt, SQLSRV_FETCH_ASSOC);
    echo "1. Query returns user: YES ✓\n";
    
    if (password_verify($test_password, $login_user['Password'])) {
        echo "2. Password verify: SUCCESS ✓\n";
        echo "3. Should create session and redirect\n\n";
        echo ">>> LOGIN SHOULD WORK! <<<\n";
    } else {
        echo "2. Password verify: FAILED ❌\n";
        echo ">>> THIS IS WHY LOGIN FAILS <<<\n";
    }
} else {
    echo "1. Query returns user: NO ❌\n";
}

echo "</pre>";
?>