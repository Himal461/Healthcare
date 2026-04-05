<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['available' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = $_GET['doctor_id'] ?? null;
$day = $_GET['day'] ?? null;

if (!$doctorId || $day === null) {
    echo json_encode(['available' => true, 'start' => '09:00:00', 'end' => '17:00:00']);
    exit();
}

// Get doctor's availability for this day
$stmt = $pdo->prepare("
    SELECT startTime, endTime, isAvailable 
    FROM doctor_availability 
    WHERE doctorId = ? AND dayOfWeek = ? AND isAvailable = 1
");
$stmt->execute([$doctorId, $day]);
$availability = $stmt->fetch();

if ($availability) {
    echo json_encode([
        'available' => true,
        'start' => $availability['startTime'],
        'end' => $availability['endTime']
    ]);
} else {
    // Check if doctor has any availability set at all
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM doctor_availability WHERE doctorId = ?");
    $stmt->execute([$doctorId]);
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        // Doctor has availability set but not for this day
        echo json_encode([
            'available' => false,
            'start' => '00:00:00',
            'end' => '00:00:00',
            'message' => 'Doctor is not available on this day'
        ]);
    } else {
        // No availability set - default to available
        echo json_encode([
            'available' => true,
            'start' => '09:00:00',
            'end' => '17:00:00'
        ]);
    }
}
?>