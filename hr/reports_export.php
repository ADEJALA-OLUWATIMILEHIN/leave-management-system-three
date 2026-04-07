<?php
require_once __DIR__ . '/../config/database.php';

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
        .section-header h2 { color: #333; font-size: 24px; margin-bottom: 10px; }
        .section-header p { color: #666; font-size: 14px; }
        .export-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .export-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; color: white; }
        .export-card h3 { font-size: 18px; margin: 15px 0 10px 0; }
        .export-card p { font-size: 14px; opacity: 0.9; margin-bottom: 15px; }
        .export-card .icon { font-size: 40px; }
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
        <a href="reports.php">Reports</a>
        <a href="reports_export.php" class="active">Export</a>
        <a href="settings.php">Settings</a>
    </div>
    
    <div class="container">
        <div class="section">
            <div class="section-header">
                <h2>📊 Export Reports to Excel</h2>
                <p>Download leave management reports in Excel format</p>
            </div>
            
            <div class="export-grid">
                <div class="export-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="icon">📋</div>
                    <h3>All Leave Requests</h3>
                    <p>Complete list of all leave requests</p>
                    <a href="export_excel.php" class="btn-export">📥 Download Excel</a>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>📄 Export Reports to PDF</h2>
                <p>Download professional PDF reports with statistics and formatting</p>
            </div>
            
            <div class="export-grid">
                <div class="export-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="icon">📄</div>
                    <h3>Summary PDF Report</h3>
                    <p>Quick overview with statistics</p>
                    <a href="export_pdf.php?type=summary" class="btn-export">📥 Download PDF</a>
                </div>

                <div class="export-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="icon">📋</div>
                    <h3>Detailed PDF Report</h3>
                    <p>Complete leave request details</p>
                    <a href="export_pdf.php?type=detailed" class="btn-export">📥 Download PDF</a>
                </div>

                <div class="export-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="icon">🏢</div>
                    <h3>Department PDF Report</h3>
                    <p>Leave statistics by department</p>
                    <a href="export_pdf.php?type=department" class="btn-export">📥 Download PDF</a>
                </div>

                <div class="export-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                    <div class="icon">👥</div>
                    <h3>Employee Balances PDF</h3>
                    <p>Current leave balances report</p>
                    <a href="export_pdf.php?type=employee" class="btn-export">📥 Download PDF</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>