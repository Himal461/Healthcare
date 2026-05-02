<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$patientId = (int)($_GET['patient_id'] ?? 0);

if (!$patientId) {
    $_SESSION['error'] = "Patient ID is required.";
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
$verifyStmt->execute([$patientId, $doctorId, $patientId, $doctorId, $patientId, $doctorId]);

if ($verifyStmt->fetchColumn() == 0) {
    $_SESSION['error'] = "You don't have permission to view this patient's records.";
    header("Location: patients.php");
    exit();
}

// Get patient details
$stmt = $pdo->prepare("
    SELECT p.*, CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email, u.phoneNumber
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE p.patientId = ? AND u.role = 'patient'
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header("Location: patients.php");
    exit();
}

// Get all medical records for this patient
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           a.dateTime as appointmentDate
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    WHERE mr.patientId = ?
    ORDER BY mr.creationDate DESC
");
$stmt->execute([$patientId]);
$records = $stmt->fetchAll();

$pageTitle = "Medical Records - " . htmlspecialchars($patient['patientName']) . " - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
include '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> Medical Records</h1>
            <p>Viewing medical history for <strong><?php echo htmlspecialchars($patient['patientName']); ?></strong></p>
        </div>
        <div class="header-actions">
            <a href="consultation.php?patient_id=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-primary">
                <i class="fas fa-notes-medical"></i> New Consultation
            </a>
            <a href="patients.php?view=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Patient
            </a>
        </div>
    </div>

    <!-- Patient Summary Card -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
        </div>
        <div class="doctor-card-body">
            <div class="doctor-patient-info-grid">
                <div class="doctor-info-group">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ? date('M j, Y', strtotime($patient['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
                </div>
                <div class="doctor-info-group">
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['knownAllergies'] ?: 'None'); ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Total Records:</strong> <?php echo count($records); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="doctor-empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Medical Records Found</h3>
            <p>This patient has no medical records yet.</p>
            <a href="consultation.php?patient_id=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-primary">
                <i class="fas fa-plus"></i> Start First Consultation
            </a>
        </div>
    <?php else: ?>
        <div class="records-list" style="display: grid; gap: 20px;">
            <?php foreach ($records as $record): ?>
                <div class="doctor-card">
                    <div class="doctor-card-header" style="background: #f8fafc;">
                        <div>
                            <i class="fas fa-calendar-alt" style="color: #2563eb;"></i>
                            <?php echo date('F j, Y', strtotime($record['creationDate'])); ?>
                        </div>
                        <div>
                            <i class="fas fa-user-md" style="color: #2563eb;"></i>
                            Dr. <?php echo htmlspecialchars($record['doctorName']); ?>
                            <span class="doctor-text-muted">(<?php echo htmlspecialchars($record['specialization']); ?>)</span>
                        </div>
                    </div>
                    <div class="doctor-card-body">
                        <?php if ($record['diagnosis']): ?>
                            <h4 style="color: #1e293b; margin-bottom: 10px;"><i class="fas fa-stethoscope" style="color: #2563eb;"></i> Diagnosis</h4>
                            <p style="background: #f8fafc; padding: 15px; border-radius: 8px;"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($record['treatmentNotes']): ?>
                            <h4 style="color: #1e293b; margin: 20px 0 10px;"><i class="fas fa-notes-medical" style="color: #2563eb;"></i> Treatment Notes</h4>
                            <p style="background: #f8fafc; padding: 15px; border-radius: 8px;"><?php echo nl2br(htmlspecialchars($record['treatmentNotes'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($record['followUpDate']): ?>
                            <div style="margin-top: 20px; padding: 15px; background: #e8f2ff; border-radius: 8px;">
                                <i class="fas fa-calendar-check" style="color: #2563eb;"></i>
                                <strong>Follow-up Date:</strong> <?php echo date('F j, Y', strtotime($record['followUpDate'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>