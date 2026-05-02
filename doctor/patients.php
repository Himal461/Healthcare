<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "My Patients - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.specialization 
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();
$doctorId = $doctor['doctorId'];

// Handle search
$search = $_GET['search'] ?? '';
$viewPatientId = (int)($_GET['view'] ?? 0);

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment_status'])) {
    $appointmentId = (int)$_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    $verifyStmt = $pdo->prepare("SELECT appointmentId FROM appointments WHERE appointmentId = ? AND doctorId = ?");
    $verifyStmt->execute([$appointmentId, $doctorId]);
    
    if ($verifyStmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] Status changed to: ', ?, ' - ', ?), updatedAt = NOW() WHERE appointmentId = ?");
        $stmt->execute([$status, $status, $notes, $appointmentId]);
        
        $patientStmt = $pdo->prepare("
            SELECT p.patientId, u.userId, u.firstName, u.lastName
            FROM appointments a
            JOIN patients p ON a.patientId = p.patientId
            JOIN users u ON p.userId = u.userId
            WHERE a.appointmentId = ?
        ");
        $patientStmt->execute([$appointmentId]);
        $patient = $patientStmt->fetch();
        
        if ($patient) {
            createNotification($patient['userId'], 'appointment', 'Appointment Status Updated',
                "Your appointment status has been updated to: " . ucfirst($status));
        }
        
        $_SESSION['success'] = "Appointment status updated to: " . ucfirst($status);
        logAction($userId, 'UPDATE_APPOINTMENT_STATUS', "Updated appointment $appointmentId to $status");
    }
    
    header("Location: patients.php" . ($viewPatientId ? "?view=$viewPatientId" : ""));
    exit();
}

// Get ALL patients associated with this doctor
$query = "
    SELECT DISTINCT 
        p.patientId,
        CONCAT(u.firstName, ' ', u.lastName) as patientName,
        u.email,
        u.phoneNumber,
        p.dateOfBirth,
        p.bloodType,
        p.knownAllergies,
        (SELECT COUNT(*) FROM appointments WHERE patientId = p.patientId AND doctorId = ?) as total_visits,
        (SELECT MAX(dateTime) FROM appointments WHERE patientId = p.patientId AND doctorId = ?) as last_visit,
        (SELECT COUNT(*) FROM medical_records WHERE patientId = p.patientId AND doctorId = ?) as total_records,
        (SELECT COUNT(*) FROM lab_tests WHERE patientId = p.patientId AND orderedBy = ?) as total_lab_tests
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.role = 'patient'
    AND (
        p.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
        OR p.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?)
        OR p.patientId IN (SELECT DISTINCT patientId FROM lab_tests WHERE orderedBy = ?)
    )
";

$params = [$doctorId, $doctorId, $doctorId, $doctorId, $doctorId, $doctorId, $doctorId];

if ($search) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY last_visit DESC, patientName ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Get patient details if viewing
$viewPatient = null;
$patientAppointments = [];
$medicalRecords = [];
$labTests = [];
$vitals = [];

if ($viewPatientId) {
    $verifyStmt = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT patientId FROM appointments WHERE patientId = ? AND doctorId = ?
            UNION
            SELECT patientId FROM medical_records WHERE patientId = ? AND doctorId = ?
            UNION
            SELECT patientId FROM lab_tests WHERE patientId = ? AND orderedBy = ?
        ) AS patient_associations
    ");
    $verifyStmt->execute([$viewPatientId, $doctorId, $viewPatientId, $doctorId, $viewPatientId, $doctorId]);
    
    if ($verifyStmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(u.firstName, ' ', u.lastName) as patientName,
                   u.email,
                   u.phoneNumber
            FROM patients p
            JOIN users u ON p.userId = u.userId
            WHERE p.patientId = ? AND u.role = 'patient'
        ");
        $stmt->execute([$viewPatientId]);
        $viewPatient = $stmt->fetch();
        
        if ($viewPatient) {
            // Get patient's appointments with this doctor
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       CONCAT(du.firstName, ' ', du.lastName) as doctorName
                FROM appointments a
                JOIN doctors d ON a.doctorId = d.doctorId
                JOIN staff s ON d.staffId = s.staffId
                JOIN users du ON s.userId = du.userId
                WHERE a.patientId = ? AND a.doctorId = ?
                ORDER BY a.dateTime DESC
            ");
            $stmt->execute([$viewPatientId, $doctorId]);
            $patientAppointments = $stmt->fetchAll();
            
            // Get ALL medical records for this patient
            $stmt = $pdo->prepare("
                SELECT mr.*, 
                       CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                       d.specialization
                FROM medical_records mr
                JOIN doctors d ON mr.doctorId = d.doctorId
                JOIN staff s ON d.staffId = s.staffId
                JOIN users du ON s.userId = du.userId
                WHERE mr.patientId = ?
                ORDER BY mr.creationDate DESC
            ");
            $stmt->execute([$viewPatientId]);
            $medicalRecords = $stmt->fetchAll();
            
            // Get lab tests for this patient
            $stmt = $pdo->prepare("
                SELECT lt.*,
                       CONCAT(du.firstName, ' ', du.lastName) as orderedByName
                FROM lab_tests lt
                LEFT JOIN doctors d ON lt.orderedBy = d.doctorId
                LEFT JOIN staff s ON d.staffId = s.staffId
                LEFT JOIN users du ON s.userId = du.userId
                WHERE lt.patientId = ?
                ORDER BY lt.orderedDate DESC
            ");
            $stmt->execute([$viewPatientId]);
            $labTests = $stmt->fetchAll();
            
            // Get vitals for this patient
            $stmt = $pdo->prepare("
                SELECT v.*, 
                       CONCAT(du.firstName, ' ', du.lastName) as recordedByName,
                       CONCAT(docu.firstName, ' ', docu.lastName) as doctorName
                FROM vitals v
                JOIN medical_records mr ON v.recordId = mr.recordId
                LEFT JOIN staff s ON v.recordedBy = s.staffId
                LEFT JOIN users du ON s.userId = du.userId
                LEFT JOIN doctors d ON mr.doctorId = d.doctorId
                LEFT JOIN staff ds ON d.staffId = ds.staffId
                LEFT JOIN users docu ON ds.userId = docu.userId
                WHERE mr.patientId = ?
                ORDER BY v.recordedDate DESC
                LIMIT 20
            ");
            $stmt->execute([$viewPatientId]);
            $vitals = $stmt->fetchAll();
        }
    } else {
        $_SESSION['error'] = "You don't have permission to view this patient's records.";
        header("Location: patients.php");
        exit();
    }
}

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-users"></i> My Patients</h1>
            <p>View and manage all patients under your care</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($viewPatient): ?>
        <!-- Patient Details View -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-user-circle"></i> Patient: <?php echo htmlspecialchars($viewPatient['patientName']); ?></h3>
                <a href="patients.php" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="doctor-card-body">
                <div class="doctor-patient-info-grid">
                    <div class="doctor-info-group">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($viewPatient['patientName']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($viewPatient['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($viewPatient['phoneNumber']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $viewPatient['dateOfBirth'] ? date('M j, Y', strtotime($viewPatient['dateOfBirth'])) : 'N/A'; ?></p>
                        <p><strong>Age:</strong> <?php echo calculateAge($viewPatient['dateOfBirth']); ?></p>
                    </div>
                    <div class="doctor-info-group">
                        <h4><i class="fas fa-notes-medical"></i> Medical Information</h4>
                        <p><strong>Blood Type:</strong> <?php echo $viewPatient['bloodType'] ?: 'N/A'; ?></p>
                        <p><strong>Allergies:</strong> <?php echo htmlspecialchars($viewPatient['knownAllergies'] ?: 'None'); ?></p>
                    </div>
                </div>

                <div class="doctor-action-buttons" style="margin-top: 25px;">
                    <a href="consultation.php?patient_id=<?php echo $viewPatientId; ?>" class="doctor-btn doctor-btn-primary">
                        <i class="fas fa-notes-medical"></i> New Consultation
                    </a>
                    <a href="prescriptions.php?patient_id=<?php echo $viewPatientId; ?>" class="doctor-btn doctor-btn-outline">
                        <i class="fas fa-prescription"></i> Write Prescription
                    </a>
                </div>
            </div>
        </div>

        <!-- Medical Records -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-notes-medical"></i> Medical Records</h3>
            </div>
            <div class="doctor-table-responsive">
                <?php if (empty($medicalRecords)): ?>
                    <p class="doctor-empty-message">No medical records found.</p>
                <?php else: ?>
                    <table class="doctor-data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicalRecords as $record): ?>
                                <tr>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></td>
                                    <td data-label="Doctor">Dr. <?php echo htmlspecialchars($record['doctorName']); ?></td>
                                    <td data-label="Diagnosis"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 60)); ?>...</td>
                                    <td data-label="Actions">
                                        <button class="doctor-btn doctor-btn-primary doctor-btn-sm" onclick="viewRecord(<?php echo $record['recordId']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointments -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-calendar-alt"></i> Appointments with You</h3>
            </div>
            <div class="doctor-table-responsive">
                <?php if (empty($patientAppointments)): ?>
                    <p class="doctor-empty-message">No appointments found.</p>
                <?php else: ?>
                    <table class="doctor-data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patientAppointments as $appointment): ?>
                                <tr>
                                    <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                                    <td data-label="Status">
                                        <span class="doctor-status-badge doctor-status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Reason"><?php echo htmlspecialchars($appointment['reason'] ?: '-'); ?></td>
                                    <td data-label="Actions">
                                        <?php if ($appointment['status'] === 'scheduled' || $appointment['status'] === 'confirmed'): ?>
                                            <a href="consultation.php?appointment_id=<?php echo $appointment['appointmentId']; ?>&patient_id=<?php echo $viewPatientId; ?>" class="doctor-btn doctor-btn-primary doctor-btn-sm">
                                                <i class="fas fa-stethoscope"></i> Start
                                            </a>
                                        <?php endif; ?>
                                        <button class="doctor-btn doctor-btn-info doctor-btn-sm" onclick="openStatusModal(<?php echo $appointment['appointmentId']; ?>, '<?php echo $appointment['status']; ?>')">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Search Bar -->
        <div class="doctor-card">
            <div class="doctor-card-body">
                <form method="GET" class="doctor-search-group">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>" class="doctor-form-control" style="flex: 1;">
                    <button type="submit" class="doctor-btn doctor-btn-primary"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="patients.php" class="doctor-btn doctor-btn-outline">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Patients List -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-list"></i> My Patients (<?php echo count($patients); ?>)</h3>
            </div>
            <div class="doctor-table-responsive">
                <?php if (empty($patients)): ?>
                    <div class="doctor-empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>No patients found.</p>
                    </div>
                <?php else: ?>
                    <table class="doctor-data-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Contact</th>
                                <th>Blood Type</th>
                                <th>Visits</th>
                                <th>Records</th>
                                <th>Lab Tests</th>
                                <th>Last Visit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td data-label="Patient Name">
                                        <strong><?php echo htmlspecialchars($patient['patientName']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($patient['email']); ?></small>
                                    </td>
                                    <td data-label="Contact"><?php echo htmlspecialchars($patient['phoneNumber']); ?></td>
                                    <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                                    <td data-label="Visits"><?php echo $patient['total_visits']; ?></td>
                                    <td data-label="Records"><?php echo $patient['total_records']; ?></td>
                                    <td data-label="Lab Tests"><?php echo $patient['total_lab_tests']; ?></td>
                                    <td data-label="Last Visit">
                                        <?php echo $patient['last_visit'] ? date('M j, Y', strtotime($patient['last_visit'])) : 'Never'; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="doctor-action-buttons">
                                            <a href="?view=<?php echo $patient['patientId']; ?>" class="doctor-btn doctor-btn-primary doctor-btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="consultation.php?patient_id=<?php echo $patient['patientId']; ?>" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                                                <i class="fas fa-notes-medical"></i> Consult
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Status Modal -->
<div id="statusModal" class="doctor-modal">
    <div class="doctor-modal-content">
        <div class="doctor-modal-header">
            <h3>Update Appointment Status</h3>
            <span class="doctor-modal-close" onclick="closeModal('statusModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="doctor-modal-body">
                <input type="hidden" name="appointment_id" id="status_appointment_id">
                <div class="doctor-form-group">
                    <label>Status</label>
                    <select name="status" id="status" class="doctor-form-control" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no-show">No Show</option>
                    </select>
                </div>
                <div class="doctor-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="doctor-form-control" placeholder="Add notes about status change..."></textarea>
                </div>
            </div>
            <div class="doctor-modal-footer">
                <button type="submit" name="update_appointment_status" class="doctor-btn doctor-btn-primary">Update Status</button>
                <button type="button" class="doctor-btn doctor-btn-outline" onclick="closeModal('statusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.doctor-search-group {
    display: flex;
    gap: 12px;
    align-items: center;
}
</style>

<?php include '../includes/footer.php'; ?>