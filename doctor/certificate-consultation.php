<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$userId = $_SESSION['user_id'];

// Get certificate_id from GET or POST - cast to integer
$certificateId = (int)($_GET['certificate_id'] ?? $_POST['certificate_id'] ?? 0);

if (!$certificateId) {
    $_SESSION['error'] = "Invalid certificate ID.";
    header("Location: dashboard.php");
    exit();
}

// Get doctor ID - CAST TO INTEGER (database returns string)
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.consultationFee,
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           u.email as doctorEmail, u.username
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

// FIX: Cast to integer - doctorId from database is a string
$doctorId = (int)$doctor['doctorId'];

// Do not include approve-certificate.php directly because it runs page logic
// and expects ?id= instead of ?certificate_id=.
// Certificate PDF helper functions should be placed in a separate helper file.
require_once __DIR__ . '/../includes/certificate-functions.php';

// Get certificate details with patient and appointment info
$stmt = $pdo->prepare("
    SELECT mc.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           u.userId as patientUserId,
           p.patientId,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies,
           p.address,
           a.dateTime as appointmentDateTime,
           a.appointmentId,
           a.doctorId as appointmentDoctorId,
           a.status as appointmentStatus,
           b.billId,
           b.totalAmount as billAmount,
           b.status as billStatus
    FROM medical_certificates mc
    JOIN patients p ON mc.patient_id = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN appointments a ON mc.appointment_id = a.appointmentId
    LEFT JOIN bills b ON mc.bill_id = b.billId
    WHERE mc.certificate_id = ?
");
$stmt->execute([$certificateId]);
$certificate = $stmt->fetch();

if (!$certificate) {
    $_SESSION['error'] = "Certificate #$certificateId not found in the system.";
    header("Location: dashboard.php");
    exit();
}

// FIX: Cast to integer for comparison - database stores these as strings
$certDoctorId = (int)$certificate['doctor_id'];
$certApptDoctorId = (int)($certificate['appointmentDoctorId'] ?? 0);

// Allow access if doctor is assigned OR if the appointment belongs to this doctor
if ($certDoctorId !== $doctorId && $certApptDoctorId !== $doctorId) {
    $_SESSION['error'] = "This certificate is not assigned to you. Cert Doctor ID: $certDoctorId, Appt Doctor ID: $certApptDoctorId, Your Doctor ID: $doctorId";
    header("Location: dashboard.php");
    exit();
}

// Auto-assign doctor if appointment belongs to this doctor but certificate doesn't
if ($certDoctorId !== $doctorId && $certApptDoctorId === $doctorId) {
    $stmt = $pdo->prepare("UPDATE medical_certificates SET doctor_id = ? WHERE certificate_id = ?");
    $stmt->execute([$doctorId, $certificateId]);
}

// Check if already processed
$currentStatus = $certificate['approval_status'] ?? null;
$allowedStatuses = ['pending', 'pending_consultation', null, ''];

if (!in_array($currentStatus, $allowedStatuses, true)) {
    $statusText = $currentStatus === 'approved' ? 'approved' : ($currentStatus === 'rejected' ? 'rejected' : $currentStatus);
    $_SESSION['info'] = "This certificate has already been $statusText.";
    header("Location: dashboard.php");
    exit();
}

// Get patient's medical history for reference
$stmt = $pdo->prepare("
    SELECT mr.*, CONCAT(du.firstName, ' ', du.lastName) as recordDoctorName
    FROM medical_records mr
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE mr.patientId = ?
    ORDER BY mr.creationDate DESC
    LIMIT 5
");
$stmt->execute([$certificate['patientId']]);
$medicalHistory = $stmt->fetchAll();

$errorMessage = null;
$successMessage = null;
$consultationCompleted = false;

// Handle certificate consultation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_consultation'])) {
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $consultationNotes = trim($_POST['consultation_notes'] ?? '');
    $approveAction = $_POST['approve_action'] ?? '';
    
    if (empty($diagnosis)) {
        $errorMessage = "Please enter a diagnosis from the consultation.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Create medical record for this certificate consultation
            $stmt = $pdo->prepare("
                INSERT INTO medical_records (patientId, doctorId, appointmentId, diagnosis, treatmentNotes, creationDate)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $certificate['patientId'],
                $doctorId,
                $certificate['appointmentId'],
                $diagnosis . ' [Medical Certificate Consultation]',
                $consultationNotes
            ]);
            $recordId = $pdo->lastInsertId();
            
            // Update appointment status to completed
            if ($certificate['appointmentId']) {
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', updatedAt = NOW() WHERE appointmentId = ?");
                $stmt->execute([$certificate['appointmentId']]);
            }
            
            if ($approveAction === 'approve') {
                // Update certificate status to approved
                $stmt = $pdo->prepare("
                    UPDATE medical_certificates 
                    SET approval_status = 'approved', 
                        approved_at = NOW(), 
                        approved_by = ?
                    WHERE certificate_id = ?
                ");
                $stmt->execute([$userId, $certificateId]);
                
                // Generate certificate PDF
                $certificateHTML = generateCertificateHTML($certificateId, $doctor['doctorName'], $doctor['username']);
                
                if ($certificateHTML) {
                    $pdfPath = generatePDF($certificateHTML, $certificate['certificate_number']);
                    error_log("Certificate PDF generated: " . ($pdfPath ?: 'Failed'));
                }
                
                $consultationCompleted = true;
                $successMessage = "Certificate consultation completed successfully! Certificate has been approved and PDF generated.";
                
                // Send approval email to patient
                $patientSubject = "Your Medical Certificate is Ready - " . SITE_NAME;
                $patientMessage = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #10b981; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                            .certificate-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                            .button { display: inline-block; background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Medical Certificate Approved</h2>
                            </div>
                            <div class='content'>
                                <p>Dear <strong>{$certificate['patientName']}</strong>,</p>
                                <p>Your medical certificate has been approved by Dr. {$doctor['doctorName']} following your consultation.</p>
                                <div class='certificate-info'>
                                    <p><strong>Certificate #:</strong> {$certificate['certificate_number']}</p>
                                    <p><strong>Period:</strong> " . date('M j, Y', strtotime($certificate['start_date'])) . " - " . date('M j, Y', strtotime($certificate['end_date'])) . "</p>
                                    <p><strong>Approved On:</strong> " . date('F j, Y g:i A') . "</p>
                                </div>
                                <p>You can download your certificate from your patient portal.</p>
                                <a href='" . SITE_URL . "/patient/view-medical-certificates.php?view={$certificateId}' class='button'>View My Certificates</a>
                                <p style='margin-top: 30px;'>Thank you for choosing " . SITE_NAME . ".</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                sendEmail($certificate['patientEmail'], $patientSubject, $patientMessage);
                
                // Notify patient
                if ($certificate['patientUserId']) {
                    createNotification(
                        $certificate['patientUserId'],
                        'medical_certificate',
                        'Medical Certificate Approved',
                        "Your medical certificate #{$certificate['certificate_number']} has been approved by Dr. {$doctor['doctorName']}.",
                        "patient/view-medical-certificates.php?view=" . $certificateId
                    );
                }
                
            } else {
                // Reject the certificate
                $stmt = $pdo->prepare("
                    UPDATE medical_certificates 
                    SET approval_status = 'rejected', 
                        approved_at = NOW(), 
                        approved_by = ?
                    WHERE certificate_id = ?
                ");
                $stmt->execute([$userId, $certificateId]);
                
                $consultationCompleted = true;
                $successMessage = "Certificate consultation completed. Certificate has been rejected.";
                
                // Send rejection email to patient
                $patientSubject = "Medical Certificate Update - " . SITE_NAME;
                $patientMessage = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #ef4444; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Medical Certificate Update</h2>
                            </div>
                            <div class='content'>
                                <p>Dear <strong>{$certificate['patientName']}</strong>,</p>
                                <p>Your medical certificate request #{$certificate['certificate_number']} could not be approved following the consultation.</p>
                                <p>Please contact our office for further assistance.</p>
                                <p>Phone: +61 438 347 3483</p>
                                <p>Thank you for understanding.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                sendEmail($certificate['patientEmail'], $patientSubject, $patientMessage);
                
                // Notify patient
                if ($certificate['patientUserId']) {
                    createNotification(
                        $certificate['patientUserId'],
                        'medical_certificate',
                        'Certificate Not Approved',
                        "Your medical certificate request #{$certificate['certificate_number']} was not approved. Please contact the office.",
                        "patient/view-medical-certificates.php?view=" . $certificateId
                    );
                }
            }
            
            $pdo->commit();
            logAction($userId, 'CERTIFICATE_CONSULTATION', "Completed certificate consultation for certificate #{$certificate['certificate_number']}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error: " . $e->getMessage();
            error_log("Certificate consultation error: " . $e->getMessage());
        }
    }
}

$certificateTypes = [
    'work' => 'Work Leave',
    'school' => 'School/University Leave',
    'travel' => 'Travel Cancellation',
    'insurance' => 'Insurance Claim',
    'other' => 'Other'
];

$statusDisplay = ($certificate['approval_status'] == 'pending_consultation' || $certificate['approval_status'] === null) 
    ? 'Pending Consultation' 
    : ucfirst($certificate['approval_status'] ?? 'Pending');

$pageTitle = "Certificate Consultation - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
include '../includes/header.php';
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-medical"></i> Certificate Consultation</h1>
            <p>Certificate #<?php echo htmlspecialchars($certificate['certificate_number']); ?> - <?php echo $statusDisplay; ?></p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="doctor-btn doctor-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="appointments.php" class="doctor-btn doctor-btn-outline">
                <i class="fas fa-calendar-alt"></i> View Schedule
            </a>
        </div>
    </div>

    <?php if ($errorMessage): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($successMessage): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <a href="dashboard.php" class="doctor-btn doctor-btn-primary doctor-btn-sm">
                    <i class="fas fa-arrow-left"></i> Return to Dashboard
                </a>
                <a href="appointments.php" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                    <i class="fas fa-calendar-alt"></i> View Schedule
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$consultationCompleted): ?>
        <!-- Certificate Details Card -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-info-circle"></i> Certificate Details</h3>
                <span class="doctor-status-badge" style="background: #fef3c7; color: #92400e;">
                    <?php echo $statusDisplay; ?>
                </span>
            </div>
            <div class="doctor-card-body">
                <div class="doctor-patient-info-grid">
                    <div class="doctor-info-group">
                        <h4><i class="fas fa-user"></i> Patient Information</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($certificate['patientName']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($certificate['patientEmail']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($certificate['patientPhone']); ?></p>
                        <?php if ($certificate['dateOfBirth']): ?>
                            <p><strong>DOB:</strong> <?php echo date('M j, Y', strtotime($certificate['dateOfBirth'])); ?></p>
                        <?php endif; ?>
                        <?php if ($certificate['bloodType']): ?>
                            <p><strong>Blood Type:</strong> <?php echo $certificate['bloodType']; ?></p>
                        <?php endif; ?>
                        <?php if ($certificate['knownAllergies']): ?>
                            <p><strong>Allergies:</strong> <?php echo htmlspecialchars($certificate['knownAllergies']); ?></p>
                        <?php endif; ?>
                        <?php if ($certificate['address']): ?>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($certificate['address']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="doctor-info-group">
                        <h4><i class="fas fa-file-medical"></i> Certificate Information</h4>
                        <p><strong>Certificate #:</strong> <?php echo htmlspecialchars($certificate['certificate_number']); ?></p>
                        <p><strong>Type:</strong> <?php echo $certificateTypes[$certificate['certificate_type']] ?? $certificate['certificate_type']; ?></p>
                        <p><strong>Condition:</strong> <?php echo htmlspecialchars($certificate['medical_condition']); ?></p>
                        <p><strong>Period:</strong> <?php echo date('M j, Y', strtotime($certificate['start_date'])) . ' - ' . date('M j, Y', strtotime($certificate['end_date'])); ?></p>
                        <p><strong>Amount Paid:</strong> $<?php echo number_format($certificate['amount_paid'], 2); ?></p>
                        <p><strong>Bill #:</strong> <?php echo str_pad($certificate['bill_id'] ?? 0, 6, '0', STR_PAD_LEFT); ?></p>
                    </div>
                </div>
                
                <?php if ($certificate['additional_notes']): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 12px;">
                    <h4 style="margin-bottom: 10px;">Additional Notes:</h4>
                    <p style="color: #475569;"><?php echo nl2br(htmlspecialchars($certificate['additional_notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($certificate['appointmentDateTime']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #eff6ff; border-radius: 12px; border: 1px solid #bfdbfe;">
                        <p><strong><i class="fas fa-calendar-check"></i> Appointment:</strong> 
                            <?php echo date('F j, Y g:i A', strtotime($certificate['appointmentDateTime'])); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="doctor-status-badge doctor-status-<?php echo $certificate['appointmentStatus']; ?>">
                                <?php echo ucfirst($certificate['appointmentStatus']); ?>
                            </span>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Medical History Card -->
        <?php if (!empty($medicalHistory)): ?>
            <div class="doctor-card">
                <div class="doctor-card-header">
                    <h3><i class="fas fa-history"></i> Patient Medical History</h3>
                </div>
                <div class="doctor-card-body">
                    <?php foreach ($medicalHistory as $record): ?>
                        <div style="padding: 12px 15px; margin-bottom: 10px; background: #f8fafc; border-radius: 8px; border-left: 3px solid #2563eb;">
                            <p><strong><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></strong> - Dr. <?php echo htmlspecialchars($record['recordDoctorName']); ?></p>
                            <p style="color: #475569; margin-top: 5px;"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 200)); ?></p>
                            <?php if ($record['treatmentNotes']): ?>
                                <p style="color: #64748b; font-size: 13px; margin-top: 5px;">
                                    <em><?php echo htmlspecialchars(substr($record['treatmentNotes'], 0, 100)); ?></em>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Certificate Consultation Form -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-stethoscope"></i> Certificate Consultation</h3>
            </div>
            <div class="doctor-card-body">
                <form method="POST" action="?certificate_id=<?php echo $certificateId; ?>" id="certificate-consultation-form">
                    <input type="hidden" name="complete_consultation" value="1">
                    <input type="hidden" name="certificate_id" value="<?php echo $certificateId; ?>">
                    
                    <div class="doctor-form-group">
                        <label>Diagnosis / Consultation Findings <span class="required">*</span></label>
                        <textarea name="diagnosis" rows="4" class="doctor-form-control" 
                                  placeholder="Enter your diagnosis from this certificate consultation..." required></textarea>
                        <small style="color: #64748b;">This diagnosis will be recorded in the patient's medical record.</small>
                    </div>
                    
                    <div class="doctor-form-group">
                        <label>Consultation Notes</label>
                        <textarea name="consultation_notes" rows="3" class="doctor-form-control" 
                                  placeholder="Additional notes from the consultation (treatment plan, recommendations, etc.)..."></textarea>
                    </div>
                    
                    <div class="doctor-form-group">
                        <label>Certificate Decision <span class="required">*</span></label>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <label class="cert-decision-option decision-approve">
                                <input type="radio" name="approve_action" value="approve" checked>
                                <div class="decision-content">
                                    <i class="fas fa-check-circle"></i>
                                    <div>
                                        <strong>Approve Certificate</strong>
                                        <small>Patient meets criteria for medical certificate</small>
                                    </div>
                                </div>
                            </label>
                            <label class="cert-decision-option decision-reject">
                                <input type="radio" name="approve_action" value="reject">
                                <div class="decision-content">
                                    <i class="fas fa-times-circle"></i>
                                    <div>
                                        <strong>Cannot Approve</strong>
                                        <small>Patient does not meet criteria for certificate</small>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="doctor-form-actions" style="display: flex; gap: 15px;">
                        <button type="submit" class="doctor-btn doctor-btn-primary">
                            <i class="fas fa-check"></i> Complete Consultation & Process Certificate
                        </button>
                        <a href="dashboard.php" class="doctor-btn doctor-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.cert-decision-option {
    flex: 1;
    min-width: 250px;
    cursor: pointer;
}

.cert-decision-option input[type="radio"] {
    display: none;
}

.decision-content {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    transition: all 0.2s ease;
}

.decision-content i {
    font-size: 28px;
}

.decision-approve .decision-content {
    background: #f0fdf4;
    border-color: #86efac;
}

.decision-approve .decision-content i {
    color: #16a34a;
}

.decision-reject .decision-content {
    background: #fef2f2;
    border-color: #fca5a5;
}

.decision-reject .decision-content i {
    color: #dc2626;
}

.cert-decision-option input[type="radio"]:checked + .decision-content {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
    border-color: #2563eb;
}

.decision-content strong {
    display: block;
    font-size: 16px;
    color: #1e293b;
}

.decision-content small {
    display: block;
    color: #64748b;
    margin-top: 4px;
}

.doctor-form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

@media (max-width: 768px) {
    .cert-decision-option {
        min-width: 100%;
    }
}
</style>

<script>
document.getElementById('certificate-consultation-form')?.addEventListener('submit', function(e) {
    const diagnosis = document.querySelector('[name="diagnosis"]').value.trim();
    if (!diagnosis) {
        e.preventDefault();
        alert('Please enter a diagnosis from the consultation.');
        return false;
    }
    
    const action = document.querySelector('[name="approve_action"]:checked').value;
    const confirmMsg = action === 'approve' 
        ? 'Approve this medical certificate? The patient will be notified via email and the certificate PDF will be generated.' 
        : 'Reject this medical certificate? The patient will be notified via email.';
    
    if (!confirm(confirmMsg)) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>