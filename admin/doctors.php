<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Doctors - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

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
    'Primary Care' => 'General Medicine'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $userId = $_POST['user_id'];
    $specialization = sanitizeInput($_POST['specialization']);
    $consultationFee = floatval($_POST['consultation_fee']);
    $yearsOfExperience = intval($_POST['years_of_experience']);
    $education = sanitizeInput($_POST['education']);
    $biography = sanitizeInput($_POST['biography']);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT staffId FROM staff WHERE userId = ?");
        $stmt->execute([$userId]);
        $staff = $stmt->fetch();
        
        if (!$staff) {
            $stmt = $pdo->prepare("INSERT INTO staff (userId, licenseNumber, hireDate, department, position) VALUES (?, ?, CURDATE(), ?, 'Doctor')");
            $stmt->execute([$userId, $_POST['license_number'] ?? '', $specialization]);
            $staffId = $pdo->lastInsertId();
        } else {
            $staffId = $staff['staffId'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO doctors (staffId, specialization, consultationFee, yearsOfExperience, education, biography, isAvailable) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$staffId, $specialization, $consultationFee, $yearsOfExperience, $education, $biography]);
        
        $pdo->commit();
        $_SESSION['success'] = "Doctor added successfully!";
        logAction($_SESSION['user_id'], 'ADD_DOCTOR', "Added doctor: $specialization");
        header("Location: doctors.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to add doctor: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_availability'])) {
    $doctorId = $_POST['doctor_id'];
    
    $stmt = $pdo->prepare("DELETE FROM doctor_availability WHERE doctorId = ?");
    $stmt->execute([$doctorId]);
    
    for ($i = 0; $i <= 6; $i++) {
        if (isset($_POST["day_{$i}_available"])) {
            $startTime = $_POST["day_{$i}_start"];
            $endTime = $_POST["day_{$i}_end"];
            $stmt = $pdo->prepare("INSERT INTO doctor_availability (doctorId, dayOfWeek, startTime, endTime, isAvailable) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$doctorId, $i, $startTime, $endTime]);
        }
    }
    
    $_SESSION['success'] = "Availability updated!";
    logAction($_SESSION['user_id'], 'UPDATE_DOCTOR_AVAILABILITY', "Updated availability for doctor $doctorId");
    header("Location: doctors.php");
    exit();
}

if (isset($_GET['delete'])) {
    $doctorId = $_GET['delete'];
    $stmt = $pdo->prepare("UPDATE doctors SET isAvailable = 0 WHERE doctorId = ?");
    $stmt->execute([$doctorId]);
    $_SESSION['success'] = "Doctor removed!";
    logAction($_SESSION['user_id'], 'DELETE_DOCTOR', "Removed doctor $doctorId");
    header("Location: doctors.php");
    exit();
}

$doctors = $pdo->query("
    SELECT d.*, u.firstName, u.lastName, u.email, u.phoneNumber, s.licenseNumber, s.hireDate
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    ORDER BY u.firstName
")->fetchAll();

$users = $pdo->query("SELECT userId, firstName, lastName, email FROM users WHERE role NOT IN ('doctor', 'admin')")->fetchAll();
$error = $error ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-md"></i> Manage Doctors</h1>
            <p>Add, edit, and manage doctor profiles</p>
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

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Doctor</h3>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Select User <span class="required">*</span></label>
                        <select name="user_id" class="admin-form-control" required>
                            <option value="">Select a user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['userId']; ?>"><?php echo htmlspecialchars($user['firstName'].' '.$user['lastName'].' ('.$user['email'].')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label>License Number <span class="required">*</span></label>
                        <input type="text" name="license_number" class="admin-form-control" required>
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Specialization <span class="required">*</span></label>
                        <select name="specialization" class="admin-form-control" required>
                            <option value="">Select</option>
                            <?php foreach ($specializationsList as $spec => $desc): ?>
                                <option value="<?php echo $spec; ?>"><?php echo $spec; ?> - <?php echo $desc; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label>Consultation Fee ($) <span class="required">*</span></label>
                        <input type="number" name="consultation_fee" class="admin-form-control" step="10" required>
                    </div>
                </div>
                
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Years of Experience</label>
                        <input type="number" name="years_of_experience" class="admin-form-control">
                    </div>
                    <div class="admin-form-group">
                        <label>Education</label>
                        <input type="text" name="education" class="admin-form-control" placeholder="MD, PhD, etc.">
                    </div>
                </div>
                
                <div class="admin-form-group">
                    <label>Biography</label>
                    <textarea name="biography" rows="3" class="admin-form-control"></textarea>
                </div>
                
                <button type="submit" name="add_doctor" class="admin-btn admin-btn-primary">Add Doctor</button>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Doctors</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>License</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                        <tr>
                            <td data-label="Name">Dr. <?php echo htmlspecialchars($doctor['firstName'].' '.$doctor['lastName']); ?></td>
                            <td data-label="Specialization"><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                            <td data-label="License"><?php echo htmlspecialchars($doctor['licenseNumber']); ?></td>
                            <td data-label="Fee">$<?php echo number_format($doctor['consultationFee'], 2); ?></td>
                            <td data-label="Status">
                                <span class="admin-status-badge <?php echo $doctor['isAvailable'] ? 'admin-status-active' : 'admin-status-cancelled'; ?>">
                                    <?php echo $doctor['isAvailable'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="admin-action-buttons">
                                    <button class="admin-btn admin-btn-primary admin-btn-sm" onclick="openModal('availabilityModal'); document.getElementById('avail_doctor_id').value=<?php echo $doctor['doctorId']; ?>;">Set Availability</button>
                                    <a href="?delete=<?php echo $doctor['doctorId']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Remove doctor?')">Remove</a>
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
<div id="availabilityModal" class="admin-modal">
    <div class="admin-modal-content admin-modal-large">
        <div class="admin-modal-header">
            <h3>Set Availability</h3>
            <span class="admin-modal-close" onclick="closeModal('availabilityModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="admin-modal-body">
                <input type="hidden" name="doctor_id" id="avail_doctor_id">
                <div class="admin-availability-grid">
                    <?php $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach ($days as $dayIndex => $dayName): ?>
                        <div class="admin-availability-day">
                            <label class="admin-day-checkbox-label">
                                <input type="checkbox" name="day_<?php echo $dayIndex; ?>_available" class="admin-day-checkbox" data-day="<?php echo $dayIndex; ?>" onchange="toggleDay(<?php echo $dayIndex; ?>)">
                                <strong><?php echo $dayName; ?></strong>
                            </label>
                            <div class="admin-availability-times" style="display:none;">
                                <div class="admin-time-select">
                                    <label>Start:</label>
                                    <input type="time" name="day_<?php echo $dayIndex; ?>_start" value="09:00">
                                </div>
                                <div class="admin-time-select">
                                    <label>End:</label>
                                    <input type="time" name="day_<?php echo $dayIndex; ?>_end" value="17:00">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" name="update_availability" class="admin-btn admin-btn-primary">Save</button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('availabilityModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>