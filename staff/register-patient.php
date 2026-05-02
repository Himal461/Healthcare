<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Register New Patient - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phoneNumber = sanitizeInput($_POST['phone_number']);
    
    if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, isVerified, dateCreated) VALUES (?, ?, ?, ?, ?, ?, 'patient', 1, NOW())");
                $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $phoneNumber]);
                $userId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO patients (userId, dateOfBirth, address, bloodType, knownAllergies, insuranceProvider, insuranceNumber, emergencyContactName, emergencyContactPhone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $_POST['date_of_birth'] ?: null, sanitizeInput($_POST['address'] ?? ''), $_POST['blood_type'] ?? null, sanitizeInput($_POST['known_allergies'] ?? ''), sanitizeInput($_POST['insurance_provider'] ?? ''), sanitizeInput($_POST['insurance_number'] ?? ''), sanitizeInput($_POST['emergency_contact_name'] ?? ''), sanitizeInput($_POST['emergency_contact_phone'] ?? '')]);
                $pdo->commit();
                $_SESSION['success'] = "Patient registered successfully!";
                logAction($_SESSION['user_id'], 'REGISTER_PATIENT', "Registered: $username");
                header("Location: dashboard.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-plus"></i> Register New Patient</h1>
            <p>Add a new patient to the system</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="staff-form-container">
        <?php if ($error): ?>
            <div class="staff-alert staff-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="register-form">
            <div class="staff-form-row">
                <div class="staff-form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" class="staff-form-control" required>
                </div>
                <div class="staff-form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="staff-form-control" required>
                </div>
            </div>
            
            <div class="staff-form-group">
                <label>Username <span class="required">*</span></label>
                <input type="text" name="username" class="staff-form-control" required>
            </div>
            
            <div class="staff-form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" class="staff-form-control" required>
            </div>
            
            <div class="staff-form-group">
                <label>Phone</label>
                <input type="tel" name="phone_number" class="staff-form-control">
            </div>
            
            <div class="staff-form-row">
                <div class="staff-form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" class="staff-form-control" required>
                </div>
                <div class="staff-form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" class="staff-form-control" required>
                </div>
            </div>
            
            <div class="staff-form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" class="staff-form-control">
            </div>
            
            <div class="staff-form-group">
                <label>Address</label>
                <textarea name="address" rows="2" class="staff-form-control"></textarea>
            </div>
            
            <div class="staff-form-row">
                <div class="staff-form-group">
                    <label>Blood Type</label>
                    <select name="blood_type" class="staff-form-control">
                        <option value="">Select</option>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                            <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="staff-form-group">
                    <label>Allergies</label>
                    <input type="text" name="known_allergies" class="staff-form-control">
                </div>
            </div>
            
            <div class="staff-form-row">
                <div class="staff-form-group">
                    <label>Insurance Provider</label>
                    <input type="text" name="insurance_provider" class="staff-form-control">
                </div>
                <div class="staff-form-group">
                    <label>Insurance Number</label>
                    <input type="text" name="insurance_number" class="staff-form-control">
                </div>
            </div>
            
            <div class="staff-form-row">
                <div class="staff-form-group">
                    <label>Emergency Contact</label>
                    <input type="text" name="emergency_contact_name" class="staff-form-control">
                </div>
                <div class="staff-form-group">
                    <label>Emergency Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="staff-form-control">
                </div>
            </div>
            
            <button type="submit" class="staff-btn staff-btn-primary staff-btn-block">
                <i class="fas fa-save"></i> Register Patient
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('register-form').addEventListener('submit', function(e) {
    const p = document.querySelector('[name="password"]').value;
    const c = document.querySelector('[name="confirm_password"]').value;
    if (p !== c) {
        e.preventDefault();
        alert('Passwords do not match');
    } else if (p.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters');
    }
});
</script>

<?php include '../includes/footer.php'; ?>