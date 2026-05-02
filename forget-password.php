<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Forgot Password - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
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
        $stmt = $pdo->prepare("SELECT userId, firstName, lastName, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $resetToken = bin2hex(random_bytes(32));
            $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("UPDATE users SET resetToken = ?, resetExpiry = ? WHERE userId = ?");
            $stmt->execute([$resetToken, $resetExpiry, $user['userId']]);
            
            $resetLink = SITE_URL . "/reset-password.php?token=" . $resetToken;
            $subject = "Password Reset Request - " . SITE_NAME;
            
            $message = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #1e3a5f 0%, #0f2440 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #1e3a5f; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                        .link { background: #f4f4f4; padding: 10px; word-break: break-all; border-radius: 5px; font-family: monospace; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'><h2>Password Reset Request</h2></div>
                        <div class='content'>
                            <p>Hello <strong>" . htmlspecialchars($user['firstName']) . " " . htmlspecialchars($user['lastName']) . "</strong>,</p>
                            <p>We received a request to reset your password.</p>
                            <p style='text-align: center;'><a href='{$resetLink}' class='button'>Reset Password</a></p>
                            <p>Or copy and paste this link:</p>
                            <div class='link'>{$resetLink}</div>
                            <p><strong>Important:</strong> This link will expire in <strong>1 hour</strong>.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $emailSent = sendEmail($email, $subject, $message);
            
            if ($emailSent) {
                $success = "Password reset instructions have been sent to your email address.";
                logAction($user['userId'], 'PASSWORD_RESET_REQUEST', "Password reset requested for email: $email");
            } else {
                $error = "Failed to send reset email. Please try again.";
            }
        } else {
            $success = "If an account exists with that email address, password reset instructions have been sent.";
        }
    }
}
?>

<div class="root-container">
    <div class="root-form-container">
        <div class="root-form-header">
            <i class="fas fa-key"></i>
            <h2>Forgot Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="root-alert root-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="root-alert root-alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="root-btn root-btn-primary">Back to Login</a>
            </div>
        <?php else: ?>
            <form method="POST" id="forgot-form">
                <div class="root-form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="email" name="email" class="root-form-control" placeholder="Enter your registered email address" required>
                    <small style="color: #64748b;">We'll send a password reset link to this email address.</small>
                </div>
                <button type="submit" class="root-btn root-btn-primary root-btn-block"><i class="fas fa-paper-plane"></i> Send Reset Instructions</button>
            </form>
            
            <div class="root-form-footer">
                <p><i class="fas fa-arrow-left"></i> <a href="login.php">Back to Login</a></p>
                <p>Don't have an account? <a href="register.php">Register Now</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>