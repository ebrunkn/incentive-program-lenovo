<?php
// admin_dashboard.php (Main Admin View for Client and Agency)
require_once 'config.php';

if (!is_admin_logged_in()) {
    redirect('admin_login.php');
}

$admin_role = get_admin_role();
$admin_message = '';
$current_view = $_GET['view'] ?? 'dashboard';

// Handle submission actions (Approve, Reject, Flag for Agency)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submission_action'])) {
    if ($admin_role == 'Agency' || $admin_role == 'Client') { // Both can approve/reject
        $submission_id = (int)$_POST['submission_id'];
        $action = $_POST['action'];
        $admin_notes = $conn->real_escape_string($_POST['admin_notes'] ?? '');

        if ($action == 'approve') {
            $stmt = $conn->prepare("UPDATE submissions SET status = 'Approved', admin_notes = ? WHERE id = ?");
            $stmt->bind_param("si", $admin_notes, $submission_id);
        } elseif ($action == 'reject') {
            $stmt = $conn->prepare("UPDATE submissions SET status = 'Rejected', admin_notes = ? WHERE id = ?");
            $stmt->bind_param("si", $admin_notes, $submission_id);
        }

        if (isset($stmt) && $stmt->execute()) {
            $admin_message = "Submission #{$submission_id} {$action}d successfully!";
            // TODO: Send email notification to user about status change
        } else if (isset($stmt)) {
            $admin_message = "Error performing action: " . $stmt->error;
        }
        if (isset($stmt)) $stmt->close();
    }
}

// Fetch metrics for dashboard
$metrics = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as total_users FROM users");
$metrics['total_users'] = $result->fetch_assoc()['total_users'];

// Total submissions
$result = $conn->query("SELECT COUNT(*) as total_submissions FROM submissions");
$metrics['total_submissions'] = $result->fetch_assoc()['total_submissions'];

// Pending submissions
$result = $conn->query("SELECT COUNT(*) as pending_submissions FROM submissions WHERE status = 'Pending'");
$metrics['pending_submissions'] = $result->fetch_assoc()['pending_submissions'];

// Approved submissions
$result = $conn->query("SELECT COUNT(*) as approved_submissions FROM submissions WHERE status = 'Approved'");
$metrics['approved_submissions'] = $result->fetch_assoc()['approved_submissions'];

// Rejected submissions
$result = $conn->query("SELECT COUNT(*) as rejected_submissions FROM submissions WHERE status = 'Rejected'");
$metrics['rejected_submissions'] = $result->fetch_assoc()['rejected_submissions'];

// Fetch recent submissions
$recent_submissions = [];
$stmt = $conn->prepare("
    SELECT s.*, u.full_name, u.email 
    FROM submissions s 
    JOIN users u ON s.user_id = u.id 
    ORDER BY s.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_submissions[] = $row;
}
$stmt->close();

// Fetch all users for users list
$all_users = [];
if ($current_view == 'users') {
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $all_users[] = $row;
    }
    $stmt->close();
}

// Fetch all submissions for submissions list
$all_submissions = [];
if ($current_view == 'submissions') {
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name, u.email 
        FROM submissions s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $all_submissions[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Incentive Program</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f8f9fa; 
            color: #333;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Side Navigation */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            opacity: 0.8;
            font-size: 14px;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin: 5px 0;
        }
        
        .nav-link {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #fff;
            transform: translateX(5px);
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-role {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        /* Metrics Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
            border-left: 4px solid;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .metric-card.users { border-left-color: #007bff; }
        .metric-card.submissions { border-left-color: #28a745; }
        .metric-card.pending { border-left-color: #ffc107; }
        .metric-card.approved { border-left-color: #20c997; }
        .metric-card.rejected { border-left-color: #dc3545; }
        
        .metric-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .metric-label {
            font-size: 16px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .card-header h2 {
            color: #333;
            font-size: 22px;
            margin: 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        /* Status badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        /* Action buttons */
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            margin: 2px;
            transition: all 0.3s ease;
        }
        
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        
        .btn:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }
        
        /* Messages */
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Modal for submission actions */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Side Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
                <p>Role: <?php echo htmlspecialchars($admin_role); ?></p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="?view=dashboard" class="nav-link <?php echo $current_view == 'dashboard' ? 'active' : ''; ?>">
                        📊 Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?view=users" class="nav-link <?php echo $current_view == 'users' ? 'active' : ''; ?>">
                        👥 Users List
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?view=submissions" class="nav-link <?php echo $current_view == 'submissions' ? 'active' : ''; ?>">
                        📋 Submissions List
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        🚪 Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1>
                    <?php 
                    switch($current_view) {
                        case 'users': echo 'Users Management'; break;
                        case 'submissions': echo 'Submissions Management'; break;
                        default: echo 'Admin Dashboard'; break;
                    }
                    ?>
                </h1>
                <div class="admin-info">
                    <span class="admin-role"><?php echo htmlspecialchars($admin_role); ?></span>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </header>

            <?php if ($admin_message): ?>
                <div class="message <?php echo (strpos($admin_message, 'successfully') !== false) ? 'success' : 'error'; ?>">
                    <?php echo $admin_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($current_view == 'dashboard'): ?>
                <!-- Metrics Cards -->
                <div class="metrics-grid">
                    <div class="metric-card users">
                        <div class="metric-number"><?php echo $metrics['total_users']; ?></div>
                        <div class="metric-label">Total Users</div>
                    </div>
                    <div class="metric-card submissions">
                        <div class="metric-number"><?php echo $metrics['total_submissions']; ?></div>
                        <div class="metric-label">Total Submissions</div>
                    </div>
                    <div class="metric-card pending">
                        <div class="metric-number"><?php echo $metrics['pending_submissions']; ?></div>
                        <div class="metric-label">Pending Review</div>
                    </div>
                    <div class="metric-card approved">
                        <div class="metric-number"><?php echo $metrics['approved_submissions']; ?></div>
                        <div class="metric-label">Approved</div>
                    </div>
                    <div class="metric-card rejected">
                        <div class="metric-number"><?php echo $metrics['rejected_submissions']; ?></div>
                        <div class="metric-label">Rejected</div>
                    </div>
                </div>

                <!-- Recent Submissions -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Recent Submissions</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_submissions)): ?>
                            <p>No submissions yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>File</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_submissions as $submission): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($submission['created_at'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($submission['full_name']); ?><br>
                                                    <small><?php echo htmlspecialchars($submission['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($submission['original_filename']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($submission['status']); ?>">
                                                        <?php echo $submission['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-info">View</a>
                                                    <?php if ($submission['status'] == 'Pending'): ?>
                                                        <button onclick="openActionModal(<?php echo $submission['id']; ?>, 'approve')" class="btn btn-success">Approve</button>
                                                        <button onclick="openActionModal(<?php echo $submission['id']; ?>, 'reject')" class="btn btn-danger">Reject</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($current_view == 'users'): ?>
                <!-- Users List -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>All Users</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_users)): ?>
                            <p>No users found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Registration Date</th>
                                            <th>Email Verified</th>
                                            <th>Submissions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_users as $user): ?>
                                            <?php
                                            // Get submission count for this user
                                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE user_id = ?");
                                            $stmt->bind_param("i", $user['id']);
                                            $stmt->execute();
                                            $submission_count = $stmt->get_result()->fetch_assoc()['count'];
                                            $stmt->close();
                                            ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $user['email_verified'] ? 'status-approved' : 'status-pending'; ?>">
                                                        <?php echo $user['email_verified'] ? 'Verified' : 'Pending'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $submission_count; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($current_view == 'submissions'): ?>
                <!-- Submissions List -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>All Submissions</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_submissions)): ?>
                            <p>No submissions found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>File</th>
                                            <th>Product</th>
                                            <th>Invoice</th>
                                            <th>Status</th>
                                            <th>Admin Notes</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_submissions as $submission): ?>
                                            <tr>
                                                <td><?php echo $submission['id']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($submission['created_at'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($submission['full_name']); ?><br>
                                                    <small><?php echo htmlspecialchars($submission['email']); ?></small>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-info">
                                                        View File
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($submission['product_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($submission['invoice_number'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($submission['status']); ?>">
                                                        <?php echo $submission['status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($submission['admin_notes'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($submission['status'] == 'Pending'): ?>
                                                        <button onclick="openActionModal(<?php echo $submission['id']; ?>, 'approve')" class="btn btn-success">Approve</button>
                                                        <button onclick="openActionModal(<?php echo $submission['id']; ?>, 'reject')" class="btn btn-danger">Reject</button>
                                                    <?php else: ?>
                                                        <button onclick="openActionModal(<?php echo $submission['id']; ?>, 'approve')" class="btn btn-warning">Re-approve</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Action Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 id="modalTitle">Action Required</h3>
            <form id="actionForm" method="POST">
                <input type="hidden" name="submission_action" value="1">
                <input type="hidden" name="submission_id" id="submissionId">
                <input type="hidden" name="action" id="actionType">
                
                <div class="form-group">
                    <label for="admin_notes">Admin Notes (Optional):</label>
                    <textarea name="admin_notes" id="admin_notes" rows="4" placeholder="Add any notes about this action..."></textarea>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openActionModal(submissionId, action) {
            document.getElementById('submissionId').value = submissionId;
            document.getElementById('actionType').value = action;
            document.getElementById('modalTitle').textContent = action.charAt(0).toUpperCase() + action.slice(1) + ' Submission #' + submissionId;
            document.getElementById('actionModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
            document.getElementById('admin_notes').value = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('actionModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal with X button
        document.querySelector('.close').onclick = closeModal;
    </script>
</body>
</html>