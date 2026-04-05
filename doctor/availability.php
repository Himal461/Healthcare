<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Set Availability - HealthManagement";
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
$doctorId = $doctor['doctorId'];

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        for ($i = 0; $i <= 6; $i++) {
            $isAvailable = isset($_POST["day_{$i}_available"]) ? 1 : 0;
            $startTime = $_POST["day_{$i}_start"] ?? '09:00';
            $endTime   = $_POST["day_{$i}_end"] ?? '17:00';
            
            if (!$isAvailable) {
                $startTime = '00:00';
                $endTime   = '00:00';
            }
            
            if ($isAvailable && $startTime >= $endTime) {
                throw new Exception("Invalid time range for " . $i);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO doctor_availability 
                (doctorId, dayOfWeek, startTime, endTime, isAvailable)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    startTime = VALUES(startTime),
                    endTime = VALUES(endTime),
                    isAvailable = VALUES(isAvailable),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$doctorId, $i, $startTime, $endTime, $isAvailable]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Availability saved successfully!";
        logAction($userId, 'UPDATE_AVAILABILITY', "Updated availability schedule for doctor ID: $doctorId");
        header("Location: availability.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch current availability
$stmt = $pdo->prepare("
    SELECT * FROM doctor_availability 
    WHERE doctorId = ?
    ORDER BY dayOfWeek
");
$stmt->execute([$doctorId]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$availability = [];
foreach ($data as $row) {
    $availability[$row['dayOfWeek']] = $row;
}

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$timeSlots = [
    '09:00', '09:30', '10:00', '10:30',
    '11:00', '11:30', '12:00', '12:30',
    '14:00', '14:30', '15:00', '15:30',
    '16:00', '16:30', '17:00'
];

$currentDayOfWeek = date('w');
$isWednesday = ($currentDayOfWeek == 3);
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Set Weekly Availability</h1>
        <p>Configure your working hours for each day of the week</p>
    </div>

    <?php if ($isWednesday): ?>
        <div class="alert alert-warning">
            <i class="fas fa-bell"></i>
            <strong>Reminder:</strong> Please review and update your availability for the upcoming week.
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Current Availability Summary -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-check"></i> Current Availability Summary</h3>
        </div>
        <div class="card-body">
            <div class="summary-grid">
                <?php for ($i = 0; $i <= 6; $i++): 
                    $dayData = $availability[$i] ?? null;
                    $isAvailable = isset($dayData['isAvailable']) && $dayData['isAvailable'] == 1;
                    $start = ($dayData && $dayData['startTime'] != '00:00') ? $dayData['startTime'] : null;
                    $end = ($dayData && $dayData['endTime'] != '00:00') ? $dayData['endTime'] : null;
                ?>
                    <div class="summary-item <?php echo $isAvailable ? 'active' : 'inactive'; ?>">
                        <div class="summary-day"><?php echo $days[$i]; ?></div>
                        <?php if ($isAvailable && $start && $end): ?>
                            <div class="summary-hours">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('g:i A', strtotime($start)); ?> - 
                                <?php echo date('g:i A', strtotime($end)); ?>
                            </div>
                        <?php else: ?>
                            <div class="summary-hours">
                                <i class="fas fa-ban"></i> Not Available
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
            <?php 
            $savedCount = 0;
            for ($i = 0; $i <= 6; $i++) {
                $dayData = $availability[$i] ?? null;
                if (isset($dayData['isAvailable']) && $dayData['isAvailable'] == 1) {
                    $savedCount++;
                }
            }
            if ($savedCount > 0): ?>
                <div class="summary-footer">
                    <i class="fas fa-database"></i> <?php echo $savedCount; ?> day(s) scheduled
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Availability Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Edit Weekly Schedule</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="availability-form">
                <div class="availability-grid">
                    <?php for ($i = 0; $i <= 6; $i++): 
                        $dayData = $availability[$i] ?? null;
                        $isAvailable = isset($dayData['isAvailable']) && $dayData['isAvailable'] == 1;
                        $start = ($dayData && $dayData['startTime'] != '00:00') ? $dayData['startTime'] : '09:00';
                        $end   = ($dayData && $dayData['endTime'] != '00:00') ? $dayData['endTime'] : '17:00';
                    ?>
                        <div class="availability-day">
                            <label class="day-checkbox-label">
                                <input type="checkbox" 
                                       name="day_<?php echo $i; ?>_available"
                                       class="day-checkbox" 
                                       data-day="<?php echo $i; ?>"
                                       onchange="toggleDay(<?php echo $i; ?>)"
                                       <?php echo $isAvailable ? 'checked' : ''; ?>>
                                <strong><?php echo $days[$i]; ?></strong>
                            </label>
                            <div class="availability-times" style="<?php echo !$isAvailable ? 'display: none;' : ''; ?>">
                                <div class="time-select">
                                    <label>Start Time:</label>
                                    <select name="day_<?php echo $i; ?>_start" class="time-select-start">
                                        <?php foreach ($timeSlots as $t): ?>
                                            <option value="<?php echo $t; ?>" 
                                                <?php echo ($start == $t) ? 'selected' : ''; ?>>
                                                <?php echo date('g:i A', strtotime($t)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="time-select">
                                    <label>End Time:</label>
                                    <select name="day_<?php echo $i; ?>_end" class="time-select-end">
                                        <?php foreach ($timeSlots as $t): ?>
                                            <option value="<?php echo $t; ?>" 
                                                <?php echo ($end == $t) ? 'selected' : ''; ?>>
                                                <?php echo date('g:i A', strtotime($t)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_availability" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Availability
                    </button>
                    <button type="button" class="btn btn-outline" onclick="setDefaultWeek()">
                        <i class="fas fa-clock"></i> Set Default (Mon-Fri, 9 AM - 5 PM)
                    </button>
                    <button type="button" class="btn btn-outline" onclick="clearAll()">
                        <i class="fas fa-times"></i> Clear All
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-question-circle"></i> Important Information</h3>
        </div>
        <div class="card-body">
            <ul class="info-list">
                <li><i class="fas fa-check-circle"></i> <strong>Working Hours:</strong> Appointments are only available during your selected hours</li>
                <li><i class="fas fa-check-circle"></i> <strong>Lunch Break:</strong> 1:00 PM - 2:00 PM is automatically blocked for lunch</li>
                <li><i class="fas fa-check-circle"></i> <strong>Appointment Duration:</strong> Each appointment slot is 30 minutes</li>
                <li><i class="fas fa-check-circle"></i> <strong>Daily Limit:</strong> Maximum 10 appointments per day</li>
                <li><i class="fas fa-check-circle"></i> <strong>Wednesday Reminder:</strong> You will be reminded every Wednesday to review your schedule</li>
                <li><i class="fas fa-check-circle"></i> <strong>Changes:</strong> Any changes you make will affect future bookings immediately</li>
            </ul>
        </div>
    </div>
</div>

<script>
function toggleDay(day) {
    const checkbox = document.querySelector(`input[name="day_${day}_available"]`);
    const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
    const startSelect = document.querySelector(`select[name="day_${day}_start"]`);
    const endSelect = document.querySelector(`select[name="day_${day}_end"]`);
    
    if (checkbox.checked) {
        if (timesDiv) timesDiv.style.display = 'block';
        if (startSelect) startSelect.disabled = false;
        if (endSelect) endSelect.disabled = false;
    } else {
        if (timesDiv) timesDiv.style.display = 'none';
        if (startSelect) startSelect.disabled = true;
        if (endSelect) endSelect.disabled = true;
    }
}

function setDefaultWeek() {
    for (let i = 1; i <= 5; i++) {
        const checkbox = document.querySelector(`input[name="day_${i}_available"]`);
        if (checkbox) {
            checkbox.checked = true;
            const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
            if (timesDiv) timesDiv.style.display = 'block';
            
            const startSelect = document.querySelector(`select[name="day_${i}_start"]`);
            if (startSelect) {
                startSelect.value = '09:00';
                startSelect.disabled = false;
            }
            
            const endSelect = document.querySelector(`select[name="day_${i}_end"]`);
            if (endSelect) {
                endSelect.value = '17:00';
                endSelect.disabled = false;
            }
        }
    }
    
    for (let i of [0, 6]) {
        const checkbox = document.querySelector(`input[name="day_${i}_available"]`);
        if (checkbox) {
            checkbox.checked = false;
            const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
            if (timesDiv) timesDiv.style.display = 'none';
            
            const startSelect = document.querySelector(`select[name="day_${i}_start"]`);
            const endSelect = document.querySelector(`select[name="day_${i}_end"]`);
            if (startSelect) startSelect.disabled = true;
            if (endSelect) endSelect.disabled = true;
        }
    }
}

function clearAll() {
    if (confirm('Are you sure you want to clear all availability settings?')) {
        for (let i = 0; i <= 6; i++) {
            const checkbox = document.querySelector(`input[name="day_${i}_available"]`);
            if (checkbox) {
                checkbox.checked = false;
                const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
                if (timesDiv) timesDiv.style.display = 'none';
                
                const startSelect = document.querySelector(`select[name="day_${i}_start"]`);
                const endSelect = document.querySelector(`select[name="day_${i}_end"]`);
                if (startSelect) startSelect.disabled = true;
                if (endSelect) endSelect.disabled = true;
            }
        }
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    for (let i = 0; i <= 6; i++) {
        toggleDay(i);
    }
});
</script>

<style>
.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
    flex-wrap: wrap;
}
</style>

<?php include '../includes/footer.php'; ?>