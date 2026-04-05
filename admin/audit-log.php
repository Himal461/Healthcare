<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Audit Log - HealthManagement";
include '../includes/header.php';

// Get filter parameters
$actionFilter = $_GET['action'] ?? '';
$userIdFilter = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$query = "
    SELECT al.*, u.username, u.firstName, u.lastName
    FROM audit_log al
    LEFT JOIN users u ON al.userId = u.userId
    WHERE 1=1
";
$params = [];

if ($actionFilter) {
    $query .= " AND al.action = ?";
    $params[] = $actionFilter;
}

if ($userIdFilter) {
    $query .= " AND al.userId = ?";
    $params[] = $userIdFilter;
}

if ($dateFrom) {
    $query .= " AND DATE(al.timestamp) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(al.timestamp) <= ?";
    $params[] = $dateTo;
}

$query .= " ORDER BY al.timestamp DESC LIMIT 500";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll();

// Get users for filter
$users = $pdo->query("SELECT userId, username, firstName, lastName FROM users ORDER BY username")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Audit Log</h1>
        <p>Track all system activities and user actions</p>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>Filter Logs</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="action">Action</label>
                        <select id="action" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo $action['action']; ?>" <?php echo $actionFilter == $action['action'] ? 'selected' : ''; ?>>
                                    <?php echo $action['action']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="user_id">User</label>
                        <select id="user_id" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['userId']; ?>" <?php echo $userIdFilter == $user['userId'] ? 'selected' : ''; ?>>
                                    <?php echo $user['firstName'] . ' ' . $user['lastName'] . ' (' . $user['username'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="audit-log.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3>System Logs (Last 500 entries)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No logs found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td data-label="Timestamp"><?php echo date('M j, Y g:i:s A', strtotime($log['timestamp'])); ?></td>
                                <td data-label="User">
                                    <?php echo $log['firstName'] ? $log['firstName'] . ' ' . $log['lastName'] : 'System'; ?>
                                    <br><small><?php echo $log['username'] ? '@' . $log['username'] : ''; ?></small>
                                </td>
                                <td data-label="Action">
                                    <span class="action-badge"><?php echo $log['action']; ?></span>
                                </td>
                                <td data-label="Details"><?php echo htmlspecialchars($log['details']); ?></td>
                                <td data-label="IP"><?php echo $log['ipAddress']; ?></td>
                                <td data-label="User Agent" class="user-agent"><?php echo htmlspecialchars(substr($log['userAgent'], 0, 50)) . (strlen($log['userAgent']) > 50 ? '...' : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>