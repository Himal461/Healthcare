<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Lab Tests - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Handle sample collection
if (isset($_GET['collect'])) {
    $testId = (int)$_GET['collect'];
    $stmt = $pdo->prepare("UPDATE lab_tests SET status = 'in-progress', performedDate = NOW() WHERE testId = ? AND status = 'ordered'");
    $stmt->execute([$testId]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Sample collected successfully! Test is now in progress.";
        logAction($userId, 'COLLECT_SAMPLE', "Collected sample for test ID: $testId");
    } else {
        $_SESSION['error'] = "Failed to collect sample. Test may already be processed.";
    }
    header("Location: lab-tests.php");
    exit();
}

// Handle result entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enter_results'])) {
    $testId = (int)$_POST['test_id'];
    $results = sanitizeInput($_POST['results']);
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE lab_tests SET results = ?, status = ?, performedDate = NOW() WHERE testId = ?");
    $stmt->execute([$results, $status, $testId]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Test results saved successfully!";
        logAction($userId, 'ENTER_LAB_RESULTS', "Entered results for test ID: $testId");
    }
    header("Location: lab-tests.php");
    exit();
}

// Handle test cancellation
if (isset($_GET['cancel'])) {
    $testId = (int)$_GET['cancel'];
    $stmt = $pdo->prepare("UPDATE lab_tests SET status = 'cancelled' WHERE testId = ? AND status IN ('ordered', 'in-progress')");
    $stmt->execute([$testId]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Lab test cancelled successfully.";
        logAction($userId, 'CANCEL_LAB_TEST', "Cancelled test ID: $testId");
    }
    header("Location: lab-tests.php");
    exit();
}

// Build query with filters
$query = "
    SELECT lt.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber as patientPhone,
           u.email as patientEmail,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName,
           d.specialization as doctorSpecialization,
           mr.diagnosis
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN doctors d ON lt.orderedBy = d.doctorId
    LEFT JOIN staff s ON d.staffId = s.staffId
    LEFT JOIN users du ON s.userId = du.userId
    LEFT JOIN medical_records mr ON lt.recordId = mr.recordId
    WHERE 1=1
";
$params = [];

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR lt.testName LIKE ? OR u.email LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($statusFilter) {
    $query .= " AND lt.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY lt.orderedDate DESC, lt.testId DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll();

// Get statistics
$totalTests = $pdo->query("SELECT COUNT(*) FROM lab_tests")->fetchColumn();
$orderedTests = $pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status = 'ordered'")->fetchColumn();
$inProgressTests = $pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status = 'in-progress'")->fetchColumn();
$completedTests = $pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status = 'completed'")->fetchColumn();
$cancelledTests = $pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status = 'cancelled'")->fetchColumn();

// Display session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-flask"></i> Lab Tests Management</h1>
            <p>View, manage, and create laboratory tests</p>
        </div>
        <div class="header-actions">
            <a href="create-lab-test.php" class="nurse-btn nurse-btn-success">
                <i class="fas fa-plus-circle"></i> Create New Lab Test
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="nurse-alert nurse-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="nurse-alert nurse-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="nurse-lab-stats-grid">
        <div class="nurse-lab-stat-card">
            <h3><?php echo $totalTests; ?></h3>
            <p>Total Tests</p>
        </div>
        <div class="nurse-lab-stat-card">
            <h3><?php echo $orderedTests; ?></h3>
            <p>Ordered</p>
        </div>
        <div class="nurse-lab-stat-card">
            <h3><?php echo $inProgressTests; ?></h3>
            <p>In Progress</p>
        </div>
        <div class="nurse-lab-stat-card">
            <h3><?php echo $completedTests; ?></h3>
            <p>Completed</p>
        </div>
        <div class="nurse-lab-stat-card">
            <h3><?php echo $cancelledTests; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-filter"></i> Filter Tests</h3>
        </div>
        <div class="nurse-card-body">
            <form method="GET" class="nurse-filter-form">
                <div class="nurse-filter-row">
                    <div class="nurse-filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="ordered" <?php echo $statusFilter == 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                            <option value="in-progress" <?php echo $statusFilter == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="nurse-filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Patient name, test name, or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    
                    <div class="nurse-filter-actions">
                        <button type="submit" class="nurse-btn nurse-btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="lab-tests.php" class="nurse-btn nurse-btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tests List -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-list"></i> Lab Tests (<?php echo count($tests); ?> found)</h3>
        </div>
        <div class="nurse-table-responsive">
            <table class="nurse-data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ordered Date</th>
                        <th>Patient</th>
                        <th>Test Name</th>
                        <th>Test Type</th>
                        <th>Ordered By</th>
                        <th>Status</th>
                        <th>Results</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tests)): ?>
                        <tr><td colspan="9" class="nurse-empty-message">No lab tests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tests as $test): ?>
                            <tr class="status-<?php echo $test['status']; ?>">
                                <td data-label="ID">#<?php echo $test['testId']; ?></td>
                                <td data-label="Ordered Date">
                                    <?php echo date('M j, Y', strtotime($test['orderedDate'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($test['orderedDate'])); ?></small>
                                </td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($test['patientName']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($test['patientPhone']); ?></small>
                                </td>
                                <td data-label="Test Name"><strong><?php echo htmlspecialchars($test['testName']); ?></strong></td>
                                <td data-label="Test Type"><?php echo htmlspecialchars($test['testType'] ?: '-'); ?></td>
                                <td data-label="Ordered By">
                                    <?php if ($test['orderedByName']): ?>
                                        Dr. <?php echo htmlspecialchars($test['orderedByName']); ?>
                                    <?php else: ?>
                                        <span class="nurse-text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <span class="nurse-test-status-badge status-<?php echo $test['status']; ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $test['status'])); ?>
                                    </span>
                                </td>
                                <td data-label="Results">
                                    <?php if ($test['results']): ?>
                                        <span title="<?php echo htmlspecialchars($test['results']); ?>">
                                            <?php echo htmlspecialchars(substr($test['results'], 0, 30)); ?>...
                                        </span>
                                    <?php else: ?>
                                        <em class="nurse-text-muted">Pending</em>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="nurse-action-buttons">
                                        <?php if ($test['status'] == 'ordered'): ?>
                                            <a href="?collect=<?php echo $test['testId']; ?>" class="nurse-btn nurse-btn-success nurse-btn-sm" onclick="return confirm('Mark sample as collected?')">
                                                <i class="fas fa-syringe"></i> Collect
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($test['status'] == 'in-progress'): ?>
                                            <button class="nurse-btn nurse-btn-primary nurse-btn-sm" onclick="openResultModal(<?php echo $test['testId']; ?>)">
                                                <i class="fas fa-edit"></i> Enter Results
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($test['results']): ?>
                                            <a href="lab-test-results.php?test_id=<?php echo $test['testId']; ?>" class="nurse-btn nurse-btn-info nurse-btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($test['status'] == 'ordered' || $test['status'] == 'in-progress'): ?>
                                            <a href="?cancel=<?php echo $test['testId']; ?>" class="nurse-btn nurse-btn-warning nurse-btn-sm" onclick="return confirm('Cancel this lab test?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
        <form method="POST">
            <div class="nurse-modal-body">
                <input type="hidden" name="test_id" id="result_test_id">
                
                <div class="nurse-form-group">
                    <label>Test Results <span class="required">*</span></label>
                    <textarea name="results" rows="6" class="nurse-form-control" required placeholder="Enter detailed test results..."></textarea>
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