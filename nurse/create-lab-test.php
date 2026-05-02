<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Create Lab Test - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$error = null;
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
    $recordId = !empty($_POST['record_id']) ? (int)$_POST['record_id'] : null;
    $testName = trim($_POST['test_name'] ?? '');
    $testType = trim($_POST['test_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    
    if (!$patientId) {
        $errors[] = "Please select a patient.";
    }
    
    if (empty($testName)) {
        $errors[] = "Test name is required.";
    }
    
    if (strlen($testName) < 3) {
        $errors[] = "Test name must be at least 3 characters.";
    }
    
    if (!empty($errors)) {
        $error = implode(' ', $errors);
        $_SESSION['form_data'] = $_POST;
    } else {
        try {
            $pdo->beginTransaction();
            
            if (!$recordId) {
                $recordStmt = $pdo->prepare("
                    SELECT recordId FROM medical_records 
                    WHERE patientId = ? 
                    ORDER BY creationDate DESC 
                    LIMIT 1
                ");
                $recordStmt->execute([$patientId]);
                $record = $recordStmt->fetch();
                
                if (!$record) {
                    if (!$doctorId) {
                        $doctorStmt = $pdo->prepare("SELECT doctorId FROM doctors WHERE isAvailable = 1 LIMIT 1");
                        $doctorStmt->execute();
                        $doctor = $doctorStmt->fetch();
                        $doctorId = $doctor ? $doctor['doctorId'] : null;
                    }
                    
                    if (!$doctorId) {
                        $doctorStmt = $pdo->prepare("SELECT doctorId FROM doctors LIMIT 1");
                        $doctorStmt->execute();
                        $doctor = $doctorStmt->fetch();
                        $doctorId = $doctor ? $doctor['doctorId'] : null;
                    }
                    
                    if (!$doctorId) {
                        throw new Exception("No doctor available to associate with the lab test.");
                    }
                    
                    $recordStmt = $pdo->prepare("
                        INSERT INTO medical_records (patientId, doctorId, diagnosis, treatmentNotes, creationDate) 
                        VALUES (?, ?, 'Lab test ordered', ?, NOW())
                    ");
                    $recordStmt->execute([$patientId, $doctorId, "Test: $testName"]);
                    $recordId = $pdo->lastInsertId();
                } else {
                    $recordId = $record['recordId'];
                    
                    if (!$doctorId) {
                        $docStmt = $pdo->prepare("SELECT doctorId FROM medical_records WHERE recordId = ?");
                        $docStmt->execute([$recordId]);
                        $doc = $docStmt->fetch();
                        $doctorId = $doc ? $doc['doctorId'] : null;
                    }
                }
            }
            
            if (!$doctorId) {
                $doctorStmt = $pdo->prepare("SELECT doctorId FROM doctors WHERE isAvailable = 1 LIMIT 1");
                $doctorStmt->execute();
                $doctor = $doctorStmt->fetch();
                $doctorId = $doctor ? $doctor['doctorId'] : null;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO lab_tests (
                    recordId, patientId, testName, testType, 
                    orderedBy, orderedDate, status, notes, results
                ) VALUES (?, ?, ?, ?, ?, NOW(), 'ordered', ?, ?)
            ");
            $stmt->execute([
                $recordId,
                $patientId,
                $testName,
                $testType ?: null,
                $doctorId,
                $notes ?: $description,
                null
            ]);
            
            $testId = $pdo->lastInsertId();
            
            $patientStmt = $pdo->prepare("
                SELECT u.userId, u.firstName, u.lastName 
                FROM patients p 
                JOIN users u ON p.userId = u.userId 
                WHERE p.patientId = ?
            ");
            $patientStmt->execute([$patientId]);
            $patient = $patientStmt->fetch();
            
            if ($patient) {
                createNotification(
                    $patient['userId'],
                    'lab_result',
                    'Lab Test Ordered',
                    "A new lab test '{$testName}' has been ordered for you."
                );
            }
            
            if ($doctorId) {
                $doctorNotifyStmt = $pdo->prepare("
                    SELECT s.userId FROM doctors d 
                    JOIN staff s ON d.staffId = s.staffId 
                    WHERE d.doctorId = ?
                ");
                $doctorNotifyStmt->execute([$doctorId]);
                $doctorUser = $doctorNotifyStmt->fetch();
                
                if ($doctorUser) {
                    createNotification(
                        $doctorUser['userId'],
                        'lab_result',
                        'Lab Test Ordered',
                        "A new lab test '{$testName}' has been ordered for patient {$patient['firstName']} {$patient['lastName']}."
                    );
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "Lab test '{$testName}' created successfully!";
            logAction($userId, 'CREATE_LAB_TEST', "Created lab test ID: {$testId} for patient ID: {$patientId}");
            
            header("Location: lab-tests.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to create lab test: " . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            error_log("Create lab test error: " . $e->getMessage());
        }
    }
}

// Get patients for dropdown
$patients = $pdo->query("
    SELECT p.patientId, CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email, u.phoneNumber, p.dateOfBirth
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.role = 'patient'
    ORDER BY u.firstName, u.lastName
")->fetchAll();

// Get doctors for dropdown
$doctors = $pdo->query("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as doctorName, d.specialization
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.isAvailable = 1
    ORDER BY u.firstName, u.lastName
")->fetchAll();

// Common lab test types
$commonTestTypes = [
    'Blood Test', 'Urine Test', 'Imaging', 'X-Ray', 'MRI', 'CT Scan', 
    'Ultrasound', 'Biopsy', 'Culture', 'PCR Test', 'Antibody Test'
];

$commonTestNames = [
    'Complete Blood Count (CBC)', 'Basic Metabolic Panel (BMP)', 'Lipid Profile',
    'Liver Function Test', 'Kidney Function Test', 'Thyroid Stimulating Hormone (TSH)',
    'Hemoglobin A1c', 'Urinalysis', 'Chest X-Ray', 'COVID-19 PCR Test'
];
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-flask"></i> Create New Lab Test</h1>
            <p>Order a laboratory test for a patient</p>
        </div>
        <div class="header-actions">
            <a href="lab-tests.php" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Lab Tests
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="nurse-alert nurse-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-flask"></i> Lab Test Details</h3>
        </div>
        <div class="nurse-card-body">
            <form method="POST" id="lab-test-form">
                <div class="nurse-form-group">
                    <label>Patient <span class="required">*</span></label>
                    <select name="patient_id" class="nurse-form-control" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['patientId']; ?>" <?php echo ($formData['patient_id'] ?? '') == $patient['patientId'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['patientName']); ?> 
                                (DOB: <?php echo $patient['dateOfBirth'] ? date('M j, Y', strtotime($patient['dateOfBirth'])) : 'N/A'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="nurse-form-group">
                    <label>Ordering Doctor</label>
                    <select name="doctor_id" class="nurse-form-control">
                        <option value="">Select Doctor (Optional)</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['doctorId']; ?>" <?php echo ($formData['doctor_id'] ?? '') == $doctor['doctorId'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['doctorName']); ?> 
                                (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="nurse-form-row">
                    <div class="nurse-form-group">
                        <label>Test Name <span class="required">*</span></label>
                        <input type="text" name="test_name" class="nurse-form-control" 
                               list="test-name-suggestions"
                               value="<?php echo htmlspecialchars($formData['test_name'] ?? ''); ?>"
                               placeholder="e.g., Complete Blood Count (CBC)" required>
                        <datalist id="test-name-suggestions">
                            <?php foreach ($commonTestNames as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="nurse-form-group">
                        <label>Test Type/Category</label>
                        <input type="text" name="test_type" class="nurse-form-control" 
                               list="test-type-suggestions"
                               value="<?php echo htmlspecialchars($formData['test_type'] ?? ''); ?>"
                               placeholder="e.g., Blood, Urine, Imaging">
                        <datalist id="test-type-suggestions">
                            <?php foreach ($commonTestTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                
                <div class="nurse-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="nurse-form-control" 
                              placeholder="Brief description of the test..."><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="nurse-form-group">
                    <label>Clinical Notes / Instructions</label>
                    <textarea name="notes" rows="3" class="nurse-form-control" 
                              placeholder="Any specific instructions or clinical notes..."><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                </div>

                <div class="nurse-summary-box">
                    <p><i class="fas fa-info-circle"></i> This test will be created with <strong>Ordered</strong> status.</p>
                    <p>Once the sample is collected, you can update the status to <strong>In Progress</strong>.</p>
                    <p>After results are available, you can mark it as <strong>Completed</strong>.</p>
                </div>

                <div class="nurse-form-group" style="margin-top: 30px; display: flex; gap: 15px;">
                    <button type="submit" class="nurse-btn nurse-btn-success">
                        <i class="fas fa-save"></i> Create Lab Test
                    </button>
                    <a href="lab-tests.php" class="nurse-btn nurse-btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('lab-test-form')?.addEventListener('submit', function(e) {
    const patientId = document.querySelector('[name="patient_id"]').value;
    const testName = document.querySelector('[name="test_name"]').value.trim();
    
    if (!patientId) {
        e.preventDefault();
        alert('Please select a patient.');
        return false;
    }
    
    if (!testName) {
        e.preventDefault();
        alert('Please enter a test name.');
        return false;
    }
    
    if (testName.length < 3) {
        e.preventDefault();
        alert('Test name must be at least 3 characters.');
        return false;
    }
    
    if (!confirm('Create this lab test?')) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>