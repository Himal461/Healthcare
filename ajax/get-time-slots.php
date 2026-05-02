<?php
require_once '../includes/config.php';
session_start();

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

// Disable error display to prevent HTML in JSON
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'slots' => []]);
    exit();
}

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$doctorId || !$date) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters', 'slots' => []]);
    exit();
}

// Validate date format
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    echo json_encode(['success' => false, 'error' => 'Invalid date format', 'slots' => []]);
    exit();
}

try {
    // Set timezone
    date_default_timezone_set('Australia/Sydney');
    
    $today = date('Y-m-d');
    $currentTimestamp = time();
    
    // Check if doctor has ANY availability records
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM doctor_availability WHERE doctorId = ?
    ");
    $countStmt->execute([$doctorId]);
    $hasAvailability = $countStmt->fetchColumn() > 0;
    
    // Get doctor's availability for this specific date
    $stmt = $pdo->prepare("
        SELECT startTime, endTime, isAvailable, isDayOff
        FROM doctor_availability 
        WHERE doctorId = ? AND availabilityDate = ?
    ");
    $stmt->execute([$doctorId, $date]);
    $availability = $stmt->fetch();
    
    // If no availability set for this date AND doctor has no availability records at all
    // Use default working hours for weekdays
    if (!$availability && !$hasAvailability) {
        $dayOfWeek = date('w', strtotime($date));
        // Monday to Friday (1-5)
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $availability = [
                'startTime' => WORKING_HOURS_START . ':00',
                'endTime' => WORKING_HOURS_END . ':00',
                'isAvailable' => 1,
                'isDayOff' => 0
            ];
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Doctor is not available on weekends.',
                'slots' => []
            ]);
            exit();
        }
    }
    
    // If no availability for this date but doctor HAS availability records
    // That means this specific date is not available
    if (!$availability && $hasAvailability) {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor has not set availability for this specific date.',
            'slots' => []
        ]);
        exit();
    }
    
    // Check if doctor is available (not day off)
    if (isset($availability['isDayOff']) && $availability['isDayOff']) {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor has a day off on this date.',
            'slots' => []
        ]);
        exit();
    }
    
    if (!$availability['isAvailable']) {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor is not available on this date.',
            'slots' => []
        ]);
        exit();
    }
    
    $startTime = $availability['startTime'];
    $endTime = $availability['endTime'];
    
    // Get booked appointments for this doctor on this date
    $stmt = $pdo->prepare("
        SELECT TIME(dateTime) as slot_time 
        FROM appointments 
        WHERE doctorId = ? AND DATE(dateTime) = ? 
        AND status NOT IN ('cancelled', 'no-show')
    ");
    $stmt->execute([$doctorId, $date]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $bookedSlotsMap = array_flip($bookedSlots);
    
    // Generate 30-minute slots
    $start = strtotime($date . ' ' . $startTime);
    $end = strtotime($date . ' ' . $endTime);
    $duration = 30;
    
    $timeSlots = [];
    
    for ($time = $start; $time + ($duration * 60) <= $end; $time += ($duration * 60)) {
        $timeValue = date('H:i:s', $time);
        $displayTime = date('g:i A', $time);
        
        // Skip if already booked
        if (isset($bookedSlotsMap[$timeValue])) {
            continue;
        }
        
        // Skip past time slots for today
        $slotTimestamp = strtotime($date . ' ' . $timeValue);
        if ($date === $today && $slotTimestamp <= $currentTimestamp) {
            continue;
        }
        
        $timeSlots[] = [
            'value' => $timeValue,
            'display' => $displayTime,
            'start' => $displayTime
        ];
    }
    
    echo json_encode([
        'success' => true,
        'slots' => $timeSlots,
        'date' => $date,
        'doctorId' => $doctorId,
        'count' => count($timeSlots),
        'source' => $hasAvailability ? 'availability_table' : 'default_hours'
    ]);
    
} catch (Exception $e) {
    error_log("Time slots error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading time slots.',
        'slots' => []
    ]);
}
exit();
?>