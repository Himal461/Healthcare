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

<style>
.form-container {
    max-width: 500px;
    margin: 50px auto;
    padding: 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    text-align: center;
}

.form-container h2 {
    color: #1a75bc;
    margin-bottom: 20px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
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

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    margin-top: 10px;
}

.btn-primary {
    background: #1a75bc;
    color: white;
}

.btn-primary:hover {
    background: #0a5a9a;
}
</style>

<?php include 'includes/footer.php'; ?>