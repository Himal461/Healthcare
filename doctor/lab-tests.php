<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Lab Tests - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
include '../includes/header.php';

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

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Get all lab tests for this doctor's patients OR directly ordered by this doctor
$query = "
    SELECT DISTINCT lt.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber as patientPhone,
           u.email as patientEmail,
           p.dateOfBirth,
           p.bloodType,
           mr.diagnosis,
           CONCAT(du.firstName, ' ', du.lastName) as orderedByName
    FROM lab_tests lt
    JOIN patients p ON lt.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN medical_records mr ON lt.recordId = mr.recordId
    LEFT JOIN doctors d_ordered ON lt.orderedBy = d_ordered.doctorId
    LEFT JOIN staff s_ordered ON d_ordered.staffId = s_ordered.staffId
    LEFT JOIN users du ON s_ordered.userId = du.userId
    WHERE u.role = 'patient'
    AND (
        lt.orderedBy = ? 
        OR lt.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
        OR lt.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?)
    )
";
$params = [$doctorId, $doctorId, $doctorId];

if ($statusFilter) {
    $query .= " AND lt.status = ?";
    $params[] = $statusFilter;
}

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR lt.testName LIKE ? OR u.email LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$query .= " ORDER BY lt.orderedDate DESC, lt.testId DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$labTests = $stmt->fetchAll();

// Statistics
$totalTests = $pdo->prepare("
    SELECT COUNT(DISTINCT lt.testId) FROM lab_tests lt
    WHERE lt.orderedBy = ? 
    OR lt.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
    OR lt.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?)
");
$totalTests->execute([$doctorId, $doctorId, $doctorId]);
$totalTestsCount = $totalTests->fetchColumn();

$orderedTests = $pdo->prepare("
    SELECT COUNT(DISTINCT lt.testId) FROM lab_tests lt
    WHERE (lt.orderedBy = ? 
        OR lt.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
        OR lt.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?))
    AND lt.status = 'ordered'
");
$orderedTests->execute([$doctorId, $doctorId, $doctorId]);
$orderedTestsCount = $orderedTests->fetchColumn();

$inProgressTests = $pdo->prepare("
    SELECT COUNT(DISTINCT lt.testId) FROM lab_tests lt
    WHERE (lt.orderedBy = ? 
        OR lt.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
        OR lt.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?))
    AND lt.status = 'in-progress'
");
$inProgressTests->execute([$doctorId, $doctorId, $doctorId]);
$inProgressTestsCount = $inProgressTests->fetchColumn();

$completedTests = $pdo->prepare("
    SELECT COUNT(DISTINCT lt.testId) FROM lab_tests lt
    WHERE (lt.orderedBy = ? 
        OR lt.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
        OR lt.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?))
    AND lt.status = 'completed'
");
$completedTests->execute([$doctorId, $doctorId, $doctorId]);
$completedTestsCount = $completedTests->fetchColumn();

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-flask"></i> Laboratory Tests</h1>
            <p>View and manage lab tests for your patients</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="doctor-lab-stats-grid">
        <div class="doctor-lab-stat-card">
            <h3><?php echo $totalTestsCount; ?></h3>
            <p>Total Tests</p>
        </div>
        <div class="doctor-lab-stat-card">
            <h3><?php echo $orderedTestsCount; ?></h3>
            <p>Ordered</p>
        </div>
        <div class="doctor-lab-stat-card">
            <h3><?php echo $inProgressTestsCount; ?></h3>
            <p>In Progress</p>
        </div>
        <div class="doctor-lab-stat-card">
            <h3><?php echo $completedTestsCount; ?></h3>
            <p>Completed</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-filter"></i> Filter Tests</h3>
        </div>
        <div class="doctor-card-body">
            <form method="GET" class="doctor-filter-form">
                <div class="doctor-filter-row">
                    <div class="doctor-filter-group">
                        <select name="status" class="doctor-form-control">
                            <option value="">All Status</option>
                            <option value="ordered" <?php echo $statusFilter == 'ordered' ? 'selected' : ''; ?>>Ordered</option>
                            <option value="in-progress" <?php echo $statusFilter == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="doctor-filter-group">
                        <input type="text" name="search" placeholder="Search patient or test name..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="doctor-form-control">
                    </div>
                    <div class="doctor-filter-actions">
                        <button type="submit" class="doctor-btn doctor-btn-primary">Apply Filters</button>
                        <a href="lab-tests.php" class="doctor-btn doctor-btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lab Tests List -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-list"></i> Lab Tests (<?php echo count($labTests); ?>)</h3>
        </div>
        <div class="doctor-table-responsive">
            <?php if (empty($labTests)): ?>
                <div class="doctor-empty-state">
                    <i class="fas fa-flask"></i>
                    <p>No lab tests found for your patients.</p>
                </div>
            <?php else: ?>
                <table class="doctor-data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ordered Date</th>
                            <th>Patient</th>
                            <th>Test Name</th>
                            <th>Test Type</th>
                            <th>Ordered By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($labTests as $test): ?>
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
                                        <span class="doctor-text-muted">Nurse/Lab</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <span class="doctor-test-status-badge status-<?php echo $test['status']; ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $test['status'])); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="doctor-action-buttons">
                                        <a href="lab-test-details.php?id=<?php echo $test['testId']; ?>" class="doctor-btn doctor-btn-primary doctor-btn-sm">
                                            <i class="fas fa-eye"></i> View/Update
                                        </a>
                                        <?php if ($test['status'] == 'completed' && $test['results']): ?>
                                            <button class="doctor-btn doctor-btn-info doctor-btn-sm" onclick="viewTestDetails(<?php echo $test['testId']; ?>)">
                                                <i class="fas fa-file-alt"></i> Results
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>