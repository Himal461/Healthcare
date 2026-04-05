<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Lab Test Results - HealthManagement";
include '../includes/header.php';

$testId = $_GET['test_id'] ?? 0;

if (!$testId) {
    $_SESSION['error'] = "Invalid test ID.";
    header("Location: lab-tests.php");
    exit();
}

// Get test details
$stmt = $pdo->prepare("
    SELECT lt.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName,
           u.phoneNumber as patientPhone,
           u.email as patientEmail
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    JOIN doctors d ON lt.orderedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE lt.testId = ?
");
$stmt->execute([$testId]);
$test = $stmt->fetch();

if (!$test) {
    $_SESSION['error'] = "Test not found.";
    header("Location: lab-tests.php");
    exit();
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Lab Test Results</h1>
        <p><?php echo $test['testName']; ?> - <?php echo htmlspecialchars($test['patientName']); ?></p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Test Information</h3>
        </div>
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-group">
                    <h4>Patient Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($test['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo $test['patientEmail']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $test['patientPhone']; ?></p>
                </div>
                <div class="info-group">
                    <h4>Test Details</h4>
                    <p><strong>Test Name:</strong> <?php echo $test['testName']; ?></p>
                    <p><strong>Test Type:</strong> <?php echo $test['testType'] ?: 'N/A'; ?></p>
                    <p><strong>Ordered By:</strong> Dr. <?php echo $test['orderedByName']; ?></p>
                    <p><strong>Ordered Date:</strong> <?php echo date('M j, Y', strtotime($test['orderedDate'])); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo $test['status']; ?>"><?php echo ucfirst($test['status']); ?></span></p>
                </div>
            </div>
            
            <?php if ($test['results']): ?>
            <div class="info-group" style="margin-top: 20px;">
                <h4>Results</h4>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap;">
                    <?php echo nl2br(htmlspecialchars($test['results'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($test['notes']): ?>
            <div class="info-group" style="margin-top: 20px;">
                <h4>Notes</h4>
                <p><?php echo nl2br(htmlspecialchars($test['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="form-actions" style="margin-top: 20px;">
                <a href="lab-tests.php" class="btn btn-outline">Back to Lab Tests</a>
                <?php if ($test['status'] === 'in-progress'): ?>
                    <button class="btn btn-primary" onclick="openResultModal()">Enter/Edit Results</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Enter Results Modal -->
<div id="resultModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Enter Test Results</h3>
            <span class="close" onclick="closeModal('resultModal')">&times;</span>
        </div>
        <form method="POST" action="lab-tests.php">
            <div class="modal-body">
                <input type="hidden" name="test_id" value="<?php echo $testId; ?>">
                <div class="form-group">
                    <label for="results">Test Results *</label>
                    <textarea id="results" name="results" rows="6" required><?php echo htmlspecialchars($test['results'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="completed" <?php echo $test['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="in-progress" <?php echo $test['status'] === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="enter_results" class="btn btn-primary">Save Results</button>
                <button type="button" class="btn" onclick="closeModal('resultModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResultModal() {
    openModal('resultModal');
}
</script>

<?php include '../includes/footer.php'; ?>