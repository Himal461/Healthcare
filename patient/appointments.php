<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Appointments - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'];

// Handle appointment booking
$bookingError = null;
$alternativeSlots = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctorId = $_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $dateTime = $date . ' ' . $time;
    $reason = sanitizeInput($_POST['reason']);

    // Check availability first
    $availability = suggestAlternativeSlots($doctorId, $dateTime);
    
    if ($availability['available']) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO appointments (patientId, doctorId, dateTime, reason, status) VALUES (?, ?, ?, ?, 'scheduled')");
            $stmt->execute([$patientId, $doctorId, $dateTime, $reason]);
            
            $appointmentId = $pdo->lastInsertId();
            
            // Create notification for doctor
            $doctorStmt = $pdo->prepare("
                SELECT s.userId, u.firstName, u.lastName 
                FROM doctors d 
                JOIN staff s ON d.staffId = s.staffId 
                JOIN users u ON s.userId = u.userId 
                WHERE d.doctorId = ?
            ");
            $doctorStmt->execute([$doctorId]);
            $doctor = $doctorStmt->fetch();
            
            createNotification(
                $doctor['userId'],
                'appointment',
                'New Appointment Booked',
                "A new appointment has been booked with you for " . date('M j, Y g:i A', strtotime($dateTime))
            );
            
            $pdo->commit();
            
            $_SESSION['success'] = "Appointment booked successfully!";
            logAction($userId, 'APPOINTMENT_BOOK', "Booked appointment with doctor ID: $doctorId");
            
            header("Location: appointments.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $bookingError = "Failed to book appointment. Please try again.";
        }
    } else {
        $bookingError = "The selected time slot is not available.";
        $alternativeSlots = $availability['alternatives'];
    }
}

// Handle appointment cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $appointmentId = $_GET['cancel'];
    
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT a.*, d.doctorId, u.firstName as doctorFirstName, u.lastName as doctorLastName
        FROM appointments a 
        JOIN patients p ON a.patientId = p.patientId 
        JOIN doctors d ON a.doctorId = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        WHERE p.userId = ? AND a.appointmentId = ?
    ");
    $stmt->execute([$userId, $appointmentId]);
    $appointment = $stmt->fetch();
    
    if ($appointment && $appointment['status'] === 'scheduled') {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancellationReason = ? WHERE appointmentId = ?");
        $stmt->execute(['Cancelled by patient', $appointmentId]);
        
        // Notify doctor
        $doctorStmt = $pdo->prepare("SELECT userId FROM doctors d JOIN staff s ON d.staffId = s.staffId WHERE d.doctorId = ?");
        $doctorStmt->execute([$appointment['doctorId']]);
        $doctorUser = $doctorStmt->fetch();
        
        if ($doctorUser) {
            createNotification(
                $doctorUser['userId'],
                'appointment',
                'Appointment Cancelled',
                "An appointment with patient " . $appointment['firstName'] . " " . $appointment['lastName'] . " has been cancelled."
            );
        }
        
        $_SESSION['success'] = "Appointment cancelled successfully!";
        logAction($userId, 'APPOINTMENT_CANCEL', "Cancelled appointment ID: $appointmentId");
        
        header("Location: appointments.php");
        exit();
    } else {
        $bookingError = "Appointment not found or cannot be cancelled.";
    }
}

// Get upcoming appointments
$upcomingStmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.firstName, u.lastName 
    FROM appointments a 
    JOIN doctors d ON a.doctorId = d.doctorId 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE a.patientId = ? AND a.dateTime > NOW() AND a.status NOT IN ('cancelled', 'no-show')
    ORDER BY a.dateTime ASC
");
$upcomingStmt->execute([$patientId]);
$upcomingAppointments = $upcomingStmt->fetchAll();

// Get past appointments
$pastStmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.firstName, u.lastName 
    FROM appointments a 
    JOIN doctors d ON a.doctorId = d.doctorId 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE a.patientId = ? AND (a.dateTime <= NOW() OR a.status IN ('cancelled', 'completed', 'no-show'))
    ORDER BY a.dateTime DESC
    LIMIT 20
");
$pastStmt->execute([$patientId]);
$pastAppointments = $pastStmt->fetchAll();

// Get available doctors for booking
$doctors = $pdo->query("
    SELECT d.doctorId, u.firstName, u.lastName, d.specialization, d.consultationFee,
           d.yearsOfExperience, d.biography, d.isAvailable
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE d.isAvailable = 1
    ORDER BY u.firstName, u.lastName
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>My Appointments</h1>
        <p>Manage your medical appointments and book new ones</p>
    </div>

    <?php if (isset($bookingError)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $bookingError; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($alternativeSlots)): ?>
        <div class="alternative-slots">
            <h4><i class="fas fa-clock"></i> Suggested Alternative Times:</h4>
            <div class="alternative-list">
                <?php foreach ($alternativeSlots as $alt): ?>
                    <div class="alternative-item" data-date="<?php echo $alt['date']; ?>" data-time="<?php echo $alt['time_value']; ?>">
                        <?php echo $alt['date_formatted']; ?> at <?php echo $alt['time']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Book Appointment Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Book New Appointment</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="appointment-form">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="doctor_id">Select Doctor *</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">Choose a doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['doctorId']; ?>" 
                                    <?php echo (isset($_GET['doctor_id']) && $_GET['doctor_id'] == $doctor['doctorId']) ? 'selected' : ''; ?>
                                    <?php echo (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doctor['doctorId']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo $doctor['firstName'] . ' ' . $doctor['lastName']; ?> 
                                    - <?php echo $doctor['specialization']; ?>
                                    (Fee: $<?php echo number_format($doctor['consultationFee'] ?? '150', 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="helper-text">
                            <i class="fas fa-info-circle"></i> 
                            <a href="<?php echo SITE_URL; ?>/doctors.php" target="_blank" class="view-doctors-link">
                                View all doctors and their profiles
                            </a>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_date">Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                               value="<?php echo $_POST['appointment_date'] ?? ''; ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_time">Time *</label>
                        <input type="time" id="appointment_time" name="appointment_time" 
                               min="<?php echo WORKING_HOURS_START; ?>" 
                               max="<?php echo WORKING_HOURS_END; ?>"
                               value="<?php echo $_POST['appointment_time'] ?? ''; ?>"
                               required>
                        <div id="time-slots-container"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Reason for Visit</label>
                        <textarea id="reason" name="reason" rows="3" 
                                  placeholder="Briefly describe your symptoms or reason for visit"><?php echo $_POST['reason'] ?? ''; ?></textarea>
                    </div>
                </div>
                
                <div id="availability-result"></div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="book_appointment" class="btn btn-primary">
                        <i class="fas fa-check"></i> Book Appointment
                    </button>
                    <button type="button" class="btn btn-outline" onclick="checkAvailability()">
                        <i class="fas fa-search"></i> Check Availability
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
        </div>
        <div class="table-container">
            <table>
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
                                <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                                <td data-label="Doctor">
                                    <a href="<?php echo SITE_URL; ?>/doctors.php?doctor_id=<?php echo $appointment['doctorId']; ?>" class="doctor-link">
                                        Dr. <?php echo $appointment['firstName'] . ' ' . $appointment['lastName']; ?>
                                    </a>
                                </td>
                                <td data-label="Specialization"><?php echo $appointment['specialization']; ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <a href="?cancel=<?php echo $appointment['appointmentId']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to cancel this appointment?')">
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
    <?php if (!empty($pastAppointments)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Past Appointments</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pastAppointments as $appointment): ?>
                        <tr>
                            <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                            <td data-label="Doctor">
                                <a href="<?php echo SITE_URL; ?>/doctors.php?doctor_id=<?php echo $appointment['doctorId']; ?>" class="doctor-link">
                                    Dr. <?php echo $appointment['firstName'] . ' ' . $appointment['lastName']; ?>
                                </a>
                            </td>
                            <td data-label="Specialization"><?php echo $appointment['specialization']; ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                            <td data-label="Notes"><?php echo $appointment['notes'] ?: '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.card {
    background: white;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.card-header h3 {
    color: #1a75bc;
    font-size: 18px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-body {
    padding: 25px;
}

.helper-text {
    display: block;
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

.helper-text i {
    margin-right: 4px;
}

.view-doctors-link {
    color: #1a75bc;
    text-decoration: none;
    font-weight: 500;
}

.view-doctors-link:hover {
    text-decoration: underline;
}

.doctor-link {
    color: #1a75bc;
    text-decoration: none;
    font-weight: 500;
}

.doctor-link:hover {
    text-decoration: underline;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-scheduled {
    background: #d4edda;
    color: #155724;
}

.status-confirmed {
    background: #cce5ff;
    color: #004085;
}

.status-in-progress {
    background: #fff3cd;
    color: #856404;
}

.status-completed {
    background: #d1ecf1;
    color: #0c5460;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.status-no-show {
    background: #e2e3e5;
    color: #383d41;
}

.alternative-slots {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 8px;
}

.alternative-slots h4 {
    color: #856404;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alternative-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.alternative-item {
    background: white;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    border: 1px solid #ffc107;
    transition: all 0.3s ease;
    font-size: 14px;
}

.alternative-item:hover {
    background: #ffc107;
    color: #333;
}

.time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, auto));
    gap: 10px;
    margin-top: 10px;
}

.time-slot {
    padding: 8px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 13px;
}

.time-slot:hover {
    background: #1a75bc;
    color: white;
    border-color: #1a75bc;
}

.time-slot.selected {
    background: #1a75bc;
    color: white;
    border-color: #1a75bc;
}

.btn-sm {
    padding: 5px 12px;
    font-size: 12px;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.table-container {
    overflow-x: auto;
    padding: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table thead {
    background: #f8f9fa;
}

table th,
table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

table th {
    font-weight: 600;
    color: #555;
}

table tbody tr:hover {
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .card-body {
        padding: 15px;
    }
    
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    table thead {
        display: none;
    }
    
    table tbody tr {
        display: block;
        margin-bottom: 15px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
    }
    
    table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        border-bottom: 1px solid #e9ecef;
    }
    
    table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        margin-right: 15px;
    }
    
    table tbody td:last-child {
        border-bottom: none;
    }
}
</style>

<script>
// Load time slots when doctor or date changes
document.getElementById('doctor_id').addEventListener('change', function() {
    const date = document.getElementById('appointment_date').value;
    if (date) {
        loadTimeSlots(date);
    }
});

document.getElementById('appointment_date').addEventListener('change', function() {
    loadTimeSlots(this.value);
});

document.getElementById('appointment_time').addEventListener('change', function() {
    checkAvailability();
});

async function loadTimeSlots(date) {
    const doctorSelect = document.getElementById('doctor_id');
    if (!doctorSelect || !doctorSelect.value) return;
    
    const timeSlotContainer = document.getElementById('time-slots-container');
    if (!timeSlotContainer) return;
    
    timeSlotContainer.innerHTML = '<div class="loading">Loading available times...</div>';
    
    try {
        const response = await fetch(`../ajax/get-time-slots.php?doctor_id=${doctorSelect.value}&date=${date}`);
        const data = await response.json();
        
        if (data.success && data.slots.length > 0) {
            let html = '<div class="time-slots">';
            data.slots.forEach(slot => {
                html += `<div class="time-slot" data-time="${slot.value}">${slot.start}</div>`;
            });
            html += '</div>';
            timeSlotContainer.innerHTML = html;
            
            // Re-initialize time slot click handlers
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    const timeInput = document.getElementById('appointment_time');
                    if (timeInput) {
                        timeInput.value = this.getAttribute('data-time');
                        checkAvailability();
                    }
                });
            });
        } else {
            timeSlotContainer.innerHTML = '<p class="alert alert-warning">No available time slots for this date. Please select another date.</p>';
        }
    } catch (error) {
        console.error('Error loading time slots:', error);
        timeSlotContainer.innerHTML = '<p class="alert alert-error">Error loading time slots. Please try again.</p>';
    }
}

async function checkAvailability() {
    const doctorId = document.getElementById('doctor_id')?.value;
    const date = document.getElementById('appointment_date')?.value;
    const time = document.getElementById('appointment_time')?.value;
    
    if (!doctorId || !date || !time) return;
    
    const datetime = `${date} ${time}`;
    const resultContainer = document.getElementById('availability-result');
    
    if (resultContainer) {
        resultContainer.innerHTML = '<div class="loading">Checking availability...</div>';
        
        try {
            const response = await fetch(`../ajax/check-availability.php?doctor_id=${doctorId}&datetime=${encodeURIComponent(datetime)}`);
            const data = await response.json();
            
            if (data.available) {
                resultContainer.innerHTML = '<div class="alert alert-success">✓ Time slot is available!</div>';
            } else if (data.alternatives && data.alternatives.length > 0) {
                let html = '<div class="alternative-slots">';
                html += '<h4><i class="fas fa-clock"></i> Suggested Alternative Times:</h4>';
                html += '<div class="alternative-list">';
                data.alternatives.forEach(alt => {
                    html += `<div class="alternative-item" data-date="${alt.date}" data-time="${alt.time_value}">
                                ${alt.date_formatted} at ${alt.time}
                            </div>`;
                });
                html += '</div></div>';
                resultContainer.innerHTML = html;
                
                // Re-initialize alternative item handlers
                document.querySelectorAll('.alternative-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const dateInput = document.getElementById('appointment_date');
                        const timeInput = document.getElementById('appointment_time');
                        if (dateInput) dateInput.value = this.getAttribute('data-date');
                        if (timeInput) timeInput.value = this.getAttribute('data-time');
                        checkAvailability();
                        loadTimeSlots(this.getAttribute('data-date'));
                    });
                });
            } else {
                resultContainer.innerHTML = '<div class="alert alert-error">✗ This time slot is not available. Please select another time.</div>';
            }
        } catch (error) {
            console.error('Error checking availability:', error);
            resultContainer.innerHTML = '<div class="alert alert-error">Error checking availability. Please try again.</div>';
        }
    }
}

// Alternative item click handler for existing alternatives
document.querySelectorAll('.alternative-item').forEach(item => {
    item.addEventListener('click', function() {
        const dateInput = document.getElementById('appointment_date');
        const timeInput = document.getElementById('appointment_time');
        if (dateInput) dateInput.value = this.getAttribute('data-date');
        if (timeInput) timeInput.value = this.getAttribute('data-time');
        checkAvailability();
        loadTimeSlots(this.getAttribute('data-date'));
        
        // Scroll to form
        document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
    });
});

// Initialize if doctor is pre-selected from URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const doctorId = urlParams.get('doctor_id');
    if (doctorId) {
        const doctorSelect = document.getElementById('doctor_id');
        if (doctorSelect) {
            doctorSelect.value = doctorId;
            const dateInput = document.getElementById('appointment_date');
            if (dateInput && dateInput.value) {
                loadTimeSlots(dateInput.value);
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>