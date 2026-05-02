<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Notifications - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_notification'])) {
    $userId = $_POST['user_id'];
    $type = $_POST['type'];
    $title = sanitizeInput($_POST['title']);
    $message = sanitizeInput($_POST['message']);
    $link = sanitizeInput($_POST['link']) ?: null;
    
    createNotification($userId, $type, $title, $message, $link);
    $_SESSION['success'] = "Notification sent!";
    logAction($_SESSION['user_id'], 'CREATE_NOTIFICATION', "Sent notification to user $userId");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE notificationId = ?");
    $stmt->execute([$_GET['delete']]);
    $_SESSION['success'] = "Notification deleted!";
    header("Location: notifications.php");
    exit();
}

$notifications = $pdo->query("
    SELECT n.*, u.username, u.firstName, u.lastName
    FROM notifications n LEFT JOIN users u ON n.userId = u.userId
    ORDER BY n.sentDate DESC
")->fetchAll();

$users = $pdo->query("SELECT userId, username, firstName, lastName FROM users WHERE role != 'admin'")->fetchAll();
$totalNotifications = count($notifications);
$unreadNotifications = $pdo->query("SELECT COUNT(*) FROM notifications WHERE isRead = 0")->fetchColumn();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p>Send and manage system notifications</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="admin-stats-grid">
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-bell"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalNotifications; ?></h3>
                <p>Total</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-envelope"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $unreadNotifications; ?></h3>
                <p>Unread</p>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-paper-plane"></i> Send Notification</h3>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <div class="admin-form-group">
                    <label>Send To <span class="required">*</span></label>
                    <select name="user_id" class="admin-form-control" required>
                        <option value="">Select user</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['userId']; ?>"><?php echo htmlspecialchars($u['firstName'].' '.$u['lastName'].' (@'.$u['username'].')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Type <span class="required">*</span></label>
                    <select name="type" class="admin-form-control" required>
                        <option value="appointment">Appointment</option>
                        <option value="reminder">Reminder</option>
                        <option value="prescription">Prescription</option>
                        <option value="lab_result">Lab Result</option>
                        <option value="system">System</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="admin-form-control" required>
                </div>
                <div class="admin-form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="message" rows="4" class="admin-form-control" required></textarea>
                </div>
                <div class="admin-form-group">
                    <label>Link (Optional)</label>
                    <input type="text" name="link" class="admin-form-control">
                </div>
                <button type="submit" name="create_notification" class="admin-btn admin-btn-primary">Send</button>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Notifications</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $n): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y g:i A', strtotime($n['sentDate'])); ?></td>
                            <td data-label="User"><?php echo $n['firstName'] ? htmlspecialchars($n['firstName'].' '.$n['lastName']) : 'System'; ?></td>
                            <td data-label="Type"><span class="admin-role-badge admin-role-<?php echo $n['type']; ?>"><?php echo ucfirst($n['type']); ?></span></td>
                            <td data-label="Title"><?php echo htmlspecialchars($n['title']); ?></td>
                            <td data-label="Status">
                                <span class="admin-status-badge <?php echo $n['isRead'] ? 'admin-status-completed' : 'admin-status-pending'; ?>">
                                    <?php echo $n['isRead'] ? 'Read' : 'Unread'; ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <a href="?delete=<?php echo $n['notificationId']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Delete?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>