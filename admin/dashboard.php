<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Admin Dashboard - HealthManagement";
include '../includes/header.php';

// Get all statistics
$totalUsers = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$totalDoctors = $pdo->query("SELECT COUNT(*) as count FROM doctors")->fetch()['count'];
$totalNurses = $pdo->query("SELECT COUNT(*) as count FROM nurses")->fetch()['count'];
$totalStaff = $pdo->query("SELECT COUNT(*) as count FROM staff")->fetch()['count'];
$totalPatients = $pdo->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
$totalDepartments = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE isActive = 1")->fetch()['count'];
$totalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments")->fetch()['count'];
$todayAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(dateTime) = CURDATE()")->fetch()['count'];
$upcomingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE dateTime > NOW() AND status = 'scheduled'")->fetch()['count'];
$totalMedicalRecords = $pdo->query("SELECT COUNT(*) as count FROM medical_records")->fetch()['count'];
$totalLabTests = $pdo->query("SELECT COUNT(*) as count FROM lab_tests")->fetch()['count'];
$pendingLabTests = $pdo->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'ordered'")->fetch()['count'];
$totalPrescriptions = $pdo->query("SELECT COUNT(*) as count FROM prescriptions")->fetch()['count'];
$totalRevenue = $pdo->query("SELECT SUM(totalAmount) as total FROM billing WHERE status = 'paid'")->fetch()['total'];
$pendingBilling = $pdo->query("SELECT COUNT(*) as count FROM billing WHERE status = 'pending'")->fetch()['count'];
$pendingVerifications = $pdo->query("SELECT COUNT(*) as count FROM users WHERE isVerified = 0")->fetch()['count'];
$totalNotifications = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE isRead = 0")->fetch()['count'];
$totalAuditLogs = $pdo->query("SELECT COUNT(*) as count FROM audit_log")->fetch()['count'];

// Get recent activities
$recentActivities = $pdo->query("
    SELECT al.*, u.username 
    FROM audit_log al
    LEFT JOIN users u ON al.userId = u.userId
    ORDER BY al.timestamp DESC 
    LIMIT 10
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p>Here's what's happening with your account today.</p>
    </div>

    <!-- User Statistics -->
    <div class="stats-section">
        <h2>User Statistics</h2>
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
                <h3><?php echo $totalNurses; ?></h3>
                <p>Nurses</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $totalStaff; ?></h3>
                <p>Support Staff</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Patients</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $pendingVerifications; ?></h3>
                <p>Pending Verifications</p>
            </div>
        </div>
    </div>

    <!-- Appointment Statistics -->
    <div class="stats-section">
        <h2>Appointment Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card admin">
                <h3><?php echo $totalAppointments; ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $todayAppointments; ?></h3>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $upcomingAppointments; ?></h3>
                <p>Upcoming</p>
            </div>
        </div>
    </div>

    <!-- Medical Statistics -->
    <div class="stats-section">
        <h2>Medical Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card admin">
                <h3><?php echo $totalMedicalRecords; ?></h3>
                <p>Medical Records</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $totalLabTests; ?></h3>
                <p>Lab Tests</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $pendingLabTests; ?></h3>
                <p>Pending Tests</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $totalPrescriptions; ?></h3>
                <p>Prescriptions</p>
            </div>
        </div>
    </div>

    <!-- Financial Statistics -->
    <div class="stats-section">
        <h2>Financial Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card admin">
                <h3>$<?php echo number_format($totalRevenue ?? 0, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $pendingBilling; ?></h3>
                <p>Pending Payments</p>
            </div>
        </div>
    </div>

    <!-- System Statistics -->
    <div class="stats-section">
        <h2>System Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card admin">
                <h3><?php echo $totalDepartments; ?></h3>
                <p>Departments</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $totalNotifications; ?></h3>
                <p>Unread Notifications</p>
            </div>
            <div class="stat-card admin">
                <h3><?php echo $totalAuditLogs; ?></h3>
                <p>Audit Log Entries</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions - User Management -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> User Management</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="users.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Manage Users
                </a>
                <a href="staff.php" class="btn btn-primary">
                    <i class="fas fa-id-badge"></i> Manage Staff
                </a>
                <a href="patients.php" class="btn btn-outline">
                    <i class="fas fa-ambulance"></i> Manage Patients
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions - Medical Management -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-notes-medical"></i> Medical Management</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="medical-records.php" class="btn btn-primary">
                    <i class="fas fa-folder-open"></i> Medical Records
                </a>
                <a href="lab-tests.php" class="btn btn-primary">
                    <i class="fas fa-flask"></i> Lab Tests
                </a>
                <a href="prescriptions.php" class="btn btn-primary">
                    <i class="fas fa-prescription"></i> Prescriptions
                </a>
                <a href="vitals.php" class="btn btn-outline">
                    <i class="fas fa-heartbeat"></i> Patient Vitals
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions - Appointments & Billing -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-check"></i> Appointments & Billing</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="appointments.php" class="btn btn-primary">
                    <i class="fas fa-calendar-alt"></i> All Appointments
                </a>
                <a href="billing.php" class="btn btn-primary">
                    <i class="fas fa-dollar-sign"></i> Billing Management
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions - Organization -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-building"></i> Organization</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="departments.php" class="btn btn-primary">
                    <i class="fas fa-building"></i> Departments
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions - System & Reports -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-cog"></i> System & Reports</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="notifications.php" class="btn btn-primary">
                    <i class="fas fa-bell"></i> Notifications
                </a>
                <a href="audit-log.php" class="btn btn-primary">
                    <i class="fas fa-history"></i> Audit Log
                </a>
                <a href="system-settings.php" class="btn btn-primary">
                    <i class="fas fa-sliders-h"></i> System Settings
                </a>
                <a href="reports.php" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3>Recent System Activity</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivities as $activity): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></td>
                            <td data-label="Action"><?php echo $activity['action']; ?></td>
                            <td data-label="Details"><?php echo $activity['details']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>