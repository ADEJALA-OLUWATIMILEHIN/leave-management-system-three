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
define('DB_SERVER', 'IT3');
define('DB_NAME',   'LeaveManagementDB');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');

// Connection info
$connectionInfo = array(
    "Database"    => DB_NAME,
    "CharacterSet"=> "UTF-8"
);

// Establish connection
$conn = sqlsrv_connect(DB_SERVER, $connectionInfo);

if ($conn === false) {
    die("<div style='color:red;padding:20px;border:1px solid red;margin:20px;'>
            <h3>Database Connection Error</h3>
            <p>Could not connect to the database. Please check your configuration.</p>
            <details>
                <summary>Error Details</summary>
                <pre>" . print_r(sqlsrv_errors(), true) . "</pre>
            </details>
         </div>");
}

// ── Helper functions ─────────────────────────────────────────────────────────

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_hod() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hod';
}

function is_hr() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}

// ── ADDED: Finance role check ─────────────────────────────────────────────────
function is_finance() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'finance';
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function set_message($message, $type = 'info') {
    $_SESSION['message']      = $message;
    $_SESSION['message_type'] = $type;
}

function get_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type    = $_SESSION['message_type'] ?? 'info';
        unset($_SESSION['message'], $_SESSION['message_type']);
        return array('message' => $message, 'type' => $type);
    }
    return null;
}

date_default_timezone_set('Africa/Lagos');

function safe_date_format($date, $format = 'M d, Y') {
    if ($date && is_object($date) && method_exists($date, 'format')) {
        return $date->format($format);
    }
    return 'N/A';
}
?>
