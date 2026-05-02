<?php
// Use absolute path resolution to avoid relative path issues
$basePath = dirname(__DIR__);
require_once $basePath . '/includes/config.php';

/**
 * Generate Medical Certificate PDF
 */
function generateMedicalCertificatePDF($certificateId) {
    global $pdo;
    
    // Get certificate details with bill information
    $stmt = $pdo->prepare("
        SELECT mc.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patient_name,
               u.email as patient_email, u.phoneNumber as patient_phone,
               p.dateOfBirth, p.address,
               CONCAT(du.firstName, ' ', du.lastName) as doctor_name,
               d.specialization, s.licenseNumber,
               b.billId as bill_id, b.totalAmount as bill_amount, b.status as bill_status
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
    $certificate = $stmt->fetch();
    
    if (!$certificate) {
        return false;
    }
    
    // If no doctor assigned, get a default doctor
    if (!$certificate['doctor_name']) {
        $stmt = $pdo->query("
            SELECT CONCAT(u.firstName, ' ', u.lastName) as doctor_name, d.specialization, s.licenseNumber
            FROM doctors d
            JOIN staff s ON d.staffId = s.staffId
            JOIN users u ON s.userId = u.userId
            WHERE d.isAvailable = 1
            LIMIT 1
        ");
        $defaultDoctor = $stmt->fetch();
        if ($defaultDoctor) {
            $certificate['doctor_name'] = $defaultDoctor['doctor_name'];
            $certificate['specialization'] = $defaultDoctor['specialization'];
            $certificate['licenseNumber'] = $defaultDoctor['licenseNumber'];
        } else {
            $certificate['doctor_name'] = 'Medical Officer';
            $certificate['specialization'] = 'General Medicine';
            $certificate['licenseNumber'] = 'N/A';
        }
    }
    
    // Get doctor's first name for signature
    $doctorFirstName = explode(' ', $certificate['doctor_name'])[0];
    
    $certificateTypes = [
        'work' => 'Work Leave',
        'school' => 'School/University Leave',
        'travel' => 'Travel Cancellation',
        'insurance' => 'Insurance Claim',
        'other' => 'Medical Certificate'
    ];
    $certificateTypeLabel = $certificateTypes[$certificate['certificate_type']] ?? 'Medical Certificate';
    
    $startDate = date('F j, Y', strtotime($certificate['start_date']));
    $endDate = date('F j, Y', strtotime($certificate['end_date']));
    $issueDate = date('F j, Y', strtotime($certificate['created_at']));
    
    // Format payment method for display
    $paymentMethodDisplay = ucfirst(str_replace('_', ' ', $certificate['payment_method'] ?? 'card'));
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Medical Certificate - ' . $certificate['certificate_number'] . '</title>
        <style>
            @page {
                size: A4;
                margin: 1.5cm;
            }
            body {
                font-family: "Times New Roman", Times, serif;
                line-height: 1.6;
                color: #1a1a1a;
                margin: 0;
                padding: 0;
            }
            .certificate-container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                border: 2px solid #1e3a5f;
                border-radius: 10px;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #1e3a5f;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .hospital-name {
                font-size: 28px;
                font-weight: bold;
                color: #1e3a5f;
                text-transform: uppercase;
                letter-spacing: 2px;
                margin-bottom: 5px;
            }
            .hospital-subtitle {
                font-size: 14px;
                color: #64748b;
            }
            .certificate-title {
                font-size: 24px;
                font-weight: bold;
                text-align: center;
                text-transform: uppercase;
                color: #1e3a5f;
                margin: 30px 0;
                letter-spacing: 3px;
                text-decoration: underline;
            }
            .certificate-number {
                text-align: right;
                font-size: 12px;
                color: #64748b;
                margin-bottom: 20px;
            }
            .content {
                margin: 30px 0;
                font-size: 14px;
            }
            .patient-details {
                margin: 20px 0;
                padding: 15px;
                background: #f8fafc;
                border-left: 4px solid #1e3a5f;
            }
            .certificate-text {
                margin: 25px 0;
                line-height: 1.8;
            }
            .signature-section {
                margin-top: 50px;
                display: flex;
                justify-content: space-between;
            }
            .signature-box {
                text-align: center;
                width: 45%;
            }
            .signature-line {
                border-bottom: 1px solid #1e3a5f;
                margin: 40px 0 10px;
            }
            .doctor-signature {
                font-family: "Brush Script MT", "Segoe Script", cursive;
                font-size: 28px;
                color: #1e3a5f;
                margin: 10px 0;
            }
            .footer {
                margin-top: 50px;
                padding-top: 20px;
                border-top: 1px solid #cbd5e1;
                font-size: 11px;
                color: #64748b;
                text-align: center;
            }
            .stamp {
                color: #dc2626;
                font-size: 12px;
                font-weight: bold;
                border: 2px solid #dc2626;
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                transform: rotate(-15deg);
                opacity: 0.7;
            }
            .payment-info {
                margin-top: 20px;
                padding: 10px;
                background: #f0fdf4;
                border: 1px solid #10b981;
                border-radius: 5px;
                text-align: center;
                font-size: 12px;
            }
            .payment-info .paid-stamp {
                color: #10b981;
                font-weight: bold;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="certificate-container">
            <div class="header">
                <div class="hospital-name">' . SITE_NAME . '</div>
                <div class="hospital-subtitle">Fussel Lane, Gungahlin, ACT 2912, Australia</div>
                <div class="hospital-subtitle">Phone: +61 438 347 3483 | Emergency: +61 455 2627</div>
            </div>
            
            <div class="certificate-number">
                Certificate #: ' . $certificate['certificate_number'] . '<br>
                Issue Date: ' . $issueDate . '<br>
                Bill #: ' . str_pad($certificate['bill_id'] ?? 0, 6, '0', STR_PAD_LEFT) . '
            </div>
            
            <div class="certificate-title">
                MEDICAL CERTIFICATE
            </div>
            
            <div class="content">
                <div class="patient-details">
                    <strong>Patient Details:</strong><br>
                    Name: ' . htmlspecialchars($certificate['patient_name']) . '<br>
                    Date of Birth: ' . ($certificate['dateOfBirth'] ? date('F j, Y', strtotime($certificate['dateOfBirth'])) : 'N/A') . '<br>
                    Address: ' . htmlspecialchars($certificate['address'] ?: 'N/A') . '
                </div>
                
                <div class="certificate-text">
                    <p>This is to certify that <strong>' . htmlspecialchars($certificate['patient_name']) . '</strong> 
                    has been under my medical care and treatment for the following condition:</p>
                    
                    <p style="padding: 10px 20px; background: #f1f5f9; border-radius: 5px;">
                        <strong>Diagnosis/Condition:</strong> ' . htmlspecialchars($certificate['medical_condition']) . '
                    </p>
                    
                    <p>Due to this medical condition, the patient was/will be unfit for 
                    ' . $certificateTypeLabel . ' from <strong>' . $startDate . '</strong> to <strong>' . $endDate . '</strong>.</p>
                    
                    ' . ($certificate['additional_notes'] ? '<p><strong>Additional Notes:</strong> ' . htmlspecialchars($certificate['additional_notes']) . '</p>' : '') . '
                    
                    <p>This certificate is issued based on the patient\'s medical history and clinical examination.</p>
                </div>
            </div>
            
            <div class="payment-info">
                <span class="paid-stamp">✓ PAYMENT CONFIRMED</span><br>
                Amount Paid: $' . number_format($certificate['amount_paid'], 2) . ' | 
                Payment Method: ' . $paymentMethodDisplay . ' | 
                Bill #: ' . str_pad($certificate['bill_id'] ?? 0, 6, '0', STR_PAD_LEFT) . '<br>
                Transaction Date: ' . $issueDate . '
            </div>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="doctor-signature">' . htmlspecialchars($doctorFirstName) . '</div>
                    <div class="signature-line"></div>
                    <strong>Dr. ' . htmlspecialchars($certificate['doctor_name']) . '</strong><br>
                    ' . htmlspecialchars($certificate['specialization'] ?? 'General Medicine') . '<br>
                    License #: ' . htmlspecialchars($certificate['licenseNumber'] ?? 'N/A') . '
                </div>
                <div class="signature-box">
                    <div class="stamp">OFFICIAL STAMP</div>
                    <div class="signature-line"></div>
                    Hospital Seal & Date
                </div>
            </div>
            
            <div class="footer">
                <p>This is a computer-generated medical certificate and is valid without a physical signature.</p>
                <p>' . SITE_NAME . ' | Fussel Lane, Gungahlin, ACT 2912 | Tel: +61 438 347 3483</p>
                <p>This certificate is issued for ' . $certificateTypeLabel . ' purposes only.</p>
                <p>ABN: 12 345 678 901 | Provider #: ' . str_pad($certificate['doctor_id'] ?? 0, 6, '0', STR_PAD_LEFT) . '</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Save as HTML file (can be converted to PDF in production)
    $pdfDir = $basePath . '/uploads/certificates/';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0777, true);
    }
    
    $pdfPath = $pdfDir . $certificate['certificate_number'] . '.html';
    file_put_contents($pdfPath, $html);
    
    return $pdfPath;
}

/**
 * Send email with attachment
 */
function sendEmailWithAttachment($to, $subject, $message, $attachmentPath, $filename) {
    require_once dirname(__DIR__) . '/PHPMailer/Exception.php';
    require_once dirname(__DIR__) . '/PHPMailer/PHPMailer.php';
    require_once dirname(__DIR__) . '/PHPMailer/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        // Add attachment if file exists
        if (file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $filename);
        }

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>