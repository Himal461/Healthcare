<?php
date_default_timezone_set('Australia/Sydney');

require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Appointments - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

/* ============================================
   GET PATIENT ID
   ============================================ */
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    $stmt = $pdo->prepare("INSERT INTO patients (userId, createdAt) VALUES (?, NOW())");
    $stmt->execute([$userId]);
    $patientId = $pdo->lastInsertId();
} else {
    $patientId = $patient['patientId'];
}

/* ============================================
   HANDLE APPOINTMENT CANCELLATION
   ============================================ */
if (isset($_GET['cancel'])) {
    $appointmentId = (int)$_GET['cancel'];
    try {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', 
                cancellationReason = 'Cancelled by patient',
                updatedAt = NOW()
            WHERE appointmentId = ? 
              AND patientId = ? 
              AND status = 'scheduled'
        ");
        $stmt->execute([$appointmentId, $patientId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Appointment cancelled successfully!";
            logAction($userId, 'CANCEL_APPOINTMENT', "Cancelled appointment ID: $appointmentId");
        } else {
            $_SESSION['error'] = "Unable to cancel this appointment.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to cancel appointment.";
        error_log("Cancel failed: " . $e->getMessage());
    }
    header("Location: appointments.php");
    exit();
}

/* ============================================
   HANDLE APPOINTMENT BOOKING
   ============================================ */
$bookingError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctorId = $_POST['doctor_id'] ?? null;
    $date     = $_POST['appointment_date'] ?? null;
    $time     = $_POST['appointment_time'] ?? null;
    $reason   = sanitizeInput($_POST['reason'] ?? '');

    error_log("BOOKING ATTEMPT | user:$userId | patient:$patientId | doctor:$doctorId | date:$date | time:$time");

    if (!$doctorId || !$date || !$time) {
        $bookingError = "All fields are required. Please select a doctor, date, and time.";
    } else {
        // Fix missing seconds (common issue)
        if (strlen($time) === 5 && strpos($time, ':') === 2) {
            $time .= ':00';
        }

        $dateTime = date('Y-m-d H:i:s', strtotime("$date $time"));

        if ($dateTime === false || $dateTime < date('Y-m-d H:i:s')) {
            $bookingError = "Invalid or past date/time selected.";
        } else {
            try {
                $pdo->beginTransaction();

                // Check slot availability
                $check = $pdo->prepare("
                    SELECT COUNT(*) FROM appointments
                    WHERE doctorId = ? 
                      AND dateTime = ? 
                      AND status NOT IN ('cancelled','no-show')
                ");
                $check->execute([$doctorId, $dateTime]);

                if ($check->fetchColumn() > 0) {
                    throw new Exception("This time slot is already booked. Please select another time.");
                }

                // Insert appointment
                $stmt = $pdo->prepare("
                    INSERT INTO appointments
                    (patientId, doctorId, dateTime, duration, reason, status, createdAt, updatedAt)
                    VALUES (?, ?, ?, 30, ?, 'scheduled', NOW(), NOW())
                ");
                $stmt->execute([$patientId, $doctorId, $dateTime, $reason]);

                $appointmentId = $pdo->lastInsertId();

                // Get doctor user info for notification
                $doctorStmt = $pdo->prepare("
                    SELECT s.userId, u.firstName, u.lastName
                    FROM doctors d
                    JOIN staff s ON d.staffId = s.staffId
                    JOIN users u ON s.userId = u.userId
                    WHERE d.doctorId = ?
                ");
                $doctorStmt->execute([$doctorId]);
                $doctor = $doctorStmt->fetch();

                if ($doctor) {
                    createNotification(
                        $doctor['userId'],
                        'appointment',
                        'New Appointment Booked',
                        "A new appointment has been booked for " . date('M j, Y g:i A', strtotime($dateTime))
                    );
                }

                createNotification(
                    $userId,
                    'appointment',
                    'Appointment Booked',
                    "Your appointment has been booked for " . date('M j, Y g:i A', strtotime($dateTime))
                );

                $pdo->commit();

                $_SESSION['success'] = "Appointment booked successfully!";
                logAction($userId, 'BOOK_APPOINTMENT', "Booked appointment with doctor ID: $doctorId");
                header("Location: appointments.php");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $bookingError = $e->getMessage();
                error_log("BOOKING FAILED: " . $e->getMessage());
            }
        }
    }
}

/* ============================================
   GET UPCOMING APPOINTMENTS
   ============================================ */
$upcomingStmt = $pdo->prepare("
    SELECT a.*, u.firstName, u.lastName, d.specialization, d.doctorId
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE a.patientId = ?
      AND a.dateTime >= NOW()
      AND a.status NOT IN ('cancelled', 'no-show')
    ORDER BY a.dateTime ASC
");
$upcomingStmt->execute([$patientId]);
$upcomingAppointments = $upcomingStmt->fetchAll();

/* ============================================
   GET PAST APPOINTMENTS
   ============================================ */
$pastStmt = $pdo->prepare("
    SELECT a.*, u.firstName, u.lastName, d.specialization, d.doctorId
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE a.patientId = ?
      AND (a.dateTime < NOW() OR a.status IN ('cancelled', 'no-show', 'completed'))
    ORDER BY a.dateTime DESC
    LIMIT 20
");
$pastStmt->execute([$patientId]);
$pastAppointments = $pastStmt->fetchAll();

/* ============================================
   GET DOCTORS LIST
   ============================================ */
$doctors = $pdo->query("
    SELECT d.doctorId, u.firstName, u.lastName, d.specialization
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.isAvailable = 1
    ORDER BY u.firstName
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>My Appointments</h1>
        <p>Book, view, and manage your appointments</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button class="close-alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error alert-dismissible">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button class="close-alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($bookingError): ?>
        <div class="alert alert-error alert-dismissible">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($bookingError); ?>
            <button class="close-alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Book Appointment Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Book New Appointment</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="appointment-form">
                <input type="hidden" name="book_appointment" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="doctor_id">Select Doctor *</label>
                        <select id="doctor_id" name="doctor_id" class="form-select" required>
                            <option value="">Choose a doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['doctorId']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?>
                                    - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="appointment_date">Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date"
                               min="<?php echo date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                               class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Select Time *</label>
                    <div id="time-slots-container" class="time-slots-container">
                        <p class="text-muted">Please select a doctor and date first</p>
                    </div>
                    <input type="hidden" id="appointment_time" name="appointment_time">
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Visit (Optional)</label>
                    <textarea id="reason" name="reason" rows="3" 
                              placeholder="Briefly describe the reason for visit"></textarea>
                </div>

                <button type="submit" name="book_appointment" class="btn btn-primary">
                    <i class="fas fa-check"></i> Book Appointment
                </button>
            </form>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingAppointments)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No upcoming appointments</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <tr>
                                <td data-label="Date & Time">
                                    <?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?>
                                </td>
                                <td data-label="Doctor">
                                    Dr. <?php echo htmlspecialchars($appointment['firstName'] . ' ' . $appointment['lastName']); ?>
                                </td>
                                <td data-label="Specialization">
                                    <?php echo htmlspecialchars($appointment['specialization']); ?>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <a href="?cancel=<?php echo $appointment['appointmentId']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Cancel this appointment?')">
                                            <i class="fas fa-times"></i> Cancel
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

    <!-- Past Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Past Appointments</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
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
                    <?php if (empty($pastAppointments)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No past appointments</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pastAppointments as $appointment): ?>
                            <tr>
                                <td data-label="Date & Time">
                                    <?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?>
                                </td>
                                <td data-label="Doctor">
                                    Dr. <?php echo htmlspecialchars($appointment['firstName'] . ' ' . $appointment['lastName']); ?>
                                </td>
                                <td data-label="Specialization">
                                    <?php echo htmlspecialchars($appointment['specialization']); ?>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Reason">
                                    <?php echo htmlspecialchars($appointment['reason'] ?: '-'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const doctorSelect       = document.getElementById('doctor_id');
    const dateInput          = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput          = document.getElementById('appointment_time');

    if (!doctorSelect || !dateInput) return;

    // Pre-select doctor if passed in URL
    const urlParams = new URLSearchParams(window.location.search);
    const doctorIdParam = urlParams.get('doctor_id');
    if (doctorIdParam && doctorSelect) {
        doctorSelect.value = doctorIdParam;
        if (dateInput.value) loadTimeSlots();
    }

    function loadTimeSlots() {
        const doctorId = doctorSelect.value;
        const date     = dateInput.value;

        if (!doctorId || !date) {
            timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a doctor and date first</p>';
            timeInput.value = '';
            return;
        }

        timeSlotsContainer.innerHTML = '<p class="text-muted">Loading available times...</p>';

        fetch(`../ajax/get-time-slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.slots && data.slots.length > 0) {
                    let html = '<div class="time-slots">';
                    data.slots.forEach(slot => {
                        html += `<div class="time-slot" data-time="${slot.value}">${slot.start}</div>`;
                    });
                    html += '</div>';
                    timeSlotsContainer.innerHTML = html;

                    // Event delegation for reliability
                    timeSlotsContainer.addEventListener('click', function(e) {
                        const slot = e.target.closest('.time-slot');
                        if (!slot) return;

                        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                        slot.classList.add('selected');
                        timeInput.value = slot.getAttribute('data-time');

                        console.log('Selected time:', timeInput.value); // for debugging
                    });
                } else {
                    timeSlotsContainer.innerHTML = '<p class="text-muted">No available time slots for this date</p>';
                    timeInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error loading slots:', error);
                timeSlotsContainer.innerHTML = '<p class="text-muted">Error loading time slots. Please try again.</p>';
            });
    }

    doctorSelect.addEventListener('change', function() {
        timeInput.value = '';
        loadTimeSlots();
    });

    dateInput.addEventListener('change', function() {
        timeInput.value = '';
        loadTimeSlots();
    });

    // Initial load if values are already set (e.g. after validation error)
    if (doctorSelect.value && dateInput.value) {
        loadTimeSlots();
    }

    // Form validation + loading state
    const form = document.getElementById('appointment-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!timeInput.value) {
                e.preventDefault();
                alert('Please select a time slot for your appointment.');
                return false;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>