<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Users - HealthManagement";
include '../includes/header.php';

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'verify':
                $stmt = $pdo->prepare("UPDATE users SET isVerified = 1, verificationCode = NULL WHERE userId = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = "User verified successfully!";
                logAction($_SESSION['user_id'], 'USER_VERIFY', "Verified user ID: $userId");
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM users WHERE userId = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = "User deleted successfully!";
                logAction($_SESSION['user_id'], 'USER_DELETE', "Deleted user ID: $userId");
                break;
                
            case 'promote':
                $newRole = $_GET['role'];
                $allowedRoles = ['admin', 'doctor', 'nurse', 'staff', 'patient'];
                if (in_array($newRole, $allowedRoles)) {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE userId = ?");
                    $stmt->execute([$newRole, $userId]);
                    $_SESSION['success'] = "User role updated successfully!";
                    logAction($_SESSION['user_id'], 'USER_PROMOTE', "Changed user $userId role to $newRole");
                }
                break;
                
            case 'suspend':
                $stmt = $pdo->prepare("UPDATE users SET isSuspended = 1 WHERE userId = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = "User suspended successfully!";
                logAction($_SESSION['user_id'], 'USER_SUSPEND', "Suspended user ID: $userId");
                break;
                
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET isSuspended = 0 WHERE userId = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = "User activated successfully!";
                logAction($_SESSION['user_id'], 'USER_ACTIVATE', "Activated user ID: $userId");
                break;
        }
        
        header("Location: users.php");
        exit();
    } catch (Exception $e) {
        $error = "Failed to perform action. Please try again.";
    }
}

// Get filters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT u.*, 
           p.dateOfBirth,
           p.bloodType,
           p.address,
           p.knownAllergies,
           d.specialization,
           d.consultationFee,
           s.licenseNumber,
           s.hireDate,
           s.department,
           s.position
    FROM users u
    LEFT JOIN patients p ON u.userId = p.userId
    LEFT JOIN staff s ON u.userId = s.userId
    LEFT JOIN doctors d ON s.staffId = d.staffId
    WHERE 1=1
";

$params = [];

if ($roleFilter) {
    $query .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter === 'verified') {
    $query .= " AND u.isVerified = 1";
} elseif ($statusFilter === 'unverified') {
    $query .= " AND u.isVerified = 0";
}

if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY u.dateCreated DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn();
$totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$pendingVerification = $pdo->query("SELECT COUNT(*) FROM users WHERE isVerified = 0")->fetchColumn();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Users</h1>
        <p>Manage system users and their permissions</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Users Statistics -->
    <div class="stats-grid">
        <div class="stat-card admin">
            <h3><?php echo $totalUsers; ?></h3>
            <p>Total Users</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $totalDoctors; ?></h3>
            <p>Doctors</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $totalPatients; ?></h3>
            <p>Patients</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $pendingVerification; ?></h3>
            <p>Pending Verification</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>Filter Users</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="role">Role</label>
                        <select name="role" id="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="doctor" <?php echo $roleFilter === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="nurse" <?php echo $roleFilter === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                            <option value="staff" <?php echo $roleFilter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="patient" <?php echo $roleFilter === 'patient' ? 'selected' : ''; ?>>Patient</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">All Status</option>
                            <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="unverified" <?php echo $statusFilter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, username...">
                    </div>
                    
                    <div class="filter-group filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="users.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h3>All Users</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
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
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td data-label="ID">#<?php echo $user['userId']; ?></td>
                                <td data-label="Name">
                                    <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?>
                                    <br>
                                    <small><?php echo htmlspecialchars($user['username']); ?></small>
                                </td>
                                <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td data-label="Role">
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td data-label="Details">
                                    <?php if ($user['role'] === 'doctor'): ?>
                                        <small>
                                            <i class="fas fa-stethoscope"></i> <strong><?php echo htmlspecialchars($user['specialization'] ?? 'N/A'); ?></strong><br>
                                            <i class="fas fa-dollar-sign"></i> $<?php echo number_format($user['consultationFee'] ?? 0, 2); ?><br>
                                            <i class="fas fa-id-card"></i> License: <?php echo htmlspecialchars($user['licenseNumber'] ?? 'N/A'); ?>
                                        </small>
                                    <?php elseif ($user['role'] === 'nurse'): ?>
                                        <small>
                                            <i class="fas fa-heartbeat"></i> Nursing Staff<br>
                                            <i class="fas fa-id-card"></i> License: <?php echo htmlspecialchars($user['licenseNumber'] ?? 'N/A'); ?>
                                        </small>
                                    <?php elseif ($user['role'] === 'staff'): ?>
                                        <small>
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?><br>
                                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?>
                                        </small>
                                    <?php elseif ($user['role'] === 'patient'): ?>
                                        <small>
                                            <i class="fas fa-tint"></i> Blood: <?php echo htmlspecialchars($user['bloodType'] ?? 'N/A'); ?><br>
                                            <i class="fas fa-calendar"></i> DOB: <?php echo htmlspecialchars($user['dateOfBirth'] ?? 'N/A'); ?><br>
                                            <i class="fas fa-allergies"></i> Allergies: <?php echo htmlspecialchars($user['knownAllergies'] ?? 'None'); ?>
                                        </small>
                                    <?php elseif ($user['role'] === 'admin'): ?>
                                        <small>
                                            <i class="fas fa-crown"></i> System Administrator
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <?php if ($user['isVerified']): ?>
                                        <span class="status-badge status-verified">Verified</span>
                                    <?php else: ?>
                                        <span class="status-badge status-unverified">Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <?php if (!$user['isVerified']): ?>
                                            <a href="?action=verify&id=<?php echo $user['userId']; ?>" 
                                               class="btn btn-success btn-sm" 
                                               title="Verify User">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm dropdown-toggle" data-dropdown="role-menu-<?php echo $user['userId']; ?>">
                                                <i class="fas fa-user-tag"></i> Role
                                            </button>
                                            <div id="role-menu-<?php echo $user['userId']; ?>" class="dropdown-menu">
                                                <?php 
                                                $roles = ['patient', 'staff', 'nurse', 'doctor', 'admin'];
                                                foreach ($roles as $role): 
                                                    if ($role !== $user['role']):
                                                ?>
                                                    <a href="?action=promote&id=<?php echo $user['userId']; ?>&role=<?php echo $role; ?>" class="dropdown-item">
                                                        <?php echo ucfirst($role); ?>
                                                    </a>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <a href="?action=delete&id=<?php echo $user['userId']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')"
                                           title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Dropdown functionality
document.querySelectorAll('.dropdown-toggle').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const dropdownId = this.getAttribute('data-dropdown');
        const dropdown = document.getElementById(dropdownId);
        
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu.id !== dropdownId) {
                menu.classList.remove('show');
            }
        });
        
        dropdown.classList.toggle('show');
    });
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>