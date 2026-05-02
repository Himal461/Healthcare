<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Appointments - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

// Handle cancellation
if (isset($_GET['cancel'])) {
    $appointmentId = (int)$_GET['cancel'];
    
    $apptStmt = $pdo->prepare("SELECT * FROM appointments WHERE appointmentId = ?");
    $apptStmt->execute([$appointmentId]);
    $appointment = $apptStmt->fetch();
    
    if ($appointment) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancellationReason = 'Cancelled by admin', updatedAt = NOW() WHERE appointmentId = ?");
        $stmt->execute([$appointmentId]);
        
        if ($stmt->rowCount() > 0) {
            sendAppointmentCancellationEmail($appointmentId, 'Cancelled by hospital administration');
            
            $patientStmt = $pdo->prepare("SELECT u.userId FROM patients p JOIN users u ON p.userId = u.userId WHERE p.patientId = ?");
            $patientStmt->execute([$appointment['patientId']]);
            $patient = $patientStmt->fetch();
            
            if ($patient) {
                createNotification($patient['userId'], 'appointment', 'Appointment Cancelled',
                    "Your appointment on " . date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled by administration.");
            }
            
            $doctorStmt = $pdo->prepare("SELECT s.userId FROM doctors d JOIN staff s ON d.staffId = s.staffId WHERE d.doctorId = ?");
            $doctorStmt->execute([$appointment['doctorId']]);
            $doctor = $doctorStmt->fetch();
            if ($doctor) {
                createNotification($doctor['userId'], 'appointment', 'Appointment Cancelled',
                    "Appointment on " . date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled by admin.");
            }
            
            $_SESSION['success'] = "Appointment cancelled successfully.";
        }
    }
    
    header("Location: appointments.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointmentId = (int)$_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    $validStatuses = ['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled', 'no-show'];
    if (in_array($status, $validStatuses)) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] Status changed to: ', ?, ' - ', ?), updatedAt = NOW() WHERE appointmentId = ?");
        $stmt->execute([$status, $status, $notes, $appointmentId]);
        
        if ($stmt->rowCount() > 0) {
            if ($status === 'cancelled') {
                sendAppointmentCancellationEmail($appointmentId, 'Cancelled by hospital administration');
            }
            
            $_SESSION['success'] = "Appointment status updated successfully!";
            logAction($_SESSION['user_id'], 'UPDATE_APPOINTMENT', "Updated appointment $appointmentId to $status");
        }
    }
    header("Location: appointments.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $appointmentId = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE appointmentId = ?");
        $stmt->execute([$appointmentId]);
        $_SESSION['success'] = "Appointment deleted successfully!";
        logAction($_SESSION['user_id'], 'DELETE_APPOINTMENT', "Deleted appointment $appointmentId");
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete appointment.";
    }
    header("Location: appointments.php");
    exit();
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$doctorFilter = $_GET['100'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query - FIXED: Show all appointments properly
$query = "
    SELECT a.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           pu.email as patientEmail,
           pu.phoneNumber as patientPhone,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           p.patientId
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE pu.role = 'patient'
";
$params = [];

if ($statusFilter) { 
    $query .= " AND a.status = ?"; 
    $params[] = $statusFilter; 
}
if ($doctorFilter) { 
    $query .= " AND a.doctorId = ?"; 
    $params[] = $doctorFilter; 
}
if ($dateFrom) { 
    $query .= " AND DATE(a.dateTime) >= ?"; 
    $params[] = $dateFrom; 
}
if ($dateTo) { 
    $query .= " AND DATE(a.dateTime) <= ?"; 
    $params[] = $dateTo; 
}

$query .= " ORDER BY a.dateTime DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get doctors for filter
$doctors = $pdo->query("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name 
    FROM doctors d JOIN staff s ON d.staffId = s.staffId JOIN users u ON s.userId = u.userId
    ORDER BY u.firstName
")->fetchAll();

// Statistics
$totalAppointments = $pdo->query("
    SELECT COUNT(*) FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId 
    WHERE u.role = 'patient'
")->fetchColumn();

$scheduledAppointments = $pdo->query("
    SELECT COUNT(*) FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId 
    WHERE u.role = 'patient' AND a.status = 'scheduled'
")->fetchColumn();

$confirmedAppointments = $pdo->query("
    SELECT COUNT(*) FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId 
    WHERE u.role = 'patient' AND a.status = 'confirmed'
")->fetchColumn();

$completedAppointments = $pdo->query("
    SELECT COUNT(*) FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId 
    WHERE u.role = 'patient' AND a.status = 'completed'
")->fetchColumn();

$cancelledAppointments = $pdo->query("
    SELECT COUNT(*) FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId 
    WHERE u.role = 'patient' AND a.status = 'cancelled'
")->fetchColumn();

$todayAppointments = $pdo->query("
    SELECT COUNT(*) FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId 
    WHERE u.role = 'patient' AND DATE(a.dateTime) = CURDATE()
")->fetchColumn();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-alt"></i> Manage Appointments</h1>
            <p>View and manage all appointments</p>
        </div>
        <div class="header-actions">
            <a href="book-appointment.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-plus"></i> Book Appointment
            </a>
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

    <div class="admin-stats-grid">
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalAppointments; ?></h3>
                <p>Total</p>
            </div>
        </div>
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $scheduledAppointments; ?></h3>
                <p>Scheduled</p>
            </div>
        </div>
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $confirmedAppointments; ?></h3>
                <p>Confirmed</p>
            </div>
        </div>
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $completedAppointments; ?></h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $cancelledAppointments; ?></h3>
                <p>Cancelled</p>
            </div>
        </div>
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $todayAppointments; ?></h3>
                <p>Today</p>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-filter"></i> Filter Appointments</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="admin-filter-form">
                <div class="admin-filter-row">
                    <div class="admin-filter-group">
                        <label>Status</label>
                        <select name="status" class="admin-form-control">
                            <option value="">All Status</option>
                            <option value="scheduled" <?php echo $statusFilter=='scheduled'?'selected':''; ?>>Scheduled</option>
                            <option value="confirmed" <?php echo $statusFilter=='confirmed'?'selected':''; ?>>Confirmed</option>
                            <option value="in-progress" <?php echo $statusFilter=='in-progress'?'selected':''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter=='completed'?'selected':''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $statusFilter=='cancelled'?'selected':''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="admin-filter-group">
                        <label>Doctor</label>
                        <select name="doctor" class="admin-form-control">
                            <option value="">All Doctors</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?php echo $doc['doctorId']; ?>" <?php echo $doctorFilter==$doc['doctorId']?'selected':''; ?>>Dr. <?php echo $doc['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" class="admin-form-control">
                    </div>
                    <div class="admin-filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $dateTo; ?>" class="admin-form-control">
                    </div>
                    <div class="admin-filter-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">Filter</button>
                        <a href="appointments.php" class="admin-btn admin-btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Appointments (<?php echo count($appointments); ?>)</h3>
        </div>
        <div class="admin-table-responsive">
            <?php if (empty($appointments)): ?>
                <div class="admin-empty-message">No appointments found.</div>
            <?php else: ?>
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td data-label="ID">#<?php echo $a['appointmentId']; ?></td>
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($a['dateTime'])); ?></td>
                                <td data-label="Patient">
                                    <?php echo htmlspecialchars($a['patientName']); ?><br>
                                    <small><?php echo htmlspecialchars($a['patientPhone']); ?></small>
                                </td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($a['doctorName']); ?><br><small><?php echo htmlspecialchars($a['specialization']); ?></small></td>
                                <td data-label="Status">
                                    <span class="admin-status-badge admin-status-<?php echo $a['status']; ?>">
                                        <?php echo ucfirst($a['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="admin-action-buttons">
                                        <?php if ($a['status'] == 'completed'): ?>
                                            <a href="view-consultation.php?appointment_id=<?php echo $a['appointmentId']; ?>" class="admin-btn admin-btn-info admin-btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($a['status'] == 'scheduled' || $a['status'] == 'confirmed'): ?>
                                            <a href="reschedule-appointment.php?id=<?php echo $a['appointmentId']; ?>" class="admin-btn admin-btn-warning admin-btn-sm">
                                                <i class="fas fa-calendar-alt"></i> Reschedule
                                            </a>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $a['appointmentId']; ?>">
                                            <select name="status" onchange="if(confirm('Change status?')) this.form.submit()" class="admin-btn admin-btn-sm" style="padding: 5px 10px;">
                                                <option value="">Change</option>
                                                <option value="scheduled" <?php echo $a['status']=='scheduled'?'selected':''; ?>>Scheduled</option>
                                                <option value="confirmed" <?php echo $a['status']=='confirmed'?'selected':''; ?>>Confirmed</option>
                                                <option value="in-progress" <?php echo $a['status']=='in-progress'?'selected':''; ?>>In Progress</option>
                                                <option value="completed" <?php echo $a['status']=='completed'?'selected':''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $a['status']=='cancelled'?'selected':''; ?>>Cancelled</option>
                                                <option value="no-show" <?php echo $a['status']=='no-show'?'selected':''; ?>>No Show</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        
                                        <a href="?cancel=<?php echo $a['appointmentId']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                                        <a href="?delete=<?php echo $a['appointmentId']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Delete permanently?')">Delete</a>
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

<style>
.admin-action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    align-items: center;
}
.admin-btn-warning {
    background: #f59e0b;
    color: white;
}
.admin-btn-warning:hover {
    background: #d97706;
}
.admin-empty-message {
    text-align: center;
    padding: 40px;
    color: #64748b;
    font-size: 15px;
}
</style>

<?php include '../includes/footer.php'; ?>