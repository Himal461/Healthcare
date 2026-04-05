<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Verify Email - HealthManagement";
include 'includes/header.php';

$code = $_GET['code'] ?? '';

if (empty($code)) {
    $error = "Invalid verification code.";
} else {
    // Check if verification code exists
    $stmt = $pdo->prepare("SELECT userId FROM users WHERE verificationCode = ?");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Invalid or expired verification code.";
    } else {
        // Verify the user
        $stmt = $pdo->prepare("UPDATE users SET isVerified = 1, verificationCode = NULL WHERE verificationCode = ?");
        $stmt->execute([$code]);
        
        $success = "Email verified successfully! You can now login to your account.";
        logAction($user['userId'], 'EMAIL_VERIFY', "User verified their email");
    }
}
?>

<div class="form-container">
    <h2>Email Verification</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <p>Please try registering again or contact support.</p>
            <a href="register.php" class="btn btn-primary">Register Again</a>
        </div>
    <?php elseif (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" class="btn btn-primary">Login Now</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>