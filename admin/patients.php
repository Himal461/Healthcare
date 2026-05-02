<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Patients - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

$searchTerm = $_GET['search'] ?? '';

$query = "
    SELECT p.patientId, p.dateOfBirth, p.bloodType, p.address, p.knownAllergies,
           u.userId, u.username, u.firstName, u.lastName, u.email, u.phoneNumber, u.dateCreated,
           (SELECT COUNT(*) FROM appointments WHERE patientId = p.patientId) as total_appointments,
           (SELECT MAX(dateTime) FROM appointments WHERE patientId = p.patientId) as last_appointment
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE u.role = 'patient'
";
$params = [];

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params = [$searchLike, $searchLike, $searchLike, $searchLike];
}

$query .= " ORDER BY u.dateCreated DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$totalPatients = count($patients);
$activePatients = $pdo->query("
    SELECT COUNT(DISTINCT patientId) FROM appointments 
    WHERE status = 'completed' AND dateTime > DATE_SUB(NOW(), INTERVAL 3 MONTH)
")->fetchColumn();

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-injured"></i> Manage Patients</h1>
            <p>View and manage all registered patients</p>
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
            <div class="admin-stat-icon"><i class="fas fa-users"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $activePatients; ?></h3>
                <p>Active (3 months)</p>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-search"></i> Search Patients</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="admin-search-group">
                <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="admin-form-control">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> Search</button>
                <?php if ($searchTerm): ?>
                    <a href="patients.php" class="admin-btn admin-btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Patients</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>DOB</th>
                        <th>Blood Type</th>
                        <th>Appointments</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                        <tr><td colspan="9" class="admin-empty-message">No patients found</td></tr>
                    <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td data-label="ID">#<?php echo $patient['patientId']; ?></td>
                                <td data-label="Name">
                                    <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong><br>
                                    <small>@<?php echo htmlspecialchars($patient['username']); ?></small>
                                </td>
                                <td data-label="Email"><?php echo htmlspecialchars($patient['email']); ?></td>
                                <td data-label="Phone"><?php echo htmlspecialchars($patient['phoneNumber']); ?></td>
                                <td data-label="DOB"><?php echo $patient['dateOfBirth'] ? date('M j, Y', strtotime($patient['dateOfBirth'])) : 'N/A'; ?></td>
                                <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                                <td data-label="Appointments">
                                    <?php echo $patient['total_appointments']; ?>
                                    <?php if ($patient['last_appointment']): ?>
                                        <br><small>Last: <?php echo date('M j, Y', strtotime($patient['last_appointment'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Registered"><?php echo date('M j, Y', strtotime($patient['dateCreated'])); ?></td>
                                <td data-label="Actions">
                                    <div class="admin-action-buttons">
                                        <a href="view-patient.php?id=<?php echo $patient['patientId']; ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="book-appointment.php?patient_id=<?php echo $patient['patientId']; ?>" class="admin-btn admin-btn-success admin-btn-sm">
                                            <i class="fas fa-calendar-plus"></i> Book
                                        </a>
                                        <a href="view-appointments.php?patient_id=<?php echo $patient['patientId']; ?>" class="admin-btn admin-btn-info admin-btn-sm">
                                            <i class="fas fa-calendar-alt"></i> Appointments
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

<?php include '../includes/footer.php'; ?>