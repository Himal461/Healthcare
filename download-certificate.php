<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$certificateNumber = $_GET['file'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if (empty($certificateNumber)) {
    header("Location: index.php");
    exit();
}

// Security: Only allow the certificate owner or doctor/admin to download
if ($userRole === 'patient') {
    // Verify this certificate belongs to the patient
    $stmt = $pdo->prepare("
        SELECT mc.certificate_id 
        FROM medical_certificates mc
        JOIN patients p ON mc.patient_id = p.patientId
        WHERE mc.certificate_number = ? AND p.userId = ?
    ");
    $stmt->execute([$certificateNumber, $userId]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "You are not authorized to download this certificate.";
        header("Location: patient/dashboard.php");
        exit();
    }
} elseif (!in_array($userRole, ['doctor', 'admin', 'nurse', 'staff'])) {
    $_SESSION['error'] = "You are not authorized to download this certificate.";
    header("Location: dashboard.php");
    exit();
}

// Check if PDF exists
$pdfFile = __DIR__ . '/uploads/certificates/' . $certificateNumber . '.pdf';
$htmlFile = __DIR__ . '/uploads/certificates/' . $certificateNumber . '.html';

if (file_exists($pdfFile)) {
    // Serve PDF file
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Medical_Certificate_' . $certificateNumber . '.pdf"');
    header('Content-Length: ' . filesize($pdfFile));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($pdfFile);
    exit();
} elseif (file_exists($htmlFile)) {
    // Fallback to HTML if PDF doesn't exist
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="Medical_Certificate_' . $certificateNumber . '.html"');
    header('Content-Length: ' . filesize($htmlFile));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($htmlFile);
    exit();
} else {
    $_SESSION['error'] = "Certificate file not found.";
    header("Location: " . ($userRole === 'patient' ? 'patient/dashboard.php' : $userRole . '/dashboard.php'));
    exit();
}
?>