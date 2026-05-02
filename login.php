<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Login - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['passwordHash'])) {
        if ($user['isVerified'] || !VERIFICATION_REQUIRED) {
            // Clear old session
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['userId'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET lastLogin = NOW() WHERE userId = ?");
                $stmt->execute([$user['userId']]);
            } catch (Exception $e) {
                error_log("Failed to update last login: " . $e->getMessage());
            }
            
            logAction($user['userId'], 'LOGIN', "User logged in successfully as {$user['role']}");
            
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE users SET resetToken = ? WHERE userId = ?");
                $stmt->execute([$token, $user['userId']]);
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
            }
            
            $redirectUrl = $_SESSION['redirect_url'] ?? '';
            unset($_SESSION['redirect_url']);
            
            if ($redirectUrl) {
                redirect($redirectUrl);
            } else {
                // Role-based redirect
                switch ($user['role']) {
                    case 'admin':
                        redirect('admin/dashboard.php');
                        break;
                    case 'doctor':
                        redirect('doctor/dashboard.php');
                        break;
                    case 'nurse':
                        redirect('nurse/dashboard.php');
                        break;
                    case 'staff':
                        redirect('staff/dashboard.php');
                        break;
                    case 'accountant':
                        redirect('accountant/dashboard.php');
                        break;
                    case 'patient':
                        redirect('patient/dashboard.php');
                        break;
                    default:
                        redirect('index.php');
                        break;
                }
            }
        } else {
            $error = "Please verify your email before logging in.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}

$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>


<div class="root-split-container">
    <div class="root-split-left">
        <h2>Welcome Back</h2>
        <p>Access your personal health dashboard, manage appointments, and connect with healthcare providers.</p>
        <ul class="root-benefits-list">
            <li><i class="fas fa-calendar-check"></i> Manage your appointments</li>
            <li><i class="fas fa-file-medical"></i> Access your medical records</li>
            <li><i class="fas fa-comments"></i> Message your healthcare providers</li>
            <li><i class="fas fa-prescription"></i> Request prescription refills</li>
        </ul>
        <div>
            <a href="register.php" class="root-btn root-btn-outline" style="border-color: white; color: white;">Create Account</a>
        </div>
    </div>
    
    <div class="root-split-right">
        <div class="root-form-container">
            <div class="root-form-header">
                <i class="fas fa-sign-in-alt"></i>
                <h2>Login to Your Account</h2>
                <p>Enter your credentials to continue</p>
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
            <?php endif; ?>
            
            <form method="POST" id="login-form">
                <div class="root-form-group">
                    <label for="username"><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" id="username" name="username" class="root-form-control" required autofocus>
                </div>
                
                <div class="root-form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="root-form-control" required>
                </div>
                
                <div class="root-remember-me">
                    <label>
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="forget-password.php" class="root-forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="root-btn root-btn-primary">Login</button>
            </form>
            
            <div class="root-form-footer">
                <p>Don't have an account? <a href="register.php">Register Now</a></p>
                <p>
                    <a href="doctors.php">Find a Doctor</a> | 
                    <a href="services.php">Our Services</a> | 
                    <a href="contact.php">Contact Us</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('pageshow', function(event) {
    if (event.persisted) window.location.reload();
});
history.pushState(null, null, location.href);
window.addEventListener('popstate', function () {
    history.pushState(null, null, location.href);
});
</script>

<?php include 'includes/footer.php'; ?>