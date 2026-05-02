<?php
date_default_timezone_set('Australia/Sydney');
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Appointments - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'] ?? 0;

// Handle cancellation
if (isset($_GET['cancel'])) {
    $appointmentId = $_GET['cancel'];
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancellationReason = 'Cancelled by patient' WHERE appointmentId = ? AND patientId = ? AND status = 'scheduled'");
    $stmt->execute([$appointmentId, $patientId]);
    $_SESSION['success'] = "Appointment cancelled!";
    header("Location: view-appointments.php");
    exit();
}

// Get all appointments
$stmt = $pdo->prepare("
    SELECT a.*, u.firstName as doctorFirstName, u.lastName as doctorLastName, 
           d.specialization, a.appointmentId
    FROM appointments a 
    JOIN doctors d ON a.doctorId = d.doctorId 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId
    WHERE a.patientId = ? 
    ORDER BY a.dateTime DESC
");
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

$upcoming = []; 
$past = [];
foreach ($appointments as $a) {
    if (strtotime($a['dateTime']) > time() && $a['status'] === 'scheduled') $upcoming[] = $a;
    else $past[] = $a;
}

$completedCount = count(array_filter($past, fn($a) => $a['status'] == 'completed'));
$cancelledCount = count(array_filter($past, fn($a) => $a['status'] == 'cancelled'));

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>
            <p>View and manage your scheduled appointments</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="appointments.php" class="patient-btn patient-btn-primary">
                <i class="fas fa-plus"></i> Book New
            </a>
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

    <div class="patient-stats-grid">
        <div class="patient-stat-card total">
            <div class="patient-stat-icon"><i class="fas fa-calendar"></i></div>
            <div class="patient-stat-content">
                <h3><?php echo count($appointments); ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        <div class="patient-stat-card upcoming">
            <div class="patient-stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="patient-stat-content">
                <h3><?php echo count($upcoming); ?></h3>
                <p>Upcoming</p>
            </div>
        </div>
        <div class="patient-stat-card completed">
            <div class="patient-stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="patient-stat-content">
                <h3><?php echo $completedCount; ?></h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="patient-stat-card cancelled">
            <div class="patient-stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="patient-stat-content">
                <h3><?php echo $cancelledCount; ?></h3>
                <p>Cancelled</p>
            </div>
        </div>
    </div>

    <div class="patient-card">
        <div class="patient-card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
        </div>
        <div class="patient-table-responsive">
            <?php if (empty($upcoming)): ?>
                <div class="patient-empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p>No upcoming appointments scheduled.</p>
                    <a href="appointments.php" class="patient-btn patient-btn-primary">Book an Appointment</a>
                </div>
            <?php else: ?>
                <table class="patient-data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $a): ?>
                            <tr>
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($a['dateTime'])); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($a['doctorFirstName'].' '.$a['doctorLastName']); ?></td>
                                <td data-label="Specialization"><?php echo $a['specialization']; ?></td>
                                <td data-label="Actions">
                                    <div class="patient-action-buttons">
                                        <a href="reschedule-appointment.php?id=<?php echo $a['appointmentId']; ?>" class="patient-btn patient-btn-warning patient-btn-sm">
                                            <i class="fas fa-calendar-alt"></i> Reschedule
                                        </a>
                                        <a href="?cancel=<?php echo $a['appointmentId']; ?>" 
                                           class="patient-btn patient-btn-danger patient-btn-sm" 
                                           onclick="return confirm('Cancel this appointment?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="patient-card">
        <div class="patient-card-header">
            <h3><i class="fas fa-history"></i> Past Appointments</h3>
        </div>
        <div class="patient-table-responsive">
            <?php if (empty($past)): ?>
                <p class="patient-text-muted" style="text-align: center; padding: 40px;">No past appointments.</p>
            <?php else: ?>
                <table class="patient-data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past as $a): ?>
                            <tr>
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($a['dateTime'])); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($a['doctorFirstName'].' '.$a['doctorLastName']); ?></td>
                                <td data-label="Specialization"><?php echo $a['specialization']; ?></td>
                                <td data-label="Status">
                                    <span class="patient-status-badge patient-status-<?php echo $a['status']; ?>">
                                        <?php echo ucfirst($a['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Reason"><?php echo htmlspecialchars($a['reason'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.patient-action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.patient-btn-warning {
    background: #f59e0b;
    color: white;
}
.patient-btn-warning:hover {
    background: #d97706;
}
</style>

<?php include '../includes/footer.php'; ?>