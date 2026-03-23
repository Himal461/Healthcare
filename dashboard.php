<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$pageTitle = "Dashboard - HealthManagement";
include 'includes/header.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Get user-specific data based on role
if ($userRole === 'patient') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_appointments 
        FROM appointments a 
        JOIN patients p ON a.patientId = p.patientId 
        WHERE p.userId = ?
    ");
    $stmt->execute([$userId]);
    $appointmentCount = $stmt->fetch()['total_appointments'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as upcoming_appointments 
        FROM appointments a 
        JOIN patients p ON a.patientId = p.patientId 
        WHERE p.userId = ? AND a.dateTime > NOW() AND a.status = 'scheduled'
    ");
    $stmt->execute([$userId]);
    $upcomingAppointments = $stmt->fetch()['upcoming_appointments'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as medical_records 
        FROM medical_records mr 
        JOIN patients p ON mr.patientId = p.patientId 
        WHERE p.userId = ?
    ");
    $stmt->execute([$userId]);
    $medicalRecords = $stmt->fetch()['medical_records'];
    
} elseif ($userRole === 'doctor') {
    $stmt = $pdo->prepare("
        SELECT d.doctorId 
        FROM doctors d 
        JOIN staff s ON d.staffId = s.staffId 
        WHERE s.userId = ?
    ");
    $stmt->execute([$userId]);
    $doctor = $stmt->fetch();
    $doctorId = $doctor['doctorId'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as today_appointments 
        FROM appointments 
        WHERE doctorId = ? AND DATE(dateTime) = CURDATE() AND status = 'scheduled'
    ");
    $stmt->execute([$doctorId]);
    $todayAppointments = $stmt->fetch()['today_appointments'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT patientId) as total_patients 
        FROM appointments 
        WHERE doctorId = ?
    ");
    $stmt->execute([$doctorId]);
    $totalPatients = $stmt->fetch()['total_patients'];
    
} elseif ($userRole === 'admin') {
    $totalUsers = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    $totalDoctors = $pdo->query("SELECT COUNT(*) as count FROM doctors")->fetch()['count'];
    $totalPatients = $pdo->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
    $todayAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(dateTime) = CURDATE()")->fetch()['count'];
    $pendingVerifications = $pdo->query("SELECT COUNT(*) as count FROM users WHERE isVerified = 0")->fetch()['count'];
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p>Here's what's happening with your account today.</p>
    </div>
    
    <div class="stats-grid">
        <?php if ($userRole === 'patient'): ?>
            <div class="stat-card">
                <h3><?php echo $appointmentCount; ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $upcomingAppointments; ?></h3>
                <p>Upcoming Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $medicalRecords; ?></h3>
                <p>Medical Records</p>
            </div>
            <div class="stat-card">
                <h3>0</h3>
                <p>Prescriptions</p>
            </div>
            
        <?php elseif ($userRole === 'doctor'): ?>
            <div class="stat-card">
                <h3><?php echo $todayAppointments; ?></h3>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Total Patients</p>
            </div>
            <div class="stat-card">
                <h3>0</h3>
                <p>Pending Consultations</p>
            </div>
            <div class="stat-card">
                <h3>0</h3>
                <p>Medical Records</p>
            </div>
            
        <?php elseif ($userRole === 'admin'): ?>
            <div class="stat-card">
                <h3><?php echo $totalUsers; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $totalDoctors; ?></h3>
                <p>Doctors</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Patients</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $todayAppointments; ?></h3>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $pendingVerifications; ?></h3>
                <p>Pending Verifications</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <?php if ($userRole === 'patient'): ?>
                    <a href="patient/appointments.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                    <a href="patient/appointments.php" class="btn btn-outline">
                        <i class="fas fa-calendar-alt"></i> View Appointments
                    </a>
                    <a href="patient/medical-records.php" class="btn btn-outline">
                        <i class="fas fa-folder-open"></i> Medical Records
                    </a>
                    <a href="patient/prescriptions.php" class="btn btn-outline">
                        <i class="fas fa-prescription"></i> Prescriptions
                    </a>
                    <a href="profile.php" class="btn btn-outline">
                        <i class="fas fa-user-edit"></i> Update Profile
                    </a>
                    
                <?php elseif ($userRole === 'doctor'): ?>
                    <a href="doctor/appointments.php" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> View Schedule
                    </a>
                    <a href="doctor/patients.php" class="btn btn-outline">
                        <i class="fas fa-users"></i> My Patients
                    </a>
                    <a href="doctor/availability.php" class="btn btn-outline">
                        <i class="fas fa-clock"></i> Set Availability
                    </a>
                    <a href="profile.php" class="btn btn-outline">
                        <i class="fas fa-user-edit"></i> Update Profile
                    </a>
                    
                <?php elseif ($userRole === 'admin'): ?>
                    <a href="admin/users.php" class="btn btn-primary">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                    <a href="admin/appointments.php" class="btn btn-outline">
                        <i class="fas fa-calendar-alt"></i> All Appointments
                    </a>
                    <a href="admin/doctors.php" class="btn btn-outline">
                        <i class="fas fa-user-md"></i> Manage Doctors
                    </a>
                    <a href="admin/reports.php" class="btn btn-outline">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3>Recent Activity</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM audit_log WHERE userId = ? ORDER BY timestamp DESC LIMIT 10");
                    $stmt->execute([$userId]);
                    $activities = $stmt->fetchAll();
                    
                    if (empty($activities)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">No recent activity</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></td>
                                <td data-label="Action"><?php echo $activity['action']; ?></td>
                                <td data-label="Details"><?php echo $activity['details']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.quick-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

@media (max-width: 768px) {
    .quick-actions {
        flex-direction: column;
    }
    
    .quick-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<?php include 'includes/footer.php'; ?>