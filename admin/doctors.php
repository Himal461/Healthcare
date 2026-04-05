<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Doctors - HealthManagement";
include '../includes/header.php';

// Define all medical specializations
$specializationsList = [
    'Cardiology' => 'Heart and Cardiovascular System',
    'Neurology' => 'Brain and Nervous System',
    'Pediatrics' => 'Child Healthcare',
    'Orthopedics' => 'Bone and Joint',
    'Dermatology' => 'Skin Care',
    'Ophthalmology' => 'Eye Care',
    'Obstetrics & Gynecology' => 'Women\'s Health',
    'Radiology' => 'Medical Imaging',
    'Emergency Medicine' => 'Emergency Care',
    'Primary Care' => 'General Medicine',
    'Urology' => 'Urinary Tract and Male Reproductive Health',
    'Gastroenterology' => 'Digestive System',
    'Pulmonology' => 'Respiratory System',
    'Endocrinology' => 'Hormone and Metabolic Disorders',
    'Oncology' => 'Cancer Treatment',
    'Psychiatry' => 'Mental Health',
    'Nephrology' => 'Kidney Care',
    'Rheumatology' => 'Autoimmune and Joint Disorders',
    'Infectious Disease' => 'Infection Management',
    'Hematology' => 'Blood Disorders'
];

// Handle doctor actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_doctor'])) {
        $userId = $_POST['user_id'];
        $specialization = sanitizeInput($_POST['specialization']);
        $consultationFee = floatval($_POST['consultation_fee']);
        $yearsOfExperience = intval($_POST['years_of_experience']);
        $education = sanitizeInput($_POST['education']);
        $biography = sanitizeInput($_POST['biography']);
        
        try {
            $pdo->beginTransaction();
            
            // Create staff record first
            $stmt = $pdo->prepare("INSERT INTO staff (userId, licenseNumber, hireDate, department, position) VALUES (?, ?, CURDATE(), ?, 'Doctor')");
            $stmt->execute([$userId, $_POST['license_number'], $specialization]);
            $staffId = $pdo->lastInsertId();
            
            // Create doctor record
            $stmt = $pdo->prepare("INSERT INTO doctors (staffId, specialization, consultationFee, yearsOfExperience, education, biography, isAvailable) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$staffId, $specialization, $consultationFee, $yearsOfExperience, $education, $biography]);
            
            $pdo->commit();
            $_SESSION['success'] = "Doctor added successfully!";
            logAction($_SESSION['user_id'], 'ADD_DOCTOR', "Added new doctor: $specialization");
            header("Location: doctors.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to add doctor: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_availability'])) {
        $doctorId = $_POST['doctor_id'];
        
        // Delete existing availability
        $stmt = $pdo->prepare("DELETE FROM doctor_availability WHERE doctorId = ?");
        $stmt->execute([$doctorId]);
        
        // Insert new availability
        for ($i = 0; $i <= 6; $i++) {
            if (isset($_POST["day_{$i}_available"])) {
                $startTime = $_POST["day_{$i}_start"];
                $endTime = $_POST["day_{$i}_end"];
                $stmt = $pdo->prepare("INSERT INTO doctor_availability (doctorId, dayOfWeek, startTime, endTime, isAvailable) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$doctorId, $i, $startTime, $endTime]);
            }
        }
        
        $_SESSION['success'] = "Doctor availability updated!";
        logAction($_SESSION['user_id'], 'UPDATE_DOCTOR_AVAILABILITY', "Updated availability for doctor ID: $doctorId");
        header("Location: doctors.php");
        exit();
    } elseif (isset($_POST['assign_department'])) {
        $doctorId = $_POST['doctor_id'];
        $departmentId = $_POST['department_id'];
        
        $stmt = $pdo->prepare("INSERT INTO doctor_department (doctorId, departmentId) VALUES (?, ?)");
        $stmt->execute([$doctorId, $departmentId]);
        
        $_SESSION['success'] = "Doctor assigned to department successfully!";
        header("Location: doctors.php");
        exit();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $doctorId = $_GET['delete'];
    $stmt = $pdo->prepare("UPDATE doctors SET isAvailable = 0 WHERE doctorId = ?");
    $stmt->execute([$doctorId]);
    $_SESSION['success'] = "Doctor removed successfully!";
    logAction($_SESSION['user_id'], 'DELETE_DOCTOR', "Removed doctor ID: $doctorId");
    header("Location: doctors.php");
    exit();
}

// Get all doctors with details
$doctors = $pdo->query("
    SELECT d.*, u.firstName, u.lastName, u.email, u.phoneNumber, s.licenseNumber, s.hireDate
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    ORDER BY u.firstName
")->fetchAll();

// Get all users for dropdown (excluding doctors and admins)
$users = $pdo->query("SELECT userId, firstName, lastName, email FROM users WHERE role NOT IN ('doctor', 'admin')")->fetchAll();

// Get departments
$departments = $pdo->query("SELECT * FROM departments WHERE isActive = 1")->fetchAll();

// Get doctor availability
$availability = $pdo->query("SELECT * FROM doctor_availability")->fetchAll();
$availabilityByDoctor = [];
foreach ($availability as $avail) {
    $availabilityByDoctor[$avail['doctorId']][$avail['dayOfWeek']] = $avail;
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Doctors</h1>
        <p>Add, edit, and manage doctor profiles and availability</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Add Doctor Form -->
    <div class="card">
        <div class="card-header">
            <h3>Add New Doctor</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_id">Select User *</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Select a user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['userId']; ?>">
                                    <?php echo $user['firstName'] . ' ' . $user['lastName'] . ' (' . $user['email'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="license_number">License Number *</label>
                        <input type="text" id="license_number" name="license_number" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="specialization">Specialization *</label>
                        <select id="specialization" name="specialization" required>
                            <option value="">Select Specialization</option>
                            <?php foreach ($specializationsList as $spec => $desc): ?>
                                <option value="<?php echo $spec; ?>"><?php echo $spec; ?> - <?php echo $desc; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="consultation_fee">Consultation Fee ($) *</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" step="10" required>
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
                    <label for="biography">Biography</label>
                    <textarea id="biography" name="biography" rows="3" placeholder="Professional background and experience..."></textarea>
                </div>
                
                <button type="submit" name="add_doctor" class="btn btn-primary">Add Doctor</button>
            </form>
        </div>
    </div>

    <!-- Doctors List -->
    <div class="card">
        <div class="card-header">
            <h3>All Doctors</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>License</th>
                        <th>Fee</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td data-label="Name">Dr. <?php echo $doctor['firstName'] . ' ' . $doctor['lastName']; ?></td>
                        <td data-label="Specialization">
                            <strong><?php echo $doctor['specialization']; ?></strong><br>
                            <small><?php echo $specializationsList[$doctor['specialization']] ?? ''; ?></small>
                        </td>
                        <td data-label="License"><?php echo $doctor['licenseNumber']; ?></td>
                        <td data-label="Fee">$<?php echo number_format($doctor['consultationFee'], 2); ?></td>
                        <td data-label="Experience"><?php echo $doctor['yearsOfExperience']; ?> years</td>
                        <td data-label="Status">
                            <span class="status-badge <?php echo $doctor['isAvailable'] ? 'status-active' : 'status-cancelled'; ?>">
                                <?php echo $doctor['isAvailable'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="openModal('availabilityModal'); document.getElementById('avail_doctor_id').value = <?php echo $doctor['doctorId']; ?>;">Set Availability</button>
                                <button class="btn btn-primary btn-sm" onclick="openModal('departmentModal'); document.getElementById('dept_doctor_id').value = <?php echo $doctor['doctorId']; ?>;">Assign Dept</button>
                                <a href="?delete=<?php echo $doctor['doctorId']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Remove</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Availability Modal -->
<div id="availabilityModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Set Doctor Availability</h3>
            <span class="close" onclick="closeModal('availabilityModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="doctor_id" id="avail_doctor_id">
                <div class="availability-grid">
                    <?php
                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach ($days as $dayIndex => $dayName):
                    ?>
                        <div class="availability-day">
                            <label class="day-checkbox-label">
                                <input type="checkbox" name="day_<?php echo $dayIndex; ?>_available" class="day-checkbox" data-day="<?php echo $dayIndex; ?>">
                                <strong><?php echo $dayName; ?></strong>
                            </label>
                            <div class="availability-times" style="display: none;">
                                <div class="time-select">
                                    <label>Start:</label>
                                    <input type="time" name="day_<?php echo $dayIndex; ?>_start" value="09:00">
                                </div>
                                <div class="time-select">
                                    <label>End:</label>
                                    <input type="time" name="day_<?php echo $dayIndex; ?>_end" value="17:00">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_availability" class="btn btn-primary">Save Availability</button>
                <button type="button" class="btn" onclick="closeModal('availabilityModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Department Modal -->
<div id="departmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Assign Doctor to Department</h3>
            <span class="close" onclick="closeModal('departmentModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="doctor_id" id="dept_doctor_id">
                <div class="form-group">
                    <label for="department_id">Select Department</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Choose department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['departmentId']; ?>"><?php echo $dept['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="assign_department" class="btn btn-primary">Assign</button>
                <button type="button" class="btn" onclick="closeModal('departmentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Show/hide availability times based on checkbox
document.querySelectorAll('.day-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const timesDiv = this.closest('.availability-day').querySelector('.availability-times');
        if (timesDiv) {
            timesDiv.style.display = this.checked ? 'block' : 'none';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>