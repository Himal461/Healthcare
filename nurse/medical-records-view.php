<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$recordId = (int)($_GET['id'] ?? 0);

if (!$recordId) {
    $_SESSION['error'] = "Invalid medical record ID.";
    header("Location: medical-records.php");
    exit();
}

$pageTitle = "Medical Record Details - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

// Get medical record details
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           pu.email as patientEmail,
           pu.phoneNumber as patientPhone,
           p.patientId,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    WHERE mr.recordId = ?
");
$stmt->execute([$recordId]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = "Medical record not found.";
    header("Location: medical-records.php");
    exit();
}

// Get vitals for this record
$vitalsStmt = $pdo->prepare("
    SELECT v.*, 
           CONCAT(u.firstName, ' ', u.lastName) as recordedByName
    FROM vitals v
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    WHERE v.recordId = ?
    ORDER BY v.recordedDate DESC
");
$vitalsStmt->execute([$recordId]);
$vitals = $vitalsStmt->fetchAll();

// Get prescriptions for this record
$prescriptionsStmt = $pdo->prepare("
    SELECT p.*
    FROM prescriptions p
    WHERE p.recordId = ?
    ORDER BY p.createdAt DESC
");
$prescriptionsStmt->execute([$recordId]);
$prescriptions = $prescriptionsStmt->fetchAll();

// Get lab tests for this record
$labTestsStmt = $pdo->prepare("
    SELECT lt.*
    FROM lab_tests lt
    WHERE lt.recordId = ?
    ORDER BY lt.orderedDate DESC
");
$labTestsStmt->execute([$recordId]);
$labTests = $labTestsStmt->fetchAll();
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> Medical Record Details</h1>
            <p>Record #<?php echo $recordId; ?> - <?php echo date('F j, Y', strtotime($record['creationDate'])); ?></p>
        </div>
        <div class="header-actions">
            <a href="medical-records.php" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Records
            </a>
            <a href="patient-history.php?patient_id=<?php echo $record['patientId']; ?>" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-history"></i> Patient History
            </a>
            <button onclick="window.print()" class="nurse-btn nurse-btn-primary">
                <i class="fas fa-print"></i> Print Record
            </button>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-patient-info-grid">
                <div class="nurse-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($record['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($record['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($record['patientPhone']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $record['dateOfBirth'] ? date('M j, Y', strtotime($record['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($record['dateOfBirth']); ?></p>
                </div>
                <div class="nurse-info-group">
                    <h4>Medical Information</h4>
                    <p><strong>Blood Type:</strong> <?php echo $record['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($record['knownAllergies'] ?: 'None reported'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Details -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-stethoscope"></i> Consultation Details</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-info-group" style="margin-bottom: 20px;">
                <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($record['doctorName']); ?> (<?php echo htmlspecialchars($record['specialization']); ?>)</p>
                <p><strong>Consultation Date:</strong> <?php echo date('F j, Y g:i A', strtotime($record['creationDate'])); ?></p>
            </div>
            
            <h4 style="color: #1e293b; margin-bottom: 15px;">Diagnosis</h4>
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($record['diagnosis'] ?: 'No diagnosis recorded')); ?>
            </div>
            
            <?php if ($record['treatmentNotes']): ?>
                <h4 style="color: #1e293b; margin-bottom: 15px;">Treatment Notes</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($record['treatmentNotes'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vitals -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
        </div>
        <div class="nurse-card-body">
            <?php if (empty($vitals)): ?>
                <div class="nurse-empty-state">
                    <i class="fas fa-heartbeat"></i>
                    <p>No vitals recorded for this consultation.</p>
                    <a href="record-vitals.php?patient_id=<?php echo $record['patientId']; ?>" class="nurse-btn nurse-btn-primary">
                        <i class="fas fa-plus"></i> Record Vitals
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($vitals as $index => $vital): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 12px; <?php echo $index > 0 ? 'opacity: 0.85;' : ''; ?>">
                        <p style="margin-bottom: 15px;">
                            <i class="fas fa-calendar-alt"></i> 
                            Recorded on <?php echo date('F j, Y g:i A', strtotime($vital['recordedDate'])); ?>
                            by <?php echo htmlspecialchars($vital['recordedByName'] ?: 'Nurse'); ?>
                        </p>
                        <div class="nurse-vitals-grid">
                            <?php if ($vital['height']): ?>
                                <div class="nurse-vital-item">
                                    <span class="nurse-vital-label">Height</span>
                                    <span class="nurse-vital-value"><?php echo $vital['height']; ?> cm</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($vital['weight']): ?>
                                <div class="nurse-vital-item">
                                    <span class="nurse-vital-label">Weight</span>
                                    <span class="nurse-vital-value"><?php echo $vital['weight']; ?> kg</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($vital['bodyTemperature']): ?>
                                <div class="nurse-vital-item">
                                    <span class="nurse-vital-label">Temperature</span>
                                    <span class="nurse-vital-value"><?php echo $vital['bodyTemperature']; ?> °C</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($vital['bloodPressureSystolic']): ?>
                                <div class="nurse-vital-item">
                                    <span class="nurse-vital-label">Blood Pressure</span>
                                    <span class="nurse-vital-value"><?php echo $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic']; ?> mmHg</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($vital['heartRate']): ?>
                                <div class="nurse-vital-item">
                                    <span class="nurse-vital-label">Heart Rate</span>
                                    <span class="nurse-vital-value"><?php echo $vital['heartRate']; ?> bpm</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($vital['oxygenSaturation']): ?>
                                <div class="nurse-vital-item">
                                    <span class="nurse-vital-label">O₂ Saturation</span>
                                    <span class="nurse-vital-value"><?php echo $vital['oxygenSaturation']; ?>%</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($vital['notes']): ?>
                            <p style="margin-top: 15px; padding: 10px; background: white; border-radius: 8px;">
                                <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($vital['notes'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <a href="record-vitals.php?patient_id=<?php echo $record['patientId']; ?>" class="nurse-btn nurse-btn-outline nurse-btn-sm">
                    <i class="fas fa-plus"></i> Add New Vitals
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prescriptions -->
    <?php if (!empty($prescriptions)): ?>
        <div class="nurse-card">
            <div class="nurse-card-header">
                <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
            </div>
            <div class="nurse-card-body">
                <?php foreach ($prescriptions as $prescription): ?>
                    <div style="background: #f8fafc; padding: 18px; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid #6f42c1;">
                        <p><strong><?php echo htmlspecialchars($prescription['medicationName']); ?></strong></p>
                        <p>Dosage: <?php echo htmlspecialchars($prescription['dosage']); ?> | Frequency: <?php echo htmlspecialchars($prescription['frequency']); ?></p>
                        <?php if ($prescription['instructions']): ?>
                            <p style="margin-top: 10px; padding: 10px; background: white; border-radius: 6px;">
                                <strong>Instructions:</strong> <?php echo htmlspecialchars($prescription['instructions']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lab Tests -->
    <?php if (!empty($labTests)): ?>
        <div class="nurse-card">
            <div class="nurse-card-header">
                <h3><i class="fas fa-flask"></i> Laboratory Tests</h3>
            </div>
            <div class="nurse-card-body">
                <div class="nurse-table-responsive">
                    <table class="nurse-data-table">
                        <thead>
                            <tr>
                                <th>Test Name</th>
                                <th>Type</th>
                                <th>Ordered Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($labTests as $test): ?>
                                <tr>
                                    <td data-label="Test Name"><?php echo htmlspecialchars($test['testName']); ?></td>
                                    <td data-label="Type"><?php echo htmlspecialchars($test['testType'] ?: '-'); ?></td>
                                    <td data-label="Ordered Date"><?php echo date('M j, Y', strtotime($test['orderedDate'])); ?></td>
                                    <td data-label="Status">
                                        <span class="nurse-test-status-badge status-<?php echo $test['status']; ?>">
                                            <?php echo ucfirst(str_replace('-', ' ', $test['status'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="lab-test-results.php?test_id=<?php echo $test['testId']; ?>" class="nurse-btn nurse-btn-info nurse-btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>