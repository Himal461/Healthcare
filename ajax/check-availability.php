<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['available' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = $_GET['doctor_id'] ?? null;
$datetime = $_GET['datetime'] ?? null;

if (!$doctorId || !$datetime) {
    echo json_encode(['available' => false, 'error' => 'Missing parameters']);
    exit();
}

// Check if the time slot is available
$date = date('Y-m-d', strtotime($datetime));
$time = date('H:i:s', strtotime($datetime));

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctorId = ? 
    AND DATE(dateTime) = ? 
    AND TIME(dateTime) = ? 
    AND status NOT IN ('cancelled', 'no-show')
");
$stmt->execute([$doctorId, $date, $time]);
$isBooked = $stmt->fetch()['count'] > 0;

if (!$isBooked) {
    echo json_encode(['available' => true]);
    exit();
}

// If not available, suggest alternatives
$alternatives = suggestAlternativeSlots($doctorId, $datetime);
echo json_encode($alternatives);
?>