<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "View Appointments - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';

$patientId = (int)($_GET['patient_id'] ?? 0);
$doctorId = (int)($_GET['doctor_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Get patient info if viewing specific patient
$patient = null;
if ($patientId) {
    $stmt = $pdo->prepare("SELECT p.*, u.firstName, u.lastName FROM patients p JOIN users u ON p.userId = u.userId WHERE p.patientId = ? AND u.role = 'patient'");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
}

// Handle cancellation
if (isset($_GET['cancel']) && $_GET['cancel']) {
    $appointmentId = (int)$_GET['cancel'];
    cancelAppointment($appointmentId, $_SESSION['user_id'], 'Cancelled by admin');
    $_SESSION['success'] = "Appointment cancelled successfully.";
    $redirect = "view-appointments.php";
    if ($patientId) $redirect .= "?patient_id=" . $patientId;
    header("Location: " . $redirect);
    exit();
}

// Build query
$query = "
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
    WHERE pu.role = 'patient'
";
$params = [];

if ($patientId) { $query .= " AND a.patientId = ?"; $params[] = $patientId; }
if ($doctorId) { $query .= " AND a.doctorId = ?"; $params[] = $doctorId; }
if ($statusFilter) { $query .= " AND a.status = ?"; $params[] = $statusFilter; }
if ($dateFrom) { $query .= " AND DATE(a.dateTime) >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $query .= " AND DATE(a.dateTime) <= ?"; $params[] = $dateTo; }

$query .= " ORDER BY a.dateTime DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$doctors = $pdo->query("SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name FROM doctors d JOIN staff s ON d.staffId = s.staffId JOIN users u ON s.userId = u.userId")->fetchAll();
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-alt"></i> Appointments</h1>
            <?php if ($patient): ?>
                <p>Viewing appointments for: <?php echo htmlspecialchars($patient['firstName'].' '.$patient['lastName']); ?></p>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <a href="<?php echo $patientId ? 'view-patient.php?id='.$patientId : 'patients.php'; ?>" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($patientId): ?>
                <a href="book-appointment.php?patient_id=<?php echo $patientId; ?>" class="admin-btn admin-btn-success">
                    <i class="fas fa-calendar-plus"></i> Book New
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-filter"></i> Filter Appointments</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="admin-filter-row">
                <?php if ($patientId): ?><input type="hidden" name="patient_id" value="<?php echo $patientId; ?>"><?php endif; ?>
                <div class="admin-filter-group">
                    <select name="doctor" class="admin-form-control">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?php echo $d['doctorId']; ?>" <?php echo $doctorId==$d['doctorId']?'selected':''; ?>>Dr. <?php echo $d['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-filter-group">
                    <select name="status" class="admin-form-control">
                        <option value="">All Status</option>
                        <option value="scheduled" <?php echo $statusFilter=='scheduled'?'selected':''; ?>>Scheduled</option>
                        <option value="confirmed" <?php echo $statusFilter=='confirmed'?'selected':''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $statusFilter=='completed'?'selected':''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $statusFilter=='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="admin-filter-group">
                    <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" class="admin-form-control">
                </div>
                <div class="admin-filter-group">
                    <input type="date" name="date_to" value="<?php echo $dateTo; ?>" class="admin-form-control">
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">Filter</button>
                    <a href="?<?php echo $patientId ? 'patient_id='.$patientId : ''; ?>" class="admin-btn admin-btn-outline">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> Appointments (<?php echo count($appointments); ?>)</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <?php if(!$patientId): ?><th>Patient</th><?php endif; ?>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr><td colspan="<?php echo $patientId ? '6' : '7'; ?>" class="admin-empty-message">No appointments found</td></tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td data-label="ID">#<?php echo $a['appointmentId']; ?></td>
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($a['dateTime'])); ?></td>
                                <?php if(!$patientId): ?>
                                    <td data-label="Patient">
                                        <?php echo htmlspecialchars($a['patientName']); ?><br>
                                        <small><?php echo $a['patientPhone']; ?></small>
                                    </td>
                                <?php endif; ?>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($a['doctorName']); ?><br><small><?php echo $a['specialization']; ?></small></td>
                                <td data-label="Status">
                                    <span class="admin-status-badge admin-status-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span>
                                </td>
                                <td data-label="Reason"><?php echo htmlspecialchars($a['reason'] ?: '-'); ?></td>
                                <td data-label="Actions">
                                    <?php if ($a['status'] == 'scheduled'): ?>
                                        <a href="?cancel=<?php echo $a['appointmentId']; ?><?php echo $patientId ? '&patient_id='.$patientId : ''; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                                    <?php endif; ?>
                                    <?php if ($a['status'] == 'completed'): ?>
                                        <a href="../doctor/view-consultation.php?appointment_id=<?php echo $a['appointmentId']; ?>" class="admin-btn admin-btn-info admin-btn-sm">View</a>
                                    <?php endif; ?>
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