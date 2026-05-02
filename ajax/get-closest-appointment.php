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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = (int)($_GET['doctor_id'] ?? 0);

if (!$doctorId) {
    echo json_encode(['success' => false, 'error' => 'Missing doctor ID']);
    exit();
}

try {
    // Set timezone
    date_default_timezone_set('Australia/Sydney');
    
    // Get doctor name
    $docStmt = $pdo->prepare("
        SELECT CONCAT(u.firstName, ' ', u.lastName) as doctor_name
        FROM doctors d
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        WHERE d.doctorId = ?
    ");
    $docStmt->execute([$doctorId]);
    $doctor = $docStmt->fetch();
    $doctorName = $doctor ? $doctor['doctor_name'] : '';
    
    $currentTimestamp = time();
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // Check if doctor has ANY availability records AT ALL
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM doctor_availability WHERE doctorId = ?
    ");
    $countStmt->execute([$doctorId]);
    $totalAvailabilityRecords = $countStmt->fetchColumn();
    
    // IF DOCTOR HAS NO AVAILABILITY RECORDS AT ALL - Return no appointments
    if ($totalAvailabilityRecords == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This doctor has not set their availability schedule yet. Please check back later or contact the clinic.',
            'no_availability_set' => true
        ]);
        exit();
    }
    
    // Check if doctor has future available dates
    $futureStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM doctor_availability 
        WHERE doctorId = ? 
        AND availabilityDate >= ?
        AND isAvailable = 1 
        AND isDayOff = 0
    ");
    $futureStmt->execute([$doctorId, $today]);
    $futureAvailable = $futureStmt->fetchColumn();
    
    if ($futureAvailable == 0) {
        // Check if there are any future dates at all (even if not available)
        $anyFutureStmt = $pdo->prepare("
            SELECT MIN(availabilityDate) as next_date
            FROM doctor_availability 
            WHERE doctorId = ? AND availabilityDate >= ?
        ");
        $anyFutureStmt->execute([$doctorId, $today]);
        $nextDate = $anyFutureStmt->fetchColumn();
        
        if ($nextDate) {
            echo json_encode([
                'success' => false,
                'message' => 'Doctor has no available dates in the near future. Next scheduled date: ' . date('M j, Y', strtotime($nextDate)) . '.',
                'no_future_availability' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Doctor has no available dates scheduled. Please check back later.',
                'no_future_availability' => true
            ]);
        }
        exit();
    }
    
    // Get all future available dates for this doctor
    $stmt = $pdo->prepare("
        SELECT availabilityDate, startTime, endTime
        FROM doctor_availability 
        WHERE doctorId = ? 
        AND availabilityDate >= ?
        AND isAvailable = 1 
        AND isDayOff = 0
        ORDER BY availabilityDate ASC, startTime ASC
    ");
    $stmt->execute([$doctorId, $today]);
    $availabilities = $stmt->fetchAll();
    
    $closestSlot = null;
    
    foreach ($availabilities as $avail) {
        $date = $avail['availabilityDate'];
        $startTime = $avail['startTime'];
        $endTime = $avail['endTime'];
        
        // Get booked slots for this date
        $bookedStmt = $pdo->prepare("
            SELECT TIME(dateTime) as slot_time 
            FROM appointments 
            WHERE doctorId = ? AND DATE(dateTime) = ? 
            AND status NOT IN ('cancelled', 'no-show')
        ");
        $bookedStmt->execute([$doctorId, $date]);
        $bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);
        $bookedSlotsMap = array_flip($bookedSlots);
        
        // Generate 30-minute slots
        $start = strtotime($date . ' ' . $startTime);
        $end = strtotime($date . ' ' . $endTime);
        
        for ($time = $start; $time + 1800 <= $end; $time += 1800) {
            $timeValue = date('H:i:s', $time);
            
            // Skip if already booked
            if (isset($bookedSlotsMap[$timeValue])) {
                continue;
            }
            
            // Skip past time slots for today
            $slotTimestamp = strtotime($date . ' ' . $timeValue);
            if ($date === $today && $slotTimestamp <= $currentTimestamp) {
                continue;
            }
            
            // Found an available slot!
            $closestSlot = [
                'date' => $date,
                'time' => $timeValue,
                'display_time' => date('g:i A', $time),
                'doctor_name' => $doctorName
            ];
            break 2;
        }
    }
    
    if ($closestSlot) {
        echo json_encode([
            'success' => true,
            'closest' => $closestSlot
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'All available time slots are fully booked. Please check back later for cancellations.',
            'fully_booked' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get closest appointment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
exit();
?>