<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Notifications - HealthManagement";
include '../includes/header.php';

// Handle notification actions
if (isset($_GET['delete'])) {
    $notificationId = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE notificationId = ?");
    $stmt->execute([$notificationId]);
    $_SESSION['success'] = "Notification deleted!";
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['send'])) {
    $notificationId = $_GET['send'];
    $stmt = $pdo->prepare("UPDATE notifications SET isRead = 1 WHERE notificationId = ?");
    $stmt->execute([$notificationId]);
    header("Location: notifications.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_notification'])) {
    $userId = $_POST['user_id'];
    $type = $_POST['type'];
    $title = sanitizeInput($_POST['title']);
    $message = sanitizeInput($_POST['message']);
    $link = sanitizeInput($_POST['link']) ?: null;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (userId, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $title, $message, $link]);
        $_SESSION['success'] = "Notification sent successfully!";
        logAction($_SESSION['user_id'], 'CREATE_NOTIFICATION', "Sent notification to user ID: $userId");
        header("Location: notifications.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to send notification: " . $e->getMessage();
    }
}

// Get all notifications
$notifications = $pdo->query("
    SELECT n.*, u.username, u.firstName, u.lastName
    FROM notifications n
    LEFT JOIN users u ON n.userId = u.userId
    ORDER BY n.sentDate DESC
")->fetchAll();

// Get users for dropdown
$users = $pdo->query("SELECT userId, username, firstName, lastName FROM users WHERE role != 'admin'")->fetchAll();

// Statistics
$totalNotifications = $pdo->query("SELECT COUNT(*) as count FROM notifications")->fetch()['count'];
$unreadNotifications = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE isRead = 0")->fetch()['count'];
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Notifications</h1>
        <p>Send and manage system notifications</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card admin">
            <h3><?php echo $totalNotifications; ?></h3>
            <p>Total Notifications</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $unreadNotifications; ?></h3>
            <p>Unread Notifications</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Create Notification Form -->
    <div class="card">
        <div class="card-header">
            <h3>Send New Notification</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form">
                <div class="form-group">
                    <label for="user_id">Send To *</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Select user</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['userId']; ?>">
                                <?php echo $user['firstName'] . ' ' . $user['lastName'] . ' (' . $user['username'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="type">Notification Type *</label>
                    <select id="type" name="type" required>
                        <option value="appointment">Appointment</option>
                        <option value="reminder">Reminder</option>
                        <option value="prescription">Prescription</option>
                        <option value="lab_result">Lab Result</option>
                        <option value="system">System</option>
                        <option value="message">Message</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message *</label>
                    <textarea id="message" name="message" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="link">Link (Optional)</label>
                    <input type="text" id="link" name="link" placeholder="e.g., /patient/appointments.php">
                </div>
                
                <button type="submit" name="create_notification" class="btn btn-primary">Send Notification</button>
            </form>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card">
        <div class="card-header">
            <h3>All Notifications</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sent Date</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                    <tr class="<?php echo !$notification['isRead'] ? 'unread' : ''; ?>">
                        <td data-label="Sent Date"><?php echo date('M j, Y g:i A', strtotime($notification['sentDate'])); ?></td>
                        <td data-label="User"><?php echo $notification['firstName'] ? $notification['firstName'] . ' ' . $notification['lastName'] : 'System'; ?></td>
                        <td data-label="Type">
                            <span class="type-badge type-<?php echo $notification['type']; ?>">
                                <?php echo ucfirst($notification['type']); ?>
                            </span>
                        </td>
                        <td data-label="Title"><?php echo htmlspecialchars($notification['title']); ?></td>
                        <td data-label="Message"><?php echo htmlspecialchars(substr($notification['message'], 0, 50)) . (strlen($notification['message']) > 50 ? '...' : ''); ?></td>
                        <td data-label="Status">
                            <span class="status-badge <?php echo $notification['isRead'] ? 'status-completed' : 'status-pending'; ?>">
                                <?php echo $notification['isRead'] ? 'Read' : 'Unread'; ?>
                            </span>
                        </td>
                        <td data-label="Actions">
                            <?php if (!$notification['isRead']): ?>
                                <a href="?send=<?php echo $notification['notificationId']; ?>" class="btn btn-success btn-sm">Mark Read</a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $notification['notificationId']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this notification?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>