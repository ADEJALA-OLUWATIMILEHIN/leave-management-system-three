<?php
/**
 * Leave Resumption Email Reminders
 * Sends daily reminders for 3 days before employee resumes work
 * Run this script daily via Windows Task Scheduler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_notifications.php';

// Create log file
$log_file = __DIR__ . '/resumption_reminders.log';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("========== Script Started ==========");

// Get today's date
$today = date('Y-m-d');
log_message("Today's date: $today");

// Calculate upcoming dates (1, 2, and 3 days from now)
$day_1 = date('Y-m-d', strtotime('+1 day'));
$day_2 = date('Y-m-d', strtotime('+2 days'));
$day_3 = date('Y-m-d', strtotime('+3 days'));

log_message("Checking for leave ending on: $day_1, $day_2, or $day_3");

// Find approved leave requests ending in the next 1-3 days
$sql = "SELECT 
            lr.RequestID,
            lr.EndDate,
            lr.TotalDays,
            u.UserID,
            u.FirstName,
            u.LastName,
            u.Email as EmployeeEmail,
            u.Department,
            lt.TypeName as LeaveType,
            hod.FirstName + ' ' + hod.LastName as HODName,
            hod.Email as HODEmail
        FROM LeaveRequests lr
        JOIN Users u ON lr.UserID = u.UserID
        JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
        LEFT JOIN Users hod ON u.ManagerID = hod.UserID
        WHERE lr.Status = 'approved'
        AND CAST(lr.EndDate AS DATE) IN (?, ?, ?)
        AND u.IsActive = 1
        ORDER BY lr.EndDate";

$params = array($day_1, $day_2, $day_3);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    log_message("ERROR: Database query failed - " . print_r(sqlsrv_errors(), true));
    exit;
}

$total_sent = 0;
$total_errors = 0;

// Get all HR users
$hr_sql = "SELECT FirstName, LastName, Email FROM Users WHERE Role = 'hr' AND IsActive = 1";
$hr_stmt = sqlsrv_query($conn, $hr_sql);
$hr_users = array();
while ($hr = sqlsrv_fetch_array($hr_stmt, SQLSRV_FETCH_ASSOC)) {
    $hr_users[] = $hr;
}
log_message("Found " . count($hr_users) . " HR user(s)");

// Process each leave request
while ($leave = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
    $employee_name = $leave['FirstName'] . ' ' . $leave['LastName'];
    $end_date_str = $leave['EndDate']->format('Y-m-d');
    $resumption_date = date('l, F d, Y', strtotime($end_date_str . ' +1 day'));
    
    // Calculate days until resumption (EndDate + 1 day = Resumption day)
    $end_timestamp = strtotime($end_date_str);
    $today_timestamp = strtotime($today);
    $days_until_end = ($end_timestamp - $today_timestamp) / 86400;
    $days_until_resumption = $days_until_end + 1;
    
    // Skip if not 1, 2, or 3 days
    if ($days_until_resumption < 1 || $days_until_resumption > 3) {
        continue;
    }
    
    log_message("Processing: $employee_name - Resumes in $days_until_resumption days");
    
    // Set urgency based on days remaining
    if ($days_until_resumption == 3) {
        $urgency = "3 days";
        $color = "#17a2b8"; // Blue
        $icon = "📅";
    } elseif ($days_until_resumption == 2) {
        $urgency = "2 days";
        $color = "#ffc107"; // Yellow
        $icon = "⏰";
    } else {
        $urgency = "TOMORROW";
        $color = "#dc3545"; // Red
        $icon = "🔔";
    }
    
    // ===========================================
    // 1. EMAIL TO EMPLOYEE
    // ===========================================
    $employee_subject = ($days_until_resumption == 1) 
        ? "🔔 URGENT: You Resume Work TOMORROW!"
        : "📅 Reminder: You Resume Work in $urgency";
    
    $employee_body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>$icon Leave Resumption Reminder</h1>
        </div>
        <div style='padding: 30px; background: #f9f9f9;'>
            <h2 style='color: #333;'>Hello $employee_name,</h2>
            <p style='font-size: 16px; color: #666;'>
                Your <strong>{$leave['LeaveType']}</strong> is ending soon.
            </p>
            
            <div style='background: $color; padding: 30px; border-radius: 10px; margin: 25px 0; text-align: center;'>
                <h2 style='color: white; margin: 0; font-size: 40px;'>$icon $urgency</h2>
                <p style='color: white; margin: 10px 0 0 0; font-size: 18px;'>Until You Resume Work</p>
            </div>
            
            <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid $color;'>
                <h3 style='color: $color; margin-top: 0;'>📋 Details</h3>
                <p><strong>Last Day of Leave:</strong> " . $leave['EndDate']->format('l, F d, Y') . "</p>
                <p><strong>Resumption Date:</strong> $resumption_date</p>
                <p><strong>Department:</strong> {$leave['Department']}</p>
            </div>
            
            <p style='margin-top: 20px; color: #666;'>
                We look forward to welcoming you back!
            </p>
            
            <p style='font-size: 12px; color: #999; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;'>
                Automated reminder from Leave Management System<br>
                Sterling Assurance Nigeria Limited
            </p>
        </div>
    </div>
    ";
    
    if (send_email($leave['EmployeeEmail'], $employee_subject, $employee_body)) {
        log_message("✓ Email sent to Employee: {$leave['EmployeeEmail']}");
        $total_sent++;
    } else {
        log_message("✗ Failed to send to Employee: {$leave['EmployeeEmail']}");
        $total_errors++;
    }
    
    sleep(1); // Pause between emails
    
    // ===========================================
    // 2. EMAIL TO HOD
    // ===========================================
    if (!empty($leave['HODEmail'])) {
        $hod_subject = ($days_until_resumption == 1)
            ? "🔔 URGENT: $employee_name Resumes TOMORROW"
            : "📅 Reminder: $employee_name Resumes in $urgency";
        
        $hod_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0;'>$icon Team Member Resumption</h1>
            </div>
            <div style='padding: 30px; background: #f9f9f9;'>
                <h2 style='color: #333;'>Hello {$leave['HODName']},</h2>
                <p style='font-size: 16px; color: #666;'>
                    Your team member will resume work soon.
                </p>
                
                <div style='background: $color; padding: 30px; border-radius: 10px; margin: 25px 0; text-align: center;'>
                    <h2 style='color: white; margin: 0; font-size: 40px;'>$icon $urgency</h2>
                    <p style='color: white; margin: 10px 0 0 0; font-size: 18px;'>Until Team Member Resumes</p>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #f5576c;'>
                    <h3 style='color: #f5576c; margin-top: 0;'>👤 Employee Details</h3>
                    <p><strong>Name:</strong> $employee_name</p>
                    <p><strong>Department:</strong> {$leave['Department']}</p>
                    <p><strong>Leave Type:</strong> {$leave['LeaveType']}</p>
                    <p><strong>Resumption Date:</strong> $resumption_date</p>
                </div>
                
                <p style='font-size: 12px; color: #999; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;'>
                    Automated reminder from Leave Management System<br>
                    Sterling Assurance Nigeria Limited
                </p>
            </div>
        </div>
        ";
        
        if (send_email($leave['HODEmail'], $hod_subject, $hod_body)) {
            log_message("✓ Email sent to HOD: {$leave['HODEmail']}");
            $total_sent++;
        } else {
            log_message("✗ Failed to send to HOD: {$leave['HODEmail']}");
            $total_errors++;
        }
        
        sleep(1);
    }
    
    // ===========================================
    // 3. EMAIL TO ALL HR USERS
    // ===========================================
    foreach ($hr_users as $hr) {
        $hr_name = $hr['FirstName'] . ' ' . $hr['LastName'];
        $hr_subject = ($days_until_resumption == 1)
            ? "🔔 URGENT: $employee_name ({$leave['Department']}) Resumes TOMORROW"
            : "📅 Reminder: $employee_name ({$leave['Department']}) Resumes in $urgency";
        
        $hr_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0;'>$icon Employee Resumption Alert</h1>
            </div>
            <div style='padding: 30px; background: #f9f9f9;'>
                <h2 style='color: #333;'>Hello $hr_name,</h2>
                <p style='font-size: 16px; color: #666;'>
                    An employee from <strong>{$leave['Department']}</strong> will resume work soon.
                </p>
                
                <div style='background: $color; padding: 30px; border-radius: 10px; margin: 25px 0; text-align: center;'>
                    <h2 style='color: white; margin: 0; font-size: 40px;'>$icon $urgency</h2>
                    <p style='color: white; margin: 10px 0 0 0; font-size: 18px;'>Until Employee Resumes</p>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #00f2fe;'>
                    <h3 style='color: #00f2fe; margin-top: 0;'>👤 Employee Information</h3>
                    <p><strong>Name:</strong> $employee_name</p>
                    <p><strong>Department:</strong> {$leave['Department']}</p>
                    <p><strong>Leave Type:</strong> {$leave['LeaveType']}</p>
                    <p><strong>Resumption Date:</strong> $resumption_date</p>
                </div>
                
                <p style='font-size: 12px; color: #999; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;'>
                    Automated reminder from Leave Management System<br>
                    Sterling Assurance Nigeria Limited
                </p>
            </div>
        </div>
        ";
        
        if (send_email($hr['Email'], $hr_subject, $hr_body)) {
            log_message("✓ Email sent to HR: {$hr['Email']}");
            $total_sent++;
        } else {
            log_message("✗ Failed to send to HR: {$hr['Email']}");
            $total_errors++;
        }
        
        sleep(1);
    }
}

// Summary
log_message("========== Script Completed ==========");
log_message("Total emails sent: $total_sent");
log_message("Total errors: $total_errors");
log_message("======================================\n");

sqlsrv_free_stmt($stmt);
sqlsrv_free_stmt($hr_stmt);
sqlsrv_close($conn);

echo "Script completed successfully!\n";
echo "Emails sent: $total_sent\n";
echo "Errors: $total_errors\n";
echo "Check log: $log_file\n";
?>