<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Medical Records - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: dashboard.php");
    exit();
}

$patientId = $patient['patientId'];

// Get medical records
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           d.specialization,
           a.dateTime as appointmentDate
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    WHERE mr.patientId = ?
    ORDER BY mr.creationDate DESC
");
$stmt->execute([$patientId]);
$records = $stmt->fetchAll();

// Get specific record if view parameter is set
$viewRecordId = (int)($_GET['view'] ?? 0);
$selectedRecord = null;
if ($viewRecordId) {
    foreach ($records as $record) {
        if ($record['recordId'] == $viewRecordId) {
            $selectedRecord = $record;
            
            // Get prescriptions for this record
            $presStmt = $pdo->prepare("SELECT * FROM prescriptions WHERE recordId = ? ORDER BY createdAt DESC");
            $presStmt->execute([$record['recordId']]);
            $selectedRecord['prescriptions'] = $presStmt->fetchAll();
            break;
        }
    }
}
?>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> My Medical Records</h1>
            <p>View your complete medical history</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php if ($selectedRecord): ?>
                <a href="my-medical-records.php" class="patient-btn patient-btn-outline">
                    <i class="fas fa-list"></i> View All Records
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selectedRecord): ?>
        <!-- Single Record View -->
        <div class="patient-card">
            <div class="patient-card-header">
                <div>
                    <div class="patient-record-date">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y', strtotime($selectedRecord['creationDate'])); ?></span>
                    </div>
                </div>
            </div>
            <div class="patient-card-body">
                <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                    <div class="patient-doctor-avatar-small">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 5px 0;">Dr. <?php echo htmlspecialchars($selectedRecord['doctorName']); ?></h3>
                        <span class="patient-specialty-pill"><?php echo htmlspecialchars($selectedRecord['specialization']); ?></span>
                    </div>
                </div>
                
                <h4 style="color: #1e293b; margin-bottom: 15px;">
                    <i class="fas fa-stethoscope" style="color: #0d9488;"></i> Diagnosis
                </h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <?php echo nl2br(htmlspecialchars($selectedRecord['diagnosis'] ?: 'No diagnosis recorded')); ?>
                </div>
                
                <?php if ($selectedRecord['treatmentNotes']): ?>
                    <h4 style="color: #1e293b; margin-bottom: 15px;">
                        <i class="fas fa-clipboard-list" style="color: #0d9488;"></i> Treatment Plan
                    </h4>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                        <?php echo nl2br(htmlspecialchars($selectedRecord['treatmentNotes'])); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($selectedRecord['prescriptions'])): ?>
                    <h4 style="color: #1e293b; margin-bottom: 15px;">
                        <i class="fas fa-prescription" style="color: #0d9488;"></i> Prescribed Medications
                    </h4>
                    <div class="patient-medications-grid">
                        <?php foreach ($selectedRecord['prescriptions'] as $prescription): ?>
                            <div class="patient-medication-item-card">
                                <div class="patient-medication-item-header">
                                    <div class="patient-medication-name-wrapper">
                                        <i class="fas fa-capsules"></i>
                                        <strong><?php echo htmlspecialchars($prescription['medicationName']); ?></strong>
                                    </div>
                                </div>
                                <div class="patient-medication-item-body">
                                    <div class="patient-info-line">
                                        <span class="patient-info-label">Dosage:</span>
                                        <span class="patient-info-value"><?php echo htmlspecialchars($prescription['dosage']); ?></span>
                                    </div>
                                    <div class="patient-info-line">
                                        <span class="patient-info-label">Frequency:</span>
                                        <span class="patient-info-value"><?php echo htmlspecialchars($prescription['frequency']); ?></span>
                                    </div>
                                    <?php if ($prescription['instructions']): ?>
                                        <div class="patient-instructions-box">
                                            <i class="fas fa-info-circle"></i>
                                            <span><?php echo htmlspecialchars($prescription['instructions']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif (empty($records)): ?>
        <div class="patient-empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Medical Records Found</h3>
            <p>Your medical records will appear here after your consultations.</p>
            <a href="appointments.php" class="patient-btn patient-btn-primary">Book Your First Appointment</a>
        </div>
    <?php else: ?>
        <!-- Records Grid -->
        <div class="patient-records-grid">
            <?php foreach ($records as $record): ?>
                <div class="patient-record-card">
                    <div class="patient-record-header">
                        <div class="patient-record-date">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></span>
                        </div>
                        <div class="patient-record-doctor">
                            <i class="fas fa-user-md"></i>
                            <span>Dr. <?php echo htmlspecialchars($record['doctorName']); ?></span>
                        </div>
                    </div>
                    <div class="patient-record-body">
                        <span class="patient-specialty-pill"><?php echo htmlspecialchars($record['specialization']); ?></span>
                        <div class="patient-diagnosis-preview">
                            <p class="patient-diagnosis-label">Diagnosis:</p>
                            <p class="patient-diagnosis-text"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 150)); ?>...</p>
                        </div>
                    </div>
                    <div class="patient-record-footer">
                        <a href="?view=<?php echo $record['recordId']; ?>" class="patient-btn patient-btn-primary patient-btn-block">
                            <i class="fas fa-eye"></i> View Full Record
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>