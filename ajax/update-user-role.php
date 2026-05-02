<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_GET['user_id'] ?? null;
$newRole = $_GET['role'] ?? null;

if (!$userId || !$newRole || !in_array($newRole, ['patient', 'staff', 'nurse', 'doctor', 'admin', 'accountant'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$result = updateUserRole($userId, $newRole);
echo json_encode(['success' => $result]);