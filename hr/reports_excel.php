<?php
/**
 * Reports Export Page - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Reports - HR Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 5px; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); }
        .nav-menu { background: white; padding: 15px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; gap: 20px; }
        .nav-menu a { padding: 8px 16px; text-decoration: none; color: #333; border-radius: 5px; font-weight: 500; }
        .nav-menu a:hover { background: #f0f0f0; }
        .nav-menu a.active { background: #4facfe; color: white; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .section { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section-header { margin-bottom: 25px; }
        .section-header h2 { color: #333; font-size: 24px; margin-bottom: 10px; }
        .section-header p { color: #666; font-size: 14px; }
        .export-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .export-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; color: white; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .export-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .export-card h3 { font-size: 18px; margin-bottom: 10px; }
        .export-card p { font-size: 14px; opacity: 0.9; margin-bottom: 15px; }
        .export-card .icon { font-size: 40px; margin-bottom: 15px; }
        .btn-export { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; font-size: 14px; border: 1px solid rgba(255,255,255,0.3); }
        .btn-export:hover { background: rgba(255,255,255,0.3); }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Admin - Export Reports</h1>
        <div>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="all_requests.php">All Requests</a>
        <a href="manage_employees.php">Manage Employees</a>
        <a href="manage_leave_types.php">Leave Types</a>
        <a href="reports.php">Reports</a>
        <a href="reports_export.php" class="active">Export</a>
        <a href="settings.php">Settings</a>
    </div>
    
    <div class="container">
        <div class="section">
            <div class="section-header">
                <h2>📊 Export Reports to Excel</h2>
                <p>Download comprehensive reports in Excel format for analysis and record-keeping</p>
            </div>
            
            <div class="export-grid">
                <div class="export-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="icon">📋</div>
                    <h3>All Leave Requests</h3>
                    <p>Complete list of all leave requests with status and details</p>
                    <a href="export_excel.php?type=all_requests" class="btn-export">📥 Download Excel</a>
                </div>
                
                <div class="export-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="icon">✅</div>
                    <h3>Approved Requests</h3>
                    <p>All approved leave requests with approval details</p>
                    <a href="export_excel.php?type=approved_requests" class="btn-export">📥 Download Excel</a>
                </div>
                
                <div class="export-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="icon">⏳</div>
                    <h3>Pending Requests</h3>
                    <p>Leave requests awaiting approval from HOD or HR</p>
                    <a href="export_excel.php?type=pending_requests" class="btn-export">📥 Download Excel</a>
                </div>
                
                <div class="export-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="icon">🏢</div>
                    <h3>Department Summary</h3>
                    <p>Leave statistics grouped by department</p>
                    <a href="export_excel.php?type=department_summary" class="btn-export">📥 Download Excel</a>
                </div>
                
                <div class="export-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                    <div class="icon">👥</div>
                    <h3>Employee Leave Balances</h3>
                    <p>Current leave balances for all active employees</p>
                    <a href="export_excel.php?type=employee_balances" class="btn-export">📥 Download Excel</a>
                </div>
                
                <div class="export-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                    <div class="icon">📈</div>
                    <h3>Leave Usage Report</h3>
                    <p>Statistics on leave type usage and trends</p>
                    <a href="export_excel.php?type=leave_usage" class="btn-export" style="color: #333; border-color: #333;">📥 Download Excel</a>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h3 style="margin-bottom: 15px;">ℹ️ Export Information</h3>
            <ul style="line-height: 2; color: #666;">
                <li>All exports include company branding and are formatted for Excel</li>
                <li>Files are downloaded immediately with automatic filename generation</li>
                <li>Color coding: Green = Approved, Yellow = Pending, Red = Rejected</li>
                <li>Data is exported in real-time from the database</li>
                <li>Files can be opened in Microsoft Excel, Google Sheets, or LibreOffice</li>
            </ul>
        </div>
    </div>
</body>
</html>