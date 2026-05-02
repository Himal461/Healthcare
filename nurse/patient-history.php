<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$patientId = (int)($_GET['patient_id'] ?? 0);

if (!$patientId) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: medical-records.php");
    exit();
}

$pageTitle = "Patient History - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

// Get patient details
$stmt = $pdo->prepare("
    SELECT p.*, CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email, u.phoneNumber, u.dateCreated
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE p.patientId = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header("Location: medical-records.php");
    exit();
}

// Get all medical records
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
$stmt->execute([$patientId]);
$records = $stmt->fetchAll();

// Get all appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE a.patientId = ?
    ORDER BY a.dateTime DESC
");
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

// Get all vitals
$stmt = $pdo->prepare("
    SELECT v.*, 
           CONCAT(du.firstName, ' ', du.lastName) as recordedByName
    FROM vitals v
    JOIN medical_records mr ON v.recordId = mr.recordId
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users du ON s.userId = du.userId
    WHERE mr.patientId = ?
    ORDER BY v.recordedDate DESC
");
$stmt->execute([$patientId]);
$vitals = $stmt->fetchAll();
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-history"></i> Patient History</h1>
            <p><?php echo htmlspecialchars($patient['patientName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="medical-records.php" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Records
            </a>
            <a href="record-vitals.php?patient_id=<?php echo $patientId; ?>" class="nurse-btn nurse-btn-primary">
                <i class="fas fa-heartbeat"></i> Record Vitals
            </a>
        </div>
    </div>

    <!-- Patient Summary -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Summary</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-patient-info-grid">
                <div class="nurse-info-group">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ? date('M j, Y', strtotime($patient['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
                </div>
                <div class="nurse-info-group">
                    <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['knownAllergies'] ?: 'None'); ?></p>
                    <p><strong>Total Records:</strong> <?php echo count($records); ?></p>
                    <p><strong>Total Appointments:</strong> <?php echo count($appointments); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Medical Records -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-notes-medical"></i> Medical Records (<?php echo count($records); ?>)</h3>
        </div>
        <div class="nurse-card-body">
            <?php if (empty($records)): ?>
                <p class="nurse-text-muted" style="text-align: center;">No medical records found.</p>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <div style="display: flex; gap: 20px; padding: 15px; background: #f8fafc; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid #6f42c1;">
                        <div style="min-width: 100px; font-weight: 600; color: #6f42c1;">
                            <?php echo date('M j, Y', strtotime($record['creationDate'])); ?>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 10px 0;">Dr. <?php echo htmlspecialchars($record['doctorName']); ?> (<?php echo htmlspecialchars($record['specialization']); ?>)</h4>
                            <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars(substr($record['diagnosis'], 0, 100)); ?>...</p>
                            <a href="medical-records-view.php?id=<?php echo $record['recordId']; ?>" class="nurse-btn nurse-btn-outline nurse-btn-sm">View Full Record</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Appointments -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-calendar-alt"></i> Appointments (<?php echo count($appointments); ?>)</h3>
        </div>
        <div class="nurse-table-responsive">
            <table class="nurse-data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($a['dateTime'])); ?></td>
                            <td data-label="Doctor">Dr. <?php echo htmlspecialchars($a['doctorName']); ?></td>
                            <td data-label="Specialization"><?php echo htmlspecialchars($a['specialization']); ?></td>
                            <td data-label="Status">
                                <span class="nurse-status-badge nurse-status-<?php echo $a['status']; ?>">
                                    <?php echo ucfirst($a['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Vitals History -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-heartbeat"></i> Vitals History (<?php echo count($vitals); ?>)</h3>
        </div>
        <div class="nurse-table-responsive">
            <table class="nurse-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Recorded By</th>
                        <th>BP</th>
                        <th>HR</th>
                        <th>Temp</th>
                        <th>Weight</th>
                        <th>SpO2</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vitals as $v): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($v['recordedDate'])); ?></td>
                            <td data-label="Recorded By"><?php echo htmlspecialchars($v['recordedByName'] ?: 'Nurse'); ?></td>
                            <td data-label="BP"><?php echo $v['bloodPressureSystolic'] ? $v['bloodPressureSystolic'].'/'.$v['bloodPressureDiastolic'] : '-'; ?></td>
                            <td data-label="HR"><?php echo $v['heartRate'] ?: '-'; ?></td>
                            <td data-label="Temp"><?php echo $v['bodyTemperature'] ?: '-'; ?>°C</td>
                            <td data-label="Weight"><?php echo $v['weight'] ?: '-'; ?> kg</td>
                            <td data-label="SpO2"><?php echo $v['oxygenSaturation'] ?: '-'; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>