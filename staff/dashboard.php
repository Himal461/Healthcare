<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Reception Dashboard - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT s.*, CONCAT(u.firstName, ' ', u.lastName) as staffName FROM staff s JOIN users u ON s.userId = u.userId WHERE u.userId = ?");
$stmt->execute([$userId]);
$staff = $stmt->fetch();

// Today's appointments
$stmt = $pdo->prepare("
    SELECT a.*, CONCAT(pu.firstName, ' ', pu.lastName) as patientName, 
           pu.email as patientEmail, pu.phoneNumber as patientPhone,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName, d.specialization,
           a.appointmentId
    FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON a.doctorId = d.doctorId 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users du ON s.userId = du.userId
    WHERE DATE(a.dateTime) = CURDATE() 
    AND pu.role = 'patient'
    ORDER BY a.dateTime
");
$stmt->execute();
$todayAppointments = $stmt->fetchAll();

// Upcoming appointments (next 7 days)
$stmt = $pdo->prepare("
    SELECT a.*, CONCAT(pu.firstName, ' ', pu.lastName) as patientName, 
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           a.appointmentId
    FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON a.doctorId = d.doctorId 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users du ON s.userId = du.userId
    WHERE DATE(a.dateTime) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
    AND a.status IN ('scheduled', 'confirmed')
    AND pu.role = 'patient'
    ORDER BY a.dateTime
");
$stmt->execute();
$upcomingAppointments = $stmt->fetchAll();

// New patients (last 10)
$stmt = $pdo->prepare("SELECT u.userId, u.firstName, u.lastName, u.email, u.phoneNumber, u.dateCreated FROM users u WHERE u.role = 'patient' ORDER BY u.dateCreated DESC LIMIT 10");
$stmt->execute();
$newPatients = $stmt->fetchAll();

// Pending payments
$stmt = $pdo->prepare("
    SELECT b.*, CONCAT(u.firstName, ' ', u.lastName) as patientName
    FROM bills b JOIN patients p ON b.patientId = p.patientId JOIN users u ON p.userId = u.userId
    WHERE b.status = 'unpaid' ORDER BY b.generatedAt DESC LIMIT 10
");
$stmt->execute();
$pendingPayments = $stmt->fetchAll();

$totalPending = $pdo->query("SELECT SUM(totalAmount) FROM bills WHERE status = 'unpaid'")->fetchColumn() ?? 0;
// Checked in count
$checkedIn = $pdo->query("
    SELECT COUNT(*) FROM appointments 
    WHERE DATE(dateTime) = CURDATE() 
    AND status IN ('confirmed', 'in-progress')
")->fetchColumn();

// Today's total
$totalAppointmentsToday = count($todayAppointments);

// Week's upcoming
$totalAppointmentsWeek = count($upcomingAppointments);
$totalAppointmentsToday = count($todayAppointments);
$totalAppointmentsWeek = count($upcomingAppointments);
$pendingPaymentsCount = count($pendingPayments);

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="staff-container">
    <div class="staff-welcome-section">
        <div class="staff-welcome-text">
            <h1>Welcome, <?php echo htmlspecialchars($staff['staffName']); ?>!</h1>
            <p>Reception Dashboard - <?php echo htmlspecialchars($staff['position'] ?? 'Receptionist'); ?></p>
        </div>
        <div class="staff-date-display">
            <i class="fas fa-calendar-alt"></i>
            <span><?php echo date('l, F j, Y'); ?></span>
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

    <div class="staff-stats-grid">
        <div class="staff-stat-card today">
            <div class="staff-stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="staff-stat-content">
                <h3><?php echo $totalAppointmentsToday; ?></h3>
                <p>Today's Appointments</p>
            </div>
        </div>
        <div class="staff-stat-card checkedin">
            <div class="staff-stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="staff-stat-content">
                <h3><?php echo $checkedIn; ?></h3>
                <p>Checked In Today</p>
            </div>
        </div>
        <div class="staff-stat-card week">
            <div class="staff-stat-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="staff-stat-content">
                <h3><?php echo $totalAppointmentsWeek; ?></h3>
                <p>This Week</p>
            </div>
        </div>
        <div class="staff-stat-card patients">
            <div class="staff-stat-icon"><i class="fas fa-user-plus"></i></div>
            <div class="staff-stat-content">
                <h3><?php echo count($newPatients); ?></h3>
                <p>New Patients</p>
            </div>
        </div>
        <div class="staff-stat-card pending">
            <div class="staff-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="staff-stat-content">
                <h3><?php echo $pendingPaymentsCount; ?></h3>
                <p>Pending Payments</p>
            </div>
        </div>
        <div class="staff-stat-card amount">
            <div class="staff-stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="staff-stat-content">
                <h3>$<?php echo number_format($totalPending, 2); ?></h3>
                <p>Pending Amount</p>
            </div>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="staff-card-body">
            <div class="staff-quick-actions-grid">
                <a href="book-appointment.php" class="staff-action-card">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Book Appointment</span>
                </a>
                <a href="register-patient.php" class="staff-action-card">
                    <i class="fas fa-user-plus"></i>
                    <span>Register Patient</span>
                </a>
                <a href="process-payment.php" class="staff-action-card">
                    <i class="fas fa-credit-card"></i>
                    <span>Process Payment</span>
                </a>
                <a href="patient-records.php" class="staff-action-card">
                    <i class="fas fa-folder-open"></i>
                    <span>Patient Records</span>
                </a>

                <a href="reschedule-appointment.php" class="staff-action-card">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Reschedule</span> 
                </a>
            </div>
        </div>
    </div>

    <div class="staff-card">
        <div class="staff-card-header">
            <h3><i class="fas fa-calendar-day"></i> Today's Appointments</h3>
        </div>
        <div class="staff-table-responsive">
            <?php if (empty($todayAppointments)): ?>
                <div class="staff-empty-message">No appointments scheduled for today.</div>
            <?php else: ?>
                <table class="staff-data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayAppointments as $a): ?>
                            <tr>
                                <td data-label="Time"><?php echo date('g:i A', strtotime($a['dateTime'])); ?></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($a['patientName']); ?></strong><br>
                                    <small><?php echo $a['patientPhone']; ?></small>
                                </td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($a['doctorName']); ?></td>
                                <td data-label="Status">
                                    <span class="staff-status-badge staff-status-<?php echo $a['status']; ?>">
                                        <?php echo ucfirst($a['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="staff-action-buttons">
                                        <?php if ($a['status'] == 'scheduled'): ?>
                                            <a href="checkin-appointment.php?id=<?php echo $a['appointmentId']; ?>" class="staff-btn staff-btn-success staff-btn-sm" onclick="return confirm('Check in patient?')">
                                                <i class="fas fa-check"></i> Check In
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($a['status'] == 'scheduled'): ?>
                                            <a href="reschedule-appointment.php?id=<?php echo $a['appointmentId']; ?>" class="staff-btn staff-btn-warning staff-btn-sm">
                                                <i class="fas fa-calendar-alt"></i> Reschedule
                                            </a>
                                            <a href="cancel-appointment.php?id=<?php echo $a['appointmentId']; ?>" class="staff-btn staff-btn-danger staff-btn-sm" onclick="return confirm('Cancel this appointment?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
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

    <div class="staff-card">
        <div class="staff-card-header">
            <h3><i class="fas fa-credit-card"></i> Pending Payments</h3>
        </div>
        <div class="staff-table-responsive">
            <?php if (empty($pendingPayments)): ?>
                <div class="staff-empty-message">No pending payments.</div>
            <?php else: ?>
                <table class="staff-data-table">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Patient</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPayments as $p): ?>
                            <tr>
                                <td data-label="Bill ID">#<?php echo $p['billId']; ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($p['patientName']); ?></td>
                                <td data-label="Amount">$<?php echo number_format($p['totalAmount'], 2); ?></td>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($p['generatedAt'])); ?></td>
                                <td data-label="Actions">
                                    <a href="process-payment.php?bill_id=<?php echo $p['billId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">
                                        <i class="fas fa-credit-card"></i> Process
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.staff-action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}
.staff-btn-warning {
    background: #f59e0b;
    color: white;
}
.staff-btn-warning:hover {
    background: #d97706;
}
</style>

<?php include '../includes/footer.php'; ?>