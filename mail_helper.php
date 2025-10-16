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
