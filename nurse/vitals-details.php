<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Vitals Details - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$vitalsId = $_GET['id'] ?? 0;
if (!$vitalsId) { 
    $_SESSION['error'] = "Invalid ID."; 
    header("Location: vitals.php"); 
    exit(); 
}

$stmt = $pdo->prepare("
    SELECT v.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName, 
           CONCAT(du.firstName, ' ', du.lastName) as recordedByName, 
           mr.diagnosis,
           p.patientId,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies
    FROM vitals v 
    JOIN medical_records mr ON v.recordId = mr.recordId 
    JOIN patients p ON mr.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId
    LEFT JOIN staff s ON v.recordedBy = s.staffId 
    LEFT JOIN users du ON s.userId = du.userId 
    WHERE v.vitalsId = ?
");
$stmt->execute([$vitalsId]);
$v = $stmt->fetch();

if (!$v) { 
    $_SESSION['error'] = "Vitals record not found."; 
    header("Location: vitals.php"); 
    exit(); 
}

// Calculate BMI if height and weight are available
$bmi = null;
$bmiCategory = '';
if ($v['height'] && $v['weight']) {
    $heightInMeters = $v['height'] / 100;
    $bmi = round($v['weight'] / ($heightInMeters * $heightInMeters), 1);
    
    if ($bmi < 18.5) {
        $bmiCategory = 'Underweight';
    } elseif ($bmi < 25) {
        $bmiCategory = 'Normal weight';
    } elseif ($bmi < 30) {
        $bmiCategory = 'Overweight';
    } else {
        $bmiCategory = 'Obese';
    }
}

// Blood pressure category
$bpCategory = '';
if ($v['bloodPressureSystolic'] && $v['bloodPressureDiastolic']) {
    if ($v['bloodPressureSystolic'] < 120 && $v['bloodPressureDiastolic'] < 80) {
        $bpCategory = 'Normal';
    } elseif ($v['bloodPressureSystolic'] < 130 && $v['bloodPressureDiastolic'] < 80) {
        $bpCategory = 'Elevated';
    } elseif ($v['bloodPressureSystolic'] < 140 || $v['bloodPressureDiastolic'] < 90) {
        $bpCategory = 'High (Stage 1)';
    } else {
        $bpCategory = 'High (Stage 2)';
    }
}
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-heartbeat"></i> Vitals Details</h1>
            <p><?php echo htmlspecialchars($v['patientName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="vitals.php" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Vitals
            </a>
            <a href="patient-history.php?patient_id=<?php echo $v['patientId']; ?>" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-history"></i> Patient History
            </a>
            <button onclick="window.print()" class="nurse-btn nurse-btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3>
                <i class="fas fa-calendar-alt"></i> 
                Recorded on <?php echo date('F j, Y g:i A', strtotime($v['recordedDate'])); ?>
            </h3>
        </div>
        <div class="nurse-card-body">
            <!-- Patient Information -->
            <div class="nurse-patient-info-grid">
                <div class="nurse-info-group">
                    <h4><i class="fas fa-user"></i> Patient</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($v['patientName']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $v['dateOfBirth'] ? date('M j, Y', strtotime($v['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($v['dateOfBirth']); ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $v['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($v['knownAllergies'] ?: 'None reported'); ?></p>
                </div>
                <div class="nurse-info-group">
                    <h4><i class="fas fa-user-nurse"></i> Recorded By</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($v['recordedByName'] ?: 'Nurse'); ?></p>
                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($v['recordedDate'])); ?></p>
                    <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($v['recordedDate'])); ?></p>
                    <?php if ($v['diagnosis']): ?>
                        <p><strong>Related Diagnosis:</strong> <?php echo htmlspecialchars($v['diagnosis']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vital Signs Grid -->
            <h4 style="color: #1e293b; margin: 30px 0 20px;">
                <i class="fas fa-activity" style="color: #6f42c1;"></i> Vital Signs
            </h4>
            
            <div class="nurse-vitals-grid">
                <?php if ($v['height']): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">Height</span>
                        <span class="nurse-vital-value"><?php echo $v['height']; ?> cm</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($v['weight']): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">Weight</span>
                        <span class="nurse-vital-value"><?php echo $v['weight']; ?> kg</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($bmi): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">BMI</span>
                        <span class="nurse-vital-value"><?php echo $bmi; ?></span>
                        <small style="display: block; font-size: 11px; color: #64748b;"><?php echo $bmiCategory; ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if ($v['bodyTemperature']): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">Temperature</span>
                        <span class="nurse-vital-value"><?php echo $v['bodyTemperature']; ?> °C</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($v['bloodPressureSystolic'] && $v['bloodPressureDiastolic']): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">Blood Pressure</span>
                        <span class="nurse-vital-value"><?php echo $v['bloodPressureSystolic'] . '/' . $v['bloodPressureDiastolic']; ?> mmHg</span>
                        <?php if ($bpCategory): ?>
                            <small style="display: block; font-size: 11px; color: #64748b;"><?php echo $bpCategory; ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($v['heartRate']): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">Heart Rate</span>
                        <span class="nurse-vital-value"><?php echo $v['heartRate']; ?> bpm</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($v['respiratoryRate']): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">Respiratory Rate</span>
                        <span class="nurse-vital-value"><?php echo $v['respiratoryRate']; ?> /min</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($v['oxygenSaturation']): ?>
                    <div class="nurse-vital-item">
                        <span class="nurse-vital-label">O₂ Saturation</span>
                        <span class="nurse-vital-value"><?php echo $v['oxygenSaturation']; ?>%</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reference Ranges -->
            <div style="margin-top: 25px; padding: 20px; background: #f0fdf4; border-radius: 12px; border-left: 4px solid #10b981;">
                <h4 style="color: #166534; margin-bottom: 15px;">
                    <i class="fas fa-info-circle"></i> Normal Reference Ranges
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 13px;">
                    <div><strong>Blood Pressure:</strong> < 120/80 mmHg</div>
                    <div><strong>Heart Rate:</strong> 60-100 bpm</div>
                    <div><strong>Temperature:</strong> 36.1-37.2 °C</div>
                    <div><strong>Respiratory Rate:</strong> 12-20 /min</div>
                    <div><strong>O₂ Saturation:</strong> 95-100%</div>
                    <div><strong>BMI:</strong> 18.5-24.9</div>
                </div>
            </div>

            <!-- Notes -->
            <?php if ($v['notes']): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 15px;">
                        <i class="fas fa-clipboard"></i> Notes
                    </h4>
                    <div style="background: #fefce8; padding: 20px; border-radius: 12px; border-left: 4px solid #f59e0b; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($v['notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <a href="vitals.php" class="nurse-btn nurse-btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="record-vitals.php?patient_id=<?php echo $v['patientId']; ?>" class="nurse-btn nurse-btn-primary">
                    <i class="fas fa-plus"></i> Record New Vitals
                </a>
                <button onclick="window.print()" class="nurse-btn nurse-btn-info">
                    <i class="fas fa-print"></i> Print Record
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>