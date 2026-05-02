<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$pageTitle = "Change Password - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$userId = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT passwordHash FROM users WHERE userId = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!password_verify($current, $user['passwordHash'])) {
        $error = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET passwordHash = ? WHERE userId = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        $_SESSION['success'] = "Password changed successfully!";
        logAction($userId, 'PASSWORD_CHANGE', "Changed password");
        header("Location: profile.php");
        exit();
    }
}
?>

<div class="root-container">
    <div class="root-page-header">
        <div class="header-title">
            <h1><i class="fas fa-key"></i> Change Password</h1>
            <p>Update your account password</p>
        </div>
        <div class="header-actions">
            <a href="profile.php" class="root-btn root-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
        </div>
    </div>

    <div class="root-form-container">
        <?php if ($error): ?>
            <div class="root-alert root-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="root-form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="root-form-control" required>
            </div>
            <div class="root-form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="root-form-control" required>
                <small style="color: #64748b;">Minimum 8 characters</small>
            </div>
            <div class="root-form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="root-form-control" required>
            </div>
            <button type="submit" class="root-btn root-btn-primary root-btn-block">Change Password</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>