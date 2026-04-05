<?php
require_once '..\/includes\/config.php';
require_once '..\/includes\/auth.php';
checkRole('nurse');

$pageTitle = "Patient Vitals - HealthManagement";
include '..\/includes\/header.php';

$userId = $_SESSION['user_id'];
$recordId = $_GET['record_id'] ?? 0;
$patientId = $_GET['patient_id'] ?? 0;

// Get vitals records
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

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Patient Vitals</h1>
        <p>View and track patient vital signs</p>
    </div>

    <!-- Vitals List -->
    <div class="card">
        <div class="card-header">
            <h3>Vitals Records (<?php echo count($vitals); ?> found)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>BP</th>
                        <th>Heart Rate</th>
                        <th>Temperature</th>
                        <th>Weight</th>
                        <th>Height</th>
                        <th>SpO2</th>
                        <th>Recorded By</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php if (empty($vitals)): ?>
                    
                            <td colspan="10" style="text-align: center;">No vitals records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vitals as $vital): ?>
                        
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($vital['recordedDate'])); ?></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($vital['patientName']); ?></strong>
                                </td>
                                <td data-label="BP">
                                    <?php echo $vital['bloodPressureSystolic'] ? $vital['bloodPressureSystolic'] . '\/' . $vital['bloodPressureDiastolic'] : '-'; ?>
                                </td>
                                <td data-label="Heart Rate"><?php echo $vital['heartRate'] ?: '-'; ?> bpm</td>
                                <td data-label="Temperature"><?php echo $vital['bodyTemperature'] ?: '-'; ?> °C</td>
                                <td data-label="Weight"><?php echo $vital['weight'] ?: '-'; ?> kg<td>
                                <td data-label="Height"><?php echo $vital['height'] ?: '-'; ?> cm</td>
                                <td data-label="SpO2"><?php echo $vital['oxygenSaturation'] ?: '-'; ?>%</td>
                                <td data-label="Recorded By"><?php echo $vital['recordedByName'] ?: 'Nurse'; ?></td>
                                <td data-label="Actions">
                                    <button class="btn btn-primary btn-sm" onclick="viewVitalsDetails(<?php echo $vital['vitalsId']; ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewVitalsDetails(vitalsId) {
    window.location.href = `vitals-details.php?id=${vitalsId}`;
}
<\/script>

<?php include '..\/includes\/footer.php'; ?>