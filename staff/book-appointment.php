<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Book Appointment for Patient - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get staff details
$stmt = $pdo->prepare("
    SELECT s.*, CONCAT(u.firstName, ' ', u.lastName) as staffName
    FROM staff s
    JOIN users u ON s.userId = u.userId
    WHERE u.userId = ?
");
$stmt->execute([$userId]);
$staff = $stmt->fetch();

// Handle search patient
$searchTerm = $_GET['search'] ?? '';
$selectedPatient = null;
$patientId = null;

if (isset($_GET['patient_id'])) {
    $patientId = $_GET['patient_id'];
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.userId, u.firstName, u.lastName, u.email, u.phoneNumber,
               p.dateOfBirth, p.bloodType, p.address
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE p.patientId = ?
    ");
    $stmt->execute([$patientId]);
    $selectedPatient = $stmt->fetch();
}

// Handle patient search results
$searchResults = [];
if ($searchTerm) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.userId, u.firstName, u.lastName, u.email, u.phoneNumber
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?
        ORDER BY u.firstName
        LIMIT 20
    ");
    $searchLike = "%$searchTerm%";
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike]);
    $searchResults = $stmt->fetchAll();
}

// Handle appointment booking
$bookingError = null;
$alternativeSlots = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctorId = $_POST['doctor_id'];
    $patientId = $_POST['patient_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $dateTime = $date . ' ' . $time;
    $reason = sanitizeInput($_POST['reason']);
    
    if (empty($time)) {
        $bookingError = "Please select a time slot for the appointment.";
    } elseif (empty($patientId)) {
        $bookingError = "Please select a patient first.";
    } else {
        // Check availability
        $availability = suggestAlternativeSlots($doctorId, $dateTime);
        
        if ($availability['available']) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO appointments (patientId, doctorId, dateTime, reason, status) VALUES (?, ?, ?, ?, 'scheduled')");
                $stmt->execute([$patientId, $doctorId, $dateTime, $reason]);
                
                $appointmentId = $pdo->lastInsertId();
                
                // Get patient details for notification
                $patientStmt = $pdo->prepare("
                    SELECT u.email, u.firstName, u.lastName, u.userId
                    FROM patients p
                    JOIN users u ON p.userId = u.userId
                    WHERE p.patientId = ?
                ");
                $patientStmt->execute([$patientId]);
                $patient = $patientStmt->fetch();
                
                // Create notification for patient
                createNotification(
                    $patient['userId'],
                    'appointment',
                    'Appointment Booked',
                    "Your appointment has been booked for " . date('M j, Y g:i A', strtotime($dateTime)) . " by reception."
                );
                
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
                    "A new appointment has been booked for " . date('M j, Y g:i A', strtotime($dateTime)) . " with patient: " . $patient['firstName'] . " " . $patient['lastName']
                );
                
                $pdo->commit();
                
                $_SESSION['success'] = "Appointment booked successfully for " . $patient['firstName'] . " " . $patient['lastName'] . "!";
                logAction($userId, 'APPOINTMENT_BOOK', "Booked appointment for patient ID: $patientId with doctor ID: $doctorId");
                
                header("Location: book-appointment.php");
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
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Book Appointment for Patient</h1>
        <p>Search for a patient and book an appointment on their behalf</p>
    </div>

    <?php if ($bookingError): ?>
        <div class="alert alert-error"><?php echo $bookingError; ?></div>
    <?php endif; ?>

    <?php if (!empty($alternativeSlots)): ?>
        <div class="alternative-slots">
            <h4>Suggested Alternative Times:</h4>
            <div class="alternative-list">
                <?php foreach ($alternativeSlots as $alt): ?>
                    <div class="alternative-item" data-date="<?php echo $alt['date']; ?>" data-time="<?php echo $alt['time_value']; ?>">
                        <?php echo $alt['date_formatted']; ?> at <?php echo $alt['time']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Patient Search -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-search"></i> Step 1: Find Patient</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="search-form">
                <div class="search-group">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>

            <?php if ($searchTerm): ?>
                <div class="search-results">
                    <h4>Search Results (<?php echo count($searchResults); ?> found)</h4>
                    <?php if (empty($searchResults)): ?>
                        <p class="text-muted">No patients found. <a href="../staff/register-patient.php">Register a new patient</a></p>
                    <?php else: ?>
                        <div class="patient-list">
                            <?php foreach ($searchResults as $patient): ?>
                                <div class="patient-item">
                                    <div class="patient-info">
                                        <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong><br>
                                        <small><?php echo $patient['email']; ?> | <?php echo $patient['phoneNumber']; ?></small>
                                    </div>
                                    <a href="?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-primary btn-sm">
                                        Select Patient
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($selectedPatient): ?>
                <div class="selected-patient">
                    <h4><i class="fas fa-user-check"></i> Selected Patient</h4>
                    <div class="patient-info-card">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($selectedPatient['firstName'] . ' ' . $selectedPatient['lastName']); ?></p>
                        <p><strong>Email:</strong> <?php echo $selectedPatient['email']; ?></p>
                        <p><strong>Phone:</strong> <?php echo $selectedPatient['phoneNumber']; ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $selectedPatient['dateOfBirth'] ?: 'N/A'; ?></p>
                        <p><strong>Blood Type:</strong> <?php echo $selectedPatient['bloodType'] ?: 'N/A'; ?></p>
                        <a href="book-appointment.php" class="btn btn-outline btn-sm">Change Patient</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Book Appointment Form -->
    <?php if ($selectedPatient): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Step 2: Book Appointment for <?php echo htmlspecialchars($selectedPatient['firstName'] . ' ' . $selectedPatient['lastName']); ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="appointment-form">
                <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patientId']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="doctor_id">Select Doctor *</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">Choose a doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['doctorId']; ?>">
                                    Dr. <?php echo $doctor['firstName'] . ' ' . $doctor['lastName']; ?> 
                                    - <?php echo $doctor['specialization']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_date">Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                               required>
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
                    <label for="reason">Reason for Visit</label>
                    <textarea id="reason" name="reason" rows="3" placeholder="Briefly describe the reason for visit"></textarea>
                </div>
                
                <div id="availability-result"></div>
                
                <button type="submit" name="book_appointment" class="btn btn-primary">
                    <i class="fas fa-check"></i> Book Appointment
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput = document.getElementById('appointment_time');
    const availabilityResult = document.getElementById('availability-result');
    
    if (!doctorSelect || !dateInput) return;
    
    function loadTimeSlots() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;
        
        if (!doctorId || !date) {
            timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a doctor and date first</p>';
            return;
        }
        
        timeSlotsContainer.innerHTML = '<p class="text-muted">Loading available times...</p>';
        
        fetch(`../ajax/get-time-slots.php?doctor_id=${doctorId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.slots && data.slots.length > 0) {
                    let html = '<div class="time-slots">';
                    data.slots.forEach(slot => {
                        html += `<div class="time-slot" data-time="${slot.value}">${slot.start}</div>`;
                    });
                    html += '</div>';
                    timeSlotsContainer.innerHTML = html;
                    
                    document.querySelectorAll('.time-slot').forEach(slot => {
                        slot.addEventListener('click', function() {
                            document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                            this.classList.add('selected');
                            timeInput.value = this.getAttribute('data-time');
                            
                            if (availabilityResult) {
                                availabilityResult.innerHTML = '';
                            }
                        });
                    });
                } else {
                    timeSlotsContainer.innerHTML = '<p class="text-muted">No available time slots for this date</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                timeSlotsContainer.innerHTML = '<p class="text-muted">Error loading time slots. Please try again.</p>';
            });
    }
    
    doctorSelect.addEventListener('change', loadTimeSlots);
    dateInput.addEventListener('change', loadTimeSlots);
    
    // Alternative item click handler
    document.querySelectorAll('.alternative-item').forEach(item => {
        item.addEventListener('click', function() {
            const altDate = this.getAttribute('data-date');
            const altTime = this.getAttribute('data-time');
            if (dateInput) dateInput.value = altDate;
            if (timeInput) timeInput.value = altTime;
            loadTimeSlots();
            
            setTimeout(() => {
                document.querySelectorAll('.time-slot').forEach(slot => {
                    if (slot.getAttribute('data-time') === altTime) {
                        slot.classList.add('selected');
                    }
                });
            }, 100);
            
            if (availabilityResult) {
                availabilityResult.innerHTML = '<div class="alert alert-success">Alternative time selected! You can now book this appointment.</div>';
            }
        });
    });
    
    // Form submission validation
    const form = document.getElementById('appointment-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!timeInput.value) {
                e.preventDefault();
                alert('Please select a time slot for the appointment.');
                return false;
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>