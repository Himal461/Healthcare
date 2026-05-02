<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$appointmentId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$appointmentId) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: view-appointments.php");
    exit();
}

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'] ?? 0;

// Get appointment details and verify ownership
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           d.specialization,
           d.doctorId,
           d.consultationFee
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE a.appointmentId = ? AND a.patientId = ? AND a.status IN ('scheduled', 'confirmed')
");
$stmt->execute([$appointmentId, $patientId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found or cannot be rescheduled.";
    header("Location: view-appointments.php");
    exit();
}

$bookingError = null;
$bookingSuccess = false;

// Handle reschedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule'])) {
    $newDate = $_POST['appointment_date'];
    $newTime = $_POST['appointment_time'];
    $reason = sanitizeInput($_POST['reason'] ?? 'Rescheduled by patient');
    
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
                
                // Check if new slot is available (excluding current appointment)
                $check = $pdo->prepare("
                    SELECT COUNT(*) FROM appointments 
                    WHERE doctorId = ? AND dateTime = ? 
                    AND appointmentId != ? 
                    AND status NOT IN ('cancelled','no-show')
                ");
                $check->execute([$appointment['doctorId'], $newDateTime, $appointmentId]);
                if ($check->fetchColumn() > 0) {
                    throw new Exception("This time slot is already booked. Please select another time.");
                }
                
                // Check doctor availability for new date
                $availStmt = $pdo->prepare("
                    SELECT isAvailable, isDayOff, startTime, endTime
                    FROM doctor_availability 
                    WHERE doctorId = ? AND availabilityDate = ?
                ");
                $availStmt->execute([$appointment['doctorId'], $newDate]);
                $availability = $availStmt->fetch();
                
                if (!$availability) {
                    throw new Exception("Doctor has not set availability for this date.");
                }
                
                if ($availability['isDayOff'] || !$availability['isAvailable']) {
                    throw new Exception("Doctor is not available on this date.");
                }
                
                // Check if time is within working hours
                $bookingTime = date('H:i:s', strtotime($newTime));
                if ($bookingTime < $availability['startTime'] || $bookingTime > $availability['endTime']) {
                    throw new Exception("Selected time is outside doctor's working hours.");
                }
                
                // Update appointment
                $oldDateTime = $appointment['dateTime'];
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET dateTime = ?, 
                        reason = CONCAT(IFNULL(reason, ''), '\n[RESCHEDULED: ', NOW(), '] New: ', ?), 
                        updatedAt = NOW() 
                    WHERE appointmentId = ?
                ");
                $stmt->execute([$newDateTime, $reason, $appointmentId]);
                
                // Notify doctor
                $doctorStmt = $pdo->prepare("
                    SELECT s.userId FROM doctors d 
                    JOIN staff s ON d.staffId = s.staffId 
                    WHERE d.doctorId = ?
                ");
                $doctorStmt->execute([$appointment['doctorId']]);
                $doctor = $doctorStmt->fetch();
                
                if ($doctor) {
                    createNotification(
                        $doctor['userId'],
                        'appointment',
                        'Appointment Rescheduled',
                        "Appointment has been rescheduled from " . date('M j, Y g:i A', strtotime($oldDateTime)) . 
                        " to " . date('M j, Y g:i A', strtotime($newDateTime))
                    );
                }
                
                // Notify patient
                createNotification(
                    $userId,
                    'appointment',
                    'Appointment Rescheduled',
                    "Your appointment has been rescheduled to " . date('M j, Y g:i A', strtotime($newDateTime))
                );
                
                // Send reschedule confirmation email
                sendAppointmentRescheduleEmail($appointmentId, $oldDateTime, $newDateTime);
                
                $pdo->commit();
                $bookingSuccess = true;
                
                $_SESSION['success'] = "Appointment rescheduled successfully!";
                logAction($userId, 'RESCHEDULE_APPOINTMENT', "Patient rescheduled appointment $appointmentId from $oldDateTime to $newDateTime");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $bookingError = $e->getMessage();
            }
        }
    }
}

$pageTitle = "Reschedule Appointment - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';
?>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-alt"></i> Reschedule Appointment</h1>
            <p>Change the date or time of your appointment</p>
        </div>
        <div class="header-actions">
            <a href="view-appointments.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
        </div>
    </div>

    <?php if ($bookingError): ?>
        <div class="patient-alert patient-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($bookingError); ?>
        </div>
    <?php endif; ?>

    <?php if ($bookingSuccess): ?>
        <div class="patient-alert patient-alert-success">
            <i class="fas fa-check-circle"></i> 
            Appointment rescheduled successfully!
            <a href="view-appointments.php" class="patient-btn patient-btn-primary patient-btn-sm" style="margin-left: auto;">View My Appointments</a>
        </div>
    <?php else: ?>
        <!-- Current Appointment Info -->
        <div class="patient-card">
            <div class="patient-card-header">
                <h3><i class="fas fa-info-circle"></i> Current Appointment</h3>
            </div>
            <div class="patient-card-body">
                <div class="patient-info-group">
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?> (<?php echo htmlspecialchars($appointment['specialization']); ?>)</p>
                    <p><strong>Current Date & Time:</strong> <?php echo date('F j, Y g:i A', strtotime($appointment['dateTime'])); ?></p>
                    <p><strong>Consultation Fee:</strong> $<?php echo number_format($appointment['consultationFee'], 2); ?></p>
                    <p><strong>Current Reason:</strong> <?php echo htmlspecialchars($appointment['reason'] ?: 'N/A'); ?></p>
                </div>
            </div>
        </div>

        <!-- Reschedule Form -->
        <div class="patient-card">
            <div class="patient-card-header">
                <h3><i class="fas fa-calendar-plus"></i> Select New Date & Time</h3>
            </div>
            <div class="patient-card-body">
                <form method="POST" id="reschedule-form">
                    <input type="hidden" name="reschedule" value="1">
                    
                    <div class="patient-form-group">
                        <label>New Date <span class="required">*</span></label>
                        <input type="date" name="appointment_date" id="appointment_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                               class="patient-form-control" required>
                    </div>
                    
                    <div class="patient-form-group">
                        <label>New Time <span class="required">*</span></label>
                        <div id="time-slots-container" class="patient-time-slots-container">
                            <p class="patient-text-muted">Please select a date first</p>
                        </div>
                        <input type="hidden" name="appointment_time" id="appointment_time">
                    </div>
                    
                    <div class="patient-form-group">
                        <label>Reason for Reschedule</label>
                        <textarea name="reason" rows="3" class="patient-form-control" 
                                  placeholder="Briefly describe why you're rescheduling...">Rescheduled by patient</textarea>
                    </div>
                    
                    <input type="hidden" name="doctor_id" id="doctor_id" value="<?php echo $appointment['doctorId']; ?>">
                    
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="patient-btn patient-btn-primary">
                            <i class="fas fa-check"></i> Confirm Reschedule
                        </button>
                        <a href="view-appointments.php" class="patient-btn patient-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const doctorId = <?php echo $appointment['doctorId']; ?>;
</script>
<script src="../js/appointments.js"></script>

<?php include '../includes/footer.php'; ?>