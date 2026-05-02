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

    // Avoid Dompdf FontLib custom font error
    $options->set('defaultFont', 'Helvetica');
    $options->set('isFontSubsettingEnabled', false);

    $dompdf = new Dompdf($options);

    // Replace risky fonts if they appear in HTML
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

    $issueDate = date('d/m/Y');
    $startDate = !empty($cert['start_date']) ? date('d/m/Y', strtotime($cert['start_date'])) : 'N/A';
    $endDate = !empty($cert['end_date']) ? date('d/m/Y', strtotime($cert['end_date'])) : 'N/A';

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

    $certificateNumber = htmlspecialchars($cert['certificate_number'] ?? ('MC-' . $certificateId));
    $patientName = htmlspecialchars($cert['patient_name'] ?? 'Patient');
    $specialization = htmlspecialchars($cert['specialization'] ?? 'General Medicine');
    $licenseNumber = !empty($cert['licenseNumber']) ? htmlspecialchars($cert['licenseNumber']) : '';

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
            margin-bottom: 25px;
        }

        .logo-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }

        .logo-cell {
            display: table-cell;
            width: 65px;
            vertical-align: middle;
        }

        .name-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .logo-image {
            width: 50px;
            height: auto;
            margin-right: 12px;
            vertical-align: middle;
        }

        .hospital-name {
            font-size: 22px;
            font-weight: 700;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1;
        }

        .hospital-details {
            font-size: 9px;
            color: #333333;
            line-height: 1.3;
            margin-top: 5px;
            margin-left: 0;
        }

        .date {
            text-align: right;
            font-size: 11px;
            margin: 25px 0 35px;
        }

        .patient-name {
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .body-text {
            font-size: 12px;
            line-height: 1.5;
        }

        .closing {
            margin: 50px 0 15px;
            font-size: 12px;
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
            font-size: 12px;
        }

        .doctor-credentials {
            font-size: 10px;
            color: #333333;
            line-height: 1.3;
        }

        .footer {
            margin-top: 45px;
            padding-top: 12px;
            border-top: 1px solid #cccccc;
            font-size: 8px;
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
        
        <div class="patient-name">
            ' . $patientName . ' has been clinically assessed.
        </div>
        
        <div class="body-text">
            They currently have a medical condition and will be unable to attend work/study 
            from <strong>' . $startDate . '</strong> to <strong>' . $endDate . '</strong> inclusive.
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