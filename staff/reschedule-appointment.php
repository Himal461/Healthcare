<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Manage Appointments - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$step = $_GET['step'] ?? 'find'; // find, select, reschedule
$patientId = (int)($_GET['patient_id'] ?? 0);
$appointmentId = (int)($_GET['id'] ?? 0);
$searchTerm = $_GET['search'] ?? '';
$action = $_GET['action'] ?? '';

// Handle cancel appointment
if ($action === 'cancel' && $appointmentId) {
    // Verify appointment exists and is scheduled
    $checkStmt = $pdo->prepare("
        SELECT a.*, p.patientId, CONCAT(u.firstName, ' ', u.lastName) as patientName
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        WHERE a.appointmentId = ? AND a.status IN ('scheduled', 'confirmed')
    ");
    $checkStmt->execute([$appointmentId]);
    $appointment = $checkStmt->fetch();
    
    if ($appointment) {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', 
                cancellationReason = 'Cancelled by staff', 
                updatedAt = NOW() 
            WHERE appointmentId = ?
        ");
        $stmt->execute([$appointmentId]);
        
        if ($stmt->rowCount() > 0) {
            sendAppointmentCancellationEmail($appointmentId, 'Cancelled by reception staff');
            
            // Notify patient
            $patientStmt = $pdo->prepare("SELECT u.userId FROM patients p JOIN users u ON p.userId = u.userId WHERE p.patientId = ?");
            $patientStmt->execute([$appointment['patientId']]);
            $patient = $patientStmt->fetch();
            
            if ($patient) {
                createNotification(
                    $patient['userId'],
                    'appointment',
                    'Appointment Cancelled',
                    "Your appointment with Dr. {$appointment['doctorName']} on " . 
                    date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled by reception."
                );
            }
            
            // Notify doctor
            $doctorStmt = $pdo->prepare("SELECT s.userId FROM doctors d JOIN staff s ON d.staffId = s.staffId WHERE d.doctorId = ?");
            $doctorStmt->execute([$appointment['doctorId']]);
            $doctor = $doctorStmt->fetch();
            if ($doctor) {
                createNotification(
                    $doctor['userId'],
                    'appointment',
                    'Appointment Cancelled',
                    "Appointment with patient {$appointment['patientName']} on " . 
                    date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled by reception."
                );
            }
            
            $_SESSION['success'] = "Appointment cancelled successfully!";
            logAction($userId, 'CANCEL_APPOINTMENT_STAFF', "Staff cancelled appointment ID: $appointmentId");
        }
    }
    
    // Redirect back to the patient's appointments page
    if ($appointment) {
        header("Location: reschedule-appointment.php?step=select&patient_id=" . $appointment['patientId']);
    } else {
        header("Location: reschedule-appointment.php");
    }
    exit();
}

$searchResults = [];
$selectedPatient = null;
$patientAppointments = [];
$selectedAppointment = null;
$bookingError = null;
$bookingSuccess = false;

// Step 1: Find Patient
if ($step === 'find' && $searchTerm) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber, p.dateOfBirth, p.bloodType
        FROM patients p 
        JOIN users u ON p.userId = u.userId
        WHERE u.role = 'patient' 
        AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)
        ORDER BY u.firstName LIMIT 20
    ");
    $searchLike = "%$searchTerm%";
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike]);
    $searchResults = $stmt->fetchAll();
}

// Step 2: Select Patient - Show their scheduled appointments
if ($step === 'select' && $patientId) {
    // Get patient details
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.userId, u.firstName, u.lastName, u.email, u.phoneNumber,
               p.dateOfBirth, p.bloodType, p.knownAllergies
        FROM patients p 
        JOIN users u ON p.userId = u.userId 
        WHERE p.patientId = ? AND u.role = 'patient'
    ");
    $stmt->execute([$patientId]);
    $selectedPatient = $stmt->fetch();
    
    if ($selectedPatient) {
        // Get only SCHEDULED/CONFIRMED appointments for this patient
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   d.specialization,
                   d.consultationFee
            FROM appointments a
            JOIN doctors d ON a.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE a.patientId = ? 
            AND a.status IN ('scheduled', 'confirmed')
            AND a.dateTime > NOW()
            ORDER BY a.dateTime ASC
        ");
        $stmt->execute([$patientId]);
        $patientAppointments = $stmt->fetchAll();
    }
}

// Step 3: Reschedule selected appointment
if ($step === 'reschedule' && $appointmentId) {
    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
               p.patientId,
               pu.email as patientEmail,
               pu.phoneNumber as patientPhone,
               CONCAT(du.firstName, ' ', du.lastName) as doctorName,
               d.specialization,
               d.doctorId,
               d.consultationFee
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId
        JOIN users pu ON p.userId = pu.userId
        JOIN doctors d ON a.doctorId = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE a.appointmentId = ? 
        AND a.status IN ('scheduled', 'confirmed')
        AND pu.role = 'patient'
    ");
    $stmt->execute([$appointmentId]);
    $selectedAppointment = $stmt->fetch();
    
    if (!$selectedAppointment) {
        $_SESSION['error'] = "Appointment not found or cannot be rescheduled.";
        header("Location: reschedule-appointment.php");
        exit();
    }
    
    // Handle reschedule form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule'])) {
        $newDate = $_POST['appointment_date'];
        $newTime = $_POST['appointment_time'];
        $reason = sanitizeInput($_POST['reason'] ?? 'Rescheduled by staff');
        
        if (!$newDate || !$newTime) {
            $bookingError = "Please select a date and time.";
        } else {
            if (strlen($newTime) === 5) $newTime .= ':00';
            $newDateTime = date('Y-m-d H:i:s', strtotime("$newDate $newTime"));
            
            if ($newDateTime < date('Y-m-d H:i:s')) {
                $bookingError = "Cannot schedule appointments in the past.";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Check if new slot is available
                    $check = $pdo->prepare("
                        SELECT COUNT(*) FROM appointments 
                        WHERE doctorId = ? AND dateTime = ? 
                        AND appointmentId != ? 
                        AND status NOT IN ('cancelled','no-show')
                    ");
                    $check->execute([$selectedAppointment['doctorId'], $newDateTime, $appointmentId]);
                    if ($check->fetchColumn() > 0) {
                        throw new Exception("This time slot is already booked.");
                    }
                    
                    // Check doctor availability
                    $availStmt = $pdo->prepare("
                        SELECT isAvailable, isDayOff, startTime, endTime
                        FROM doctor_availability 
                        WHERE doctorId = ? AND availabilityDate = ?
                    ");
                    $availStmt->execute([$selectedAppointment['doctorId'], $newDate]);
                    $availability = $availStmt->fetch();
                    
                    if (!$availability) {
                        throw new Exception("Doctor has not set availability for this date.");
                    }
                    
                    if ($availability['isDayOff'] || !$availability['isAvailable']) {
                        throw new Exception("Doctor is not available on this date.");
                    }
                    
                    $bookingTime = date('H:i:s', strtotime($newTime));
                    if ($bookingTime < $availability['startTime'] || $bookingTime > $availability['endTime']) {
                        throw new Exception("Selected time is outside doctor's working hours.");
                    }
                    
                    // Update appointment
                    $oldDateTime = $selectedAppointment['dateTime'];
                    $stmt = $pdo->prepare("
                        UPDATE appointments 
                        SET dateTime = ?, 
                            reason = CONCAT(IFNULL(reason, ''), '\n[RESCHEDULED by staff: ', NOW(), '] ', ?), 
                            updatedAt = NOW() 
                        WHERE appointmentId = ?
                    ");
                    $stmt->execute([$newDateTime, $reason, $appointmentId]);
                    
                    // Notify patient
                    $patientStmt = $pdo->prepare("
                        SELECT u.userId FROM patients p JOIN users u ON p.userId = u.userId WHERE p.patientId = ?
                    ");
                    $patientStmt->execute([$selectedAppointment['patientId']]);
                    $patient = $patientStmt->fetch();
                    
                    if ($patient) {
                        createNotification(
                            $patient['userId'],
                            'appointment',
                            'Appointment Rescheduled',
                            "Your appointment has been rescheduled to " . date('M j, Y g:i A', strtotime($newDateTime)) . " by reception."
                        );
                    }
                    
                    // Notify doctor
                    $doctorStmt = $pdo->prepare("
                        SELECT s.userId FROM doctors d JOIN staff s ON d.staffId = s.staffId WHERE d.doctorId = ?
                    ");
                    $doctorStmt->execute([$selectedAppointment['doctorId']]);
                    $doctor = $doctorStmt->fetch();
                    
                    if ($doctor) {
                        createNotification(
                            $doctor['userId'],
                            'appointment',
                            'Appointment Rescheduled',
                            "Appointment with patient has been rescheduled to " . date('M j, Y g:i A', strtotime($newDateTime))
                        );
                    }
                    
                    sendAppointmentRescheduleEmail($appointmentId, $oldDateTime, $newDateTime);
                    
                    $pdo->commit();
                    $bookingSuccess = true;
                    
                    $_SESSION['success'] = "Appointment rescheduled successfully!";
                    logAction($userId, 'RESCHEDULE_APPOINTMENT_STAFF', "Staff rescheduled appointment $appointmentId to $newDateTime");
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $bookingError = $e->getMessage();
                }
            }
        }
    }
}

// Get recent patients for quick access
$recentPatients = $pdo->query("
    SELECT DISTINCT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber,
           (SELECT MAX(dateTime) FROM appointments WHERE patientId = p.patientId) as last_visit
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.role = 'patient'
    ORDER BY last_visit DESC, u.firstName ASC
    LIMIT 10
")->fetchAll();
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-alt"></i> Manage Appointments</h1>
            <p>
                <?php if ($step === 'find'): ?>
                    Step 1: Find Patient
                <?php elseif ($step === 'select'): ?>
                    Step 2: Select Appointment
                <?php elseif ($step === 'reschedule'): ?>
                    Step 3: Choose New Time
                <?php endif; ?>
            </p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php if ($step !== 'find'): ?>
                <a href="reschedule-appointment.php" class="staff-btn staff-btn-outline">
                    <i class="fas fa-search"></i> Find Another Patient
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="staff-alert staff-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="staff-alert staff-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if ($bookingSuccess): ?>
        <div class="staff-alert staff-alert-success">
            <i class="fas fa-check-circle"></i> 
            Appointment rescheduled successfully!
            <a href="reschedule-appointment.php?step=select&patient_id=<?php echo $selectedAppointment['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm" style="margin-left: auto;">
                Manage More Appointments
            </a>
            <a href="dashboard.php" class="staff-btn staff-btn-outline staff-btn-sm">Return to Dashboard</a>
        </div>
    <?php endif; ?>

    <!-- STEP 1: FIND PATIENT -->
    <?php if ($step === 'find'): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-search"></i> Find Patient</h3>
            </div>
            <div class="staff-card-body">
                <form method="GET" class="staff-search-group">
                    <input type="hidden" name="step" value="find">
                    <input type="text" name="search" placeholder="Search by name, email, or phone number..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" class="staff-search-input">
                    <button type="submit" class="staff-btn staff-btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>

                <?php if ($searchTerm): ?>
                    <div class="staff-search-results">
                        <h4>Search Results (<?php echo count($searchResults); ?> found)</h4>
                        <?php if (empty($searchResults)): ?>
                            <p class="staff-text-muted">No patients found.</p>
                        <?php else: ?>
                            <div class="staff-patient-list">
                                <?php foreach ($searchResults as $patient): ?>
                                    <div class="staff-patient-item">
                                        <div class="staff-patient-info">
                                            <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong>
                                            <small><?php echo htmlspecialchars($patient['email']); ?> | <?php echo htmlspecialchars($patient['phoneNumber']); ?></small>
                                            <?php if ($patient['dateOfBirth']): ?>
                                                <small>DOB: <?php echo date('M j, Y', strtotime($patient['dateOfBirth'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <a href="?step=select&patient_id=<?php echo $patient['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">
                                            Select Patient
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$searchTerm && !empty($recentPatients)): ?>
                    <div class="staff-recent-patients">
                        <h4><i class="fas fa-clock"></i> Recent Patients</h4>
                        <div class="staff-patient-list">
                            <?php foreach ($recentPatients as $recent): ?>
                                <div class="staff-patient-item">
                                    <div class="staff-patient-info">
                                        <strong><?php echo htmlspecialchars($recent['firstName'] . ' ' . $recent['lastName']); ?></strong>
                                        <small><?php echo htmlspecialchars($recent['email']); ?> | <?php echo htmlspecialchars($recent['phoneNumber']); ?></small>
                                        <?php if ($recent['last_visit']): ?>
                                            <small>Last Visit: <?php echo date('M j, Y', strtotime($recent['last_visit'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <a href="?step=select&patient_id=<?php echo $recent['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">
                                        Select
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- STEP 2: SELECT APPOINTMENT -->
    <?php if ($step === 'select' && $selectedPatient): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-user-circle"></i> Patient: <?php echo htmlspecialchars($selectedPatient['firstName'] . ' ' . $selectedPatient['lastName']); ?></h3>
                <a href="book-appointment.php?patient_id=<?php echo $selectedPatient['patientId']; ?>" class="staff-btn staff-btn-success staff-btn-sm">
                    <i class="fas fa-plus"></i> Book New Appointment
                </a>
            </div>
            <div class="staff-card-body">
                <div class="staff-patient-info-grid">
                    <div class="staff-info-group">
                        <h4>Contact Information</h4>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selectedPatient['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($selectedPatient['phoneNumber']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $selectedPatient['dateOfBirth'] ? date('M j, Y', strtotime($selectedPatient['dateOfBirth'])) : 'N/A'; ?></p>
                        <p><strong>Blood Type:</strong> <?php echo $selectedPatient['bloodType'] ?: 'N/A'; ?></p>
                        <p><strong>Allergies:</strong> <?php echo htmlspecialchars($selectedPatient['knownAllergies'] ?: 'None'); ?></p>
                    </div>
                </div>

                <h4 style="margin-top: 25px; margin-bottom: 15px; color: #1e293b;">
                    <i class="fas fa-calendar-check"></i> Scheduled Appointments
                </h4>

                <?php if (empty($patientAppointments)): ?>
                    <div class="staff-empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No scheduled appointments found for this patient.</p>
                        <a href="book-appointment.php?patient_id=<?php echo $selectedPatient['patientId']; ?>" class="staff-btn staff-btn-primary">
                            <i class="fas fa-plus"></i> Book New Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="staff-table-responsive">
                        <table class="staff-data-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Doctor</th>
                                    <th>Specialization</th>
                                    <th>Fee</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patientAppointments as $appt): ?>
                                    <tr>
                                        <td data-label="Date & Time">
                                            <strong><?php echo date('M j, Y', strtotime($appt['dateTime'])); ?></strong><br>
                                            <small><?php echo date('g:i A', strtotime($appt['dateTime'])); ?></small>
                                        </td>
                                        <td data-label="Doctor">Dr. <?php echo htmlspecialchars($appt['doctorName']); ?></td>
                                        <td data-label="Specialization"><?php echo htmlspecialchars($appt['specialization']); ?></td>
                                        <td data-label="Fee">$<?php echo number_format($appt['consultationFee'], 2); ?></td>
                                        <td data-label="Reason"><?php echo htmlspecialchars($appt['reason'] ?: '-'); ?></td>
                                        <td data-label="Actions">
                                            <div class="staff-action-buttons">
                                                <a href="?step=reschedule&id=<?php echo $appt['appointmentId']; ?>" class="staff-btn staff-btn-warning staff-btn-sm">
                                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                                </a>
                                                <a href="?action=cancel&id=<?php echo $appt['appointmentId']; ?>" 
                                                   class="staff-btn staff-btn-danger staff-btn-sm" 
                                                   onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- STEP 3: RESCHEDULE FORM -->
    <?php if ($step === 'reschedule' && $selectedAppointment && !$bookingSuccess): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-info-circle"></i> Current Appointment</h3>
            </div>
            <div class="staff-card-body">
                <div class="staff-info-group">
                    <p><strong>Patient:</strong> <?php echo htmlspecialchars($selectedAppointment['patientName']); ?></p>
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($selectedAppointment['doctorName']); ?> (<?php echo htmlspecialchars($selectedAppointment['specialization']); ?>)</p>
                    <p><strong>Current Date & Time:</strong> <?php echo date('F j, Y g:i A', strtotime($selectedAppointment['dateTime'])); ?></p>
                    <p><strong>Consultation Fee:</strong> $<?php echo number_format($selectedAppointment['consultationFee'], 2); ?></p>
                </div>
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <a href="?action=cancel&id=<?php echo $selectedAppointment['appointmentId']; ?>" 
                       class="staff-btn staff-btn-danger staff-btn-sm" 
                       onclick="return confirm('Are you sure you want to cancel this appointment?')">
                        <i class="fas fa-times"></i> Cancel This Appointment
                    </a>
                </div>
            </div>
        </div>

        <?php if ($bookingError): ?>
            <div class="staff-alert staff-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($bookingError); ?>
            </div>
        <?php endif; ?>

        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-calendar-plus"></i> Select New Date & Time</h3>
            </div>
            <div class="staff-card-body">
                <form method="POST" id="reschedule-form">
                    <input type="hidden" name="reschedule" value="1">
                    
                    <div class="staff-form-group">
                        <label>New Date <span class="required">*</span></label>
                        <input type="date" name="appointment_date" id="appointment_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                               class="staff-form-control" required>
                    </div>
                    
                    <div class="staff-form-group">
                        <label>New Time <span class="required">*</span></label>
                        <div id="time-slots-container" class="staff-time-slots-container">
                            <p class="staff-text-muted">Please select a date first</p>
                        </div>
                        <input type="hidden" name="appointment_time" id="appointment_time">
                    </div>
                    
                    <div class="staff-form-group">
                        <label>Reason for Reschedule</label>
                        <textarea name="reason" rows="3" class="staff-form-control">Rescheduled by staff</textarea>
                    </div>
                    
                    <input type="hidden" name="doctor_id" id="doctor_id" value="<?php echo $selectedAppointment['doctorId']; ?>">
                    
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="staff-btn staff-btn-primary">
                            <i class="fas fa-check"></i> Confirm Reschedule
                        </button>
                        <a href="?step=select&patient_id=<?php echo $selectedAppointment['patientId']; ?>" class="staff-btn staff-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.staff-time-slots-container {
    min-height: 100px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #cbd5e1;
}
.staff-empty-state {
    text-align: center;
    padding: 40px;
}
.staff-empty-state i {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 15px;
}
.staff-btn-warning {
    background: #f59e0b;
    color: white;
}
.staff-btn-warning:hover {
    background: #d97706;
}
.staff-btn-success {
    background: #10b981;
    color: white;
}
.staff-btn-success:hover {
    background: #059669;
}
.staff-action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}
</style>

<script>
const doctorId = <?php echo $selectedAppointment['doctorId'] ?? 0; ?>;
</script>
<script src="../js/appointments.js"></script>

<?php include '../includes/footer.php'; ?>