<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient'); // This should be 'patient', not 'doctor'

$pageTitle = "My Medical Records - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: dashboard.php");
    exit();
}

$patientId = $patient['patientId'];

// Get medical records for this patient only
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

// Get prescriptions for each record
$prescriptionsByRecord = [];
foreach ($records as $record) {
    $presStmt = $pdo->prepare("
        SELECT * FROM prescriptions 
        WHERE recordId = ? 
        ORDER BY createdAt DESC
    ");
    $presStmt->execute([$record['recordId']]);
    $prescriptionsByRecord[$record['recordId']] = $presStmt->fetchAll();
}

// Get specific record if view parameter is set
$viewRecordId = (int)($_GET['view'] ?? 0);
$selectedRecord = null;
if ($viewRecordId) {
    foreach ($records as $record) {
        if ($record['recordId'] == $viewRecordId) {
            $selectedRecord = $record;
            break;
        }
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>My Medical Records</h1>
            <p>View your complete medical history</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php if ($selectedRecord): ?>
                <a href="my-medical-records.php" class="btn btn-outline">
                    <i class="fas fa-list"></i> View All Records
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selectedRecord): ?>
        <!-- Single Record View -->
        <div class="record-card">
            <div class="record-header">
                <div class="record-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('F j, Y', strtotime($selectedRecord['creationDate'])); ?>
                    <?php if ($selectedRecord['appointmentDate']): ?>
                        <span class="appointment-time">
                            (Appointment: <?php echo date('g:i A', strtotime($selectedRecord['appointmentDate'])); ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="record-doctor">
                    <i class="fas fa-user-md"></i>
                    Dr. <?php echo htmlspecialchars($selectedRecord['doctorName']); ?>
                    <span class="specialization">(<?php echo htmlspecialchars($selectedRecord['specialization']); ?>)</span>
                </div>
            </div>
            <div class="record-body">
                <?php if ($selectedRecord['diagnosis']): ?>
                    <div class="record-section">
                        <h3><i class="fas fa-stethoscope"></i> Diagnosis</h3>
                        <p><?php echo nl2br(htmlspecialchars($selectedRecord['diagnosis'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($selectedRecord['treatmentNotes']): ?>
                    <div class="record-section">
                        <h3><i class="fas fa-notes-medical"></i> Treatment Notes</h3>
                        <p><?php echo nl2br(htmlspecialchars($selectedRecord['treatmentNotes'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($prescriptionsByRecord[$selectedRecord['recordId']])): ?>
                    <div class="record-section">
                        <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
                        <div class="prescriptions-list">
                            <?php foreach ($prescriptionsByRecord[$selectedRecord['recordId']] as $prescription): ?>
                                <div class="prescription-item">
                                    <div class="medication-name">
                                        <i class="fas fa-capsules"></i>
                                        <strong><?php echo htmlspecialchars($prescription['medicationName']); ?></strong>
                                    </div>
                                    <div class="medication-details">
                                        <div><strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?></div>
                                        <div><strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?></div>
                                        <?php if ($prescription['instructions']): ?>
                                            <div><strong>Instructions:</strong> <?php echo htmlspecialchars($prescription['instructions']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($selectedRecord['followUpDate']): ?>
                    <div class="record-section followup-section">
                        <h3><i class="fas fa-calendar-check"></i> Follow-up Date</h3>
                        <p><strong><?php echo date('F j, Y', strtotime($selectedRecord['followUpDate'])); ?></strong></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif (empty($records)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Medical Records Found</h3>
            <p>Your medical records will appear here after your consultations.</p>
            <a href="appointments.php?view=book" class="btn btn-primary">Book an Appointment</a>
        </div>
    <?php else: ?>
        <!-- List View -->
        <div class="records-list">
            <?php foreach ($records as $record): ?>
                <div class="record-card">
                    <div class="record-header">
                        <div class="record-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F j, Y', strtotime($record['creationDate'])); ?>
                        </div>
                        <div class="record-doctor">
                            <i class="fas fa-user-md"></i>
                            Dr. <?php echo htmlspecialchars($record['doctorName']); ?>
                            <span class="specialization">(<?php echo htmlspecialchars($record['specialization']); ?>)</span>
                        </div>
                    </div>
                    <div class="record-body">
                        <div class="record-section">
                            <h3>Diagnosis</h3>
                            <p><?php echo nl2br(htmlspecialchars(substr($record['diagnosis'], 0, 150))); ?>...</p>
                        </div>
                        <div class="record-actions">
                            <a href="my-medical-records.php?view=<?php echo $record['recordId']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View Full Record
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.dashboard {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-header h1 {
    margin: 0;
    color: #333;
}

.record-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.record-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.record-date {
    color: #6c757d;
    font-size: 14px;
}

.record-doctor {
    color: #1a75bc;
    font-weight: 500;
}

.specialization {
    color: #6c757d;
    font-size: 12px;
    font-weight: normal;
}

.record-body {
    padding: 20px;
}

.record-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.record-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.record-section h3 {
    color: #495057;
    margin-bottom: 10px;
    font-size: 16px;
}

.record-actions {
    text-align: right;
    margin-top: 15px;
}

.prescriptions-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.prescription-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #1a75bc;
}

.medication-name {
    margin-bottom: 10px;
    color: #1a75bc;
}

.medication-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.followup-section {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 8px;
    border: none;
}

.followup-section h3 {
    color: #004085;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.empty-state i {
    font-size: 64px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #495057;
    margin-bottom: 10px;
}

.btn-primary {
    background: #1a75bc;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}

.btn-primary:hover {
    background: #0e5a92;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .record-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .prescriptions-list {
        grid-template-columns: 1fr;
    }
    
    .medication-details {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../includes/footer.php'; ?>