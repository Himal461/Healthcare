<?php
// No need to start session here - it's already started in config.php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = $_GET['doctor_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$doctorId || !$date) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// Verify doctor exists
$stmt = $pdo->prepare("SELECT doctorId FROM doctors WHERE doctorId = ? AND isAvailable = 1");
$stmt->execute([$doctorId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Invalid doctor']);
    exit();
}

// Get available time slots
try {
    $slots = getAvailableTimeSlots($doctorId, $date);
    echo json_encode(['success' => true, 'slots' => $slots]);
} catch (Exception $e) {
    error_log("Error in get-time-slots.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>