<?php
require_once __DIR__ . '/../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function getDoctorSignaturePath($doctorFullName, $username) {
    if (
        stripos($doctorFullName, 'Abinash') !== false ||
        stripos($doctorFullName, 'Karki') !== false ||
        $username === 'alok'
    ) {
        $signaturePath = __DIR__ . '/../uploads/signatures/Abinash.png';
        if (file_exists($signaturePath)) return $signaturePath;
    }

    if (
        stripos($doctorFullName, 'Wilson') !== false ||
        stripos($doctorFullName, 'David') !== false ||
        $username === 'dr.wilson'
    ) {
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

function generatePDF($html, $certificateNumber) {
    $options = new Options();

    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);

    // Prevent Dompdf FontLib error
    $options->set('defaultFont', 'Helvetica');
    $options->set('isFontSubsettingEnabled', false);

    $dompdf = new Dompdf($options);

    $html = str_replace('DejaVu Sans, Arial, sans-serif', 'Helvetica, Arial, sans-serif', $html);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $uploadDir = __DIR__ . '/../uploads/certificates/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $certificateNumber);
    $fileName = $safeName . '.pdf';
    $filePath = $uploadDir . $fileName;

    file_put_contents($filePath, $dompdf->output());

    return 'uploads/certificates/' . $fileName;
}

function formatCertificateDate($date) {
    if (empty($date)) {
        return 'N/A';
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'N/A';
    }

    $day = (int)date('j', $timestamp);

    if ($day >= 11 && $day <= 13) {
        $suffix = 'th';
    } else {
        switch ($day % 10) {
            case 1:
                $suffix = 'st';
                break;
            case 2:
                $suffix = 'nd';
                break;
            case 3:
                $suffix = 'rd';
                break;
            default:
                $suffix = 'th';
                break;
        }
    }

    return $day . $suffix . ' of ' . date('F Y', $timestamp);
}

function calculateCertificateDays($startDate, $endDate) {
    if (empty($startDate) || empty($endDate)) {
        return '1 day';
    }

    try {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        // Inclusive date calculation
        $days = $start->diff($end)->days + 1;

        if ($days <= 1) {
            return '1 day';
        }

        return $days . ' days';
    } catch (Exception $e) {
        return '1 day';
    }
}

function formatCertificatePurpose($purpose) {
    $purpose = trim((string)$purpose);

    if ($purpose === '') {
        return 'Employment';
    }

    $lowerPurpose = strtolower($purpose);

    if (
        $lowerPurpose === 'work' ||
        $lowerPurpose === 'employment' ||
        strpos($lowerPurpose, 'work') !== false ||
        strpos($lowerPurpose, 'employ') !== false
    ) {
        return 'Employment';
    }

    // For study, school, university, travel, insurance, other etc.
    $parts = preg_split('/[\s\/\-_]+/', $purpose);
    $firstWord = $parts[0] ?? $purpose;

    return ucfirst(strtolower($firstWord));
}

function generateCertificateHTML($certificateId, $doctorFullName, $username) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT mc.*, 
               CONCAT(u.firstName, ' ', u.lastName) AS patient_name,
               u.email AS patient_email,
               u.phoneNumber AS patient_phone,
               p.dateOfBirth,
               p.address,
               CONCAT(du.firstName, ' ', du.lastName) AS doctor_name,
               d.specialization,
               s.licenseNumber,
               b.billId AS bill_id,
               b.totalAmount AS bill_amount
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
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cert) {
        return false;
    }

    $doctorDisplayName = trim($doctorFullName);
    if ($doctorDisplayName === '') {
        $doctorDisplayName = $cert['doctor_name'] ?? 'Medical Officer';
    }

    $certificateNumber = htmlspecialchars($cert['certificate_number'] ?? ('MC-' . $certificateId));
    $patientName = htmlspecialchars($cert['patient_name'] ?? 'Patient');

    $issueDate = !empty($cert['created_at'])
        ? date('d/m/Y', strtotime($cert['created_at']))
        : date('d/m/Y');

    $startDateRaw = $cert['start_date'] ?? null;
    $endDateRaw = $cert['end_date'] ?? null;

    $startDate = formatCertificateDate($startDateRaw);
    $endDate = formatCertificateDate($endDateRaw);
    $daysText = calculateCertificateDays($startDateRaw, $endDateRaw);

    $purposeRaw = $cert['certificate_type'] ?? 'Employment';
    $purposeText = formatCertificatePurpose($purposeRaw);

    $specialization = htmlspecialchars($cert['specialization'] ?? 'General');
    $licenseNumber = !empty($cert['licenseNumber']) ? htmlspecialchars($cert['licenseNumber']) : '';

    // Logo image
    $logoHtml = '';
    $logoPath = getLogoPath();

    if ($logoPath && file_exists($logoPath)) {
        $imageData = file_get_contents($logoPath);
        if ($imageData) {
            $base64 = base64_encode($imageData);
            $mime = mime_content_type($logoPath);
            $logoHtml = '<img src="data:' . $mime . ';base64,' . $base64 . '" class="logo-image">';
        }
    }

    // Signature image
    $signatureHtml = '';
    $signaturePath = getDoctorSignaturePath($doctorDisplayName, $username);

    if ($signaturePath && file_exists($signaturePath)) {
        $signatureData = file_get_contents($signaturePath);
        if ($signatureData) {
            $signatureBase64 = base64_encode($signatureData);
            $signatureMime = mime_content_type($signaturePath);
            $signatureHtml = '<img src="data:' . $signatureMime . ';base64,' . $signatureBase64 . '">';
        }
    }

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Medical Certificate - ' . $certificateNumber . '</title>

    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body { 
            font-family: Helvetica, Arial, sans-serif;
            line-height: 1.5; 
            color: #000000; 
            margin: 0; 
            padding: 35px;
            background: white;
        }

        .certificate {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 28px;
        }

        .logo-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .logo-cell {
            display: table-cell;
            width: 68px;
            vertical-align: middle;
        }

        .name-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .logo-image {
            width: 53px;
            height: auto;
            margin-right: 12px;
            vertical-align: middle;
        }

        .hospital-name {
            font-size: 25px;
            font-weight: 700;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1;
        }

        .hospital-details {
            font-size: 12px;
            color: #333333;
            line-height: 1.35;
            margin-top: 6px;
        }

        .date {
    text-align: right;
    font-size: 14px;
    font-weight: 500;
    margin: 28px 0 80px;
}

.certificate-title {
    text-align: center;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 65px;
}

        .body-text {
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .closing {
            margin: 52px 0 15px;
            font-size: 15px;
        }

        .signature-section {
            margin-top: 15px;
        }

        .signature-image {
            margin: 5px 0 8px;
            min-height: 35px;
        }

        .signature-image img {
            width: 180px;
            height: auto;
        }

        .doctor-name {
            font-weight: 700;
            font-size: 15px;
        }

        .doctor-credentials {
            font-size: 13px;
            color: #333333;
            line-height: 1.35;
            margin-top: 2px;
        }

        .footer {
            margin-top: 45px;
            padding-top: 12px;
            border-top: 1px solid #cccccc;
            font-size: 11px;
            color: #555555;
            text-align: center;
        }

        @media print {
            body { padding: 20px; }
        }
    </style>
</head>

<body>
    <div class="certificate">
        <div class="header">
            <div class="logo-row">
                <div class="logo-cell">
                    ' . $logoHtml . '
                </div>

                <div class="name-cell">
                    <span class="hospital-name">Care Aus</span>
                </div>
            </div>

            <div class="hospital-details">
                Level 35, 100 Barangaroo Avenue, BARANGAROO, NSW, 2000<br>
                Email: admin@careaus.com.au | ABN: 43-668-260-964
            </div>
        </div>

        <div class="date">
            ' . $issueDate . '
        </div>

        <div class="certificate-title">
            Medical Certificate
        </div>

        <div class="body-text">
            This document is to certify that <strong>' . $patientName . '</strong>, is unfit for their study/work 
            from the <strong>' . $startDate . '</strong> until the <strong>' . $endDate . '</strong> inclusive 
            (' . $daysText . ').
        </div>

        <div class="body-text">
            This document is produced for the purpose of ' . htmlspecialchars($purposeText) . '.
        </div>
        
        <div class="closing">
            Sincerely,
        </div>
        
        <div class="signature-section">
            <div class="signature-image">
                ' . $signatureHtml . '
            </div>
            
            <div class="doctor-name">
                Dr ' . htmlspecialchars($doctorDisplayName) . '
            </div>

            <div class="doctor-credentials">
                ' . $specialization . '
                ' . ($licenseNumber ? '| AHPRA Registration No. ' . $licenseNumber : '') . '
            </div>
        </div>
        
        <div class="footer">
            This is a computer-generated medical certificate and is valid without a physical signature.<br>
            Certificate #: ' . $certificateNumber . ' | Issued: ' . $issueDate . '
        </div>
    </div>
</body>
</html>';

    return $html;
}