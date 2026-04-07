<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management System - Sterling Assurance Nigeria Limited</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e22ce 100%);
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background shapes */
        .bg-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }
        
        .shape:nth-child(1) {
            width: 300px;
            height: 300px;
            background: linear-gradient(45deg, #fff, #7e22ce);
            border-radius: 50%;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 200px;
            height: 200px;
            background: linear-gradient(45deg, #2a5298, #fff);
            border-radius: 50%;
            top: 60%;
            right: 15%;
            animation-delay: 3s;
        }
        
        .shape:nth-child(3) {
            width: 250px;
            height: 250px;
            background: linear-gradient(45deg, #7e22ce, #2a5298);
            border-radius: 50%;
            bottom: 15%;
            left: 20%;
            animation-delay: 6s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(50px, 30px) rotate(90deg); }
            50% { transform: translate(20px, -40px) rotate(180deg); }
            75% { transform: translate(-30px, 20px) rotate(270deg); }
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 10;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .company-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        
        .company-info h1 {
            color: #1e3c72;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .company-info p {
            color: #666;
            font-size: 14px;
        }
        
        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 30px;
            position: relative;
            z-index: 10;
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 60px;
            color: white;
        }
        
        .welcome-section h2 {
            font-size: 48px;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .welcome-section p {
            font-size: 20px;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Portal Cards */
        .portals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .portal-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 2px solid transparent;
        }
        
        .portal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            border-color: #2a5298;
        }
        
        .portal-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            transition: 0.3s;
        }
        
        .portal-card:hover .portal-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .portal-card.employee .portal-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .portal-card.hod .portal-icon {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .portal-card.hr .portal-icon {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }
        
        .portal-card h3 {
            color: #1e3c72;
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .portal-card p {
            color: #666;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .portal-card .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
        }
        
        .portal-card:hover .btn {
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            transform: translateY(-2px);
        }
        
        /* Features Section */
        .features {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
        }
        
        .features h3 {
            color: #1e3c72;
            font-size: 28px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .feature-item {
            text-align: center;
            padding: 20px;
        }
        
        .feature-item .icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .feature-item h4 {
            color: #1e3c72;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .feature-item p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 30px;
            color: white;
            position: relative;
            z-index: 10;
        }
        
        .footer p {
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .welcome-section h2 {
                font-size: 32px;
            }
            
            .portals-grid {
                grid-template-columns: 1fr;
            }
            
            .portal-card {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background Shapes -->
    <div class="bg-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <img src="/leave-management/sterling_logo_2.png" 
     alt="Sterling Assurance"
     style="width: 10%; height: 10%; object-fit: contain;">
            <div class="company-info">
                <h1>Sterling Assurance Nigeria Limited</h1>
                <p>Leave Management System</p>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>Welcome to Leave Management</h2>
            <p>Select your portal below to access the leave management system</p>
        </div>
        
        <!-- Portal Cards -->
        <div class="portals-grid">
            <!-- Employee Portal -->
            <a href="employee/index.php" class="portal-card employee">
                <div class="portal-icon">👤</div>
                <h3>Employee Portal</h3>
                <p>Apply for leave, view leave balance, track leave requests, and manage your leave allowance payments</p>
                <span class="btn">Access Employee Portal →</span>
            </a>
            
            <!-- HOD Portal -->
            <a href="hod/login.php" class="portal-card hod">
                <div class="portal-icon">👔</div>
                <h3>HOD Portal</h3>
                <p>Review and approve leave requests from your team members and manage departmental leave schedules</p>
                <span class="btn">Access HOD Portal →</span>
            </a>
            
            <!-- HR Portal -->
            <a href="hr/login.php" class="portal-card hr">
                <div class="portal-icon">🏢</div>
                <h3>HR Portal</h3>
                <p>Manage employees, approve leave requests, track payments, generate reports, and oversee the entire system</p>
                <span class="btn">Access HR Portal →</span>
            </a>
        </div>
        
        <!-- Features Section -->
        <div class="features">
            <h3>✨ System Features</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="icon">📝</div>
                    <h4>Easy Leave Application</h4>
                    <p>Simple and intuitive leave request process</p>
                </div>
                <div class="feature-item">
                    <div class="icon">⚡</div>
                    <h4>Quick Approvals</h4>
                    <p>Two-stage approval workflow (HOD & HR)</p>
                </div>
                <div class="feature-item">
                    <div class="icon">💰</div>
                    <h4>Payment Tracking</h4>
                    <p>Track annual leave allowance payments</p>
                </div>
                <div class="feature-item">
                    <div class="icon">📧</div>
                    <h4>Email Notifications</h4>
                    <p>Automated reminders and confirmations</p>
                </div>
                <div class="feature-item">
                    <div class="icon">📊</div>
                    <h4>Reports & Analytics</h4>
                    <p>Comprehensive leave reports and exports</p>
                </div>
                <div class="feature-item">
                    <div class="icon">🔒</div>
                    <h4>Secure & Reliable</h4>
                    <p>Role-based access control system</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p><strong>Sterling Assurance Nigeria Limited</strong></p>
        <p>IT Department | Leave Management System</p>
        <p><a href="https://www.sterlingassure.com/" target="_blank">www.sterlingassure.com</a></p>
        <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
            © <?php echo date('Y'); ?> Sterling Assurance Nigeria Limited. All rights reserved.
        </p>
    </div>
</body>
</html>