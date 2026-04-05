<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Forgot Password - HealthManagement";
include 'includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT userId, firstName, lastName, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Update user with reset token
            $stmt = $pdo->prepare("UPDATE users SET resetToken = ?, resetExpiry = ? WHERE userId = ?");
            $result = $stmt->execute([$resetToken, $resetExpiry, $user['userId']]);
            
            if ($result) {
                // Create reset link
                $resetLink = SITE_URL . "/reset-password.php?token=" . $resetToken;
                $subject = "Password Reset Request - " . SITE_NAME;
                
                // HTML email message
                $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <title>Password Reset Request</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                            .button { display: inline-block; background: #1a75bc; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; text-align: center; }
                            .link { background: #f4f4f4; padding: 10px; word-break: break-all; border-radius: 5px; font-family: monospace; margin: 15px 0; }
                            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #e9ecef; margin-top: 20px; }
                            .warning { color: #dc3545; font-size: 12px; margin-top: 15px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Password Reset Request</h2>
                            </div>
                            <div class='content'>
                                <p>Hello <strong>" . htmlspecialchars($user['firstName']) . " " . htmlspecialchars($user['lastName']) . "</strong>,</p>
                                <p>We received a request to reset your password for your " . SITE_NAME . " account.</p>
                                <p>Click the button below to create a new password:</p>
                                <p style='text-align: center;'>
                                    <a href='{$resetLink}' class='button'>Reset Password</a>
                                </p>
                                <p>Or copy and paste this link in your browser:</p>
                                <div class='link'>{$resetLink}</div>
                                <p><strong>Important:</strong> This link will expire in <strong>1 hour</strong>.</p>
                                <p>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                                <div class='warning'>
                                    <i class='fas fa-shield-alt'></i> For security reasons, do not share this link with anyone.
                                </div>
                            </div>
                            <div class='footer'>
                                <p>This is an automated message from " . SITE_NAME . ". Please do not reply to this email.</p>
                                <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                // Send email
                $emailSent = sendEmail($email, $subject, $message);
                
                if ($emailSent) {
                    $success = "Password reset instructions have been sent to your email address. Please check your inbox (and spam folder).";
                    logAction($user['userId'], 'PASSWORD_RESET_REQUEST', "Password reset requested for email: $email");
                } else {
                    $error = "Failed to send reset email. Please try again or contact support.";
                    error_log("Failed to send password reset email to: $email");
                }
            } else {
                $error = "Failed to process request. Please try again.";
            }
        } else {
            // Don't reveal if email exists or not for security
            $success = "If an account exists with that email address, password reset instructions have been sent.";
        }
    }
}
?>

<div class="form-container">
    <div class="form-header">
        <i class="fas fa-key"></i>
        <h2>Forgot Password</h2>
        <p>Enter your email address and we'll send you a link to reset your password.</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary">Back to Login</a>
        </div>
    <?php else: ?>
        <form method="POST" action="" id="forgot-form">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i>
                    Email Address
                </label>
                <input type="email" id="email" name="email" placeholder="Enter your registered email address" required>
                <small>We'll send a password reset link to this email address.</small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-paper-plane"></i> Send Reset Instructions
            </button>
            
            <div class="form-footer">
                <p><i class="fas fa-arrow-left"></i> <a href="<?php echo SITE_URL; ?>/login.php">Back to Login</a></p>
                <p>Don't have an account? <a href="<?php echo SITE_URL; ?>/register.php">Register Now</a></p>
            </div>
        </form>
    <?php endif; ?>
</div>


<script>
document.getElementById('forgot-form')?.addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailPattern.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
    }
});
</script>

<?php include 'includes/footer.php'; ?>