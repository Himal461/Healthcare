<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Departments - HealthManagement";
include '../includes/header.php';

// Define all departments with their details
$departmentsData = [
    ['name' => 'Cardiology', 'description' => 'Heart and cardiovascular system care', 'location' => 'Building A, Floor 3', 'phone' => '+61 2 1234 5601', 'email' => 'cardiology@healthmanagement.com'],
    ['name' => 'Neurology', 'description' => 'Brain and nervous system care', 'location' => 'Building A, Floor 4', 'phone' => '+61 2 1234 5602', 'email' => 'neurology@healthmanagement.com'],
    ['name' => 'Pediatrics', 'description' => 'Child healthcare services', 'location' => 'Building B, Floor 1', 'phone' => '+61 2 1234 5603', 'email' => 'pediatrics@healthmanagement.com'],
    ['name' => 'Orthopedics', 'description' => 'Bone and joint care', 'location' => 'Building B, Floor 2', 'phone' => '+61 2 1234 5604', 'email' => 'orthopedics@healthmanagement.com'],
    ['name' => 'Dermatology', 'description' => 'Skin care services', 'location' => 'Building C, Floor 1', 'phone' => '+61 2 1234 5605', 'email' => 'dermatology@healthmanagement.com'],
    ['name' => 'Ophthalmology', 'description' => 'Eye care services', 'location' => 'Building C, Floor 2', 'phone' => '+61 2 1234 5606', 'email' => 'ophthalmology@healthmanagement.com'],
    ['name' => 'Obstetrics & Gynecology', 'description' => 'Women\'s health services', 'location' => 'Building D, Floor 1', 'phone' => '+61 2 1234 5607', 'email' => 'obgyn@healthmanagement.com'],
    ['name' => 'Radiology', 'description' => 'Medical imaging services', 'location' => 'Building D, Floor 2', 'phone' => '+61 2 1234 5608', 'email' => 'radiology@healthmanagement.com'],
    ['name' => 'Emergency Medicine', 'description' => 'Emergency care services - 24/7', 'location' => 'Building E, Ground Floor', 'phone' => '+61 2 1234 5609', 'email' => 'emergency@healthmanagement.com'],
    ['name' => 'Primary Care', 'description' => 'General medicine and family practice', 'location' => 'Building A, Floor 2', 'phone' => '+61 2 1234 5610', 'email' => 'primarycare@healthmanagement.com'],
    ['name' => 'Urology', 'description' => 'Urinary tract and male reproductive health', 'location' => 'Building E, Floor 1', 'phone' => '+61 2 1234 5611', 'email' => 'urology@healthmanagement.com'],
    ['name' => 'Gastroenterology', 'description' => 'Digestive system care', 'location' => 'Building E, Floor 2', 'phone' => '+61 2 1234 5612', 'email' => 'gastro@healthmanagement.com'],
    ['name' => 'Pulmonology', 'description' => 'Respiratory system care', 'location' => 'Building F, Floor 1', 'phone' => '+61 2 1234 5613', 'email' => 'pulmonology@healthmanagement.com'],
    ['name' => 'Endocrinology', 'description' => 'Hormone and metabolic disorders', 'location' => 'Building F, Floor 2', 'phone' => '+61 2 1234 5614', 'email' => 'endocrinology@healthmanagement.com'],
    ['name' => 'Oncology', 'description' => 'Cancer treatment and care', 'location' => 'Building G, Floor 1', 'phone' => '+61 2 1234 5615', 'email' => 'oncology@healthmanagement.com'],
    ['name' => 'Psychiatry', 'description' => 'Mental health services', 'location' => 'Building G, Floor 2', 'phone' => '+61 2 1234 5616', 'email' => 'psychiatry@healthmanagement.com'],
    ['name' => 'Nephrology', 'description' => 'Kidney care and dialysis', 'location' => 'Building H, Floor 1', 'phone' => '+61 2 1234 5617', 'email' => 'nephrology@healthmanagement.com'],
    ['name' => 'Rheumatology', 'description' => 'Autoimmune and joint disorders', 'location' => 'Building H, Floor 2', 'phone' => '+61 2 1234 5618', 'email' => 'rheumatology@healthmanagement.com'],
    ['name' => 'Infectious Disease', 'description' => 'Infection management', 'location' => 'Building I, Floor 1', 'phone' => '+61 2 1234 5619', 'email' => 'infectious@healthmanagement.com'],
    ['name' => 'Hematology', 'description' => 'Blood disorders', 'location' => 'Building I, Floor 2', 'phone' => '+61 2 1234 5620', 'email' => 'hematology@healthmanagement.com']
];

// Check if departments need to be populated
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments");
$checkStmt->execute();
if ($checkStmt->fetchColumn() == 0) {
    foreach ($departmentsData as $dept) {
        $stmt = $pdo->prepare("INSERT INTO departments (name, description, location, phoneNumber, email, isActive) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$dept['name'], $dept['description'], $dept['location'], $dept['phone'], $dept['email']]);
    }
    $_SESSION['success'] = "Default departments added successfully!";
}

// Handle department actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $location = sanitizeInput($_POST['location']);
        $phoneNumber = sanitizeInput($_POST['phone_number']);
        $email = sanitizeInput($_POST['email']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO departments (name, description, location, phoneNumber, email, isActive) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$name, $description, $location, $phoneNumber, $email]);
            $_SESSION['success'] = "Department added successfully!";
            logAction($_SESSION['user_id'], 'ADD_DEPARTMENT', "Added department: $name");
            header("Location: departments.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to add department: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_department'])) {
        $deptId = $_POST['department_id'];
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $location = sanitizeInput($_POST['location']);
        $phoneNumber = sanitizeInput($_POST['phone_number']);
        $email = sanitizeInput($_POST['email']);
        $headDoctorId = $_POST['head_doctor_id'] ?: null;
        
        try {
            $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ?, location = ?, phoneNumber = ?, email = ?, headDoctorId = ? WHERE departmentId = ?");
            $stmt->execute([$name, $description, $location, $phoneNumber, $email, $headDoctorId, $deptId]);
            $_SESSION['success'] = "Department updated successfully!";
            header("Location: departments.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to update department: " . $e->getMessage();
        }
    }
}

// Handle delete (deactivate)
if (isset($_GET['delete'])) {
    $deptId = $_GET['delete'];
    $stmt = $pdo->prepare("UPDATE departments SET isActive = 0 WHERE departmentId = ?");
    $stmt->execute([$deptId]);
    $_SESSION['success'] = "Department deactivated successfully!";
    header("Location: departments.php");
    exit();
}

// Get all active departments
$departments = $pdo->query("
    SELECT d.*, CONCAT(u.firstName, ' ', u.lastName) as headDoctorName
    FROM departments d
    LEFT JOIN doctors doc ON d.headDoctorId = doc.doctorId
    LEFT JOIN staff s ON doc.staffId = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    WHERE d.isActive = 1
    ORDER BY d.name
")->fetchAll();

// Get doctors for head doctor selection
$doctors = $pdo->query("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name, d.specialization
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.isAvailable = 1
    ORDER BY u.firstName
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Departments</h1>
        <p>Organize and manage hospital departments</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Department Statistics -->
    <div class="stats-grid">
        <div class="stat-card admin">
            <h3><?php echo count($departments); ?></h3>
            <p>Active Departments</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo count($departmentsData); ?></h3>
            <p>Total Specialties</p>
        </div>
    </div>

    <!-- Add Department Form -->
    <div class="card">
        <div class="card-header">
            <h3>Add New Department</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Department Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="text" id="phone_number" name="phone_number">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
            </form>
        </div>
    </div>

    <!-- Departments List -->
    <div class="card">
        <div class="card-header">
            <h3>All Departments</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Head Doctor</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td data-label="Name">
                            <strong><?php echo $dept['name']; ?></strong><br>
                            <small><?php echo substr($dept['description'], 0, 50) . (strlen($dept['description']) > 50 ? '...' : ''); ?></small>
                        </td>
                        <td data-label="Location"><?php echo $dept['location']; ?></td>
                        <td data-label="Phone"><?php echo $dept['phoneNumber']; ?></td>
                        <td data-label="Email"><?php echo $dept['email']; ?></td>
                        <td data-label="Head Doctor"><?php echo $dept['headDoctorName'] ?: 'Not assigned'; ?></td>
                        <td data-label="Actions">
                            <button class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($dept)); ?>)">Edit</button>
                            <a href="?delete=<?php echo $dept['departmentId']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Deactivate</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Department</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="department_id" id="edit_dept_id">
                <div class="form-group">
                    <label for="edit_name">Department Name *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_location">Location</label>
                    <input type="text" id="edit_location" name="location">
                </div>
                
                <div class="form-group">
                    <label for="edit_phone_number">Phone Number</label>
                    <input type="text" id="edit_phone_number" name="phone_number">
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="edit_head_doctor_id">Head Doctor</label>
                    <select id="edit_head_doctor_id" name="head_doctor_id">
                        <option value="">Select Head Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['doctorId']; ?>">Dr. <?php echo $doctor['name']; ?> (<?php echo $doctor['specialization']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(dept) {
    document.getElementById('edit_dept_id').value = dept.departmentId;
    document.getElementById('edit_name').value = dept.name;
    document.getElementById('edit_location').value = dept.location || '';
    document.getElementById('edit_phone_number').value = dept.phoneNumber || '';
    document.getElementById('edit_email').value = dept.email || '';
    document.getElementById('edit_description').value = dept.description || '';
    document.getElementById('edit_head_doctor_id').value = dept.headDoctorId || '';
    openModal('editModal');
}
</script>

<?php include '../includes/footer.php'; ?>