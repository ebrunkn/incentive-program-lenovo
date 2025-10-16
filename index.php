<?php
require_once 'config.php';

// 🔹 Redirect if logged in
if (is_user_logged_in()) {
    redirect('dashboard.php');
}


// ===============================
// 🔹 Variables
// ===============================
$registration_error = '';
$login_error = '';
$success_message = '';

// ===============================
// 🔹 Handle Registration
// ===============================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
        $registration_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registration_error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $registration_error = "Password must be at least 6 characters.";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $verification_token = bin2hex(random_bytes(32));

        $stmt = $conn->prepare("INSERT INTO users (full_name, email, username, password_hash, verification_token) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
        $stmt->bind_param("sssss", $full_name, $email, $username, $password_hash, $verification_token);

        if ($stmt->execute()) {
            $verification_link = BASE_URL . '/verify_email.php?token=' . $verification_token;

            // ✉️ Verification Email
            $subject = "Verify your email for Incentive Program";
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h3>Hi $full_name,</h3>
                <p>Thank you for registering for the <strong>Incentive Program</strong>.</p>
                <p>Please verify your email by clicking the link below:</p>
                <p><a href='$verification_link' style='color: #007bff;'>Verify My Email</a></p>
                <p>Best regards,<br>Incentive Program Team</p>
            </body>
            </html>";

            if (send_email($email, $subject, $body)) {
                $success_message = "Registration successful! Please check your email to verify your account.";

                // 🎉 Optional: Thank-you Email
                $thankyou_subject = "Welcome to the Incentive Program!";
                $thankyou_body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h3>Welcome, $full_name!</h3>
                    <p>We’re excited to have you join our Incentive Program.</p>
                    <p>We’ll keep you updated with the latest rewards and opportunities.</p>
                    <p>Best regards,<br>The Incentive Program Team</p>
                </body>
                </html>";
                send_email($email, $thankyou_subject, $thankyou_body);
            } else {
                $registration_error = "Registration successful, but failed to send verification email.";
            }
        } else {
            if ($conn->errno == 1062) {
                $registration_error = "Email or Username already exists.";
            } else {
                $registration_error = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// ===============================
// 🔹 Handle Login
// ===============================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password_hash, email_verified FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($user_id, $password_hash, $email_verified);
        $stmt->fetch();

        if (password_verify($password, $password_hash)) {
            if ($email_verified) {
                $_SESSION['user_id'] = $user_id;
                redirect('dashboard.php');
            } else {
                $login_error = "Please verify your email before logging in.";
            }
        } else {
            $login_error = "Invalid username or password.";
        }
    } else {
        $login_error = "Invalid username or password.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incentive Program - Register / Login</title>
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
            width: 100%;
            max-width: 600px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .form-section { flex: 1; min-width: 250px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background-color: #0056b3; }
        .error { color: red; text-align: center; margin-bottom: 10px; }
        .success { color: green; text-align: center; margin-bottom: 10px; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-section">
            <h2>Register</h2>
            <?php if ($registration_error): ?><p class="error"><?= $registration_error ?></p><?php endif; ?>
            <?php if ($success_message && isset($_POST['register'])): ?><p class="success"><?= $success_message ?></p><?php endif; ?>
            <form action="index.php" method="POST">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" required>
                <label for="email_reg">Email:</label>
                <input type="email" id="email_reg" name="email" required>
                <label for="username_reg">Username:</label>
                <input type="text" id="username_reg" name="username" required>
                <label for="password_reg">Password:</label>
                <input type="password" id="password_reg" name="password" required>
                <button type="submit" name="register">Register Now</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Login</h2>
            <?php if ($login_error): ?><p class="error"><?= $login_error ?></p><?php endif; ?>
            <?php if ($success_message && isset($_POST['login'])): ?><p class="success"><?= $success_message ?></p><?php endif; ?>
            <form action="index.php" method="POST">
                <label for="username_login">Username:</label>
                <input type="text" id="username_login" name="username" required>
                <label for="password_login">Password:</label>
                <input type="password" id="password_login" name="password" required>
                <button type="submit" name="login">Login</button>
            </form>
            <div class="links">
                <p>Admin? <a href="admin_login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
