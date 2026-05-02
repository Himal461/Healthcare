<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$appointmentId = (int)($_GET['appointment_id'] ?? 0);

if (!$appointmentId) {
    $_SESSION['error'] = "Appointment ID required.";
    header("Location: appointments.php");
    exit();
}

$pageTitle = "View Consultation - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';

// Get consultation details
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName, 
           pu.email as patientEmail, 
           pu.phoneNumber as patientPhone,
           p.dateOfBirth, 
           p.bloodType, 
           p.knownAllergies,
           p.patientId,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization,
           a.dateTime as appointmentDate, 
           a.status as appointmentStatus
    FROM medical_records mr 
    JOIN patients p ON mr.patientId = p.patientId 
    JOIN users pu ON p.userId = pu.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE mr.appointmentId = ? AND pu.role = 'patient'
    ORDER BY mr.creationDate DESC 
    LIMIT 1
");
$stmt->execute([$appointmentId]);
$consultation = $stmt->fetch();

if (!$consultation) {
    $_SESSION['error'] = "Consultation not found.";
    header("Location: appointments.php");
    exit();
}

$patientId = $consultation['patientId'];
$recordId = $consultation['recordId'];

// Get prescriptions
$stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE recordId = ? ORDER BY createdAt DESC");
$stmt->execute([$recordId]);
$prescriptions = $stmt->fetchAll();

// Get vitals
$stmt = $pdo->prepare("
    SELECT v.*, CONCAT(u.firstName, ' ', u.lastName) as recordedByName
    FROM vitals v
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    WHERE v.recordId = ? 
    ORDER BY v.recordedDate DESC
");
$stmt->execute([$recordId]);
$vitals = $stmt->fetchAll();

// Get bill
$stmt = $pdo->prepare("SELECT * FROM bills WHERE recordId = ? LIMIT 1");
$stmt->execute([$recordId]);
$bill = $stmt->fetch();

$additionalCharges = [];
if ($bill) { 
    $stmt = $pdo->prepare("SELECT * FROM bill_charges WHERE billId = ?"); 
    $stmt->execute([$bill['billId']]); 
    $additionalCharges = $stmt->fetchAll(); 
}

// Get lab tests
$stmt = $pdo->prepare("
    SELECT lt.*, CONCAT(u.firstName, ' ', u.lastName) as orderedByName
    FROM lab_tests lt
    LEFT JOIN doctors d ON lt.orderedBy = d.doctorId
    LEFT JOIN staff s ON d.staffId = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    WHERE lt.recordId = ?
    ORDER BY lt.orderedDate DESC
");
$stmt->execute([$recordId]);
$labTests = $stmt->fetchAll();
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> Consultation Details</h1>
            <p><?php echo date('F j, Y', strtotime($consultation['creationDate'])); ?></p>
        </div>
        <div class="header-actions">
            <a href="appointments.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
            <a href="view-patient.php?id=<?php echo $patientId; ?>" class="admin-btn admin-btn-outline">
                <i class="fas fa-user"></i> View Patient
            </a>
            <?php if ($bill): ?>
                <a href="view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="admin-btn admin-btn-primary">
                    <i class="fas fa-file-invoice"></i> View Bill
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="admin-btn admin-btn-info">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-patient-info-grid">
                <div class="admin-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($consultation['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($consultation['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($consultation['patientPhone']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $consultation['dateOfBirth'] ? date('M j, Y', strtotime($consultation['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($consultation['dateOfBirth']); ?></p>
                </div>
                <div class="admin-info-group">
                    <h4>Medical Information</h4>
                    <p><strong>Blood Type:</strong> <?php echo $consultation['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($consultation['knownAllergies'] ?: 'None reported'); ?></p>
                </div>
                <div class="admin-info-group">
                    <h4>Consultation Info</h4>
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($consultation['doctorName']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($consultation['specialization']); ?></p>
                    <p><strong>Consultation Date:</strong> <?php echo date('F j, Y g:i A', strtotime($consultation['creationDate'])); ?></p>
                    <?php if ($consultation['appointmentDate']): ?>
                        <p><strong>Appointment Date:</strong> <?php echo date('F j, Y g:i A', strtotime($consultation['appointmentDate'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Diagnosis and Treatment -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-stethoscope"></i> Diagnosis & Treatment</h3>
        </div>
        <div class="admin-card-body">
            <h4 style="color: #1e293b; margin-bottom: 15px;">Diagnosis</h4>
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                <?php echo nl2br(htmlspecialchars($consultation['diagnosis'] ?: 'No diagnosis recorded')); ?>
            </div>
            
            <?php if ($consultation['treatmentNotes']): ?>
                <h4 style="color: #1e293b; margin-bottom: 15px;">Treatment Notes</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <?php echo nl2br(htmlspecialchars($consultation['treatmentNotes'])); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($consultation['followUpDate']): ?>
                <h4 style="color: #1e293b; margin-bottom: 15px;">Follow-up Date</h4>
                <div style="background: #e8f2ff; padding: 15px 20px; border-radius: 12px;">
                    <i class="fas fa-calendar-check" style="color: #dc2626;"></i>
                    <strong><?php echo date('F j, Y', strtotime($consultation['followUpDate'])); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vitals -->
    <?php if (!empty($vitals)): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
            </div>
            <div class="admin-card-body">
                <?php foreach ($vitals as $vital): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 12px;">
                        <p style="margin-bottom: 15px; color: #64748b;">
                            <i class="fas fa-calendar-alt"></i> 
                            Recorded on <?php echo date('F j, Y g:i A', strtotime($vital['recordedDate'])); ?>
                            by <?php echo htmlspecialchars($vital['recordedByName'] ?: 'Nurse'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                            <?php if ($vital['height']): ?>
                                <div><strong>Height:</strong> <?php echo $vital['height']; ?> cm</div>
                            <?php endif; ?>
                            <?php if ($vital['weight']): ?>
                                <div><strong>Weight:</strong> <?php echo $vital['weight']; ?> kg</div>
                            <?php endif; ?>
                            <?php if ($vital['bodyTemperature']): ?>
                                <div><strong>Temperature:</strong> <?php echo $vital['bodyTemperature']; ?> °C</div>
                            <?php endif; ?>
                            <?php if ($vital['bloodPressureSystolic']): ?>
                                <div><strong>Blood Pressure:</strong> <?php echo $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic']; ?> mmHg</div>
                            <?php endif; ?>
                            <?php if ($vital['heartRate']): ?>
                                <div><strong>Heart Rate:</strong> <?php echo $vital['heartRate']; ?> bpm</div>
                            <?php endif; ?>
                            <?php if ($vital['oxygenSaturation']): ?>
                                <div><strong>O₂ Saturation:</strong> <?php echo $vital['oxygenSaturation']; ?>%</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($vital['notes']): ?>
                            <p style="margin-top: 15px;"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($vital['notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Prescriptions -->
    <?php if (!empty($prescriptions)): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; gap: 15px;">
                    <?php foreach ($prescriptions as $p): ?>
                        <div style="background: #f8fafc; padding: 18px; border-radius: 12px; border-left: 4px solid #dc2626;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <strong style="font-size: 16px; color: #dc2626;">
                                    <i class="fas fa-capsules"></i> <?php echo htmlspecialchars($p['medicationName']); ?>
                                </strong>
                                <span class="admin-status-badge admin-status-<?php echo $p['status']; ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                                <p><strong>Dosage:</strong> <?php echo htmlspecialchars($p['dosage']); ?></p>
                                <p><strong>Frequency:</strong> <?php echo htmlspecialchars($p['frequency']); ?></p>
                                <p><strong>Duration:</strong> <?php echo date('M j, Y', strtotime($p['startDate'])); ?> - <?php echo $p['endDate'] ? date('M j, Y', strtotime($p['endDate'])) : 'Ongoing'; ?></p>
                            </div>
                            <?php if ($p['instructions']): ?>
                                <p style="margin-top: 12px; padding: 10px; background: #fffbeb; border-radius: 6px;">
                                    <strong>Instructions:</strong> <?php echo htmlspecialchars($p['instructions']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lab Tests -->
    <?php if (!empty($labTests)): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-flask"></i> Laboratory Tests</h3>
            </div>
            <div class="admin-card-body">
                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>Test Name</th>
                                <th>Type</th>
                                <th>Ordered Date</th>
                                <th>Ordered By</th>
                                <th>Status</th>
                                <th>Results</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($labTests as $test): ?>
                                <tr>
                                    <td data-label="Test Name"><?php echo htmlspecialchars($test['testName']); ?></td>
                                    <td data-label="Type"><?php echo htmlspecialchars($test['testType'] ?: '-'); ?></td>
                                    <td data-label="Ordered Date"><?php echo date('M j, Y', strtotime($test['orderedDate'])); ?></td>
                                    <td data-label="Ordered By"><?php echo $test['orderedByName'] ? htmlspecialchars($test['orderedByName']) : 'N/A'; ?></td>
                                    <td data-label="Status">
                                        <span class="admin-status-badge admin-status-<?php echo $test['status']; ?>">
                                            <?php echo ucfirst(str_replace('-', ' ', $test['status'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Results">
                                        <?php echo $test['results'] ? htmlspecialchars(substr($test['results'], 0, 50)) . '...' : '<em class="admin-text-muted">Pending</em>'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bill Summary -->
    <?php if ($bill): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-file-invoice-dollar"></i> Bill Summary</h3>
            </div>
            <div class="admin-card-body">
                <table class="admin-bill-table" style="width: 100%; max-width: 500px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0;">Consultation Fee:</td>
                        <td style="padding: 8px 0; text-align: right;">$<?php echo number_format($bill['consultationFee'], 2); ?></td>
                    </tr>
                    <?php foreach ($additionalCharges as $c): ?>
                        <tr>
                            <td style="padding: 8px 0;"><?php echo htmlspecialchars($c['chargeName']); ?>:</td>
                            <td style="padding: 8px 0; text-align: right;">$<?php echo number_format($c['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td style="padding: 8px 0;"><strong>Subtotal:</strong></td>
                        <td style="padding: 8px 0; text-align: right;"><strong>$<?php echo number_format($bill['consultationFee'] + $bill['additionalCharges'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0;">Service Charge (3%):</td>
                        <td style="padding: 8px 0; text-align: right;">$<?php echo number_format($bill['serviceCharge'], 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0;">GST (13%):</td>
                        <td style="padding: 8px 0; text-align: right;">$<?php echo number_format($bill['gst'], 2); ?></td>
                    </tr>
                    <tr style="border-top: 2px solid #dc2626;">
                        <td style="padding: 15px 0;"><strong style="font-size: 18px;">Total Amount:</strong></td>
                        <td style="padding: 15px 0; text-align: right;"><strong style="font-size: 20px; color: #dc2626;">$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                    </tr>
                </table>
                
                <div style="margin-top: 20px;">
                    <p><strong>Bill Status:</strong> 
                        <span class="admin-status-badge admin-status-<?php echo $bill['status']; ?>">
                            <?php echo ucfirst($bill['status']); ?>
                        </span>
                    </p>
                    <?php if ($bill['paidAt']): ?>
                        <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="admin-btn admin-btn-primary">
                        <i class="fas fa-eye"></i> View Full Bill
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>