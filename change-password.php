<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$pageTitle = "Change Password - HealthManagement";
include 'includes/header.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Get current password hash
    $stmt = $pdo->prepare("SELECT passwordHash FROM users WHERE userId = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!password_verify($currentPassword, $user['passwordHash'])) {
        $error = "Current password is incorrect.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET passwordHash = ? WHERE userId = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        $_SESSION['success'] = "Password changed successfully!";
        logAction($userId, 'PASSWORD_CHANGE', "User changed their password");
        
        header("Location: profile.php");
        exit();
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Change Password</h1>
        <p>Update your account password</p>
    </div>

    <div class="form-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">Current Password *</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password *</label>
                <input type="password" id="new_password" name="new_password" required>
                <small style="color: #666;">Password must be at least 8 characters long</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Change Password</button>
            <a href="profile.php" class="btn" style="background: #6c757d; color: white; margin-left: 10px;">Cancel</a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>