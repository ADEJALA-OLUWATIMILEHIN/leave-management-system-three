<?php
/**
 * Logout Script
 * Leave Management System
 */

session_start();

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/config/database.php';
    
    $log_sql = "INSERT INTO ActivityLog (UserID, Action, Description, IPAddress) 
                VALUES (?, 'Logout', 'User logged out', ?)";
    $log_params = array($_SESSION['user_id'], $_SERVER['REMOTE_ADDR']);
    sqlsrv_query($conn, $log_sql, $log_params);
}

// Destroy all session data
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
