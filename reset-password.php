<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Reset Password - HealthManagement";
include 'includes/header.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Debug output (remove in production)
if (!empty($token)) {
    error_log("Reset token received: " . $token);
}

if (empty($token)) {
    $error = "Invalid reset token. Please request a new password reset link.";
} else {
    // Check if token is valid and not expired
    $stmt = $pdo->prepare("SELECT userId, firstName, lastName, email, resetToken, resetExpiry FROM users WHERE resetToken = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    // Debug output
    if ($user) {
        error_log("User found: " . $user['email']);
        error_log("Token expiry: " . $user['resetExpiry']);
        error_log("Current time: " . date('Y-m-d H:i:s'));
        
        if (strtotime($user['resetExpiry']) < time()) {
            $error = "Your reset link has expired. Please request a new password reset link.";
            error_log("Token expired for user: " . $user['email']);
        }
    } else {
        error_log("No user found with token: " . $token);
        $error = "Invalid or expired reset token. Please request a new password reset link.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && isset($user)) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate password
    if ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = "Password must contain at least one number.";
    } else {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $pdo->prepare("UPDATE users SET passwordHash = ?, resetToken = NULL, resetExpiry = NULL WHERE userId = ?");
        $result = $stmt->execute([$newPasswordHash, $user['userId']]);
        
        if ($result) {
            // Log the password reset
            logAction($user['userId'], 'PASSWORD_RESET', "User reset their password via email");
            
            // Send confirmation email
            $subject = "Password Changed Successfully - " . SITE_NAME;
            $message = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>Password Changed</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #1a75bc; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Password Changed Successfully</h2>
                        </div>
                        <div class='content'>
                            <p>Hello <strong>" . htmlspecialchars($user['firstName']) . " " . htmlspecialchars($user['lastName']) . "</strong>,</p>
                            <p>Your password has been successfully changed.</p>
                            <p>If you did not make this change, please contact us immediately.</p>
                            <p style='text-align: center;'>
                                <a href='" . SITE_URL . "/login.php' class='button'>Login to Your Account</a>
                            </p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            sendEmail($user['email'], $subject, $message);
            
            $_SESSION['success'] = "Password reset successfully! You can now login with your new password.";
            redirect('login.php');
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>

<div class="form-container">
    <div class="form-header">
        <i class="fas fa-lock"></i>
        <h2>Reset Password</h2>
        <p>Create a new secure password for your account</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php if (strpos($error, 'Invalid') !== false || strpos($error, 'expired') !== false): ?>
            <div class="reset-actions">
                <a href="<?php echo SITE_URL; ?>/forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline">Back to Login</a>
            </div>
        <?php endif; ?>
    <?php elseif (!$error && isset($user) && !$success): ?>
        <form method="POST" action="" id="reset-form">
            <div class="form-group">
                <label for="new_password">
                    <i class="fas fa-key"></i>
                    New Password
                </label>
                <input type="password" id="new_password" name="new_password" required>
                <small>Password must be at least 8 characters with uppercase, lowercase, and numbers.</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">
                    <i class="fas fa-check-circle"></i>
                    Confirm New Password
                </label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="password-strength">
                <div class="strength-bar"></div>
                <span class="strength-text"></span>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save"></i> Reset Password
            </button>
            
            <div class="form-footer">
                <p><i class="fas fa-arrow-left"></i> <a href="<?php echo SITE_URL; ?>/login.php">Back to Login</a></p>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
document.getElementById('reset-form')?.addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match');
    } else if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
    } else if (!/[A-Z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one uppercase letter');
    } else if (!/[a-z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one lowercase letter');
    } else if (!/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one number');
    }
});

// Password strength meter
const passwordInput = document.getElementById('new_password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.querySelector('.strength-bar');
        const strengthText = document.querySelector('.strength-text');
        
        if (!strengthBar || !strengthText) return;
        
        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        const width = (strength / 5) * 100;
        strengthBar.style.width = width + '%';
        
        if (strength <= 2) {
            strengthBar.style.background = '#dc3545';
            strengthText.textContent = 'Weak password';
            strengthText.style.color = '#dc3545';
        } else if (strength <= 4) {
            strengthBar.style.background = '#ffc107';
            strengthText.textContent = 'Medium password';
            strengthText.style.color = '#ffc107';
        } else {
            strengthBar.style.background = '#28a745';
            strengthText.textContent = 'Strong password';
            strengthText.style.color = '#28a745';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>