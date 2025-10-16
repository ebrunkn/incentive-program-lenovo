<?php
// admin_dashboard.php (Main Admin View for Client and Agency)
require_once 'config.php';

if (!is_admin_logged_in()) {
    redirect('admin_login.php');
}

$admin_role = get_admin_role();
$admin_message = '';

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

    if ($admin_role == '