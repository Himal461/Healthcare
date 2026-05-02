<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Reset Password - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (empty($token)) {
    $error = "Invalid reset token. Please request a new password reset link.";
} else {
    $stmt = $pdo->prepare("SELECT userId, firstName, lastName, email, resetToken, resetExpiry FROM users WHERE resetToken = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Invalid reset token. Please request a new password reset link.";
    } elseif (strtotime($user['resetExpiry']) < time()) {
        $error = "Your reset link has expired. Please request a new password reset link.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && isset($user)) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET passwordHash = ?, resetToken = NULL, resetExpiry = NULL WHERE userId = ?");
        $stmt->execute([$newPasswordHash, $user['userId']]);
        
        logAction($user['userId'], 'PASSWORD_RESET', "User reset their password via email");
        
        $subject = "Password Changed Successfully - " . SITE_NAME;
        $message = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body>
                <div style='max-width:600px;margin:0 auto;padding:20px;'>
                    <h2>Password Changed Successfully</h2>
                    <p>Hello <strong>" . htmlspecialchars($user['firstName']) . " " . htmlspecialchars($user['lastName']) . "</strong>,</p>
                    <p>Your password has been successfully changed.</p>
                    <p><a href='" . SITE_URL . "/login.php'>Login to Your Account</a></p>
                </div>
            </body>
            </html>
        ";
        sendEmail($user['email'], $subject, $message);
        
        $_SESSION['success'] = "Password reset successfully! You can now login with your new password.";
        redirect('login.php');
    }
}
?>

<div class="root-container">
    <div class="root-form-container">
        <div class="root-form-header">
            <i class="fas fa-lock"></i>
            <h2>Reset Password</h2>
            <p>Create a new secure password for your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="root-alert root-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php if (strpos($error, 'Invalid') !== false || strpos($error, 'expired') !== false): ?>
                <div class="root-reset-actions">
                    <a href="forget-password.php" class="root-btn root-btn-primary">Request New Reset Link</a>
                    <a href="login.php" class="root-btn root-btn-outline">Back to Login</a>
                </div>
            <?php endif; ?>
        <?php elseif (!$error && isset($user)): ?>
            <form method="POST" id="reset-form">
                <div class="root-form-group">
                    <label for="new_password"><i class="fas fa-key"></i> New Password</label>
                    <input type="password" id="new_password" name="new_password" class="root-form-control" required>
                    <small style="color: #64748b;">Password must be at least 8 characters</small>
                </div>
                <div class="root-form-group">
                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="root-form-control" required>
                </div>
                <button type="submit" class="root-btn root-btn-primary root-btn-block"><i class="fas fa-save"></i> Reset Password</button>
            </form>
            
            <div class="root-form-footer">
                <p><i class="fas fa-arrow-left"></i> <a href="login.php">Back to Login</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>