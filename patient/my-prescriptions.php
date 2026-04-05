<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Prescriptions - HealthManagement";
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

// Get prescriptions
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

// Group by consultation
$groupedPrescriptions = [];
foreach ($prescriptions as $prescription) {
    $recordId = $prescription['recordId'];
    if (!isset($groupedPrescriptions[$recordId])) {
        $groupedPrescriptions[$recordId] = [
            'consultationDate' => $prescription['consultationDate'],
            'doctorName' => $prescription['doctorName'],
            'specialization' => $prescription['specialization'],
            'diagnosis' => $prescription['diagnosis'],
            'medications' => []
        ];
    }
    $groupedPrescriptions[$recordId]['medications'][] = $prescription;
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>My Prescriptions</h1>
            <p>View your medication history</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if (empty($groupedPrescriptions)): ?>
        <div class="empty-state">
            <i class="fas fa-prescription"></i>
            <h3>No Prescriptions Found</h3>
            <p>Your prescriptions will appear here after your consultations.</p>
            <a href="appointments.php?view=book" class="btn btn-primary">Book an Appointment</a>
        </div>
    <?php else: ?>
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
                        <h3>Diagnosis</h3>
                        <p><?php echo htmlspecialchars($record['diagnosis']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="medications-section">
                        <h3>Medications</h3>
                        <?php foreach ($record['medications'] as $med): ?>
                            <div class="medication-item">
                                <div class="medication-name">
                                    <strong><?php echo htmlspecialchars($med['medicationName']); ?></strong>
                                    <span class="status-badge status-<?php echo $med['status']; ?>">
                                        <?php echo ucfirst($med['status']); ?>
                                    </span>
                                </div>
                                <div class="medication-details">
                                    <div><strong>Dosage:</strong> <?php echo htmlspecialchars($med['dosage']); ?></div>
                                    <div><strong>Frequency:</strong> <?php echo htmlspecialchars($med['frequency']); ?></div>
                                    <div><strong>Duration:</strong> <?php echo date('M j, Y', strtotime($med['startDate'])); ?>
                                    <?php if ($med['endDate']): ?> - <?php echo date('M j, Y', strtotime($med['endDate'])); ?><?php endif; ?></div>
                                    <?php if ($med['instructions']): ?>
                                        <div><strong>Instructions:</strong> <?php echo htmlspecialchars($med['instructions']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.dashboard {
    max-width: 800px;
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

.prescription-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
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
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.diagnosis-section h3,
.medications-section h3 {
    color: #495057;
    margin-bottom: 10px;
    font-size: 16px;
}

.medication-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 10px;
}

.medication-name {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.medication-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
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

.btn-primary {
    background: #1a75bc;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
}
</style>

<?php include '../includes/footer.php'; ?>