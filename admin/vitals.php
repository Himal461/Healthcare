<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Patient Vitals - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';

$vitals = $pdo->query("
    SELECT v.*, CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as recordedByName
    FROM vitals v
    JOIN medical_records mr ON v.recordId = mr.recordId
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users du ON s.userId = du.userId
    WHERE u.role = 'patient'
    ORDER BY v.recordedDate DESC
")->fetchAll();
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-heartbeat"></i> Patient Vitals</h1>
            <p>Track patient vital signs</p>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Vitals Records</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Height</th>
                        <th>Weight</th>
                        <th>Temp</th>
                        <th>BP</th>
                        <th>HR</th>
                        <th>SpO2</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vitals)): ?>
                        <tr><td colspan="9" class="admin-empty-message">No vitals recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vitals as $v): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($v['recordedDate'])); ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($v['patientName']); ?></td>
                                <td data-label="Height"><?php echo $v['height'] ? $v['height'].' cm' : '-'; ?></td>
                                <td data-label="Weight"><?php echo $v['weight'] ? $v['weight'].' kg' : '-'; ?></td>
                                <td data-label="Temp"><?php echo $v['bodyTemperature'] ? $v['bodyTemperature'].'°C' : '-'; ?></td>
                                <td data-label="BP"><?php echo $v['bloodPressureSystolic'] ? $v['bloodPressureSystolic'].'/'.$v['bloodPressureDiastolic'] : '-'; ?></td>
                                <td data-label="HR"><?php echo $v['heartRate'] ? $v['heartRate'].' bpm' : '-'; ?></td>
                                <td data-label="SpO2"><?php echo $v['oxygenSaturation'] ? $v['oxygenSaturation'].'%' : '-'; ?></td>
                                <td data-label="Recorded By"><?php echo htmlspecialchars($v['recordedByName'] ?: 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>