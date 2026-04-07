<?php
/**
 * Database Configuration File
 * Leave Management System
 * MSSQL / SQL Server Connection
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
define('DB_SERVER', 'IT3'); // Your SQL Server instance name
define('DB_NAME', 'LeaveManagementDB');
define('DB_USERNAME', ''); // Windows Authentication - leave empty
define('DB_PASSWORD', ''); // Windows Authentication - leave empty

// Connection info array for sqlsrv
$connectionInfo = array(
    "Database" => DB_NAME,
    "CharacterSet" => "UTF-8"
);

// If using SQL Server Authentication instead of Windows Authentication, use this:
// $connectionInfo = array(
//     "Database" => DB_NAME,
//     "UID" => DB_USERNAME,
//     "PWD" => DB_PASSWORD,
//     "CharacterSet" => "UTF-8"
// );

// Establish database connection
$conn = sqlsrv_connect(DB_SERVER, $connectionInfo);

// Check connection
if ($conn === false) {
    die("<div style='color: red; padding: 20px; border: 1px solid red; margin: 20px;'>
            <h3>Database Connection Error</h3>
            <p>Could not connect to the database. Please check your configuration.</p>
            <details>
                <summary>Error Details (Click to expand)</summary>
                <pre>" . print_r(sqlsrv_errors(), true) . "</pre>
            </details>
         </div>");
}

// Helper function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Helper function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Helper function to check if user is HOD
function is_hod() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hod';
}

// Helper function to check if user is HR
function is_hr() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Helper function to display alert message
function set_message($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type; // success, error, warning, info
}

// Helper function to get and clear message
function get_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        return array('message' => $message, 'type' => $type);
    }
    return null;
}


// Timezone setting
date_default_timezone_set('Africa/Lagos');

// Safe date format helper
function safe_date_format($date, $format = 'M d, Y') {
    if ($date && is_object($date) && method_exists($date, 'format')) {
        return $date->format($format);
    }
    return 'N/A';
}

?>
