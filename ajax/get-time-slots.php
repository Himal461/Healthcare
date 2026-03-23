<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

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

$slots = getAvailableTimeSlots($doctorId, $date);

echo json_encode(['success' => true, 'slots' => $slots]);
?>