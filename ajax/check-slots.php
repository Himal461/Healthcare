<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['available' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

if (!$doctorId || !$date || !$time) {
    echo json_encode(['available' => false, 'error' => 'Missing parameters']);
    exit();
}

if (strlen($time) === 5 && strpos($time, ':') === 2) {
    $time .= ':00';
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments
    WHERE doctorId = ? AND DATE(dateTime) = ? AND TIME(dateTime) = ?
    AND status NOT IN ('cancelled','no-show')
");
$stmt->execute([$doctorId, $date, $time]);

echo json_encode([
    'available' => $stmt->fetchColumn() == 0
]);