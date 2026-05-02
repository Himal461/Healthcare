<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Users - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['new_role'];
    
    if (in_array($newRole, ['patient', 'staff', 'nurse', 'doctor', 'admin', 'accountant'])) {
        if (updateUserRole($userId, $newRole)) {
            $_SESSION['success'] = "User role updated successfully to: " . ucfirst($newRole);
            logAction($_SESSION['user_id'], 'UPDATE_USER_ROLE', "Changed user $userId role to $newRole");
        } else {
            $_SESSION['error'] = "Failed to update user role.";
        }
    }
    header("Location: users.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM patients WHERE userId = ?");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("SELECT staffId FROM staff WHERE userId = ?");
        $stmt->execute([$userId]);
        $staff = $stmt->fetch();
        
        if ($staff) {
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE staffId = ?");
            $stmt->execute([$staff['staffId']]);
            $stmt = $pdo->prepare("DELETE FROM nurses WHERE staffId = ?");
            $stmt->execute([$staff['staffId']]);
            $stmt = $pdo->prepare("DELETE FROM accountants WHERE staffId = ?");
            $stmt->execute([$staff['staffId']]);
            $stmt = $pdo->prepare("DELETE FROM administrators WHERE staffId = ?");
            $stmt->execute([$staff['staffId']]);
            $stmt = $pdo->prepare("DELETE FROM staff WHERE userId = ?");
            $stmt->execute([$userId]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE userId = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        $_SESSION['success'] = "User deleted successfully!";
        logAction($_SESSION['user_id'], 'DELETE_USER', "Deleted user $userId");
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Cannot delete user: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

// Get all users
$users = $pdo->query("
    SELECT u.*, p.dateOfBirth, p.bloodType, d.specialization, s.department
    FROM users u
    LEFT JOIN patients p ON u.userId = p.userId
    LEFT JOIN staff s ON u.userId = s.userId
    LEFT JOIN doctors d ON s.staffId = d.staffId
    ORDER BY u.dateCreated DESC
")->fetchAll();

$totalUsers = count($users);
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn();
$totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$pendingVerification = $pdo->query("SELECT COUNT(*) FROM users WHERE isVerified = 0")->fetchColumn();

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-users-cog"></i> Manage Users</h1>
            <p>View and manage user accounts</p>
        </div>
        <div class="header-actions">
            <a href="manage-users.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-user-plus"></i> Create User
            </a>
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
        <div class="admin-stat-card users">
            <div class="admin-stat-icon"><i class="fas fa-users"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalUsers; ?></h3>
                <p>Total Users</p>
            </div>
        </div>
        <div class="admin-stat-card doctors">
            <div class="admin-stat-icon"><i class="fas fa-user-md"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalDoctors; ?></h3>
                <p>Doctors</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-user-injured"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Patients</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $pendingVerification; ?></h3>
                <p>Pending Verification</p>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Users</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="ID">#<?php echo $user['userId']; ?></td>
                            <td data-label="Name">
                                <?php echo htmlspecialchars($user['firstName'].' '.$user['lastName']); ?><br>
                                <small>@<?php echo $user['username']; ?></small>
                            </td>
                            <td data-label="Email"><?php echo $user['email']; ?><br><small><?php echo $user['phoneNumber']; ?></small></td>
                            <td data-label="Role">
                                <span class="admin-role-badge admin-role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td data-label="Details">
                                <?php if ($user['role'] == 'doctor'): ?>
                                    <?php echo $user['specialization'] ?? 'N/A'; ?>
                                <?php elseif ($user['role'] == 'patient'): ?>
                                    <?php echo $user['bloodType'] ?? 'N/A'; ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <span class="admin-status-badge <?php echo $user['isVerified'] ? 'admin-status-completed' : 'admin-status-cancelled'; ?>">
                                    <?php echo $user['isVerified'] ? 'Verified' : 'Unverified'; ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="admin-action-buttons">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Change user role?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['userId']; ?>">
                                        <select name="new_role" class="admin-btn admin-btn-sm" onchange="this.form.submit()" style="padding: 5px 10px;">
                                            <option value="">Change Role</option>
                                            <option value="patient" <?php echo $user['role']=='patient'?'selected':''; ?>>Patient</option>
                                            <option value="staff" <?php echo $user['role']=='staff'?'selected':''; ?>>Staff</option>
                                            <option value="nurse" <?php echo $user['role']=='nurse'?'selected':''; ?>>Nurse</option>
                                            <option value="doctor" <?php echo $user['role']=='doctor'?'selected':''; ?>>Doctor</option>
                                            <option value="accountant" <?php echo $user['role']=='accountant'?'selected':''; ?>>Accountant</option>
                                            <option value="admin" <?php echo $user['role']=='admin'?'selected':''; ?>>Admin</option>
                                        </select>
                                        <input type="hidden" name="update_role" value="1">
                                    </form>
                                    <a href="?delete=<?php echo $user['userId']; ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Delete user? This will permanently remove all associated data.')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>