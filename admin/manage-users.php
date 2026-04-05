<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Users - HealthManagement";
include '../includes/header.php';

// Handle user creation
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
        $error = "Password must be at least 8 characters long.";
    } else {
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $pdo->beginTransaction();
                
                // Create user
                $stmt = $pdo->prepare("INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, isVerified, dateCreated) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $phoneNumber, $role]);
                $userId = $pdo->lastInsertId();
                
                // Create staff record for all except patients
                if ($role !== 'patient') {
                    $licenseNumber = sanitizeInput($_POST['license_number'] ?? '');
                    $hireDate = $_POST['hire_date'] ?? date('Y-m-d');
                    $department = sanitizeInput($_POST['department'] ?? '');
                    $position = sanitizeInput($_POST['position'] ?? ucfirst($role));
                    
                    $stmt = $pdo->prepare("INSERT INTO staff (userId, licenseNumber, hireDate, department, position) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $licenseNumber, $hireDate, $department, $position]);
                    $staffId = $pdo->lastInsertId();
                    
                    // Role-specific records
                    if ($role === 'doctor') {
                        $specialization = sanitizeInput($_POST['specialization']);
                        $consultationFee = floatval($_POST['consultation_fee']);
                        $yearsOfExperience = intval($_POST['years_of_experience']);
                        $education = sanitizeInput($_POST['education']);
                        $biography = sanitizeInput($_POST['biography']);
                        
                        $stmt = $pdo->prepare("INSERT INTO doctors (staffId, specialization, consultationFee, yearsOfExperience, education, biography, isAvailable) VALUES (?, ?, ?, ?, ?, ?, 1)");
                        $stmt->execute([$staffId, $specialization, $consultationFee, $yearsOfExperience, $education, $biography]);
                        
                    } elseif ($role === 'nurse') {
                        $nursingSpecialty = sanitizeInput($_POST['nursing_specialty']);
                        $certification = sanitizeInput($_POST['certification']);
                        
                        $stmt = $pdo->prepare("INSERT INTO nurses (staffId, nursingSpecialty, certification) VALUES (?, ?, ?)");
                        $stmt->execute([$staffId, $nursingSpecialty, $certification]);
                        
                    } elseif ($role === 'admin') {
                        $adminLevel = $_POST['admin_level'];
                        $permissions = sanitizeInput($_POST['permissions']);
                        
                        $stmt = $pdo->prepare("INSERT INTO administrators (staffId, adminLevel, permissions) VALUES (?, ?, ?)");
                        $stmt->execute([$staffId, $adminLevel, $permissions]);
                    }
                } else {
                    // Patient record
                    $dateOfBirth = $_POST['date_of_birth'] ?: null;
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $bloodType = $_POST['blood_type'] ?? null;
                    $knownAllergies = sanitizeInput($_POST['known_allergies'] ?? '');
                    $insuranceProvider = sanitizeInput($_POST['insurance_provider'] ?? '');
                    $insuranceNumber = sanitizeInput($_POST['insurance_number'] ?? '');
                    $emergencyContactName = sanitizeInput($_POST['emergency_contact_name'] ?? '');
                    $emergencyContactPhone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
                    
                    $stmt = $pdo->prepare("INSERT INTO patients (userId, dateOfBirth, address, bloodType, knownAllergies, insuranceProvider, insuranceNumber, emergencyContactName, emergencyContactPhone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $dateOfBirth, $address, $bloodType, $knownAllergies, $insuranceProvider, $insuranceNumber, $emergencyContactName, $emergencyContactPhone]);
                }
                
                $pdo->commit();
                $_SESSION['success'] = "User created successfully!";
                logAction($_SESSION['user_id'], 'CREATE_USER', "Created new $role: $username");
                header("Location: manage-users.php");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to create user: " . $e->getMessage();
            }
        }
    }
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $userId = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE userId = ?");
        $stmt->execute([$userId]);
        $_SESSION['success'] = "User deleted successfully!";
        header("Location: manage-users.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to delete user.";
    }
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = $_POST['user_id'];
    $newRole = $_POST['new_role'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE userId = ?");
        $stmt->execute([$newRole, $userId]);
        $_SESSION['success'] = "User role updated successfully!";
        header("Location: manage-users.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to update role.";
    }
}

// Get all users with their details
$users = $pdo->query("
    SELECT u.*, 
           p.dateOfBirth, p.bloodType,
           d.specialization, d.consultationFee,
           n.nursingSpecialty,
           s.licenseNumber, s.hireDate, s.department, s.position,
           a.adminLevel
    FROM users u
    LEFT JOIN patients p ON u.userId = p.userId
    LEFT JOIN staff s ON u.userId = s.userId
    LEFT JOIN doctors d ON s.staffId = d.staffId
    LEFT JOIN nurses n ON s.staffId = n.staffId
    LEFT JOIN administrators a ON s.staffId = a.staffId
    ORDER BY u.dateCreated DESC
")->fetchAll();

// Get departments for dropdown
$departments = $pdo->query("SELECT DISTINCT name FROM departments WHERE isActive = 1")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Users</h1>
        <p>Create and manage users with role-specific details</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Create User Form -->
    <div class="card">
        <div class="card-header">
            <h3>Create New User</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="create-user-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number">
                    </div>
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required onchange="toggleRoleForm()">
                            <option value="patient">Patient</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
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
                
                <!-- Common Staff Fields (for doctor, nurse, staff, admin) -->
                <div id="staff-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="license_number">License/Certificate Number</label>
                            <input type="text" id="license_number" name="license_number">
                        </div>
                        <div class="form-group">
                            <label for="hire_date">Hire Date</label>
                            <input type="date" id="hire_date" name="hire_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="department">Department</label>
                            <select id="department" name="department">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['name']; ?>"><?php echo $dept['name']; ?></option>
                                <?php endforeach; ?>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" name="position" placeholder="e.g., Senior Cardiologist">
                        </div>
                    </div>
                </div>
                
                <!-- Doctor Specific Fields -->
                <div id="doctor-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialization">Specialization *</label>
                            <input type="text" id="specialization" name="specialization" placeholder="e.g., Cardiology, Neurology">
                        </div>
                        <div class="form-group">
                            <label for="consultation_fee">Consultation Fee ($) *</label>
                            <input type="number" id="consultation_fee" name="consultation_fee" step="10">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="years_of_experience">Years of Experience</label>
                            <input type="number" id="years_of_experience" name="years_of_experience">
                        </div>
                        <div class="form-group">
                            <label for="education">Education & Qualifications</label>
                            <input type="text" id="education" name="education" placeholder="MD, PhD, etc.">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="biography">Professional Biography</label>
                        <textarea id="biography" name="biography" rows="3" placeholder="Professional background, achievements, etc."></textarea>
                    </div>
                </div>
                
                <!-- Nurse Specific Fields -->
                <div id="nurse-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nursing_specialty">Nursing Specialty *</label>
                            <input type="text" id="nursing_specialty" name="nursing_specialty" placeholder="e.g., Cardiac Care, Emergency, Pediatrics">
                        </div>
                        <div class="form-group">
                            <label for="certification">Certification</label>
                            <input type="text" id="certification" name="certification" placeholder="e.g., CCRN, ACLS, BLS">
                        </div>
                    </div>
                </div>
                
                <!-- Admin Specific Fields -->
                <div id="admin-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_level">Admin Level *</label>
                            <select id="admin_level" name="admin_level">
                                <option value="super">Super Admin (Full Access)</option>
                                <option value="regular">Regular Admin (Limited Access)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="permissions">Permissions (JSON format)</label>
                            <input type="text" id="permissions" name="permissions" placeholder='{"users":true,"doctors":true}'>
                        </div>
                    </div>
                </div>
                
                <!-- Patient Specific Fields -->
                <div id="patient-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth">
                        </div>
                        <div class="form-group">
                            <label for="blood_type">Blood Type</label>
                            <select id="blood_type" name="blood_type">
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="known_allergies">Known Allergies</label>
                            <input type="text" id="known_allergies" name="known_allergies" placeholder="e.g., Penicillin, Peanuts">
                        </div>
                        <div class="form-group">
                            <label for="insurance_provider">Insurance Provider</label>
                            <input type="text" id="insurance_provider" name="insurance_provider">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="insurance_number">Insurance Number</label>
                            <input type="text" id="insurance_number" name="insurance_number">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emergency_contact_name">Emergency Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name">
                        </div>
                        <div class="form-group">
                            <label for="emergency_contact_phone">Emergency Contact Phone</label>
                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone">
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="card">
        <div class="card-header">
            <h3>All Users</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Details</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td data-label="ID">#<?php echo $user['userId']; ?></td>
                        <td data-label="Name">
                            <strong><?php echo $user['firstName'] . ' ' . $user['lastName']; ?></strong><br>
                            <small>@<?php echo $user['username']; ?></small>
                        </td>
                        <td data-label="Email">
                            <?php echo $user['email']; ?><br>
                            <small><?php echo $user['phoneNumber']; ?></small>
                        </td>
                        <td data-label="Role">
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td data-label="Details">
                            <?php if ($user['role'] === 'doctor'): ?>
                                <small>
                                    <i class="fas fa-stethoscope"></i> <?php echo $user['specialization']; ?><br>
                                    <i class="fas fa-dollar-sign"></i> $<?php echo number_format($user['consultationFee'], 2); ?>
                                </small>
                            <?php elseif ($user['role'] === 'nurse'): ?>
                                <small>
                                    <i class="fas fa-heartbeat"></i> <?php echo $user['nursingSpecialty']; ?><br>
                                    <i class="fas fa-certificate"></i> <?php echo $user['certification']; ?>
                                </small>
                            <?php elseif ($user['role'] === 'staff'): ?>
                                <small>
                                    <i class="fas fa-building"></i> <?php echo $user['department']; ?><br>
                                    <i class="fas fa-briefcase"></i> <?php echo $user['position']; ?>
                                </small>
                            <?php elseif ($user['role'] === 'patient'): ?>
                                <small>
                                    <i class="fas fa-tint"></i> <?php echo $user['bloodType'] ?: 'N/A'; ?><br>
                                    <i class="fas fa-calendar"></i> <?php echo $user['dateOfBirth'] ?: 'N/A'; ?>
                                </small>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <small>
                                    <i class="fas fa-crown"></i> <?php echo ucfirst($user['adminLevel']); ?> Admin
                                </small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="openModal('roleModal'); document.getElementById('role_user_id').value = <?php echo $user['userId']; ?>; document.getElementById('new_role').value = '<?php echo $user['role']; ?>';">
                                    <i class="fas fa-user-tag"></i> Change Role
                                </button>
                                <a href="?delete=<?php echo $user['userId']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user permanently?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div id="roleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change User Role</h3>
            <span class="close" onclick="closeModal('roleModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="user_id" id="role_user_id">
                <div class="form-group">
                    <label for="new_role">Select New Role</label>
                    <select id="new_role" name="new_role" required>
                        <option value="patient">Patient</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <small>Note: Changing role will not automatically update professional details. Please update details separately.</small>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_role" class="btn btn-primary">Update Role</button>
                <button type="button" class="btn" onclick="closeModal('roleModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleRoleForm() {
    const role = document.getElementById('role').value;
    
    // Hide all role-specific sections
    document.getElementById('staff-fields').style.display = 'none';
    document.getElementById('doctor-fields').style.display = 'none';
    document.getElementById('nurse-fields').style.display = 'none';
    document.getElementById('admin-fields').style.display = 'none';
    document.getElementById('patient-fields').style.display = 'block';
    
    // Show relevant sections based on role
    if (role === 'doctor' || role === 'nurse' || role === 'staff' || role === 'admin') {
        document.getElementById('staff-fields').style.display = 'block';
    }
    
    if (role === 'doctor') {
        document.getElementById('doctor-fields').style.display = 'block';
        document.getElementById('patient-fields').style.display = 'none';
    } else if (role === 'nurse') {
        document.getElementById('nurse-fields').style.display = 'block';
        document.getElementById('patient-fields').style.display = 'none';
    } else if (role === 'admin') {
        document.getElementById('admin-fields').style.display = 'block';
        document.getElementById('patient-fields').style.display = 'none';
    } else if (role === 'staff') {
        document.getElementById('patient-fields').style.display = 'none';
    }
    
    // Update position field based on role
    const positionField = document.getElementById('position');
    if (positionField) {
        if (role === 'doctor') {
            positionField.value = 'Doctor';
        } else if (role === 'nurse') {
            positionField.value = 'Nurse';
        } else if (role === 'admin') {
            positionField.value = 'Administrator';
        } else if (role === 'staff') {
            positionField.value = '';
        }
    }
}

// Password confirmation validation
document.getElementById('create-user-form')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match');
    } else if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
    }
});

// Initialize role fields on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleRoleForm();
});
</script>

<?php include '../includes/footer.php'; ?>