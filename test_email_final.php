<?php
require_once 'config/email_notifications.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Test - Leave Management</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .success { 
            color: #155724;
            background: #d4edda; 
            padding: 15px; 
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin: 20px 0;
        }
        .error { 
            color: #721c24;
            background: #f8d7da; 
            padding: 15px; 
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin: 20px 0;
        }
        .info { 
            color: #004085;
            background: #d1ecf1; 
            padding: 15px; 
            border-radius: 5px;
            border: 1px solid #bee5eb;
            margin: 20px 0;
        }
        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .config-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .config-table td:first-child {
            font-weight: bold;
            background: #f8f9fa;
            width: 200px;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Configuration Test</h1>
        
        <div class="info">
            <strong>SMTP Configuration:</strong>
            <table class="config-table">
                <tr>
                    <td>SMTP Host</td>
                    <td><?php echo SMTP_HOST; ?></td>
                </tr>
                <tr>
                    <td>SMTP Port</td>
                    <td><?php echo SMTP_PORT; ?></td>
                </tr>
                <tr>
                    <td>SMTP Username</td>
                    <td><?php echo SMTP_USERNAME; ?></td>
                </tr>
                <tr>
                    <td>From Email</td>
                    <td><?php echo FROM_EMAIL; ?></td>
                </tr>
                <tr>
                    <td>From Name</td>
                    <td><?php echo FROM_NAME; ?></td>
                </tr>
            </table>
        </div>
        
        <h2>🚀 Sending Test Email...</h2>
        
        <?php
        $test_email = 'emmayo382@gmail.com';
        
        echo "<div class='info'>";
        echo "<p><strong>Recipient:</strong> $test_email</p>";
        echo "<p><strong>Note:</strong> This may take 30-90 seconds due to slow connection...</p>";
        echo "</div>";
        
        echo "<div class='loader'></div>";
        
        flush();
        ob_flush();
        
        $start_time = microtime(true);
        
        $test_body = '
            <h2>🎉 Email Test Successful!</h2>
            <p>If you receive this email, your Leave Management System email configuration is working correctly.</p>
            <div style="background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0;">
                <p><strong>Test Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
                <p><strong>Server:</strong> ' . $_SERVER['SERVER_NAME'] . '</p>
                <p><strong>System:</strong> Leave Management v1.0</p>
            </div>
            <p>You can now test the full workflow by submitting a leave request!</p>
        ';
        
        $result = send_email(
            $test_email,
            'Test Email - Leave Management System',
            'Email Configuration Test',
            $test_body
        );
        
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        if ($result) {
            echo "<div class='success'>";
            echo "<h3>✅ Email Sent Successfully!</h3>";
            echo "<p><strong>Time taken:</strong> {$duration} seconds</p>";
            echo "<p><strong>Check your inbox:</strong> $test_email</p>";
            echo "<p><strong>Status:</strong> Email system is working perfectly! 🎉</p>";
            echo "</div>";
            
            echo "<div class='info'>";
            echo "<h3>✅ Next Steps:</h3>";
            echo "<ol>";
            echo "<li>Check your Gmail inbox for the test email</li>";
            echo "<li>Test the complete workflow:
                <ul>
                    <li>Login as Employee: <a href='login.php'>http://localhost:8080/leave-management/login.php</a></li>
                    <li>Submit a leave request</li>
                    <li>Check HOD receives email notification</li>
                    <li>Approve as HOD</li>
                    <li>Check HR receives email notification</li>
                    <li>Approve as HR</li>
                    <li>Check Employee receives approval email</li>
                </ul>
            </li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<h3>❌ Email Failed to Send</h3>";
            echo "<p><strong>Time taken:</strong> {$duration} seconds</p>";
            echo "<p><strong>Possible Issues:</strong></p>";
            echo "<ul>";
            echo "<li>App Password is incorrect (must be 16 characters, no spaces)</li>";
            echo "<li>Internet connection is unstable</li>";
            echo "<li>Firewall/Antivirus blocking port 587</li>";
            echo "<li>Gmail account doesn't have 2-Step Verification enabled</li>";
            echo "</ul>";
            echo "<p><strong>Action Required:</strong></p>";
            echo "<ol>";
            echo "<li>Verify your Gmail App Password in <code>config/email_notifications.php</code></li>";
            echo "<li>Check Apache error log: <code>C:\\xampp\\apache\\logs\\error.log</code></li>";
            echo "<li>Try disabling firewall temporarily</li>";
            echo "</ol>";
            echo "</div>";
        }
        ?>
        
        <hr style="margin: 30px 0;">
        
        <div style="text-align: center;">
            <p><a href="login.php" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Login Page</a></p>
            <p style="color: #666; font-size: 14px; margin-top: 20px;">
                Sterling Assurance Nigeria Limited<br>
                Leave Management System v1.0
            </p>
        </div>
    </div>
</body>
</html>