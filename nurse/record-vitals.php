<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$patientId = (int)($_GET['patient_id'] ?? 0);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $bodyTemperature = !empty($_POST['body_temperature']) ? floatval($_POST['body_temperature']) : null;
    $heartRate = !empty($_POST['heart_rate']) ? intval($_POST['heart_rate']) : null;
    $bpSystolic = !empty($_POST['bp_systolic']) ? intval($_POST['bp_systolic']) : null;
    $bpDiastolic = !empty($_POST['bp_diastolic']) ? intval($_POST['bp_diastolic']) : null;
    $respiratoryRate = !empty($_POST['respiratory_rate']) ? intval($_POST['respiratory_rate']) : null;
    $oxygenSaturation = !empty($_POST['oxygen_saturation']) ? intval($_POST['oxygen_saturation']) : null;
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (!$patientId) {
        $error = "Invalid patient ID.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT patientId FROM patients WHERE patientId = ?");
            $stmt->execute([$patientId]);
            if (!$stmt->fetch()) {
                throw new Exception("Patient not found.");
            }
            
            $stmt = $pdo->prepare("
                SELECT recordId FROM medical_records 
                WHERE patientId = ? 
                ORDER BY creationDate DESC 
                LIMIT 1
            ");
            $stmt->execute([$patientId]);
            $record = $stmt->fetch();
            
            if (!$record) {
                $doctorStmt = $pdo->prepare("SELECT doctorId FROM doctors WHERE isAvailable = 1 LIMIT 1");
                $doctorStmt->execute();
                $doctor = $doctorStmt->fetch();
                
                if (!$doctor) {
                    $doctorStmt = $pdo->prepare("SELECT doctorId FROM doctors LIMIT 1");
                    $doctorStmt->execute();
                    $doctor = $doctorStmt->fetch();
                }
                
                if (!$doctor) {
                    throw new Exception("No doctor available.");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO medical_records (patientId, doctorId, diagnosis, treatmentNotes, creationDate) 
                    VALUES (?, ?, 'Routine vitals check', 'Vitals recorded by nurse', NOW())
                ");
                $stmt->execute([$patientId, $doctor['doctorId']]);
                $recordId = $pdo->lastInsertId();
            } else {
                $recordId = $record['recordId'];
            }
            
            $stmt = $pdo->prepare("
                SELECT s.staffId 
                FROM staff s 
                JOIN users u ON s.userId = u.userId 
                WHERE u.userId = ? AND u.role = 'nurse'
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $nurse = $stmt->fetch();
            
            if (!$nurse) {
                $stmt = $pdo->prepare("
                    SELECT n.staffId 
                    FROM nurses n 
                    JOIN staff s ON n.staffId = s.staffId 
                    WHERE s.userId = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $nurse = $stmt->fetch();
            }
            
            $recordedBy = $nurse ? $nurse['staffId'] : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO vitals (
                    recordId, height, weight, bodyTemperature, 
                    bloodPressureSystolic, bloodPressureDiastolic, 
                    heartRate, respiratoryRate, oxygenSaturation, 
                    notes, recordedBy, recordedDate
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $recordId, $height, $weight, $bodyTemperature,
                $bpSystolic, $bpDiastolic, $heartRate, $respiratoryRate,
                $oxygenSaturation, $notes, $recordedBy
            ]);
            
            $pdo->commit();
            $_SESSION['success'] = "Patient vitals recorded successfully!";
            logAction($_SESSION['user_id'], 'RECORD_VITALS', "Recorded vitals for patient ID: $patientId");
            
            if (isset($_POST['redirect_url']) && !empty($_POST['redirect_url'])) {
                header("Location: " . $_POST['redirect_url']);
            } else {
                header("Location: medical-records.php");
            }
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to record vitals: " . $e->getMessage();
        }
    }
}

$patient = null;
if ($patientId) {
    $stmt = $pdo->prepare("
        SELECT p.*, CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.email, u.phoneNumber
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE p.patientId = ?
    ");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: medical-records.php");
        exit();
    }
}

$redirectUrl = $_GET['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? 'medical-records.php');
$pageTitle = "Record Patient Vitals - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-heartbeat"></i> Record Patient Vitals</h1>
            <p>Record vital signs for patient examination</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="nurse-alert nurse-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($patient): ?>
        <div class="nurse-card">
            <div class="nurse-card-header">
                <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
            </div>
            <div class="nurse-card-body">
                <div class="nurse-patient-info-grid">
                    <div class="nurse-info-group">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['patientName']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ? date('M j, Y', strtotime($patient['dateOfBirth'])) : 'N/A'; ?></p>
                        <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
                    </div>
                    <div class="nurse-info-group">
                        <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                        <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['knownAllergies'] ?: 'None reported'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="nurse-card">
            <div class="nurse-card-header">
                <h3><i class="fas fa-heartbeat"></i> Record Vital Signs</h3>
            </div>
            <div class="nurse-card-body">
                <form method="POST" id="vitals-form">
                    <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirectUrl); ?>">
                    
                    <h4 style="color: #1e293b; margin-bottom: 20px;">Body Measurements</h4>
                    <div class="nurse-form-row">
                        <div class="nurse-form-group">
                            <label>Height (cm)</label>
                            <input type="number" name="height" step="0.1" class="nurse-form-control" placeholder="Enter height">
                        </div>
                        <div class="nurse-form-group">
                            <label>Weight (kg)</label>
                            <input type="number" name="weight" step="0.1" class="nurse-form-control" placeholder="Enter weight">
                        </div>
                    </div>

                    <h4 style="color: #1e293b; margin: 25px 0 20px;">Cardiovascular</h4>
                    <div class="nurse-form-row">
                        <div class="nurse-form-group">
                            <label>BP Systolic (mmHg)</label>
                            <input type="number" name="bp_systolic" class="nurse-form-control" placeholder="e.g., 120">
                        </div>
                        <div class="nurse-form-group">
                            <label>BP Diastolic (mmHg)</label>
                            <input type="number" name="bp_diastolic" class="nurse-form-control" placeholder="e.g., 80">
                        </div>
                    </div>
                    <div class="nurse-form-row">
                        <div class="nurse-form-group">
                            <label>Heart Rate (bpm)</label>
                            <input type="number" name="heart_rate" class="nurse-form-control" placeholder="e.g., 72">
                        </div>
                    </div>

                    <h4 style="color: #1e293b; margin: 25px 0 20px;">Respiratory & Other</h4>
                    <div class="nurse-form-row">
                        <div class="nurse-form-group">
                            <label>Temperature (°C)</label>
                            <input type="number" name="body_temperature" step="0.1" class="nurse-form-control" placeholder="e.g., 36.6">
                        </div>
                        <div class="nurse-form-group">
                            <label>Respiratory Rate (/min)</label>
                            <input type="number" name="respiratory_rate" class="nurse-form-control" placeholder="e.g., 16">
                        </div>
                    </div>
                    <div class="nurse-form-row">
                        <div class="nurse-form-group">
                            <label>O2 Saturation (%)</label>
                            <input type="number" name="oxygen_saturation" class="nurse-form-control" placeholder="e.g., 98">
                        </div>
                    </div>

                    <h4 style="color: #1e293b; margin: 25px 0 20px;">Additional Notes</h4>
                    <div class="nurse-form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="4" class="nurse-form-control" placeholder="Any additional observations..."></textarea>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <button type="submit" class="nurse-btn nurse-btn-primary">
                            <i class="fas fa-save"></i> Save Vitals
                        </button>
                        <a href="<?php echo htmlspecialchars($redirectUrl); ?>" class="nurse-btn nurse-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="nurse-card">
            <div class="nurse-card-body">
                <div class="nurse-empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>No Patient Selected</h3>
                    <p>Please select a patient to record vitals.</p>
                    <a href="medical-records.php" class="nurse-btn nurse-btn-primary">Go to Medical Records</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('vitals-form')?.addEventListener('submit', function(e) {
    const fields = ['height', 'weight', 'body_temperature', 'bp_systolic', 'bp_diastolic', 'heart_rate', 'respiratory_rate', 'oxygen_saturation'];
    let hasValue = false;
    fields.forEach(field => {
        const input = this.querySelector(`[name="${field}"]`);
        if (input && input.value.trim() !== '') hasValue = true;
    });
    if (!hasValue) {
        e.preventDefault();
        alert('Please enter at least one vital sign measurement.');
        return false;
    }
    if (!confirm('Save these vital signs?')) {
        e.preventDefault();
        return false;
    }
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>