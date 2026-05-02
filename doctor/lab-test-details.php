<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$testId = (int)($_GET['id'] ?? 0);

if (!$testId) {
    $_SESSION['error'] = "Invalid lab test ID.";
    header("Location: lab-tests.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: dashboard.php");
    exit();
}

$doctorId = $doctor['doctorId'];

// Get lab test details
$stmt = $pdo->prepare("
    SELECT lt.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           p.patientId,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies,
           mr.diagnosis,
           mr.treatmentNotes,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN medical_records mr ON lt.recordId = mr.recordId
    LEFT JOIN doctors d_ordered ON lt.orderedBy = d_ordered.doctorId
    LEFT JOIN staff s_ordered ON d_ordered.staffId = s_ordered.staffId
    LEFT JOIN users du ON s_ordered.userId = du.userId
    WHERE lt.testId = ? AND u.role = 'patient'
");
$stmt->execute([$testId]);
$test = $stmt->fetch();

if (!$test) {
    $_SESSION['error'] = "Lab test not found.";
    header("Location: lab-tests.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? $test['status'];
    $results = trim($_POST['results'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE lab_tests 
            SET status = ?, results = ?, notes = ?, performedDate = NOW() 
            WHERE testId = ?
        ");
        $stmt->execute([$status, $results, $notes, $testId]);
        
        $patientStmt = $pdo->prepare("
            SELECT u.userId, u.firstName, u.lastName 
            FROM patients p 
            JOIN users u ON p.userId = u.userId 
            WHERE p.patientId = ?
        ");
        $patientStmt->execute([$test['patientId']]);
        $patient = $patientStmt->fetch();
        
        if ($patient) {
            $statusText = $status == 'completed' ? 'completed' : 'updated to ' . str_replace('-', ' ', $status);
            createNotification(
                $patient['userId'],
                'lab_result',
                'Lab Test ' . ucfirst($statusText),
                "Your lab test '{$test['testName']}' has been " . ($status == 'completed' ? 'completed with results available.' : 'updated to ' . str_replace('-', ' ', $status) . '.')
            );
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Lab test updated successfully!";
        logAction($userId, 'UPDATE_LAB_TEST', "Doctor updated lab test ID: $testId to status: $status");
        
        header("Location: lab-test-details.php?id=$testId");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update lab test: " . $e->getMessage();
    }
}

$pageTitle = "Lab Test Details - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
include '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-flask"></i> Lab Test Details</h1>
            <p>Test #<?php echo $testId; ?> - <?php echo htmlspecialchars($test['testName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="lab-tests.php" class="doctor-btn doctor-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Lab Tests
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="doctor-card">
        <div class="doctor-card-header">
            <div>
                <h3><?php echo htmlspecialchars($test['testName']); ?></h3>
                <span class="doctor-text-muted">Test ID: #<?php echo $testId; ?> | Ordered: <?php echo date('F j, Y', strtotime($test['orderedDate'])); ?></span>
            </div>
            <span class="doctor-test-status-badge status-<?php echo $test['status']; ?>">
                <?php echo ucfirst(str_replace('-', ' ', $test['status'])); ?>
            </span>
        </div>
        <div class="doctor-card-body">
            <div class="doctor-patient-info-grid">
                <div class="doctor-info-group">
                    <h4><i class="fas fa-user"></i> Patient Information</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($test['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($test['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($test['patientPhone']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $test['dateOfBirth'] ? date('M j, Y', strtotime($test['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($test['dateOfBirth']); ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $test['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($test['knownAllergies'] ?: 'None reported'); ?></p>
                </div>
                <div class="doctor-info-group">
                    <h4><i class="fas fa-flask"></i> Test Information</h4>
                    <p><strong>Test Name:</strong> <?php echo htmlspecialchars($test['testName']); ?></p>
                    <p><strong>Test Type:</strong> <?php echo htmlspecialchars($test['testType'] ?: 'N/A'); ?></p>
                    <p><strong>Ordered By:</strong> <?php echo $test['orderedByName'] ? 'Dr. ' . htmlspecialchars($test['orderedByName']) : 'Nurse/Lab'; ?></p>
                    <p><strong>Ordered Date:</strong> <?php echo date('F j, Y g:i A', strtotime($test['orderedDate'])); ?></p>
                    <?php if ($test['performedDate']): ?>
                        <p><strong>Performed Date:</strong> <?php echo date('F j, Y g:i A', strtotime($test['performedDate'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($test['diagnosis']): ?>
                <h4 style="color: #1e293b; margin: 20px 0 15px;"><i class="fas fa-notes-medical" style="color: #2563eb;"></i> Related Diagnosis</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <?php echo nl2br(htmlspecialchars($test['diagnosis'])); ?>
                </div>
            <?php endif; ?>

            <?php if ($test['results']): ?>
                <h4 style="color: #1e293b; margin: 20px 0 15px;"><i class="fas fa-file-alt" style="color: #2563eb;"></i> Test Results</h4>
                <div style="background: #e8f2ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2563eb;">
                    <?php echo nl2br(htmlspecialchars($test['results'])); ?>
                </div>
            <?php endif; ?>

            <!-- Update Form -->
            <h4 style="color: #1e293b; margin: 30px 0 15px;"><i class="fas fa-edit" style="color: #2563eb;"></i> Update Test</h4>
            <form method="POST">
                <div class="doctor-form-group">
                    <label>Status</label>
                    <select name="status" class="doctor-form-control" required>
                        <option value="ordered" <?php echo $test['status'] == 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                        <option value="in-progress" <?php echo $test['status'] == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $test['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $test['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="doctor-form-group">
                    <label>Test Results</label>
                    <textarea name="results" rows="6" class="doctor-form-control" placeholder="Enter test results, values, and observations..."><?php echo htmlspecialchars($test['results'] ?? ''); ?></textarea>
                </div>
                
                <div class="doctor-form-group">
                    <label>Additional Notes</label>
                    <textarea name="notes" rows="3" class="doctor-form-control" placeholder="Any additional notes or comments..."><?php echo htmlspecialchars($test['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="doctor-form-actions" style="display: flex; gap: 15px;">
                    <button type="submit" class="doctor-btn doctor-btn-primary">
                        <i class="fas fa-save"></i> Update Lab Test
                    </button>
                    <a href="lab-tests.php" class="doctor-btn doctor-btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>