<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Prescription Details - HealthManagement";
include '../includes/header.php';

$prescriptionId = $_GET['id'] ?? 0;

if (!$prescriptionId) {
    $_SESSION['error'] = "Invalid prescription ID.";
    header("Location: prescriptions.php");
    exit();
}

// Get prescription details
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
$prescription = $stmt->fetch();

if (!$prescription) {
    $_SESSION['error'] = "Prescription not found.";
    header("Location: prescriptions.php");
    exit();
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Prescription Details</h1>
        <p>View prescription information for <?php echo htmlspecialchars($prescription['patientName']); ?></p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Prescription Information</h3>
        </div>
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-group">
                    <h4>Patient Information</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($prescription['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($prescription['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($prescription['patientPhone']); ?></p>
                    <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($prescription['diagnosis']); ?></p>
                </div>
                <div class="info-group">
                    <h4>Prescriber Information</h4>
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?></p>
                    <p><strong>Prescribed Date:</strong> <?php echo date('M j, Y', strtotime($prescription['createdAt'])); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo $prescription['status']; ?>"><?php echo ucfirst($prescription['status']); ?></span></p>
                </div>
            </div>
            
            <div class="info-group" style="margin-top: 20px;">
                <h4>Medication Details</h4>
                <p><strong>Medication:</strong> <?php echo htmlspecialchars($prescription['medicationName']); ?></p>
                <p><strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?></p>
                <p><strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?></p>
                <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($prescription['startDate'])); ?></p>
                <p><strong>End Date:</strong> <?php echo $prescription['endDate'] ? date('M j, Y', strtotime($prescription['endDate'])) : 'Ongoing'; ?></p>
                <p><strong>Refills:</strong> <?php echo $prescription['refills'] ?? 0; ?></p>
            </div>
            
            <?php if ($prescription['instructions']): ?>
            <div class="info-group" style="margin-top: 20px;">
                <h4>Instructions</h4>
                <p><?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="form-actions" style="margin-top: 20px;">
                <a href="prescriptions.php" class="btn btn-outline">Back to Prescriptions</a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Prescription
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>