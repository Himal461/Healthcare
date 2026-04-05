<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Staff - HealthManagement";
include '../includes/header.php';

// Handle staff creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phoneNumber = sanitizeInput($_POST['phone_number']);
    $staffRole = $_POST['staff_role'];
    $position = sanitizeInput($_POST['position']);
    $department = sanitizeInput($_POST['department']);
    $licenseNumber = sanitizeInput($_POST['license_number']);
    $hireDate = $_POST['hire_date'];
    
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
                
                $stmt = $pdo->prepare("INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, isVerified, dateCreated) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $phoneNumber, $staffRole]);
                $userId = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO staff (userId, licenseNumber, hireDate, department, position) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $licenseNumber, $hireDate, $department, $position]);
                $staffId = $pdo->lastInsertId();
                
                if ($staffRole === 'doctor') {
                    $specialization = sanitizeInput($_POST['specialization']);
                    $consultationFee = floatval($_POST['consultation_fee']);
                    $yearsOfExperience = intval($_POST['years_of_experience']);
                    $education = sanitizeInput($_POST['education']);
                    $biography = sanitizeInput($_POST['biography']);
                    
                    $stmt = $pdo->prepare("INSERT INTO doctors (staffId, specialization, consultationFee, yearsOfExperience, education, biography, isAvailable) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$staffId, $specialization, $consultationFee, $yearsOfExperience, $education, $biography]);
                    
                } elseif ($staffRole === 'nurse') {
                    $nursingSpecialty = sanitizeInput($_POST['nursing_specialty']);
                    $certification = sanitizeInput($_POST['certification']);
                    
                    $stmt = $pdo->prepare("INSERT INTO nurses (staffId, nursingSpecialty, certification) VALUES (?, ?, ?)");
                    $stmt->execute([$staffId, $nursingSpecialty, $certification]);
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Staff member created successfully!";
                logAction($_SESSION['user_id'], 'CREATE_STAFF', "Created new $staffRole: $username");
                header("Location: staff.php");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to create staff: " . $e->getMessage();
            }
        }
    }
}

// Handle staff update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $staffId = $_POST['staff_id'];
    $userId = $_POST['user_id'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phoneNumber = sanitizeInput($_POST['phone_number']);
    $licenseNumber = sanitizeInput($_POST['license_number']);
    $department = sanitizeInput($_POST['department']);
    $position = sanitizeInput($_POST['position']);
    $staffRole = $_POST['staff_role'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE users SET firstName = ?, lastName = ?, email = ?, phoneNumber = ? WHERE userId = ?");
        $stmt->execute([$firstName, $lastName, $email, $phoneNumber, $userId]);
        
        $stmt = $pdo->prepare("UPDATE staff SET licenseNumber = ?, department = ?, position = ? WHERE staffId = ?");
        $stmt->execute([$licenseNumber, $department, $position, $staffId]);
        
        if ($staffRole === 'doctor') {
            $specialization = sanitizeInput($_POST['specialization']);
            $consultationFee = floatval($_POST['consultation_fee']);
            $yearsOfExperience = intval($_POST['years_of_experience']);
            $education = sanitizeInput($_POST['education']);
            $biography = sanitizeInput($_POST['biography']);
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE doctors SET specialization = ?, consultationFee = ?, yearsOfExperience = ?, education = ?, biography = ?, isAvailable = ? WHERE staffId = ?");
            $stmt->execute([$specialization, $consultationFee, $yearsOfExperience, $education, $biography, $isAvailable, $staffId]);
            
        } elseif ($staffRole === 'nurse') {
            $nursingSpecialty = sanitizeInput($_POST['nursing_specialty']);
            $certification = sanitizeInput($_POST['certification']);
            
            $stmt = $pdo->prepare("UPDATE nurses SET nursingSpecialty = ?, certification = ? WHERE staffId = ?");
            $stmt->execute([$nursingSpecialty, $certification, $staffId]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Staff member updated successfully!";
        logAction($_SESSION['user_id'], 'UPDATE_STAFF', "Updated staff ID: $staffId");
        header("Location: staff.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update staff: " . $e->getMessage();
    }
}

// Handle staff deletion
if (isset($_GET['delete'])) {
    $staffId = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM staff WHERE staffId = ?");
        $stmt->execute([$staffId]);
        $_SESSION['success'] = "Staff member deleted successfully!";
        header("Location: staff.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to delete staff.";
    }
}

// Get all staff members
$staff = $pdo->query("
    SELECT s.*, u.userId, u.username, u.firstName, u.lastName, u.email, u.phoneNumber, u.role,
           d.specialization, d.consultationFee, d.yearsOfExperience, d.education, d.biography, d.isAvailable,
           n.nursingSpecialty, n.certification
    FROM staff s
    JOIN users u ON s.userId = u.userId
    LEFT JOIN doctors d ON s.staffId = d.staffId
    LEFT JOIN nurses n ON s.staffId = n.staffId
    WHERE u.role IN ('doctor', 'nurse', 'staff')
    ORDER BY u.role, u.firstName
")->fetchAll();

// Get departments
$departments = $pdo->query("SELECT DISTINCT name FROM departments WHERE isActive = 1")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Staff</h1>
        <p>Add and manage doctors, nurses, and support staff</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Create Staff Form -->
    <div class="card">
        <div class="card-header">
            <h3>Add New Staff Member</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="staff-form">
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
                        <label for="staff_role">Staff Role *</label>
                        <select id="staff_role" name="staff_role" required onchange="toggleRoleFields()">
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="staff">Support Staff</option>
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
                        <label for="position">Position Title</label>
                        <input type="text" id="position" name="position" placeholder="e.g., Senior Cardiologist, Registered Nurse">
                    </div>
                </div>
                
                <!-- Doctor Specific Fields -->
                <div id="doctor-fields" class="role-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialization">Specialization *</label>
                            <input type="text" id="specialization" name="specialization" placeholder="e.g., Cardiology, Neurology">
                        </div>
                        <div class="form-group">
                            <label for="consultation_fee">Consultation Fee ($) *</label>
                            <input type="number" id="consultation_fee" name="consultation_fee" step="10" value="150">
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
                <div id="nurse-fields" class="role-fields" style="display: none;">
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
                
                <!-- Support Staff Fields -->
                <div id="staff-fields" class="role-fields" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Support staff will have access to basic system features.
                    </div>
                </div>
                
                <button type="submit" name="create_staff" class="btn btn-primary">Add Staff Member</button>
            </form>
        </div>
    </div>

    <!-- Staff List -->
    <div class="card">
        <div class="card-header">
            <h3>All Staff Members</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                    <tr>
                        <td data-label="ID">#<?php echo $member['staffId']; ?></td>
                        <td data-label="Name">
                            <strong><?php echo $member['firstName'] . ' ' . $member['lastName']; ?></strong><br>
                            <small><?php echo $member['email']; ?></small>
                        </td>
                        <td data-label="Role">
                            <span class="role-badge role-<?php echo $member['role']; ?>">
                                <?php echo ucfirst($member['role']); ?>
                            </span>
                        </td>
                        <td data-label="Department">
                            <?php echo $member['department'] ?: '-'; ?><br>
                            <small><?php echo $member['position']; ?></small>
                        </td>
                        <td data-label="Details">
                            <?php if ($member['role'] === 'doctor'): ?>
                                <small>
                                    <i class="fas fa-stethoscope"></i> <?php echo $member['specialization']; ?><br>
                                    <i class="fas fa-dollar-sign"></i> $<?php echo number_format($member['consultationFee'], 2); ?><br>
                                    <i class="fas fa-graduation-cap"></i> <?php echo $member['yearsOfExperience']; ?> years
                                </small>
                            <?php elseif ($member['role'] === 'nurse'): ?>
                                <small>
                                    <i class="fas fa-heartbeat"></i> <?php echo $member['nursingSpecialty']; ?><br>
                                    <i class="fas fa-certificate"></i> <?php echo $member['certification']; ?>
                                </small>
                            <?php else: ?>
                                <small>
                                    <i class="fas fa-id-badge"></i> License: <?php echo $member['licenseNumber'] ?: 'N/A'; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($member['role'] === 'doctor'): ?>
                                <span class="status-badge <?php echo $member['isAvailable'] ? 'status-active' : 'status-cancelled'; ?>">
                                    <?php echo $member['isAvailable'] ? 'Available' : 'Unavailable'; ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-active">Active</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($member)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?delete=<?php echo $member['staffId']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this staff member permanently?')">
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

<!-- Edit Staff Modal -->
<div id="editModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Edit Staff Member</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="" id="edit-form">
            <div class="modal-body">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="staff_role" id="edit_staff_role">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_first_name">First Name *</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Last Name *</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone_number">Phone Number</label>
                        <input type="tel" id="edit_phone_number" name="phone_number">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_license_number">License/Certificate Number</label>
                        <input type="text" id="edit_license_number" name="license_number">
                    </div>
                    <div class="form-group">
                        <label for="edit_department">Department</label>
                        <input type="text" id="edit_department" name="department">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_position">Position Title</label>
                        <input type="text" id="edit_position" name="position">
                    </div>
                </div>
                
                <!-- Doctor Edit Fields -->
                <div id="edit-doctor-fields" class="edit-role-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_specialization">Specialization</label>
                            <input type="text" id="edit_specialization" name="specialization">
                        </div>
                        <div class="form-group">
                            <label for="edit_consultation_fee">Consultation Fee ($)</label>
                            <input type="number" id="edit_consultation_fee" name="consultation_fee" step="10">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_years_of_experience">Years of Experience</label>
                            <input type="number" id="edit_years_of_experience" name="years_of_experience">
                        </div>
                        <div class="form-group">
                            <label for="edit_education">Education & Qualifications</label>
                            <input type="text" id="edit_education" name="education">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_biography">Biography</label>
                        <textarea id="edit_biography" name="biography" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="edit_is_available" name="is_available" value="1">
                            Available for appointments
                        </label>
                    </div>
                </div>
                
                <!-- Nurse Edit Fields -->
                <div id="edit-nurse-fields" class="edit-role-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_nursing_specialty">Nursing Specialty</label>
                            <input type="text" id="edit_nursing_specialty" name="nursing_specialty">
                        </div>
                        <div class="form-group">
                            <label for="edit_certification">Certification</label>
                            <input type="text" id="edit_certification" name="certification">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_staff" class="btn btn-primary">Update Staff</button>
                <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleRoleFields() {
    const role = document.getElementById('staff_role').value;
    
    document.getElementById('doctor-fields').style.display = 'none';
    document.getElementById('nurse-fields').style.display = 'none';
    document.getElementById('staff-fields').style.display = 'none';
    
    if (role === 'doctor') {
        document.getElementById('doctor-fields').style.display = 'block';
        document.getElementById('position').value = 'Doctor';
    } else if (role === 'nurse') {
        document.getElementById('nurse-fields').style.display = 'block';
        document.getElementById('position').value = 'Nurse';
    } else if (role === 'staff') {
        document.getElementById('staff-fields').style.display = 'block';
        document.getElementById('position').value = '';
    }
}

function openEditModal(staff) {
    document.getElementById('edit_staff_id').value = staff.staffId;
    document.getElementById('edit_user_id').value = staff.userId;
    document.getElementById('edit_staff_role').value = staff.role;
    document.getElementById('edit_first_name').value = staff.firstName;
    document.getElementById('edit_last_name').value = staff.lastName;
    document.getElementById('edit_email').value = staff.email;
    document.getElementById('edit_phone_number').value = staff.phoneNumber || '';
    document.getElementById('edit_license_number').value = staff.licenseNumber || '';
    document.getElementById('edit_department').value = staff.department || '';
    document.getElementById('edit_position').value = staff.position || '';
    
    document.getElementById('edit-doctor-fields').style.display = 'none';
    document.getElementById('edit-nurse-fields').style.display = 'none';
    
    if (staff.role === 'doctor') {
        document.getElementById('edit-doctor-fields').style.display = 'block';
        document.getElementById('edit_specialization').value = staff.specialization || '';
        document.getElementById('edit_consultation_fee').value = staff.consultationFee || '';
        document.getElementById('edit_years_of_experience').value = staff.yearsOfExperience || '';
        document.getElementById('edit_education').value = staff.education || '';
        document.getElementById('edit_biography').value = staff.biography || '';
        document.getElementById('edit_is_available').checked = staff.isAvailable == 1;
    } else if (staff.role === 'nurse') {
        document.getElementById('edit-nurse-fields').style.display = 'block';
        document.getElementById('edit_nursing_specialty').value = staff.nursingSpecialty || '';
        document.getElementById('edit_certification').value = staff.certification || '';
    }
    
    openModal('editModal');
}

document.getElementById('staff-form')?.addEventListener('submit', function(e) {
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

document.addEventListener('DOMContentLoaded', function() {
    toggleRoleFields();
});
</script>

<?php include '../includes/footer.php'; ?>