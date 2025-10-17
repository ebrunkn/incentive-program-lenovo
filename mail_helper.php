<?php
include_once __DIR__ . '/env.php';
// Mailgun configuration from env.php with empty defaults
define('MAILGUN_API_KEY', isset($MAILGUN_API_KEY) ? $MAILGUN_API_KEY : '');
define('MAILGUN_DOMAIN', isset($MAILGUN_DOMAIN) ? $MAILGUN_DOMAIN : ''); // e.g., mg.yourdomain.com
define('MAILGUN_FROM', isset($MAILGUN_FROM_EMAIL) ? $MAILGUN_FROM_EMAIL : '');

function send_email($to, $subject, $body)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, 'api:' . MAILGUN_API_KEY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_URL, 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages');
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'from'    => MAILGUN_FROM,
        'to'      => $to,
        'subject' => $subject,
        'html'    => $body
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}

// Email template for submission approval
function get_approval_email_template($user_name, $submission_id, $incentive_amount, $admin_notes = '') {
    $formatted_amount = number_format($incentive_amount, 2);
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .success-badge { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; text-align: center; margin: 20px 0; font-weight: bold; }
            .amount-highlight { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; text-align: center; font-size: 18px; font-weight: bold; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Submission Approved!</h1>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($user_name) . ",</p>
                
                <div class='success-badge'>
                    ✅ Your submission #{$submission_id} has been approved!
                </div>
                
                <p>Great news! Your submission has been reviewed and approved by our team.</p>
                
                <div class='amount-highlight'>
                    💰 Incentive Amount: $" . $formatted_amount . "
                </div>
                
                <p>You will receive your incentive payment according to our payment schedule. If you have any questions about your payment, please don't hesitate to contact us.</p>";
                
    if (!empty($admin_notes)) {
        $template .= "
                <div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <strong>Admin Notes:</strong><br>
                    " . nl2br(htmlspecialchars($admin_notes)) . "
                </div>";
    }
    
    $template .= "
                <p>Thank you for participating in our incentive program!</p>
                
                <div class='footer'>
                    <p>Best regards,<br>Incentive Program Team</p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return $template;
}

// Email template for submission rejection
function get_rejection_email_template($user_name, $submission_id, $admin_notes = '') {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .rejection-badge { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; text-align: center; margin: 20px 0; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📋 Submission Update</h1>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($user_name) . ",</p>
                
                <div class='rejection-badge'>
                    ❌ Your submission #{$submission_id} has been reviewed
                </div>
                
                <p>We have reviewed your submission, but unfortunately it does not meet our current requirements for approval.</p>";
                
    if (!empty($admin_notes)) {
        $template .= "
                <div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <strong>Review Notes:</strong><br>
                    " . nl2br(htmlspecialchars($admin_notes)) . "
                </div>";
    }
    
    $template .= "
                <p>Please feel free to submit new documentation that meets our requirements. We encourage you to review our submission guidelines and try again.</p>
                
                <p>If you have any questions about this decision or need clarification on our requirements, please don't hesitate to contact us.</p>
                
                <div class='footer'>
                    <p>Best regards,<br>Incentive Program Team</p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return $template;
}

// Function to send submission status notification
function send_submission_status_notification($user_email, $user_name, $submission_id, $action, $incentive_amount = null, $admin_notes = '') {
    if ($action === 'approve') {
        $subject = "✅ Submission #{$submission_id} Approved - Incentive Program";
        $body = get_approval_email_template($user_name, $submission_id, $incentive_amount, $admin_notes);
    } elseif ($action === 'reject') {
        $subject = "📋 Submission #{$submission_id} Update - Incentive Program";
        $body = get_rejection_email_template($user_name, $submission_id, $admin_notes);
    } else {
        return false;
    }
    
    return send_email($user_email, $subject, $body);
}
