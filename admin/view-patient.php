<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$patientId = (int)($_GET['id'] ?? 0);

if (!$patientId) {
    $_SESSION['error'] = "Patient ID required.";
    header("Location: patients.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, u.userId, u.username, u.firstName, u.lastName, u.email, u.phoneNumber, u.dateCreated
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE p.patientId = ? AND u.role = 'patient'
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header("Location: patients.php");
    exit();
}

$appointments = $pdo->prepare("
    SELECT a.*, CONCAT(du.firstName, ' ', du.lastName) as doctorName, d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE a.patientId = ? ORDER BY a.dateTime DESC LIMIT 20
");
$appointments->execute([$patientId]);
$appointments = $appointments->fetchAll();

$medicalRecords = $pdo->prepare("
    SELECT mr.*, CONCAT(du.firstName, ' ', du.lastName) as doctorName, d.specialization
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE mr.patientId = ? ORDER BY mr.creationDate DESC LIMIT 10
");
$medicalRecords->execute([$patientId]);
$medicalRecords = $medicalRecords->fetchAll();

$bills = $pdo->prepare("SELECT * FROM bills WHERE patientId = ? ORDER BY generatedAt DESC LIMIT 10");
$bills->execute([$patientId]);
$bills = $bills->fetchAll();

$pageTitle = "Patient: " . htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']) . " - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user"></i> Patient Details</h1>
            <p><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="patients.php" class="admin-btn admin-btn-outline"><i class="fas fa-arrow-left"></i> Back to Patients</a>
            <a href="book-appointment.php?patient_id=<?php echo $patientId; ?>" class="admin-btn admin-btn-success"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-info-circle"></i> Patient Information</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-patient-info-grid">
                <div class="admin-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></p>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($patient['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ?: 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($patient['address'] ?: 'N/A'); ?></p>
                </div>
                <div class="admin-info-group">
                    <h4>Medical Information</h4>
                    <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['knownAllergies'] ?: 'None'); ?></p>
                    <p><strong>Insurance Provider:</strong> <?php echo htmlspecialchars($patient['insuranceProvider'] ?: 'N/A'); ?></p>
                    <p><strong>Insurance Number:</strong> <?php echo htmlspecialchars($patient['insuranceNumber'] ?: 'N/A'); ?></p>
                </div>
                <div class="admin-info-group">
                    <h4>Emergency Contact</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['emergencyContactName'] ?: 'N/A'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['emergencyContactPhone'] ?: 'N/A'); ?></p>
                </div>
                <div class="admin-info-group">
                    <h4>Account Info</h4>
                    <p><strong>Registered:</strong> <?php echo date('M j, Y', strtotime($patient['dateCreated'])); ?></p>
                    <p><strong>Total Appointments:</strong> <?php echo count($appointments); ?></p>
                    <p><strong>Medical Records:</strong> <?php echo count($medicalRecords); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-calendar-alt"></i> Appointment History</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr><th>Date & Time</th><th>Doctor</th><th>Specialization</th><th>Status</th><th>Reason</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr><td colspan="5" class="admin-empty-message">No appointments found</td></tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($a['dateTime'])); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($a['doctorName']); ?></td>
                                <td data-label="Specialization"><?php echo htmlspecialchars($a['specialization']); ?></td>
                                <td data-label="Status">
                                    <span class="admin-status-badge admin-status-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span>
                                </td>
                                <td data-label="Reason"><?php echo htmlspecialchars($a['reason'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-notes-medical"></i> Medical Records</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr><th>Date</th><th>Doctor</th><th>Diagnosis</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($medicalRecords)): ?>
                        <tr><td colspan="4" class="admin-empty-message">No records found</td></tr>
                    <?php else: ?>
                        <?php foreach ($medicalRecords as $r): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($r['creationDate'])); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($r['doctorName']); ?></td>
                                <td data-label="Diagnosis"><?php echo htmlspecialchars(substr($r['diagnosis'], 0, 60)); ?>...</td>
                                <td data-label="Actions">
                                    <a href="medical-records-view.php?id=<?php echo $r['recordId']; ?>" class="admin-btn admin-btn-primary admin-btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-file-invoice"></i> Billing History</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr><th>Bill ID</th><th>Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="5" class="admin-empty-message">No bills found</td></tr>
                    <?php else: ?>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td data-label="Bill ID">#<?php echo str_pad($b['billId'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($b['generatedAt'])); ?></td>
                                <td data-label="Amount">$<?php echo number_format($b['totalAmount'], 2); ?></td>
                                <td data-label="Status">
                                    <span class="admin-status-badge admin-status-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span>
                                </td>
                                <td data-label="Actions">
                                    <a href="view-bill.php?bill_id=<?php echo $b['billId']; ?>" class="admin-btn admin-btn-info admin-btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>