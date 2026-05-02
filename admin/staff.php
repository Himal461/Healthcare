<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Staff - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

// Handle delete
if (isset($_GET['delete'])) {
    $staffId = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("
            SELECT u.userId, u.firstName, u.lastName, u.role 
            FROM users u 
            JOIN staff s ON u.userId = s.userId 
            WHERE s.staffId = ?
        ");
        $stmt->execute([$staffId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $stmt = $pdo->prepare("DELETE FROM staff WHERE staffId = ?");
            $stmt->execute([$staffId]);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE userId = ?");
            $stmt->execute([$user['userId']]);
            
            $_SESSION['success'] = "Staff member '{$user['firstName']} {$user['lastName']}' deleted successfully!";
            logAction($_SESSION['user_id'], 'DELETE_STAFF', "Deleted staff ID: {$staffId}");
        } else {
            $_SESSION['error'] = "Staff member not found.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete staff: " . $e->getMessage();
    }
    header("Location: staff.php");
    exit();
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

// Get all staff with salary
$staff = $pdo->query("
    SELECT s.*, u.userId, u.username, u.firstName, u.lastName, u.email, u.phoneNumber, u.role, u.dateCreated,
           d.specialization, d.consultationFee, d.yearsOfExperience, d.education, d.biography, d.isAvailable,
           n.nursingSpecialty, n.certification,
           a.qualification, a.certification as accountant_cert, a.specialization as accountant_specialization, 
           a.yearsOfExperience as accountant_experience,
           COALESCE(s.salary, 2500.00) as salary
    FROM staff s
    JOIN users u ON s.userId = u.userId
    LEFT JOIN doctors d ON s.staffId = d.staffId
    LEFT JOIN nurses n ON s.staffId = n.staffId
    LEFT JOIN accountants a ON s.staffId = a.staffId
    WHERE u.role IN ('doctor', 'nurse', 'staff', 'admin', 'accountant')
    ORDER BY FIELD(u.role, 'admin', 'doctor', 'nurse', 'accountant', 'staff'), u.firstName, u.lastName
")->fetchAll();

$departments = $pdo->query("SELECT DISTINCT name FROM departments WHERE isActive = 1 ORDER BY name")->fetchAll();
$staffByRole = [];
foreach ($staff as $member) {
    $staffByRole[$member['role']][] = $member;
}
$roleOrder = ['admin', 'doctor', 'nurse', 'accountant', 'staff'];
$roleIcons = [
    'admin' => 'fa-crown',
    'doctor' => 'fa-user-md',
    'nurse' => 'fa-user-nurse',
    'accountant' => 'fa-calculator',
    'staff' => 'fa-user-tie'
];
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-md"></i> Manage Staff</h1>
            <p>Add and manage doctors, nurses, accountants, and support staff</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Create Staff Form -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-user-plus"></i> Add New Staff Member</h3>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="create-staff.php" id="create-staff-form">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="admin-form-control" required value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="admin-form-control" required value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" class="admin-form-control" required value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="admin-form-control" required value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone_number" class="admin-form-control" value="<?php echo htmlspecialchars($formData['phone_number'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Staff Role <span class="required">*</span></label>
                        <select name="staff_role" id="staff_role" class="admin-form-control" required onchange="toggleRoleForm()">
                            <option value="">-- Select Role --</option>
                            <option value="staff" <?php echo ($formData['staff_role'] ?? '') == 'staff' ? 'selected' : ''; ?>>Support Staff</option>
                            <option value="nurse" <?php echo ($formData['staff_role'] ?? '') == 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                            <option value="doctor" <?php echo ($formData['staff_role'] ?? '') == 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="accountant" <?php echo ($formData['staff_role'] ?? '') == 'accountant' ? 'selected' : ''; ?>>Accountant</option>
                            <option value="admin" <?php echo ($formData['staff_role'] ?? '') == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" class="admin-form-control" required>
                        <small>Minimum 8 characters</small>
                    </div>
                    <div class="admin-form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="admin-form-control" required>
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>License/Certificate Number</label>
                        <input type="text" name="license_number" class="admin-form-control" value="<?php echo htmlspecialchars($formData['license_number'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Hire Date</label>
                        <input type="date" name="hire_date" class="admin-form-control" value="<?php echo htmlspecialchars($formData['hire_date'] ?? date('Y-m-d')); ?>">
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Department</label>
                        <select name="department" class="admin-form-control">
                            <option value="">Select Department</option>
                            <option value="Finance" <?php echo ($formData['department'] ?? '') == 'Finance' ? 'selected' : ''; ?>>Finance</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['name']); ?>" <?php echo ($formData['department'] ?? '') == $dept['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label>Position Title</label>
                        <input type="text" name="position" id="position" class="admin-form-control" value="<?php echo htmlspecialchars($formData['position'] ?? ''); ?>" placeholder="e.g., Senior Accountant">
                    </div>
                </div>
                
                <!-- SALARY FIELD ADDED -->
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Base Salary ($) <span class="required">*</span></label>
                        <input type="number" name="salary" class="admin-form-control" step="0.01" min="0" 
                               value="<?php echo htmlspecialchars($formData['salary'] ?? '2500.00'); ?>" required>
                        <small>Fixed monthly salary</small>
                    </div>
                    <div class="admin-form-group">
                        <!-- Empty div for layout balance -->
                    </div>
                </div>
                
                <!-- Doctor Fields -->
                <div id="doctor-fields" style="display: none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Specialization</label>
                            <input type="text" name="specialization" class="admin-form-control" value="<?php echo htmlspecialchars($formData['specialization'] ?? ''); ?>" placeholder="e.g., Cardiology">
                        </div>
                        <div class="admin-form-group">
                            <label>Consultation Fee ($)</label>
                            <input type="number" name="consultation_fee" class="admin-form-control" step="10" value="<?php echo htmlspecialchars($formData['consultation_fee'] ?? '150'); ?>">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Years of Experience</label>
                            <input type="number" name="years_of_experience" class="admin-form-control" value="<?php echo htmlspecialchars($formData['years_of_experience'] ?? ''); ?>">
                        </div>
                        <div class="admin-form-group">
                            <label>Education & Qualifications</label>
                            <input type="text" name="education" class="admin-form-control" value="<?php echo htmlspecialchars($formData['education'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label>Professional Biography</label>
                        <textarea name="biography" rows="3" class="admin-form-control"><?php echo htmlspecialchars($formData['biography'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Nurse Fields -->
                <div id="nurse-fields" style="display: none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Nursing Specialty</label>
                            <input type="text" name="nursing_specialty" class="admin-form-control" value="<?php echo htmlspecialchars($formData['nursing_specialty'] ?? ''); ?>">
                        </div>
                        <div class="admin-form-group">
                            <label>Certification</label>
                            <input type="text" name="certification" class="admin-form-control" value="<?php echo htmlspecialchars($formData['certification'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Accountant Fields -->
                <div id="accountant-fields" style="display: none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Qualification</label>
                            <input type="text" name="qualification" class="admin-form-control" value="<?php echo htmlspecialchars($formData['qualification'] ?? ''); ?>" placeholder="e.g., CPA, MBA Finance">
                        </div>
                        <div class="admin-form-group">
                            <label>Certification</label>
                            <input type="text" name="accountant_certification" class="admin-form-control" value="<?php echo htmlspecialchars($formData['accountant_certification'] ?? ''); ?>" placeholder="e.g., Certified Public Accountant">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Specialization</label>
                            <input type="text" name="accountant_specialization" class="admin-form-control" value="<?php echo htmlspecialchars($formData['accountant_specialization'] ?? 'General Accounting'); ?>" placeholder="e.g., Healthcare Finance">
                        </div>
                        <div class="admin-form-group">
                            <label>Years of Experience</label>
                            <input type="number" name="accountant_experience" class="admin-form-control" value="<?php echo htmlspecialchars($formData['accountant_experience'] ?? '0'); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Admin Fields -->
                <div id="admin-fields" style="display: none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Admin Level</label>
                            <select name="admin_level" class="admin-form-control">
                                <option value="regular" <?php echo ($formData['admin_level'] ?? '') == 'regular' ? 'selected' : ''; ?>>Regular Admin</option>
                                <option value="super" <?php echo ($formData['admin_level'] ?? '') == 'super' ? 'selected' : ''; ?>>Super Admin</option>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label>Permissions (JSON)</label>
                            <input type="text" name="permissions" class="admin-form-control" value="<?php echo htmlspecialchars($formData['permissions'] ?? '{"all":true}'); ?>" placeholder='{"all":true}'>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fas fa-save"></i> Add Staff Member
                </button>
            </form>
        </div>
    </div>

    <!-- Staff List -->
    <?php foreach ($roleOrder as $role): ?>
        <?php if (!empty($staffByRole[$role])): ?>
            <div class="admin-role-section">
                <div class="admin-role-header">
                    <h2>
                        <span class="admin-role-icon role-<?php echo $role; ?>">
                            <i class="fas <?php echo $roleIcons[$role]; ?>"></i>
                        </span>
                        <?php echo ucfirst($role); ?>s
                        <span class="admin-count-badge"><?php echo count($staffByRole[$role]); ?></span>
                    </h2>
                </div>
                <div class="admin-staff-grid">
                    <?php foreach ($staffByRole[$role] as $member): ?>
                        <div class="admin-staff-card">
                            <div class="admin-staff-card-header">
                                <div class="admin-staff-avatar">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div class="admin-staff-info">
                                    <h3><?php echo htmlspecialchars($member['firstName'] . ' ' . $member['lastName']); ?></h3>
                                    <span class="admin-staff-role admin-role-<?php echo $member['role']; ?>">
                                        <?php echo ucfirst($member['role']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="admin-staff-card-body">
                                <div class="admin-info-row">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($member['email']); ?></span>
                                </div>
                                <div class="admin-info-row">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($member['phoneNumber'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="admin-info-row">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($member['department'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="admin-info-row">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span><strong>$<?php echo number_format($member['salary'], 2); ?></strong> / month</span>
                                </div>
                                <?php if ($member['role'] == 'doctor' && $member['specialization']): ?>
                                    <div class="admin-info-row">
                                        <i class="fas fa-stethoscope"></i>
                                        <span><?php echo htmlspecialchars($member['specialization']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="admin-staff-card-footer">
                                <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" onclick='openEditStaffModal(<?php echo json_encode($member); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?delete=<?php echo $member['staffId']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Are you sure you want to delete this staff member?\nThis action cannot be undone.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- Edit Staff Modal -->
<div id="editStaffModal" class="admin-modal">
    <div class="admin-modal-content admin-modal-large">
        <div class="admin-modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Staff Member</h3>
            <span class="admin-modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" action="update-staff.php" id="edit-staff-form">
            <div class="admin-modal-body">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="staff_role" id="edit_staff_role">
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" id="edit_first_name" name="first_name" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" id="edit_last_name" name="last_name" class="admin-form-control" required>
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" id="edit_email" name="email" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Phone Number</label>
                        <input type="tel" id="edit_phone_number" name="phone_number" class="admin-form-control">
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>License Number</label>
                        <input type="text" id="edit_license_number" name="license_number" class="admin-form-control">
                    </div>
                    <div class="admin-form-group">
                        <label>Department</label>
                        <input type="text" id="edit_department" name="department" class="admin-form-control">
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Position</label>
                        <input type="text" id="edit_position" name="position" class="admin-form-control">
                    </div>
                    <div class="admin-form-group">
                        <label>Salary ($)</label>
                        <input type="number" id="edit_salary" name="salary" class="admin-form-control" step="0.01" min="0">
                    </div>
                </div>
                
                <!-- Doctor Edit Fields -->
                <div id="edit-doctor-fields" style="display: none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Specialization</label>
                            <input type="text" id="edit_specialization" name="specialization" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Consultation Fee ($)</label>
                            <input type="number" id="edit_consultation_fee" name="consultation_fee" class="admin-form-control" step="10">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Years of Experience</label>
                            <input type="number" id="edit_years_of_experience" name="years_of_experience" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Education</label>
                            <input type="text" id="edit_education" name="education" class="admin-form-control">
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label>Biography</label>
                        <textarea id="edit_biography" name="biography" rows="3" class="admin-form-control"></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label>
                            <input type="checkbox" id="edit_is_available" name="is_available" value="1"> 
                            Available for appointments
                        </label>
                    </div>
                </div>
                
                <!-- Nurse Edit Fields -->
                <div id="edit-nurse-fields" style="display: none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Nursing Specialty</label>
                            <input type="text" id="edit_nursing_specialty" name="nursing_specialty" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Certification</label>
                            <input type="text" id="edit_certification" name="certification" class="admin-form-control">
                        </div>
                    </div>
                </div>
                
                <!-- Accountant Edit Fields -->
                <div id="edit-accountant-fields" style="display: none;">
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Qualification</label>
                            <input type="text" id="edit_qualification" name="qualification" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Certification</label>
                            <input type="text" id="edit_accountant_certification" name="accountant_certification" class="admin-form-control">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Specialization</label>
                            <input type="text" id="edit_accountant_specialization" name="accountant_specialization" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label>Years of Experience</label>
                            <input type="number" id="edit_accountant_experience" name="accountant_experience" class="admin-form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fas fa-save"></i> Update Staff
                </button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    toggleRoleForm();
});

function toggleRoleForm() {
    const role = document.getElementById('staff_role')?.value || '';
    
    const staffFields = document.getElementById('staff-fields');
    const doctorFields = document.getElementById('doctor-fields');
    const nurseFields = document.getElementById('nurse-fields');
    const adminFields = document.getElementById('admin-fields');
    const accountantFields = document.getElementById('accountant-fields');
    
    if (doctorFields) doctorFields.style.display = 'none';
    if (nurseFields) nurseFields.style.display = 'none';
    if (adminFields) adminFields.style.display = 'none';
    if (accountantFields) accountantFields.style.display = 'none';
    
    if (role === 'doctor') {
        if (doctorFields) doctorFields.style.display = 'block';
    } else if (role === 'nurse') {
        if (nurseFields) nurseFields.style.display = 'block';
    } else if (role === 'admin') {
        if (adminFields) adminFields.style.display = 'block';
    } else if (role === 'accountant') {
        if (accountantFields) accountantFields.style.display = 'block';
    }
    
    const positionField = document.getElementById('position');
    if (positionField) {
        if (role === 'doctor') positionField.value = 'Doctor';
        else if (role === 'nurse') positionField.value = 'Nurse';
        else if (role === 'admin') positionField.value = 'Administrator';
        else if (role === 'accountant') positionField.value = 'Accountant';
        else if (role === 'staff') positionField.value = '';
    }
}

function openEditStaffModal(staff) {
    document.getElementById('edit_staff_id').value = staff.staffId;
    document.getElementById('edit_user_id').value = staff.userId;
    document.getElementById('edit_staff_role').value = staff.role;
    document.getElementById('edit_first_name').value = staff.firstName || '';
    document.getElementById('edit_last_name').value = staff.lastName || '';
    document.getElementById('edit_email').value = staff.email || '';
    document.getElementById('edit_phone_number').value = staff.phoneNumber || '';
    document.getElementById('edit_license_number').value = staff.licenseNumber || '';
    document.getElementById('edit_department').value = staff.department || '';
    document.getElementById('edit_position').value = staff.position || '';
    document.getElementById('edit_salary').value = staff.salary || '2500.00';
    
    const doctorFields = document.getElementById('edit-doctor-fields');
    const nurseFields = document.getElementById('edit-nurse-fields');
    const accountantFields = document.getElementById('edit-accountant-fields');
    
    if (doctorFields) doctorFields.style.display = 'none';
    if (nurseFields) nurseFields.style.display = 'none';
    if (accountantFields) accountantFields.style.display = 'none';
    
    if (staff.role === 'doctor') {
        if (doctorFields) doctorFields.style.display = 'block';
        document.getElementById('edit_specialization').value = staff.specialization || '';
        document.getElementById('edit_consultation_fee').value = staff.consultationFee || '';
        document.getElementById('edit_years_of_experience').value = staff.yearsOfExperience || '';
        document.getElementById('edit_education').value = staff.education || '';
        document.getElementById('edit_biography').value = staff.biography || '';
        document.getElementById('edit_is_available').checked = staff.isAvailable == 1;
    } else if (staff.role === 'nurse') {
        if (nurseFields) nurseFields.style.display = 'block';
        document.getElementById('edit_nursing_specialty').value = staff.nursingSpecialty || '';
        document.getElementById('edit_certification').value = staff.certification || '';
    } else if (staff.role === 'accountant') {
        if (accountantFields) accountantFields.style.display = 'block';
        document.getElementById('edit_qualification').value = staff.qualification || '';
        document.getElementById('edit_accountant_certification').value = staff.accountant_cert || '';
        document.getElementById('edit_accountant_specialization').value = staff.accountant_specialization || 'General Accounting';
        document.getElementById('edit_accountant_experience').value = staff.accountant_experience || 0;
    }
    
    openModal('editStaffModal');
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editStaffModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('admin-modal')) {
        event.target.style.display = 'none';
    }
}

document.getElementById('create-staff-form')?.addEventListener('submit', function(e) {
    const password = document.querySelector('[name="password"]').value;
    const confirm = document.querySelector('[name="confirm_password"]').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long!');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>