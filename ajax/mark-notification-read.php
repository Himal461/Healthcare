<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$notificationId = (int)($_POST['notification_id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$notificationId) {
    echo json_encode(['success' => false, 'error' => 'Missing notification ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET isRead = 1, readDate = NOW() 
        WHERE notificationId = ? AND userId = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Mark notification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>