<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$recordId = (int)($_GET['id'] ?? 0);

if (!$recordId) {
    $_SESSION['error'] = "Invalid record ID.";
    header("Location: patients.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: dashboard.php");
    exit();
}

$doctorId = $doctor['doctorId'];

// Get medical record with verification that this doctor has access
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           pu.email as patientEmail,
           pu.phoneNumber as patientPhone,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies,
           p.patientId,
           CONCAT(du.firstName, ' ', du.lastName) as recordDoctorName,
           d.specialization
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE mr.recordId = ? AND pu.role = 'patient'
");
$stmt->execute([$recordId]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = "Medical record not found.";
    header("Location: patients.php");
    exit();
}

// Verify this doctor has access to this patient
$verifyStmt = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT patientId FROM appointments WHERE patientId = ? AND doctorId = ?
        UNION
        SELECT patientId FROM medical_records WHERE patientId = ? AND doctorId = ?
        UNION
        SELECT patientId FROM lab_tests WHERE patientId = ? AND orderedBy = ?
    ) AS patient_access
");
$verifyStmt->execute([$record['patientId'], $doctorId, $record['patientId'], $doctorId, $record['patientId'], $doctorId]);

if ($verifyStmt->fetchColumn() == 0) {
    $_SESSION['error'] = "You don't have permission to view this patient's records.";
    header("Location: patients.php");
    exit();
}

// Get vitals for this record
$vitalsStmt = $pdo->prepare("
    SELECT v.*, CONCAT(u.firstName, ' ', u.lastName) as recordedByName
    FROM vitals v
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    WHERE v.recordId = ? ORDER BY v.recordedDate DESC
");
$vitalsStmt->execute([$recordId]);
$vitals = $vitalsStmt->fetchAll();

// Get prescriptions for this record
$prescriptionsStmt = $pdo->prepare("
    SELECT * FROM prescriptions WHERE recordId = ? ORDER BY createdAt DESC
");
$prescriptionsStmt->execute([$recordId]);
$prescriptions = $prescriptionsStmt->fetchAll();

$pageTitle = "Medical Record - " . htmlspecialchars($record['patientName']) . " - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
include '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> Medical Record Details</h1>
            <p><?php echo htmlspecialchars($record['patientName']); ?> - <?php echo date('F j, Y', strtotime($record['creationDate'])); ?></p>
        </div>
        <div class="header-actions">
            <a href="patients.php?view=<?php echo $record['patientId']; ?>" class="doctor-btn doctor-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Patient
            </a>
            <button onclick="window.print()" class="doctor-btn doctor-btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
        </div>
        <div class="doctor-card-body">
            <div class="doctor-patient-info-grid">
                <div class="doctor-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($record['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($record['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($record['patientPhone']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $record['dateOfBirth'] ? date('M j, Y', strtotime($record['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($record['dateOfBirth']); ?></p>
                </div>
                <div class="doctor-info-group">
                    <h4>Medical Information</h4>
                    <p><strong>Blood Type:</strong> <?php echo $record['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($record['knownAllergies'] ?: 'None reported'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Details -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-stethoscope"></i> Consultation Details</h3>
        </div>
        <div class="doctor-card-body">
            <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($record['recordDoctorName']); ?> (<?php echo htmlspecialchars($record['specialization']); ?>)</p>
            <p><strong>Consultation Date:</strong> <?php echo date('F j, Y g:i A', strtotime($record['creationDate'])); ?></p>
            
            <h4 style="margin-top: 25px; color: #1e293b;">Diagnosis</h4>
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-top: 10px;">
                <?php echo nl2br(htmlspecialchars($record['diagnosis'] ?: 'No diagnosis recorded')); ?>
            </div>
            
            <?php if ($record['treatmentNotes']): ?>
                <h4 style="margin-top: 25px; color: #1e293b;">Treatment Notes</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-top: 10px;">
                    <?php echo nl2br(htmlspecialchars($record['treatmentNotes'])); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($record['followUpDate']): ?>
                <h4 style="margin-top: 25px; color: #1e293b;">Follow-up Date</h4>
                <div style="background: #e8f2ff; padding: 15px; border-radius: 12px; margin-top: 10px;">
                    <i class="fas fa-calendar-check" style="color: #2563eb;"></i>
                    <strong><?php echo date('F j, Y', strtotime($record['followUpDate'])); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vitals -->
    <?php if (!empty($vitals)): ?>
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
            </div>
            <div class="doctor-card-body">
                <?php foreach ($vitals as $vital): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 12px;">
                        <p style="margin-bottom: 15px;">
                            <i class="fas fa-calendar-alt"></i> 
                            Recorded on <?php echo date('F j, Y g:i A', strtotime($vital['recordedDate'])); ?>
                            by <?php echo htmlspecialchars($vital['recordedByName'] ?: 'Nurse'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                            <?php if ($vital['height']): ?>
                                <div><strong>Height:</strong> <?php echo $vital['height']; ?> cm</div>
                            <?php endif; ?>
                            <?php if ($vital['weight']): ?>
                                <div><strong>Weight:</strong> <?php echo $vital['weight']; ?> kg</div>
                            <?php endif; ?>
                            <?php if ($vital['bodyTemperature']): ?>
                                <div><strong>Temperature:</strong> <?php echo $vital['bodyTemperature']; ?> °C</div>
                            <?php endif; ?>
                            <?php if ($vital['bloodPressureSystolic']): ?>
                                <div><strong>Blood Pressure:</strong> <?php echo $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic']; ?> mmHg</div>
                            <?php endif; ?>
                            <?php if ($vital['heartRate']): ?>
                                <div><strong>Heart Rate:</strong> <?php echo $vital['heartRate']; ?> bpm</div>
                            <?php endif; ?>
                            <?php if ($vital['oxygenSaturation']): ?>
                                <div><strong>O₂ Saturation:</strong> <?php echo $vital['oxygenSaturation']; ?>%</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($vital['notes']): ?>
                            <p style="margin-top: 15px;"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($vital['notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Prescriptions -->
    <?php if (!empty($prescriptions)): ?>
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
            </div>
            <div class="doctor-card-body">
                <?php foreach ($prescriptions as $prescription): ?>
                    <div style="background: #f8fafc; padding: 18px; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid #2563eb;">
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
</div>

<?php include '../includes/footer.php'; ?>