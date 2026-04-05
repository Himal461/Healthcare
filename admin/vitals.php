<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Patient Vitals - HealthManagement";
include '../includes/header.php';

// Get all vitals
$vitals = $pdo->query("
    SELECT v.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as recordedByName
    FROM vitals v
    JOIN medical_records mr ON v.recordId = mr.recordId
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users du ON s.userId = du.userId
    ORDER BY v.recordedDate DESC
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Patient Vitals</h1>
        <p>Track and manage patient vital signs</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>All Vitals Records</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Height</th>
                        <th>Weight</th>
                        <th>Temperature</th>
                        <th>Blood Pressure</th>
                        <th>Heart Rate</th>
                        <th>SpO2</th>
                    </thead>
                <tbody>
                    <?php foreach ($vitals as $vital): ?>
                    <tr>
                        <td data-label="Date"><?php echo date('M j, Y', strtotime($vital['recordedDate'])); ?></td>
                        <td data-label="Patient"><?php echo $vital['patientName']; ?></td>
                        <td data-label="Height"><?php echo $vital['height'] ?: '-'; ?> cm</td>
                        <td data-label="Weight"><?php echo $vital['weight'] ?: '-'; ?> kg</td>
                        <td data-label="Temperature"><?php echo $vital['bodyTemperature'] ?: '-'; ?> °C</td>
                        <td data-label="BP"><?php echo $vital['bloodPressureSystolic'] ? $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic'] : '-'; ?></td>
                        <td data-label="Heart Rate"><?php echo $vital['heartRate'] ?: '-'; ?> bpm</td>
                        <td data-label="SpO2"><?php echo $vital['oxygenSaturation'] ?: '-'; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>