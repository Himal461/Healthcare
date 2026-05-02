<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "Patient Dashboard - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient details
$stmt = $pdo->prepare("
    SELECT p.*, CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email, u.phoneNumber
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.userId = ? AND u.role = 'patient'
");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: ../logout.php");
    exit();
}

$patientId = $patient['patientId'];

// Get upcoming appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           d.specialization,
           a.appointmentId
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE a.patientId = ? 
    AND a.dateTime >= NOW() 
    AND a.status IN ('scheduled', 'confirmed')
    ORDER BY a.dateTime ASC
    LIMIT 5
");
$stmt->execute([$patientId]);
$upcomingAppointments = $stmt->fetchAll();

// Get recent medical records
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           d.specialization
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE mr.patientId = ?
    ORDER BY mr.creationDate DESC
    LIMIT 3
");
$stmt->execute([$patientId]);
$recentRecords = $stmt->fetchAll();

// Get pending bills
$stmt = $pdo->prepare("
    SELECT * FROM bills 
    WHERE patientId = ? AND status = 'unpaid'
    ORDER BY generatedAt DESC
");
$stmt->execute([$patientId]);
$pendingBills = $stmt->fetchAll();
$pendingBillsTotal = array_sum(array_column($pendingBills, 'totalAmount'));

// Get recent prescriptions
$stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           mr.diagnosis
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN doctors d ON p.prescribedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE mr.patientId = ? AND p.status = 'active'
    ORDER BY p.createdAt DESC
    LIMIT 3
");
$stmt->execute([$patientId]);
$activePrescriptions = $stmt->fetchAll();

// Statistics
$totalAppointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patientId = ?");
$totalAppointments->execute([$patientId]);
$totalAppointmentsCount = $totalAppointments->fetchColumn();

$upcomingCount = count($upcomingAppointments);
$pendingBillsCount = count($pendingBills);

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="patient-container">
    <!-- Welcome Section -->
    <div class="patient-welcome-section">
        <div class="patient-welcome-text">
            <h1>Welcome, <?php echo htmlspecialchars($patient['patientName']); ?>!</h1>
            <p>Your Health Dashboard</p>
        </div>
        <div class="patient-date-display">
            <i class="fas fa-calendar-alt"></i>
            <span><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="patient-alert patient-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="patient-alert patient-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="patient-stats-grid">
        <div class="patient-stat-card total">
            <div class="patient-stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="patient-stat-content">
                <h3><?php echo $totalAppointmentsCount; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        <div class="patient-stat-card upcoming">
            <div class="patient-stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="patient-stat-content">
                <h3><?php echo $upcomingCount; ?></h3>
                <p>Upcoming</p>
            </div>
        </div>
        <div class="patient-stat-card bills">
            <div class="patient-stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="patient-stat-content">
                <h3>$<?php echo number_format($pendingBillsTotal, 2); ?></h3>
                <p>Pending Bills</p>
            </div>
        </div>
        <div class="patient-stat-card completed">
            <div class="patient-stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="patient-stat-content">
                <h3><?php echo count($activePrescriptions); ?></h3>
                <p>Active Prescriptions</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="patient-card">
        <div class="patient-card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="patient-card-body">
            <div class="patient-quick-actions-grid">
                <a href="appointments.php" class="patient-action-card">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Book Appointment</span>
                </a>
                <a href="view-appointments.php" class="patient-action-card">
                    <i class="fas fa-calendar-alt"></i>
                    <span>My Appointments</span>
                </a>
                <a href="my-medical-records.php" class="patient-action-card">
                    <i class="fas fa-notes-medical"></i>
                    <span>Medical Records</span>
                </a>
                <a href="my-prescriptions.php" class="patient-action-card">
                    <i class="fas fa-prescription"></i>
                    <span>Prescriptions</span>
                </a>
                <a href="view-bills.php" class="patient-action-card">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>My Bills</span>
                </a>
                <a href="../profile.php" class="patient-action-card">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>
                <a href="view-medical-certificates.php" class="patient-action-card">
                    <i class="fas fa-certificate"></i>
                    <span>Medical Certificates</span>       
            </a>
            </div>
        </div>
    </div>

    <!-- Pending Bills Alert -->
    <?php if ($pendingBillsCount > 0): ?>
        <div class="patient-alert patient-alert-info">
            <i class="fas fa-exclamation-circle"></i>
            <strong>You have <?php echo $pendingBillsCount; ?> pending bill(s)</strong> totaling $<?php echo number_format($pendingBillsTotal, 2); ?>
            <a href="view-bills.php" class="patient-btn patient-btn-primary patient-btn-sm" style="margin-left: auto;">View</a>
        </div>
    <?php endif; ?>

    <!-- Upcoming Appointments -->
    <div class="patient-card">
        <div class="patient-card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
            <a href="view-appointments.php" class="patient-btn patient-btn-outline patient-btn-sm">View All</a>
        </div>
        <div class="patient-card-body">
            <?php if (empty($upcomingAppointments)): ?>
                <div class="patient-empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No upcoming appointments.</p>
                    <a href="appointments.php" class="patient-btn patient-btn-primary">Book an Appointment</a>
                </div>
            <?php else: ?>
                <div class="patient-table-responsive">
                    <table class="patient-data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Specialization</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <tr>
                                    <td data-label="Date & Time">
                                        <strong><?php echo date('M j, Y', strtotime($appointment['dateTime'])); ?></strong><br>
                                        <small><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></small>
                                    </td>
                                    <td data-label="Doctor">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?></td>
                                    <td data-label="Specialization"><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                    <td data-label="Status">
                                        <span class="patient-status-badge patient-status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="view-appointments.php?cancel=<?php echo $appointment['appointmentId']; ?>" 
                                           class="patient-btn patient-btn-danger patient-btn-sm"
                                           onclick="return confirm('Cancel this appointment?')">
                                            Cancel
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Medical Records -->
    <div class="patient-card">
        <div class="patient-card-header">
            <h3><i class="fas fa-notes-medical"></i> Recent Medical Records</h3>
            <a href="my-medical-records.php" class="patient-btn patient-btn-outline patient-btn-sm">View All</a>
        </div>
        <div class="patient-card-body">
            <?php if (empty($recentRecords)): ?>
                <p class="patient-text-muted" style="text-align: center;">No medical records yet.</p>
            <?php else: ?>
                <?php foreach ($recentRecords as $record): ?>
                    <div style="padding: 15px; background: #f8fafc; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid #0d9488;">
                        <p><strong><?php echo date('F j, Y', strtotime($record['creationDate'])); ?></strong> - Dr. <?php echo htmlspecialchars($record['doctorName']); ?></p>
                        <p style="color: #64748b;"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 100)); ?>...</p>
                        <a href="my-medical-records.php?view=<?php echo $record['recordId']; ?>" class="patient-btn patient-btn-outline patient-btn-sm">View Details</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>