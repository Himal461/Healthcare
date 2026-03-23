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
$patientId = $patient['patientId'];

// Get prescriptions from medical records
$stmt = $pdo->prepare("
    SELECT mr.*, u.firstName as doctorFirstName, u.lastName as doctorLastName,
           d.specialization, a.dateTime as appointmentDate
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    WHERE mr.patientId = ? AND (mr.prescriptions IS NOT NULL AND mr.prescriptions != '')
    ORDER BY mr.creationDate DESC
");
$stmt->execute([$patientId]);
$prescriptions = $stmt->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>My Prescriptions</h1>
        <p>View and manage your medications</p>
    </div>

    <?php if (empty($prescriptions)): ?>
        <div class="empty-state">
            <i class="fas fa-prescription"></i>
            <h3>No Prescriptions Found</h3>
            <p>Your prescriptions will appear here after your appointments.</p>
        </div>
    <?php else: ?>
        <div class="prescriptions-list">
            <?php foreach ($prescriptions as $prescription): ?>
                <div class="prescription-card">
                    <div class="prescription-header">
                        <div class="prescription-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F j, Y', strtotime($prescription['creationDate'])); ?>
                        </div>
                        <div class="prescription-doctor">
                            <i class="fas fa-user-md"></i>
                            Dr. <?php echo $prescription['doctorFirstName'] . ' ' . $prescription['doctorLastName']; ?>
                        </div>
                    </div>
                    <div class="prescription-body">
                        <div class="prescription-content">
                            <h3><i class="fas fa-prescription"></i> Prescribed Medications</h3>
                            <div class="medications">
                                <?php
                                $medications = explode("\n", $prescription['prescriptions']);
                                foreach ($medications as $med):
                                    if (trim($med)):
                                ?>
                                    <div class="medication-item">
                                        <i class="fas fa-capsules"></i>
                                        <?php echo htmlspecialchars(trim($med)); ?>
                                    </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>
                        
                        <?php if ($prescription['diagnosis']): ?>
                            <div class="prescription-diagnosis">
                                <h3><i class="fas fa-stethoscope"></i> Diagnosis</h3>
                                <p><?php echo htmlspecialchars($prescription['diagnosis']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($prescription['treatmentNotes']): ?>
                            <div class="prescription-notes">
                                <h3><i class="fas fa-notes-medical"></i> Doctor's Notes</h3>
                                <p><?php echo nl2br(htmlspecialchars($prescription['treatmentNotes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="prescription-footer">
                        <button class="btn btn-outline" onclick="printPrescription(<?php echo $prescription['recordId']; ?>)">
                            <i class="fas fa-print"></i> Print Prescription
                        </button>
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

.prescriptions-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.prescription-card {
    background: #f8f9fa;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.prescription-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.prescription-header {
    background: #28a745;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.prescription-body {
    padding: 20px;
}

.prescription-content h3,
.prescription-diagnosis h3,
.prescription-notes h3 {
    color: #1a75bc;
    font-size: 16px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.medications {
    margin-bottom: 20px;
}

.medication-item {
    padding: 10px;
    background: white;
    border-radius: 8px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 3px solid #28a745;
}

.medication-item i {
    color: #28a745;
}

.prescription-diagnosis,
.prescription-notes {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.prescription-diagnosis p,
.prescription-notes p {
    color: #555;
    line-height: 1.6;
    margin-left: 25px;
}

.prescription-footer {
    padding: 15px 20px;
    background: #fff;
    border-top: 1px solid #e9ecef;
    text-align: right;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

@media (max-width: 768px) {
    .prescription-header {
        flex-direction: column;
    }
}
</style>

<script>
function printPrescription(recordId) {
    window.print();
}
</script>

<?php include '../includes/footer.php'; ?>