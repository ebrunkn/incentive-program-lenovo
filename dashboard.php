<?php
require_once 'config.php';

if (!is_user_logged_in()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$upload_message = '';
$user_full_name = '';
$user_email = '';

// Fetch user details
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_full_name, $user_email);
$stmt->fetch();
$stmt->close();

// Handle Proof Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_proof'])) {
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['proof_file']['tmp_name'];
        $original_filename = basename($_FILES['proof_file']['name']);
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

        $allowed_ext = ['png', 'pdf', 'jpg', 'jpeg'];
        if (!in_array($file_extension, $allowed_ext)) {
            $upload_message = "Invalid file type. Only PNG, PDF, JPG, JPEG are allowed.";
        } else {
            $new_filename = uniqid('proof_', true) . '.' . $file_extension;
            $upload_dir = 'uploads/';
            $file_path = $upload_dir . $new_filename;

            // Ensure upload directory exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (move_uploaded_file($file_tmp_name, $file_path)) {
                $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
                $product_name = !empty($_POST['product_name']) ? $conn->real_escape_string($_POST['product_name']) : NULL;
                $invoice_number = !empty($_POST['invoice_number']) ? $conn->real_escape_string($_POST['invoice_number']) : NULL;

                $stmt = $conn->prepare("INSERT INTO submissions (user_id, file_path, original_filename, purchase_date, product_name, invoice_number) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $user_id, $file_path, $original_filename, $purchase_date, $product_name, $invoice_number);

                if ($stmt->execute()) {
                    $upload_message = "Proof uploaded successfully!";

                    // ✅ Send Thank You Email
                    $subject = "Thank you for your submission";
                    $body = "
                        <p>Hi {$user_full_name},</p>
                        <p>Thank you for submitting your proof. Our team will review your submission shortly.</p>
                        <p>We appreciate your participation in our Incentive Program!</p>
                        <br>
                        <p>Best regards,<br><strong>Incentive Program Team</strong></p>
                    ";

                    $emailSent = send_email($user_email, $subject, $body);
                    if (!$emailSent) {
                        error_log('Failed to send thank-you email to ' . $user_email);
                    }

                } else {
                    $upload_message = "Error saving submission details: " . $stmt->error;
                    unlink($file_path);
                }
                $stmt->close();
            } else {
                $upload_message = "Error moving uploaded file.";
            }
        }
    } else {
        $upload_message = "Please select a file to upload or an error occurred: " . $_FILES['proof_file']['error'];
    }
}

// Fetch user's submissions
$submissions = [];
$stmt = $conn->prepare("SELECT id, original_filename, status, created_at, file_path FROM submissions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Incentive Program</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; background-color: #007bff; color: white; padding: 10px 20px; border-radius: 8px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header .user-info { font-size: 18px; }
        .header a { color: white; text-decoration: none; margin-left: 15px; }
        .header a:hover { text-decoration: underline; }

        .dashboard-content { display: flex; flex-wrap: wrap; gap: 20px; }
        .card { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); flex: 1; min-width: 300px; }
        .card h2 { color: #333; margin-top: 0; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="date"], input[type="text"], input[type="file"] { width: calc(100% - 20px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #218838; }
        .message { margin-bottom: 15px; padding: 10px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-Pending { color: orange; }
        .status-Approved { color: green; }
        .status-Rejected { color: red; }
        .view-file-btn { background-color: #007bff; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; }
        .accept-file-btn { background-color: green; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; }
        .reject-file-btn { background-color: red; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; }
        .view-file-btn:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Incentive Program</h1>
        <div class="user-info">
            Welcome, <?php echo htmlspecialchars($user_full_name); ?>!
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="card">
            <h2>Upload New Proof</h2>
            <?php if ($upload_message): ?>
                <p class="message <?php echo (strpos($upload_message, 'successfully') !== false) ? 'success' : 'error'; ?>"><?php echo $upload_message; ?></p>
            <?php endif; ?>
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <label for="proof_file">Choose File or Take Photo (PNG, PDF, JPG, JPEG):</label>
                <input type="file" id="proof_file" name="proof_file" accept=".png,.pdf,.jpg,.jpeg" required>
                
                <label for="purchase_date">Purchase Date (Optional):</label>
                <input type="date" id="purchase_date" name="purchase_date">
                
                <label for="product_name">Product Name (Optional):</label>
                <input type="text" id="product_name" name="product_name">
                
                <label for="invoice_number">Invoice Number (Optional):</label>
                <input type="text" id="invoice_number" name="invoice_number">
                
                <button type="submit" name="upload_proof">Upload Proof</button>
            </form>
        </div>

        <div class="card">
            <h2>Your Submissions History</h2>
            <?php if (empty($submissions)): ?>
                <p>No submissions yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date Uploaded</th>
                            <th>Original Filename</th>
                            <th>Status</th>
                            <th>Incentive Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($submission['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($submission['original_filename']); ?></td>
                                <td><span class="status-<?php echo $submission['status']; ?>"><?php echo $submission['status']; ?></span></td>
                                <td><?php echo $submission['incentive_amount'] ?? 'TBD'; ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="reject-file-btn">Delete</a>
                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="view-file-btn">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
