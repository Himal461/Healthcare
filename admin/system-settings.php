<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "System Settings - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = substr($key, 8);
            $stmt = $pdo->prepare("UPDATE system_settings SET settingValue = ? WHERE settingKey = ?");
            $stmt->execute([$value, $settingKey]);
        }
    }
    $_SESSION['success'] = "Settings updated!";
    logAction($_SESSION['user_id'], 'UPDATE_SETTINGS', "Updated system settings");
    header("Location: system-settings.php");
    exit();
}

$settings = $pdo->query("SELECT * FROM system_settings ORDER BY settingId")->fetchAll();
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <p>Configure system-wide preferences</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-sliders-h"></i> General Settings</h3>
            </div>
            <div class="admin-card-body">
                <?php foreach ($settings as $setting): ?>
                    <div class="admin-setting-item">
                        <label for="setting_<?php echo $setting['settingKey']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $setting['settingKey'])); ?>
                        </label>
                        <?php if ($setting['settingType'] === 'boolean'): ?>
                            <select name="setting_<?php echo $setting['settingKey']; ?>" class="admin-form-control">
                                <option value="true" <?php echo $setting['settingValue']=='true'?'selected':''; ?>>Enabled</option>
                                <option value="false" <?php echo $setting['settingValue']=='false'?'selected':''; ?>>Disabled</option>
                            </select>
                        <?php elseif ($setting['settingType'] === 'integer'): ?>
                            <input type="number" name="setting_<?php echo $setting['settingKey']; ?>" value="<?php echo $setting['settingValue']; ?>" class="admin-form-control">
                        <?php else: ?>
                            <input type="text" name="setting_<?php echo $setting['settingKey']; ?>" value="<?php echo $setting['settingValue']; ?>" class="admin-form-control">
                        <?php endif; ?>
                        <?php if ($setting['description']): ?>
                            <small><?php echo $setting['description']; ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-tools"></i> Maintenance</h3>
            </div>
            <div class="admin-card-body">
                <div class="admin-maintenance-actions">
                    <button type="button" class="admin-btn admin-btn-warning" onclick="clearCache()"><i class="fas fa-broom"></i> Clear Cache</button>
                    <button type="button" class="admin-btn admin-btn-warning" onclick="optimizeDatabase()"><i class="fas fa-database"></i> Optimize Database</button>
                    <button type="button" class="admin-btn admin-btn-danger" onclick="backupDatabase()"><i class="fas fa-download"></i> Backup Database</button>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 15px; margin-top: 20px;">
            <button type="submit" name="update_settings" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>