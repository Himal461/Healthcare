<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$pageTitle = "Register - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phoneNumber = sanitizeInput($_POST['phone_number']);
    $role = 'patient';
    
    if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $verificationCode = generateVerificationCode();
            
            $pdo->beginTransaction();
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, verificationCode, dateCreated) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $phoneNumber, $role, $verificationCode]);
                $userId = $pdo->lastInsertId();
                
                $dateOfBirth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                $address = sanitizeInput($_POST['address'] ?? '');
                $bloodType = $_POST['blood_type'] ?? null;
                $knownAllergies = sanitizeInput($_POST['known_allergies'] ?? '');
                $insuranceProvider = sanitizeInput($_POST['insurance_provider'] ?? '');
                $insuranceNumber = sanitizeInput($_POST['insurance_number'] ?? '');
                $emergencyContactName = sanitizeInput($_POST['emergency_contact_name'] ?? '');
                $emergencyContactPhone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
                
                $stmt = $pdo->prepare("
                    INSERT INTO patients (userId, dateOfBirth, address, bloodType, knownAllergies, insuranceProvider, insuranceNumber, emergencyContactName, emergencyContactPhone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $dateOfBirth, $address, $bloodType, $knownAllergies, $insuranceProvider, $insuranceNumber, $emergencyContactName, $emergencyContactPhone]);
                
                $verificationLink = SITE_URL . "/verify.php?code=" . $verificationCode;
                $subject = "Verify Your Email - " . SITE_NAME;
                $message = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #1e3a5f 0%, #0f2440 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                            .button { display: inline-block; background: #1e3a5f; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'><h2>Welcome to " . SITE_NAME . "!</h2></div>
                            <div class='content'>
                                <p>Dear <strong>{$firstName} {$lastName}</strong>,</p>
                                <p>Thank you for registering. Please verify your email address to complete your registration.</p>
                                <p style='text-align: center;'><a href='{$verificationLink}' class='button'>Verify Email Address</a></p>
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

<div class="root-container">
    <div class="root-form-container">
        <div class="root-form-header">
            <i class="fas fa-user-plus"></i>
            <h2>Create Your Account</h2>
            <p>Join our healthcare community and take control of your health</p>
        </div>
        
        <?php if ($error): ?>
            <div class="root-alert root-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="register-form">
            <div class="root-form-row">
                <div class="root-form-group">
                    <label for="first_name"><i class="fas fa-user"></i> First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="root-form-control" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                </div>
                <div class="root-form-group">
                    <label for="last_name"><i class="fas fa-user"></i> Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="root-form-control" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="root-form-group">
                <label for="username"><i class="fas fa-at"></i> Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" class="root-form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                <small style="color: #64748b;">Used for login (must be unique)</small>
            </div>
            
            <div class="root-form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="root-form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="root-form-group">
                <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" class="root-form-control" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
            </div>
            
            <div class="root-form-row">
                <div class="root-form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="root-form-control" required>
                    <small style="color: #64748b;">Minimum 8 characters</small>
                </div>
                <div class="root-form-group">
                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="root-form-control" required>
                </div>
            </div>
            
            <div class="root-form-group">
                <label for="date_of_birth"><i class="fas fa-calendar"></i> Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="root-form-control" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
            </div>
            
            <div class="root-form-group">
                <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                <textarea id="address" name="address" rows="3" class="root-form-control"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="root-form-row">
                <div class="root-form-group">
                    <label for="blood_type"><i class="fas fa-tint"></i> Blood Type</label>
                    <select id="blood_type" name="blood_type" class="root-form-control">
                        <option value="">Select Blood Type</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo ($_POST['blood_type'] ?? '') === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="root-form-group">
                    <label for="known_allergies"><i class="fas fa-exclamation-triangle"></i> Known Allergies</label>
                    <input type="text" id="known_allergies" name="known_allergies" class="root-form-control" value="<?php echo htmlspecialchars($_POST['known_allergies'] ?? ''); ?>" placeholder="e.g., Penicillin, Peanuts">
                </div>
            </div>
            
            <div class="root-form-row">
                <div class="root-form-group">
                    <label for="insurance_provider"><i class="fas fa-shield-alt"></i> Insurance Provider</label>
                    <input type="text" id="insurance_provider" name="insurance_provider" class="root-form-control" value="<?php echo htmlspecialchars($_POST['insurance_provider'] ?? ''); ?>">
                </div>
                <div class="root-form-group">
                    <label for="insurance_number"><i class="fas fa-id-card"></i> Insurance Number</label>
                    <input type="text" id="insurance_number" name="insurance_number" class="root-form-control" value="<?php echo htmlspecialchars($_POST['insurance_number'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="root-form-row">
                <div class="root-form-group">
                    <label for="emergency_contact_name"><i class="fas fa-user-friends"></i> Emergency Contact Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="root-form-control" value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                </div>
                <div class="root-form-group">
                    <label for="emergency_contact_phone"><i class="fas fa-phone-alt"></i> Emergency Contact Phone</label>
                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="root-form-control" value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="root-terms-group">
                <label class="root-terms-label">
                    <input type="checkbox" name="terms" required>
                    <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                </label>
            </div>
            
            <button type="submit" class="root-btn root-btn-primary root-btn-block">Register</button>
        </form>
        
        <div class="root-form-footer">
            <p>Already have an account? <a href="login.php">Login Here</a></p>
            <p><a href="services.php">Learn about our services</a> | <a href="doctors.php">Find a doctor</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>