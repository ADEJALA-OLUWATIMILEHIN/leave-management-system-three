<?php
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];

// Get month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$first_day = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
$last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
$days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
$first_day_of_week = date('w', mktime(0, 0, 0, $month, 1, $year));

// Fetch approved leave
$leave_sql = "SELECT lr.StartDate, lr.EndDate, u.FirstName + ' ' + u.LastName as EmployeeName, 
              u.Department, lt.TypeName as LeaveType
              FROM LeaveRequests lr
              JOIN Users u ON lr.UserID = u.UserID
              JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
              WHERE lr.Status = 'approved'
              AND lr.StartDate <= ? AND lr.EndDate >= ?";
$leave_stmt = sqlsrv_query($conn, $leave_sql, array($last_day, $first_day));

$leave_by_date = array();
while ($leave = sqlsrv_fetch_array($leave_stmt, SQLSRV_FETCH_ASSOC)) {
    $start = $leave['StartDate']->format('Y-m-d');
    $end = $leave['EndDate']->format('Y-m-d');
    
    $current = strtotime($start);
    $end_time = strtotime($end);
    
    while ($current <= $end_time) {
        $date = date('Y-m-d', $current);
        if ($date >= $first_day && $date <= $last_day) {
            if (!isset($leave_by_date[$date])) {
                $leave_by_date[$date] = array();
            }
            $leave_by_date[$date][] = $leave;
        }
        $current = strtotime('+1 day', $current);
    }
}

// Fetch holidays
$holiday_sql = "SELECT HolidayName, HolidayDate FROM PublicHolidays 
                WHERE HolidayDate >= ? AND HolidayDate <= ? AND IsActive = 1";
$holiday_stmt = sqlsrv_query($conn, $holiday_sql, array($first_day, $last_day));

$holidays = array();
while ($holiday = sqlsrv_fetch_array($holiday_stmt, SQLSRV_FETCH_ASSOC)) {
    $date = $holiday['HolidayDate']->format('Y-m-d');
    $holidays[$date] = $holiday['HolidayName'];
}

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Leave Calendar</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; }
        .header h1 { font-size: 24px; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 5px; }
        .nav-menu { background: white; padding: 15px 30px; display: flex; gap: 20px; }
        .nav-menu a { padding: 8px 16px; text-decoration: none; color: #333; border-radius: 5px; }
        .nav-menu a.active { background: #4facfe; color: white; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .calendar-header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .calendar-header h2 { font-size: 28px; }
        .btn-nav { padding: 8px 16px; background: #4facfe; color: white; text-decoration: none; border-radius: 5px; margin: 0 5px; }
        .calendar { width: 100%; background: white; border-collapse: collapse; padding: 20px; }
        .calendar th { background: #4facfe; color: white; padding: 15px; }
        .calendar td { border: 1px solid #ddd; height: 100px; vertical-align: top; padding: 5px; }
        .day-number { font-weight: bold; margin-bottom: 5px; }
        .weekend { background: #f8f8f8; }
        .today { background: #e3f2fd; border: 2px solid #4facfe; }
        .holiday { background: #fff9c4; }
        .leave-item { background: #667eea; color: white; padding: 2px 5px; margin: 2px 0; border-radius: 3px; font-size: 11px; }
        .holiday-label { background: #ffc107; padding: 2px 5px; margin: 2px 0; border-radius: 3px; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Leave Calendar</h1>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="all_requests.php">All Requests</a>
        <a href="manage_employees.php">Manage Employees</a>
        <a href="calendar.php" class="active">Calendar</a>
        <a href="reports.php">Reports</a>
    </div>
    
    <div class="container">
        <div class="calendar-header">
            <h2>📅 <?php echo $month_name . ' ' . $year; ?></h2>
            <div>
                <a href="calendar.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn-nav">← Previous</a>
                <a href="calendar.php" class="btn-nav">Today</a>
                <a href="calendar.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn-nav">Next →</a>
            </div>
        </div>
        
        <table class="calendar">
            <tr>
                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
            </tr>
            <?php
            $day = 1;
            $weeks = ceil(($days_in_month + $first_day_of_week) / 7);
            
            for ($week = 0; $week < $weeks; $week++) {
                echo '<tr>';
                for ($d = 0; $d < 7; $d++) {
                    $cell_index = ($week * 7) + $d;
                    
                    if ($cell_index < $first_day_of_week || $day > $days_in_month) {
                        echo '<td></td>';
                    } else {
                        $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $is_weekend = ($d == 0 || $d == 6);
                        $is_today = ($current_date == $today);
                        $is_holiday = isset($holidays[$current_date]);
                        
                        $class = '';
                        if ($is_weekend) $class .= 'weekend ';
                        if ($is_today) $class .= 'today ';
                        if ($is_holiday) $class .= 'holiday ';
                        
                        echo '<td class="' . trim($class) . '">';
                        echo '<div class="day-number">' . $day . '</div>';
                        
                        if ($is_holiday) {
                            echo '<div class="holiday-label">' . htmlspecialchars($holidays[$current_date]) . '</div>';
                        }
                        
                        if (isset($leave_by_date[$current_date])) {
                            foreach ($leave_by_date[$current_date] as $leave) {
                                echo '<div class="leave-item">';
                                echo htmlspecialchars(substr($leave['EmployeeName'], 0, 15));
                                echo '</div>';
                            }
                        }
                        
                        echo '</td>';
                        $day++;
                    }
                }
                echo '</tr>';
            }
            ?>
        </table>
    </div>
</body>
</html>