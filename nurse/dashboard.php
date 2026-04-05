<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Nurse Dashboard - HealthManagement";
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
    ORDER BY a.dateTime
");
$stmt->execute();
$todayAppointments = $stmt->fetchAll();

// Get patients requiring vitals monitoring (no vitals in last 7 days)
$stmt = $pdo->prepare("
    SELECT DISTINCT p.patientId, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber,
           p.bloodType,
           p.knownAllergies,
           MAX(v.recordedDate) as lastVitalsDate,
           (SELECT COUNT(*) FROM vitals v2 
            JOIN medical_records mr2 ON v2.recordId = mr2.recordId 
            WHERE mr2.patientId = p.patientId) as vitalsCount
    FROM patients p
    JOIN users u ON p.userId = u.userId
    LEFT JOIN medical_records mr ON p.patientId = mr.patientId
    LEFT JOIN vitals v ON mr.recordId = v.recordId
    WHERE p.patientId IN (
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
    ORDER BY lt.orderedDate DESC
    LIMIT 15
");
$stmt->execute();
$pendingTests = $stmt->fetchAll();

// Get recent medical records
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           mr.diagnosis, mr.treatmentNotes
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    ORDER BY mr.creationDate DESC
    LIMIT 15
");
$stmt->execute();
$recentRecords = $stmt->fetchAll();

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
    ORDER BY p.createdAt DESC
    LIMIT 15
");
$stmt->execute();
$activePrescriptions = $stmt->fetchAll();

// Get statistics
$totalVitalsNeeded = count($patientsNeedingVitals);
$totalPendingTests = count($pendingTests);
$totalActivePrescriptions = count($activePrescriptions);
$totalAppointmentsToday = count($todayAppointments);
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, Nurse <?php echo htmlspecialchars($nurse['nurseName']); ?>!</h1>
        <p>Nursing Dashboard - <?php echo $nurse['nursingSpecialty'] ?? 'General Nursing'; ?></p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card nurse">
            <h3><?php echo $totalAppointmentsToday; ?></h3>
            <p>Today's Appointments</p>
        </div>
        <div class="stat-card nurse">
            <h3><?php echo $totalVitalsNeeded; ?></h3>
            <p>Need Vitals Check</p>
        </div>
        <div class="stat-card nurse">
            <h3><?php echo $totalPendingTests; ?></h3>
            <p>Pending Lab Tests</p>
        </div>
        <div class="stat-card nurse">
            <h3><?php echo $totalActivePrescriptions; ?></h3>
            <p>Active Prescriptions</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions-grid">
                <a href="medical-records.php" class="btn btn-primary">
                    <i class="fas fa-folder-open"></i> Medical Records
                </a>
                <a href="lab-tests.php" class="btn btn-primary">
                    <i class="fas fa-flask"></i> Lab Tests
                </a>
                <a href="prescriptions.php" class="btn btn-primary">
                    <i class="fas fa-prescription"></i> Prescriptions
                </a>
                <a href="vitals.php" class="btn btn-primary">
                    <i class="fas fa-heartbeat"></i> Patient Vitals
                </a>
                <a href="record-vitals.php" class="btn btn-outline">
                    <i class="fas fa-stethoscope"></i> Record New Vitals
                </a>
            </div>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-day"></i> Today's Appointments (<?php echo date('F j, Y'); ?>)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Age</th>
                        <th>Blood Type</th>
                        <th>Allergies</th>
                        <th>Doctor</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php if (empty($todayAppointments)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No appointments scheduled for today</td>
                        </tr>
                    <?php else: ?>
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
                                    <button class="btn btn-primary btn-sm" onclick="openVitalsModal(<?php echo $appointment['patientId']; ?>, '<?php echo addslashes($appointment['patientName']); ?>')">
                                        <i class="fas fa-heartbeat"></i> Record Vitals
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="viewPatient(<?php echo $appointment['patientId']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Patients Needing Vitals Check -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-heartbeat"></i> Patients Needing Vitals Check</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
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
                    <?php if (empty($patientsNeedingVitals)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">All patients have recent vitals recorded</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($patientsNeedingVitals as $patient): ?>
                            <tr>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($patient['patientName']); ?></strong>
                                </td>
                                <td data-label="Phone"><?php echo $patient['phoneNumber']; ?></td>
                                <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                                <td data-label="Allergies"><?php echo $patient['knownAllergies'] ?: 'None'; ?></td>
                                <td data-label="Last Vitals">
                                    <?php echo $patient['lastVitalsDate'] ? date('M j, Y', strtotime($patient['lastVitalsDate'])) : 'Never'; ?>
                                </td>
                                <td data-label="Actions">
                                    <button class="btn btn-primary btn-sm" onclick="openVitalsModal(<?php echo $patient['patientId']; ?>, '<?php echo addslashes($patient['patientName']); ?>')">
                                        <i class="fas fa-stethoscope"></i> Record Vitals
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending Lab Tests -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-flask"></i> Pending Lab Tests</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ordered Date</th>
                        <th>Patient</th>
                        <th>Test Name</th>
                        <th>Test Type</th>
                        <th>Ordered By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingTests)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No pending lab tests</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingTests as $test): ?>
                            <tr>
                                <td data-label="Ordered Date"><?php echo date('M j, Y', strtotime($test['orderedDate'])); ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($test['patientName']); ?></td>
                                <td data-label="Test Name"><?php echo $test['testName']; ?></td>
                                <td data-label="Test Type"><?php echo $test['testType'] ?: '-'; ?></td>
                                <td data-label="Ordered By">Dr. <?php echo $test['orderedByName']; ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $test['status']; ?>">
                                        <?php echo ucfirst($test['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a href="collect-sample.php?test_id=<?php echo $test['testId']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this lab test sample as collected?')">
                                        <i class="fas fa-syringe"></i> Collect Sample
                                    </a>
                                    <button class="btn btn-primary btn-sm" onclick="enterResults(<?php echo $test['testId']; ?>)">
                                        <i class="fas fa-edit"></i> Enter Results
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Active Prescriptions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-prescription"></i> Active Prescriptions</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activePrescriptions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No active prescriptions</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activePrescriptions as $prescription): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($prescription['createdAt'])); ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($prescription['patientName']); ?></td>
                                <td data-label="Doctor">Dr. <?php echo $prescription['doctorName']; ?></td>
                                <td data-label="Medication"><?php echo $prescription['medicationName']; ?></td>
                                <td data-label="Dosage"><?php echo $prescription['dosage']; ?></td>
                                <td data-label="Frequency"><?php echo $prescription['frequency']; ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-active">Active</span>
                                </td>
                                <td data-label="Actions">
                                    <button class="btn btn-info btn-sm" onclick="viewPrescription(<?php echo $prescription['prescriptionId']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Medical Records -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-notes-medical"></i> Recent Medical Records</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRecords as $record): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></td>
                            <td data-label="Patient"><?php echo htmlspecialchars($record['patientName']); ?></td>
                            <td data-label="Doctor">Dr. <?php echo htmlspecialchars($record['doctorName']); ?></td>
                            <td data-label="Diagnosis"><?php echo substr($record['diagnosis'], 0, 50) . (strlen($record['diagnosis']) > 50 ? '...' : ''); ?></td>
                            <td data-label="Actions">
                                <button class="btn btn-primary btn-sm" onclick="viewRecord(<?php echo $record['recordId']; ?>)">
                                    <i class="fas fa-eye"></i> View Full
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Record Vitals Modal -->
<div id="vitalsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Record Patient Vitals</h3>
            <span class="close" onclick="closeModal('vitalsModal')">&times;</span>
        </div>
        <form method="POST" action="record-vitals.php" id="vitals-form">
            <div class="modal-body">
                <input type="hidden" name="patient_id" id="vitals_patient_id">
                <p><strong>Patient:</strong> <span id="vitals_patient_name"></span></p>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" step="0.1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="body_temperature">Temperature (°C)</label>
                        <input type="number" id="body_temperature" name="body_temperature" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="heart_rate">Heart Rate (bpm)</label>
                        <input type="number" id="heart_rate" name="heart_rate">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bp_systolic">Blood Pressure (Systolic)</label>
                        <input type="number" id="bp_systolic" name="bp_systolic">
                    </div>
                    <div class="form-group">
                        <label for="bp_diastolic">Blood Pressure (Diastolic)</label>
                        <input type="number" id="bp_diastolic" name="bp_diastolic">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="respiratory_rate">Respiratory Rate</label>
                        <input type="number" id="respiratory_rate" name="respiratory_rate">
                    </div>
                    <div class="form-group">
                        <label for="oxygen_saturation">Oxygen Saturation (%)</label>
                        <input type="number" id="oxygen_saturation" name="oxygen_saturation">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="vitals_notes">Notes</label>
                    <textarea id="vitals_notes" name="notes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="record_vitals" class="btn btn-primary">Save Vitals</button>
                <button type="button" class="btn" onclick="closeModal('vitalsModal')">Cancel</button>
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

function viewPatient(patientId) {
    window.location.href = `../staff/patient-records.php?patient_id=${patientId}`;
}

function enterResults(testId) {
    window.location.href = `lab-test-results.php?test_id=${testId}`;
}

function viewPrescription(prescriptionId) {
    window.location.href = `prescription-details.php?id=${prescriptionId}`;
}

function viewRecord(recordId) {
    window.location.href = `../admin/medical-records.php?view=${recordId}`;
}
</script>

<?php include '../includes/footer.php'; ?>