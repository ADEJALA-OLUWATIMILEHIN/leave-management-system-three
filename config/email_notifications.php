<?php
/**
 * Email Notifications Configuration
 * Leave Management System - TIMEOUT FIX FOR SLOW CONNECTIONS
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'emmayo382@gmail.com');
define('SMTP_PASSWORD', 'iaxffsklhiquyukx');
define('FROM_EMAIL', 'emmayo382@gmail.com');
define('FROM_NAME', 'Leave Management System - SANL');

/**
 * Send email using PHPMailer (with increased timeout for slow connections)
 * @param string $to
 * @param string $subject
 * @param string $heading  — shown in the coloured header banner
 * @param string $body     — HTML body content
 */
function send_email($to, $subject, $heading, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->Timeout    = 90;
        $mail->SMTPDebug  = 0;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->CharSet = 'UTF-8';

        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f5f5f5; }
                .email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 30px 20px; }
                .content p { margin: 0 0 15px 0; color: #333; }
                .footer { background: #2c3e50; color: #ecf0f1; padding: 20px; text-align: center; font-size: 13px; }
                .footer p { margin: 5px 0; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white !important; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: 600; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h1>' . htmlspecialchars($heading) . '</h1>
                </div>
                <div class="content">
                    ' . $body . '
                </div>
                <div class="footer">
                    <p><strong>Sterling Assurance Nigeria Limited</strong></p>
                    <p>Leave Management System</p>
                    <p>&copy; ' . date('Y') . ' All rights reserved</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error to $to: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * 1. Notify HOD of new leave request from employee
 */
function notify_hod_new_request($hod_email, $hod_name, $employee_name, $leave_type, $start_date, $end_date, $days, $reason) {
    $subject = "New Leave Request - $employee_name";
    $heading = "New Leave Request";

    $body = "
        <h2 style='color: #e91e63; margin-top: 0;'>Action Required: New Leave Request</h2>
        <p>Dear <strong>$hod_name</strong>,</p>
        <p>A new leave request has been submitted by <strong>$employee_name</strong> and requires your review.</p>
        <div style='background: #fff3e0; padding: 20px; border-left: 4px solid #e91e63; margin: 20px 0; border-radius: 4px;'>
            <p style='margin: 5px 0;'><strong>Employee:</strong> $employee_name</p>
            <p style='margin: 5px 0;'><strong>Leave Type:</strong> $leave_type</p>
            <p style='margin: 5px 0;'><strong>Start Date:</strong> $start_date</p>
            <p style='margin: 5px 0;'><strong>End Date:</strong> $end_date</p>
            <p style='margin: 5px 0;'><strong>Duration:</strong> $days working days</p>
            <p style='margin: 5px 0;'><strong>Reason:</strong> $reason</p>
        </div>
        <p>Please login to the HOD portal to review and approve/reject this request.</p>
        <p style='text-align: center;'>
            <a href='http://localhost/leave-management/hod/login.php' class='button' style='background: #e91e63;'>Review Request Now</a>
        </p>
    ";

    return send_email($hod_email, $subject, $heading, $body);
}

/**
 * 2. Notify HR of HOD-approved leave request
 */
function notify_hr_hod_approved($hr_email, $employee_name, $hod_name, $leave_type, $start_date, $end_date, $days, $reason, $hod_remarks) {
    $subject = "Leave Request Approved by HOD - $employee_name";
    $heading = "HOD Approval Received";

    $body = "
        <h2 style='color: #00acc1; margin-top: 0;'>Leave Request Awaiting HR Approval</h2>
        <p>Dear HR Team,</p>
        <p>A leave request from <strong>$employee_name</strong> has been approved by HOD <strong>$hod_name</strong> and requires your final approval.</p>
        <div style='background: #e0f7fa; padding: 20px; border-left: 4px solid #00acc1; margin: 20px 0; border-radius: 4px;'>
            <p style='margin: 5px 0;'><strong>Employee:</strong> $employee_name</p>
            <p style='margin: 5px 0;'><strong>Leave Type:</strong> $leave_type</p>
            <p style='margin: 5px 0;'><strong>Start Date:</strong> $start_date</p>
            <p style='margin: 5px 0;'><strong>End Date:</strong> $end_date</p>
            <p style='margin: 5px 0;'><strong>Duration:</strong> $days working days</p>
            <p style='margin: 5px 0;'><strong>Employee Reason:</strong> $reason</p>
            <p style='margin: 5px 0;'><strong>HOD Remarks:</strong> " . ($hod_remarks ?: 'None provided') . "</p>
        </div>
        <p>Please login to the HR portal to review and make final decision.</p>
        <p style='text-align: center;'>
            <a href='http://localhost/leave-management/hr/login.php' class='button' style='background: #00acc1;'>Review Request Now</a>
        </p>
    ";

    return send_email($hr_email, $subject, $heading, $body);
}

/**
 * 3. Notify employee that HOD rejected their request
 */
function notify_employee_hod_rejected($employee_email, $employee_name, $hod_name, $leave_type, $start_date, $end_date, $hod_remarks) {
    $subject = "Leave Request Rejected - $leave_type";
    $heading = "Leave Request Not Approved";

    $body = "
        <h2 style='color: #d32f2f; margin-top: 0;'>Leave Request Rejected by HOD</h2>
        <p>Dear <strong>$employee_name</strong>,</p>
        <p>Your leave request has been reviewed by your Head of Department <strong>$hod_name</strong> and was not approved at this time.</p>
        <div style='background: #ffebee; padding: 20px; border-left: 4px solid #d32f2f; margin: 20px 0; border-radius: 4px;'>
            <p style='margin: 5px 0;'><strong>Leave Type:</strong> $leave_type</p>
            <p style='margin: 5px 0;'><strong>Requested Dates:</strong> $start_date to $end_date</p>
            <p style='margin: 5px 0;'><strong>HOD Remarks:</strong> " . ($hod_remarks ?: 'No remarks provided') . "</p>
        </div>
        <p>If you have questions about this decision, please contact your HOD directly.</p>
        <p>You may submit a new request with different dates if needed.</p>
    ";

    return send_email($employee_email, $subject, $heading, $body);
}

/**
 * 4. Notify employee and HOD that HR approved the request
 */
function notify_employee_approved($employee_email, $employee_name, $leave_type, $start_date, $end_date, $days, $hr_remarks, $hod_email = null) {
    $subject = "Leave Request Approved - $leave_type";
    $heading = "Leave Approved!";

    $body = "
        <h2 style='color: #4caf50; margin-top: 0;'>Your Leave Request is Approved</h2>
        <p>Dear <strong>$employee_name</strong>,</p>
        <p>We are pleased to inform you that your leave request has been <strong>approved by HR</strong>.</p>
        <div style='background: #e8f5e9; padding: 20px; border-left: 4px solid #4caf50; margin: 20px 0; border-radius: 4px;'>
            <p style='margin: 5px 0;'><strong>Leave Type:</strong> $leave_type</p>
            <p style='margin: 5px 0;'><strong>Start Date:</strong> $start_date</p>
            <p style='margin: 5px 0;'><strong>End Date:</strong> $end_date</p>
            <p style='margin: 5px 0;'><strong>Duration:</strong> $days working days</p>
            <p style='margin: 5px 0;'><strong>HR Remarks:</strong> " . ($hr_remarks ?: 'Approved') . "</p>
        </div>
        <p><strong>Important:</strong> Your leave balance has been updated to reflect this approved leave.</p>
        <p>Have a wonderful time off!</p>
    ";

    $result = send_email($employee_email, $subject, $heading, $body);

    if ($hod_email) {
        $cc_body = "
            <h2 style='color: #4caf50; margin-top: 0;'>Leave Request Approved</h2>
            <p>This is to inform you that the following leave request has been approved by HR:</p>
            <div style='background: #e8f5e9; padding: 20px; border-left: 4px solid #4caf50; margin: 20px 0; border-radius: 4px;'>
                <p style='margin: 5px 0;'><strong>Employee:</strong> $employee_name</p>
                <p style='margin: 5px 0;'><strong>Leave Type:</strong> $leave_type</p>
                <p style='margin: 5px 0;'><strong>Start Date:</strong> $start_date</p>
                <p style='margin: 5px 0;'><strong>End Date:</strong> $end_date</p>
                <p style='margin: 5px 0;'><strong>Duration:</strong> $days working days</p>
            </div>
            <p>Please plan accordingly for the team member's absence.</p>
        ";
        send_email($hod_email, "Leave Approved - $employee_name", "Leave Request Approved", $cc_body);
    }

    return $result;
}

/**
 * 5. Notify employee and HOD that HR rejected the request
 */
function notify_employee_hr_rejected($employee_email, $employee_name, $leave_type, $start_date, $end_date, $hr_remarks, $hod_email = null) {
    $subject = "Leave Request Rejected by HR - $leave_type";
    $heading = "Leave Request Not Approved";

    $body = "
        <h2 style='color: #d32f2f; margin-top: 0;'>Leave Request Rejected by HR</h2>
        <p>Dear <strong>$employee_name</strong>,</p>
        <p>After review, HR has decided not to approve your leave request at this time.</p>
        <div style='background: #ffebee; padding: 20px; border-left: 4px solid #d32f2f; margin: 20px 0; border-radius: 4px;'>
            <p style='margin: 5px 0;'><strong>Leave Type:</strong> $leave_type</p>
            <p style='margin: 5px 0;'><strong>Requested Dates:</strong> $start_date to $end_date</p>
            <p style='margin: 5px 0;'><strong>HR Remarks:</strong> " . ($hr_remarks ?: 'No remarks provided') . "</p>
        </div>
        <p>If you have questions or would like to discuss this further, please contact HR.</p>
    ";

    $result = send_email($employee_email, $subject, $heading, $body);

    if ($hod_email) {
        $cc_body = "
            <h2 style='color: #d32f2f; margin-top: 0;'>Leave Request Rejected by HR</h2>
            <p>This is to inform you that the following leave request has been rejected by HR:</p>
            <div style='background: #ffebee; padding: 20px; border-left: 4px solid #d32f2f; margin: 20px 0; border-radius: 4px;'>
                <p style='margin: 5px 0;'><strong>Employee:</strong> $employee_name</p>
                <p style='margin: 5px 0;'><strong>Leave Type:</strong> $leave_type</p>
                <p style='margin: 5px 0;'><strong>Requested Dates:</strong> $start_date to $end_date</p>
                <p style='margin: 5px 0;'><strong>HR Remarks:</strong> " . ($hr_remarks ?: 'No remarks provided') . "</p>
            </div>
        ";
        send_email($hod_email, "Leave Rejected by HR - $employee_name", "Leave Request Rejected", $cc_body);
    }

    return $result;
}

/**
 * 6. Send notification from Finance to Employee after payment is marked paid
 */
function send_finance_to_employee_confirmation($payment_id, $conn) {
    $sql = "SELECT 
                lp.PaymentID,
                lp.Amount,
                lp.PaymentReference,
                lp.PaymentMethod,
                lp.ProcessedDate,
                lp.PaymentSplitChoice,
                lp.FirstPaymentAmount,
                lp.SecondPaymentAmount,
                emp.FirstName + ' ' + emp.LastName as EmployeeName,
                emp.Email as EmployeeEmail,
                lt.TypeName as LeaveType,
                lr.TotalDays
            FROM LeavePayments lp
            JOIN Users emp ON lp.EmployeeID = emp.UserID
            JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            WHERE lp.PaymentID = ?";

    $stmt = sqlsrv_query($conn, $sql, array($payment_id));
    $payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$payment) { return false; }

    $is_split   = $payment['PaymentSplitChoice'] === 'split';
    $subject    = "Leave Allowance Payment Confirmed - " . number_format($payment['Amount'], 2);
    $heading    = "Payment Confirmed!";

    $split_html = $is_split ? "
        <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #856404; margin-top: 0;'>Payment Split Breakdown</h3>
            <p style='margin: 8px 0; color: #856404;'><strong>First Payment:</strong> &#8358;" . number_format($payment['FirstPaymentAmount'], 2) . "</p>
            <p style='margin: 8px 0; color: #856404;'><strong>Second Payment:</strong> &#8358;" . number_format($payment['SecondPaymentAmount'], 2) . " (To be paid later)</p>
        </div>" : "";

    $processed_date = ($payment['ProcessedDate'] instanceof DateTime)
        ? $payment['ProcessedDate']->format('F d, Y') : 'N/A';

    $body = "
        <h2 style='color: #28a745; margin-top: 0;'>Dear {$payment['EmployeeName']},</h2>
        <p style='font-size: 16px; line-height: 1.6; color: #666;'>
            Your <strong>{$payment['LeaveType']}</strong> allowance has been successfully processed.
        </p>
        <div style='background: #d4edda; padding: 25px; border-radius: 10px; margin: 20px 0; text-align: center;'>
            <h3 style='color: #155724; margin: 0 0 10px 0;'>Total Amount Paid</h3>
            <h2 style='color: #155724; margin: 0; font-size: 42px; font-weight: 700;'>&#8358;" . number_format($payment['Amount'], 2) . "</h2>
        </div>
        $split_html
        <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
            <h3 style='color: #28a745; margin-top: 0;'>Payment Details</h3>
            <p style='margin: 8px 0;'><strong>Payment Method:</strong> {$payment['PaymentMethod']}</p>
            <p style='margin: 8px 0;'><strong>Reference Number:</strong> {$payment['PaymentReference']}</p>
            <p style='margin: 8px 0;'><strong>Payment Date:</strong> $processed_date</p>
            <p style='margin: 8px 0;'><strong>Leave Days:</strong> {$payment['TotalDays']} days</p>
        </div>
        <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 0; color: #004085;'>Please allow 1-3 business days for the payment to reflect in your account.</p>
        </div>
        <div style='text-align: center; margin: 25px 0;'>
            <a href='http://localhost/leave-management/employee/'
               style='display: inline-block; padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>
                View Dashboard
            </a>
        </div>
    ";

    $result = send_email($payment['EmployeeEmail'], $subject, $heading, $body);
    sqlsrv_free_stmt($stmt);
    return $result;
}

/**
 * 7. Send notification to HR when Finance marks payment as paid
 */
function send_finance_payment_notification_to_hr($payment_id, $conn) {
    $sql = "SELECT 
                lp.PaymentID,
                lp.Amount,
                lp.PaymentReference,
                lp.PaymentMethod,
                lp.ProcessedDate,
                emp.FirstName + ' ' + emp.LastName as EmployeeName,
                emp.Department,
                lt.TypeName as LeaveType
            FROM LeavePayments lp
            JOIN Users emp ON lp.EmployeeID = emp.UserID
            JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            WHERE lp.PaymentID = ?";

    $stmt = sqlsrv_query($conn, $sql, array($payment_id));
    $payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$payment) { return false; }

    $processed_date = ($payment['ProcessedDate'] instanceof DateTime)
        ? $payment['ProcessedDate']->format('F d, Y') : 'N/A';

    $hr_sql  = "SELECT Email, FirstName, LastName FROM Users WHERE Role = 'hr' AND IsActive = 1";
    $hr_stmt = sqlsrv_query($conn, $hr_sql);
    $success = true;

    while ($hr = sqlsrv_fetch_array($hr_stmt, SQLSRV_FETCH_ASSOC)) {
        $hr_name = $hr['FirstName'] . ' ' . $hr['LastName'];
        $subject = "Payment Completed: {$payment['EmployeeName']} - &#8358;" . number_format($payment['Amount'], 2);
        $heading = "Payment Notification";

        $body = "
            <h2 style='color: #333;'>Hello $hr_name,</h2>
            <p style='font-size: 16px; line-height: 1.6; color: #666;'>Finance has successfully processed a leave allowance payment.</p>
            <div style='background: #d4edda; padding: 25px; border-radius: 10px; margin: 20px 0; text-align: center; border: 2px solid #28a745;'>
                <h3 style='color: #155724; margin: 0 0 10px 0;'>Payment Completed</h3>
                <h2 style='color: #155724; margin: 0; font-size: 36px;'>&#8358;" . number_format($payment['Amount'], 2) . "</h2>
            </div>
            <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4facfe;'>
                <p style='margin: 8px 0;'><strong>Employee:</strong> {$payment['EmployeeName']}</p>
                <p style='margin: 8px 0;'><strong>Department:</strong> {$payment['Department']}</p>
                <p style='margin: 8px 0;'><strong>Leave Type:</strong> {$payment['LeaveType']}</p>
                <p style='margin: 8px 0;'><strong>Payment Method:</strong> {$payment['PaymentMethod']}</p>
                <p style='margin: 8px 0;'><strong>Reference:</strong> {$payment['PaymentReference']}</p>
                <p style='margin: 8px 0;'><strong>Payment Date:</strong> $processed_date</p>
            </div>
            <div style='text-align: center; margin: 25px 0;'>
                <a href='http://localhost/leave-management/hr/payment_tracking.php'
                   style='display: inline-block; padding: 15px 30px; background: #4facfe; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>
                    View Payment Dashboard
                </a>
            </div>
        ";

        if (!send_email($hr['Email'], $subject, $heading, $body)) {
            $success = false;
        }
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_free_stmt($hr_stmt);
    return $success;
}

/**
 * 8. Send leave payment notification to Finance (triggered when HR approves Annual Leave)
 */
function send_finance_payment_notification($payment_id, $conn) {
    $sql = "SELECT 
                lp.PaymentID,
                lp.Amount,
                lr.StartDate,
                lr.EndDate,
                lr.TotalDays,
                emp.FirstName + ' ' + emp.LastName as EmployeeName,
                emp.Email as EmployeeEmail,
                emp.Department,
                emp.EmployeeNumber,
                lt.TypeName as LeaveType,
                hr.FirstName + ' ' + hr.LastName as HRName
            FROM LeavePayments lp
            JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
            JOIN Users emp ON lp.EmployeeID = emp.UserID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            JOIN Users hr ON lp.ApprovedByHR = hr.UserID
            WHERE lp.PaymentID = ?";

    $stmt = sqlsrv_query($conn, $sql, array($payment_id));
    $payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$payment) { return false; }

    $start = ($payment['StartDate'] instanceof DateTime) ? $payment['StartDate']->format('F d, Y') : 'N/A';
    $end   = ($payment['EndDate']   instanceof DateTime) ? $payment['EndDate']->format('F d, Y')   : 'N/A';

    $finance_sql  = "SELECT Email, FirstName, LastName FROM Users WHERE Role = 'finance' AND IsActive = 1";
    $finance_stmt = sqlsrv_query($conn, $finance_sql);
    $success      = true;

    while ($finance_user = sqlsrv_fetch_array($finance_stmt, SQLSRV_FETCH_ASSOC)) {
        $finance_name = $finance_user['FirstName'] . ' ' . $finance_user['LastName'];
        $subject = "ACTION REQUIRED: Leave Payment for {$payment['EmployeeName']}";
        $heading = "Leave Payment Request";

        $body = "
            <h2 style='color: #333;'>Hello $finance_name,</h2>
            <p style='font-size: 16px; line-height: 1.6; color: #666;'>
                A leave request has been approved by HR and requires payment processing.
            </p>
            <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
                <h3 style='color: #28a745; margin-top: 0;'>Employee Details</h3>
                <p style='margin: 8px 0;'><strong>Name:</strong> {$payment['EmployeeName']}</p>
                <p style='margin: 8px 0;'><strong>Employee Number:</strong> {$payment['EmployeeNumber']}</p>
                <p style='margin: 8px 0;'><strong>Email:</strong> {$payment['EmployeeEmail']}</p>
                <p style='margin: 8px 0;'><strong>Department:</strong> {$payment['Department']}</p>
            </div>
            <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8;'>
                <h3 style='color: #17a2b8; margin-top: 0;'>Leave Information</h3>
                <p style='margin: 8px 0;'><strong>Leave Type:</strong> {$payment['LeaveType']}</p>
                <p style='margin: 8px 0;'><strong>Start Date:</strong> $start</p>
                <p style='margin: 8px 0;'><strong>End Date:</strong> $end</p>
                <p style='margin: 8px 0;'><strong>Total Days:</strong> {$payment['TotalDays']} days</p>
            </div>
            <div style='background: #d4edda; padding: 25px; border-radius: 8px; margin: 20px 0; text-align: center;'>
                <h3 style='color: #155724; margin: 0 0 10px 0;'>Payment Amount</h3>
                <h2 style='color: #155724; margin: 0; font-size: 32px;'>&#8358;" . number_format($payment['Amount'], 2) . "</h2>
            </div>
            <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0; color: #856404;'>
                    <strong>Action Required:</strong> Please process this leave allowance payment and update the status in the system.
                </p>
            </div>
            <div style='text-align: center; margin: 25px 0;'>
                <a href='http://localhost/leave-management/hr/payment_tracking.php'
                   style='display: inline-block; padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: 600;'>
                    Process Payment
                </a>
            </div>
            <p style='color: #666; font-size: 13px; margin-top: 20px;'>
                <strong>Approved by:</strong> {$payment['HRName']} (HR Department)
            </p>
        ";

        if (!send_email($finance_user['Email'], $subject, $heading, $body)) {
            $success = false;
        }
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_free_stmt($finance_stmt);
    return $success;
}

/**
 * 9. Send payment confirmation to employee and HR (called after Finance marks paid)
 */
function send_payment_confirmation($payment_id, $conn) {
    $sql = "SELECT 
                lp.Amount,
                lp.PaymentReference,
                lp.PaymentMethod,
                lp.ProcessedDate,
                emp.FirstName + ' ' + emp.LastName as EmployeeName,
                emp.Email as EmployeeEmail,
                hr.Email as HREmail,
                hr.FirstName + ' ' + hr.LastName as HRName,
                fin.FirstName + ' ' + fin.LastName as FinanceName
            FROM LeavePayments lp
            JOIN Users emp ON lp.EmployeeID = emp.UserID
            JOIN Users hr  ON lp.ApprovedByHR = hr.UserID
            LEFT JOIN Users fin ON lp.ProcessedBy = fin.UserID
            WHERE lp.PaymentID = ?";

    $stmt = sqlsrv_query($conn, $sql, array($payment_id));
    $payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$payment) { return false; }

    $processed_date = ($payment['ProcessedDate'] instanceof DateTime)
        ? $payment['ProcessedDate']->format('F d, Y') : 'N/A';

    // Email to Employee
    $emp_subject = "Leave Payment Processed - &#8358;" . number_format($payment['Amount'], 2);
    $emp_body = "
        <h2 style='color: #333;'>Hello {$payment['EmployeeName']},</h2>
        <p style='font-size: 16px; color: #666;'>Your leave allowance payment has been processed successfully.</p>
        <div style='background: #d4edda; padding: 25px; border-radius: 8px; margin: 20px 0; text-align: center;'>
            <h3 style='color: #155724; margin: 0 0 10px 0;'>Amount Paid</h3>
            <h2 style='color: #155724; margin: 0; font-size: 36px;'>&#8358;" . number_format($payment['Amount'], 2) . "</h2>
        </div>
        <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 8px 0;'><strong>Payment Method:</strong> {$payment['PaymentMethod']}</p>
            <p style='margin: 8px 0;'><strong>Reference:</strong> {$payment['PaymentReference']}</p>
            <p style='margin: 8px 0;'><strong>Processed Date:</strong> $processed_date</p>
            <p style='margin: 8px 0;'><strong>Processed By:</strong> {$payment['FinanceName']}</p>
        </div>
    ";
    send_email($payment['EmployeeEmail'], $emp_subject, "Payment Confirmed", $emp_body);

    // Email to HR
    $hr_subject = "Payment Processed: {$payment['EmployeeName']} - &#8358;" . number_format($payment['Amount'], 2);
    $hr_body = "
        <h2 style='color: #333;'>Hello {$payment['HRName']},</h2>
        <p style='font-size: 16px; color: #666;'>Leave payment has been processed by the Finance department.</p>
        <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 8px 0;'><strong>Employee:</strong> {$payment['EmployeeName']}</p>
            <p style='margin: 8px 0;'><strong>Amount:</strong> &#8358;" . number_format($payment['Amount'], 2) . "</p>
            <p style='margin: 8px 0;'><strong>Status:</strong> Paid</p>
            <p style='margin: 8px 0;'><strong>Processed By:</strong> {$payment['FinanceName']}</p>
        </div>
    ";
    send_email($payment['HREmail'], $hr_subject, "Payment Update", $hr_body);

    sqlsrv_free_stmt($stmt);
    return true;
}

/**
 * 10. Send employee payment request to HR
 */
function send_employee_payment_request_to_hr($payment_id, $conn) {
    $sql = "SELECT 
                lp.PaymentID,
                lp.Amount,
                lp.PaymentSplitChoice,
                lp.FirstPaymentAmount,
                lp.SecondPaymentAmount,
                emp.FirstName + ' ' + emp.LastName as EmployeeName,
                emp.Email as EmployeeEmail,
                emp.Department,
                emp.EmployeeNumber,
                lt.TypeName as LeaveType,
                lr.StartDate,
                lr.EndDate,
                lr.TotalDays
            FROM LeavePayments lp
            JOIN Users emp ON lp.EmployeeID = emp.UserID
            JOIN LeaveRequests lr ON lp.RequestID = lr.RequestID
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            WHERE lp.PaymentID = ?";

    $stmt = sqlsrv_query($conn, $sql, array($payment_id));
    $payment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$payment) { return false; }

    $is_split = $payment['PaymentSplitChoice'] === 'split';
    $start    = ($payment['StartDate'] instanceof DateTime) ? $payment['StartDate']->format('M d, Y') : 'N/A';
    $end      = ($payment['EndDate']   instanceof DateTime) ? $payment['EndDate']->format('M d, Y')   : 'N/A';

    $split_html = $is_split ? "
        <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #856404; margin-top: 0;'>SPLIT PAYMENT REQUESTED</h3>
            <p style='margin: 8px 0; color: #856404;'><strong>First Payment:</strong> &#8358;" . number_format($payment['FirstPaymentAmount'], 2) . " (Pay Now)</p>
            <p style='margin: 8px 0; color: #856404;'><strong>Second Payment:</strong> &#8358;" . number_format($payment['SecondPaymentAmount'], 2) . " (Pay Later)</p>
        </div>" : "
        <div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='color: #155724; margin-top: 0;'>FULL PAYMENT REQUESTED</h3>
            <p style='margin: 0; color: #155724;'>Employee has requested the entire allowance in one payment.</p>
        </div>";

    $hr_sql  = "SELECT Email, FirstName, LastName FROM Users WHERE Role = 'hr' AND IsActive = 1";
    $hr_stmt = sqlsrv_query($conn, $hr_sql);
    $success = true;

    while ($hr = sqlsrv_fetch_array($hr_stmt, SQLSRV_FETCH_ASSOC)) {
        $hr_name = $hr['FirstName'] . ' ' . $hr['LastName'];
        $subject = "PAYMENT REQUEST: {$payment['EmployeeName']} - " . ($is_split ? "SPLIT" : "FULL") . " Payment";
        $heading = "Employee Payment Request";

        $body = "
            <h2 style='color: #333;'>Hello $hr_name,</h2>
            <p style='font-size: 16px; line-height: 1.6; color: #666;'>An employee has submitted a leave allowance payment request.</p>
            <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea;'>
                <h3 style='color: #667eea; margin-top: 0;'>Employee Information</h3>
                <p style='margin: 8px 0;'><strong>Name:</strong> {$payment['EmployeeName']}</p>
                <p style='margin: 8px 0;'><strong>Employee Number:</strong> {$payment['EmployeeNumber']}</p>
                <p style='margin: 8px 0;'><strong>Department:</strong> {$payment['Department']}</p>
                <p style='margin: 8px 0;'><strong>Email:</strong> {$payment['EmployeeEmail']}</p>
            </div>
            <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8;'>
                <h3 style='color: #17a2b8; margin-top: 0;'>Leave Details</h3>
                <p style='margin: 8px 0;'><strong>Leave Type:</strong> {$payment['LeaveType']}</p>
                <p style='margin: 8px 0;'><strong>Period:</strong> $start - $end</p>
                <p style='margin: 8px 0;'><strong>Total Days:</strong> {$payment['TotalDays']} days</p>
            </div>
            <div style='background: #d4edda; padding: 25px; border-radius: 10px; margin: 20px 0; text-align: center;'>
                <h3 style='color: #155724; margin: 0 0 10px 0;'>Total Leave Allowance</h3>
                <h2 style='color: #155724; margin: 0; font-size: 42px;'>&#8358;" . number_format($payment['Amount'], 2) . "</h2>
            </div>
            $split_html
            <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0; color: #004085;'>
                    <strong>Action Required:</strong> Please review and process this payment request in the HR Payment Dashboard.
                </p>
            </div>
            <div style='text-align: center; margin: 25px 0;'>
                <a href='http://localhost/leave-management/hr/payment_tracking.php'
                   style='display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>
                    Review Payment Request
                </a>
            </div>
        ";

        if (!send_email($hr['Email'], $subject, $heading, $body)) {
            $success = false;
        }
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_free_stmt($hr_stmt);
    return $success;
}
?>
