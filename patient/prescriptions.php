<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Patient Prescriptions - HealthManagement";
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

// Get all prescriptions for this patient
$stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           d.specialization,
           mr.diagnosis,
           mr.creationDate as consultationDate
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

// Group prescriptions by record
$groupedPrescriptions = [];
foreach ($prescriptions as $prescription) {
    $recordId = $prescription['recordId'];
    if (!isset($groupedPrescriptions[$recordId])) {
        $groupedPrescriptions[$recordId] = [
            'recordId' => $prescription['recordId'],
            'consultationDate' => $prescription['consultationDate'],
            'doctorName' => $prescription['doctorName'],
            'specialization' => $prescription['specialization'],
            'diagnosis' => $prescription['diagnosis'],
            'medications' => []
        ];
    }
    $groupedPrescriptions[$recordId]['medications'][] = [
        'prescriptionId' => $prescription['prescriptionId'],
        'medicationName' => $prescription['medicationName'],
        'dosage' => $prescription['dosage'],
        'frequency' => $prescription['frequency'],
        'instructions' => $prescription['instructions'],
        'refills' => $prescription['refills'],
        'status' => $prescription['status'],
        'startDate' => $prescription['startDate'],
        'endDate' => $prescription['endDate'],
        'createdAt' => $prescription['createdAt']
    ];
}

// Get active prescriptions
$activePrescriptions = array_filter($groupedPrescriptions, function($record) {
    foreach ($record['medications'] as $med) {
        if ($med['status'] == 'active') {
            return true;
        }
    }
    return false;
});
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>Prescription History</h1>
            <p>Viewing prescriptions for <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong></p>
        </div>
        <div>
            <a href="../doctor/consultation.php?patient_id=<?php echo $patientId; ?>" class="btn btn-primary">
                <i class="fas fa-prescription"></i> New Prescription
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
                </div>
                <div class="info-group">
                    <p><strong>Allergies:</strong> <?php echo $patient['knownAllergies'] ?: 'None'; ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Total Prescriptions:</strong> <?php echo count($prescriptions); ?></p>
                    <p><strong>Active Courses:</strong> <?php echo count($activePrescriptions); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($groupedPrescriptions)): ?>
        <div class="empty-state">
            <i class="fas fa-prescription"></i>
            <h3>No Prescriptions Found</h3>
            <p>This patient has no prescriptions yet.</p>
            <a href="../doctor/consultation.php?patient_id=<?php echo $patientId; ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Start New Prescription
            </a>
        </div>
    <?php else: ?>
        <div class="prescriptions-list">
            <?php foreach ($groupedPrescriptions as $record): ?>
                <div class="prescription-card">
                    <div class="prescription-header">
                        <div class="prescription-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F j, Y', strtotime($record['consultationDate'])); ?>
                        </div>
                        <div class="prescription-doctor">
                            <i class="fas fa-user-md"></i>
                            Dr. <?php echo htmlspecialchars($record['doctorName']); ?>
                            <span class="specialization">(<?php echo htmlspecialchars($record['specialization']); ?>)</span>
                        </div>
                    </div>
                    <div class="prescription-body">
                        <?php if ($record['diagnosis']): ?>
                            <div class="diagnosis-section">
                                <h3><i class="fas fa-stethoscope"></i> Diagnosis</h3>
                                <p><?php echo htmlspecialchars($record['diagnosis']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="medications-section">
                            <h3><i class="fas fa-prescription"></i> Prescribed Medications</h3>
                            <div class="medications-list">
                                <?php foreach ($record['medications'] as $medication): ?>
                                    <div class="medication-card">
                                        <div class="medication-header">
                                            <div class="medication-name">
                                                <i class="fas fa-capsules"></i>
                                                <strong><?php echo htmlspecialchars($medication['medicationName']); ?></strong>
                                            </div>
                                            <div class="medication-status">
                                                <span class="badge badge-<?php echo $medication['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($medication['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="medication-details">
                                            <div class="detail-row">
                                                <span class="detail-label">Dosage:</span>
                                                <span><?php echo htmlspecialchars($medication['dosage']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Frequency:</span>
                                                <span><?php echo htmlspecialchars($medication['frequency']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Refills:</span>
                                                <span><?php echo $medication['refills']; ?> remaining</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Duration:</span>
                                                <span>
                                                    <?php echo date('M j, Y', strtotime($medication['startDate'])); ?>
                                                    <?php if ($medication['endDate']): ?>
                                                        - <?php echo date('M j, Y', strtotime($medication['endDate'])); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <?php if ($medication['instructions']): ?>
                                                <div class="detail-row instructions">
                                                    <span class="detail-label">Instructions:</span>
                                                    <span><?php echo htmlspecialchars($medication['instructions']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
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

.prescriptions-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.prescription-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.prescription-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.prescription-date {
    color: #6c757d;
    font-size: 14px;
}

.prescription-doctor {
    color: #1a75bc;
    font-weight: 500;
}

.specialization {
    color: #6c757d;
    font-size: 12px;
    font-weight: normal;
}

.prescription-body {
    padding: 20px;
}

.diagnosis-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.diagnosis-section h3,
.medications-section h3 {
    color: #495057;
    font-size: 18px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.medications-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
}

.medication-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.medication-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.medication-name {
    font-size: 16px;
    color: #1a75bc;
}

.medication-name i {
    margin-right: 8px;
}

.medication-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    font-size: 14px;
}

.detail-label {
    font-weight: 600;
    color: #495057;
}

.detail-row.instructions {
    grid-column: 1 / -1;
    background: #fff3cd;
    padding: 8px 12px;
    border-radius: 5px;
    margin-top: 5px;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-secondary {
    background: #e2e3e5;
    color: #383d41;
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
    
    .prescription-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .medication-details {
        grid-template-columns: 1fr;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>