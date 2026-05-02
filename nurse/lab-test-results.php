<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Lab Test Results - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$testId = $_GET['test_id'] ?? 0;
if (!$testId) { 
    $_SESSION['error'] = "Invalid test ID."; 
    header("Location: lab-tests.php"); 
    exit(); 
}

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

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-flask"></i> Lab Test Results</h1>
            <p><?php echo htmlspecialchars($test['testName']); ?> - <?php echo htmlspecialchars($test['patientName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="lab-tests.php" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Lab Tests
            </a>
            <button onclick="window.print()" class="nurse-btn nurse-btn-primary">
                <i class="fas fa-print"></i> Print Results
            </button>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-info-circle"></i> Test Information</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-patient-info-grid">
                <div class="nurse-info-group">
                    <h4><i class="fas fa-user"></i> Patient Information</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($test['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($test['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($test['patientPhone']); ?></p>
                </div>
                <div class="nurse-info-group">
                    <h4><i class="fas fa-flask"></i> Test Details</h4>
                    <p><strong>Test Name:</strong> <?php echo htmlspecialchars($test['testName']); ?></p>
                    <p><strong>Test Type:</strong> <?php echo htmlspecialchars($test['testType'] ?: 'N/A'); ?></p>
                    <p><strong>Ordered By:</strong> Dr. <?php echo htmlspecialchars($test['orderedByName']); ?></p>
                    <p><strong>Ordered Date:</strong> <?php echo date('M j, Y', strtotime($test['orderedDate'])); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="nurse-test-status-badge status-<?php echo $test['status']; ?>">
                            <?php echo ucfirst(str_replace('-', ' ', $test['status'])); ?>
                        </span>
                    </p>
                    <?php if ($test['performedDate']): ?>
                        <p><strong>Performed Date:</strong> <?php echo date('M j, Y g:i A', strtotime($test['performedDate'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($test['results']): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 15px;">
                        <i class="fas fa-file-alt" style="color: #6f42c1;"></i> Test Results
                    </h4>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border-left: 4px solid #6f42c1; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($test['results'])); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="nurse-alert nurse-alert-info" style="margin-top: 20px;">
                    <i class="fas fa-info-circle"></i> No results have been entered for this test yet.
                </div>
            <?php endif; ?>
            
            <?php if ($test['notes']): ?>
                <div style="margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 15px;">
                        <i class="fas fa-clipboard" style="color: #6f42c1;"></i> Clinical Notes
                    </h4>
                    <div style="background: #fefce8; padding: 20px; border-radius: 12px; border-left: 4px solid #f59e0b;">
                        <?php echo nl2br(htmlspecialchars($test['notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <a href="lab-tests.php" class="nurse-btn nurse-btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <?php if ($test['status'] == 'in-progress'): ?>
                    <button class="nurse-btn nurse-btn-primary" onclick="openResultModal(<?php echo $test['testId']; ?>)">
                        <i class="fas fa-edit"></i> Enter Results
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Enter Results Modal -->
<div id="resultModal" class="nurse-modal">
    <div class="nurse-modal-content">
        <div class="nurse-modal-header">
            <h3><i class="fas fa-flask"></i> Enter Test Results</h3>
            <span class="nurse-modal-close" onclick="closeModal('resultModal')">&times;</span>
        </div>
        <form method="POST" action="lab-tests.php">
            <div class="nurse-modal-body">
                <input type="hidden" name="test_id" id="result_test_id">
                
                <div class="nurse-form-group">
                    <label>Test Results <span class="required">*</span></label>
                    <textarea name="results" rows="6" class="nurse-form-control" required 
                              placeholder="Enter detailed test results, values, observations..."></textarea>
                </div>
                
                <div class="nurse-form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status" class="nurse-form-control" required>
                        <option value="completed">Completed</option>
                        <option value="in-progress">Keep In Progress</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="nurse-modal-footer">
                <button type="submit" name="enter_results" class="nurse-btn nurse-btn-primary">
                    <i class="fas fa-save"></i> Save Results
                </button>
                <button type="button" class="nurse-btn nurse-btn-outline" onclick="closeModal('resultModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResultModal(testId) {
    document.getElementById('result_test_id').value = testId;
    openModal('resultModal');
}

function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(e) { if (e.target.classList.contains('nurse-modal')) e.target.style.display = 'none'; }
</script>

<?php include '../includes/footer.php'; ?>