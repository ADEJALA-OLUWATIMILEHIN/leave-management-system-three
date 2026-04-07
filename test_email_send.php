<?php
require_once 'config/email_notifications.php';

echo "<h2>Email Sending Test</h2>";

// Replace with your actual email
$test_email = 'emmayo382@gmail.com';  // ← CHANGE THIS TO YOUR EMAIL

$result = send_email(
    $test_email,
    'Test User',
    'Test Email - Leave Management System',
    '<div style="font-family: Arial; padding: 20px; background: #f0f0f0;">
        <h1 style="color: #4facfe;">✓ Success!</h1>
        <p>Your email system is working correctly.</p>
        <p><strong>Leave Management System</strong> is ready to send automated notifications.</p>
    </div>'
);

if ($result) {
    echo "<p style='color: green; font-size: 18px;'>✓ Email sent successfully!</p>";
    echo "<p>Check your inbox at: <strong>$test_email</strong></p>";
} else {
    echo "<p style='color: red; font-size: 18px;'>✗ Email failed to send.</p>";
    echo "<p>Please check your Gmail settings and App Password.</p>";
}
?>