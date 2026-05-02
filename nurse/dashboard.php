<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Nurse Dashboard - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get nurse details
$stmt = $pdo->prepare("
    SELECT n.*, s.department, s.licenseNumber, s.hireDate,
           CONCAT(u.firstName, ' ', u.lastName) as nurseName
    FROM nurses n
    JOIN staff s ON n.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE u.userId = ?
");
$stmt->execute([$userId]);
$nurse = $stmt->fetch();

// Get total patients count - FIXED: Only count users with role = 'patient'
$totalPatients = $pdo->query("
    SELECT COUNT(*) 
    FROM patients p 
    JOIN users u ON p.userId = u.userId 
    WHERE u.role = 'patient'
")->fetchColumn();

// Get today's appointments that need nursing care
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           pu.phoneNumber as patientPhone,
           pu.email as patientEmail,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE DATE(a.dateTime) = CURDATE() 
    AND a.status IN ('scheduled', 'confirmed', 'in-progress')
    AND pu.role = 'patient'
    ORDER BY a.dateTime
");
$stmt->execute();
$todayAppointments = $stmt->fetchAll();

// Get patients requiring vitals monitoring
$stmt = $pdo->prepare("
    SELECT DISTINCT p.patientId, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber,
           u.email,
           p.bloodType,
           p.knownAllergies,
           p.dateOfBirth,
           MAX(v.recordedDate) as lastVitalsDate,
           (SELECT COUNT(*) FROM vitals v2 
            JOIN medical_records mr2 ON v2.recordId = mr2.recordId 
            WHERE mr2.patientId = p.patientId) as vitalsCount
    FROM patients p
    JOIN users u ON p.userId = u.userId
    LEFT JOIN medical_records mr ON p.patientId = mr.patientId
    LEFT JOIN vitals v ON mr.recordId = v.recordId
    WHERE u.role = 'patient'
    AND p.patientId IN (
        SELECT DISTINCT patientId FROM appointments 
        WHERE DATE(dateTime) >= CURDATE() - INTERVAL 30 DAY
    )
    GROUP BY p.patientId
    ORDER BY lastVitalsDate ASC
    LIMIT 15
");
$stmt->execute();
$patientsNeedingVitals = $stmt->fetchAll();

// Get pending lab tests
$stmt = $pdo->prepare("
    SELECT lt.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName,
           lt.testName, lt.testType, lt.status
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    JOIN doctors d ON lt.orderedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE lt.status IN ('ordered', 'in-progress')
    AND u.role = 'patient'
    ORDER BY lt.orderedDate DESC
    LIMIT 15
");
$stmt->execute();
$pendingTests = $stmt->fetchAll();

// Get active prescriptions
$stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           p.medicationName, p.dosage, p.frequency, p.status
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN patients pt ON mr.patientId = pt.patientId
    JOIN users u ON pt.userId = u.userId
    JOIN doctors d ON p.prescribedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE p.status = 'active'
    AND u.role = 'patient'
    ORDER BY p.createdAt DESC
    LIMIT 15
");
$stmt->execute();
$activePrescriptions = $stmt->fetchAll();

// FIXED: Get all patients - ONLY users with role = 'patient'
$allPatients = $pdo->query("
    SELECT p.patientId, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email, 
           u.phoneNumber, 
           p.dateOfBirth, 
           p.bloodType, 
           p.knownAllergies,
           (SELECT COUNT(*) FROM appointments WHERE patientId = p.patientId) as totalAppointments,
           (SELECT MAX(dateTime) FROM appointments WHERE patientId = p.patientId) as lastVisit
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.role = 'patient'
    ORDER BY u.firstName, u.lastName
")->fetchAll();

$totalVitalsNeeded = count($patientsNeedingVitals);
$totalPendingTests = count($pendingTests);
$totalActivePrescriptions = count($activePrescriptions);
$totalAppointmentsToday = count($todayAppointments);

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="nurse-container">
    <!-- Welcome Section -->
    <div class="nurse-welcome-section">
        <div class="nurse-welcome-text">
            <h1>Welcome, Nurse <?php echo htmlspecialchars($nurse['nurseName']); ?>!</h1>
            <p>Nursing Dashboard - <?php echo htmlspecialchars($nurse['nursingSpecialty'] ?? 'General Nursing'); ?></p>
        </div>
        <div class="nurse-date-display">
            <i class="fas fa-calendar-alt"></i>
            <span><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="nurse-alert nurse-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="nurse-alert nurse-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="nurse-stats-grid">
        <div class="nurse-stat-card total">
            <div class="nurse-stat-icon"><i class="fas fa-users"></i></div>
            <div class="nurse-stat-content">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        <div class="nurse-stat-card today">
            <div class="nurse-stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="nurse-stat-content">
                <h3><?php echo $totalAppointmentsToday; ?></h3>
                <p>Today's Appointments</p>
            </div>
        </div>
        <div class="nurse-stat-card vitals">
            <div class="nurse-stat-icon"><i class="fas fa-heartbeat"></i></div>
            <div class="nurse-stat-content">
                <h3><?php echo $totalVitalsNeeded; ?></h3>
                <p>Need Vitals Check</p>
            </div>
        </div>
        <div class="nurse-stat-card pending">
            <div class="nurse-stat-icon"><i class="fas fa-flask"></i></div>
            <div class="nurse-stat-content">
                <h3><?php echo $totalPendingTests; ?></h3>
                <p>Pending Lab Tests</p>
            </div>
        </div>
        <div class="nurse-stat-card prescriptions">
            <div class="nurse-stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="nurse-stat-content">
                <h3><?php echo $totalActivePrescriptions; ?></h3>
                <p>Active Prescriptions</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-quick-actions-grid">
                <a href="medical-records.php" class="nurse-action-card">
                    <i class="fas fa-folder-open"></i>
                    <span>Medical Records</span>
                </a>
                <a href="lab-tests.php" class="nurse-action-card">
                    <i class="fas fa-flask"></i>
                    <span>Lab Tests</span>
                </a>
                <a href="create-lab-test.php" class="nurse-action-card">
                    <i class="fas fa-plus-circle"></i>
                    <span>Create Lab Test</span>
                </a>
                <a href="prescriptions.php" class="nurse-action-card">
                    <i class="fas fa-prescription"></i>
                    <span>Prescriptions</span>
                </a>
                <a href="vitals.php" class="nurse-action-card">
                    <i class="fas fa-heartbeat"></i>
                    <span>Patient Vitals</span>
                </a>
            </div>
        </div>
    </div>

    <!-- All Patients List -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-users"></i> All Patients (<?php echo $totalPatients; ?>)</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-table-responsive" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($allPatients)): ?>
                    <div class="nurse-empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>No patients found in the system.</p>
                    </div>
                <?php else: ?>
                    <table class="nurse-data-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Age</th>
                                <th>Blood Type</th>
                                <th>Allergies</th>
                                <th>Last Visit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allPatients as $patient): ?>
                                <tr>
                                    <td data-label="Patient Name">
                                        <strong><?php echo htmlspecialchars($patient['patientName']); ?></strong>
                                    </td>
                                    <td data-label="Email"><?php echo htmlspecialchars($patient['email']); ?></td>
                                    <td data-label="Phone"><?php echo htmlspecialchars($patient['phoneNumber']); ?></td>
                                    <td data-label="Age"><?php echo calculateAge($patient['dateOfBirth']); ?></td>
                                    <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                                    <td data-label="Allergies">
                                        <?php 
                                        $allergies = $patient['knownAllergies'] ?: 'None';
                                        echo htmlspecialchars(substr($allergies, 0, 30)) . (strlen($allergies) > 30 ? '...' : ''); 
                                        ?>
                                    </td>
                                    <td data-label="Last Visit">
                                        <?php echo $patient['lastVisit'] ? date('M j, Y', strtotime($patient['lastVisit'])) : 'Never'; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="medical-records.php?patient_id=<?php echo $patient['patientId']; ?>" class="nurse-btn nurse-btn-primary nurse-btn-sm">
                                            <i class="fas fa-eye"></i> View Records
                                        </a>
                                        <a href="record-vitals.php?patient_id=<?php echo $patient['patientId']; ?>" class="nurse-btn nurse-btn-outline nurse-btn-sm" style="margin-top: 5px;">
                                            <i class="fas fa-heartbeat"></i> Record Vitals
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-calendar-day"></i> Today's Appointments (<?php echo date('F j, Y'); ?>)</h3>
        </div>
        <div class="nurse-table-responsive">
            <?php if (empty($todayAppointments)): ?>
                <div class="nurse-empty-state">
                    <i class="fas fa-calendar-day"></i>
                    <p>No appointments scheduled for today.</p>
                </div>
            <?php else: ?>
                <table class="nurse-data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Age</th>
                            <th>Blood Type</th>
                            <th>Allergies</th>
                            <th>Doctor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayAppointments as $appointment): 
                            $age = calculateAge($appointment['dateOfBirth']);
                        ?>
                            <tr>
                                <td data-label="Time"><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($appointment['patientName']); ?></strong><br>
                                    <small><?php echo $appointment['patientPhone']; ?></small>
                                </td>
                                <td data-label="Age"><?php echo $age; ?></td>
                                <td data-label="Blood Type"><?php echo $appointment['bloodType'] ?: 'N/A'; ?></td>
                                <td data-label="Allergies"><?php echo $appointment['knownAllergies'] ?: 'None'; ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?></td>
                                <td data-label="Actions">
                                    <button class="nurse-btn nurse-btn-primary nurse-btn-sm" onclick="openVitalsModal(<?php echo $appointment['patientId']; ?>, '<?php echo addslashes($appointment['patientName']); ?>')">
                                        <i class="fas fa-heartbeat"></i> Vitals
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Patients Needing Vitals -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-heartbeat"></i> Patients Needing Vitals Check</h3>
        </div>
        <div class="nurse-table-responsive">
            <?php if (empty($patientsNeedingVitals)): ?>
                <div class="nurse-empty-state">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    <p>All patients have recent vitals recorded.</p>
                </div>
            <?php else: ?>
                <table class="nurse-data-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Blood Type</th>
                            <th>Allergies</th>
                            <th>Last Vitals</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patientsNeedingVitals as $patient): ?>
                            <tr>
                                <td data-label="Patient"><strong><?php echo htmlspecialchars($patient['patientName']); ?></strong></td>
                                <td data-label="Phone"><?php echo $patient['phoneNumber']; ?></td>
                                <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                                <td data-label="Allergies"><?php echo $patient['knownAllergies'] ? substr($patient['knownAllergies'], 0, 20) . '...' : 'None'; ?></td>
                                <td data-label="Last Vitals"><?php echo $patient['lastVitalsDate'] ? date('M j, Y', strtotime($patient['lastVitalsDate'])) : 'Never'; ?></td>
                                <td data-label="Actions">
                                    <button class="nurse-btn nurse-btn-primary nurse-btn-sm" onclick="openVitalsModal(<?php echo $patient['patientId']; ?>, '<?php echo addslashes($patient['patientName']); ?>')">
                                        <i class="fas fa-stethoscope"></i> Record Vitals
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Record Vitals Modal -->
<div id="vitalsModal" class="nurse-modal">
    <div class="nurse-modal-content">
        <div class="nurse-modal-header">
            <h3>Record Patient Vitals</h3>
            <span class="nurse-modal-close" onclick="closeModal('vitalsModal')">&times;</span>
        </div>
        <form method="POST" action="record-vitals.php" id="vitals-form">
            <div class="nurse-modal-body">
                <input type="hidden" name="patient_id" id="vitals_patient_id">
                <input type="hidden" name="redirect_url" value="dashboard.php">
                <p><strong>Patient:</strong> <span id="vitals_patient_name"></span></p>
                
                <div class="nurse-form-row">
                    <div class="nurse-form-group">
                        <label>Height (cm)</label>
                        <input type="number" name="height" step="0.1" class="nurse-form-control">
                    </div>
                    <div class="nurse-form-group">
                        <label>Weight (kg)</label>
                        <input type="number" name="weight" step="0.1" class="nurse-form-control">
                    </div>
                </div>
                <div class="nurse-form-row">
                    <div class="nurse-form-group">
                        <label>Temperature (°C)</label>
                        <input type="number" name="body_temperature" step="0.1" class="nurse-form-control">
                    </div>
                    <div class="nurse-form-group">
                        <label>Heart Rate (bpm)</label>
                        <input type="number" name="heart_rate" class="nurse-form-control">
                    </div>
                </div>
                <div class="nurse-form-row">
                    <div class="nurse-form-group">
                        <label>BP Systolic</label>
                        <input type="number" name="bp_systolic" class="nurse-form-control">
                    </div>
                    <div class="nurse-form-group">
                        <label>BP Diastolic</label>
                        <input type="number" name="bp_diastolic" class="nurse-form-control">
                    </div>
                </div>
                <div class="nurse-form-row">
                    <div class="nurse-form-group">
                        <label>Respiratory Rate</label>
                        <input type="number" name="respiratory_rate" class="nurse-form-control">
                    </div>
                    <div class="nurse-form-group">
                        <label>O2 Saturation (%)</label>
                        <input type="number" name="oxygen_saturation" class="nurse-form-control">
                    </div>
                </div>
                <div class="nurse-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="nurse-form-control"></textarea>
                </div>
            </div>
            <div class="nurse-modal-footer">
                <button type="submit" class="nurse-btn nurse-btn-primary">Save Vitals</button>
                <button type="button" class="nurse-btn nurse-btn-outline" onclick="closeModal('vitalsModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVitalsModal(patientId, patientName) {
    document.getElementById('vitals_patient_id').value = patientId;
    document.getElementById('vitals_patient_name').innerText = patientName;
    openModal('vitalsModal');
}

function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(e) { if (e.target.classList.contains('nurse-modal')) e.target.style.display = 'none'; }
</script>

<?php include '../includes/footer.php'; ?>