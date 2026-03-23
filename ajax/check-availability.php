<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$doctorId = $_GET['doctor_id'] ?? null;
$datetime = $_GET['datetime'] ?? null;

if (!$doctorId || !$datetime) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

$result = suggestAlternativeSlots($doctorId, $datetime);

echo json_encode($result);
?>