<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "Patient Dashboard - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header("Location: ../login.php");
    exit();
}

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'] ?? null;

if (!$patientId) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: ../logout.php");
    exit();
}

// ── Statistics ───────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE patientId = ?");
$stmt->execute([$patientId]);
$totalAppointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as upcoming 
    FROM appointments 
    WHERE patientId = ? AND dateTime > NOW() AND status = 'scheduled'
");
$stmt->execute([$patientId]);
$upcomingAppointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as completed 
    FROM appointments 
    WHERE patientId = ? AND status = 'completed'
");
$stmt->execute([$patientId]);
$completedAppointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM medical_records WHERE patientId = ?");
$stmt->execute([$patientId]);
$medicalRecords = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    WHERE mr.patientId = ?
");
$stmt->execute([$patientId]);
$prescriptionsCount = $stmt->fetchColumn();

// ── Unpaid Bills Count ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unpaid_count, SUM(totalAmount) as unpaid_total
    FROM bills 
    WHERE patientId = ? AND status = 'unpaid'
");
$stmt->execute([$patientId]);
$unpaidBills = $stmt->fetch(PDO::FETCH_ASSOC);
$unpaidCount = $unpaidBills['unpaid_count'] ?? 0;
$unpaidTotal = $unpaidBills['unpaid_total'] ?? 0;

// ── Upcoming appointments list ──────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT a.*, u.firstName, u.lastName, d.specialization, d.doctorId
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE a.patientId = ? AND a.dateTime > NOW() AND a.status = 'scheduled'
    ORDER BY a.dateTime ASC
    LIMIT 5
");
$stmt->execute([$patientId]);
$upcomingList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Recent medical records ──────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT mr.*, CONCAT(u.firstName, ' ', u.lastName) as doctorName, d.specialization
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE mr.patientId = ?
    ORDER BY mr.creationDate DESC
    LIMIT 5
");
$stmt->execute([$patientId]);
$recentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Patient'); ?>!</h1>
        <p>Manage your health and appointments from one place</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card patient">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $upcomingAppointments; ?></h3>
            <p>Upcoming</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $completedAppointments; ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $medicalRecords; ?></h3>
            <p>Medical Records</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $prescriptionsCount; ?></h3>
            <p>Prescriptions</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="appointments.php?view=book" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="view-appointments.php?view=list" class="btn btn-outline">
                    <i class="fas fa-calendar-alt"></i> View Appointments
                </a>
                <a href="medical-records.php" class="btn btn-outline">
                    <i class="fas fa-folder-open"></i> Medical Records
                </a>
                <a href="prescriptions.php" class="btn btn-outline">
                    <i class="fas fa-prescription"></i> Prescriptions
                </a>
                <a href="../profile.php" class="btn btn-outline">
                    <i class="fas fa-user-edit"></i> Update Profile
                </a>
                <a href="view-bills.php" class="btn btn-outline <?php echo $unpaidCount > 0 ? 'btn-warning' : ''; ?>">
                    <i class="fas fa-credit-card"></i> Make Payment
                    <?php if ($unpaidCount > 0): ?>
                        <span class="badge badge-danger"><?php echo $unpaidCount; ?> Bill<?php echo $unpaidCount > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
            <a href="appointments.php?view=list" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php if (empty($upcomingList)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem;">
                                No upcoming appointments
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upcomingList as $appointment): ?>
                            <tr>
                                <td data-label="Date & Time">
                                    <?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?>
                                </td>
                                <td data-label="Doctor">
                                    Dr. <?php echo htmlspecialchars($appointment['firstName'] . ' ' . $appointment['lastName']); ?>
                                </td>
                                <td data-label="Specialization">
                                    <?php echo htmlspecialchars($appointment['specialization'] ?: 'N/A'); ?>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a href="appointments.php?cancel=<?php echo $appointment['appointmentId']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Cancel this appointment?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Medical Records -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-notes-medical"></i> Recent Medical Records</h3>
            <a href="medical-records.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Diagnosis</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRecords)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem;">
                                No medical records found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRecords as $record): ?>
                            <tr>
                                <td data-label="Date">
                                    <?php echo date('M j, Y', strtotime($record['creationDate'])); ?>
                                </td>
                                <td data-label="Doctor">
                                    Dr. <?php echo htmlspecialchars($record['doctorName']); ?>
                                </td>
                                <td data-label="Specialization">
                                    <?php echo htmlspecialchars($record['specialization'] ?: 'N/A'); ?>
                                </td>
                                <td data-label="Diagnosis">
                                    <?php 
                                        $diag = $record['diagnosis'] ?? '';
                                        echo htmlspecialchars(substr($diag, 0, 50)) . (strlen($diag) > 50 ? '...' : '');
                                    ?>
                                </td>
                                <td data-label="Actions">
                                    <a href="medical-records.php?view=<?php echo $record['recordId']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Add these styles to your existing CSS */
.btn-warning {
    background: #ffc107;
    color: #212529;
    border: 1px solid #ffc107;<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "Patient Dashboard - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header("Location: ../login.php");
    exit();
}

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'] ?? null;

if (!$patientId) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: ../logout.php");
    exit();
}

// ── Statistics ───────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE patientId = ?");
$stmt->execute([$patientId]);
$totalAppointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as upcoming 
    FROM appointments 
    WHERE patientId = ? AND dateTime > NOW() AND status = 'scheduled'
");
$stmt->execute([$patientId]);
$upcomingAppointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as completed 
    FROM appointments 
    WHERE patientId = ? AND status = 'completed'
");
$stmt->execute([$patientId]);
$completedAppointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM medical_records WHERE patientId = ?");
$stmt->execute([$patientId]);
$medicalRecords = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    WHERE mr.patientId = ?
");
$stmt->execute([$patientId]);
$prescriptionsCount = $stmt->fetchColumn();

// ── Unpaid Bills Count ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unpaid_count, SUM(totalAmount) as unpaid_total
    FROM bills 
    WHERE patientId = ? AND status = 'unpaid'
");
$stmt->execute([$patientId]);
$unpaidBills = $stmt->fetch(PDO::FETCH_ASSOC);
$unpaidCount = $unpaidBills['unpaid_count'] ?? 0;
$unpaidTotal = $unpaidBills['unpaid_total'] ?? 0;

// ── Upcoming appointments list ──────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT a.*, u.firstName, u.lastName, d.specialization, d.doctorId
    FROM appointments a
    JOIN doctors d ON a.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE a.patientId = ? AND a.dateTime > NOW() AND a.status = 'scheduled'
    ORDER BY a.dateTime ASC
    LIMIT 5
");
$stmt->execute([$patientId]);
$upcomingList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Recent medical records ──────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT mr.*, CONCAT(u.firstName, ' ', u.lastName) as doctorName, d.specialization
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE mr.patientId = ?
    ORDER BY mr.creationDate DESC
    LIMIT 5
");
$stmt->execute([$patientId]);
$recentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Patient'); ?>!</h1>
        <p>Manage your health and appointments from one place</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card patient">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $upcomingAppointments; ?></h3>
            <p>Upcoming</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $completedAppointments; ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $medicalRecords; ?></h3>
            <p>Medical Records</p>
        </div>
        <div class="stat-card patient">
            <h3><?php echo $prescriptionsCount; ?></h3>
            <p>Prescriptions</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="appointments.php?view=book" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="appointments.php?view=list" class="btn btn-outline">
                    <i class="fas fa-calendar-alt"></i> View Appointments
                </a>
                <a href="my-medical-records.php" class="btn btn-outline">
                    <i class="fas fa-folder-open"></i> Medical Records
                </a>
                <a href="my-prescriptions.php" class="btn btn-outline">
                    <i class="fas fa-prescription"></i> Prescriptions
                </a>
                <a href="../profile.php" class="btn btn-outline">
                    <i class="fas fa-user-edit"></i> Update Profile
                </a>
                <a href="view-bills.php" class="btn btn-outline <?php echo $unpaidCount > 0 ? 'btn-warning' : ''; ?>">
                    <i class="fas fa-credit-card"></i> Make Payment
                    <?php if ($unpaidCount > 0): ?>
                        <span class="badge badge-danger"><?php echo $unpaidCount; ?> Bill<?php echo $unpaidCount > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
            <a href="appointments.php?view=list" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingList)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem;">
                                No upcoming appointments
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upcomingList as $appointment): ?>
                            <tr>
                                <td data-label="Date & Time">
                                    <?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?>
                                </td>
                                <td data-label="Doctor">
                                    Dr. <?php echo htmlspecialchars($appointment['firstName'] . ' ' . $appointment['lastName']); ?>
                                </td>
                                <td data-label="Specialization">
                                    <?php echo htmlspecialchars($appointment['specialization'] ?: 'N/A'); ?>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a href="appointments.php?cancel=<?php echo $appointment['appointmentId']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Cancel this appointment?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Medical Records -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-notes-medical"></i> Recent Medical Records</h3>
            <a href="my-medical-records.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Diagnosis</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRecords)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem;">
                                No medical records found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRecords as $record): ?>
                            <tr>
                                <td data-label="Date">
                                    <?php echo date('M j, Y', strtotime($record['creationDate'])); ?>
                                </td>
                                <td data-label="Doctor">
                                    Dr. <?php echo htmlspecialchars($record['doctorName']); ?>
                                </td>
                                <td data-label="Specialization">
                                    <?php echo htmlspecialchars($record['specialization'] ?: 'N/A'); ?>
                                </td>
                                <td data-label="Diagnosis">
                                    <?php 
                                        $diag = $record['diagnosis'] ?? '';
                                        echo htmlspecialchars(substr($diag, 0, 50)) . (strlen($diag) > 50 ? '...' : '');
                                    ?>
                                </td>
                                <td data-label="Actions">
                                    <a href="my-medical-records.php?view=<?php echo $record['recordId']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Add these styles to your existing CSS */
.btn-warning {
    background: #ffc107;
    color: #212529;
    border: 1px solid #ffc107;
}

.btn-warning:hover {
    background: #e0a800;
    color: #212529;
}

.badge {
    display: inline-block;
    padding: 3px 6px;
    font-size: 10px;
    font-weight: bold;
    border-radius: 10px;
    margin-left: 8px;
}

.badge-danger {
    background: #dc3545;
    color: white;
}

.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.quick-actions .btn {
    flex: 1;
    min-width: 150px;
    text-align: center;
}

.d-flex {
    display: flex;
}

.justify-content-between {
    justify-content: space-between;
}

.align-items-center {
    align-items: center;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    color: white;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.stat-card h3 {
    font-size: 32px;
    margin: 0 0 8px 0;
    color: white;
    font-weight: bold;
}

.stat-card p {
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    color: #495057;
    font-size: 18px;
}

.card-header i {
    margin-right: 8px;
    color: #1a75bc;
}

.card-body {
    padding: 20px;
}

.table-container {
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

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.btn-primary {
    background: #1a75bc;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #0e5a92;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}

.btn-danger:hover {
    background: #c82333;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
    
    .quick-actions .btn {
        min-width: 100%;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
        font-size: 12px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
}

.btn-warning:hover {
    background: #e0a800;
    color: #212529;
}

.badge {
    display: inline-block;
    padding: 3px 6px;
    font-size: 10px;
    font-weight: bold;
    border-radius: 10px;
    margin-left: 8px;
}

.badge-danger {
    background: #dc3545;
    color: white;
}

.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.quick-actions .btn {
    flex: 1;
    min-width: 150px;
    text-align: center;
}

.d-flex {
    display: flex;
}

.justify-content-between {
    justify-content: space-between;
}

.align-items-center {
    align-items: center;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    color: white;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.stat-card h3 {
    font-size: 32px;
    margin: 0 0 8px 0;
    color: white;
    font-weight: bold;
}

.stat-card p {
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    color: #495057;
    font-size: 18px;
}

.card-header i {
    margin-right: 8px;
    color: #1a75bc;
}

.card-body {
    padding: 20px;
}

.table-container {
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

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.btn-primary {
    background: #1a75bc;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #0e5a92;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}

.btn-danger:hover {
    background: #c82333;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
    
    .quick-actions .btn {
        min-width: 100%;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
        font-size: 12px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>