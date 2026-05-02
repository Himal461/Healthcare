<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$pageTitle = "My Profile - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE userId = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($userRole === 'patient') {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE userId = ?");
    $stmt->execute([$userId]);
    $patient = $stmt->fetch();
} elseif (in_array($userRole, ['doctor', 'nurse', 'staff', 'admin', 'accountant'])) {
    $stmt = $pdo->prepare("SELECT s.*, d.specialization, d.consultationFee, d.yearsOfExperience, d.biography, d.education FROM staff s LEFT JOIN doctors d ON s.staffId = d.staffId WHERE s.userId = ?");
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
        $stmt = $pdo->prepare("UPDATE users SET firstName = ?, lastName = ?, phoneNumber = ?, email = ? WHERE userId = ?");
        $stmt->execute([$firstName, $lastName, $phoneNumber, $email, $userId]);

        if ($userRole === 'patient') {
            $stmt = $pdo->prepare("UPDATE patients SET dateOfBirth = ?, address = ?, bloodType = ?, knownAllergies = ?, emergencyContactName = ?, emergencyContactPhone = ?, insuranceProvider = ?, insuranceNumber = ? WHERE userId = ?");
            $stmt->execute([$_POST['date_of_birth']?:null, sanitizeInput($_POST['address']), $_POST['blood_type']?:null, sanitizeInput($_POST['known_allergies']), sanitizeInput($_POST['emergency_contact_name']), sanitizeInput($_POST['emergency_contact_phone']), sanitizeInput($_POST['insurance_provider']), sanitizeInput($_POST['insurance_number']), $userId]);
        } elseif (in_array($userRole, ['doctor', 'nurse', 'staff', 'admin', 'accountant']) && isset($staff)) {
            $stmt = $pdo->prepare("UPDATE staff SET licenseNumber = ? WHERE staffId = ?");
            $stmt->execute([sanitizeInput($_POST['license_number']), $staff['staffId']]);
            if ($userRole === 'doctor') {
                $stmt = $pdo->prepare("UPDATE doctors SET specialization = ?, consultationFee = ?, yearsOfExperience = ?, biography = ?, education = ? WHERE staffId = ?");
                $stmt->execute([sanitizeInput($_POST['specialization']), floatval($_POST['consultation_fee']), intval($_POST['years_of_experience']), sanitizeInput($_POST['biography']), sanitizeInput($_POST['education']), $staff['staffId']]);
            }
        }
        $pdo->commit();
        $_SESSION['success'] = "Profile updated successfully!";
        logAction($userId, 'PROFILE_UPDATE', "Updated profile");
        header("Location: profile.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update profile.";
    }
}

$success = $_SESSION['success'] ?? null;
$error = $error ?? null;
unset($_SESSION['success']);
?>

<div class="root-container">
    <div class="root-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <p>Manage your personal information</p>
        </div>
        <div class="header-actions">
            <a href="change-password.php" class="root-btn root-btn-outline">
                <i class="fas fa-key"></i> Change Password
            </a>
        </div>
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

    <form method="POST" id="profile-form">
        <div class="root-profile-card">
            <h3><i class="fas fa-user"></i> Basic Information</h3>
            <div class="root-form-row">
                <div class="root-form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="root-form-control" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                </div>
                <div class="root-form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="root-form-control" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                </div>
            </div>
            <div class="root-form-row">
                <div class="root-form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="root-form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="root-form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone_number" class="root-form-control" value="<?php echo htmlspecialchars($user['phoneNumber']); ?>">
                </div>
            </div>
            <div class="root-form-group">
                <label>Username</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" class="root-form-control" readonly>
            </div>
        </div>

        <?php if ($userRole === 'patient' && isset($patient)): ?>
        <div class="root-profile-card">
            <h3><i class="fas fa-notes-medical"></i> Medical Information</h3>
            <div class="root-form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" class="root-form-control" value="<?php echo $patient['dateOfBirth']??''; ?>">
            </div>
            <div class="root-form-group">
                <label>Address</label>
                <textarea name="address" rows="2" class="root-form-control"><?php echo htmlspecialchars($patient['address']??''); ?></textarea>
            </div>
            <div class="root-form-row">
                <div class="root-form-group">
                    <label>Blood Type</label>
                    <select name="blood_type" class="root-form-control">
                        <option value="">Select</option>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                            <option value="<?php echo $bt; ?>" <?php echo ($patient['bloodType']??'')==$bt?'selected':''; ?>><?php echo $bt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="root-form-group">
                    <label>Allergies</label>
                    <input type="text" name="known_allergies" class="root-form-control" value="<?php echo htmlspecialchars($patient['knownAllergies']??''); ?>">
                </div>
            </div>
            <div class="root-form-row">
                <div class="root-form-group">
                    <label>Emergency Contact</label>
                    <input type="text" name="emergency_contact_name" class="root-form-control" value="<?php echo htmlspecialchars($patient['emergencyContactName']??''); ?>">
                </div>
                <div class="root-form-group">
                    <label>Emergency Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="root-form-control" value="<?php echo htmlspecialchars($patient['emergencyContactPhone']??''); ?>">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($userRole, ['doctor','nurse','staff','admin','accountant']) && isset($staff)): ?>
        <div class="root-profile-card">
            <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
            <div class="root-form-group">
                <label>License Number</label>
                <input type="text" name="license_number" class="root-form-control" value="<?php echo htmlspecialchars($staff['licenseNumber']??''); ?>">
            </div>
            <?php if ($userRole === 'doctor'): ?>
                <div class="root-form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" class="root-form-control" value="<?php echo htmlspecialchars($staff['specialization']??''); ?>">
                </div>
                <div class="root-form-row">
                    <div class="root-form-group">
                        <label>Consultation Fee ($)</label>
                        <input type="number" name="consultation_fee" step="10" class="root-form-control" value="<?php echo $staff['consultationFee']??100; ?>">
                    </div>
                    <div class="root-form-group">
                        <label>Years Experience</label>
                        <input type="number" name="years_of_experience" class="root-form-control" value="<?php echo $staff['yearsOfExperience']??0; ?>">
                    </div>
                </div>
                <div class="root-form-group">
                    <label>Education</label>
                    <textarea name="education" rows="2" class="root-form-control"><?php echo htmlspecialchars($staff['education']??''); ?></textarea>
                </div>
                <div class="root-form-group">
                    <label>Biography</label>
                    <textarea name="biography" rows="3" class="root-form-control"><?php echo htmlspecialchars($staff['biography']??''); ?></textarea>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="display: flex; gap: 15px; margin-top: 20px;">
            <button type="submit" class="root-btn root-btn-primary">Update Profile</button>
            <a href="dashboard.php" class="root-btn root-btn-outline">Back to Dashboard</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>