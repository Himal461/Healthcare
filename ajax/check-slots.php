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

$date = date('Y-m-d', strtotime($datetime));
$time = date('H:i:s', strtotime($datetime));

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments
    WHERE doctorId = ?
    AND DATE(dateTime) = ?
    AND TIME(dateTime) = ?
    AND status NOT IN ('cancelled','no-show')
");

$stmt->execute([$doctorId, $date, $time]);

echo json_encode([
    'available' => $stmt->fetchColumn() == 0
]);
?>