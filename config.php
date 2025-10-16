<?php
// config.php

include_once __DIR__ . '/env.php';
include_once 'mail_helper.php';

// Database credentials
// define('DB_SERVER', 'localhost');
// define('DB_USERNAME', 'u723857567_lenovo'); // Your MySQL username
// define('DB_PASSWORD', 'Poojam12'); // Your MySQL password
// define('DB_NAME', 'u723857567_incentive_prog'); // The database name you created

define('DB_SERVER', isset($DB_SERVER) ? $DB_SERVER : 'db');
define('DB_USERNAME', isset($DB_USERNAME) ? $DB_USERNAME : 'root'); // Your MySQL username
define('DB_PASSWORD', isset($DB_PASSWORD) ? $DB_PASSWORD : 'root'); // Your MySQL password
define('DB_NAME', isset($DB_NAME) ? $DB_NAME : 'pam_db'); // The database name you created

// Base URL for the application (for email verification links)
define('BASE_URL', isset($BASE_URL) ? $BASE_URL : 'http://techprojects.online'); // Change to your domain/path


// Establish database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start(); // Start session for user login/status

// --- Helper Functions ---

// Placeholder for sending emails (integrate with real service like PHPMailer or Mailgun)
// function send_email($to, $subject, $body) {
//     // In a real application, you'd use a library like PHPMailer or
//     // an API from a service like Mailgun/SendGrid here.
//     // For now, we'll just simulate it.
//     // file_put_contents('emails.log', "To: $to\nSubject: $subject\nBody: $body\n\n", FILE_APPEND);
//     return true; // Assume email sent successfully
// }


// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Function to check if a user is logged in
function is_user_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if an admin is logged in
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

// Function to check admin role
function get_admin_role() {
    return $_SESSION['admin_role'] ?? null;
}
?>