<?php
require_once 'config/email_notifications.php';

echo "<h2>Email Configuration Debug</h2>";
echo "<pre>";

// Show configuration (hide password partially)
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP Username: " . SMTP_USERNAME . "\n";
echo "SMTP Password: " . substr(SMTP_PASSWORD, 0, 4) . "****" . substr(SMTP_PASSWORD, -4) . " (length: " . strlen(SMTP_PASSWORD) . ")\n";
echo "From Email: " . FROM_EMAIL . "\n";
echo "From Name: " . FROM_NAME . "\n";
echo "\n" . str_repeat("-", 50) . "\n\n";

// Test email sending with detailed error output
$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    echo "Configuring SMTP...\n";
    
    // Server settings
    $mail->SMTPDebug = 2;  // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    
    echo "\n" . str_repeat("-", 50) . "\n";
    echo "Sending test email...\n\n";
    
    // Recipients
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress(SMTP_USERNAME, 'Test User');  // Send to yourself
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - Leave Management System';
    $mail->Body    = '<h1>Success!</h1><p>Email system is working.</p>';
    
    $mail->send();
    
    echo "\n\n";
    echo "========================================\n";
    echo "✓✓✓ EMAIL SENT SUCCESSFULLY! ✓✓✓\n";
    echo "========================================\n";
    echo "Check your inbox at: " . SMTP_USERNAME . "\n";
    
} catch (Exception $e) {
    echo "\n\n";
    echo "========================================\n";
    echo "✗✗✗ EMAIL FAILED ✗✗✗\n";
    echo "========================================\n";
    echo "Error: {$mail->ErrorInfo}\n";
    echo "\nCommon issues:\n";
    echo "1. Check Gmail App Password (16 chars, no spaces)\n";
    echo "2. Make sure 2-Step Verification is enabled\n";
    echo "3. Check email address is correct\n";
}

echo "</pre>";
?>