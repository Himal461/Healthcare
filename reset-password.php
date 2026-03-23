<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Reset Password - HealthManagement";
include 'includes/header.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Invalid reset token.";
} else {
    // Check if token is valid and not expired
    $stmt = $pdo->prepare("SELECT userId FROM users WHERE resetToken = ? AND resetExpiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Invalid or expired reset token.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET passwordHash = ?, resetToken = NULL, resetExpiry = NULL WHERE resetToken = ?");
        $stmt->execute([$newPasswordHash, $token]);
        
        $_SESSION['success'] = "Password reset successfully! You can now login with your new password.";
        logAction($user['userId'], 'PASSWORD_RESET', "User reset their password via email");
        
        redirect('login.php');
    }
}
?>

<div class="form-container">
    <h2 style="color: #1a75bc; text-align: center; margin-bottom: 30px;">Reset Password</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="new_password">New Password *</label>
                <input type="password" id="new_password" name="new_password" required>
                <small style="color: #666;">Password must be at least 8 characters long</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Reset Password</button>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>