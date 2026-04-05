<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Appointments - HealthManagement";
include '../includes/header.php';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $appointmentId = $_POST['appointment_id'];
        $status = $_POST['status'];
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = ? WHERE appointmentId = ?");
            $stmt->execute([$status, $notes, $appointmentId]);
            
            $_SESSION['success'] = "Appointment status updated successfully!";
            logAction($_SESSION['user_id'], 'UPDATE_APPOINTMENT', "Updated appointment ID: $appointmentId to status: $status");
            
            header("Location: appointments.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to update appointment. Please try again.";
        }
    } elseif (isset($_POST['reschedule'])) {
        $appointmentId = $_POST['appointment_id'];
        $newDate = $_POST['new_date'];
        $newTime = $_POST['new_time'];
        $newDateTime = $newDate . ' ' . $newTime;
        
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET dateTime = ? WHERE appointmentId = ?");
            $stmt->execute([$newDateTime, $appointmentId]);
            
            $_SESSION['success'] = "Appointment rescheduled successfully!";
            logAction($_SESSION['user_id'], 'RESCHEDULE_APPOINTMENT', "Rescheduled appointment ID: $appointmentId to: $newDateTime");
            
            header("Location: appointments.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to reschedule appointment. Please try again.";
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $appointmentId = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE appointmentId = ?");
        $stmt->execute([$appointmentId]);
        
        $_SESSION['success'] = "Appointment deleted successfully!";
        logAction($_SESSION['user_id'], 'DELETE_APPOINTMENT', "Deleted appointment ID: $appointmentId");
        
        header("Location: appointments.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to delete appointment.";
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$doctorFilter = $_GET['doctor'] ?? '';
$patientFilter = $_GET['patient'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$query = "
    SELECT a.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           pu.email as patientEmail,
           pu.phoneNumber as patientPhone,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           d.consultationFee
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE 1=1
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

if ($patientFilter) {
    $query .= " AND pu.userId LIKE ?";
    $params[] = "%$patientFilter%";
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

// Get statistics
$totalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments")->fetch()['count'];
$scheduledAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'")->fetch()['count'];
$completedAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")->fetch()['count'];
$cancelledAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")->fetch()['count'];
$todayAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(dateTime) = CURDATE()")->fetch()['count'];
$upcomingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE dateTime > NOW() AND status = 'scheduled'")->fetch()['count'];

// Get doctors for filter
$doctors = $pdo->query("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name 
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId
")->fetchAll();

// Get statuses for filter
$statuses = ['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled', 'no-show'];
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Appointments</h1>
        <p>View and manage all appointments in the system</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card admin">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $scheduledAppointments; ?></h3>
            <p>Scheduled</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $completedAppointments; ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $cancelledAppointments; ?></h3>
            <p>Cancelled</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $todayAppointments; ?></h3>
            <p>Today's Appointments</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $upcomingAppointments; ?></h3>
            <p>Upcoming</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>Filter Appointments</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $statusFilter == $s ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="doctor">Doctor</label>
                        <select id="doctor" name="doctor">
                            <option value="">All Doctors</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['doctorId']; ?>" <?php echo $doctorFilter == $doctor['doctorId'] ? 'selected' : ''; ?>>
                                    Dr. <?php echo $doctor['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="patient">Patient (Name/Email)</label>
                        <input type="text" id="patient" name="patient" value="<?php echo htmlspecialchars($patientFilter); ?>" placeholder="Search patient...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="appointments.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Appointments Table -->
    <div class="card">
        <div class="card-header">
            <h3>All Appointments</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No appointments found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr class="status-<?php echo $appointment['status']; ?>">
                                <td data-label="ID">#<?php echo $appointment['appointmentId']; ?></td>
                                <td data-label="Date & Time">
                                    <?php echo date('M j, Y', strtotime($appointment['dateTime'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></small>
                                </td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($appointment['patientName']); ?></strong><br>
                                    <small><?php echo $appointment['patientEmail']; ?></small><br>
                                    <small><?php echo $appointment['patientPhone']; ?></small>
                                </td>
                                <td data-label="Doctor">
                                    <strong>Dr. <?php echo $appointment['doctorName']; ?></strong><br>
                                    <small><?php echo $appointment['specialization']; ?></small>
                                </td>
                                <td data-label="Specialization"><?php echo $appointment['specialization']; ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Reason">
                                    <?php echo $appointment['reason'] ?: '-'; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-sm" onclick="openModal('statusModal'); document.getElementById('status_appointment_id').value = <?php echo $appointment['appointmentId']; ?>; document.getElementById('status').value = '<?php echo $appointment['status']; ?>'; document.getElementById('notes').value = `<?php echo addslashes($appointment['notes']); ?>`;">
                                            <i class="fas fa-edit"></i> Status
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="openModal('rescheduleModal'); document.getElementById('reschedule_appointment_id').value = <?php echo $appointment['appointmentId']; ?>; document.getElementById('new_date').value = '<?php echo date('Y-m-d', strtotime($appointment['dateTime'])); ?>';">
                                            <i class="fas fa-calendar-alt"></i> Reschedule
                                        </button>
                                        <a href="?delete=<?php echo $appointment['appointmentId']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this appointment permanently?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Appointment Status</h3>
            <span class="close" onclick="closeModal('statusModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="appointment_id" id="status_appointment_id">
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no-show">No Show</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Add consultation notes, diagnosis, etc..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                <button type="button" class="btn" onclick="closeModal('statusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reschedule Appointment</h3>
            <span class="close" onclick="closeModal('rescheduleModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                
                <div class="form-group">
                    <label for="new_date">New Date</label>
                    <input type="date" id="new_date" name="new_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="new_time">New Time</label>
                    <select id="new_time" name="new_time" required>
                        <option value="">Select Time</option>
                        <option value="09:00:00">9:00 AM</option>
                        <option value="09:30:00">9:30 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="10:30:00">10:30 AM</option>
                        <option value="11:00:00">11:00 AM</option>
                        <option value="11:30:00">11:30 AM</option>
                        <option value="12:00:00">12:00 PM</option>
                        <option value="12:30:00">12:30 PM</option>
                        <option value="14:00:00">2:00 PM</option>
                        <option value="14:30:00">2:30 PM</option>
                        <option value="15:00:00">3:00 PM</option>
                        <option value="15:30:00">3:30 PM</option>
                        <option value="16:00:00">4:00 PM</option>
                        <option value="16:30:00">4:30 PM</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="reschedule" class="btn btn-primary">Reschedule</button>
                <button type="button" class="btn" onclick="closeModal('rescheduleModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>