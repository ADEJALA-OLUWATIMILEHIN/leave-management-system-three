<?php
require_once 'config/email_notifications.php';

echo "<h2>Testing Email Configuration</h2>";
echo "<p>SMTP Host: " . SMTP_HOST . "</p>";
echo "<p>SMTP Port: " . SMTP_PORT . "</p>";
echo "<p>SMTP Username: " . SMTP_USERNAME . "</p>";
echo "<p>From Email: " . FROM_EMAIL . "</p>";
echo "<hr>";

// Increase PHP timeout for slow connection
set_time_limit(120); // 2 minutes
ini_set('default_socket_timeout', 120);

// Test email send
$to = 'emmayo382@gmail.com';
$subject = 'Test Email from Leave Management';
$message = '<h1>Test Email</h1><p>If you receive this, email is working!</p>';

echo "<p>Attempting to send test email to: $to</p>";
echo "<p><em>Please wait... (this may take up to 60 seconds due to slow connection)</em></p>";

$result = send_test_email($to, $subject, 'Test Email', $message);

if ($result) {
    echo "<p style='color: green; font-size: 18px;'><strong>✓ EMAIL SENT SUCCESSFULLY!</strong></p>";
    echo "<p>Check your inbox: $to</p>";
} else {
    echo "<p style='color: red; font-size: 18px;'><strong>✗ EMAIL FAILED TO SEND!</strong></p>";
    echo "<p>Check the error above.</p>";
}

// Function to test email with increased timeout
function send_test_email($to, $subject, $heading, $body) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings with LONG TIMEOUT
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // CRITICAL: Increase timeout for Nigeria network
        $mail->Timeout    = 120;  // 2 minutes timeout
        $mail->SMTPDebug  = 2;    // Verbose debug output
        
        // Disable SSL verification for slow connections
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "<pre style='color: red; background: #ffe6e6; padding: 15px; border: 1px solid red;'>";
        echo "Detailed Error Information:\n";
        echo "Error Message: {$mail->ErrorInfo}\n";
        echo "Exception: {$e->getMessage()}\n";
        echo "</pre>";
        return false;
    }
}
?>