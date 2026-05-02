<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$notificationId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$notificationId) {
    header("Location: dashboard.php");
    exit();
}

// Get notification details
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE notificationId = ? AND userId = ?
");
$stmt->execute([$notificationId, $userId]);
$notification = $stmt->fetch();

if (!$notification) {
    header("Location: dashboard.php");
    exit();
}

// Mark as read
$stmt = $pdo->prepare("
    UPDATE notifications 
    SET isRead = 1, readDate = NOW() 
    WHERE notificationId = ?
");
$stmt->execute([$notificationId]);

// Redirect to the link
$redirectUrl = $notification['link'] ?? 'dashboard.php';
header("Location: " . $redirectUrl);
exit();
?>