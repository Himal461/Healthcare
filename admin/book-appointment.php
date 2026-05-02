<?php
date_default_timezone_set('Australia/Sydney');
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Book Appointment - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$patientId = (int)($_GET['patient_id'] ?? 0);
$selectedPatient = null;
$bookingError = null;
$bookingSuccess = false;
$searchTerm = $_GET['search'] ?? '';
$searchResults = [];
$bookedAppointmentId = null;

// Get patient if ID provided
if ($patientId) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.userId, u.firstName, u.lastName, u.email, u.phoneNumber, p.dateOfBirth, p.bloodType
        FROM patients p JOIN users u ON p.userId = u.userId WHERE p.patientId = ? AND u.role = 'patient'
    ");
    $stmt->execute([$patientId]);
    $selectedPatient = $stmt->fetch();
}

// Handle patient search
if ($searchTerm && !$patientId) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber
        FROM patients p JOIN users u ON p.userId = u.userId
        WHERE u.role = 'patient' AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)
        ORDER BY u.firstName LIMIT 20
    ");
    $searchLike = "%$searchTerm%";
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike]);
    $searchResults = $stmt->fetchAll();
}

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctorId = (int)$_POST['doctor_id'];
    $patientId = (int)$_POST['patient_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    if (!$doctorId || !$date || !$time) {
        $bookingError = "All fields are required. Please select a doctor, date, and time.";
    } elseif (empty($patientId)) {
        $bookingError = "Please select a patient first.";
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
                    // Use default working hours if no availability set
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
                    FROM patients p JOIN users u ON p.userId = u.userId 
                    WHERE p.patientId = ?
                ");
                $patientStmt->execute([$patientId]);
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
                                .header { background: #dc2626; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                                .appointment-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                                .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'><h2>✓ Appointment Confirmed</h2></div>
                                <div class='content'>
                                    <p>Dear <strong>{$patientInfo['firstName']} {$patientInfo['lastName']}</strong>,</p>
                                    <p>Your appointment has been booked by administration.</p>
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
                    $patientEmailSent = sendEmail($patientInfo['email'], $patientSubject, $patientMessage);
                    error_log("Admin booking - Patient email sent: " . ($patientEmailSent ? 'Yes' : 'No') . " to {$patientInfo['email']}");
                }
                
                // ========== SEND EMAIL TO DOCTOR ==========
                if ($doctorInfo && !empty($doctorInfo['doctorEmail'])) {
                    $doctorSubject = "New Appointment Booked by Admin - " . SITE_NAME;
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
                                <div class='header'><h2>📅 New Appointment Booked by Admin</h2></div>
                                <div class='content'>
                                    <p>Dear Dr. <strong>{$doctorInfo['doctorName']}</strong>,</p>
                                    <p>A new appointment has been booked by administration.</p>
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
                    $doctorEmailSent = sendEmail($doctorInfo['doctorEmail'], $doctorSubject, $doctorMessage);
                    error_log("Admin booking - Doctor email sent: " . ($doctorEmailSent ? 'Yes' : 'No') . " to {$doctorInfo['doctorEmail']}");
                }
                
                // ========== CREATE NOTIFICATION FOR PATIENT ==========
createNotification(
    $patientInfo['userId'],
    'appointment',
    'Appointment Booked by Admin',
    "Your appointment with Dr. {$doctorInfo['doctorName']} has been booked for {$formattedDateTime} by administration.",
    "patient/view-appointments.php"
);

// ========== CREATE NOTIFICATION FOR DOCTOR ==========
if ($doctorInfo && $doctorInfo['doctorUserId']) {
    createNotification(
        $doctorInfo['doctorUserId'],
        'appointment',
        'New Appointment Booked by Admin',
        "New appointment with patient {$patientInfo['firstName']} {$patientInfo['lastName']} on {$formattedDateTime} (booked by admin).",
        "doctor/appointments.php?date=" . date('Y-m-d', strtotime($dateTime))
    );
}
                
                $pdo->commit();
                $bookingSuccess = true;
                $_SESSION['success'] = "Appointment booked successfully! Confirmation emails sent to patient and doctor.";
                logAction($userId, 'BOOK_APPOINTMENT_ADMIN', "Admin booked appointment ID: $appointmentId for patient $patientId with doctor $doctorId");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $bookingError = $e->getMessage();
                error_log("Admin appointment booking error: " . $e->getMessage());
            }
        }
    }
}

// Get available doctors
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

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-plus"></i> Book Appointment</h1>
            <p>Book an appointment for a patient</p>
        </div>
        <div class="header-actions">
            <a href="appointments.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Appointments
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
    
    <?php if ($bookingError): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($bookingError); ?>
        </div>
    <?php endif; ?>

    <?php if ($bookingSuccess): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> 
            Appointment booked successfully! 
            <a href="appointments.php" class="admin-btn admin-btn-primary admin-btn-sm" style="margin-left: auto;">View Appointments</a>
        </div>
    <?php endif; ?>

    <!-- Step 1: Find Patient -->
    <?php if (!$selectedPatient && !$bookingSuccess): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-search"></i> Step 1: Find Patient</h3>
            </div>
            <div class="admin-card-body">
                <form method="GET" class="admin-search-group">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" class="admin-form-control" style="flex: 1;">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="patients.php" class="admin-btn admin-btn-outline">View All Patients</a>
                    <?php if ($searchTerm || $patientId): ?>
                        <a href="book-appointment.php" class="admin-btn admin-btn-outline">Clear</a>
                    <?php endif; ?>
                </form>
                
                <?php if ($searchTerm && !empty($searchResults)): ?>
                    <div style="margin-top: 20px;">
                        <h4>Search Results (<?php echo count($searchResults); ?>)</h4>
                        <?php foreach ($searchResults as $p): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #dc2626;">
                                <div>
                                    <strong><?php echo htmlspecialchars($p['firstName'].' '.$p['lastName']); ?></strong><br>
                                    <small><?php echo $p['email']; ?> | <?php echo $p['phoneNumber']; ?></small>
                                </div>
                                <a href="?patient_id=<?php echo $p['patientId']; ?>" class="admin-btn admin-btn-primary admin-btn-sm">Select</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($searchTerm): ?>
                    <p class="admin-text-muted" style="margin-top: 20px;">No patients found.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Step 2: Book Appointment -->
    <?php if ($selectedPatient && !$bookingSuccess): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Selected Patient: <?php echo htmlspecialchars($selectedPatient['firstName'].' '.$selectedPatient['lastName']); ?></h3>
                <a href="book-appointment.php" class="admin-btn admin-btn-outline admin-btn-sm">Change Patient</a>
            </div>
            <div class="admin-card-body">
                <div class="admin-patient-info-grid">
                    <div class="admin-info-group">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($selectedPatient['firstName'].' '.$selectedPatient['lastName']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selectedPatient['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($selectedPatient['phoneNumber']); ?></p>
                    </div>
                    <div class="admin-info-group">
                        <p><strong>Date of Birth:</strong> <?php echo $selectedPatient['dateOfBirth'] ? date('M j, Y', strtotime($selectedPatient['dateOfBirth'])) : 'N/A'; ?></p>
                        <p><strong>Blood Type:</strong> <?php echo $selectedPatient['bloodType'] ?: 'N/A'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-calendar-plus"></i> Step 2: Book Appointment</h3>
            </div>
            <div class="admin-card-body">
                <form method="POST" id="appointment-form">
                    <input type="hidden" name="book_appointment" value="1">
                    <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patientId']; ?>">
                    
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label>Doctor <span class="required">*</span></label>
                            <select name="doctor_id" id="doctor_id" class="admin-form-control" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?php echo $d['doctorId']; ?>">
                                        Dr. <?php echo htmlspecialchars($d['firstName'].' '.$d['lastName']); ?> - <?php echo $d['specialization']; ?> ($<?php echo $d['consultationFee']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="appointment_date" id="appointment_date" class="admin-form-control"
                                   min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Closest Appointment Info -->
                    <div id="closest-appointment-info" class="closest-appointment-info" style="display: none;">
                        <div class="closest-badge">
                            <i class="fas fa-star"></i> Earliest Available Appointment
                        </div>
                        <p id="closest-appointment-text">
                            <i class="fas fa-spinner fa-spin"></i> Checking availability...
                        </p>
                        <button type="button" id="use-closest-appointment" class="admin-btn admin-btn-outline admin-btn-sm" style="display: none;">
                            <i class="fas fa-calendar-check"></i> Use This Time
                        </button>
                    </div>
                    
                    <div class="admin-form-group">
                        <label>Time <span class="required">*</span></label>
                        <div id="time-slots-container" class="admin-time-slots-container">
                            <p class="admin-text-muted">Select doctor and date first</p>
                        </div>
                        <input type="hidden" name="appointment_time" id="appointment_time">
                    </div>
                    
                    <div class="admin-form-group">
                        <label>Reason for Visit</label>
                        <textarea name="reason" rows="3" class="admin-form-control" placeholder="Briefly describe reason..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_booking" class="admin-btn admin-btn-primary">Book Appointment</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.admin-time-slots-container {
    min-height: 100px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #cbd5e1;
}
.admin-search-group {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
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
    background: #dc2626;
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
    background: white;
    border: 2px solid #dc2626;
    color: #dc2626;
    font-weight: 600;
}
#use-closest-appointment:hover {
    background: #dc2626;
    color: white;
}
.admin-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
}
.admin-time-slot {
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
.admin-time-slot:hover {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
    transform: scale(1.05);
}
.admin-time-slot.selected {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}
.admin-text-muted {
    color: #64748b;
    text-align: center;
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
            } catch (e) {
                console.error('Invalid JSON');
            }
            return null;
        } catch (error) {
            return null;
        }
    }
    
    async function loadTimeSlots(doctorId, date) {
        if (!doctorId || !date) {
            timeSlotsContainer.innerHTML = '<p class="admin-text-muted">Please select a doctor and date first</p>';
            return;
        }
        
        timeSlotsContainer.innerHTML = '<p class="admin-text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
        
        try {
            const response = await fetch(`../ajax/get-time-slots.php?doctor_id=${doctorId}&date=${date}`);
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (data.success && data.slots && data.slots.length > 0) {
                let html = '<div class="admin-time-slots">';
                data.slots.forEach(slot => {
                    html += `<div class="admin-time-slot" data-time="${slot.value}">${slot.display}</div>`;
                });
                html += '</div>';
                timeSlotsContainer.innerHTML = html;
                
                document.querySelectorAll('.admin-time-slot').forEach(slot => {
                    slot.addEventListener('click', function() {
                        document.querySelectorAll('.admin-time-slot').forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        if (timeInput) timeInput.value = this.getAttribute('data-time');
                    });
                });
            } else {
                timeSlotsContainer.innerHTML = '<p class="admin-text-muted">No available time slots for this date.</p>';
                if (timeInput) timeInput.value = '';
            }
        } catch (error) {
            timeSlotsContainer.innerHTML = '<p class="admin-text-muted">Error loading time slots.</p>';
        }
    }
    
    async function updateClosestAppointment() {
    const doctorId = doctorSelect?.value;
    if (!doctorId) {
        closestInfo.style.display = 'none';
        return;
    }
    
    closestInfo.style.display = 'block';
    closestText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding earliest available appointment...';
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
    } else {
        // Show a more helpful message
        closestText.innerHTML = '<i class="fas fa-calendar-times"></i> No available appointments found. The doctor may not have set their availability schedule.';
    }
}
    
    if (useClosestBtn) {
        useClosestBtn.addEventListener('click', function() {
            if (closestSlot) {
                dateInput.value = closestSlot.date;
                timeInput.value = closestSlot.time;
                loadTimeSlots(doctorSelect.value, closestSlot.date);
                setTimeout(() => {
                    document.querySelectorAll('.admin-time-slot').forEach(slot => {
                        if (slot.getAttribute('data-time') === closestSlot.time) {
                            slot.classList.add('selected');
                        }
                    });
                }, 500);
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
    
    if (doctorSelect?.value) {
        updateClosestAppointment();
    }
    
    const form = document.getElementById('appointment-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!timeInput || !timeInput.value) {
                e.preventDefault();
                alert('Please select a time slot for the appointment.');
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