<?php
date_default_timezone_set('Australia/Sydney');

require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Appointments - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'];

// Handle appointment cancellation
if (isset($_GET['cancel'])) {
    $appointmentId = $_GET['cancel'];
    try {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', cancellationReason = 'Cancelled by patient' 
            WHERE appointmentId = ? AND patientId = ? AND status = 'scheduled'
        ");
        $stmt->execute([$appointmentId, $patientId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Appointment cancelled successfully!";
            logAction($userId, 'CANCEL_APPOINTMENT', "Cancelled appointment ID: $appointmentId");
        } else {
            $_SESSION['error'] = "Unable to cancel this appointment.";
        }
        
        header("Location: view-appointments.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to cancel appointment.";
        header("Location: view-appointments.php");
        exit();
    }
}

// Get all appointments with full details
$stmt = $pdo->prepare("
    SELECT a.*, 
           u.firstName as doctorFirstName, 
           u.lastName as doctorLastName, 
           d.specialization,
           a.reason,
           a.notes,
           a.status,
           a.dateTime,
           a.createdAt
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE a.patientId = ?
    ORDER BY a.dateTime DESC
");
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

// Separate appointments into upcoming and past
$upcomingAppointments = [];
$pastAppointments = [];

foreach ($appointments as $appointment) {
    if (strtotime($appointment['dateTime']) > time() && $appointment['status'] === 'scheduled') {
        $upcomingAppointments[] = $appointment;
    } else {
        $pastAppointments[] = $appointment;
    }
}

// Get statistics
$totalAppointments = count($appointments);
$upcomingCount = count($upcomingAppointments);
$completedCount = 0;
$cancelledCount = 0;

foreach ($pastAppointments as $appointment) {
    if ($appointment['status'] === 'completed') {
        $completedCount++;
    } elseif ($appointment['status'] === 'cancelled') {
        $cancelledCount++;
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>My Appointments</h1>
        <p>View and manage all your appointments</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card patient">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $upcomingCount; ?></h3>
            <p>Upcoming</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $completedCount; ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $cancelledCount; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
        </div>
        <div class="table-container">
            <?php if (empty($upcomingAppointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p>No upcoming appointments. <a href="appointments.php?view=book">Book one now!</a></p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <tr class="appointment-row">
                                <td data-label="Date & Time">
                                    <strong><?php echo date('M j, Y', strtotime($appointment['dateTime'])); ?></strong><br>
                                    <small><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></small>
                                </td>
                                <td data-label="Doctor">
                                    <strong>Dr. <?php echo htmlspecialchars($appointment['doctorFirstName'] . ' ' . $appointment['doctorLastName']); ?></strong>
                                </td>
                                <td data-label="Specialization"><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                <td data-label="Reason"><?php echo htmlspecialchars($appointment['reason'] ?: 'Not specified'); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a href="?cancel=<?php echo $appointment['appointmentId']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Past Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Past Appointments</h3>
        </div>
        <div class="table-container">
            <?php if (empty($pastAppointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No past appointments found.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                    
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </thead>
                    <tbody>
                        <?php foreach ($pastAppointments as $appointment): ?>
                            <tr class="appointment-row">
                                <td data-label="Date & Time">
                                    <?php echo date('M j, Y', strtotime($appointment['dateTime'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></small>
                                </td>
                                <td data-label="Doctor">
                                    Dr. <?php echo htmlspecialchars($appointment['doctorFirstName'] . ' ' . $appointment['doctorLastName']); ?>
                                </td>
                                <td data-label="Specialization"><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                <td data-label="Reason"><?php echo htmlspecialchars($appointment['reason'] ?: 'Not specified'); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Notes"><?php echo htmlspecialchars($appointment['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-footer">
        <a href="appointments.php?view=book" class="btn btn-primary">
            <i class="fas fa-calendar-plus"></i> Book New Appointment
        </a>
        <a href="medical-records.php" class="btn btn-outline">
            <i class="fas fa-folder-open"></i> View Medical Records
        </a>
        <a href="prescriptions.php" class="btn btn-outline">
            <i class="fas fa-prescription"></i> View Prescriptions
        </a>
    </div>
</div>


<?php include '../includes/footer.php'; ?>