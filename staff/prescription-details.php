<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$prescriptionId = (int)($_GET['id'] ?? 0);

if (!$prescriptionId) {
    $_SESSION['error'] = "Invalid prescription ID.";
    header("Location: patient-records.php");
    exit();
}

$pageTitle = "Prescription Details - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           pt.patientId,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           mr.diagnosis,
           mr.creationDate as consultationDate
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN patients pt ON mr.patientId = pt.patientId
    JOIN users u ON pt.userId = u.userId
    JOIN doctors d ON p.prescribedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE p.prescriptionId = ?
");
$stmt->execute([$prescriptionId]);
$prescription = $stmt->fetch();

if (!$prescription) {
    $_SESSION['error'] = "Prescription not found.";
    header("Location: patient-records.php");
    exit();
}
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-prescription"></i> Prescription Details</h1>
            <p><?php echo htmlspecialchars($prescription['patientName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="patient-records.php?patient_id=<?php echo $prescription['patientId']; ?>" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="staff-btn staff-btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h3><i class="fas fa-info-circle"></i> Prescription Information</h3>
            <span class="staff-status-badge staff-status-<?php echo $prescription['status']; ?>">
                <?php echo ucfirst($prescription['status']); ?>
            </span>
        </div>
        <div class="staff-card-body">
            <div class="staff-patient-info-grid">
                <div class="staff-info-group">
                    <h4><i class="fas fa-user"></i> Patient</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($prescription['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($prescription['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($prescription['patientPhone']); ?></p>
                </div>
                <div class="staff-info-group">
                    <h4><i class="fas fa-user-md"></i> Prescriber</h4>
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($prescription['specialization']); ?></p>
                    <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($prescription['createdAt'])); ?></p>
                </div>
            </div>
            
            <div style="margin-top: 25px;">
                <h4 style="color: #1e293b; margin-bottom: 15px;">Medication</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border-left: 4px solid #f59e0b;">
                    <p><strong style="font-size: 18px;"><?php echo htmlspecialchars($prescription['medicationName']); ?></strong></p>
                    <p><strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?></p>
                    <p><strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?></p>
                    <p><strong>Duration:</strong> 
                        <?php echo date('M j, Y', strtotime($prescription['startDate'])); ?>
                        <?php if ($prescription['endDate']): ?>
                            - <?php echo date('M j, Y', strtotime($prescription['endDate'])); ?>
                        <?php else: ?>
                            (Ongoing)
                        <?php endif; ?>
                    </p>
                    <?php if ($prescription['instructions']): ?>
                        <p style="margin-top: 15px; padding: 15px; background: #fffbeb; border-radius: 8px;">
                            <strong>Instructions:</strong><br>
                            <?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($prescription['diagnosis']): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 15px;">Diagnosis</h4>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>