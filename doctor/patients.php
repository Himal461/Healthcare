<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "My Patients - HealthManagement";
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
$viewPatientId = $_GET['view'] ?? 0;

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment_status'])) {
    $appointmentId = $_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    try {
        $verifyStmt = $pdo->prepare("
            SELECT appointmentId FROM appointments 
            WHERE appointmentId = ? AND doctorId = ?
        ");
        $verifyStmt->execute([$appointmentId, $doctorId]);
        
        if (!$verifyStmt->fetch()) {
            throw new Exception("You don't have permission to update this appointment.");
        }
        
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = ?, 
                notes = CONCAT(IFNULL(notes, ''), ' [', NOW(), '] Status changed to: ', ?, ' - ', ?),
                updatedAt = NOW() 
            WHERE appointmentId = ? AND doctorId = ?
        ");
        $stmt->execute([$status, $status, $notes, $appointmentId, $doctorId]);
        
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
            createNotification(
                $patient['userId'],
                'appointment',
                'Appointment Status Updated',
                "Your appointment status has been updated to: " . ucfirst($status) . ($notes ? " Note: " . $notes : "")
            );
        }
        
        $_SESSION['success'] = "Appointment status updated successfully to: " . ucfirst($status);
        logAction($userId, 'UPDATE_APPOINTMENT_STATUS', "Updated appointment $appointmentId to $status");
        
        header("Location: patients.php?view=" . $viewPatientId);
        exit();
        
    } catch (Exception $e) {
        $error = "Failed to update appointment status: " . $e->getMessage();
    }
}

// Get ONLY patients that belong to this doctor
$query = "
    SELECT DISTINCT p.patientId,
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email,
           u.phoneNumber,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies,
           p.address,
           p.emergencyContactName,
           p.emergencyContactPhone,
           p.insuranceProvider,
           p.insuranceNumber,
           (SELECT COUNT(*) FROM appointments WHERE patientId = p.patientId AND doctorId = ?) as total_visits,
           (SELECT MAX(dateTime) FROM appointments WHERE patientId = p.patientId AND doctorId = ?) as last_visit
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.doctorId = ?
";

$params = [$doctorId, $doctorId, $doctorId];

if ($search) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY last_visit DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Get patient details if viewing
$viewPatient = null;
$patientAppointments = [];
$medicalRecords = [];
$vitals = [];

if ($viewPatientId) {
    $verifyStmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE patientId = ? AND doctorId = ?
    ");
    $verifyStmt->execute([$viewPatientId, $doctorId]);
    
    if ($verifyStmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(u.firstName, ' ', u.lastName) as patientName,
                   u.email,
                   u.phoneNumber
            FROM patients p
            JOIN users u ON p.userId = u.userId
            WHERE p.patientId = ?
        ");
        $stmt->execute([$viewPatientId]);
        $viewPatient = $stmt->fetch();
        
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
        
        // Get ALL medical records for this patient with doctor details
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
        
        // Get ALL vitals for this patient
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
    } else {
        $_SESSION['error'] = "You don't have permission to view this patient's records.";
        header("Location: patients.php");
        exit();
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>My Patients</h1>
        <p>View and manage your patient list</p>
    </div>

    <!-- Search Bar -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="search-form">
                <div class="search-group">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search): ?>
                        <a href="patients.php" class="btn btn-outline">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($viewPatient): ?>
        <!-- Patient Details View -->
        <div class="patient-details">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-circle"></i> Patient Details: <?php echo htmlspecialchars($viewPatient['patientName']); ?></h3>
                    <a href="patients.php" class="btn btn-outline btn-sm">Back to List</a>
                </div>
                <div class="card-body">
                    <div class="patient-info-grid">
                        <div class="info-section">
                            <h4>Personal Information</h4>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($viewPatient['patientName']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($viewPatient['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($viewPatient['phoneNumber']); ?></p>
                            <p><strong>Date of Birth:</strong> <?php echo $viewPatient['dateOfBirth'] ?: 'N/A'; ?></p>
                            <p><strong>Age:</strong> <?php echo calculateAge($viewPatient['dateOfBirth']); ?></p>
                            <p><strong>Address:</strong> <?php echo $viewPatient['address'] ?: 'N/A'; ?></p>
                        </div>
                        <div class="info-section">
                            <h4>Medical Information</h4>
                            <p><strong>Blood Type:</strong> <?php echo $viewPatient['bloodType'] ?: 'N/A'; ?></p>
                            <p><strong>Allergies:</strong> <?php echo $viewPatient['knownAllergies'] ?: 'None'; ?></p>
                            <p><strong>Insurance Provider:</strong> <?php echo $viewPatient['insuranceProvider'] ?: 'N/A'; ?></p>
                            <p><strong>Insurance Number:</strong> <?php echo $viewPatient['insuranceNumber'] ?: 'N/A'; ?></p>
                        </div>
                        <div class="info-section">
                            <h4>Emergency Contact</h4>
                            <p><strong>Name:</strong> <?php echo $viewPatient['emergencyContactName'] ?: 'N/A'; ?></p>
                            <p><strong>Phone:</strong> <?php echo $viewPatient['emergencyContactPhone'] ?: 'N/A'; ?></p>
                        </div>
                    </div>

                    <!-- Patient Appointments -->
                    <h3 class="section-title">Patient Appointments</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                汽
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </thead>
                            <tbody>
                                <?php if (empty($patientAppointments)): ?>
                                    汽
                                        <td colspan="4" style="text-align: center;">No appointments found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($patientAppointments as $appointment): ?>
                                        <tr>
                                            <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                                            <td data-label="Status">
                                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td data-label="Reason"><?php echo $appointment['reason'] ?: '-'; ?></td>
                                            <td data-label="Actions">
                                                <div class="action-buttons">
                                                    <?php if ($appointment['status'] === 'scheduled' || $appointment['status'] === 'confirmed'): ?>
                                                        <a href="consultation.php?appointment_id=<?php echo $appointment['appointmentId']; ?>&patient_id=<?php echo $viewPatientId; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-stethoscope"></i> Start Consultation
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['status'] === 'in-progress'): ?>
                                                        <a href="consultation.php?appointment_id=<?php echo $appointment['appointmentId']; ?>&patient_id=<?php echo $viewPatientId; ?>" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Complete
                                                        </a>
                                                    <?php endif; ?>
                                                    <button class="btn btn-info btn-sm" onclick="openStatusModal(<?php echo $appointment['appointmentId']; ?>, '<?php echo $appointment['status']; ?>')">
                                                        <i class="fas fa-edit"></i> Update Status
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Medical Records -->
                    <h3 class="section-title">Medical Records</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                汽
                                    <th>Date</th>
                                    <th>Doctor</th>
                                    <th>Specialization</th>
                                    <th>Diagnosis</th>
                                    <th>Actions</th>
                                </thead>
                            <tbody>
                                <?php if (empty($medicalRecords)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No medical records found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($medicalRecords as $record): ?>
                                        <tr>
                                            <td data-label="Date"><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></td>
                                            <td data-label="Doctor">Dr. <?php echo htmlspecialchars($record['doctorName']); ?></td>
                                            <td data-label="Specialization"><?php echo htmlspecialchars($record['specialization']); ?></td>
                                            <td data-label="Diagnosis"><?php echo substr($record['diagnosis'], 0, 60) . (strlen($record['diagnosis']) > 60 ? '...' : ''); ?></td>
                                            <td data-label="Actions">
                                                <button class="btn btn-primary btn-sm" onclick="viewRecord(<?php echo $record['recordId']; ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Vitals History -->
                    <h3 class="section-title">Vitals History</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                汽
                                    <th>Date</th>
                                    <th>Recorded By</th>
                                    <th>Doctor</th>
                                    <th>BP</th>
                                    <th>Heart Rate</th>
                                    <th>Temperature</th>
                                    <th>Weight</th>
                                    <th>Height</th>
                                    <th>SpO2</th>
                                </thead>
                            <tbody>
                                <?php if (empty($vitals)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center;">No vitals recorded</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($vitals as $vital): ?>
                                        <tr>
                                            <td data-label="Date"><?php echo date('M j, Y', strtotime($vital['recordedDate'])); ?></td>
                                            <td data-label="Recorded By"><?php echo $vital['recordedByName'] ?: 'Nurse'; ?></td>
                                            <td data-label="Doctor">Dr. <?php echo $vital['doctorName'] ?: 'N/A'; ?></td>
                                            <td data-label="BP"><?php echo $vital['bloodPressureSystolic'] ? $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic'] : '-'; ?></td>
                                            <td data-label="Heart Rate"><?php echo $vital['heartRate'] ?: '-'; ?></td>
                                            <td data-label="Temperature"><?php echo $vital['bodyTemperature'] ?: '-'; ?>°C</td>
                                            <td data-label="Weight"><?php echo $vital['weight'] ?: '-'; ?> kg</td>
                                            <td data-label="Height"><?php echo $vital['height'] ?: '-'; ?> cm</td>
                                            <td data-label="SpO2"><?php echo $vital['oxygenSaturation'] ?: '-'; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="action-buttons">
                        <a href="../patient/appointments.php?doctor_id=<?php echo $doctorId; ?>&patient_id=<?php echo $viewPatientId; ?>" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                        </a>
                        <a href="../admin/medical-records.php?add&patient_id=<?php echo $viewPatientId; ?>" class="btn btn-primary">
                            <i class="fas fa-notes-medical"></i> Add Medical Record
                        </a>
                        <button class="btn btn-info" onclick="recordVitals(<?php echo $viewPatientId; ?>)">
                            <i class="fas fa-heartbeat"></i> Record Vitals
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Appointment Status Modal -->
        <div id="statusModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Update Appointment Status</h3>
                    <span class="close" onclick="closeStatusModal()">&times;</span>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="status_appointment_id">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="in-progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no-show">No Show</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Add notes about status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update_appointment_status" class="btn btn-primary">Update Status</button>
                        <button type="button" class="btn" onclick="closeStatusModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- Patients List -->
        <div class="card">
            <div class="card-header">
                <h3>My Patients (<?php echo count($patients); ?>)</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        汽
                            <th>Patient Name</th>
                            <th>Contact</th>
                            <th>Blood Type</th>
                            <th>Total Visits</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No patients found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td data-label="Patient Name">
                                        <strong><?php echo htmlspecialchars($patient['patientName']); ?></strong><br>
                                        <small><?php echo $patient['email']; ?></small>
                                    </td>
                                    <td data-label="Contact"><?php echo $patient['phoneNumber']; ?></td>
                                    <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                                    <td data-label="Total Visits"><?php echo $patient['total_visits']; ?></td>
                                    <td data-label="Last Visit"><?php echo $patient['last_visit'] ? date('M j, Y', strtotime($patient['last_visit'])) : 'Never'; ?></td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">
                                            <a href="?view=<?php echo $patient['patientId']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="../patient/appointments.php?doctor_id=<?php echo $doctorId; ?>&patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-outline btn-sm">
                                                <i class="fas fa-calendar-plus"></i> Book
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function viewRecord(recordId) {
    window.location.href = '../admin/medical-records-view.php?id=' + recordId;
}

function recordVitals(patientId) {
    window.location.href = '../nurse/record-vitals.php?patient_id=' + patientId;
}

function openStatusModal(appointmentId, currentStatus) {
    document.getElementById('status_appointment_id').value = appointmentId;
    document.getElementById('status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'flex';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<style>
.section-title {
    color: #1a75bc;
    margin: 25px 0 15px;
    font-size: 18px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header .close {
    font-size: 28px;
    cursor: pointer;
    color: #999;
}

.modal-header .close:hover {
    color: #333;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>

<?php include '../includes/footer.php'; ?>