<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Admin Dashboard - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

// User statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM doctors WHERE isAvailable = 1")->fetchColumn();
$totalNurses = $pdo->query("SELECT COUNT(*) FROM nurses")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalAccountants = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'accountant'")->fetchColumn();

// Appointment statistics
$totalAppointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$todayAppointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(dateTime) = CURDATE()")->fetchColumn();
$upcomingAppointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE dateTime > NOW() AND status = 'scheduled'")->fetchColumn();

// Medical statistics
$totalMedicalRecords = $pdo->query("SELECT COUNT(*) FROM medical_records")->fetchColumn();
$totalLabTests = $pdo->query("SELECT COUNT(*) FROM lab_tests")->fetchColumn();
$pendingLabTests = $pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status IN ('ordered', 'in-progress')")->fetchColumn();
$totalPrescriptions = $pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();

// Financial statistics
$totalRevenue = getTotalRevenue();
$totalExpenses = getTotalExpenses();
$totalSalaries = getTotalSalariesPaid();
$netBalance = getNetBalance();
$totalPaid = $pdo->query("SELECT SUM(totalAmount) FROM bills WHERE status = 'paid'")->fetchColumn() ?? 0;
$totalUnpaid = $pdo->query("SELECT SUM(totalAmount) FROM bills WHERE status = 'unpaid'")->fetchColumn() ?? 0;

// Pending salaries this month
$pendingSalariesCount = $pdo->prepare("
    SELECT COUNT(*) FROM staff s
    JOIN users u ON s.userId = u.userId
    WHERE u.role IN ('doctor', 'nurse', 'staff', 'accountant')
    AND NOT EXISTS (
        SELECT 1 FROM salary_payments sp 
        WHERE sp.userId = u.userId AND sp.salaryMonth = ?
    )
");
$pendingSalariesCount->execute([date('Y-m')]);
$pendingSalaries = $pendingSalariesCount->fetchColumn();

// Recent activities
$recentActivities = $pdo->query("
    SELECT al.*, u.username 
    FROM audit_log al
    LEFT JOIN users u ON al.userId = u.userId
    ORDER BY al.timestamp DESC LIMIT 10
")->fetchAll();

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <!-- Welcome Section -->
    <div class="admin-welcome-section">
        <div class="admin-welcome-text">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>Admin Dashboard Overview</p>
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

    <!-- User Statistics -->
    <h2 style="color: #1e293b; margin-bottom: 20px;">User Statistics</h2>
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
        <div class="admin-stat-card nurses">
            <div class="admin-stat-icon"><i class="fas fa-user-nurse"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalNurses; ?></h3>
                <p>Nurses</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-user"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalStaff; ?></h3>
                <p>Support Staff</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-calculator"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalAccountants; ?></h3>
                <p>Accountants</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-user-injured"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Patients</p>
            </div>
        </div>
    </div>

    <!-- Appointment Statistics -->
    <h2 style="color: #1e293b; margin: 30px 0 20px;">Appointment Statistics</h2>
    <div class="admin-stats-grid">
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalAppointments; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $todayAppointments; ?></h3>
                <p>Today's</p>
            </div>
        </div>
        <div class="admin-stat-card appointments">
            <div class="admin-stat-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $upcomingAppointments; ?></h3>
                <p>Upcoming</p>
            </div>
        </div>
    </div>

    <!-- Medical Statistics -->
    <h2 style="color: #1e293b; margin: 30px 0 20px;">Medical Statistics</h2>
    <div class="admin-stats-grid">
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-notes-medical"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalMedicalRecords; ?></h3>
                <p>Medical Records</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-flask"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalLabTests; ?></h3>
                <p>Lab Tests</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $pendingLabTests; ?></h3>
                <p>Pending Tests</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalPrescriptions; ?></h3>
                <p>Prescriptions</p>
            </div>
        </div>
    </div>

    <!-- Financial Overview -->
    <h2 style="color: #1e293b; margin: 30px 0 20px;">Financial Overview</h2>
    <div class="admin-stats-grid">
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalRevenue, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                <p>Paid Bills</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalUnpaid, 2); ?></h3>
                <p>Outstanding</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-users"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalSalaries, 2); ?></h3>
                <p>Salaries Paid</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($netBalance, 2); ?></h3>
                <p>Net Balance</p>
            </div>
        </div>
    </div>

    <!-- Finance Quick Actions -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-dollar-sign"></i> Finance Quick Actions</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-quick-actions">
                <a href="salaries.php" class="admin-btn admin-btn-primary">
                    <i class="fas fa-money-bill-wave"></i> Manage Salaries
                    <?php if ($pendingSalaries > 0): ?>
                        <span class="badge" style="background: #f59e0b; color: white; padding: 3px 8px; border-radius: 20px; margin-left: 8px;"><?php echo $pendingSalaries; ?> pending</span>
                    <?php endif; ?>
                </a>
                <a href="revenue.php" class="admin-btn admin-btn-outline">
                    <i class="fas fa-chart-bar"></i> Revenue Report
                </a>
                <a href="billing.php" class="admin-btn admin-btn-outline">
                    <i class="fas fa-file-invoice"></i> Billing Management
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-quick-actions">
                <a href="users.php" class="admin-btn admin-btn-primary"><i class="fas fa-users"></i> Manage Users</a>
                <a href="staff.php" class="admin-btn admin-btn-primary"><i class="fas fa-id-badge"></i> Manage Staff</a>
                <a href="patients.php" class="admin-btn admin-btn-outline"><i class="fas fa-user-injured"></i> Manage Patients</a>
                <a href="appointments.php" class="admin-btn admin-btn-outline"><i class="fas fa-calendar-alt"></i> Appointments</a>
                <a href="reports.php" class="admin-btn admin-btn-outline"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="audit-log.php" class="admin-btn admin-btn-outline"><i class="fas fa-history"></i> Audit Log</a>
                <a href="departments.php" class="admin-btn admin-btn-outline"><i class="fas fa-building"></i> Departments</a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivities as $activity): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></td>
                            <td data-label="User"><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></td>
                            <td data-label="Action"><?php echo htmlspecialchars($activity['action']); ?></td>
                            <td data-label="Details"><?php echo htmlspecialchars($activity['details']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>