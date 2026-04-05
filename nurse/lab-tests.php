<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Lab Tests - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Handle sample collection
if (isset($_GET['collect'])) {
    $testId = $_GET['collect'];
    $stmt = $pdo->prepare("UPDATE lab_tests SET status = 'in-progress' WHERE testId = ?");
    $stmt->execute([$testId]);
    $_SESSION['success'] = "Sample collected successfully!";
    header("Location: lab-tests.php");
    exit();
}

// Handle result entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enter_results'])) {
    $testId = $_POST['test_id'];
    $results = sanitizeInput($_POST['results']);
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE lab_tests SET results = ?, status = ?, performedDate = NOW() WHERE testId = ?");
    $stmt->execute([$results, $status, $testId]);
    $_SESSION['success'] = "Test results saved successfully!";
    header("Location: lab-tests.php");
    exit();
}

// Get lab tests
$query = "
    SELECT lt.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName,
           u.phoneNumber as patientPhone
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    JOIN doctors d ON lt.orderedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE 1=1
";

$params = [];

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR lt.testName LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($statusFilter) {
    $query .= " AND lt.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY lt.orderedDate DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Lab Tests Management</h1>
        <p>View and manage laboratory tests</p>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>Filter Tests</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="ordered" <?php echo $statusFilter == 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                            <option value="in-progress" <?php echo $statusFilter == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Patient name or test name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="lab-tests.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tests List -->
    <div class="card">
        <div class="card-header">
            <h3>Lab Tests (<?php echo count($tests); ?> found)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
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
                        <tr>
                            <td colspan="8" style="text-align: center;">No lab tests found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tests as $test): ?>
                            <tr>
                                <td data-label="Ordered Date"><?php echo date('M j, Y', strtotime($test['orderedDate'])); ?></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($test['patientName']); ?></strong><br>
                                    <small><?php echo $test['patientPhone']; ?></small>
                                </td>
                                <td data-label="Test Name"><?php echo $test['testName']; ?></td>
                                <td data-label="Test Type"><?php echo $test['testType'] ?: '-'; ?></td>
                                <td data-label="Ordered By">Dr. <?php echo $test['orderedByName']; ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $test['status']; ?>">
                                        <?php echo ucfirst($test['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Results">
                                    <?php if ($test['results']): ?>
                                        <span title="<?php echo htmlspecialchars($test['results']); ?>">
                                            <?php echo substr($test['results'], 0, 30) . (strlen($test['results']) > 30 ? '...' : ''); ?>
                                        </span>
                                    <?php else: ?>
                                        <em>Pending</em>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($test['status'] == 'ordered'): ?>
                                        <a href="?collect=<?php echo $test['testId']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Collect sample for this test?')">
                                            <i class="fas fa-syringe"></i> Collect Sample
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($test['status'] == 'in-progress'): ?>
                                        <button class="btn btn-primary btn-sm" onclick="openModal('resultModal'); document.getElementById('result_test_id').value = <?php echo $test['testId']; ?>;">
                                            <i class="fas fa-edit"></i> Enter Results
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($test['results']): ?>
                                        <button class="btn btn-info btn-sm" onclick="viewResults(<?php echo $test['testId']; ?>)">
                                            <i class="fas fa-eye"></i> View Results
                                        </button>
                                    <?php endif; ?>
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
<div id="resultModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Enter Test Results</h3>
            <span class="close" onclick="closeModal('resultModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="test_id" id="result_test_id">
                <div class="form-group">
                    <label for="results">Test Results *</label>
                    <textarea id="results" name="results" rows="6" required placeholder="Enter detailed test results..."></textarea>
                </div>
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="completed">Completed</option>
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
function viewResults(testId) {
    window.location.href = `lab-test-results.php?test_id=${testId}`;
}
</script>

<?php include '../includes/footer.php'; ?>