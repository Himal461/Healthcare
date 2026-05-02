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

// Determine the correct redirect URL
$link = $notification['link'] ?? '';
$userRole = $_SESSION['user_role'];

// Build the full redirect URL
if (!empty($link) && $link !== '#') {
    // Remove any leading ../ or ./ from the link
    $link = preg_replace('/^(\.\.\/|\.\/)+/', '', $link);
    
    // Check if link already has the full SITE_URL
    if (strpos($link, SITE_URL) === 0) {
        $redirectUrl = $link;
    }
    // Check if link starts with http (external link)
    elseif (strpos($link, 'http') === 0) {
        $redirectUrl = $link;
    }
    // Check if link starts with the role folder
    elseif (strpos($link, $userRole . '/') === 0) {
        $redirectUrl = SITE_URL . '/' . $link;
    }
    // Check if link starts with any known role folder
    elseif (preg_match('/^(patient|doctor|nurse|staff|admin|accountant)\//', $link)) {
        $redirectUrl = SITE_URL . '/' . $link;
    }
    // Otherwise, assume it's relative to the role folder
    else {
        $redirectUrl = SITE_URL . '/' . $userRole . '/' . ltrim($link, '/');
    }
} else {
    // Default to role dashboard
    $redirectUrl = SITE_URL . '/' . $userRole . '/dashboard.php';
}

// Log the redirect for debugging
error_log("Notification redirect: User $userId, Role $userRole, Link: $link -> $redirectUrl");

// Redirect
header("Location: " . $redirectUrl);
exit();
?>