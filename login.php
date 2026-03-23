<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
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
                setcookie('remember_token', $token, time() + 86400 * 30, '/');
            }
            
            $redirectUrl = $_SESSION['redirect_url'] ?? 'dashboard.php';
            unset($_SESSION['redirect_url']);
            redirect($redirectUrl);
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
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group remember-me">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Register Now</a></p>
                <div class="footer-links">
                    <a href="doctors.php">Find a Doctor</a>
                    <span class="separator">|</span>
                    <a href="services.php">Our Services</a>
                    <span class="separator">|</span>
                    <a href="contact.php">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.login-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 600px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.login-left {
    background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%);
    color: white;
    padding: 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-left h2 {
    font-size: 32px;
    margin-bottom: 20px;
}

.login-left p {
    line-height: 1.6;
    margin-bottom: 30px;
    opacity: 0.9;
}

.benefits {
    list-style: none;
    margin-top: 20px;
}

.benefits li {
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.benefits li i {
    width: 20px;
    font-size: 16px;
}

.login-links {
    margin-top: 40px;
}

.btn-outline-light {
    background: transparent;
    border: 2px solid white;
    color: white;
    display: inline-block;
    padding: 10px 30px;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-outline-light:hover {
    background: white;
    color: #1a75bc;
    transform: translateY(-2px);
}

.login-right {
    padding: 50px;
    background: white;
    display: flex;
    align-items: center;
}

.form-container {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
}

.form-container h2 {
    color: #1a75bc;
    text-align: center;
    margin-bottom: 30px;
    font-size: 28px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #1a75bc;
    box-shadow: 0 0 0 3px rgba(26,117,188,0.1);
}

.remember-me {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-weight: normal;
    color: #666;
}

.checkbox-label input {
    width: auto;
    cursor: pointer;
}

.checkbox-label span {
    font-size: 14px;
}

.forgot-link {
    color: #1a75bc;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.forgot-link:hover {
    text-decoration: underline;
    color: #0a5a9a;
}

.btn-block {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    margin-top: 10px;
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
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

.form-footer {
    text-align: center;
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #e9ecef;
}

.form-footer p {
    margin-bottom: 10px;
    color: #666;
}

.form-footer a {
    color: #1a75bc;
    text-decoration: none;
}

.form-footer a:hover {
    text-decoration: underline;
}

.footer-links {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.separator {
    color: #ddd;
}

@media (max-width: 768px) {
    .login-container {
        grid-template-columns: 1fr;
        margin: 20px;
    }
    
    .login-left {
        display: none;
    }
    
    .login-right {
        padding: 30px;
    }
    
    .form-container {
        max-width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>