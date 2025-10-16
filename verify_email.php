<?php
// verify_email.php
require_once 'config.php';


$message = '';

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);

    // Check if token exists and user not verified yet
    $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE verification_token = ? AND email_verified = FALSE");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($user_id, $full_name, $email);
        $stmt->fetch();

        // Update user verification status
        $update_stmt = $conn->prepare("UPDATE users SET email_verified = TRUE, verification_token = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        if ($update_stmt->execute()) {
            $message = "Your email has been successfully verified! You can now log in.";

            // 🎉 Send confirmation email
            $subject = "Your Incentive Program Account is Verified!";
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h3>Hi $full_name,</h3>
                <p>Your email has been successfully verified.</p>
                <p>You can now log in and start using the <strong>Incentive Program</strong>.</p>
                <p><a href='" . BASE_URL . "/index.php' 
                    style='display:inline-block;padding:10px 20px;background:#007bff;color:#fff;border-radius:4px;text-decoration:none;'>
                    Go to Login Page
                </a></p>
                <p>Thank you,<br>The Incentive Program Team</p>
            </body>
            </html>";

            send_email($email, $subject, $body);
        } else {
            $message = "Error updating verification status: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        $message = "Invalid or expired verification token.";
    }
    $stmt->close();
} else {
    $message = "No verification token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f4f4f4; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
        }
        .container { 
            background-color: #fff; 
            padding: 20px 40px; 
            border-radius: 8px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            text-align: center; 
            max-width: 450px;
        }
        h2 { color: #333; }
        p { color: #555; }
        .success { color: green; }
        .error { color: red; }
        a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Email Verification</h2>
        <p class="<?php echo (strpos($message, 'successfully') !== false) ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </p>
        <p><a href="index.php">Go to Login Page</a></p>
    </div>
</body>
</html>
