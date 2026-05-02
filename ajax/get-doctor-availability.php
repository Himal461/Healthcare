<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['available' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$day = (int)($_GET['day'] ?? -1);

if (!$doctorId || $day < 0) {
    echo json_encode(['available' => true, 'start' => WORKING_HOURS_START, 'end' => WORKING_HOURS_END]);
    exit();
}

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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM doctor_availability WHERE doctorId = ?");
    $stmt->execute([$doctorId]);
    $hasAvailability = $stmt->fetchColumn() > 0;
    
    if ($hasAvailability) {
        echo json_encode([
            'available' => false,
            'start' => '00:00:00',
            'end' => '00:00:00',
            'message' => 'Doctor is not available on this day'
        ]);
    } else {
        echo json_encode([
            'available' => true,
            'start' => WORKING_HOURS_START,
            'end' => WORKING_HOURS_END
        ]);
    }
}