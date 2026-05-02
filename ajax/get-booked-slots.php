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

$stmt = $pdo->prepare("
    SELECT TIME(dateTime) as slot_time 
    FROM appointments 
    WHERE doctorId = ? AND DATE(dateTime) = ? 
    AND status NOT IN ('cancelled', 'no-show')
");
$stmt->execute([$doctorId, $date]);
$booked = $stmt->fetchAll();

$bookedSlots = [];
foreach ($booked as $b) {
    $bookedSlots[] = $b['slot_time'];
}

echo json_encode(['success' => true, 'booked_slots' => $bookedSlots]);