<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Vitals Details - HealthManagement";
include '../includes/header.php';

$vitalsId = $_GET['id'] ?? 0;

if (!$vitalsId) {
    $_SESSION['error'] = "Invalid vitals ID.";
    header("Location: vitals.php");
    exit();
}

// Get vitals details
$stmt = $pdo->prepare("
    SELECT v.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as recordedByName,
           mr.diagnosis,
           mr.treatmentNotes
    FROM vitals v
    JOIN medical_records mr ON v.recordId = mr.recordId
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users du ON s.userId = du.userId
    WHERE v.vitalsId = ?
");
$stmt->execute([$vitalsId]);
$vital = $stmt->fetch();

if (!$vital) {
    $_SESSION['error'] = "Vitals record not found.";
    header("Location: vitals.php");
    exit();
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Vitals Details</h1>
        <p>Patient: <?php echo htmlspecialchars($vital['patientName']); ?></p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Vitals Record - <?php echo date('M j, Y g:i A', strtotime($vital['recordedDate'])); ?></h3>
        </div>
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-group">
                    <h4>Patient Information</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($vital['patientName']); ?></p>
                    <p><strong>Diagnosis:</strong> <?php echo $vital['diagnosis']; ?></p>
                </div>
                <div class="info-group">
                    <h4>Recorded By</h4>
                    <p><strong>Name:</strong> <?php echo $vital['recordedByName'] ?: 'Nurse'; ?></p>
                    <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($vital['recordedDate'])); ?></p>
                </div>
            </div>
            
            <div class="info-group" style="margin-top: 20px;">
                <h4>Vital Signs</h4>
                <div class="vitals-grid">
                    <div class="vital-item">
                        <strong>Height:</strong> <?php echo $vital['height'] ?: '-'; ?> cm
                    </div>
                    <div class="vital-item">
                        <strong>Weight:</strong> <?php echo $vital['weight'] ?: '-'; ?> kg
                    </div>
                    <div class="vital-item">
                        <strong>BMI:</strong> <?php echo ($vital['height'] && $vital['weight']) ? round($vital['weight'] / (($vital['height']/100) ** 2), 1) : '-'; ?>
                    </div>
                    <div class="vital-item">
                        <strong>Temperature:</strong> <?php echo $vital['bodyTemperature'] ?: '-'; ?> °C
                    </div>
                    <div class="vital-item">
                        <strong>Blood Pressure:</strong> <?php echo $vital['bloodPressureSystolic'] ? $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic'] : '-'; ?>
                    </div>
                    <div class="vital-item">
                        <strong>Heart Rate:</strong> <?php echo $vital['heartRate'] ?: '-'; ?> bpm
                    </div>
                    <div class="vital-item">
                        <strong>Respiratory Rate:</strong> <?php echo $vital['respiratoryRate'] ?: '-'; ?> breaths/min
                    </div>
                    <div class="vital-item">
                        <strong>Oxygen Saturation:</strong> <?php echo $vital['oxygenSaturation'] ?: '-'; ?>%
                    </div>
                </div>
            </div>
            
            <?php if ($vital['notes']): ?>
            <div class="info-group" style="margin-top: 20px;">
                <h4>Notes</h4>
                <p><?php echo nl2br(htmlspecialchars($vital['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="form-actions" style="margin-top: 20px;">
                <a href="vitals.php?record_id=<?php echo $vital['recordId']; ?>" class="btn btn-outline">Back to Vitals</a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Record
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>