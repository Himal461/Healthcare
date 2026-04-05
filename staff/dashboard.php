<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Reception Dashboard - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get staff details
$stmt = $pdo->prepare("
    SELECT s.*, CONCAT(u.firstName, ' ', u.lastName) as staffName
    FROM staff s
    JOIN users u ON s.userId = u.userId
    WHERE u.userId = ?
");
$stmt->execute([$userId]);
$staff = $stmt->fetch();

// Get today's appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           pu.email as patientEmail,
           pu.phoneNumber as patientPhone,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE DATE(a.dateTime) = CURDATE()
    ORDER BY a.dateTime
");
$stmt->execute();
$todayAppointments = $stmt->fetchAll();

// Get upcoming appointments for the week
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           a.status
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE DATE(a.dateTime) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND a.status = 'scheduled'
    ORDER BY a.dateTime
");
$stmt->execute();
$upcomingAppointments = $stmt->fetchAll();

// Get recent patient registrations
$stmt = $pdo->prepare("
    SELECT u.userId, u.firstName, u.lastName, u.email, u.phoneNumber, u.dateCreated
    FROM users u
    WHERE u.role = 'patient'
    ORDER BY u.dateCreated DESC
    LIMIT 10
");
$stmt->execute();
$newPatients = $stmt->fetchAll();

// Get pending payments
$stmt = $pdo->prepare("
    SELECT b.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName
    FROM billing b
    JOIN patients p ON b.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE b.status = 'pending'
    ORDER BY b.dueDate ASC
    LIMIT 10
");
$stmt->execute();
$pendingPayments = $stmt->fetchAll();

// Get total pending amount
$totalPending = $pdo->query("
    SELECT SUM(totalAmount) as total FROM billing WHERE status = 'pending'
")->fetch()['total'] ?? 0;

// Get today's check-ins
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE DATE(dateTime) = CURDATE() AND status IN ('confirmed', 'in-progress')
");
$stmt->execute();
$checkedIn = $stmt->fetch()['count'];

// Get statistics
$totalAppointmentsToday = count($todayAppointments);
$totalAppointmentsWeek = count($upcomingAppointments);
$newPatientsCount = count($newPatients);
$pendingPaymentsCount = count($pendingPayments);
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($staff['staffName']); ?>!</h1>
        <p>Reception Dashboard - <?php echo htmlspecialchars($staff['position'] ?? 'Receptionist'); ?></p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card staff">
            <h3><?php echo $totalAppointmentsToday; ?></h3>
            <p>Today's Appointments</p>
        </div>
        <div class="stat-card staff">
            <h3><?php echo $checkedIn; ?></h3>
            <p>Checked In Today</p>
        </div>
        <div class="stat-card staff">
            <h3><?php echo $totalAppointmentsWeek; ?></h3>
            <p>This Week's Appointments</p>
        </div>
        <div class="stat-card staff">
            <h3><?php echo $newPatientsCount; ?></h3>
            <p>New Patients (This Month)</p>
        </div>
        <div class="stat-card staff">
            <h3><?php echo $pendingPaymentsCount; ?></h3>
            <p>Pending Payments</p>
        </div>
        <div class="stat-card staff">
            <h3>$<?php echo number_format($totalPending, 2); ?></h3>
            <p>Total Pending Amount</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions-grid">
                <a href="book-appointment.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="register-patient.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Register New Patient
                </a>
                <a href="process-payment.php" class="btn btn-primary">
                    <i class="fas fa-dollar-sign"></i> Process Payment
                </a>
                <a href="../patient/appointments.php" class="btn btn-outline">
                    <i class="fas fa-calendar-alt"></i> View All Appointments
                </a>
                <a href="../doctors.php" class="btn btn-outline">
                    <i class="fas fa-user-md"></i> View Doctors
                </a>
                <a href="patient-records.php" class="btn btn-outline">
                    <i class="fas fa-folder-open"></i> Patient Records
                </a>
            </div>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-day"></i> Today's Appointments (<?php echo date('F j, Y'); ?>)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todayAppointments)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No appointments scheduled for today</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($todayAppointments as $appointment): ?>
                            <tr>
                                <td data-label="Time"><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($appointment['patientName']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($appointment['patientPhone']); ?></small>
                                </td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?></td>
                                <td data-label="Specialization"><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <a href="checkin-appointment.php?id=<?php echo $appointment['appointmentId']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this patient as checked in?')">
                                            <i class="fas fa-check-circle"></i> Check In
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($appointment['status'] === 'completed' && !hasBill($appointment['appointmentId'])): ?>
                                        <a href="create-bill.php?appointment_id=<?php echo $appointment['appointmentId']; ?>&patient_id=<?php echo $appointment['patientId']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-receipt"></i> Create Bill
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments (This Week)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingAppointments)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No upcoming appointments this week</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <tr>
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($appointment['patientName']); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a href="../patient/appointments.php?reschedule=<?php echo $appointment['appointmentId']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-calendar-alt"></i> Reschedule
                                    </a>
                                    <a href="cancel-appointment.php?id=<?php echo $appointment['appointmentId']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this appointment?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending Payments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-dollar-sign"></i> Pending Payments</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Patient</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingPayments)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No pending payments</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingPayments as $payment): ?>
                            <tr>
                                <td data-label="Bill ID">#<?php echo $payment['billId']; ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($payment['patientName']); ?></td>
                                <td data-label="Amount">$<?php echo number_format($payment['totalAmount'], 2); ?></td>
                                <td data-label="Due Date"><?php echo date('M j, Y', strtotime($payment['dueDate'])); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-pending">Pending</span>
                                </td>
                                <td data-label="Actions">
                                    <a href="process-payment.php?bill_id=<?php echo $payment['billId']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-credit-card"></i> Process Payment
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Patient Registrations -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-plus"></i> Recent Patient Registrations</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newPatients as $patient): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($patient['dateCreated'])); ?></td>
                            <td data-label="Name"><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($patient['email']); ?></td>
                            <td data-label="Phone"><?php echo htmlspecialchars($patient['phoneNumber']); ?></td>
                            <td data-label="Actions">
                                <a href="patient-records.php?patient_id=<?php echo $patient['userId']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>