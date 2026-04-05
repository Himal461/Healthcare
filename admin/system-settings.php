<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "System Settings - HealthManagement";
include '../includes/header.php';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = substr($key, 8);
                $stmt = $pdo->prepare("UPDATE system_settings SET settingValue = ? WHERE settingKey = ?");
                $stmt->execute([$value, $settingKey]);
            }
        }
        $_SESSION['success'] = "System settings updated successfully!";
        logAction($_SESSION['user_id'], 'UPDATE_SYSTEM_SETTINGS', "Updated system configuration");
        header("Location: system-settings.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to update settings: " . $e->getMessage();
    }
}

// Get all settings
$settings = $pdo->query("SELECT * FROM system_settings ORDER BY settingId")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>System Settings</h1>
        <p>Configure system-wide settings and preferences</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="settings-form">
        <div class="card">
            <div class="card-header">
                <h3>General Settings</h3>
            </div>
            <div class="card-body">
                <?php foreach ($settings as $setting): ?>
                    <div class="setting-item">
                        <label for="setting_<?php echo $setting['settingKey']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $setting['settingKey'])); ?>
                            <?php if ($setting['description']): ?>
                                <span class="help-text" title="<?php echo $setting['description']; ?>">
                                    <i class="fas fa-question-circle"></i>
                                </span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($setting['settingType'] === 'boolean'): ?>
                            <select id="setting_<?php echo $setting['settingKey']; ?>" name="setting_<?php echo $setting['settingKey']; ?>">
                                <option value="true" <?php echo $setting['settingValue'] === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="false" <?php echo $setting['settingValue'] === 'false' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        <?php elseif ($setting['settingType'] === 'integer'): ?>
                            <input type="number" id="setting_<?php echo $setting['settingKey']; ?>" 
                                   name="setting_<?php echo $setting['settingKey']; ?>" 
                                   value="<?php echo $setting['settingValue']; ?>">
                        <?php elseif ($setting['settingType'] === 'json'): ?>
                            <textarea id="setting_<?php echo $setting['settingKey']; ?>" 
                                      name="setting_<?php echo $setting['settingKey']; ?>" 
                                      rows="3"><?php echo $setting['settingValue']; ?></textarea>
                        <?php else: ?>
                            <input type="text" id="setting_<?php echo $setting['settingKey']; ?>" 
                                   name="setting_<?php echo $setting['settingKey']; ?>" 
                                   value="<?php echo $setting['settingValue']; ?>">
                        <?php endif; ?>
                        
                        <?php if ($setting['description']): ?>
                            <small><?php echo $setting['description']; ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Maintenance Actions</h3>
            </div>
            <div class="card-body">
                <div class="maintenance-actions">
                    <button type="button" class="btn btn-warning" onclick="clearCache()">
                        <i class="fas fa-broom"></i> Clear System Cache
                    </button>
                    <button type="button" class="btn btn-warning" onclick="optimizeDatabase()">
                        <i class="fas fa-database"></i> Optimize Database
                    </button>
                    <button type="button" class="btn btn-danger" onclick="backupDatabase()">
                        <i class="fas fa-download"></i> Backup Database
                    </button>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="update_settings" class="btn btn-primary">
                <i class="fas fa-save"></i> Save All Settings
            </button>
        </div>
    </form>
</div>

<style>
.settings-form {
    max-width: 800px;
    margin: 0 auto;
}

.setting-item {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.setting-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.setting-item label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.help-text {
    cursor: help;
    margin-left: 5px;
    color: #1a75bc;
}

.setting-item input,
.setting-item select,
.setting-item textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.setting-item input:focus,
.setting-item select:focus,
.setting-item textarea:focus {
    outline: none;
    border-color: #1a75bc;
}

.setting-item small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.maintenance-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.maintenance-actions .btn {
    padding: 10px 20px;
}

.btn-warning {
    background: #ffc107;
    color: #333;
}

.btn-warning:hover {
    background: #e0a800;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.form-actions {
    margin-top: 30px;
    text-align: center;
}

@media (max-width: 768px) {
    .settings-form {
        max-width: 100%;
    }
    
    .maintenance-actions {
        flex-direction: column;
    }
    
    .maintenance-actions .btn {
        width: 100%;
    }
}
</style>

<script>
function clearCache() {
    if (confirm('Clear system cache? This will temporarily slow down the system while cache rebuilds.')) {
        fetch('../ajax/clear-cache.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cache cleared successfully!');
                } else {
                    alert('Failed to clear cache: ' + data.error);
                }
            });
    }
}

function optimizeDatabase() {
    if (confirm('Optimize database tables? This may take a few moments.')) {
        fetch('../ajax/optimize-db.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Database optimized successfully!');
                } else {
                    alert('Failed to optimize database: ' + data.error);
                }
            });
    }
}

function backupDatabase() {
    if (confirm('Download database backup? This may take a moment.')) {
        window.location.href = '../ajax/backup-db.php';
    }
}
</script>

<?php include '../includes/footer.php'; ?>