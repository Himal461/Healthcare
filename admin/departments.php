<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Departments - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

// Initialize default departments if none exist
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments");
$checkStmt->execute();
if ($checkStmt->fetchColumn() == 0) {
    $defaultDepts = [
        ['name' => 'Cardiology', 'description' => 'Heart and cardiovascular system care'],
        ['name' => 'Neurology', 'description' => 'Brain and nervous system care'],
        ['name' => 'Pediatrics', 'description' => 'Child healthcare services'],
        ['name' => 'Orthopedics', 'description' => 'Bone and joint care'],
        ['name' => 'Emergency Medicine', 'description' => 'Emergency care services - 24/7'],
        ['name' => 'Radiology', 'description' => 'Medical imaging services'],
        ['name' => 'Dermatology', 'description' => 'Skin care services'],
        ['name' => 'Ophthalmology', 'description' => 'Eye care services'],
        ['name' => 'General Medicine', 'description' => 'Primary care and general health']
    ];
    foreach ($defaultDepts as $dept) {
        $stmt = $pdo->prepare("INSERT INTO departments (name, description, isActive) VALUES (?, ?, 1)");
        $stmt->execute([$dept['name'], $dept['description']]);
    }
}

// Handle ADD department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $name = trim(sanitizeInput($_POST['name']));
    $description = trim(sanitizeInput($_POST['description']));
    $location = trim(sanitizeInput($_POST['location']));
    $phoneNumber = trim(sanitizeInput($_POST['phone_number']));
    $email = trim(sanitizeInput($_POST['email']));
    
    $errors = [];
    if (empty($name)) $errors[] = "Department name is required.";
    
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE name = ?");
    $checkStmt->execute([$name]);
    if ($checkStmt->fetchColumn() > 0) {
        $errors[] = "Department '$name' already exists.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO departments (name, description, location, phoneNumber, email, isActive) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $description, $location, $phoneNumber, $email]);
        $_SESSION['success'] = "Department '$name' added successfully!";
        logAction($_SESSION['user_id'], 'ADD_DEPARTMENT', "Added department: $name");
    } else {
        $_SESSION['error'] = implode(' ', $errors);
    }
    header("Location: departments.php");
    exit();
}

// Handle UPDATE department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_department'])) {
    $deptId = (int)$_POST['department_id'];
    $name = trim(sanitizeInput($_POST['name']));
    $description = trim(sanitizeInput($_POST['description']));
    $location = trim(sanitizeInput($_POST['location']));
    $phoneNumber = trim(sanitizeInput($_POST['phone_number']));
    $email = trim(sanitizeInput($_POST['email']));
    $headDoctorId = !empty($_POST['head_doctor_id']) ? (int)$_POST['head_doctor_id'] : null;
    
    $errors = [];
    if (empty($name)) $errors[] = "Department name is required.";
    
    // Check if name already exists for different department
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE name = ? AND departmentId != ?");
    $checkStmt->execute([$name, $deptId]);
    if ($checkStmt->fetchColumn() > 0) {
        $errors[] = "Department '$name' already exists.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE departments 
            SET name = ?, description = ?, location = ?, phoneNumber = ?, email = ?, headDoctorId = ?
            WHERE departmentId = ?
        ");
        $stmt->execute([$name, $description, $location, $phoneNumber, $email, $headDoctorId, $deptId]);
        $_SESSION['success'] = "Department '$name' updated successfully!";
        logAction($_SESSION['user_id'], 'UPDATE_DEPARTMENT', "Updated department ID: $deptId");
    } else {
        $_SESSION['error'] = implode(' ', $errors);
    }
    header("Location: departments.php");
    exit();
}

// Handle TOGGLE active/inactive
if (isset($_GET['toggle'])) {
    $deptId = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT name, isActive FROM departments WHERE departmentId = ?");
    $stmt->execute([$deptId]);
    $dept = $stmt->fetch();
    
    if ($dept) {
        $newStatus = $dept['isActive'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE departments SET isActive = ? WHERE departmentId = ?");
        $stmt->execute([$newStatus, $deptId]);
        $_SESSION['success'] = "Department '" . htmlspecialchars($dept['name']) . "' " . ($newStatus ? 'activated' : 'deactivated') . " successfully!";
        logAction($_SESSION['user_id'], 'TOGGLE_DEPARTMENT', "Toggled department ID: $deptId to " . ($newStatus ? 'active' : 'inactive'));
    }
    header("Location: departments.php");
    exit();
}

// Get all departments
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == 1;
$query = "
    SELECT d.*, CONCAT(u.firstName, ' ', u.lastName) as headDoctorName
    FROM departments d
    LEFT JOIN doctors doc ON d.headDoctorId = doc.doctorId
    LEFT JOIN staff s ON doc.staffId = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
";
if (!$showInactive) {
    $query .= " WHERE d.isActive = 1";
}
$query .= " ORDER BY d.name";

$departments = $pdo->query($query)->fetchAll();

// Get doctors for head doctor dropdown
$doctors = $pdo->query("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name, d.specialization
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE d.isAvailable = 1
    ORDER BY u.firstName
")->fetchAll();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-building"></i> Manage Departments</h1>
            <p>Add, edit, and manage hospital departments</p>
        </div>
        <div class="header-actions">
            <a href="?show_inactive=<?php echo $showInactive ? '0' : '1'; ?>" class="admin-btn admin-btn-outline">
                <i class="fas fa-<?php echo $showInactive ? 'eye-slash' : 'eye'; ?>"></i>
                <?php echo $showInactive ? 'Hide Inactive' : 'Show Inactive'; ?>
            </a>
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

    <!-- Add Department Form -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Department</h3>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Department Name <span class="required">*</span></label>
                        <input type="text" name="name" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="admin-form-control" placeholder="Building/Floor">
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" class="admin-form-control">
                    </div>
                    <div class="admin-form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="admin-form-control">
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="admin-form-control"></textarea>
                </div>
                <button type="submit" name="add_department" class="admin-btn admin-btn-primary">
                    <i class="fas fa-save"></i> Add Department
                </button>
            </form>
        </div>
    </div>

    <!-- Departments List -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> Departments (<?php echo count($departments); ?>)</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Head Doctor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td data-label="Name">
                                <strong><?php echo htmlspecialchars($dept['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars(substr($dept['description'] ?? '', 0, 50)); ?>...</small>
                            </td>
                            <td data-label="Location"><?php echo htmlspecialchars($dept['location'] ?: '-'); ?></td>
                            <td data-label="Contact">
                                <?php if ($dept['phoneNumber']): ?>
                                    <?php echo htmlspecialchars($dept['phoneNumber']); ?><br>
                                <?php endif; ?>
                                <?php if ($dept['email']): ?>
                                    <small><?php echo htmlspecialchars($dept['email']); ?></small>
                                <?php endif; ?>
                                <?php if (!$dept['phoneNumber'] && !$dept['email']): ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td data-label="Head Doctor">
                                <?php echo $dept['headDoctorName'] ? 'Dr. ' . htmlspecialchars($dept['headDoctorName']) : '<span class="admin-text-muted">Not assigned</span>'; ?>
                            </td>
                            <td data-label="Status">
                                <span class="admin-status-badge <?php echo $dept['isActive'] ? 'admin-status-active' : 'admin-status-inactive'; ?>">
                                    <?php echo $dept['isActive'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="admin-action-buttons">
                                    <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($dept)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?toggle=<?php echo $dept['departmentId']; ?>" class="admin-btn admin-btn-<?php echo $dept['isActive'] ? 'danger' : 'success'; ?> admin-btn-sm" onclick="return confirm('<?php echo $dept['isActive'] ? 'Deactivate' : 'Activate'; ?> this department?')">
                                        <i class="fas fa-<?php echo $dept['isActive'] ? 'ban' : 'check'; ?>"></i>
                                        <?php echo $dept['isActive'] ? 'Deactivate' : 'Activate'; ?>
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

<!-- Edit Department Modal -->
<div id="editModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3><i class="fas fa-edit"></i> Edit Department</h3>
            <span class="admin-modal-close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" id="editDepartmentForm">
            <div class="admin-modal-body">
                <input type="hidden" name="department_id" id="edit_dept_id">
                <div class="admin-form-group">
                    <label>Department Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_name" class="admin-form-control" required>
                </div>
                <div class="admin-form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="edit_location" class="admin-form-control">
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" id="edit_phone_number" class="admin-form-control">
                    </div>
                    <div class="admin-form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" class="admin-form-control">
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>Head Doctor</label>
                    <select name="head_doctor_id" id="edit_head_doctor_id" class="admin-form-control">
                        <option value="">-- Select Head Doctor --</option>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?php echo $doc['doctorId']; ?>">
                                Dr. <?php echo htmlspecialchars($doc['name']); ?> (<?php echo htmlspecialchars($doc['specialization']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3" class="admin-form-control"></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" name="update_department" class="admin-btn admin-btn-primary">
                    <i class="fas fa-save"></i> Update Department
                </button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('editModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Open edit modal with department data
function openEditModal(dept) {
    console.log('Opening edit modal for:', dept);
    
    document.getElementById('edit_dept_id').value = dept.departmentId || '';
    document.getElementById('edit_name').value = dept.name || '';
    document.getElementById('edit_location').value = dept.location || '';
    document.getElementById('edit_phone_number').value = dept.phoneNumber || '';
    document.getElementById('edit_email').value = dept.email || '';
    document.getElementById('edit_description').value = dept.description || '';
    
    // Set head doctor if exists
    if (dept.headDoctorId) {
        document.getElementById('edit_head_doctor_id').value = dept.headDoctorId;
    } else {
        document.getElementById('edit_head_doctor_id').value = '';
    }
    
    openModal('editModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('admin-modal')) {
        event.target.style.display = 'none';
    }
}

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.admin-modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }
});
</script>

<style>
.admin-action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.admin-btn-success {
    background: #10b981;
    color: white;
}
.admin-btn-success:hover {
    background: #059669;
}
.admin-text-muted {
    color: #94a3b8;
    font-style: italic;
}
</style>

<?php include '../includes/footer.php'; ?>