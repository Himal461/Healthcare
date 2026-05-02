<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$pageTitle = "Notifications - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$userId = $_SESSION['user_id'];

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notificationId = (int)$_GET['mark_read'];
    markNotificationRead($notificationId, $userId);
    $_SESSION['success'] = "Notification marked as read.";
    header("Location: notifications.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $notificationId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE notificationId = ? AND userId = ?");
    $stmt->execute([$notificationId, $userId]);
    $_SESSION['success'] = "Notification deleted.";
    header("Location: notifications.php");
    exit();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET isRead = 1, readDate = NOW() WHERE userId = ? AND isRead = 0");
    $stmt->execute([$userId]);
    $_SESSION['success'] = "All notifications marked as read.";
    header("Location: notifications.php");
    exit();
}

$notifications = getUserNotifications($userId, 50);
$unreadCount = getUnreadNotificationsCount($userId);
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>

<div class="root-container">
    <div class="root-page-header">
        <div class="header-title">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p>View and manage your notifications</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="root-alert root-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="root-card">
        <div class="root-card-header">
            <h3><i class="fas fa-bell"></i> All Notifications</h3>
            <?php if ($unreadCount > 0): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="root-btn root-btn-outline root-btn-sm">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="root-card-body">
            <?php if (empty($notifications)): ?>
                <div class="root-empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications found.</p>
                </div>
            <?php else: ?>
                <div class="root-notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="root-notification-item <?php echo $notification['isRead'] ? '' : 'unread'; ?>">
                            <div class="root-notification-icon">
                                <?php if ($notification['type'] == 'appointment'): ?>
                                    <i class="fas fa-calendar-check"></i>
                                <?php elseif ($notification['type'] == 'prescription'): ?>
                                    <i class="fas fa-prescription"></i>
                                <?php elseif ($notification['type'] == 'lab_result'): ?>
                                    <i class="fas fa-flask"></i>
                                <?php elseif ($notification['type'] == 'billing'): ?>
                                    <i class="fas fa-dollar-sign"></i>
                                <?php else: ?>
                                    <i class="fas fa-bell"></i>
                                <?php endif; ?>
                            </div>
                            <div class="root-notification-content">
                                <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                <span class="root-notification-time">
                                    <?php echo date('M j, Y g:i A', strtotime($notification['sentDate'])); ?>
                                </span>
                                <?php if ($notification['link']): ?>
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="root-notification-link">
                                        View Details
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="root-notification-actions">
                                <?php if (!$notification['isRead']): ?>
                                    <a href="?mark_read=<?php echo $notification['notificationId']; ?>" class="root-btn root-btn-success root-btn-sm" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?delete=<?php echo $notification['notificationId']; ?>" class="root-btn root-btn-danger root-btn-sm" title="Delete" onclick="return confirm('Delete this notification?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>