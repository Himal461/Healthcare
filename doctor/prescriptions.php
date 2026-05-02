<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: dashboard.php");
    exit();
}

$doctorId = $doctor['doctorId'];

// Check if viewing specific patient's prescriptions
$patientId = (int)($_GET['patient_id'] ?? 0);
$prescriptionId = (int)($_GET['prescription_id'] ?? 0);
$viewPatient = null;

// If viewing single prescription, redirect to details
if ($prescriptionId) {
    // Verify this doctor has access to this prescription
    $verifyStmt = $pdo->prepare("
        SELECT p.* FROM prescriptions p
        JOIN medical_records mr ON p.recordId = mr.recordId
        WHERE p.prescriptionId = ? AND mr.doctorId = ?
    ");
    $verifyStmt->execute([$prescriptionId, $doctorId]);
    if ($verifyStmt->fetch()) {
        $_SESSION['info'] = "Prescription details view coming soon.";
    }
    header("Location: prescriptions.php");
    exit();
}

$pageTitle = "Prescriptions - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';

if ($patientId) {
    // Verify this doctor has access to this patient
    $verifyStmt = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT patientId FROM appointments WHERE patientId = ? AND doctorId = ?
            UNION
            SELECT patientId FROM medical_records WHERE patientId = ? AND doctorId = ?
            UNION
            SELECT patientId FROM lab_tests WHERE patientId = ? AND orderedBy = ?
        ) AS patient_access
    ");
    $verifyStmt->execute([$patientId, $doctorId, $patientId, $doctorId, $patientId, $doctorId]);
    
    if ($verifyStmt->fetchColumn() == 0) {
        $_SESSION['error'] = "You don't have permission to view this patient's prescriptions.";
        header("Location: patients.php");
        exit();
    }
    
    // Get patient details
    $stmt = $pdo->prepare("
        SELECT p.*, CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.email, u.phoneNumber
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE p.patientId = ? AND u.role = 'patient'
    ");
    $stmt->execute([$patientId]);
    $viewPatient = $stmt->fetch();
    
    if (!$viewPatient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: patients.php");
        exit();
    }
    
    $pageTitle = "Prescriptions - " . htmlspecialchars($viewPatient['patientName']) . " - HealthManagement";
}

include '../includes/header.php';

if ($patientId && $viewPatient) {
    // Display patient-specific prescriptions
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(u.firstName, ' ', u.lastName) as prescribedByDoctor,
               d.specialization,
               mr.diagnosis,
               mr.creationDate as consultationDate
        FROM prescriptions p
        JOIN medical_records mr ON p.recordId = mr.recordId
        JOIN doctors d ON p.prescribedBy = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        WHERE mr.patientId = ?
        ORDER BY p.createdAt DESC
    ");
    $stmt->execute([$patientId]);
    $prescriptions = $stmt->fetchAll();
    ?>
    
    <div class="doctor-container">
        <div class="doctor-page-header">
            <div class="header-title">
                <h1><i class="fas fa-prescription"></i> Prescriptions</h1>
                <p><?php echo htmlspecialchars($viewPatient['patientName']); ?></p>
            </div>
            <div class="header-actions">
                <a href="patients.php?view=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Patient
                </a>
                <a href="prescriptions.php" class="doctor-btn doctor-btn-outline">
                    <i class="fas fa-list"></i> All Prescriptions
                </a>
            </div>
        </div>
        
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
            </div>
            <div class="doctor-card-body">
                <div class="doctor-patient-info-grid">
                    <div>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($viewPatient['patientName']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($viewPatient['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($viewPatient['phoneNumber']); ?></p>
                    </div>
                    <div>
                        <p><strong>Allergies:</strong> <?php echo htmlspecialchars($viewPatient['knownAllergies'] ?: 'None'); ?></p>
                        <p><strong>Blood Type:</strong> <?php echo $viewPatient['bloodType'] ?: 'N/A'; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (empty($prescriptions)): ?>
            <div class="doctor-empty-state">
                <i class="fas fa-prescription"></i>
                <h3>No Prescriptions Found</h3>
                <p>This patient has no prescriptions yet.</p>
                <a href="consultation.php?patient_id=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-primary">
                    <i class="fas fa-plus"></i> Start Consultation
                </a>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($prescriptions as $p): ?>
                    <div class="doctor-card">
                        <div class="doctor-card-header" style="background: #f8fafc;">
                            <div>
                                <i class="fas fa-calendar-alt" style="color: #2563eb;"></i>
                                <?php echo date('F j, Y', strtotime($p['consultationDate'])); ?>
                            </div>
                            <div>
                                Dr. <?php echo htmlspecialchars($p['prescribedByDoctor']); ?>
                            </div>
                        </div>
                        <div class="doctor-card-body">
                            <p><strong>Medication:</strong> <?php echo htmlspecialchars($p['medicationName']); ?></p>
                            <p><strong>Dosage:</strong> <?php echo htmlspecialchars($p['dosage']); ?> | <strong>Frequency:</strong> <?php echo htmlspecialchars($p['frequency']); ?></p>
                            <?php if ($p['instructions']): ?>
                                <p style="margin-top: 10px; padding: 10px; background: #fffbeb; border-radius: 6px;">
                                    <strong>Instructions:</strong> <?php echo htmlspecialchars($p['instructions']); ?>
                                </p>
                            <?php endif; ?>
                            <p style="margin-top: 10px;">
                                <span class="doctor-status-badge doctor-status-<?php echo $p['status']; ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
<?php } else {
    // Display all prescriptions for this doctor's patients
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.*, 
               CONCAT(pt.firstName, ' ', pt.lastName) as patientName,
               pat.patientId,
               CONCAT(u.firstName, ' ', u.lastName) as prescribedByDoctor,
               mr.diagnosis,
               mr.creationDate as consultationDate
        FROM prescriptions p
        JOIN medical_records mr ON p.recordId = mr.recordId
        JOIN patients pat ON mr.patientId = pat.patientId
        JOIN users pt ON pat.userId = pt.userId
        JOIN doctors d ON p.prescribedBy = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        WHERE pt.role = 'patient'
        AND (
            mr.doctorId = ?
            OR pat.patientId IN (SELECT DISTINCT patientId FROM appointments WHERE doctorId = ?)
            OR pat.patientId IN (SELECT DISTINCT patientId FROM lab_tests WHERE orderedBy = ?)
        )
        ORDER BY p.createdAt DESC
        LIMIT 100
    ");
    $stmt->execute([$doctorId, $doctorId, $doctorId]);
    $prescriptions = $stmt->fetchAll();
    ?>
    
    <div class="doctor-container">
        <div class="doctor-page-header">
            <div class="header-title">
                <h1><i class="fas fa-prescription"></i> All Prescriptions</h1>
                <p>View prescriptions for all your patients</p>
            </div>
        </div>
        
        <?php if (empty($prescriptions)): ?>
            <div class="doctor-empty-state">
                <i class="fas fa-prescription"></i>
                <h3>No Prescriptions Found</h3>
                <p>You haven't prescribed any medications yet.</p>
            </div>
        <?php else: ?>
            <div class="doctor-card">
                <div class="doctor-table-responsive">
                    <table class="doctor-data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prescriptions as $p): ?>
                                <tr>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($p['createdAt'])); ?></td>
                                    <td data-label="Patient">
                                        <a href="prescriptions.php?patient_id=<?php echo $p['patientId']; ?>" style="color: #2563eb; text-decoration: none;">
                                            <?php echo htmlspecialchars($p['patientName']); ?>
                                        </a>
                                    </td>
                                    <td data-label="Medication"><?php echo htmlspecialchars($p['medicationName']); ?></td>
                                    <td data-label="Dosage"><?php echo htmlspecialchars($p['dosage']); ?></td>
                                    <td data-label="Frequency"><?php echo htmlspecialchars($p['frequency']); ?></td>
                                    <td data-label="Status">
                                        <span class="doctor-status-badge doctor-status-<?php echo $p['status']; ?>">
                                            <?php echo ucfirst($p['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="patients.php?view=<?php echo $p['patientId']; ?>" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                                            View Patient
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
<?php } ?>

<?php include '../includes/footer.php'; ?>