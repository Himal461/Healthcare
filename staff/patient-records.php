<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Patient Records - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$patientId = $_GET['patient_id'] ?? 0;
$searchTerm = $_GET['search'] ?? '';
$patient = null;
$appointments = [];
$bills = [];
$medicalRecords = [];
$prescriptions = [];
$vitals = [];
$searchResults = [];

// Handle patient search - FIXED: Only search users with role = 'patient'
if ($searchTerm) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber, p.dateOfBirth, p.bloodType
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE u.role = 'patient' 
        AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)
        ORDER BY u.firstName, u.lastName
        LIMIT 50
    ");
    $searchLike = "%$searchTerm%";
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike]);
    $searchResults = $stmt->fetchAll();
}

// FIXED: Get recent patients - only actual patients, no GROUP BY issue
$recentPatients = $pdo->query("
    SELECT DISTINCT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber,
           (SELECT MAX(dateTime) FROM appointments WHERE patientId = p.patientId) as last_visit
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.role = 'patient'
    ORDER BY last_visit DESC, u.firstName ASC
    LIMIT 10
")->fetchAll();

// Get patient details if patient selected - FIXED: Verify patient role
if ($patientId) {
    $stmt = $pdo->prepare("
        SELECT u.userId, u.firstName, u.lastName, u.email, u.phoneNumber, u.dateCreated,
               p.patientId, p.dateOfBirth, p.bloodType, p.address, p.knownAllergies,
               p.emergencyContactName, p.emergencyContactPhone, p.insuranceProvider, p.insuranceNumber
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE p.patientId = ? AND u.role = 'patient'
    ");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    
    if ($patient) {
        // Get patient appointments
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   d.specialization
            FROM appointments a
            JOIN doctors d ON a.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE a.patientId = ?
            ORDER BY a.dateTime DESC
            LIMIT 20
        ");
        $stmt->execute([$patientId]);
        $appointments = $stmt->fetchAll();
        
        // Get patient bills
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE patientId = ? ORDER BY generatedAt DESC");
        $stmt->execute([$patientId]);
        $bills = $stmt->fetchAll();
        
        // Get patient medical records
        $stmt = $pdo->prepare("
            SELECT mr.*, 
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   d.specialization
            FROM medical_records mr
            JOIN doctors d ON mr.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE mr.patientId = ?
            ORDER BY mr.creationDate DESC
            LIMIT 10
        ");
        $stmt->execute([$patientId]);
        $medicalRecords = $stmt->fetchAll();
        
        // Get patient prescriptions
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   mr.diagnosis
            FROM prescriptions p
            JOIN medical_records mr ON p.recordId = mr.recordId
            JOIN doctors d ON p.prescribedBy = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE mr.patientId = ?
            ORDER BY p.createdAt DESC
        ");
        $stmt->execute([$patientId]);
        $prescriptions = $stmt->fetchAll();
        
        // Get patient vitals
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   CONCAT(du.firstName, ' ', du.lastName) as recordedByName
            FROM vitals v
            JOIN medical_records mr ON v.recordId = mr.recordId
            LEFT JOIN staff s ON v.recordedBy = s.staffId
            LEFT JOIN users du ON s.userId = du.userId
            WHERE mr.patientId = ?
            ORDER BY v.recordedDate DESC
            LIMIT 10
        ");
        $stmt->execute([$patientId]);
        $vitals = $stmt->fetchAll();
    }
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-folder-open"></i> Patient Records</h1>
            <p>Search and view patient information</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="staff-alert staff-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="staff-alert staff-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Patient Search Section -->
    <div class="staff-card">
        <div class="staff-card-header">
            <h3><i class="fas fa-search"></i> Find Patient</h3>
        </div>
        <div class="staff-card-body">
            <form method="GET" class="staff-search-group">
                <input type="text" name="search" placeholder="Search by name, email, or phone..." 
                       value="<?php echo htmlspecialchars($searchTerm); ?>" class="staff-search-input">
                <button type="submit" class="staff-btn staff-btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($searchTerm || $patientId): ?>
                    <a href="patient-records.php" class="staff-btn staff-btn-outline">Clear</a>
                <?php endif; ?>
            </form>

            <?php if ($searchTerm && !empty($searchResults)): ?>
                <div class="staff-search-results">
                    <h4>Search Results (<?php echo count($searchResults); ?> found)</h4>
                    <div class="staff-patient-list">
                        <?php foreach ($searchResults as $result): ?>
                            <div class="staff-patient-item">
                                <div class="staff-patient-info">
                                    <strong><?php echo htmlspecialchars($result['firstName'] . ' ' . $result['lastName']); ?></strong>
                                    <small><?php echo htmlspecialchars($result['email']); ?> | <?php echo htmlspecialchars($result['phoneNumber']); ?></small>
                                </div>
                                <a href="?patient_id=<?php echo $result['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">
                                    View Records
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($searchTerm): ?>
                <div class="staff-search-results">
                    <p class="staff-text-muted">No patients found. <a href="register-patient.php">Register new patient</a></p>
                </div>
            <?php endif; ?>

            <?php if (!$searchTerm && !$patientId && !empty($recentPatients)): ?>
                <div class="staff-recent-patients">
                    <h4><i class="fas fa-clock"></i> Recent Patients</h4>
                    <div class="staff-patient-list">
                        <?php foreach ($recentPatients as $recent): ?>
                            <div class="staff-patient-item">
                                <div class="staff-patient-info">
                                    <strong><?php echo htmlspecialchars($recent['firstName'] . ' ' . $recent['lastName']); ?></strong>
                                    <small><?php echo htmlspecialchars($recent['email']); ?> | <?php echo htmlspecialchars($recent['phoneNumber']); ?></small>
                                </div>
                                <a href="?patient_id=<?php echo $recent['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">
                                    View Records
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($patient): ?>
        <!-- Patient Information -->
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
                <a href="patient-records.php" class="staff-btn staff-btn-outline staff-btn-sm">Search Another</a>
            </div>
            <div class="staff-card-body">
                <div class="staff-patient-info-grid">
                    <div class="staff-info-group">
                        <h4><i class="fas fa-user"></i> Personal Details</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ? date('M j, Y', strtotime($patient['dateOfBirth'])) : 'N/A'; ?></p>
                        <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
                        <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                    </div>
                    <div class="staff-info-group">
                        <h4><i class="fas fa-notes-medical"></i> Medical Information</h4>
                        <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['knownAllergies'] ?: 'None'); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($patient['address'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="staff-info-group">
                        <h4><i class="fas fa-phone-alt"></i> Emergency Contact</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['emergencyContactName'] ?: 'N/A'); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['emergencyContactPhone'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="staff-info-group">
                        <h4><i class="fas fa-shield-alt"></i> Insurance</h4>
                        <p><strong>Provider:</strong> <?php echo htmlspecialchars($patient['insuranceProvider'] ?: 'N/A'); ?></p>
                        <p><strong>Number:</strong> <?php echo htmlspecialchars($patient['insuranceNumber'] ?: 'N/A'); ?></p>
                    </div>
                </div>

                <div class="staff-action-buttons" style="margin-top: 25px;">
                    <a href="book-appointment.php?patient_id=<?php echo $patient['patientId']; ?>" class="staff-btn staff-btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                    <a href="create-bill.php?patient_id=<?php echo $patient['patientId']; ?>" class="staff-btn staff-btn-primary">
                        <i class="fas fa-receipt"></i> Create Bill
                    </a>
                    <a href="process-payment.php?patient_id=<?php echo $patient['patientId']; ?>" class="staff-btn staff-btn-outline">
                        <i class="fas fa-credit-card"></i> Process Payment
                    </a>
                </div>
            </div>
        </div>

        <!-- Medical Records -->
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-notes-medical"></i> Medical Records</h3>
            </div>
            <div class="staff-table-responsive">
                <?php if (empty($medicalRecords)): ?>
                    <p class="staff-empty-message">No medical records found.</p>
                <?php else: ?>
                    <table class="staff-data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicalRecords as $record): ?>
                                <tr>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></td>
                                    <td data-label="Doctor">Dr. <?php echo htmlspecialchars($record['doctorName']); ?></td>
                                    <td data-label="Diagnosis"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 50)); ?>...</td>
                                    <td data-label="Actions">
                                        <a href="../admin/medical-records-view.php?id=<?php echo $record['recordId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm" target="_blank">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Billing History -->
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-file-invoice-dollar"></i> Billing History</h3>
            </div>
            <div class="staff-table-responsive">
                <?php if (empty($bills)): ?>
                    <p class="staff-empty-message">No billing records found.</p>
                <?php else: ?>
                    <table class="staff-data-table">
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td data-label="Bill #">#<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($bill['generatedAt'])); ?></td>
                                    <td data-label="Amount">$<?php echo number_format($bill['totalAmount'], 2); ?></td>
                                    <td data-label="Status">
                                        <span class="staff-status-badge staff-status-<?php echo $bill['status']; ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="../admin/view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="staff-btn staff-btn-info staff-btn-sm" target="_blank">View</a>
                                        <?php if ($bill['status'] == 'unpaid'): ?>
                                            <a href="process-payment.php?bill_id=<?php echo $bill['billId']; ?>" class="staff-btn staff-btn-success staff-btn-sm">Process</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointments History -->
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-calendar-alt"></i> Appointment History</h3>
            </div>
            <div class="staff-table-responsive">
                <?php if (empty($appointments)): ?>
                    <p class="staff-empty-message">No appointments found.</p>
                <?php else: ?>
                    <table class="staff-data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                                    <td data-label="Doctor">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?></td>
                                    <td data-label="Status">
                                        <span class="staff-status-badge staff-status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Reason"><?php echo htmlspecialchars($appointment['reason'] ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>