<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkAuth();

$recordId = $_GET['id'] ?? 0;

if (!$recordId) {
    $_SESSION['error'] = "Invalid record ID.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit();
}

$stmt = $pdo->prepare("
    SELECT mr.*, CONCAT(du.firstName, ' ', du.lastName) as doctorName, d.specialization,
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName, pu.email as patientEmail,
           pu.phoneNumber as patientPhone, p.dateOfBirth, p.bloodType
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    WHERE mr.recordId = ? AND pu.role = 'patient'
");
$stmt->execute([$recordId]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = "Record not found.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit();
}

$pageTitle = "Medical Record Details - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> Medical Record Details</h1>
            <p>View complete medical record</p>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="admin-btn admin-btn-primary"><i class="fas fa-print"></i> Print</button>
            <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? 'medical-records.php'; ?>" class="admin-btn admin-btn-outline">Back</a>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-info-circle"></i> Record Information</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-patient-info-grid">
                <div class="admin-info-group">
                    <h4>Patient</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($record['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($record['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($record['patientPhone']); ?></p>
                    <p><strong>DOB:</strong> <?php echo $record['dateOfBirth'] ?: 'N/A'; ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $record['bloodType'] ?: 'N/A'; ?></p>
                </div>
                <div class="admin-info-group">
                    <h4>Doctor</h4>
                    <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($record['doctorName']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($record['specialization']); ?></p>
                    <p><strong>Record Date:</strong> <?php echo date('M j, Y g:i A', strtotime($record['creationDate'])); ?></p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <h4 style="color: #1e293b; margin-bottom: 15px;">Diagnosis</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                    <?php echo nl2br(htmlspecialchars($record['diagnosis'] ?: 'No diagnosis')); ?>
                </div>
            </div>

            <?php if ($record['treatmentNotes']): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 15px;">Treatment Notes</h4>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <?php echo nl2br(htmlspecialchars($record['treatmentNotes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($record['prescriptions']): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 15px;">Prescriptions</h4>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <?php echo nl2br(htmlspecialchars($record['prescriptions'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($record['followUpDate']): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 15px;">Follow-up Date</h4>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <?php echo date('M j, Y', strtotime($record['followUpDate'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>