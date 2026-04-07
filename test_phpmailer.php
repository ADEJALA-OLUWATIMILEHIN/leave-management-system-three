<?php
// Test if PHPMailer is installed

// For Composer installation:
// require_once 'vendor/autoload.php';

// For Manual installation:
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once 'vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

echo "<h2>PHPMailer Installation Test</h2>";

try {
    $mail = new PHPMailer(true);
    echo "✓ PHPMailer is installed correctly!<br>";
    echo "Version: Using PHPMailer library<br>";
} catch (Exception $e) {
    echo "✗ PHPMailer installation failed: " . $e->getMessage();
}
?>