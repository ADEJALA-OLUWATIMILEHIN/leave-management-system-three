<?php
/**
 * Manage Leave Types - HR Admin
 * Leave Management System
 */

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is HR
if (!is_logged_in() || !is_hr()) {
    redirect('login.php');
}

$user_name = $_SESSION['name'];

// Handle Add/Edit/Toggle Leave Type
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $type_name = sanitize_input($_POST['type_name']);
            $max_days = (int)$_POST['max_days'];
            $description = sanitize_input($_POST['description']);
            
            $sql = "INSERT INTO LeaveTypes (TypeName, MaxDaysPerYear, Description, IsActive)
                    VALUES (?, ?, ?, 1)";
            $params = array($type_name, $max_days, $description);
            
            if (sqlsrv_query($conn, $sql, $params)) {
                set_message('Leave type added successfully!', 'success');
            } else {
                set_message('Failed to add leave type.', 'error');
            }
            redirect('manage_leave_types.php');
        }
        
        if ($action === 'edit') {
            $leave_type_id = (int)$_POST['leave_type_id'];
            $type_name = sanitize_input($_POST['type_name']);
            $max_days = (int)$_POST['max_days'];
            $description = sanitize_input($_POST['description']);
            
            $sql = "UPDATE LeaveTypes 
                    SET TypeName = ?, MaxDaysPerYear = ?, Description = ?
                    WHERE LeaveTypeID = ?";
            $params = array($type_name, $max_days, $description, $leave_type_id);
            
            if (sqlsrv_query($conn, $sql, $params)) {
                set_message('Leave type updated successfully!', 'success');
            } else {
                set_message('Failed to update leave type.', 'error');
            }
            redirect('manage_leave_types.php');
        }
        
        if ($action === 'toggle') {
            $leave_type_id = (int)$_POST['leave_type_id'];
            $is_active = (int)$_POST['is_active'];
            $new_status = $is_active ? 0 : 1;
            
            $sql = "UPDATE LeaveTypes SET IsActive = ? WHERE LeaveTypeID = ?";
            $params = array($new_status, $leave_type_id);
            
            if (sqlsrv_query($conn, $sql, $params)) {
                $msg = $new_status ? 'activated' : 'deactivated';
                set_message("Leave type {$msg} successfully!", 'success');
            }
            redirect('manage_leave_types.php');
        }
    }
}

// Fetch all leave types
$types_sql = "SELECT LeaveTypeID, TypeName, MaxDaysPerYear, Description, IsActive 
              FROM LeaveTypes 
              ORDER BY TypeName";
$types_stmt = sqlsrv_query($conn, $types_sql);

$message = get_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Types - HR Admin</title>
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
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section-header { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .section-header h2 { color: #333; font-size: 20px; }
        .btn-primary { padding: 10px 20px; background: #4facfe; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; display: inline-block; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; padding: 12px; background: #f8f9fa; color: #666; font-weight: 600; font-size: 14px; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; color: #333; font-size: 14px; }
        table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 30px; width: 90%; max-width: 500px; border-radius: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; margin-right: 5px; color: white; }
        .btn-edit { background: #4facfe; }
        .btn-toggle { background: #ffc107; }
        .btn-close { background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HR Admin - Leave Types</h1>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
    
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="all_requests.php">All Requests</a>
        <a href="manage_employees.php">Manage Employees</a>
        <a href="manage_leave_types.php" class="active">Leave Types</a>
        <a href="reports.php">Reports</a>
        <a href="settings.php">Settings</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <div class="section-header">
                <h2>Leave Types</h2>
                <button class="btn-primary" onclick="openAddModal()">+ Add Leave Type</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Max Days/Year</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($type = sqlsrv_fetch_array($types_stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($type['TypeName']); ?></strong></td>
                            <td><?php echo $type['MaxDaysPerYear']; ?> days</td>
                            <td><?php echo htmlspecialchars($type['Description']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $type['IsActive'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $type['IsActive'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($type); ?>)'>Edit</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="leave_type_id" value="<?php echo $type['LeaveTypeID']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $type['IsActive']; ?>">
                                    <button type="submit" name="action" value="toggle" class="btn-action btn-toggle">
                                        <?php echo $type['IsActive'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Add Leave Type</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Leave Type Name *</label>
                    <input type="text" name="type_name" placeholder="e.g., Bereavement Leave" required>
                </div>
                <div class="form-group">
                    <label>Maximum Days Per Year *</label>
                    <input type="number" name="max_days" min="1" max="365" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description of this leave type"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary">Add Leave Type</button>
                    <button type="button" class="btn-close" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Edit Leave Type</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="leave_type_id" id="edit_leave_type_id">
                <div class="form-group">
                    <label>Leave Type Name *</label>
                    <input type="text" name="type_name" id="edit_type_name" required>
                </div>
                <div class="form-group">
                    <label>Maximum Days Per Year *</label>
                    <input type="number" name="max_days" id="edit_max_days" min="1" max="365" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary">Update Leave Type</button>
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(type) {
            document.getElementById('edit_leave_type_id').value = type.LeaveTypeID;
            document.getElementById('edit_type_name').value = type.TypeName;
            document.getElementById('edit_max_days').value = type.MaxDaysPerYear;
            document.getElementById('edit_description').value = type.Description || '';
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php
if ($types_stmt) sqlsrv_free_stmt($types_stmt);
?>
