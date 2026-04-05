<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$pageTitle = "My Profile - HealthManagement";
include 'includes/header.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE userId = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get additional data based on role
if ($userRole === 'patient') {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE userId = ?");
    $stmt->execute([$userId]);
    $patient = $stmt->fetch();
} elseif (in_array($userRole, ['doctor', 'nurse', 'staff'])) {
    $stmt = $pdo->prepare("
        SELECT s.*, d.specialization, d.consultationFee, d.yearsOfExperience, d.biography, d.education
        FROM staff s 
        LEFT JOIN doctors d ON s.staffId = d.staffId 
        WHERE s.userId = ?
    ");
    $stmt->execute([$userId]);
    $staff = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phoneNumber = sanitizeInput($_POST['phone_number']);
    $email = sanitizeInput($_POST['email']);

    try {
        $pdo->beginTransaction();

        // Update user data
        $stmt = $pdo->prepare("UPDATE users SET firstName = ?, lastName = ?, phoneNumber = ?, email = ? WHERE userId = ?");
        $stmt->execute([$firstName, $lastName, $phoneNumber, $email, $userId]);

        // Update role-specific data
        if ($userRole === 'patient') {
            $dateOfBirth = $_POST['date_of_birth'] ?: null;
            $address = sanitizeInput($_POST['address']);
            $bloodType = $_POST['blood_type'] ?: null;
            $knownAllergies = sanitizeInput($_POST['known_allergies']);
            $emergencyContactName = sanitizeInput($_POST['emergency_contact_name']);
            $emergencyContactPhone = sanitizeInput($_POST['emergency_contact_phone']);
            $insuranceProvider = sanitizeInput($_POST['insurance_provider']);
            $insuranceNumber = sanitizeInput($_POST['insurance_number']);

            $stmt = $pdo->prepare("UPDATE patients SET dateOfBirth = ?, address = ?, bloodType = ?, knownAllergies = ?, emergencyContactName = ?, emergencyContactPhone = ?, insuranceProvider = ?, insuranceNumber = ? WHERE userId = ?");
            $stmt->execute([$dateOfBirth, $address, $bloodType, $knownAllergies, $emergencyContactName, $emergencyContactPhone, $insuranceProvider, $insuranceNumber, $userId]);
        } elseif (in_array($userRole, ['doctor', 'nurse', 'staff'])) {
            $licenseNumber = sanitizeInput($_POST['license_number']);
            
            $stmt = $pdo->prepare("UPDATE staff SET licenseNumber = ? WHERE userId = ?");
            $stmt->execute([$licenseNumber, $userId]);

            if ($userRole === 'doctor') {
                $specialization = sanitizeInput($_POST['specialization']);
                $consultationFee = floatval($_POST['consultation_fee']);
                $yearsOfExperience = intval($_POST['years_of_experience']);
                $biography = sanitizeInput($_POST['biography']);
                $education = sanitizeInput($_POST['education']);
                
                $stmt = $pdo->prepare("UPDATE doctors SET specialization = ?, consultationFee = ?, yearsOfExperience = ?, biography = ?, education = ? WHERE staffId = ?");
                $stmt->execute([$specialization, $consultationFee, $yearsOfExperience, $biography, $education, $staff['staffId']]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Profile updated successfully!";
        logAction($userId, 'PROFILE_UPDATE', "User updated their profile");
        
        // Refresh data
        header("Location: profile.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update profile. Please try again.";
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>My Profile</h1>
        <p>Manage your personal information and account settings</p>
    </div>

    <div class="profile-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="profile-form" id="profile-form">
            <div class="profile-card">
                <h3><i class="fas fa-user-circle"></i> Basic Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phoneNumber']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="background: #f8f9fa;">
                    <small>Username cannot be changed</small>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <input type="text" value="<?php echo ucfirst($userRole); ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>

            <?php if ($userRole === 'patient'): ?>
            <div class="profile-card">
                <h3><i class="fas fa-notes-medical"></i> Medical Information</h3>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $patient['dateOfBirth'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="blood_type">Blood Type</label>
                        <select id="blood_type" name="blood_type">
                            <option value="">Select Blood Type</option>
                            <?php
                            $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($bloodTypes as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo ($patient['bloodType'] ?? '') === $type ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="known_allergies">Known Allergies</label>
                        <input type="text" id="known_allergies" name="known_allergies" value="<?php echo htmlspecialchars($patient['knownAllergies'] ?? ''); ?>" placeholder="e.g., Penicillin, Peanuts">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="insurance_provider">Insurance Provider</label>
                        <input type="text" id="insurance_provider" name="insurance_provider" value="<?php echo htmlspecialchars($patient['insuranceProvider'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="insurance_number">Insurance Number</label>
                        <input type="text" id="insurance_number" name="insurance_number" value="<?php echo htmlspecialchars($patient['insuranceNumber'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($patient['emergencyContactName'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($patient['emergencyContactPhone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['doctor', 'nurse', 'staff'])): ?>
            <div class="profile-card">
                <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                <div class="form-group">
                    <label for="license_number">License Number</label>
                    <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($staff['licenseNumber'] ?? ''); ?>">
                </div>
                
                <?php if ($userRole === 'doctor'): ?>
                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($staff['specialization'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="consultation_fee">Consultation Fee ($)</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" step="10" value="<?php echo $staff['consultationFee'] ?? 100; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="years_of_experience">Years of Experience</label>
                        <input type="number" id="years_of_experience" name="years_of_experience" value="<?php echo $staff['yearsOfExperience'] ?? 0; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="education">Education & Qualifications</label>
                    <textarea id="education" name="education" rows="3"><?php echo htmlspecialchars($staff['education'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="biography">Professional Biography</label>
                    <textarea id="biography" name="biography" rows="4"><?php echo htmlspecialchars($staff['biography'] ?? ''); ?></textarea>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Hire Date</label>
                    <input type="text" value="<?php echo $staff['hireDate'] ?? 'Not set'; ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
                <a href="change-password.php" class="btn btn-outline">
                    <i class="fas fa-key"></i> Change Password
                </a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>