<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Create User - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phoneNumber = sanitizeInput($_POST['phone_number']);
    $role = $_POST['role'];
    
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
                
                $stmt = $pdo->prepare("INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, isVerified, dateCreated) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $phoneNumber, $role]);
                $userId = $pdo->lastInsertId();
                
                if ($role !== 'patient') {
                    $licenseNumber = sanitizeInput($_POST['license_number'] ?? '');
                    $hireDate = $_POST['hire_date'] ?? date('Y-m-d');
                    $department = sanitizeInput($_POST['department'] ?? '');
                    $position = sanitizeInput($_POST['position'] ?? ucfirst($role));
                    
                    $stmt = $pdo->prepare("INSERT INTO staff (userId, licenseNumber, hireDate, department, position) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $licenseNumber, $hireDate, $department, $position]);
                    $staffId = $pdo->lastInsertId();
                    
                    if ($role === 'doctor') {
                        $specialization = sanitizeInput($_POST['specialization'] ?? 'General');
                        $consultationFee = floatval($_POST['consultation_fee'] ?? 100);
                        $stmt = $pdo->prepare("INSERT INTO doctors (staffId, specialization, consultationFee, isAvailable) VALUES (?, ?, ?, 1)");
                        $stmt->execute([$staffId, $specialization, $consultationFee]);
                    } elseif ($role === 'nurse') {
                        $nursingSpecialty = sanitizeInput($_POST['nursing_specialty'] ?? 'General');
                        $stmt = $pdo->prepare("INSERT INTO nurses (staffId, nursingSpecialty) VALUES (?, ?)");
                        $stmt->execute([$staffId, $nursingSpecialty]);
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO patients (userId) VALUES (?)");
                    $stmt->execute([$userId]);
                }
                
                $pdo->commit();
                $_SESSION['success'] = "User created successfully!";
                logAction($_SESSION['user_id'], 'CREATE_USER', "Created $role: $username");
                header("Location: users.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed: " . $e->getMessage();
            }
        }
    }
}

$departments = $pdo->query("SELECT DISTINCT name FROM departments WHERE isActive = 1")->fetchAll();
$error = $error ?? null;
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-plus"></i> Create New User</h1>
            <p>Add a new user with role-specific details</p>
        </div>
        <div class="header-actions">
            <a href="users.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <div class="admin-card-body">
            <form method="POST" id="create-user-form">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="admin-form-control" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="admin-form-control" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone_number" class="admin-form-control">
                    </div>
                    <div class="admin-form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="role" id="role" class="admin-form-control" required onchange="toggleRoleForm()">
                            <option value="patient">Patient</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="staff">Staff</option>
                            <option value="accountant">Accountant</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Confirm <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="admin-form-control" required>
                    </div>
                </div>
                
                <div id="staff-fields" style="display:none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>License Number</label>
                            <input type="text" name="license_number" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Hire Date</label>
                            <input type="date" name="hire_date" class="admin-form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Department</label>
                            <select name="department" class="admin-form-control">
                                <option value="">Select</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['name']; ?>"><?php echo $dept['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label>Position</label>
                            <input type="text" name="position" id="position" class="admin-form-control">
                        </div>
                    </div>
                </div>
                
                <div id="doctor-fields" style="display:none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Specialization</label>
                            <input type="text" name="specialization" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Consultation Fee ($)</label>
                            <input type="number" name="consultation_fee" class="admin-form-control" step="10" value="150">
                        </div>
                    </div>
                </div>
                
                <div id="nurse-fields" style="display:none;">
                    <div class="admin-form-group">
                        <label>Nursing Specialty</label>
                        <input type="text" name="nursing_specialty" class="admin-form-control">
                    </div>
                </div>
                
                <div id="patient-fields">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Blood Type</label>
                            <select name="blood_type" class="admin-form-control">
                                <option value="">Select</option>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                    <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2" class="admin-form-control"></textarea>
                    </div>
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Allergies</label>
                            <input type="text" name="known_allergies" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Insurance Provider</label>
                            <input type="text" name="insurance_provider" class="admin-form-control">
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="create_user" class="admin-btn admin-btn-primary">Create User</button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRoleForm() {
    const role = document.getElementById('role').value;
    document.getElementById('staff-fields').style.display = (role==='doctor'||role==='nurse'||role==='staff'||role==='admin'||role==='accountant')?'block':'none';
    document.getElementById('doctor-fields').style.display = role==='doctor'?'block':'none';
    document.getElementById('nurse-fields').style.display = role==='nurse'?'block':'none';
    document.getElementById('patient-fields').style.display = role==='patient'?'block':'none';
    
    const pos = document.getElementById('position');
    if (role==='doctor') pos.value='Doctor';
    else if (role==='nurse') pos.value='Nurse';
    else if (role==='admin') pos.value='Administrator';
    else if (role==='accountant') pos.value='Accountant';
    else if (role==='staff') pos.value='';
}
document.addEventListener('DOMContentLoaded', toggleRoleForm);
</script>

<?php include '../includes/footer.php'; ?>