<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

// Manual Dompdf inclusion
require_once __DIR__ . '/../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$certificateId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';
$doctorUserId = $_SESSION['user_id'];

// ========== PROCESS ALL ACTIONS BEFORE ANY OUTPUT ==========

// Get doctor ID and name
$stmt = $pdo->prepare("
    SELECT d.doctorId, u.firstName, u.lastName, u.email as doctorEmail,
           CONCAT(u.firstName, ' ', u.lastName) as doctorFullName,
           u.username
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE s.userId = ?
");
$stmt->execute([$doctorUserId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: dashboard.php");
    exit();
}

$doctorId = $doctor['doctorId'];
$doctorFullName = $doctor['doctorFullName'];
$doctorUsername = $doctor['username'];

// Get certificate details
$stmt = $pdo->prepare("
    SELECT mc.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patient_name,
           u.email as patient_email,
           p.patientId,
           p.userId as patient_user_id
    FROM medical_certificates mc
    JOIN patients p ON mc.patient_id = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE mc.certificate_id = ?
");
$stmt->execute([$certificateId]);
$certificate = $stmt->fetch();

if (!$certificate) {
    $_SESSION['error'] = "Certificate not found.";
    header("Location: dashboard.php");
    exit();
}

if ($certificate['doctor_id'] != $doctorId) {
    $_SESSION['error'] = "This certificate is assigned to a different doctor.";
    header("Location: dashboard.php");
    exit();
}

// ========== FUNCTION DEFINITIONS ==========

function getDoctorSignaturePath($doctorFullName, $username) {
    if (stripos($doctorFullName, 'Abinash') !== false || 
        stripos($doctorFullName, 'Karki') !== false || 
        $username === 'alok') {
        $signaturePath = __DIR__ . '/../uploads/signatures/Abinash.png';
        if (file_exists($signaturePath)) return $signaturePath;
    }
    
    if (stripos($doctorFullName, 'Wilson') !== false || 
        stripos($doctorFullName, 'David') !== false || 
        $username === 'dr.wilson') {
        $signaturePath = __DIR__ . '/../uploads/signatures/Wilson.png';
        if (file_exists($signaturePath)) return $signaturePath;
    }
    
    return null;
}

function getLogoPath() {
    $logoPath = __DIR__ . '/../uploads/logo/logo.png';
    if (file_exists($logoPath)) return $logoPath;
    return null;
}

function generateCertificateHTML($certificateId, $doctorFullName, $username) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT mc.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patient_name,
               u.email as patient_email, u.phoneNumber as patient_phone,
               p.dateOfBirth, p.address,
               CONCAT(du.firstName, ' ', du.lastName) as doctor_name,
               d.specialization, s.licenseNumber,
               b.billId as bill_id, b.totalAmount as bill_amount
        FROM medical_certificates mc
        JOIN patients p ON mc.patient_id = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN doctors d ON mc.doctor_id = d.doctorId
        LEFT JOIN staff s ON d.staffId = s.staffId
        LEFT JOIN users du ON s.userId = du.userId
        LEFT JOIN bills b ON mc.bill_id = b.billId
        WHERE mc.certificate_id = ?
    ");
    $stmt->execute([$certificateId]);
    $cert = $stmt->fetch();
    
    if (!$cert) return false;
    
    $doctorDisplayName = $doctorFullName;
    if (empty($doctorDisplayName) || trim($doctorDisplayName) == '') {
        $doctorDisplayName = $cert['doctor_name'] ?? 'Medical Officer';
    }
    
    $issueDate = date('d/m/Y');
    $startDate = date('d/m/Y', strtotime($cert['start_date']));
    $endDate = date('d/m/Y', strtotime($cert['end_date']));
    
    // Get logo
    $logoHtml = '';
    $logoPath = __DIR__ . '/../uploads/logo/logo.png';
    if (file_exists($logoPath)) {
        $imageData = file_get_contents($logoPath);
        if ($imageData) {
            $base64 = base64_encode($imageData);
            $mime = mime_content_type($logoPath);
            $logoHtml = '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Logo" style="width: 50px; height: auto; margin-right: 12px; vertical-align: middle;">';
        }
    }
    if (empty($logoHtml)) {
        $logoHtml = '<span style="font-size: 28px; margin-right: 12px; color: #dc2626; line-height: 1;">♥</span>';
    }
    
    // Get signature
    $signatureHtml = '';
    $signaturePath = __DIR__ . '/../uploads/signatures/Abinash.png';
    if (file_exists($signaturePath)) {
        $imageData = file_get_contents($signaturePath);
        if ($imageData) {
            $base64 = base64_encode($imageData);
            $mime = mime_content_type($signaturePath);
            $signatureHtml = '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Signature" style="width: 180px; height: auto; margin-top: 10px;">';
        }
    }
    
    if (empty($signatureHtml)) {
        $signaturePath = __DIR__ . '/../uploads/signatures/Wilson.png';
        if (file_exists($signaturePath)) {
            $imageData = file_get_contents($signaturePath);
            if ($imageData) {
                $base64 = base64_encode($imageData);
                $mime = mime_content_type($signaturePath);
                $signatureHtml = '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Signature" style="width: 180px; height: auto; margin-top: 10px;">';
            }
        }
    }
    
    if (empty($signatureHtml)) {
        $doctorFirstName = explode(' ', $doctorDisplayName)[0];
        $signatureHtml = '<span style="font-family: Brush Script MT, cursive; font-size: 28px; color: #1a1a1a;">' . htmlspecialchars($doctorFirstName) . '</span>';
    }
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Medical Certificate - ' . $cert['certificate_number'] . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; line-height: 1.6; color: #1a1a1a; margin: 0; padding: 35px; background: white; }
        .certificate { max-width: 650px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #1e3a5f; }
        .hospital-name { font-size: 24px; font-weight: bold; color: #1e3a5f; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px; }
        .hospital-details { font-size: 11px; color: #475569; line-height: 1.4; }
        .certificate-title { text-align: center; font-size: 22px; font-weight: bold; color: #1e3a5f; text-transform: uppercase; letter-spacing: 3px; margin: 25px 0; text-decoration: underline; }
        .date { text-align: right; font-size: 12px; margin: 20px 0 30px; }
        .patient-name { font-weight: 700; font-size: 13px; margin-bottom: 15px; }
        .body-text { font-size: 13px; line-height: 1.6; }
        .closing { margin: 50px 0 15px; font-size: 13px; }
        .signature-section { margin-top: 15px; }
        .signature-image { margin: 5px 0 8px; }
        .signature-image img { width: 180px; height: auto; }
        .doctor-name { font-weight: 700; font-size: 13px; }
        .doctor-credentials { font-size: 11px; color: #475569; line-height: 1.4; }
        .footer { margin-top: 50px; padding-top: 15px; border-top: 1px solid #cbd5e1; font-size: 9px; color: #64748b; text-align: center; }
        .payment-info { margin-top: 20px; padding: 10px; background: #f0fdf4; border: 1px solid #10b981; border-radius: 5px; text-align: center; font-size: 11px; color: #166534; }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div>' . $logoHtml . '<span class="hospital-name">HealthManagement</span></div>
            <div class="hospital-details">
                Fussel Lane, Gungahlin, ACT 2912, Australia<br>
                Phone: +61 438 347 3483 | Emergency: +61 455 2627<br>
                Email: info@healthmanagement.com.au | ABN: 43 571 905 327
            </div>
        </div>
        <div class="date">' . $issueDate . '</div>
        <div class="patient-name">' . htmlspecialchars($cert['patient_name']) . ' has been clinically assessed.</div>
        <div class="body-text">
            They currently have a medical condition and will be unable to attend work/study 
            from <strong>' . $startDate . '</strong> to <strong>' . $endDate . '</strong> inclusive.
        </div>
        <div class="closing">Sincerely,</div>
        <div class="signature-section">
            <div class="signature-image">' . $signatureHtml . '</div>
            <div class="doctor-name">Dr ' . htmlspecialchars($doctorDisplayName) . '</div>
            <div class="doctor-credentials">
                ' . htmlspecialchars($cert['specialization'] ?? 'General Medicine') . '
                ' . ($cert['licenseNumber'] ? '| AHPRA Registration No. ' . htmlspecialchars($cert['licenseNumber']) : '') . '
            </div>
        </div>
        <div class="payment-info">
            <strong>PAYMENT CONFIRMED</strong> | Amount: $' . number_format($cert['bill_amount'] ?? 13.00, 2) . ' | 
            Certificate #: ' . $cert['certificate_number'] . ' | Issued: ' . $issueDate . '
        </div>
        <div class="footer">
            This is a computer-generated medical certificate and is valid without a physical signature.<br>
            Certificate #: ' . $cert['certificate_number'] . ' | Issued: ' . $issueDate . '
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

function generatePDF($html, $certificateNumber) {
    $dompdfAutoload = __DIR__ . '/../dompdf/autoload.inc.php';
    
    if (file_exists($dompdfAutoload)) {
        require_once $dompdfAutoload;
        
        $options = new Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Times-Roman');
        $options->set('isPhpEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $pdfDir = __DIR__ . '/../uploads/certificates/';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $pdfPath = $pdfDir . $certificateNumber . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());
        
        return $pdfPath;
    }
    
    // Fallback: Save as HTML
    $pdfDir = __DIR__ . '/../uploads/certificates/';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }
    
    $htmlPath = $pdfDir . $certificateNumber . '.html';
    file_put_contents($htmlPath, $html);
    
    return $htmlPath;
}

// ========== HANDLE APPROVAL (before any output) ==========
if ($action === 'approve' && ($certificate['approval_status'] === 'pending' || $certificate['approval_status'] === 'pending_consultation' || $certificate['approval_status'] === null)) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE medical_certificates 
            SET approval_status = 'approved', 
                approved_at = NOW(), 
                approved_by = ?
            WHERE certificate_id = ?
        ");
        $stmt->execute([$doctorUserId, $certificateId]);
        
        $certificateHTML = generateCertificateHTML($certificateId, $doctorFullName, $doctorUsername);
        $pdfPath = generatePDF($certificateHTML, $certificate['certificate_number']);
        
        $subject = "Your Medical Certificate is Ready - " . SITE_NAME;
        $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #10b981; color: white; padding: 25px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 25px; border-radius: 0 0 10px 10px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'><h2>Medical Certificate Approved</h2></div>
                    <div class='content'>
                        <p>Dear {$certificate['patient_name']},</p>
                        <p>Your medical certificate has been approved by Dr. {$doctorFullName}.</p>
                        <p><strong>Period:</strong> " . date('d/m/Y', strtotime($certificate['start_date'])) . " - " . date('d/m/Y', strtotime($certificate['end_date'])) . "</p>
                        <p>The signed certificate (PDF) is attached to this email.</p>
                        <p>Thank you for choosing " . SITE_NAME . ".</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        if (function_exists('sendEmailWithAttachment')) {
            sendEmailWithAttachment($certificate['patient_email'], $subject, $message, $pdfPath, "Medical_Certificate_{$certificate['certificate_number']}.pdf");
        } else {
            sendEmail($certificate['patient_email'], $subject, $message);
        }
        
        if ($certificate['patient_user_id']) {
            createNotification($certificate['patient_user_id'], 'medical_certificate', 'Certificate Approved', 
                "Your medical certificate has been approved by Dr. {$doctorFullName}.", 
                "patient/view-medical-certificates.php?view=" . $certificateId);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Certificate approved and PDF generated successfully!";
        logAction($doctorUserId, 'APPROVE_CERTIFICATE', "Approved certificate #{$certificate['certificate_number']}");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to approve: " . $e->getMessage();
        error_log("Certificate approval error: " . $e->getMessage());
    }
    
    header("Location: dashboard.php");
    exit();
}

// ========== HANDLE REJECTION (before any output) ==========
if ($action === 'reject' && ($certificate['approval_status'] === 'pending' || $certificate['approval_status'] === 'pending_consultation' || $certificate['approval_status'] === null)) {
    $stmt = $pdo->prepare("
        UPDATE medical_certificates 
        SET approval_status = 'rejected', 
            approved_at = NOW(), 
            approved_by = ?
        WHERE certificate_id = ?
    ");
    $stmt->execute([$doctorUserId, $certificateId]);
    
    $subject = "Medical Certificate Request Update - " . SITE_NAME;
    $message = "
        <p>Dear {$certificate['patient_name']},</p>
        <p>Your medical certificate request could not be approved.</p>
        <p>Please contact our office for more information.</p>
    ";
    sendEmail($certificate['patient_email'], $subject, $message);
    
    if ($certificate['patient_user_id']) {
        createNotification($certificate['patient_user_id'], 'medical_certificate', 'Certificate Update', 
            "Your certificate request requires further attention.");
    }
    
    $_SESSION['success'] = "Certificate rejected.";
    logAction($doctorUserId, 'REJECT_CERTIFICATE', "Rejected certificate #{$certificate['certificate_number']}");
    
    header("Location: dashboard.php");
    exit();
}

// ========== NOW INCLUDE HEADER (after all redirect logic) ==========
$pageTitle = "Approve Medical Certificate - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
include '../includes/header.php';

// ========== DISPLAY PAGE ==========
$certificateTypes = ['work' => 'Work Leave', 'school' => 'School Leave', 'travel' => 'Travel', 'insurance' => 'Insurance', 'other' => 'Other'];

// Check if already processed
if ($certificate['approval_status'] && $certificate['approval_status'] !== 'pending' && $certificate['approval_status'] !== 'pending_consultation') {
    $statusText = $certificate['approval_status'] === 'approved' ? 'Approved' : 'Rejected';
    ?>
    <div class="doctor-container">
        <div class="doctor-page-header">
            <div class="header-title">
                <h1><i class="fas fa-file-medical"></i> Medical Certificate</h1>
                <p>Certificate #<?php echo $certificate['certificate_number']; ?></p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="doctor-btn doctor-btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="doctor-card">
            <div class="doctor-card-body">
                <div class="doctor-alert doctor-alert-info">
                    <i class="fas fa-info-circle"></i> This certificate has already been <strong><?php echo $statusText; ?></strong>.
                </div>
                <a href="dashboard.php" class="doctor-btn doctor-btn-primary">Return to Dashboard</a>
            </div>
        </div>
    </div>
    <?php
    include '../includes/footer.php';
    exit();
}
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-medical"></i> Review Medical Certificate</h1>
            <p>Certificate #<?php echo $certificate['certificate_number']; ?></p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="doctor-btn doctor-btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-info-circle"></i> Certificate Information</h3>
            <span class="doctor-status-badge" style="background: #fef3c7; color: #92400e;">Pending Approval</span>
        </div>
        <div class="doctor-card-body">
            <div class="doctor-patient-info-grid">
                <div class="doctor-info-group">
                    <h4>Patient Information</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($certificate['patient_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($certificate['patient_email']); ?></p>
                </div>
                <div class="doctor-info-group">
                    <h4>Certificate Details</h4>
                    <p><strong>Type:</strong> <?php echo $certificateTypes[$certificate['certificate_type']] ?? $certificate['certificate_type']; ?></p>
                    <p><strong>Period:</strong> <?php echo date('d/m/Y', strtotime($certificate['start_date'])) . ' - ' . date('d/m/Y', strtotime($certificate['end_date'])); ?></p>
                    <p><strong>Amount Paid:</strong> $<?php echo number_format($certificate['amount_paid'], 2); ?></p>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <a href="?id=<?php echo $certificateId; ?>&action=approve" class="doctor-btn doctor-btn-success" onclick="return confirm('Approve and sign this certificate?')">
                    <i class="fas fa-check"></i> Approve & Sign
                </a>
                <a href="?id=<?php echo $certificateId; ?>&action=reject" class="doctor-btn doctor-btn-danger" onclick="return confirm('Reject this certificate?')">
                    <i class="fas fa-times"></i> Reject
                </a>
                <a href="dashboard.php" class="doctor-btn doctor-btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>