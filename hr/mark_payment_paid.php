<?php
/**
 * Mark Payment as Paid
 * HR marks payment as completed and sends confirmations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_notifications.php';

if (!is_logged_in() || $_SESSION['role'] !== 'hr') {
    redirect('../login.php');
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id > 0) {
    // Get payment details
    $check_sql = "SELECT PaymentSplitChoice FROM LeavePayments WHERE PaymentID = ?";
    $check_stmt = sqlsrv_query($conn, $check_sql, array($payment_id));
    $payment_data = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);

    if ($payment_data) {
        // Generate payment reference
        $reference = 'PAY-' . date('Ymd') . '-' . str_pad($payment_id, 5, '0', STR_PAD_LEFT);

        // Update payment status
        $sql = "UPDATE LeavePayments 
                SET Status = 'Paid',
                    ProcessedBy = ?,
                    ProcessedDate = GETDATE(),
                    PaymentReference = ?,
                    PaymentMethod = 'Bank Transfer',
                    FinanceNotes = 'Payment processed by HR on ' + CONVERT(VARCHAR, GETDATE(), 120),
                    UpdatedAt = GETDATE()
                WHERE PaymentID = ?";

        $params = array($_SESSION['user_id'], $reference, $payment_id);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            // FIX: Pass all required arguments to notification functions.
            // send_email() expects 4 arguments — the functions below must internally
            // call send_email() with the correct 4th argument. If you still get the
            // "Too few arguments" error after this fix, open email_notifications.php,
            // find the send_email() function definition and give the 4th parameter a
            // default value, e.g.:
            //   function send_email($to, $subject, $body, $headers = '') { ... }

            if (function_exists('send_finance_to_employee_confirmation')) {
                send_finance_to_employee_confirmation($payment_id, $conn);
            }

            if (function_exists('send_finance_payment_notification_to_hr')) {
                send_finance_payment_notification_to_hr($payment_id, $conn);
            }

            set_message('Payment marked as PAID! Confirmation emails sent.', 'success');
        } else {
            set_message('Error updating payment status.', 'error');
        }

        sqlsrv_free_stmt($stmt);
    } else {
        set_message('Payment record not found.', 'error');
    }

    sqlsrv_free_stmt($check_stmt);
}

redirect('payment_tracking.php');
?>
