<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Audit Log - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

$actionFilter = $_GET['action'] ?? '';
$userIdFilter = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$query = "SELECT al.*, u.username, u.firstName, u.lastName FROM audit_log al LEFT JOIN users u ON al.userId = u.userId WHERE 1=1";
$params = [];

if ($actionFilter) { $query .= " AND al.action = ?"; $params[] = $actionFilter; }
if ($userIdFilter) { $query .= " AND al.userId = ?"; $params[] = $userIdFilter; }
if ($dateFrom) { $query .= " AND DATE(al.timestamp) >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $query .= " AND DATE(al.timestamp) <= ?"; $params[] = $dateTo; }

$query .= " ORDER BY al.timestamp DESC LIMIT 500";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll();
$users = $pdo->query("SELECT userId, username, firstName, lastName FROM users ORDER BY username")->fetchAll();
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-history"></i> Audit Log</h1>
            <p>Track all system activities</p>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-filter"></i> Filter Logs</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="admin-filter-form">
                <div class="admin-filter-row">
                    <div class="admin-filter-group">
                        <select name="action" class="admin-form-control">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $a): ?>
                                <option value="<?php echo $a['action']; ?>" <?php echo $actionFilter==$a['action']?'selected':''; ?>><?php echo $a['action']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-filter-group">
                        <select name="user_id" class="admin-form-control">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['userId']; ?>" <?php echo $userIdFilter==$u['userId']?'selected':''; ?>><?php echo $u['firstName'].' '.$u['lastName']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-filter-group">
                        <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" class="admin-form-control">
                    </div>
                    <div class="admin-filter-group">
                        <input type="date" name="date_to" value="<?php echo $dateTo; ?>" class="admin-form-control">
                    </div>
                    <div class="admin-filter-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">Filter</button>
                        <a href="audit-log.php" class="admin-btn admin-btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> System Logs</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td data-label="Timestamp"><?php echo date('M j, Y g:i:s A', strtotime($log['timestamp'])); ?></td>
                            <td data-label="User"><?php echo $log['firstName'] ? $log['firstName'].' '.$log['lastName'] : 'System'; ?></td>
                            <td data-label="Action"><span class="admin-role-badge admin-role-admin"><?php echo $log['action']; ?></span></td>
                            <td data-label="Details"><?php echo htmlspecialchars($log['details']); ?></td>
                            <td data-label="IP"><?php echo $log['ipAddress']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>