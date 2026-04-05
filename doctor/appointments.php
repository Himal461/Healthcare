<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "My Schedule - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.specialization 
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();
$doctorId = $doctor['doctorId'];

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointmentId = $_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = sanitizeInput($_POST['notes']);

    try {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = ?, notes = ? 
            WHERE appointmentId = ? AND doctorId = ?
        ");
        $stmt->execute([$status, $notes, $appointmentId, $doctorId]);
        
        // Get patient info for notification
        $patientStmt = $pdo->prepare("
            SELECT p.patientId, u.userId, u.email, u.firstName, u.lastName
            FROM appointments a
            JOIN patients p ON a.patientId = p.patientId
            JOIN users u ON p.userId = u.userId
            WHERE a.appointmentId = ?
        ");
        $patientStmt->execute([$appointmentId]);
        $patient = $patientStmt->fetch();
        
        if ($patient) {
            createNotification(
                $patient['userId'],
                'appointment',
                'Appointment Status Updated',
                "Your appointment status has been updated to: " . ucfirst($status)
            );
        }
        
        $_SESSION['success'] = "Appointment updated successfully!";
        logAction($userId, 'APPOINTMENT_UPDATE', "Updated appointment $appointmentId status to $status");
        
        header("Location: appointments.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to update appointment. Please try again.";
    }
}

// Handle availability settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_availability'])) {
    try {
        // Clear existing availability
        $stmt = $pdo->prepare("DELETE FROM doctor_availability WHERE doctorId = ?");
        $stmt->execute([$doctorId]);
        
        // Insert new availability
        for ($i = 0; $i <= 6; $i++) {
            $isAvailable = isset($_POST["day_{$i}_available"]);
            if ($isAvailable) {
                $startTime = $_POST["day_{$i}_start"];
                $endTime = $_POST["day_{$i}_end"];
                
                $stmt = $pdo->prepare("
                    INSERT INTO doctor_availability (doctorId, dayOfWeek, startTime, endTime, isAvailable) 
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$doctorId, $i, $startTime, $endTime]);
            }
        }
        
        $_SESSION['success'] = "Availability updated successfully!";
        logAction($userId, 'AVAILABILITY_UPDATE', "Updated availability schedule");
        
        header("Location: appointments.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to update availability. Please try again.";
    }
}

// Get current availability
$availabilityStmt = $pdo->prepare("
    SELECT * FROM doctor_availability 
    WHERE doctorId = ? AND isAvailable = 1
");
$availabilityStmt->execute([$doctorId]);
$availability = $availabilityStmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
$availabilityByDay = [];
foreach ($availability as $day => $slots) {
    $availabilityByDay[$day] = $slots[0];
}

// Get today's date
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get appointments for selected date
$appointments = getDoctorSchedule($doctorId, $selectedDate);

// Get upcoming appointments
$upcomingStmt = $pdo->prepare("
    SELECT a.*, u.firstName, u.lastName, u.phoneNumber, p.dateOfBirth, p.bloodType
    FROM appointments a 
    JOIN patients p ON a.patientId = p.patientId 
    JOIN users u ON p.userId = u.userId 
    WHERE a.doctorId = ? AND a.dateTime > NOW() AND a.status = 'scheduled'
    ORDER BY a.dateTime 
    LIMIT 20
");
$upcomingStmt->execute([$doctorId]);
$upcomingAppointments = $upcomingStmt->fetchAll();

// Get statistics
$todayCount = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctorId = ? AND DATE(dateTime) = CURDATE() 
    AND status NOT IN ('cancelled', 'no-show')
");
$todayCount->execute([$doctorId]);
$todayAppointments = $todayCount->fetch()['count'];

$totalPatients = $pdo->prepare("
    SELECT COUNT(DISTINCT patientId) as count 
    FROM appointments 
    WHERE doctorId = ?
");
$totalPatients->execute([$doctorId]);
$totalPatientsCount = $totalPatients->fetch()['count'];
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>My Schedule</h1>
        <p>Manage your appointments and availability</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card doctor">
            <h3><?php echo $todayAppointments; ?></h3>
            <p>Today's Appointments</p>
        </div>
        <div class="stat-card doctor">
            <h3><?php echo count($upcomingAppointments); ?></h3>
            <p>Upcoming Appointments</p>
        </div>
        <div class="stat-card doctor">
            <h3><?php echo $totalPatientsCount; ?></h3>
            <p>Total Patients</p>
        </div>
        <div class="stat-card doctor">
            <h3><?php echo $doctor['specialization']; ?></h3>
            <p>Specialization</p>
        </div>
    </div>

    <!-- Date Navigation -->
    <div class="card">
        <div class="card-header">
            <h3>Schedule for <?php echo date('l, F j, Y', strtotime($selectedDate)); ?></h3>
        </div>
        <div class="card-body">
            <div class="date-navigation">
                <a href="?date=<?php echo date('Y-m-d', strtotime($selectedDate . ' -1 day')); ?>" class="btn btn-outline">
                    <i class="fas fa-chevron-left"></i> Previous Day
                </a>
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-primary">Today</a>
                <a href="?date=<?php echo date('Y-m-d', strtotime($selectedDate . ' +1 day')); ?>" class="btn btn-outline">
                    Next Day <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Age</th>
                            <th>Phone</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                        汽
                            <td colspan="7" style="text-align: center;">No appointments scheduled for this day</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appointment): 
                                $age = $appointment['dateOfBirth'] ? date_diff(date_create($appointment['dateOfBirth']), date_create('today'))->y : 'N/A';
                            ?>
                            <tr>
                                <td data-label="Time"><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></td>
                                <td data-label="Patient"><?php echo $appointment['firstName'] . ' ' . $appointment['lastName']; ?></td>
                                <td data-label="Age"><?php echo $age; ?></td>
                                <td data-label="Phone"><?php echo $appointment['phoneNumber']; ?></td>
                                <td data-label="Reason"><?php echo $appointment['reason'] ?: 'Not specified'; ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <button class="btn btn-primary btn-sm" onclick="openModal('updateModal'); document.getElementById('modal_appointment_id').value = <?php echo $appointment['appointmentId']; ?>; document.getElementById('modal_status').value = '<?php echo $appointment['status']; ?>'; document.getElementById('modal_notes').value = `<?php echo addslashes($appointment['notes']); ?>`;">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header">
            <h3>Upcoming Appointments</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Age</th>
                        <th>Blood Type</th>
                        <th>Contact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingAppointments)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No upcoming appointments</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $appointment): 
                            $age = $appointment['dateOfBirth'] ? date_diff(date_create($appointment['dateOfBirth']), date_create('today'))->y : 'N/A';
                        ?>
                        <tr>
                            <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                            <td data-label="Patient"><?php echo $appointment['firstName'] . ' ' . $appointment['lastName']; ?></td>
                            <td data-label="Age"><?php echo $age; ?></td>
                            <td data-label="Blood Type"><?php echo $appointment['bloodType'] ?: 'Unknown'; ?></td>
                            <td data-label="Contact"><?php echo $appointment['phoneNumber']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Availability Settings -->
    <div class="card">
        <div class="card-header">
            <h3>Set Availability</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="availability-grid">
                    <?php
                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach ($days as $dayIndex => $dayName):
                        $currentAvailability = $availabilityByDay[$dayIndex] ?? null;
                    ?>
                        <div class="availability-day">
                            <label class="day-checkbox-label">
                                <input type="checkbox" name="day_<?php echo $dayIndex; ?>_available" 
                                       <?php echo $currentAvailability ? 'checked' : ''; ?>>
                                <strong><?php echo $dayName; ?></strong>
                            </label>
                            <div class="availability-times" style="<?php echo !$currentAvailability ? 'display: none;' : ''; ?>">
                                <div class="time-select">
                                    <label>Start:</label>
                                    <input type="time" name="day_<?php echo $dayIndex; ?>_start" 
                                           value="<?php echo $currentAvailability['startTime'] ?? WORKING_HOURS_START; ?>">
                                </div>
                                <div class="time-select">
                                    <label>End:</label>
                                    <input type="time" name="day_<?php echo $dayIndex; ?>_end" 
                                           value="<?php echo $currentAvailability['endTime'] ?? WORKING_HOURS_END; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="update_availability" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Availability
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Update Appointment Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Appointment</h3>
            <span class="close" onclick="closeModal('updateModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <div class="form-group">
                    <label for="modal_status">Status</label>
                    <select id="modal_status" name="status" class="form-control" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no-show">No Show</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modal_notes">Consultation Notes</label>
                    <textarea id="modal_notes" name="notes" rows="5" placeholder="Add consultation notes, diagnosis, treatment plan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('updateModal')">Cancel</button>
                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
// Show/hide availability times based on checkbox
document.querySelectorAll('input[type="checkbox"][name*="_available"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const dayDiv = this.closest('.availability-day');
        const timesDiv = dayDiv.querySelector('.availability-times');
        if (timesDiv) {
            timesDiv.style.display = this.checked ? 'block' : 'none';
        }
    });
});
</script>

<style>
.date-navigation {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
</style>

<?php include '../includes/footer.php'; ?>