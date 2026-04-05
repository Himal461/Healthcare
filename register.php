<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Register - HealthManagement";
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phoneNumber = sanitizeInput($_POST['phone_number']);
    $role = 'patient';
    
    // Validation
    if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
        } else {
            // Create user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $verificationCode = generateVerificationCode();
            
            $pdo->beginTransaction();
            
            try {
                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, verificationCode, dateCreated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $phoneNumber, $role, $verificationCode]);
                
                $userId = $pdo->lastInsertId();
                
                // Create patient record
                $dateOfBirth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                $address = sanitizeInput($_POST['address'] ?? '');
                $bloodType = $_POST['blood_type'] ?? null;
                $knownAllergies = sanitizeInput($_POST['known_allergies'] ?? '');
                $insuranceProvider = sanitizeInput($_POST['insurance_provider'] ?? '');
                $insuranceNumber = sanitizeInput($_POST['insurance_number'] ?? '');
                $emergencyContactName = sanitizeInput($_POST['emergency_contact_name'] ?? '');
                $emergencyContactPhone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
                
                $stmt = $pdo->prepare("INSERT INTO patients (userId, dateOfBirth, address, bloodType, knownAllergies, insuranceProvider, insuranceNumber, emergencyContactName, emergencyContactPhone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $dateOfBirth, $address, $bloodType, $knownAllergies, $insuranceProvider, $insuranceNumber, $emergencyContactName, $emergencyContactPhone]);
                
                // Send verification email
                $verificationLink = SITE_URL . "/verify.php?code=" . $verificationCode;
                $subject = "Verify Your Email - " . SITE_NAME;
                $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <title>Email Verification</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                            .button { display: inline-block; background: #1a75bc; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Welcome to " . SITE_NAME . "!</h2>
                            </div>
                            <div class='content'>
                                <p>Dear <strong>{$firstName} {$lastName}</strong>,</p>
                                <p>Thank you for registering with us. Please verify your email address to complete your registration.</p>
                                <p style='text-align: center;'>
                                    <a href='{$verificationLink}' class='button'>Verify Email Address</a>
                                </p>
                                <p>Or copy and paste this link: <br><code>{$verificationLink}</code></p>
                                <p>If you didn't create an account, please ignore this email.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " " . SITE_NAME . "</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                $emailSent = sendEmail($email, $subject, $message);
                
                if ($emailSent) {
                    $pdo->commit();
                    $_SESSION['success'] = "Registration successful! Please check your email to verify your account.";
                    redirect('login.php');
                } else {
                    throw new Exception("Failed to send verification email.");
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="form-container">
    <h2>Create Your Account</h2>
    <p>Join our healthcare community and take control of your health</p>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" action="" id="register-form">
        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name *</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="last_name">Last Name *</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="username">Username *</label>
            <input type="text" id="username" name="username" value="<?php echo $_POST['username'] ?? ''; ?>" required>
            <small>Used for login (must be unique)</small>
        </div>
        
        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
        </div>
        
        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo $_POST['phone_number'] ?? ''; ?>">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
                <small>Minimum 8 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $_POST['date_of_birth'] ?? ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3"><?php echo $_POST['address'] ?? ''; ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="blood_type">Blood Type</label>
                <select id="blood_type" name="blood_type">
                    <option value="">Select Blood Type</option>
                    <?php
                    $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                    foreach ($bloodTypes as $type):
                    ?>
                        <option value="<?php echo $type; ?>" <?php echo ($_POST['blood_type'] ?? '') === $type ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="known_allergies">Known Allergies</label>
                <input type="text" id="known_allergies" name="known_allergies" value="<?php echo $_POST['known_allergies'] ?? ''; ?>" placeholder="e.g., Penicillin, Peanuts">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="insurance_provider">Insurance Provider</label>
                <input type="text" id="insurance_provider" name="insurance_provider" value="<?php echo $_POST['insurance_provider'] ?? ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="insurance_number">Insurance Number</label>
                <input type="text" id="insurance_number" name="insurance_number" value="<?php echo $_POST['insurance_number'] ?? ''; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="emergency_contact_name">Emergency Contact Name</label>
                <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo $_POST['emergency_contact_name'] ?? ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo $_POST['emergency_contact_phone'] ?? ''; ?>">
            </div>
        </div>
        
        <div class="terms-group">
            <label class="terms-label">
                <input type="checkbox" name="terms" required>
                <span>I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a></span>
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">Register</button>
        
        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Login Here</a></p>
            <p><a href="services.php">Learn about our services</a> | <a href="doctors.php">Find a doctor</a></p>
        </div>
    </form>
</div>

<script>
document.getElementById('register-form')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const termsCheckbox = document.querySelector('input[name="terms"]');
    
    if (!termsCheckbox.checked) {
        e.preventDefault();
        alert('Please agree to the Terms of Service and Privacy Policy');
        return;
    }
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match');
    } else if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
    }
});
</script>

<?php include 'includes/footer.php'; ?>