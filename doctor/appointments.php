<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "My Schedule - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.specialization, CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: ../login.php");
    exit();
}

$doctorId = $doctor['doctorId'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointmentId = (int)$_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    $validStatuses = ['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled', 'no-show'];
    if (in_array($status, $validStatuses)) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] Status changed to: ', ?, ' - ', ?), updatedAt = NOW() WHERE appointmentId = ? AND doctorId = ?");
        $stmt->execute([$status, $status, $notes, $appointmentId, $doctorId]);
        
        if ($stmt->rowCount() > 0) {
            if ($status === 'cancelled') {
                sendAppointmentCancellationEmail($appointmentId, 'Cancelled by doctor');
            }
            
            $patientStmt = $pdo->prepare("
                SELECT u.userId FROM appointments a 
                JOIN patients p ON a.patientId = p.patientId 
                JOIN users u ON p.userId = u.userId 
                WHERE a.appointmentId = ?
            ");
            $patientStmt->execute([$appointmentId]);
            $patient = $patientStmt->fetch();
            if ($patient) {
                createNotification($patient['userId'], 'appointment', 'Appointment Updated',
                    "Your appointment status has been updated to: " . ucfirst($status));
            }
            
            $_SESSION['success'] = "Appointment status updated successfully!";
            logAction($userId, 'UPDATE_APPOINTMENT_STATUS', "Updated appointment $appointmentId to $status");
        }
    }
    header("Location: appointments.php?date=" . $_POST['redirect_date']);
    exit();
}

// Get selected date
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get appointments for selected date
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber as patientPhone,
           u.email as patientEmail,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies,
           TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) as age,
           p.patientId,
           a.appointmentId
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.doctorId = ? AND DATE(a.dateTime) = ? 
    AND a.status NOT IN ('cancelled', 'no-show')
    AND u.role = 'patient'
    ORDER BY a.dateTime ASC
");
$stmt->execute([$doctorId, $selectedDate]);
$appointments = $stmt->fetchAll();

// Get upcoming appointments (next 7 days)
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber as patientPhone,
           p.dateOfBirth,
           p.bloodType,
           TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) as age,
           p.patientId,
           a.appointmentId
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.doctorId = ? 
    AND DATE(a.dateTime) > CURDATE() 
    AND DATE(a.dateTime) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND a.status IN ('scheduled', 'confirmed')
    AND u.role = 'patient'
    ORDER BY a.dateTime ASC
    LIMIT 10
");
$stmt->execute([$doctorId]);
$upcomingAppointments = $stmt->fetchAll();

// Get today's availability
$todayAvailability = null;
$stmt = $pdo->prepare("
    SELECT * FROM doctor_availability 
    WHERE doctorId = ? AND availabilityDate = ?
");
$stmt->execute([$doctorId, $selectedDate]);
$result = $stmt->fetch();
if ($result && is_array($result)) {
    $todayAvailability = $result;
}

// Get all future availability for calendar display
$stmt = $pdo->prepare("
    SELECT availabilityDate, isAvailable, isDayOff, startTime, endTime
    FROM doctor_availability 
    WHERE doctorId = ? AND availabilityDate >= CURDATE()
    ORDER BY availabilityDate
    LIMIT 60
");
$stmt->execute([$doctorId]);
$futureAvailability = $stmt->fetchAll();

// Statistics
$todayCount = count($appointments);
$upcomingCount = count($upcomingAppointments);

$totalPatients = $pdo->prepare("
    SELECT COUNT(DISTINCT patientId) FROM appointments WHERE doctorId = ?
");
$totalPatients->execute([$doctorId]);
$totalPatientsCount = $totalPatients->fetchColumn();

$totalAppointments = $pdo->prepare("
    SELECT COUNT(*) FROM appointments WHERE doctorId = ?
");
$totalAppointments->execute([$doctorId]);
$totalAppointmentsCount = $totalAppointments->fetchColumn();

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-alt"></i> My Schedule</h1>
            <p>Manage your appointments and view your daily schedule</p>
        </div>
        <div class="header-actions">
            <a href="availability.php" class="doctor-btn doctor-btn-primary">
                <i class="fas fa-clock"></i> Set Availability
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="doctor-stats-grid">
        <div class="doctor-stat-card today">
            <div class="doctor-stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $todayCount; ?></h3>
                <p>Today's Appointments</p>
                <small><?php echo date('l, F j'); ?></small>
            </div>
        </div>
        <div class="doctor-stat-card upcoming">
            <div class="doctor-stat-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $upcomingCount; ?></h3>
                <p>Upcoming (7 days)</p>
            </div>
        </div>
        <div class="doctor-stat-card patients">
            <div class="doctor-stat-icon"><i class="fas fa-users"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $totalPatientsCount; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        <div class="doctor-stat-card appointments">
            <div class="doctor-stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $totalAppointmentsCount; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
    </div>

    <!-- Date Navigation -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-calendar"></i> Schedule for <?php echo date('l, F j, Y', strtotime($selectedDate)); ?></h3>
        </div>
        <div class="doctor-card-body">
            <div class="doctor-date-navigation">
                <a href="?date=<?php echo date('Y-m-d', strtotime($selectedDate . ' -1 day')); ?>" class="doctor-btn doctor-btn-outline">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="doctor-btn doctor-btn-primary">Today</a>
                <a href="?date=<?php echo date('Y-m-d', strtotime($selectedDate . ' +1 day')); ?>" class="doctor-btn doctor-btn-outline">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <input type="date" id="datePicker" class="doctor-form-control" style="width: auto; margin-left: auto;" 
                       value="<?php echo $selectedDate; ?>" min="<?php echo date('Y-m-d'); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                       onchange="window.location.href='?date=' + this.value">
            </div>
            
            <!-- Today's Availability Status -->
            <div style="margin-top: 20px; padding: 15px; background: <?php echo isset($todayAvailability) ? ($todayAvailability['isDayOff'] ? '#fee2e2' : ($todayAvailability['isAvailable'] ? '#dcfce7' : '#fef3c7')) : '#fef3c7'; ?>; border-radius: 12px;">
                <?php if ($todayAvailability): ?>
                    <?php if ($todayAvailability['isDayOff']): ?>
                        <i class="fas fa-calendar-times" style="color: #ef4444;"></i>
                        <strong style="color: #991b1b;">Day Off</strong>
                        <span style="color: #7f1d1d;">- Not available for appointments</span>
                    <?php elseif ($todayAvailability['isAvailable']): ?>
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        <strong style="color: #166534;">Available</strong>
                        <span style="color: #14532d;">
                            Working hours: <?php echo date('g:i A', strtotime($todayAvailability['startTime'])); ?> - 
                            <?php echo date('g:i A', strtotime($todayAvailability['endTime'])); ?>
                        </span>
                    <?php else: ?>
                        <i class="fas fa-ban" style="color: #f59e0b;"></i>
                        <strong style="color: #92400e;">Unavailable</strong>
                    <?php endif; ?>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>
                    <strong style="color: #92400e;">No availability set for this date</strong>
                    <a href="availability.php" class="doctor-btn doctor-btn-outline doctor-btn-sm" style="margin-left: 15px;">Set Availability</a>
                <?php endif; ?>
            </div>
            
            <!-- Appointments Table -->
            <h4 style="margin: 25px 0 15px; color: #1e293b;">
                <i class="fas fa-list"></i> Appointments
            </h4>
            <div class="doctor-table-responsive">
                <?php if (empty($appointments)): ?>
                    <div class="doctor-empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No appointments scheduled for this day.</p>
                    </div>
                <?php else: ?>
                    <table class="doctor-data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Age</th>
                                <th>Phone</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $a): ?>
                                <tr>
                                    <td data-label="Time">
                                        <strong><?php echo date('g:i A', strtotime($a['dateTime'])); ?></strong>
                                    </td>
                                    <td data-label="Patient">
                                        <strong><?php echo htmlspecialchars($a['patientName']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($a['patientEmail']); ?></small>
                                    </td>
                                    <td data-label="Age"><?php echo $a['age']; ?></td>
                                    <td data-label="Phone"><?php echo htmlspecialchars($a['patientPhone']); ?></td>
                                    <td data-label="Reason"><?php echo htmlspecialchars($a['reason'] ?: '-'); ?></td>
                                    <td data-label="Status">
                                        <span class="doctor-status-badge doctor-status-<?php echo $a['status']; ?>">
                                            <?php echo ucfirst($a['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
    <div class="doctor-action-buttons">
        <?php if ($a['status'] == 'completed'): ?>
            <a href="view-consultation.php?appointment_id=<?php echo $a['appointmentId']; ?>" class="doctor-btn doctor-btn-info doctor-btn-sm">
                <i class="fas fa-eye"></i> View
            </a>
        <?php elseif ($a['status'] == 'scheduled' || $a['status'] == 'confirmed'): ?>
            <?php
            // Check if this is a certificate appointment
            $certCheck = $pdo->prepare("
                SELECT certificate_id FROM medical_certificates 
                WHERE appointment_id = ? AND approval_status = 'pending_consultation'
            ");
            $certCheck->execute([$a['appointmentId']]);
            $hasCertificate = $certCheck->fetch();
            
            if ($hasCertificate) {
                $startLink = "certificate-consultation.php?certificate_id=" . $hasCertificate['certificate_id'];
                $btnLabel = '<i class="fas fa-file-medical"></i> Cert Consultation';
                $btnClass = 'doctor-btn doctor-btn-warning doctor-btn-sm';
            } else {
                $startLink = "consultation.php?appointment_id=" . $a['appointmentId'] . "&patient_id=" . $a['patientId'];
                $btnLabel = '<i class="fas fa-stethoscope"></i> Start';
                $btnClass = 'doctor-btn doctor-btn-primary doctor-btn-sm';
            }
            ?>
            <a href="<?php echo $startLink; ?>" class="<?php echo $btnClass; ?>">
                <?php echo $btnLabel; ?>
            </a>
        <?php elseif ($a['status'] == 'cancelled'): ?>
            <span class="doctor-text-muted">Cancelled</span>
        <?php elseif ($a['status'] == 'no-show'): ?>
            <span class="doctor-text-muted">No Show</span>
        <?php endif; ?>
        
        <?php if ($a['status'] != 'cancelled' && $a['status'] != 'completed'): ?>
            <button class="doctor-btn doctor-btn-outline doctor-btn-sm" onclick="openStatusModal(<?php echo $a['appointmentId']; ?>, '<?php echo $a['status']; ?>', '<?php echo $selectedDate; ?>')">
                <i class="fas fa-edit"></i> Status
            </button>
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
    </div>

    <!-- Upcoming Appointments -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments (Next 7 Days)</h3>
        </div>
        <div class="doctor-card-body">
            <?php if (empty($upcomingAppointments)): ?>
                <div class="doctor-empty-state">
                    <i class="fas fa-calendar-week"></i>
                    <p>No upcoming appointments in the next 7 days.</p>
                </div>
            <?php else: ?>
                <div class="doctor-table-responsive">
                    <table class="doctor-data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient</th>
                                <th>Age</th>
                                <th>Phone</th>
                                <th>Blood Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingAppointments as $a): ?>
                                <tr>
                                    <td data-label="Date & Time">
                                        <strong><?php echo date('M j, Y', strtotime($a['dateTime'])); ?></strong><br>
                                        <small><?php echo date('g:i A', strtotime($a['dateTime'])); ?></small>
                                    </td>
                                    <td data-label="Patient">
                                        <strong><?php echo htmlspecialchars($a['patientName']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($a['patientPhone']); ?></small>
                                    </td>
                                    <td data-label="Age"><?php echo $a['age']; ?></td>
                                    <td data-label="Phone"><?php echo htmlspecialchars($a['patientPhone']); ?></td>
                                    <td data-label="Blood Type"><?php echo $a['bloodType'] ?: 'N/A'; ?></td>
                                    <td data-label="Actions">
                                        <a href="consultation.php?appointment_id=<?php echo $a['appointmentId']; ?>&patient_id=<?php echo $a['patientId']; ?>" class="doctor-btn doctor-btn-primary doctor-btn-sm">
                                            <i class="fas fa-stethoscope"></i> Start
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

    <!-- Quick Availability Overview -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-calendar-check"></i> Your Availability Overview</h3>
            <a href="availability.php" class="doctor-view-all">Manage Availability <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="doctor-card-body">
            <?php if (empty($futureAvailability)): ?>
                <div class="doctor-empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No availability set for upcoming dates.</p>
                    <a href="availability.php" class="doctor-btn doctor-btn-primary">Set Your Schedule</a>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; max-height: 300px; overflow-y: auto;">
                    <?php 
                    $shownDates = 0;
                    foreach ($futureAvailability as $avail): 
                        if ($shownDates >= 30) break;
                        $shownDates++;
                        $date = $avail['availabilityDate'];
                        $isToday = ($date == date('Y-m-d'));
                        
                        $isDayOff = isset($avail['isDayOff']) && $avail['isDayOff'] == 1;
                        $isAvailable = isset($avail['isAvailable']) && $avail['isAvailable'] == 1;
                        $startTime = $avail['startTime'] ?? '09:00:00';
                        $endTime = $avail['endTime'] ?? '17:00:00';
                        
                        $bgColor = $isDayOff ? '#fee2e2' : '#dcfce7';
                        $borderColor = $isDayOff ? '#ef4444' : '#10b981';
                    ?>
                        <div style="padding: 10px; background: <?php echo $bgColor; ?>; border-radius: 8px; border-left: 3px solid <?php echo $borderColor; ?>; <?php echo $isToday ? 'box-shadow: 0 0 0 2px #2563eb;' : ''; ?>">
                            <strong><?php echo date('D, M j', strtotime($date)); ?></strong>
                            <?php if ($isDayOff): ?>
                                <span style="color: #ef4444; display: block; font-size: 12px;">Day Off</span>
                            <?php elseif ($isAvailable): ?>
                                <span style="color: #10b981; display: block; font-size: 12px;">
                                    <?php echo date('g:i A', strtotime($startTime)); ?> - 
                                    <?php echo date('g:i A', strtotime($endTime)); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #f59e0b; display: block; font-size: 12px;">Unavailable</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($futureAvailability) > 30): ?>
                    <p class="doctor-text-muted" style="margin-top: 10px;">Showing 30 of <?php echo count($futureAvailability); ?> scheduled days.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="doctor-modal">
    <div class="doctor-modal-content">
        <div class="doctor-modal-header">
            <h3>Update Appointment Status</h3>
            <span class="doctor-modal-close" onclick="closeModal('statusModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="doctor-modal-body">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                <input type="hidden" name="redirect_date" id="redirect_date" value="<?php echo $selectedDate; ?>">
                <div class="doctor-form-group">
                    <label>Status</label>
                    <select name="status" id="modal_status" class="doctor-form-control" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no-show">No Show</option>
                    </select>
                </div>
                <div class="doctor-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="doctor-form-control" placeholder="Add notes about status change..."></textarea>
                </div>
            </div>
            <div class="doctor-modal-footer">
                <button type="submit" name="update_status" class="doctor-btn doctor-btn-primary">Update Status</button>
                <button type="button" class="doctor-btn doctor-btn-outline" onclick="closeModal('statusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.doctor-action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    align-items: center;
}
.doctor-text-muted {
    color: #94a3b8;
    font-size: 13px;
    font-style: italic;
}
</style>

<script>
function openStatusModal(appointmentId, currentStatus, redirectDate) {
    document.getElementById('modal_appointment_id').value = appointmentId;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('redirect_date').value = redirectDate;
    openModal('statusModal');
}

document.getElementById('datePicker')?.addEventListener('change', function() {
    window.location.href = '?date=' + this.value;
});
</script>

<?php include '../includes/footer.php'; ?>