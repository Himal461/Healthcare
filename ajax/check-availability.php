<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['available' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$datetime = $_GET['datetime'] ?? '';

if (!$doctorId || !$datetime) {
    echo json_encode(['available' => false, 'error' => 'Missing parameters']);
    exit();
}

$date = date('Y-m-d', strtotime($datetime));
$time = date('H:i:s', strtotime($datetime));

if (!$date || !$time) {
    echo json_encode(['available' => false, 'error' => 'Invalid datetime format']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctorId = ? 
    AND DATE(dateTime) = ? 
    AND TIME(dateTime) = ? 
    AND status NOT IN ('cancelled', 'no-show')
");
$stmt->execute([$doctorId, $date, $time]);
$isBooked = $stmt->fetchColumn() > 0;

if (!$isBooked) {
    echo json_encode(['available' => true]);
    exit();
}

$dayOfWeek = date('w', strtotime($date));
$stmt = $pdo->prepare("
    SELECT startTime, endTime FROM doctor_availability 
    WHERE doctorId = ? AND dayOfWeek = ? AND isAvailable = 1
");
$stmt->execute([$doctorId, $dayOfWeek]);
$availability = $stmt->fetch();

if (!$availability) {
    $startTime = WORKING_HOURS_START;
    $endTime = WORKING_HOURS_END;
} else {
    $startTime = $availability['startTime'];
    $endTime = $availability['endTime'];
}

$timeSlots = getAvailableTimeSlots($doctorId, $date);
$alternatives = array_slice($timeSlots, 0, 5);

echo json_encode([
    'available' => false,
    'message' => 'This time slot is already booked.',
    'alternatives' => $alternatives
]);