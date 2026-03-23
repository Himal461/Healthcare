<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Forgot Password - HealthManagement";
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
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
        $stmt->execute([$resetToken, $resetExpiry, $user['userId']]);
        
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
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        margin: 0;
                        padding: 0;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .header {
                        background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%);
                        color: white;
                        padding: 30px;
                        text-align: center;
                        border-radius: 10px 10px 0 0;
                    }
                    .header h2 {
                        margin: 0;
                        font-size: 24px;
                    }
                    .content {
                        background: #f9f9f9;
                        padding: 30px;
                        border-radius: 0 0 10px 10px;
                    }
                    .button {
                        display: inline-block;
                        background: #1a75bc;
                        color: white;
                        padding: 12px 30px;
                        text-decoration: none;
                        border-radius: 5px;
                        margin: 20px 0;
                        font-weight: bold;
                        text-align: center;
                    }
                    .button:hover {
                        background: #0a5a9a;
                    }
                    .link {
                        background: #f4f4f4;
                        padding: 10px;
                        word-break: break-all;
                        border-radius: 5px;
                        font-family: monospace;
                        margin: 15px 0;
                    }
                    .footer {
                        text-align: center;
                        padding: 20px;
                        color: #666;
                        font-size: 12px;
                        border-top: 1px solid #e9ecef;
                        margin-top: 20px;
                    }
                    .warning {
                        color: #dc3545;
                        font-size: 12px;
                        margin-top: 15px;
                    }
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
        
        // Plain text alternative
        $altMessage = "
            Password Reset Request - " . SITE_NAME . "\n\n"
            . "Hello " . $user['firstName'] . " " . $user['lastName'] . ",\n\n"
            . "We received a request to reset your password.\n\n"
            . "Click the link below to reset your password:\n"
            . $resetLink . "\n\n"
            . "This link will expire in 1 hour.\n\n"
            . "If you didn't request this, please ignore this email.\n\n"
            . SITE_NAME;
        
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
        // Don't reveal if email exists or not for security
        $success = "If an account exists with that email address, password reset instructions have been sent.";
    }
}
?>

<div class="form-container">
    <div class="form-header">
        <i class="fas fa-key"></i>
        <h2>Forgot Password</h2>
        <p>Enter your email address and we'll send you a link to reset your password.</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>
    
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
            <p><i class="fas fa-arrow-left"></i> <a href="login.php">Back to Login</a></p>
            <p>Don't have an account? <a href="register.php">Register Now</a></p>
        </div>
    </form>
</div>

<style>
.form-container {
    max-width: 500px;
    margin: 50px auto;
    padding: 40px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.form-header {
    text-align: center;
    margin-bottom: 30px;
}

.form-header i {
    font-size: 48px;
    color: #1a75bc;
    margin-bottom: 15px;
}

.form-header h2 {
    color: #1a75bc;
    font-size: 28px;
    margin-bottom: 10px;
}

.form-header p {
    color: #666;
    font-size: 14px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-group label i {
    margin-right: 8px;
    color: #1a75bc;
}

.form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #1a75bc;
    box-shadow: 0 0 0 3px rgba(26,117,188,0.1);
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.btn-block {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert i {
    font-size: 18px;
}

.form-footer {
    text-align: center;
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #e9ecef;
}

.form-footer p {
    margin-bottom: 10px;
    color: #666;
}

.form-footer a {
    color: #1a75bc;
    text-decoration: none;
}

.form-footer a:hover {
    text-decoration: underline;
}

.form-footer i {
    margin-right: 5px;
}

@media (max-width: 768px) {
    .form-container {
        margin: 20px;
        padding: 25px;
    }
    
    .form-header h2 {
        font-size: 24px;
    }
}
</style>

<script>
document.getElementById('forgot-form').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailPattern.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
    }
});
</script>

<?php include 'includes/footer.php'; ?>