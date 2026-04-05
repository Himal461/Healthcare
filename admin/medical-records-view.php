<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkAuth();

$recordId = $_GET['id'] ?? 0;

if (!$recordId) {
    $_SESSION['error'] = "Invalid medical record ID.";
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    exit();
}

// Get medical record details with doctor information
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           d.consultationFee,
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           pu.email as patientEmail,
           pu.phoneNumber as patientPhone,
           p.dateOfBirth,
           p.bloodType,
           a.dateTime as appointmentDate
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    WHERE mr.recordId = ?
");
$stmt->execute([$recordId]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = "Medical record not found.";
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    exit();
}

$pageTitle = "Medical Record Details - HealthManagement";
include '../includes/header.php';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Medical Record Details</h1>
        <p>View complete medical record information</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-notes-medical"></i> Record Information</h3>
        </div>
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-group">
                    <h4>Patient Information</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($record['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo $record['patientEmail']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $record['patientPhone']; ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $record['dateOfBirth'] ?: 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($record['dateOfBirth']); ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $record['bloodType'] ?: 'N/A'; ?></p>
                </div>
                <div class="info-group">
                    <h4>Doctor Information</h4>
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($record['doctorName']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($record['specialization']); ?></p>
                    <p><strong>Consultation Fee:</strong> $<?php echo number_format($record['consultationFee'], 2); ?></p>
                    <p><strong>Record Date:</strong> <?php echo date('M j, Y g:i A', strtotime($record['creationDate'])); ?></p>
                    <?php if ($record['appointmentDate']): ?>
                        <p><strong>Appointment Date:</strong> <?php echo date('M j, Y g:i A', strtotime($record['appointmentDate'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="record-section">
                <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                <div class="record-content">
                    <?php echo nl2br(htmlspecialchars($record['diagnosis'] ?: 'No diagnosis recorded')); ?>
                </div>
            </div>

            <?php if ($record['treatmentNotes']): ?>
            <div class="record-section">
                <h4><i class="fas fa-notes-medical"></i> Treatment Notes</h4>
                <div class="record-content">
                    <?php echo nl2br(htmlspecialchars($record['treatmentNotes'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($record['prescriptions']): ?>
            <div class="record-section">
                <h4><i class="fas fa-prescription"></i> Prescriptions</h4>
                <div class="record-content">
                    <?php 
                    $prescriptions = explode("\n", $record['prescriptions']);
                    echo "<ul>";
                    foreach ($prescriptions as $prescription) {
                        if (trim($prescription)) {
                            echo "<li>" . htmlspecialchars(trim($prescription)) . "</li>";
                        }
                    }
                    echo "</ul>";
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($record['followUpDate']): ?>
            <div class="record-section">
                <h4><i class="fas fa-calendar-check"></i> Follow-up Date</h4>
                <div class="record-content">
                    <?php echo date('M j, Y', strtotime($record['followUpDate'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Record
        </button>
        <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? 'dashboard.php'; ?>" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<style>
.record-section {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.record-section h4 {
    color: #1a75bc;
    margin-bottom: 15px;
    font-size: 16px;
}

.record-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
    color: #555;
}

.record-content ul {
    margin: 0;
    padding-left: 20px;
}

.record-content li {
    margin-bottom: 5px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    justify-content: center;
}

@media print {
    .dashboard-header,
    .form-actions,
    header,
    footer,
    .btn {
        display: none;
    }
    
    .record-section {
        break-inside: avoid;
    }
}
</style>

<?php include '../includes/footer.php'; ?>