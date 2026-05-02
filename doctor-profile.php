<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$doctorId = (int)($_GET['id'] ?? 0);

if (!$doctorId) {
    header("Location: doctors.php");
    exit();
}

// Get doctor details
$stmt = $pdo->prepare("
    SELECT d.*, u.firstName, u.lastName, u.email, u.phoneNumber,
           d.specialization, d.yearsOfExperience, d.consultationFee,
           d.biography, d.education, d.isAvailable,
           s.licenseNumber, s.hireDate, s.department,
           dep.name as departmentName
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    LEFT JOIN departments dep ON s.department = dep.name
    WHERE d.doctorId = ?
");
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: doctors.php");
    exit();
}

// Get doctor's availability summary
$availabilityStmt = $pdo->prepare("
    SELECT availabilityDate, startTime, endTime, isAvailable, isDayOff
    FROM doctor_availability 
    WHERE doctorId = ? AND availabilityDate >= CURDATE()
    ORDER BY availabilityDate
    LIMIT 10
");
$availabilityStmt->execute([$doctorId]);
$availabilities = $availabilityStmt->fetchAll();

// Get total patients count
$patientsStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT patientId) FROM (
        SELECT patientId FROM appointments WHERE doctorId = ?
        UNION
        SELECT patientId FROM medical_records WHERE doctorId = ?
        UNION
        SELECT patientId FROM lab_tests WHERE orderedBy = ?
    ) AS all_patients
");
$patientsStmt->execute([$doctorId, $doctorId, $doctorId]);
$totalPatients = $patientsStmt->fetchColumn();

// Get total appointments
$appointmentsStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctorId = ?");
$appointmentsStmt->execute([$doctorId]);
$totalAppointments = $appointmentsStmt->fetchColumn();

$pageTitle = "Dr. " . htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']) . " - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';
?>

<div class="root-container">
    <div class="root-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-md"></i> Doctor Profile</h1>
            <p>Detailed information about Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="doctors.php" class="root-btn root-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Doctors
            </a>
        </div>
    </div>

    <!-- Doctor Profile Card -->
    <div class="doctor-profile-main-card">
        <div class="doctor-profile-header">
            <div class="doctor-profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="doctor-profile-title">
                <h2>Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?></h2>
                <span class="doctor-profile-specialty-badge"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                <?php if ($doctor['isAvailable']): ?>
                    <span class="doctor-profile-status available">
                        <i class="fas fa-check-circle"></i> Available for Appointments
                    </span>
                <?php else: ?>
                    <span class="doctor-profile-status unavailable">
                        <i class="fas fa-times-circle"></i> Currently Unavailable
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="doctor-profile-body">
            <div class="doctor-profile-grid">
                <!-- Left Column -->
                <div class="doctor-profile-left">
                    <div class="doctor-info-section">
                        <h3><i class="fas fa-info-circle"></i> Professional Information</h3>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-stethoscope"></i> Specialization:</span>
                            <span class="info-value"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                        </div>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-briefcase"></i> Experience:</span>
                            <span class="info-value"><?php echo $doctor['yearsOfExperience']; ?>+ years</span>
                        </div>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-dollar-sign"></i> Consultation Fee:</span>
                            <span class="info-value">$<?php echo number_format($doctor['consultationFee'], 2); ?></span>
                        </div>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-building"></i> Department:</span>
                            <span class="info-value"><?php echo htmlspecialchars($doctor['departmentName'] ?: $doctor['department'] ?: 'General'); ?></span>
                        </div>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-id-card"></i> License Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($doctor['licenseNumber'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-calendar-alt"></i> Joined:</span>
                            <span class="info-value"><?php echo $doctor['hireDate'] ? date('F Y', strtotime($doctor['hireDate'])) : 'N/A'; ?></span>
                        </div>
                    </div>

                    <div class="doctor-info-section">
                        <h3><i class="fas fa-graduation-cap"></i> Education & Qualifications</h3>
                        <div class="doctor-education-content">
                            <?php if ($doctor['education']): ?>
                                <?php echo nl2br(htmlspecialchars($doctor['education'])); ?>
                            <?php else: ?>
                                <p class="text-muted">Education information not provided.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="doctor-profile-right">
                    <div class="doctor-info-section">
                        <h3><i class="fas fa-address-card"></i> Biography</h3>
                        <div class="doctor-biography-content">
                            <?php if ($doctor['biography']): ?>
                                <?php echo nl2br(htmlspecialchars($doctor['biography'])); ?>
                            <?php else: ?>
                                <p class="text-muted">
                                    Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?> 
                                    is an experienced <?php echo htmlspecialchars($doctor['specialization']); ?> 
                                    dedicated to providing high-quality healthcare services.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="doctor-info-section">
                        <h3><i class="fas fa-chart-bar"></i> Practice Statistics</h3>
                        <div class="doctor-stats-mini-grid">
                            <div class="doctor-stat-mini-card">
                                <div class="stat-mini-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-mini-content">
                                    <span class="stat-mini-number"><?php echo $totalPatients; ?></span>
                                    <span class="stat-mini-label">Total Patients</span>
                                </div>
                            </div>
                            <div class="doctor-stat-mini-card">
                                <div class="stat-mini-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-mini-content">
                                    <span class="stat-mini-number"><?php echo $totalAppointments; ?></span>
                                    <span class="stat-mini-label">Appointments</span>
                                </div>
                            </div>
                            <div class="doctor-stat-mini-card">
                                <div class="stat-mini-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-mini-content">
                                    <span class="stat-mini-number"><?php echo $doctor['yearsOfExperience']; ?>+</span>
                                    <span class="stat-mini-label">Years Experience</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="doctor-info-section">
                        <h3><i class="fas fa-clock"></i> Upcoming Availability</h3>
                        <?php if (empty($availabilities)): ?>
                            <p class="text-muted">No availability schedule set for upcoming dates.</p>
                        <?php else: ?>
                            <div class="doctor-availability-mini-list">
                                <?php 
                                $shownCount = 0;
                                foreach ($availabilities as $avail): 
                                    if ($shownCount >= 5) break;
                                    $shownCount++;
                                    $isDayOff = $avail['isDayOff'] == 1;
                                    $bgClass = $isDayOff ? 'day-off' : 'available';
                                ?>
                                    <div class="availability-mini-item <?php echo $bgClass; ?>">
                                        <span class="avail-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('D, M j', strtotime($avail['availabilityDate'])); ?>
                                        </span>
                                        <?php if ($isDayOff): ?>
                                            <span class="avail-status day-off-badge">Day Off</span>
                                        <?php else: ?>
                                            <span class="avail-time">
                                                <?php echo date('g:i A', strtotime($avail['startTime'])); ?> - 
                                                <?php echo date('g:i A', strtotime($avail['endTime'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="doctor-info-section">
                        <h3><i class="fas fa-envelope"></i> Contact Information</h3>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-envelope"></i> Email:</span>
                            <span class="info-value">
                                <a href="mailto:<?php echo htmlspecialchars($doctor['email']); ?>">
                                    <?php echo htmlspecialchars($doctor['email']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="doctor-info-item">
                            <span class="info-label"><i class="fas fa-phone"></i> Phone:</span>
                            <span class="info-value">
                                <a href="tel:<?php echo htmlspecialchars($doctor['phoneNumber']); ?>">
                                    <?php echo htmlspecialchars($doctor['phoneNumber'] ?: 'N/A'); ?>
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="doctor-profile-actions">
                <?php if (isLoggedIn() && $_SESSION['user_role'] === 'patient'): ?>
                    <a href="patient/appointments.php?doctor_id=<?php echo $doctor['doctorId']; ?>" class="root-btn root-btn-primary root-btn-large">
                        <i class="fas fa-calendar-plus"></i> Book Appointment with Dr. <?php echo htmlspecialchars($doctor['lastName']); ?>
                    </a>
                <?php elseif (!isLoggedIn()): ?>
                    <a href="login.php" class="root-btn root-btn-primary root-btn-large">
                        <i class="fas fa-sign-in-alt"></i> Login to Book Appointment
                    </a>
                <?php endif; ?>
                <a href="doctors.php" class="root-btn root-btn-outline">
                    <i class="fas fa-users"></i> View All Doctors
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>