<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Doctor Dashboard - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get doctor details
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.specialization, d.consultationFee,
           CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: ../login.php");
    exit();
}

$doctorId = $doctor['doctorId'];

// Get today's date
$today = date('Y-m-d');

// Get statistics
// Today's appointments count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE doctorId = ? AND DATE(dateTime) = ?
");
$stmt->execute([$doctorId, $today]);
$todayCount = $stmt->fetch()['count'];

// Upcoming appointments count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE doctorId = ? AND DATE(dateTime) > ? AND status NOT IN ('cancelled', 'completed')
");
$stmt->execute([$doctorId, $today]);
$upcomingCount = $stmt->fetch()['count'];

// Total patients
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT patientId) as count FROM appointments 
    WHERE doctorId = ?
");
$stmt->execute([$doctorId]);
$totalPatients = $stmt->fetch()['count'];

// Total appointments
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM appointments WHERE doctorId = ?
");
$stmt->execute([$doctorId]);
$totalAppointments = $stmt->fetch()['count'];

// Get today's appointments
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber,
           p.dateOfBirth,
           p.bloodType,
           TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) as age
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.doctorId = ? AND DATE(a.dateTime) = ?
    ORDER BY a.dateTime ASC
");
$stmt->execute([$doctorId, $today]);
$todayAppointments = $stmt->fetchAll();

// Get upcoming appointments (next 7 days excluding today)
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber,
           p.dateOfBirth,
           p.bloodType,
           TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) as age
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.doctorId = ? AND DATE(a.dateTime) > ? AND DATE(a.dateTime) <= DATE_ADD(?, INTERVAL 7 DAY)
      AND a.status NOT IN ('cancelled', 'completed')
    ORDER BY a.dateTime ASC
    LIMIT 5
");
$stmt->execute([$doctorId, $today, $today]);
$upcomingAppointments = $stmt->fetchAll();
?>

<div class="dashboard">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Welcome back, Dr. <?php echo htmlspecialchars($doctor['doctorName']); ?></h1>
            <p>Here's what's happening with your practice today.</p>
        </div>
        <div class="weather-widget">
            <i class="fas fa-sun"></i>
            <span class="temperature">27°C</span>
            <span class="weather-text">Partly sunny</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $todayCount; ?></h3>
                <p>Today's Appointments</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $upcomingCount; ?></h3>
                <p>Upcoming Appointments</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $totalAppointments; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions - Horizontal Layout -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="actions-grid-horizontal">
            <a href="schedule.php" class="action-card">
                <i class="fas fa-calendar-alt"></i>
                <span>View Schedule</span>
            </a>
            <a href="patients.php" class="action-card">
                <i class="fas fa-user-injured"></i>
                <span>My Patients</span>
            </a>
            <a href="prescriptions.php" class="action-card">
                <i class="fas fa-prescription"></i>
                <span>Prescriptions</span>
            </a>
            <a href="availability.php" class="action-card">
                <i class="fas fa-clock"></i>
                <span>Set Availability</span>
            </a>
            <a href="profile.php" class="action-card">
                <i class="fas fa-user-edit"></i>
                <span>Update Profile</span>
            </a>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="appointments-section">
        <h3>Today's Appointments (<?php echo date('F j, Y'); ?>)</h3>
        
        <?php if (empty($todayAppointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No appointments scheduled for today.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Age</th>
                            <th>Blood Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayAppointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['patientName']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($appointment['phoneNumber']); ?></small>
                                </td>
                                <td><?php echo $appointment['age']; ?></td>
                                <td><?php echo $appointment['bloodType'] ?: 'N/A'; ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    switch($appointment['status']) {
                                        case 'scheduled':
                                            $statusClass = 'status-scheduled';
                                            $statusText = 'Scheduled';
                                            break;
                                        case 'confirmed':
                                            $statusClass = 'status-confirmed';
                                            $statusText = 'Confirmed';
                                            break;
                                        case 'in-progress':
                                            $statusClass = 'status-progress';
                                            $statusText = 'In Progress';
                                            break;
                                        case 'completed':
                                            $statusClass = 'status-completed';
                                            $statusText = 'Completed';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'status-cancelled';
                                            $statusText = 'Cancelled';
                                            break;
                                        default:
                                            $statusClass = 'status-scheduled';
                                            $statusText = ucfirst($appointment['status']);
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($appointment['status'] == 'completed'): ?>
                                        <a href="view-consultation.php?appointment_id=<?php echo $appointment['appointmentId']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php elseif ($appointment['status'] == 'cancelled'): ?>
                                        <span class="text-muted">Cancelled</span>
                                    <?php else: ?>
                                        <a href="consultation.php?appointment_id=<?php echo $appointment['appointmentId']; ?>&patient_id=<?php echo $appointment['patientId']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-play"></i> Start
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Appointments -->
    <div class="appointments-section">
        <h3>Upcoming Appointments</h3>
        
        <?php if (empty($upcomingAppointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-week"></i>
                <p>No upcoming appointments in the next 7 days.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Age</th>
                            <th>Blood Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['patientName']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($appointment['phoneNumber']); ?></small>
                                </td>
                                <td><?php echo $appointment['age']; ?></td>
                                <td><?php echo $appointment['bloodType'] ?: 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge status-scheduled">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="consultation.php?appointment_id=<?php echo $appointment['appointmentId']; ?>&patient_id=<?php echo $appointment['patientId']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-play"></i> Start
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($upcomingAppointments) >= 5): ?>
                <div class="view-all">
                    <a href="schedule.php" class="btn btn-outline">View All Appointments</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    color: white;
}

.welcome-text h1 {
    margin: 0 0 5px 0;
    font-size: 24px;
}

.welcome-text p {
    margin: 0;
    opacity: 0.9;
}

.weather-widget {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.2);
    padding: 10px 20px;
    border-radius: 50px;
}

.weather-widget i {
    font-size: 24px;
}

.temperature {
    font-size: 24px;
    font-weight: bold;
}

.weather-text {
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: #e3f2fd;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon i {
    font-size: 28px;
    color: #1a75bc;
}

.stat-info h3 {
    margin: 0;
    font-size: 28px;
    color: #333;
}

.stat-info p {
    margin: 5px 0 0;
    color: #666;
    font-size: 14px;
}

/* Quick Actions - Horizontal Layout */
.quick-actions {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.quick-actions h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.actions-grid-horizontal {
    display: flex;
    flex-direction: row;
    justify-content: space-around;
    gap: 15px;
    flex-wrap: wrap;
}

.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: #f8f9fa;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    min-width: 120px;
    flex: 1;
}

.action-card:hover {
    background: #e9ecef;
    transform: translateY(-3px);
}

.action-card i {
    font-size: 24px;
    color: #1a75bc;
}

.action-card span {
    color: #495057;
    font-size: 14px;
    font-weight: 500;
}

.appointments-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.appointments-section h3 {
    margin: 0 0 20px 0;
    color: #333;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.data-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-scheduled {
    background: #e3f2fd;
    color: #1976d2;
}

.status-confirmed {
    background: #e8f5e9;
    color: #388e3c;
}

.status-progress {
    background: #fff3e0;
    color: #f57c00;
}

.status-completed {
    background: #e0f2fe;
    color: #0284c7;
}

.status-cancelled {
    background: #ffebee;
    color: #d32f2f;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: #1a75bc;
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #0e5a92;
}

.btn-info {
    background: #17a2b8;
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-info:hover {
    background: #138496;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 10px;
    color: #dee2e6;
}

.empty-state p {
    margin: 0;
}

.view-all {
    text-align: center;
    margin-top: 20px;
}

.text-muted {
    color: #6c757d;
    font-style: italic;
}

@media (max-width: 768px) {
    .welcome-section {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid-horizontal {
        flex-direction: column;
    }
    
    .action-card {
        flex-direction: row;
        justify-content: center;
        gap: 15px;
    }
    
    .data-table {
        font-size: 12px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>