<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Patients - HealthManagement";
include '../includes/header.php';

// Get all patients with their details
$patients = $pdo->query("
    SELECT p.patientId, p.dateOfBirth, p.bloodType, p.address, p.knownAllergies,
           p.emergencyContactName, p.emergencyContactPhone, p.insuranceProvider, p.insuranceNumber,
           u.userId, u.username, u.firstName, u.lastName, u.email, u.phoneNumber, u.dateCreated
    FROM patients p
    JOIN users u ON p.userId = u.userId
    ORDER BY u.dateCreated DESC
")->fetchAll();

// Get statistics
$totalPatients = count($patients);
$activePatients = $pdo->query("SELECT COUNT(DISTINCT patientId) as count FROM appointments WHERE status = 'completed' AND dateTime > DATE_SUB(NOW(), INTERVAL 3 MONTH)")->fetch()['count'];
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Patients</h1>
        <p>View and manage all registered patients</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card admin">
            <h3><?php echo $totalPatients; ?></h3>
            <p>Total Patients</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $activePatients; ?></h3>
            <p>Active Patients (Last 3 months)</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>All Patients</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Date of Birth</th>
                        <th>Blood Type</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td data-label="ID">#<?php echo $patient['patientId']; ?></td>
                            <td data-label="Name">
                                <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong><br>
                                <small><?php echo $patient['username']; ?></small>
                            </td>
                            <td data-label="Email"><?php echo $patient['email']; ?></td>
                            <td data-label="Phone"><?php echo $patient['phoneNumber']; ?></td>
                            <td data-label="Date of Birth"><?php echo $patient['dateOfBirth'] ?: 'N/A'; ?></td>
                            <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                            <td data-label="Registered"><?php echo date('M j, Y', strtotime($patient['dateCreated'])); ?></td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <a href="../staff/patient-records.php?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../staff/book-appointment.php?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-calendar-plus"></i> Book
                                    </a>
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