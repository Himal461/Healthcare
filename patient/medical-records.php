<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "Medical Records - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'];

// Get medical records
$stmt = $pdo->prepare("
    SELECT mr.*, u.firstName as doctorFirstName, u.lastName as doctorLastName,
           d.specialization, a.dateTime as appointmentDate
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
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Medical Records</h1>
        <p>View your medical history and treatment records</p>
    </div>

    <?php if (empty($records)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Medical Records Found</h3>
            <p>Your medical records will appear here after your appointments.</p>
        </div>
    <?php else: ?>
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
                            Dr. <?php echo $record['doctorFirstName'] . ' ' . $record['doctorLastName']; ?>
                            <span class="specialization">(<?php echo $record['specialization']; ?>)</span>
                        </div>
                    </div>
                    <div class="record-body">
                        <?php if ($record['diagnosis']): ?>
                            <div class="record-section">
                                <h3><i class="fas fa-stethoscope"></i> Diagnosis</h3>
                                <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($record['treatmentNotes']): ?>
                            <div class="record-section">
                                <h3><i class="fas fa-notes-medical"></i> Treatment Notes</h3>
                                <p><?php echo nl2br(htmlspecialchars($record['treatmentNotes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($record['prescriptions']): ?>
                            <div class="record-section">
                                <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
                                <p><?php echo nl2br(htmlspecialchars($record['prescriptions'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($record['followUpDate']): ?>
                            <div class="record-section">
                                <h3><i class="fas fa-calendar-check"></i> Follow-up Date</h3>
                                <p><?php echo date('F j, Y', strtotime($record['followUpDate'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.dashboard {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.dashboard-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e9ecef;
}

.dashboard-header h1 {
    color: #1a75bc;
    font-size: 28px;
    margin-bottom: 10px;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: #f8f9fa;
    border-radius: 12px;
}

.empty-state i {
    font-size: 60px;
    color: #1a75bc;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 10px;
    color: #333;
}

.empty-state p {
    color: #666;
}

.records-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.record-card {
    background: #f8f9fa;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.record-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.record-header {
    background: #1a75bc;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.record-date i,
.record-doctor i {
    margin-right: 8px;
}

.record-doctor .specialization {
    opacity: 0.9;
    font-size: 12px;
}

.record-body {
    padding: 20px;
}

.record-section {
    margin-bottom: 20px;
}

.record-section:last-child {
    margin-bottom: 0;
}

.record-section h3 {
    color: #1a75bc;
    font-size: 16px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.record-section p {
    color: #555;
    line-height: 1.6;
    margin-left: 25px;
}

@media (max-width: 768px) {
    .record-header {
        flex-direction: column;
    }
    
    .record-section p {
        margin-left: 0;
    }
}
</style>

<?php include '../includes/footer.php'; ?>