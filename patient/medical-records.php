<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Patient Medical Records - HealthManagement";
include '../includes/header.php';

$patientId = (int)($_GET['patient_id'] ?? 0);

if (!$patientId) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: ../doctor/patients.php");
    exit();
}

// Get patient details
$stmt = $pdo->prepare("
    SELECT p.*, u.firstName, u.lastName, u.email, u.phoneNumber
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE p.patientId = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header("Location: ../doctor/patients.php");
    exit();
}

// Get medical records for this specific patient
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

// Get vitals for each record
$vitalsByRecord = [];
foreach ($records as $record) {
    $vitalsStmt = $pdo->prepare("
        SELECT * FROM vitals 
        WHERE recordId = ? 
        ORDER BY recordedDate DESC
        LIMIT 5
    ");
    $vitalsStmt->execute([$record['recordId']]);
    $vitalsByRecord[$record['recordId']] = $vitalsStmt->fetchAll();
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>Medical Records</h1>
            <p>Viewing medical history for <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong></p>
        </div>
        <div>
            <a href="../doctor/consultation.php?patient_id=<?php echo $patientId; ?>" class="btn btn-primary">
                <i class="fas fa-notes-medical"></i> New Consultation
            </a>
            <a href="../doctor/patients.php?view=<?php echo $patientId; ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Patient
            </a>
        </div>
    </div>

    <!-- Patient Summary Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
        </div>
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-group">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ?: 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
                </div>
                <div class="info-group">
                    <p><strong>Allergies:</strong> <?php echo $patient['knownAllergies'] ?: 'None'; ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Insurance:</strong> <?php echo $patient['insuranceProvider'] ?: 'N/A'; ?></p>
                    <p><strong>Total Records:</strong> <?php echo count($records); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Medical Records Found</h3>
            <p>This patient has no medical records yet.</p>
            <a href="../doctor/consultation.php?patient_id=<?php echo $patientId; ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Start First Consultation
            </a>
        </div>
    <?php else: ?>
        <div class="records-list">
            <?php foreach ($records as $record): ?>
                <div class="record-card">
                    <div class="record-header">
                        <div class="record-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F j, Y', strtotime($record['creationDate'])); ?>
                            <?php if ($record['appointmentDate']): ?>
                                <span class="appointment-time">
                                    (Appointment: <?php echo date('g:i A', strtotime($record['appointmentDate'])); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="record-doctor">
                            <i class="fas fa-user-md"></i>
                            Dr. <?php echo htmlspecialchars($record['doctorName']); ?>
                            <span class="specialization">(<?php echo htmlspecialchars($record['specialization']); ?>)</span>
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
                        
                        <?php if (!empty($prescriptionsByRecord[$record['recordId']])): ?>
                            <div class="record-section">
                                <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
                                <div class="medications">
                                    <?php foreach ($prescriptionsByRecord[$record['recordId']] as $prescription): ?>
                                        <div class="medication-item">
                                            <div class="medication-name">
                                                <i class="fas fa-capsules"></i>
                                                <strong><?php echo htmlspecialchars($prescription['medicationName']); ?></strong>
                                            </div>
                                            <div class="medication-details">
                                                <span><strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?></span>
                                                <span><strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?></span>
                                                <span><strong>Refills:</strong> <?php echo $prescription['refills']; ?></span>
                                                <?php if ($prescription['instructions']): ?>
                                                    <span><strong>Instructions:</strong> <?php echo htmlspecialchars($prescription['instructions']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($vitalsByRecord[$record['recordId']])): ?>
                            <div class="record-section">
                                <h3><i class="fas fa-heartbeat"></i> Vitals</h3>
                                <div class="vitals-grid">
                                    <?php foreach ($vitalsByRecord[$record['recordId']] as $vital): ?>
                                        <div class="vital-item">
                                            <strong><?php echo date('M j, Y', strtotime($vital['recordedDate'])); ?></strong>
                                            <?php if ($vital['bloodPressureSystolic']): ?>
                                                <div>BP: <?php echo $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic']; ?> mmHg</div>
                                            <?php endif; ?>
                                            <?php if ($vital['heartRate']): ?>
                                                <div>Heart Rate: <?php echo $vital['heartRate']; ?> bpm</div>
                                            <?php endif; ?>
                                            <?php if ($vital['bodyTemperature']): ?>
                                                <div>Temperature: <?php echo $vital['bodyTemperature']; ?>°C</div>
                                            <?php endif; ?>
                                            <?php if ($vital['weight']): ?>
                                                <div>Weight: <?php echo $vital['weight']; ?> kg</div>
                                            <?php endif; ?>
                                            <?php if ($vital['height']): ?>
                                                <div>Height: <?php echo $vital['height']; ?> cm</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($record['followUpDate']): ?>
                            <div class="record-section followup-section">
                                <h3><i class="fas fa-calendar-check"></i> Follow-up Date</h3>
                                <p><strong><?php echo date('F j, Y', strtotime($record['followUpDate'])); ?></strong></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-header .btn {
    margin-left: 10px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    color: #495057;
}

.card-body {
    padding: 20px;
}

.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-group p {
    margin: 8px 0;
}

.records-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.record-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

.appointment-time {
    color: #1a75bc;
    font-size: 12px;
    margin-left: 5px;
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
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.record-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.record-section h3 {
    color: #495057;
    font-size: 18px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.medications {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.medication-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
}

.medication-name {
    margin-bottom: 8px;
    color: #1a75bc;
}

.medication-details {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 14px;
    color: #6c757d;
}

.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.vital-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
}

.vital-item div {
    margin-top: 5px;
    color: #6c757d;
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

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .dashboard-header .btn {
        margin-left: 0;
        margin-right: 10px;
    }
    
    .record-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .vitals-grid {
        grid-template-columns: 1fr;
    }
    
    .medication-details {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>