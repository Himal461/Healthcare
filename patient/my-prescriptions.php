<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Prescriptions - HealthManagement";
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

// Get prescriptions grouped by consultation
$stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           d.specialization,
           mr.diagnosis,
           mr.creationDate as consultationDate,
           mr.recordId
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN doctors d ON p.prescribedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE mr.patientId = ?
    ORDER BY p.createdAt DESC
");
$stmt->execute([$patientId]);
$prescriptions = $stmt->fetchAll();

// Group by consultation
$groupedPrescriptions = [];
foreach ($prescriptions as $prescription) {
    $recordId = $prescription['recordId'];
    if (!isset($groupedPrescriptions[$recordId])) {
        $groupedPrescriptions[$recordId] = [
            'consultationDate' => $prescription['consultationDate'],
            'doctorName' => $prescription['doctorName'],
            'specialization' => $prescription['specialization'],
            'diagnosis' => $prescription['diagnosis'],
            'medications' => []
        ];
    }
    $groupedPrescriptions[$recordId]['medications'][] = $prescription;
}
?>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-prescription"></i> My Prescriptions</h1>
            <p>View and manage your medication history</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (empty($groupedPrescriptions)): ?>
        <div class="patient-empty-state">
            <i class="fas fa-prescription-bottle"></i>
            <h3>No Prescriptions Found</h3>
            <p>Your prescriptions will appear here after your consultations.</p>
            <a href="appointments.php" class="patient-btn patient-btn-primary">Book an Appointment</a>
        </div>
    <?php else: ?>
        <div class="patient-prescriptions-timeline">
            <?php foreach ($groupedPrescriptions as $record): ?>
                <div class="patient-prescription-group-card">
                    <div class="patient-group-header">
                        <div class="patient-consultation-date-badge">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date('F j, Y', strtotime($record['consultationDate'])); ?></span>
                        </div>
                        <div class="patient-doctor-info-row">
                            <div class="patient-doctor-avatar-small">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="patient-doctor-info-text">
                                <h3>Dr. <?php echo htmlspecialchars($record['doctorName']); ?></h3>
                                <span class="patient-specialty-pill"><?php echo htmlspecialchars($record['specialization']); ?></span>
                            </div>
                        </div>
                        <?php if ($record['diagnosis']): ?>
                            <div class="patient-diagnosis-box">
                                <i class="fas fa-stethoscope"></i>
                                <span><?php echo htmlspecialchars($record['diagnosis']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="patient-medications-section">
                        <div class="patient-medications-header">
                            <i class="fas fa-capsules"></i>
                            <h4>Prescribed Medications</h4>
                            <span class="patient-med-count"><?php echo count($record['medications']); ?> medication<?php echo count($record['medications']) > 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="patient-medications-grid">
                            <?php foreach ($record['medications'] as $med): ?>
                                <div class="patient-medication-item-card">
                                    <div class="patient-medication-item-header">
                                        <div class="patient-medication-name-wrapper">
                                            <i class="fas fa-pill"></i>
                                            <strong><?php echo htmlspecialchars($med['medicationName']); ?></strong>
                                        </div>
                                        <span class="patient-status-badge patient-status-<?php echo $med['status']; ?>">
                                            <?php echo ucfirst($med['status']); ?>
                                        </span>
                                    </div>
                                    <div class="patient-medication-item-body">
                                        <div class="patient-info-line">
                                            <span class="patient-info-label">Dosage:</span>
                                            <span class="patient-info-value"><?php echo htmlspecialchars($med['dosage']); ?></span>
                                        </div>
                                        <div class="patient-info-line">
                                            <span class="patient-info-label">Frequency:</span>
                                            <span class="patient-info-value"><?php echo htmlspecialchars($med['frequency']); ?></span>
                                        </div>
                                        <?php if ($med['instructions']): ?>
                                            <div class="patient-instructions-box">
                                                <i class="fas fa-info-circle"></i>
                                                <span><?php echo htmlspecialchars($med['instructions']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="patient-medication-item-footer">
                                        <i class="far fa-clock"></i>
                                        <span>Prescribed on <?php echo date('M j, Y', strtotime($med['createdAt'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>