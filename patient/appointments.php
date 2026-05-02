<?php
date_default_timezone_set('Australia/Sydney');
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "Book Appointment - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient ID
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

$bookingError = null;
$bookingSuccess = false;
$bookedAppointmentId = null;

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctorId = (int)$_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    if (!$doctorId || !$date || !$time) {
        $bookingError = "All fields are required. Please select a doctor, date, and time.";
    } else {
        if (strlen($time) === 5 && strpos($time, ':') === 2) {
            $time .= ':00';
        }
        $dateTime = date('Y-m-d H:i:s', strtotime("$date $time"));
        
        if ($dateTime < date('Y-m-d H:i:s')) {
            $bookingError = "Cannot book appointments in the past.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Double-check availability
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM appointments 
                    WHERE doctorId = ? AND dateTime = ? 
                    AND status NOT IN ('cancelled', 'no-show')
                ");
                $checkStmt->execute([$doctorId, $dateTime]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("This time slot is already booked. Please select another time.");
                }
                
                // Check doctor availability
                $availStmt = $pdo->prepare("
                    SELECT isAvailable, isDayOff, startTime, endTime
                    FROM doctor_availability 
                    WHERE doctorId = ? AND availabilityDate = ?
                ");
                $availStmt->execute([$doctorId, $date]);
                $availability = $availStmt->fetch();
                
                if (!$availability) {
                    $dayOfWeek = date('w', strtotime($date));
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $availability = [
                            'startTime' => WORKING_HOURS_START . ':00',
                            'endTime' => WORKING_HOURS_END . ':00',
                            'isAvailable' => 1,
                            'isDayOff' => 0
                        ];
                    } else {
                        throw new Exception("Doctor is not available on weekends.");
                    }
                }
                
                if ($availability['isDayOff'] || !$availability['isAvailable']) {
                    throw new Exception("Doctor is not available on this date.");
                }
                
                $bookingTime = date('H:i:s', strtotime($time));
                if ($bookingTime < $availability['startTime'] || $bookingTime > $availability['endTime']) {
                    throw new Exception("Selected time is outside doctor's working hours.");
                }
                
                // Insert appointment
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (patientId, doctorId, dateTime, duration, reason, status, createdAt) 
                    VALUES (?, ?, ?, 30, ?, 'scheduled', NOW())
                ");
                $stmt->execute([$patientId, $doctorId, $dateTime, $reason]);
                $appointmentId = $pdo->lastInsertId();
                $bookedAppointmentId = $appointmentId;
                
                // ========== GET PATIENT DETAILS ==========
                $patientStmt = $pdo->prepare("
                    SELECT u.userId, u.firstName, u.lastName, u.email, u.phoneNumber 
                    FROM users u WHERE u.userId = ?
                ");
                $patientStmt->execute([$userId]);
                $patientInfo = $patientStmt->fetch();
                
                // ========== GET DOCTOR DETAILS ==========
                $doctorStmt = $pdo->prepare("
                    SELECT s.userId as doctorUserId, u.email as doctorEmail, 
                           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
                           d.specialization
                    FROM doctors d 
                    JOIN staff s ON d.staffId = s.staffId 
                    JOIN users u ON s.userId = u.userId
                    WHERE d.doctorId = ?
                ");
                $doctorStmt->execute([$doctorId]);
                $doctorInfo = $doctorStmt->fetch();
                
                $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($dateTime));
                
                // ========== SEND EMAIL TO PATIENT ==========
                if ($patientInfo && !empty($patientInfo['email'])) {
                    $patientSubject = "Appointment Confirmation - " . SITE_NAME;
                    $patientMessage = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: #0d9488; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                                .appointment-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                                .button { display: inline-block; background: #0d9488; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'><h2>✓ Appointment Confirmed</h2></div>
                                <div class='content'>
                                    <p>Dear <strong>{$patientInfo['firstName']} {$patientInfo['lastName']}</strong>,</p>
                                    <p>Your appointment has been successfully booked.</p>
                                    <div class='appointment-info'>
                                        <p><strong>Doctor:</strong> Dr. {$doctorInfo['doctorName']} ({$doctorInfo['specialization']})</p>
                                        <p><strong>Date & Time:</strong> {$formattedDateTime}</p>
                                        <p><strong>Reason:</strong> " . ($reason ?: 'General Consultation') . "</p>
                                    </div>
                                    <p>Please arrive 15 minutes before your appointment time.</p>
                                    <a href='" . SITE_URL . "/patient/view-appointments.php' class='button'>View My Appointments</a>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    sendEmail($patientInfo['email'], $patientSubject, $patientMessage);
                }
                
                // ========== SEND EMAIL TO DOCTOR ==========
                if ($doctorInfo && !empty($doctorInfo['doctorEmail'])) {
                    $doctorSubject = "New Appointment Booked - " . SITE_NAME;
                    $doctorMessage = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: #2563eb; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                                .appointment-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                                .button { display: inline-block; background: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'><h2>📅 New Appointment Booked</h2></div>
                                <div class='content'>
                                    <p>Dear Dr. <strong>{$doctorInfo['doctorName']}</strong>,</p>
                                    <p>A new appointment has been booked with you.</p>
                                    <div class='appointment-info'>
                                        <p><strong>Patient:</strong> {$patientInfo['firstName']} {$patientInfo['lastName']}</p>
                                        <p><strong>Date & Time:</strong> {$formattedDateTime}</p>
                                        <p><strong>Reason:</strong> " . ($reason ?: 'General Consultation') . "</p>
                                        <p><strong>Contact:</strong> {$patientInfo['phoneNumber']} | {$patientInfo['email']}</p>
                                    </div>
                                    <a href='" . SITE_URL . "/doctor/appointments.php?date=" . date('Y-m-d', strtotime($dateTime)) . "' class='button'>View My Schedule</a>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    sendEmail($doctorInfo['doctorEmail'], $doctorSubject, $doctorMessage);
                }
                
                // ========== CREATE NOTIFICATION FOR PATIENT ==========
createNotification(
    $userId,
    'appointment',
    'Appointment Booked',
    "Your appointment with Dr. {$doctorInfo['doctorName']} has been booked for {$formattedDateTime}.",
    "patient/view-appointments.php"  // No leading slash or ../
);

// ========== CREATE NOTIFICATION FOR DOCTOR ==========
if ($doctorInfo && $doctorInfo['doctorUserId']) {
    createNotification(
        $doctorInfo['doctorUserId'],
        'appointment',
        'New Appointment Booked',
        "New appointment with patient {$patientInfo['firstName']} {$patientInfo['lastName']} on {$formattedDateTime}.",
        "doctor/appointments.php?date=" . date('Y-m-d', strtotime($dateTime))
    );
}
                
                $pdo->commit();
                $bookingSuccess = true;
                $_SESSION['success'] = "Appointment booked successfully! Confirmation emails sent to you and the doctor.";
                logAction($userId, 'BOOK_APPOINTMENT', "Booked appointment ID: $appointmentId with doctor ID: $doctorId");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $bookingError = $e->getMessage();
                error_log("Appointment booking error: " . $e->getMessage());
            }
        }
    }
}

// Get doctors list
$doctors = $pdo->query("
    SELECT d.doctorId, u.firstName, u.lastName, d.specialization, d.consultationFee
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.isAvailable = 1
    ORDER BY u.firstName
")->fetchAll();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-plus"></i> Book Appointment</h1>
            <p>Schedule your visit with our healthcare professionals</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="view-appointments.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-list"></i> My Appointments
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
    
    <?php if ($bookingError): ?>
        <div class="patient-alert patient-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($bookingError); ?>
        </div>
    <?php endif; ?>

    <?php if ($bookingSuccess): ?>
        <div class="patient-alert patient-alert-success">
            <i class="fas fa-check-circle"></i> 
            Your appointment has been booked successfully! 
            <a href="view-appointments.php" class="patient-btn patient-btn-primary patient-btn-sm" style="margin-left: auto;">View My Appointments</a>
        </div>
    <?php else: ?>
        <div class="patient-card">
            <div class="patient-card-header">
                <h3><i class="fas fa-calendar-plus"></i> New Appointment</h3>
            </div>
            <div class="patient-card-body">
                <form method="POST" id="appointment-form">
                    <input type="hidden" name="book_appointment" value="1">
                    
                    <div class="patient-form-row">
                        <div class="patient-form-group">
                            <label for="doctor_id"><i class="fas fa-user-md"></i> Select Doctor <span class="required">*</span></label>
                            <select id="doctor_id" name="doctor_id" class="patient-form-control" required>
                                <option value="">-- Choose a doctor --</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['doctorId']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?>
                                        - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                        ($<?php echo number_format($doctor['consultationFee'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="patient-form-group">
                            <label for="appointment_date"><i class="fas fa-calendar"></i> Date <span class="required">*</span></label>
                            <input type="date" id="appointment_date" name="appointment_date"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                                   class="patient-form-control" required>
                        </div>
                    </div>
                    
                    <!-- Closest Appointment Info -->
                    <div id="closest-appointment-info" class="closest-appointment-info" style="display: none;">
                        <div class="closest-badge">
                            <i class="fas fa-star"></i> Earliest Available Appointment
                        </div>
                        <p id="closest-appointment-text">
                            <i class="fas fa-spinner fa-spin"></i> Checking doctor availability...
                        </p>
                        <button type="button" id="use-closest-appointment" class="patient-btn patient-btn-outline patient-btn-sm" style="display: none;">
                            <i class="fas fa-calendar-check"></i> Use This Time
                        </button>
                    </div>
                    
                    <div class="patient-form-group">
                        <label><i class="fas fa-clock"></i> Select Time <span class="required">*</span></label>
                        <div id="time-slots-container" class="patient-time-slots-container">
                            <p class="patient-text-muted" style="text-align: center;">Please select a doctor and date first</p>
                        </div>
                        <input type="hidden" id="appointment_time" name="appointment_time">
                    </div>
                    
                    <div class="patient-form-group">
                        <label for="reason"><i class="fas fa-notes-medical"></i> Reason for Visit</label>
                        <textarea id="reason" name="reason" rows="3" class="patient-form-control" 
                                  placeholder="Briefly describe the reason for your visit..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_booking" class="patient-btn patient-btn-primary patient-btn-large">
                        <i class="fas fa-check"></i> Book Appointment
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.closest-appointment-info {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 2px solid #fcd34d;
    border-radius: 16px;
    padding: 18px 22px;
    margin-bottom: 25px;
}
.closest-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0d9488;
    color: white;
    padding: 6px 18px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
}
.closest-appointment-info p {
    margin: 0 0 18px 0;
    color: #92400e;
    font-size: 16px;
    font-weight: 500;
}
#use-closest-appointment {
    background: transparent !important;
    border: 2px solid #0d9488;
    color: #0d9488;
    font-weight: 600;
}
#use-closest-appointment:hover {
    background: #0d9488;
    color: white;
}
.patient-time-slots-container {
    min-height: 100px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 16px;
    border: 2px dashed #cbd5e1;
    margin-top: 10px;
}
.patient-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
}
.patient-time-slot {
    padding: 12px 8px;
    background: white;
    border: 2px solid #cbd5e1;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 15px;
    font-weight: 600;
    color: #334155;
}
.patient-time-slot:hover {
    background: #0d9488;
    color: white;
    border-color: #0d9488;
    transform: scale(1.05);
}
.patient-time-slot.selected {
    background: #0d9488;
    color: white;
    border-color: #0d9488;
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
}
.patient-text-muted {
    color: #64748b;
    text-align: center;
    padding: 20px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const closestInfo = document.getElementById('closest-appointment-info');
    const closestText = document.getElementById('closest-appointment-text');
    const useClosestBtn = document.getElementById('use-closest-appointment');
    
    let closestSlot = null;
    
    if (dateInput && !dateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
    }
    
    async function findClosestAppointment(doctorId) {
        if (!doctorId) return null;
        try {
            const response = await fetch(`../ajax/get-closest-appointment.php?doctor_id=${doctorId}`);
            const text = await response.text();
            try {
                const data = JSON.parse(text);
                if (data.success && data.closest) return data.closest;
            } catch (e) {}
            return null;
        } catch (error) {
            return null;
        }
    }
    
    async function loadTimeSlots(doctorId, date) {
        if (!doctorId || !date) {
            timeSlotsContainer.innerHTML = '<p class="patient-text-muted">Please select a doctor and date first</p>';
            return;
        }
        
        timeSlotsContainer.innerHTML = '<p class="patient-text-muted"><i class="fas fa-spinner fa-spin"></i> Loading available times...</p>';
        
        try {
            const response = await fetch(`../ajax/get-time-slots.php?doctor_id=${doctorId}&date=${date}`);
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (data.success && data.slots && data.slots.length > 0) {
                let html = '<div class="patient-time-slots">';
                data.slots.forEach(slot => {
                    html += `<div class="patient-time-slot" data-time="${slot.value}">${slot.display}</div>`;
                });
                html += '</div>';
                timeSlotsContainer.innerHTML = html;
                
                document.querySelectorAll('.patient-time-slot').forEach(slot => {
                    slot.addEventListener('click', function() {
                        document.querySelectorAll('.patient-time-slot').forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        if (timeInput) timeInput.value = this.getAttribute('data-time');
                    });
                });
            } else {
                const message = data.message || 'No available time slots for this date.';
                timeSlotsContainer.innerHTML = `<p class="patient-text-muted">${message}</p>`;
                if (timeInput) timeInput.value = '';
            }
        } catch (error) {
            timeSlotsContainer.innerHTML = '<p class="patient-text-muted">Error loading time slots.</p>';
        }
    }
    
    async function updateClosestAppointment() {
        const doctorId = doctorSelect?.value;
        if (!doctorId) {
            closestInfo.style.display = 'none';
            return;
        }
        
        closestInfo.style.display = 'block';
        closestText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking doctor availability...';
        useClosestBtn.style.display = 'none';
        
        closestSlot = await findClosestAppointment(doctorId);
        
        if (closestSlot) {
            const dateObj = new Date(closestSlot.date + 'T' + closestSlot.time);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric',
                year: 'numeric'
            });
            closestText.innerHTML = `<i class="fas fa-calendar-check"></i> <strong>${formattedDate}</strong> at <strong>${closestSlot.display_time}</strong>`;
            useClosestBtn.style.display = 'inline-block';
            closestInfo.style.borderColor = '#10b981';
        } else {
            closestText.innerHTML = '<i class="fas fa-calendar-times"></i> This doctor has not set their availability schedule yet. No appointments available.';
            closestInfo.style.borderColor = '#ef4444';
        }
    }
    
    if (useClosestBtn) {
        useClosestBtn.addEventListener('click', function() {
            if (closestSlot) {
                dateInput.value = closestSlot.date;
                timeInput.value = closestSlot.time;
                loadTimeSlots(doctorSelect.value, closestSlot.date);
                setTimeout(() => {
                    document.querySelectorAll('.patient-time-slot').forEach(slot => {
                        if (slot.getAttribute('data-time') === closestSlot.time) {
                            slot.classList.add('selected');
                        }
                    });
                }, 500);
                timeSlotsContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
    
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function() {
            if (timeInput) timeInput.value = '';
            if (this.value && dateInput.value) loadTimeSlots(this.value, dateInput.value);
            updateClosestAppointment();
        });
    }
    
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            if (timeInput) timeInput.value = '';
            if (doctorSelect.value && this.value) loadTimeSlots(doctorSelect.value, this.value);
        });
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    const doctorIdParam = urlParams.get('doctor_id');
    if (doctorIdParam && doctorSelect) {
        doctorSelect.value = doctorIdParam;
        if (dateInput.value) loadTimeSlots(doctorIdParam, dateInput.value);
        updateClosestAppointment();
    }
    
    if (doctorSelect?.value) {
        updateClosestAppointment();
    }
    
    const form = document.getElementById('appointment-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!timeInput || !timeInput.value) {
                e.preventDefault();
                alert('Please select a time slot for your appointment.');
                return false;
            }
            
            if (!confirm('Confirm this appointment booking?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>