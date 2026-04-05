<?php
// Make sure we're in the correct directory
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Prevent caching of login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (isLoggedIn()) {
    // Redirect based on role if already logged in
    if ($_SESSION['user_role'] === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'doctor') {
        redirect('doctor/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'nurse') {
        redirect('nurse/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'staff') {
        redirect('staff/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'patient') {
        redirect('patient/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

$pageTitle = "Login - HealthManagement";
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['passwordHash'])) {
        if ($user['isVerified'] || !VERIFICATION_REQUIRED) {
            loginUser($user['userId'], $user['username'], $user['role']);
            
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE userId = ?");
                $stmt->execute([$token, $user['userId']]);
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
            }
            
            // Redirect based on user role
            $redirectUrl = $_SESSION['redirect_url'] ?? '';
            unset($_SESSION['redirect_url']);
            
            if ($redirectUrl) {
                redirect($redirectUrl);
            } else {
                // Redirect to appropriate dashboard based on role
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($user['role'] === 'doctor') {
                    redirect('doctor/dashboard.php');
                } elseif ($user['role'] === 'nurse') {
                    redirect('nurse/dashboard.php');
                } elseif ($user['role'] === 'staff') {
                    redirect('staff/dashboard.php');
                } elseif ($user['role'] === 'patient') {
                    redirect('patient/dashboard.php');
                } else {
                    redirect('dashboard.php');
                }
            }
        } else {
            $error = "Please verify your email before logging in.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<div class="login-container">
    <div class="login-left">
        <h2>Welcome Back</h2>
        <p>Access your personal health dashboard, manage appointments, and connect with healthcare providers.</p>
        <ul class="benefits">
            <li><i class="fas fa-calendar-check"></i> Manage your appointments</li>
            <li><i class="fas fa-file-medical"></i> Access your medical records</li>
            <li><i class="fas fa-comments"></i> Message your healthcare providers</li>
            <li><i class="fas fa-prescription"></i> Request prescription refills</li>
        </ul>
        <div class="login-links">
            <a href="register.php" class="btn btn-outline-light">Create Account</a>
        </div>
    </div>
    
    <div class="login-right">
        <div class="form-container">
            <h2>Login to Your Account</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="login-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="remember-me">
                    <label>
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Register Now</a></p>
                <p><a href="doctors.php">Find a Doctor</a> | <a href="services.php">Our Services</a> | <a href="contact.php">Contact Us</a></p>
            </div>
        </div>
    </div>
</div>


<script>
// Prevent browser from caching the login page
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});

// Disable browser back button after logout
history.pushState(null, null, location.href);
window.addEventListener('popstate', function () {
    history.pushState(null, null, location.href);
});
</script>

<?php include 'includes/footer.php'; ?>