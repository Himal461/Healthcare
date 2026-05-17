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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate - ' . $certificateNumber . '</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        html, body {
            width: 100%;
            background: #ffffff;
        }

        body {
            font-family: "Times New Roman", Times, Georgia, serif;
            color: #1a1a1a;
            margin: 0;
            padding: 28px 44px 24px 44px;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .certificate {
            max-width: 735px;
            margin: 0 auto;
            background: #ffffff;
        }
        
        .header {
            margin-bottom: 24px;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .header-top {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 0;
        }
        
        .header-logo {
            flex-shrink: 0;
            width: 95px;
            padding-top: 4px;
        }
        
        .header-logo img {
            width: 85px;
            height: auto;
            display: block;
        }
        
        .header-info {
            flex: 1;
        }
        
        .hospital-name {
            font-size: 34px;
            font-weight: 700;
            color: #000;
            letter-spacing: 1.4px;
            line-height: 1.05;
            text-transform: uppercase;
            margin-bottom: 9px;
        }
        
        .hospital-address {
            font-size: 16px;
            color: #2d2d2d;
            line-height: 1.45;
            margin-top: 0;
        }
        
        .hospital-address a {
            color: #2d2d2d;
            text-decoration: none;
        }
        
        .reference-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 28px 0 38px 0;
            font-size: 16px;
            line-height: 1.4;
        }
        
        .date-line {
            font-weight: 400;
            color: #1a1a1a;
        }
        
        .ref-line {
            font-weight: 400;
            color: #1a1a1a;
        }
        
        .certificate-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            color: #000;
            margin: 0 0 42px 0;
            letter-spacing: 1px;
        }
        
        .patient-section {
            margin-bottom: 34px;
        }

        .re-line {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a1a1a;
        }
        
        .patient-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 9px;
            color: #1a1a1a;
            padding-left: 48px;
        }
        
        .patient-address {
            font-size: 15px;
            color: #333;
            margin-bottom: 0;
            line-height: 1.5;
            padding-left: 48px;
            max-width: 520px;
        }
        
        .body-text {
            font-size: 16px;
            line-height: 1.72;
            margin-bottom: 34px;
            color: #1a1a1a;
        }
        
        .purpose-text {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 42px;
            color: #1a1a1a;
        }
        
        .closing {
            font-size: 16px;
            margin-bottom: 18px;
            color: #1a1a1a;
        }
        
        .signature-section {
            margin-top: 0;
            margin-bottom: 28px;
        }
        
        .signature-section img {
            width: 195px;
            height: auto;
            margin: 0 0 12px 0;
            display: block;
        }
        
        .signed-line {
            font-size: 12px;
            color: #555;
            margin-top: 0;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .doctor-name {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin-top: 0;
            margin-bottom: 6px;
        }
        
        .doctor-credentials {
            font-size: 14px;
            color: #333;
            line-height: 1.45;
            margin-top: 0;
        }
        
        .divider {
            border: none;
            border-top: 1px solid #ccc;
            margin: 24px 0 12px 0;
        }
        
        .footer {
            font-size: 12px;
            color: #666;
            text-align: center;
            line-height: 1.45;
            padding-bottom: 0;
        }
        
        .paid-stamp {
            display: block;
            text-align: right;
            font-size: 12px;
            color: #16a34a;
            font-weight: 600;
            margin-top: 8px;
        }
        
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }

            html, body {
                width: 100%;
                height: auto;
            }

            body {
                padding: 0;
            }

            .certificate {
                max-width: 100%;
                page-break-inside: avoid;
            }

            .divider,
            .footer {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        
        <!-- HEADER -->
        <div class="header">
            <div class="header-top">
                <div class="header-logo">
                    ' . $logoHtml . '
                </div>
                <div class="header-info">
                    <div class="hospital-name">CARE AUS</div>
                    <div class="hospital-address">
                        Level 35, 100 Barangaroo Avenue<br>
                        BARANGAROO, NSW, 2000<br>
                        <a href="mailto:admin@careaus.com.au">admin@careaus.com.au</a><br>
                        ABN: 43-668-260-964
                    </div>
                </div>
            </div>
        </div>
        
        <!-- DATE & REFERENCE -->
        <div class="reference-line">
            <span class="date-line">Date: ' . $issueDate . '</span>
            <span class="ref-line">Reference: ' . $certificateNumber . '</span>
        </div>
        
        <!-- TITLE -->
        <div class="certificate-title">Medical Certificate</div>
        
        <!-- PATIENT -->
        <div class="patient-section">
            <div class="re-line">Re:</div>
            <div class="patient-name">' . $patientName . '</div>
            ' . ($patientAddress ? '<div class="patient-address">' . $patientAddress . '</div>' : '') . '
        </div>
        
        <!-- BODY -->
        <div class="body-text">
            This document is to certify that the above patient, <strong>' . $patientName . '</strong>, 
            is unfit for their normal duties from the <strong>' . $startDate . '</strong> 
            until the <strong>' . $endDate . '</strong> inclusive (' . (int)$days . ' day' . ($days > 1 ? 's' : '') . ').
        </div>
        
        <div class="purpose-text">
            This document is produced for the purposes of <strong>' . $purposeLabel . '</strong>.
        </div>
        
        <!-- SIGNATURE -->
        <div class="closing">Yours sincerely,</div>
        
        <div class="signature-section">
            ' . $signatureHtml . '
            <div class="signed-line">Signed with digital certificate</div>
            <div class="doctor-name">Dr ' . htmlspecialchars($doctorDisplayName) . '</div>
            <div class="doctor-credentials">
                ' . $specialization . '<br>
                AHPRA: ' . $licenseNumber . '
            </div>
        </div>
        
        <!-- FOOTER -->
        <hr class="divider">
        <div class="footer">
            This is a computer-generated medical certificate and is valid without a physical signature.<br>
            Certificate #: ' . $certificateNumber . ' &nbsp;|&nbsp; Issued: ' . $issueDate . '
        </div>
        
    </div>
</body>
</html>';

    return $html;
}