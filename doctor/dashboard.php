<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Doctor Dashboard - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
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
$today = date('Y-m-d');

// Today's appointments count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments 
    WHERE doctorId = ? AND DATE(dateTime) = CURDATE()
    AND status NOT IN ('cancelled', 'no-show')
");
$stmt->execute([$doctorId]);
$todayCount = $stmt->fetchColumn();

// Upcoming appointments count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments 
    WHERE doctorId = ? 
    AND DATE(dateTime) > CURDATE() 
    AND status IN ('scheduled', 'confirmed')
");
$stmt->execute([$doctorId]);
$upcomingCount = $stmt->fetchColumn();

// Total patients
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT patientId) FROM (
        SELECT patientId FROM appointments WHERE doctorId = ?
        UNION
        SELECT patientId FROM medical_records WHERE doctorId = ?
        UNION
        SELECT patientId FROM lab_tests WHERE orderedBy = ?
    ) AS all_patients
");
$stmt->execute([$doctorId, $doctorId, $doctorId]);
$totalPatients = $stmt->fetchColumn();

// Total appointments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctorId = ?");
$stmt->execute([$doctorId]);
$totalAppointments = $stmt->fetchColumn();

// Lab tests statistics
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lab_tests lt
    WHERE lt.orderedBy = ? 
    OR lt.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
    OR lt.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?)
");
$stmt->execute([$doctorId, $doctorId, $doctorId]);
$totalLabTests = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lab_tests lt
    WHERE (lt.orderedBy = ? 
        OR lt.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
        OR lt.patientId IN (SELECT DISTINCT patientId FROM medical_records WHERE doctorId = ?))
    AND lt.status IN ('ordered', 'in-progress')
");
$stmt->execute([$doctorId, $doctorId, $doctorId]);
$pendingLabTests = $stmt->fetchColumn();

// Get pending medical certificates for this doctor
$pendingCertificates = [];
$certStmt = $pdo->prepare("
    SELECT mc.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patient_name,
           u.email as patient_email
    FROM medical_certificates mc
    JOIN patients p ON mc.patient_id = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE mc.doctor_id = ? 
    AND (mc.approval_status = 'pending' OR mc.approval_status = 'pending_consultation' OR mc.approval_status IS NULL)
    ORDER BY mc.created_at DESC
    LIMIT 5
");
$certStmt->execute([$doctorId]);
$pendingCertificates = $certStmt->fetchAll();
$pendingCertificatesCount = count($pendingCertificates);

// Today's appointments - FIXED: Added appointmentId for smart detection
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber,
           p.dateOfBirth,
           p.bloodType,
           TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) as age,
           p.patientId,
           a.appointmentId
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.doctorId = ? AND DATE(a.dateTime) = CURDATE()
    AND a.status NOT IN ('cancelled', 'no-show')
    AND u.role = 'patient'
    ORDER BY a.dateTime ASC
");
$stmt->execute([$doctorId]);
$todayAppointments = $stmt->fetchAll();

// Upcoming appointments (next 7 days) - FIXED: Added appointmentId for smart detection
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.phoneNumber,
           p.dateOfBirth,
           p.bloodType,
           TIMESTAMPDIFF(YEAR, p.dateOfBirth, CURDATE()) as age,
           p.patientId,
           a.appointmentId
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.doctorId = ? 
    AND DATE(a.dateTime) > CURDATE() 
    AND DATE(a.dateTime) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND a.status IN ('scheduled', 'confirmed')
    AND u.role = 'patient'
    ORDER BY a.dateTime ASC
    LIMIT 5
");
$stmt->execute([$doctorId]);
$upcomingAppointments = $stmt->fetchAll();

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

/**
 * Helper function to get appointment link based on type
 */
function getAppointmentLink($pdo, $appointmentId, $patientId) {
    // Check if this appointment is for a medical certificate
    $certCheck = $pdo->prepare("
        SELECT certificate_id FROM medical_certificates 
        WHERE appointment_id = ? 
        AND (approval_status = 'pending_consultation' OR approval_status = 'pending' OR approval_status IS NULL)
        LIMIT 1
    ");
    $certCheck->execute([$appointmentId]);
    $certificate = $certCheck->fetch();
    
    if ($certificate) {
        return [
            'url' => 'certificate-consultation.php?certificate_id=' . $certificate['certificate_id'],
            'label' => 'Cert Consultation',
            'class' => 'doctor-btn doctor-btn-warning doctor-btn-sm',
            'icon' => 'fa-file-medical',
            'type' => 'certificate'
        ];
    }
    
    return [
        'url' => 'consultation.php?appointment_id=' . $appointmentId . '&patient_id=' . $patientId,
        'label' => 'Start',
        'class' => 'doctor-btn doctor-btn-primary doctor-btn-sm',
        'icon' => 'fa-stethoscope',
        'type' => 'regular'
    ];
}
?>

<div class="doctor-container">
    <!-- Welcome Section -->
    <div class="doctor-welcome-section">
        <div class="doctor-welcome-text">
            <h1>Welcome back, Dr. <?php echo htmlspecialchars($doctor['doctorName']); ?></h1>
            <p><?php echo htmlspecialchars($doctor['specialization']); ?> · Here's your practice overview</p>
        </div>
        <div class="doctor-welcome-stats">
            <div class="doctor-welcome-stat-item">
                <span class="stat-number"><?php echo $todayCount; ?></span>
                <span class="stat-label">Today's Patients</span>
            </div>
            <div class="doctor-welcome-stat-item">
                <span class="stat-number"><?php echo $upcomingCount; ?></span>
                <span class="stat-label">Upcoming</span>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="doctor-stats-grid">
        <div class="doctor-stat-card today">
            <div class="doctor-stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $todayCount; ?></h3>
                <p>Today's Appointments</p>
                <small><?php echo date('l, F j'); ?></small>
            </div>
        </div>
        
        <div class="doctor-stat-card upcoming">
            <div class="doctor-stat-icon"><i class="fas fa-calendar-plus"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $upcomingCount; ?></h3>
                <p>Upcoming</p>
                <small>Next 7 days</small>
            </div>
        </div>
        
        <div class="doctor-stat-card patients">
            <div class="doctor-stat-icon"><i class="fas fa-users"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $totalPatients; ?></h3>
                <p>Total Patients</p>
                <small>Under your care</small>
            </div>
        </div>
        
        <div class="doctor-stat-card appointments">
            <div class="doctor-stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $totalAppointments; ?></h3>
                <p>Total Appointments</p>
                <small>All time</small>
            </div>
        </div>
        
        <div class="doctor-stat-card labtests">
            <div class="doctor-stat-icon"><i class="fas fa-flask"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $totalLabTests; ?></h3>
                <p>Lab Tests</p>
                <small><?php echo $pendingLabTests; ?> pending</small>
            </div>
        </div>
        
        <?php if ($pendingCertificatesCount > 0): ?>
        <div class="doctor-stat-card certificates">
            <div class="doctor-stat-icon"><i class="fas fa-file-medical"></i></div>
            <div class="doctor-stat-content">
                <h3><?php echo $pendingCertificatesCount; ?></h3>
                <p>Pending Certificates</p>
                <small>Awaiting consultation</small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="doctor-quick-actions">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="doctor-actions-grid">
            <a href="appointments.php" class="doctor-action-card">
                <i class="fas fa-calendar-alt"></i>
                <span>View Schedule</span>
            </a>
            <a href="patients.php" class="doctor-action-card">
                <i class="fas fa-user-injured"></i>
                <span>My Patients</span>
            </a>
            <a href="prescriptions.php" class="doctor-action-card">
                <i class="fas fa-prescription"></i>
                <span>Prescriptions</span>
            </a>
            <a href="lab-tests.php" class="doctor-action-card">
                <i class="fas fa-flask"></i>
                <span>Lab Tests</span>
            </a>
            <a href="availability.php" class="doctor-action-card">
                <i class="fas fa-clock"></i>
                <span>Set Availability</span>
            </a>
            <a href="../profile.php" class="doctor-action-card">
                <i class="fas fa-user-edit"></i>
                <span>Update Profile</span>
            </a>
        </div>
    </div>

    <!-- Pending Medical Certificates -->
    <?php if (!empty($pendingCertificates)): ?>
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-file-medical" style="color: #f59e0b;"></i> Pending Medical Certificates (<?php echo count($pendingCertificates); ?>)</h3>
            <span class="doctor-status-badge" style="background: #fef3c7; color: #92400e;">Awaiting Consultation</span>
        </div>
        <div class="doctor-table-responsive">
            <table class="doctor-data-table">
                <thead>
                    <tr>
                        <th>Certificate #</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingCertificates as $cert): ?>
                        <tr>
                            <td data-label="Certificate #">
                                <strong><?php echo htmlspecialchars($cert['certificate_number']); ?></strong>
                            </td>
                            <td data-label="Patient">
                                <strong><?php echo htmlspecialchars($cert['patient_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($cert['patient_email']); ?></small>
                            </td>
                            <td data-label="Type">
                                <?php 
                                $typeLabels = [
                                    'work' => 'Work Leave',
                                    'school' => 'School Leave',
                                    'travel' => 'Travel',
                                    'insurance' => 'Insurance',
                                    'other' => 'Other'
                                ];
                                echo $typeLabels[$cert['certificate_type']] ?? ucfirst($cert['certificate_type']);
                                ?>
                            </td>
                            <td data-label="Period">
                                <?php echo date('M j', strtotime($cert['start_date'])) . ' - ' . date('M j, Y', strtotime($cert['end_date'])); ?>
                            </td>
                            <td data-label="Requested">
                                <?php echo date('M j, g:i A', strtotime($cert['created_at'])); ?>
                            </td>
                            <td data-label="Actions">
                                <a href="certificate-consultation.php?certificate_id=<?php echo $cert['certificate_id']; ?>" class="doctor-btn doctor-btn-primary doctor-btn-sm">
                                    <i class="fas fa-stethoscope"></i> Start Consultation
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Today's Appointments -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-calendar-day"></i> Today's Appointments</h3>
            <a href="appointments.php" class="doctor-view-all">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <?php if (empty($todayAppointments)): ?>
            <div class="doctor-empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No appointments scheduled for today.</p>
            </div>
        <?php else: ?>
            <div class="doctor-table-responsive">
                <table class="doctor-data-table">
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
                        <?php foreach ($todayAppointments as $appointment): 
                            $linkData = getAppointmentLink($pdo, $appointment['appointmentId'], $appointment['patientId']);
                        ?>
                            <tr>
                                <td data-label="Time"><strong><?php echo date('g:i A', strtotime($appointment['dateTime'])); ?></strong></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($appointment['patientName']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($appointment['phoneNumber']); ?></small>
                                    <?php if ($linkData['type'] === 'certificate'): ?>
                                        <br><span class="doctor-cert-badge">Certificate</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Age"><?php echo $appointment['age']; ?></td>
                                <td data-label="Blood Type"><?php echo $appointment['bloodType'] ?: 'N/A'; ?></td>
                                <td data-label="Status">
                                    <span class="doctor-status-badge doctor-status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($appointment['status'] == 'completed'): ?>
                                        <a href="view-consultation.php?appointment_id=<?php echo $appointment['appointmentId']; ?>" class="doctor-btn doctor-btn-info doctor-btn-sm">View</a>
                                    <?php elseif ($appointment['status'] != 'cancelled'): ?>
                                        <a href="<?php echo $linkData['url']; ?>" class="<?php echo $linkData['class']; ?>">
                                            <i class="fas <?php echo $linkData['icon']; ?>"></i> <?php echo $linkData['label']; ?>
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
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-calendar-week"></i> Upcoming Appointments</h3>
            <a href="appointments.php" class="doctor-view-all">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <?php if (empty($upcomingAppointments)): ?>
            <div class="doctor-empty-state">
                <i class="fas fa-calendar-week"></i>
                <p>No upcoming appointments in the next 7 days.</p>
            </div>
        <?php else: ?>
            <div class="doctor-table-responsive">
                <table class="doctor-data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Age</th>
                            <th>Blood Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAppointments as $appointment): 
                            $linkData = getAppointmentLink($pdo, $appointment['appointmentId'], $appointment['patientId']);
                        ?>
                            <tr>
                                <td data-label="Date & Time"><strong><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?></strong></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($appointment['patientName']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($appointment['phoneNumber']); ?></small>
                                    <?php if ($linkData['type'] === 'certificate'): ?>
                                        <br><span class="doctor-cert-badge">Certificate</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Age"><?php echo $appointment['age']; ?></td>
                                <td data-label="Blood Type"><?php echo $appointment['bloodType'] ?: 'N/A'; ?></td>
                                <td data-label="Actions">
                                    <a href="<?php echo $linkData['url']; ?>" class="<?php echo $linkData['class']; ?>">
                                        <i class="fas <?php echo $linkData['icon']; ?>"></i> <?php echo $linkData['label']; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.doctor-stat-card.certificates .doctor-stat-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.doctor-cert-badge {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    margin-top: 3px;
}

.doctor-btn-warning {
    background: #f59e0b;
    color: white;
}

.doctor-btn-warning:hover {
    background: #d97706;
}
</style>

<?php include '../includes/footer.php'; ?>