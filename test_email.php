<?php
require_once 'includes/config.php';

$to = "himalkumarkari@gmail.com";
$subject = "Test Email from Healthcare System";
$message = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a75bc; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Email Test</h2>
        </div>
        <div class='content'>
            <p>If you're reading this, your email configuration is working correctly!</p>
            <p>SMTP Settings used:</p>
            <ul>
                <li>Host: " . SMTP_HOST . "</li>
                <li>Port: " . SMTP_PORT . "</li>
                <li>Username: " . SMTP_USERNAME . "</li>
                <li>From: " . SMTP_FROM_NAME . "</li>
            </ul>
        </div>
    </div>
</body>
</html>
";

echo "Sending test email to: " . $to . "<br>";
$result = sendEmail($to, $subject, $message);

if ($result) {
    echo "<div style='color: green;'>✅ Email sent successfully! Check your inbox (and spam folder).</div>";
} else {
    echo "<div style='color: red;'>❌ Failed to send email. Please check your SMTP settings.</div>";
    echo "<div style='color: orange;'>Make sure:</div>";
    echo "<ul>";
    echo "<li>Your internet connection is working</li>";
    echo "<li>You have the PHPMailer files in the correct location (PHPMailer/ folder)</li>";
    echo "<li>Your Gmail account has 2-Step Verification enabled and you're using an App Password</li>";
    echo "<li>Your App Password is correct: zzzy qqwx dawy itxv</li>";
    echo "</ul>";
}
?>