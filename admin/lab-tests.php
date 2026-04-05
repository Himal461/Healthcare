<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Lab Tests - HealthManagement";
include '../includes/header.php';

// Handle lab test actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['order_test'])) {
        $recordId = $_POST['record_id'];
        $patientId = $_POST['patient_id'];
        $testName = sanitizeInput($_POST['test_name']);
        $testType = sanitizeInput($_POST['test_type']);
        $orderedBy = $_POST['ordered_by'];
        $notes = sanitizeInput($_POST['notes']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO lab_tests (recordId, patientId, testName, testType, orderedBy, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'ordered')");
            $stmt->execute([$recordId, $patientId, $testName, $testType, $orderedBy, $notes]);
            $_SESSION['success'] = "Lab test ordered successfully!";
            logAction($_SESSION['user_id'], 'ORDER_LAB_TEST', "Ordered test: $testName");
            header("Location: lab-tests.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to order test: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_result'])) {
        $testId = $_POST['test_id'];
        $results = sanitizeInput($_POST['results']);
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE lab_tests SET results = ?, status = ?, performedDate = NOW() WHERE testId = ?");
            $stmt->execute([$results, $status, $testId]);
            $_SESSION['success'] = "Lab test results updated!";
            logAction($_SESSION['user_id'], 'UPDATE_LAB_TEST', "Updated test ID: $testId");
            header("Location: lab-tests.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to update results: " . $e->getMessage();
        }
    }
}

// Get all lab tests
$tests = $pdo->query("
    SELECT lt.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName,
           mr.diagnosis
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    JOIN doctors d ON lt.orderedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    LEFT JOIN medical_records mr ON lt.recordId = mr.recordId
    ORDER BY lt.orderedDate DESC
")->fetchAll();

// Get medical records for dropdown
$records = $pdo->query("
    SELECT mr.recordId, CONCAT(u.firstName, ' ', u.lastName) as patientName, mr.diagnosis
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    ORDER BY mr.creationDate DESC
")->fetchAll();

// Get patients for dropdown
$patients = $pdo->query("
    SELECT p.patientId, CONCAT(u.firstName, ' ', u.lastName) as name
    FROM patients p
    JOIN users u ON p.userId = u.userId
")->fetchAll();

// Get doctors for dropdown
$doctors = $pdo->query("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Lab Tests</h1>
        <p>Order and manage laboratory tests</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Order New Test Form -->
    <div class="card">
        <div class="card-header">
            <h3>Order New Lab Test</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="record_id">Medical Record (Optional)</label>
                        <select id="record_id" name="record_id">
                            <option value="">Select medical record</option>
                            <?php foreach ($records as $record): ?>
                                <option value="<?php echo $record['recordId']; ?>">
                                    <?php echo $record['patientName']; ?> - <?php echo substr($record['diagnosis'], 0, 50); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patientId']; ?>"><?php echo $patient['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="test_name">Test Name *</label>
                        <input type="text" id="test_name" name="test_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="test_type">Test Type</label>
                        <input type="text" id="test_type" name="test_type" placeholder="e.g., Blood, Urine, Imaging">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ordered_by">Ordering Doctor *</label>
                        <select id="ordered_by" name="ordered_by" required>
                            <option value="">Select doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['doctorId']; ?>">Dr. <?php echo $doctor['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any specific instructions or clinical notes..."></textarea>
                </div>
                
                <button type="submit" name="order_test" class="btn btn-primary">Order Test</button>
            </form>
        </div>
    </div>

    <!-- Lab Tests List -->
    <div class="card">
        <div class="card-header">
            <h3>Lab Tests</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ordered Date</th>
                        <th>Patient</th>
                        <th>Test Name</th>
                        <th>Ordered By</th>
                        <th>Status</th>
                        <th>Results</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test): ?>
                    <tr>
                        <td data-label="Ordered Date"><?php echo date('M j, Y', strtotime($test['orderedDate'])); ?></td>
                        <td data-label="Patient"><?php echo $test['patientName']; ?></td>
                        <td data-label="Test Name"><?php echo $test['testName']; ?></td>
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
                                Pending
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <?php if ($test['status'] === 'ordered' || $test['status'] === 'in-progress'): ?>
                                <button class="btn btn-primary btn-sm" onclick="openModal('resultsModal'); document.getElementById('result_test_id').value = <?php echo $test['testId']; ?>;">Enter Results</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enter Results Modal -->
<div id="resultsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Enter Lab Test Results</h3>
            <span class="close" onclick="closeModal('resultsModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="test_id" id="result_test_id">
                <div class="form-group">
                    <label for="results">Test Results *</label>
                    <textarea id="results" name="results" rows="5" required></textarea>
                </div>
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="completed">Completed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_result" class="btn btn-primary">Save Results</button>
                <button type="button" class="btn" onclick="closeModal('resultsModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>