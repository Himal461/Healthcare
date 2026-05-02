<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Patient Vitals - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$recordId = $_GET['record_id'] ?? 0;
$patientId = $_GET['patient_id'] ?? 0;

$query = "
    SELECT v.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName, 
           CONCAT(du.firstName, ' ', du.lastName) as recordedByName, 
           mr.diagnosis
    FROM vitals v 
    JOIN medical_records mr ON v.recordId = mr.recordId 
    JOIN patients p ON mr.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId
    LEFT JOIN staff s ON v.recordedBy = s.staffId 
    LEFT JOIN users du ON s.userId = du.userId 
    WHERE 1=1
";
$params = [];

if ($recordId) {
    $query .= " AND v.recordId = ?";
    $params[] = $recordId;
}
if ($patientId) {
    $query .= " AND p.patientId = ?";
    $params[] = $patientId;
}
$query .= " ORDER BY v.recordedDate DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vitals = $stmt->fetchAll();
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-heartbeat"></i> Patient Vitals</h1>
            <p>View vital signs records</p>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-list"></i> Vitals Records (<?php echo count($vitals); ?>)</h3>
        </div>
        <div class="nurse-table-responsive">
            <table class="nurse-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>BP</th>
                        <th>HR</th>
                        <th>Temp</th>
                        <th>Weight</th>
                        <th>SpO2</th>
                        <th>Recorded By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vitals as $v): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y g:i A', strtotime($v['recordedDate'])); ?></td>
                            <td data-label="Patient"><?php echo htmlspecialchars($v['patientName']); ?></td>
                            <td data-label="BP"><?php echo $v['bloodPressureSystolic'] ? $v['bloodPressureSystolic'].'/'.$v['bloodPressureDiastolic'] : '-'; ?></td>
                            <td data-label="HR"><?php echo $v['heartRate'] ?: '-'; ?> bpm</td>
                            <td data-label="Temp"><?php echo $v['bodyTemperature'] ?: '-'; ?> °C</td>
                            <td data-label="Weight"><?php echo $v['weight'] ?: '-'; ?> kg</td>
                            <td data-label="SpO2"><?php echo $v['oxygenSaturation'] ?: '-'; ?>%</td>
                            <td data-label="Recorded By"><?php echo $v['recordedByName'] ?: 'Nurse'; ?></td>
                            <td data-label="Actions">
                                <button class="nurse-btn nurse-btn-primary nurse-btn-sm" onclick="viewDetails(<?php echo $v['vitalsId']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewDetails(id) {
    window.location.href = `vitals-details.php?id=${id}`;
}
</script>

<?php include '../includes/footer.php'; ?>