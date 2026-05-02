<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Prescription Details - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$prescriptionId = $_GET['id'] ?? 0;
if (!$prescriptionId) { 
    $_SESSION['error'] = "Invalid ID."; 
    header("Location: prescriptions.php"); 
    exit(); 
}

$stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName, 
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           u.email as patientEmail, 
           u.phoneNumber as patientPhone, 
           mr.diagnosis, 
           mr.treatmentNotes
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
$p = $stmt->fetch();

if (!$p) { 
    $_SESSION['error'] = "Not found."; 
    header("Location: prescriptions.php"); 
    exit(); 
}
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-prescription"></i> Prescription Details</h1>
            <p><?php echo htmlspecialchars($p['patientName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="prescriptions.php" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="nurse-btn nurse-btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-info-circle"></i> Information</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-patient-info-grid">
                <div class="nurse-info-group">
                    <h4>Patient</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($p['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($p['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($p['patientPhone']); ?></p>
                </div>
                <div class="nurse-info-group">
                    <h4>Prescriber</h4>
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($p['doctorName']); ?></p>
                    <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($p['createdAt'])); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="nurse-status-badge nurse-status-<?php echo $p['status']; ?>">
                            <?php echo ucfirst($p['status']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div style="margin-top: 25px;">
                <h4 style="color: #1e293b; margin-bottom: 15px;">Medication</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border-left: 4px solid #6f42c1;">
                    <p><strong style="font-size: 18px;"><?php echo htmlspecialchars($p['medicationName']); ?></strong></p>
                    <p><strong>Dosage:</strong> <?php echo htmlspecialchars($p['dosage']); ?></p>
                    <p><strong>Frequency:</strong> <?php echo htmlspecialchars($p['frequency']); ?></p>
                    <?php if ($p['instructions']): ?>
                        <p style="margin-top: 15px; padding: 15px; background: white; border-radius: 8px;">
                            <strong>Instructions:</strong><br>
                            <?php echo nl2br(htmlspecialchars($p['instructions'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>