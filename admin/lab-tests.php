<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Lab Tests - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_test'])) {
    $recordId = $_POST['record_id'];
    $patientId = $_POST['patient_id'];
    $testName = sanitizeInput($_POST['test_name']);
    $testType = sanitizeInput($_POST['test_type']);
    $orderedBy = $_POST['ordered_by'];
    $notes = sanitizeInput($_POST['notes']);
    
    $stmt = $pdo->prepare("INSERT INTO lab_tests (recordId, patientId, testName, testType, orderedBy, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'ordered')");
    $stmt->execute([$recordId, $patientId, $testName, $testType, $orderedBy, $notes]);
    $_SESSION['success'] = "Lab test ordered!";
    logAction($_SESSION['user_id'], 'ORDER_LAB_TEST', "Ordered test: $testName");
    header("Location: lab-tests.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_result'])) {
    $testId = $_POST['test_id'];
    $results = sanitizeInput($_POST['results']);
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE lab_tests SET results = ?, status = ?, performedDate = NOW() WHERE testId = ?");
    $stmt->execute([$results, $status, $testId]);
    $_SESSION['success'] = "Results updated!";
    logAction($_SESSION['user_id'], 'UPDATE_LAB_TEST', "Updated test $testId");
    header("Location: lab-tests.php");
    exit();
}

$tests = $pdo->query("
    SELECT lt.*, CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName, mr.diagnosis
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    JOIN doctors d ON lt.orderedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    LEFT JOIN medical_records mr ON lt.recordId = mr.recordId
    WHERE u.role = 'patient'
    ORDER BY lt.orderedDate DESC
")->fetchAll();

$records = $pdo->query("SELECT mr.recordId, CONCAT(u.firstName, ' ', u.lastName) as patientName, mr.diagnosis FROM medical_records mr JOIN patients p ON mr.patientId = p.patientId JOIN users u ON p.userId = u.userId WHERE u.role = 'patient' ORDER BY mr.creationDate DESC")->fetchAll();
$patients = $pdo->query("SELECT p.patientId, CONCAT(u.firstName, ' ', u.lastName) as name FROM patients p JOIN users u ON p.userId = u.userId WHERE u.role = 'patient'")->fetchAll();
$doctors = $pdo->query("SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name FROM doctors d JOIN staff s ON d.staffId = s.staffId JOIN users u ON s.userId = u.userId")->fetchAll();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-flask"></i> Lab Tests</h1>
            <p>Order and manage laboratory tests</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-plus-circle"></i> Order New Test</h3>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Medical Record</label>
                        <select name="record_id" class="admin-form-control">
                            <option value="">Select</option>
                            <?php foreach ($records as $r): ?>
                                <option value="<?php echo $r['recordId']; ?>"><?php echo htmlspecialchars($r['patientName']); ?> - <?php echo substr($r['diagnosis'], 0, 30); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label>Patient <span class="required">*</span></label>
                        <select name="patient_id" class="admin-form-control" required>
                            <option value="">Select</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['patientId']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Test Name <span class="required">*</span></label>
                        <input type="text" name="test_name" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label>Test Type</label>
                        <input type="text" name="test_type" class="admin-form-control" placeholder="Blood, Urine, etc.">
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>Ordering Doctor <span class="required">*</span></label>
                    <select name="ordered_by" class="admin-form-control" required>
                        <option value="">Select</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?php echo $d['doctorId']; ?>">Dr. <?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="admin-form-control"></textarea>
                </div>
                <button type="submit" name="order_test" class="admin-btn admin-btn-primary">Order Test</button>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Lab Tests</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Ordered By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $t): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($t['orderedDate'])); ?></td>
                            <td data-label="Patient"><?php echo htmlspecialchars($t['patientName']); ?></td>
                            <td data-label="Test"><?php echo htmlspecialchars($t['testName']); ?></td>
                            <td data-label="Ordered By">Dr. <?php echo htmlspecialchars($t['orderedByName']); ?></td>
                            <td data-label="Status">
                                <span class="admin-status-badge admin-status-<?php echo $t['status']; ?>">
                                    <?php echo ucfirst($t['status']); ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <?php if ($t['status'] == 'ordered' || $t['status'] == 'in-progress'): ?>
                                    <button class="admin-btn admin-btn-primary admin-btn-sm" onclick="openModal('resultsModal'); document.getElementById('result_test_id').value=<?php echo $t['testId']; ?>;">Enter Results</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Results Modal -->
<div id="resultsModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Enter Results</h3>
            <span class="admin-modal-close" onclick="closeModal('resultsModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="admin-modal-body">
                <input type="hidden" name="test_id" id="result_test_id">
                <div class="admin-form-group">
                    <label>Results <span class="required">*</span></label>
                    <textarea name="results" rows="5" class="admin-form-control" required></textarea>
                </div>
                <div class="admin-form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status" class="admin-form-control" required>
                        <option value="completed">Completed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" name="update_result" class="admin-btn admin-btn-primary">Save</button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('resultsModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>