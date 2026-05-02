<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Verify Email - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
include 'includes/header.php';

$code = $_GET['code'] ?? '';
$error = '';
$success = '';

if (empty($code)) {
    $error = "Invalid verification code.";
} else {
    $stmt = $pdo->prepare("SELECT userId FROM users WHERE verificationCode = ?");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Invalid or expired verification code.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET isVerified = 1, verificationCode = NULL WHERE verificationCode = ?");
        $stmt->execute([$code]);
        
        $success = "Email verified successfully! You can now login to your account.";
        logAction($user['userId'], 'EMAIL_VERIFY', "User verified their email");
    }
}
?>

<div class="root-container">
    <div class="root-form-container">
        <div class="root-form-header">
            <i class="fas fa-envelope-open-text"></i>
            <h2>Email Verification</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="root-alert root-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <p>Please try registering again or contact support.</p>
                <a href="register.php" class="root-btn root-btn-primary">Register Again</a>
            </div>
        <?php elseif ($success): ?>
            <div class="root-alert root-alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="root-btn root-btn-primary">Login Now</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>