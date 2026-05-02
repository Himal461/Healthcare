<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Book Appointment - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$searchTerm = $_GET['search'] ?? '';
$selectedPatient = null;
$patientId = $_GET['patient_id'] ?? null;

// Get staff details
$stmt = $pdo->prepare("
    SELECT s.*, CONCAT(u.firstName, ' ', u.lastName) as staffName
    FROM staff s JOIN users u ON s.userId = u.userId WHERE u.userId = ?
");
$stmt->execute([$userId]);
$staff = $stmt->fetch();

// Handle patient search
$searchResults = [];
if ($searchTerm) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber, p.dateOfBirth, p.bloodType
        FROM patients p JOIN users u ON p.userId = u.userId
        WHERE u.role = 'patient' AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)
        ORDER BY u.firstName LIMIT 20
    ");
    $searchLike = "%$searchTerm%";
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike]);
    $searchResults = $stmt->fetchAll();
}

// Get selected patient details
if ($patientId) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.userId, u.firstName, u.lastName, u.email, u.phoneNumber,
               p.dateOfBirth, p.bloodType, p.address, p.knownAllergies
        FROM patients p JOIN users u ON p.userId = u.userId 
        WHERE p.patientId = ? AND u.role = 'patient'
    ");
    $stmt->execute([$patientId]);
    $selectedPatient = $stmt->fetch();
}

// Handle appointment booking
$bookingError = null;
$bookingSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctorId = (int)$_POST['doctor_id'];
    $patientId = (int)$_POST['patient_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    if (!$doctorId || !$date || !$time) {
        $bookingError = "All fields are required.";
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
                
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM appointments 
                    WHERE doctorId = ? AND dateTime = ? 
                    AND status NOT IN ('cancelled', 'no-show')
                ");
                $checkStmt->execute([$doctorId, $dateTime]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("This time slot is already booked.");
                }
                
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
                
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (patientId, doctorId, dateTime, duration, reason, status, createdAt) 
                    VALUES (?, ?, ?, 30, ?, 'scheduled', NOW())
                ");
                $stmt->execute([$patientId, $doctorId, $dateTime, $reason]);
                $appointmentId = $pdo->lastInsertId();
                
                // ========== GET PATIENT DETAILS ==========
                $patientStmt = $pdo->prepare("
                    SELECT u.userId, u.firstName, u.lastName, u.email, u.phoneNumber 
                    FROM patients p JOIN users u ON p.userId = u.userId WHERE p.patientId = ?
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
                        <head><style>body{font-family:Arial}.container{max-width:600px;margin:0 auto;padding:20px}.header{background:#f59e0b;color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0}.content{background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px}</style></head>
                        <body><div class='container'><div class='header'><h2>✓ Appointment Confirmed</h2></div>
                        <div class='content'><p>Dear <strong>{$patientInfo['firstName']} {$patientInfo['lastName']}</strong>,</p>
                        <p>Your appointment has been booked by our reception staff.</p>
                        <p><strong>Doctor:</strong> Dr. {$doctorInfo['doctorName']} ({$doctorInfo['specialization']})</p>
                        <p><strong>Date & Time:</strong> {$formattedDateTime}</p>
                        <p><strong>Reason:</strong> " . ($reason ?: 'General Consultation') . "</p>
                        <p>Please arrive 15 minutes before your appointment time.</p></div></div></body></html>
                    ";
                    sendEmail($patientInfo['email'], $patientSubject, $patientMessage);
                }
                
                // ========== SEND EMAIL TO DOCTOR ==========
                if ($doctorInfo && !empty($doctorInfo['doctorEmail'])) {
                    $doctorSubject = "New Appointment Booked by Reception - " . SITE_NAME;
                    $doctorMessage = "
                        <!DOCTYPE html>
                        <html>
                        <head><style>body{font-family:Arial}.container{max-width:600px;margin:0 auto;padding:20px}.header{background:#2563eb;color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0}.content{background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px}</style></head>
                        <body><div class='container'><div class='header'><h2>📅 New Appointment Booked</h2></div>
                        <div class='content'><p>Dear Dr. <strong>{$doctorInfo['doctorName']}</strong>,</p>
                        <p>A new appointment has been booked by reception.</p>
                        <p><strong>Patient:</strong> {$patientInfo['firstName']} {$patientInfo['lastName']}</p>
                        <p><strong>Date & Time:</strong> {$formattedDateTime}</p>
                        <p><strong>Reason:</strong> " . ($reason ?: 'General Consultation') . "</p>
                        <p><strong>Contact:</strong> {$patientInfo['phoneNumber']} | {$patientInfo['email']}</p></div></div></body></html>
                    ";
                    sendEmail($doctorInfo['doctorEmail'], $doctorSubject, $doctorMessage);
                }
                
                // ========== CREATE NOTIFICATION FOR PATIENT ==========
createNotification(
    $patientInfo['userId'],
    'appointment',
    'Appointment Booked by Reception',
    "Your appointment with Dr. {$doctorInfo['doctorName']} has been booked for {$formattedDateTime}.",
    "patient/view-appointments.php"
);

// ========== CREATE NOTIFICATION FOR DOCTOR ==========
if ($doctorInfo && $doctorInfo['doctorUserId']) {
    createNotification(
        $doctorInfo['doctorUserId'],
        'appointment',
        'New Appointment Booked by Reception',
        "New appointment with patient {$patientInfo['firstName']} {$patientInfo['lastName']} on {$formattedDateTime}.",
        "doctor/appointments.php?date=" . date('Y-m-d', strtotime($dateTime))
    );
}
                
                $pdo->commit();
                $bookingSuccess = true;
                $_SESSION['success'] = "Appointment booked successfully!";
                logAction($userId, 'APPOINTMENT_BOOK', "Staff booked appointment for patient ID: $patientId with doctor ID: $doctorId");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $bookingError = $e->getMessage();
                error_log("Staff appointment booking error: " . $e->getMessage());
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
    ORDER BY u.firstName, u.lastName
")->fetchAll();

// Get recent patients
$recentPatients = $pdo->query("
    SELECT DISTINCT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber,
           (SELECT MAX(dateTime) FROM appointments WHERE patientId = p.patientId) as last_visit
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.role = 'patient'
    ORDER BY last_visit DESC, u.firstName ASC
    LIMIT 10
")->fetchAll();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-plus"></i> Book Appointment</h1>
            <p>Search for a patient and book an appointment on their behalf</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="staff-alert staff-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="staff-alert staff-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($bookingError): ?>
        <div class="staff-alert staff-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($bookingError); ?>
        </div>
    <?php endif; ?>

    <?php if ($bookingSuccess): ?>
        <div class="staff-alert staff-alert-success">
            <i class="fas fa-check-circle"></i> 
            Appointment booked successfully! 
            <a href="dashboard.php" class="staff-btn staff-btn-primary staff-btn-sm" style="margin-left: auto;">Return to Dashboard</a>
        </div>
    <?php endif; ?>

    <!-- Step 1: Find Patient -->
    <?php if (!$selectedPatient && !$bookingSuccess): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-search"></i> Step 1: Find Patient</h3>
            </div>
            <div class="staff-card-body">
                <form method="GET" class="staff-search-group">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" class="staff-search-input">
                    <button type="submit" class="staff-btn staff-btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($searchTerm || $patientId): ?>
                        <a href="book-appointment.php" class="staff-btn staff-btn-outline">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if ($searchTerm): ?>
                    <div class="staff-search-results">
                        <h4>Search Results (<?php echo count($searchResults); ?> found)</h4>
                        <?php if (empty($searchResults)): ?>
                            <p class="staff-text-muted">No patients found. <a href="register-patient.php">Register a new patient</a></p>
                        <?php else: ?>
                            <div class="staff-patient-list">
                                <?php foreach ($searchResults as $patient): ?>
                                    <div class="staff-patient-item">
                                        <div class="staff-patient-info">
                                            <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong>
                                            <small><?php echo htmlspecialchars($patient['email']); ?> | <?php echo htmlspecialchars($patient['phoneNumber']); ?></small>
                                        </div>
                                        <a href="?patient_id=<?php echo $patient['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">
                                            Select Patient
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$searchTerm && !$selectedPatient && !empty($recentPatients)): ?>
                    <div class="staff-recent-patients">
                        <h4><i class="fas fa-clock"></i> Recent Patients</h4>
                        <div class="staff-patient-list">
                            <?php foreach ($recentPatients as $recent): ?>
                                <div class="staff-patient-item">
                                    <div class="staff-patient-info">
                                        <strong><?php echo htmlspecialchars($recent['firstName'] . ' ' . $recent['lastName']); ?></strong>
                                        <small><?php echo htmlspecialchars($recent['email']); ?> | <?php echo htmlspecialchars($recent['phoneNumber']); ?></small>
                                    </div>
                                    <a href="?patient_id=<?php echo $recent['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">
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

    <!-- Step 2: Book Appointment -->
    <?php if ($selectedPatient && !$bookingSuccess): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-calendar-plus"></i> Step 2: Book Appointment for <?php echo htmlspecialchars($selectedPatient['firstName'] . ' ' . $selectedPatient['lastName']); ?></h3>
                <a href="book-appointment.php" class="staff-btn staff-btn-outline staff-btn-sm">Change Patient</a>
            </div>
            <div class="staff-card-body">
                <div class="staff-selected-patient">
                    <div class="staff-patient-info-card">
                        <div class="staff-info-row"><strong>Name:</strong> <?php echo htmlspecialchars($selectedPatient['firstName'] . ' ' . $selectedPatient['lastName']); ?></div>
                        <div class="staff-info-row"><strong>Email:</strong> <?php echo htmlspecialchars($selectedPatient['email']); ?></div>
                        <div class="staff-info-row"><strong>Phone:</strong> <?php echo htmlspecialchars($selectedPatient['phoneNumber']); ?></div>
                        <div class="staff-info-row"><strong>DOB:</strong> <?php echo $selectedPatient['dateOfBirth'] ? date('M j, Y', strtotime($selectedPatient['dateOfBirth'])) : 'N/A'; ?></div>
                        <div class="staff-info-row"><strong>Blood Type:</strong> <?php echo $selectedPatient['bloodType'] ?: 'N/A'; ?></div>
                        <div class="staff-info-row"><strong>Allergies:</strong> <?php echo htmlspecialchars($selectedPatient['knownAllergies'] ?: 'None'); ?></div>
                    </div>
                </div>

                <form method="POST" id="appointment-form" style="margin-top: 25px;">
                    <input type="hidden" name="book_appointment" value="1">
                    <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patientId']; ?>">
                    
                    <div class="staff-form-row">
                        <div class="staff-form-group">
                            <label for="doctor_id">Select Doctor <span class="required">*</span></label>
                            <select id="doctor_id" name="doctor_id" class="staff-form-control" required>
                                <option value="">Choose a doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['doctorId']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?> 
                                        - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                        ($<?php echo number_format($doctor['consultationFee'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="staff-form-group">
                            <label for="appointment_date">Date <span class="required">*</span></label>
                            <input type="date" id="appointment_date" name="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                                   class="staff-form-control" required>
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
                        <button type="button" id="use-closest-appointment" class="staff-btn staff-btn-outline staff-btn-sm" style="display: none;">
                            <i class="fas fa-calendar-check"></i> Use This Time
                        </button>
                    </div>
                    
                    <div class="staff-form-group">
                        <label>Select Time <span class="required">*</span></label>
                        <div id="time-slots-container" class="staff-time-slots-container">
                            <p class="staff-text-muted">Please select a doctor and date first</p>
                        </div>
                        <input type="hidden" id="appointment_time" name="appointment_time">
                    </div>
                    
                    <div class="staff-form-group">
                        <label for="reason">Reason for Visit</label>
                        <textarea id="reason" name="reason" rows="3" class="staff-form-control" 
                                  placeholder="Briefly describe the reason for visit"></textarea>
                    </div>
                    
                    <button type="submit" name="submit_booking" class="staff-btn staff-btn-primary">
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
    background: #f59e0b;
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
    border: 2px solid #f59e0b;
    color: #f59e0b;
    font-weight: 600;
}
#use-closest-appointment:hover {
    background: #f59e0b;
    color: white;
}
.staff-time-slots-container {
    min-height: 100px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #cbd5e1;
}
.staff-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
}
.staff-time-slot {
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
.staff-time-slot:hover {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
    transform: scale(1.05);
}
.staff-time-slot.selected {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}
.staff-text-muted {
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
            timeSlotsContainer.innerHTML = '<p class="staff-text-muted">Please select a doctor and date first</p>';
            return;
        }
        
        timeSlotsContainer.innerHTML = '<p class="staff-text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
        
        try {
            const response = await fetch(`../ajax/get-time-slots.php?doctor_id=${doctorId}&date=${date}`);
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (data.success && data.slots && data.slots.length > 0) {
                let html = '<div class="staff-time-slots">';
                data.slots.forEach(slot => {
                    html += `<div class="staff-time-slot" data-time="${slot.value}">${slot.display}</div>`;
                });
                html += '</div>';
                timeSlotsContainer.innerHTML = html;
                
                document.querySelectorAll('.staff-time-slot').forEach(slot => {
                    slot.addEventListener('click', function() {
                        document.querySelectorAll('.staff-time-slot').forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        if (timeInput) timeInput.value = this.getAttribute('data-time');
                    });
                });
            } else {
                timeSlotsContainer.innerHTML = '<p class="staff-text-muted">No available time slots for this date.</p>';
                if (timeInput) timeInput.value = '';
            }
        } catch (error) {
            timeSlotsContainer.innerHTML = '<p class="staff-text-muted">Error loading time slots.</p>';
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
                    document.querySelectorAll('.staff-time-slot').forEach(slot => {
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
            return confirm('Confirm this appointment booking?');
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>