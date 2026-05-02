<?php
require_once 'includes/config.php';

$pageTitle = "Generate Medical Certificate - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

// Define certificate types array at the very top
$certificateTypes = [
    'work' => 'Work Leave',
    'school' => 'School/University Leave',
    'travel' => 'Travel Cancellation',
    'insurance' => 'Insurance Claim',
    'other' => 'Other'
];

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';
$patientData = [];
$certificateData = [];
$generatedCertificate = null;
$billData = null;
$selectedDoctor = null;
$appointmentData = null;

// Check if user is logged in and pre-fill data
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    
    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.*, p.dateOfBirth, p.address, p.bloodType, p.patientId
        FROM users u
        LEFT JOIN patients p ON u.userId = p.userId
        WHERE u.userId = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    
    if ($userData) {
        $patientData = [
            'first_name' => $userData['firstName'],
            'last_name' => $userData['lastName'],
            'email' => $userData['email'],
            'phone' => $userData['phoneNumber'],
            'date_of_birth' => $userData['dateOfBirth'] ?? '',
            'address' => $userData['address'] ?? '',
            'patient_id' => $userData['patientId'] ?? null
        ];
    }
}

// Handle form submission - Step 1: Patient Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1'])) {
    $patientData = [
        'first_name' => sanitizeInput($_POST['first_name']),
        'last_name' => sanitizeInput($_POST['last_name']),
        'email' => sanitizeInput($_POST['email']),
        'phone' => sanitizeInput($_POST['phone']),
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'address' => sanitizeInput($_POST['address'] ?? '')
    ];
    
    $errors = [];
    if (empty($patientData['first_name'])) $errors[] = "First name is required.";
    if (empty($patientData['last_name'])) $errors[] = "Last name is required.";
    if (empty($patientData['email']) || !filter_var($patientData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required.";
    }
    
    if (empty($errors)) {
        $_SESSION['mc_patient_data'] = $patientData;
        header("Location: medical-certificate.php?step=2");
        exit();
    } else {
        $error = implode(' ', $errors);
    }
}

// Handle form submission - Step 2: Certificate Details + Book Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2'])) {
    $patientData = $_SESSION['mc_patient_data'] ?? [];
    
    $certificateData = [
        'certificate_type' => $_POST['certificate_type'] ?? 'work',
        'medical_condition' => sanitizeInput($_POST['medical_condition']),
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'doctor_id' => (int)($_POST['doctor_id'] ?? 0),
        'additional_notes' => sanitizeInput($_POST['additional_notes'] ?? ''),
        'appointment_date' => $_POST['appointment_date'] ?? '',
        'appointment_time' => $_POST['appointment_time'] ?? ''
    ];
    
    $errors = [];
    if (empty($certificateData['medical_condition'])) {
        $errors[] = "Medical condition/reason is required.";
    }
    if (empty($certificateData['start_date'])) {
        $errors[] = "Start date is required.";
    }
    if (empty($certificateData['end_date'])) {
        $errors[] = "End date is required.";
    }
    if (strtotime($certificateData['end_date']) < strtotime($certificateData['start_date'])) {
        $errors[] = "End date cannot be before start date.";
    }
    if (empty($certificateData['doctor_id'])) {
        $errors[] = "Please select an issuing doctor.";
    }
    if (empty($certificateData['appointment_date'])) {
        $errors[] = "Please select an appointment date.";
    }
    if (empty($certificateData['appointment_time'])) {
        $errors[] = "Please select an appointment time.";
    }
    
    // Validate date range (within 7 days past or future)
    $today = strtotime(date('Y-m-d'));
    $startDate = strtotime($certificateData['start_date']);
    $endDate = strtotime($certificateData['end_date']);
    $maxPast = strtotime('-7 days', $today);
    $maxFuture = strtotime('+7 days', $today);
    
    if ($startDate < $maxPast || $startDate > $maxFuture) {
        $errors[] = "Start date must be within 7 days before or after today.";
    }
    if ($endDate < $maxPast || $endDate > $maxFuture) {
        $errors[] = "End date must be within 7 days before or after today.";
    }
    
    if (empty($errors)) {
        // Validate appointment time slot availability
        $apptTime = $certificateData['appointment_time'];
        if (strlen($apptTime) === 5 && strpos($apptTime, ':') === 2) {
            $apptTime .= ':00';
        }
        $apptDateTime = date('Y-m-d H:i:s', strtotime($certificateData['appointment_date'] . ' ' . $apptTime));
        
        // Check if slot is already booked
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE doctorId = ? AND dateTime = ? 
            AND status NOT IN ('cancelled', 'no-show')
        ");
        $checkStmt->execute([$certificateData['doctor_id'], $apptDateTime]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "This time slot is already booked. Please select another time.";
        }
    }
    
    if (empty($errors)) {
        $_SESSION['mc_certificate_data'] = $certificateData;
        header("Location: medical-certificate.php?step=3");
        exit();
    } else {
        $error = implode(' ', $errors);
    }
}

// Handle payment and generation - Step 3
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step3'])) {
    $patientData = $_SESSION['mc_patient_data'] ?? [];
    $certificateData = $_SESSION['mc_certificate_data'] ?? [];
    
    if (empty($patientData) || empty($certificateData)) {
        header("Location: medical-certificate.php");
        exit();
    }
    
    $paymentMethod = $_POST['payment_method'] ?? 'card';
    $amount = MEDICAL_CERTIFICATE_FEE;
    
    try {
        $pdo->beginTransaction();
        
        // Check if patient exists
        $stmt = $pdo->prepare("
            SELECT u.userId, p.patientId 
            FROM users u 
            LEFT JOIN patients p ON u.userId = p.userId 
            WHERE u.email = ?
        ");
        $stmt->execute([$patientData['email']]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            // Create temporary patient record
            $tempUsername = 'temp_' . uniqid();
            $tempPassword = bin2hex(random_bytes(8));
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, isVerified, dateCreated)
                VALUES (?, ?, ?, ?, ?, ?, 'patient', 0, NOW())
            ");
            $stmt->execute([
                $tempUsername,
                password_hash($tempPassword, PASSWORD_DEFAULT),
                $patientData['email'],
                $patientData['first_name'],
                $patientData['last_name'],
                $patientData['phone'],
            ]);
            $userId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("
                INSERT INTO patients (userId, dateOfBirth, address, createdAt)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $patientData['date_of_birth'] ?: null, $patientData['address']]);
            $patientId = $pdo->lastInsertId();
        } else {
            $userId = $existingUser['userId'];
            $patientId = $existingUser['patientId'];
            
            if (!empty($patientData['address'])) {
                $stmt = $pdo->prepare("UPDATE patients SET address = ? WHERE patientId = ?");
                $stmt->execute([$patientData['address'], $patientId]);
            }
        }
        
        // BOOK THE APPOINTMENT FIRST
        $apptTime = $certificateData['appointment_time'];
        if (strlen($apptTime) === 5 && strpos($apptTime, ':') === 2) {
            $apptTime .= ':00';
        }
        $apptDateTime = date('Y-m-d H:i:s', strtotime($certificateData['appointment_date'] . ' ' . $apptTime));
        $apptReason = 'Medical Certificate: ' . $certificateData['medical_condition'];
        
        $stmt = $pdo->prepare("
            INSERT INTO appointments (patientId, doctorId, dateTime, duration, reason, status, createdAt) 
            VALUES (?, ?, ?, 30, ?, 'scheduled', NOW())
        ");
        $stmt->execute([$patientId, $certificateData['doctor_id'], $apptDateTime, $apptReason]);
        $appointmentId = $pdo->lastInsertId();
        
        // Create medical certificate record
        $certificateNumber = 'MC-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        // Create bill (bills table uses appointmentId - camelCase)
        $stmt = $pdo->prepare("
            INSERT INTO bills (
                patientId, appointmentId, consultationFee, additionalCharges, 
                serviceCharge, gst, totalAmount, status, generatedAt, paidAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $patientId,
            $appointmentId,
            $amount,
            0,      // additionalCharges
            0,      // serviceCharge
            0,      // gst
            $amount, // totalAmount
            'paid',
            date('Y-m-d H:i:s')
        ]);
        $billId = $pdo->lastInsertId();
        
        // Add bill charge for medical certificate
        $stmt = $pdo->prepare("INSERT INTO bill_charges (billId, chargeName, amount) VALUES (?, ?, ?)");
        $stmt->execute([$billId, 'Medical Certificate Fee - ' . $certificateNumber, $amount]);
        
        // Create medical certificate record
        $stmt = $pdo->prepare("
            INSERT INTO medical_certificates (
                certificate_number, patient_id, doctor_id, certificate_type,
                medical_condition, start_date, end_date, additional_notes,
                amount_paid, payment_method, payment_status, approval_status, 
                bill_id, appointment_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'pending_consultation', ?, ?, NOW())
        ");
        $stmt->execute([
            $certificateNumber,
            $patientId,
            $certificateData['doctor_id'],
            $certificateData['certificate_type'],
            $certificateData['medical_condition'],
            $certificateData['start_date'],
            $certificateData['end_date'],
            $certificateData['additional_notes'],
            $amount,
            $paymentMethod,
            $billId,
            $appointmentId
        ]);
        $certificateId = $pdo->lastInsertId();
        
        // Add finance transaction
        addFinanceTransaction('revenue', 'medical_certificate', $amount, $certificateId, 
            "Medical certificate fee - {$certificateNumber} - via {$paymentMethod}");
        
        // Get doctor details for notifications
        $doctorStmt = $pdo->prepare("
            SELECT s.userId as doctor_user_id, u.email as doctor_email, 
                   CONCAT(u.firstName, ' ', u.lastName) as doctor_name,
                   d.specialization
            FROM doctors d
            JOIN staff s ON d.staffId = s.staffId
            JOIN users u ON s.userId = u.userId
            WHERE d.doctorId = ?
        ");
        $doctorStmt->execute([$certificateData['doctor_id']]);
        $doctorInfo = $doctorStmt->fetch();
        
        $certificateTypeLabel = isset($certificateTypes[$certificateData['certificate_type']]) 
            ? $certificateTypes[$certificateData['certificate_type']] 
            : 'Medical Certificate';
        
        $formattedApptDateTime = date('l, F j, Y \a\t g:i A', strtotime($apptDateTime));
        $certificateLink = SITE_URL . "/doctor/certificate-consultation.php?certificate_id=" . $certificateId;
        
        // SEND EMAIL TO DOCTOR
        if ($doctorInfo && $doctorInfo['doctor_email']) {
            $doctorSubject = "New Medical Certificate Request - Consultation Required";
            $doctorMessage = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #1e3a5f; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .certificate-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                        .button { display: inline-block; background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 10px; font-weight: bold; }
                        .highlight { background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Medical Certificate Request - In-Person Consultation Required</h2>
                        </div>
                        <div class='content'>
                            <p>Dear Dr. <strong>{$doctorInfo['doctor_name']}</strong>,</p>
                            <p>A patient has requested a medical certificate and has scheduled a consultation with you.</p>
                            <div class='highlight'>
                                <p><strong>Appointment Scheduled:</strong> {$formattedApptDateTime}</p>
                                <p><strong>Note:</strong> Patient must attend this in-person consultation before the certificate can be approved.</p>
                            </div>
                            <div class='certificate-info'>
                                <p><strong>Certificate #:</strong> {$certificateNumber}</p>
                                <p><strong>Patient:</strong> {$patientData['first_name']} {$patientData['last_name']}</p>
                                <p><strong>Condition:</strong> {$certificateData['medical_condition']}</p>
                                <p><strong>Period:</strong> " . date('M j, Y', strtotime($certificateData['start_date'])) . " - " . date('M j, Y', strtotime($certificateData['end_date'])) . "</p>
                                <p><strong>Type:</strong> {$certificateTypeLabel}</p>
                                <p><strong>Payment:</strong> $" . number_format($amount, 2) . " (Paid)</p>
                            </div>
                            <p>Please conduct the consultation and approve the certificate through the dedicated portal:</p>
                            <p style='text-align: center;'>
                                <a href='{$certificateLink}' class='button'>Start Certificate Consultation</a>
                            </p>
                            <p>Or login to your dashboard to manage all pending requests.</p>
                            <p><a href='" . SITE_URL . "/doctor/dashboard.php'>Go to Doctor Dashboard</a></p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            sendEmail($doctorInfo['doctor_email'], $doctorSubject, $doctorMessage);
            
            createNotification(
                $doctorInfo['doctor_user_id'],
                'medical_certificate',
                'Medical Certificate - Consultation Required',
                "Patient {$patientData['first_name']} {$patientData['last_name']} has requested a medical certificate and scheduled a consultation for {$formattedApptDateTime}.",
                "doctor/certificate-consultation.php?certificate_id=" . $certificateId
            );
        }
        
        // SEND EMAIL TO PATIENT
        $patientSubject = "Medical Certificate Request & Appointment Confirmation - " . SITE_NAME;
        $patientMessage = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1e3a5f; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .certificate-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .payment-badge { background: #10b981; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; font-weight: bold; }
                    .appointment-highlight { background: #eff6ff; padding: 15px; border-radius: 8px; border-left: 4px solid #3b82f6; margin: 15px 0; }
                    .status-badge { background: #f59e0b; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; font-weight: bold; }
                    .important-note { background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Medical Certificate Request Received</h2>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>{$patientData['first_name']} {$patientData['last_name']}</strong>,</p>
                        <p>Your medical certificate request has been received and payment has been confirmed.</p>
                        
                        <div class='appointment-highlight'>
                            <p><strong>In-Person Consultation Required</strong></p>
                            <p><strong>Doctor:</strong> Dr. {$doctorInfo['doctor_name']} ({$doctorInfo['specialization']})</p>
                            <p><strong>Date & Time:</strong> {$formattedApptDateTime}</p>
                            <p><strong>Location:</strong> Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                        </div>
                        
                        <div class='important-note'>
                            <p><strong>Important:</strong> You must attend this in-person consultation with the doctor. The medical certificate will only be approved after the consultation.</p>
                        </div>
                        
                        <div class='certificate-info'>
                            <p><strong>Certificate Number:</strong> {$certificateNumber}</p>
                            <p><strong>Period:</strong> " . date('M j, Y', strtotime($certificateData['start_date'])) . " - " . date('M j, Y', strtotime($certificateData['end_date'])) . "</p>
                            <p><strong>Amount Paid:</strong> $" . number_format($amount, 2) . "</p>
                            <p><span class='payment-badge'>PAYMENT CONFIRMED</span></p>
                            <p><span class='status-badge'>PENDING IN-PERSON CONSULTATION</span></p>
                            <p><strong>Bill #:</strong> " . str_pad($billId, 6, '0', STR_PAD_LEFT) . "</p>
                        </div>
                        
                        <p>Please arrive 15 minutes before your appointment time. Bring any relevant medical documents.</p>
                        <p>If you have any questions, please contact our office at +61 438 347 3483.</p>
                        <p style='margin-top: 30px;'>Thank you for choosing HealthManagement System.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        sendEmail($patientData['email'], $patientSubject, $patientMessage);
        
        if ($existingUser) {
            createNotification($userId, 'medical_certificate', 'Certificate Request & Appointment Confirmed', 
                "Your medical certificate request #{$certificateNumber} has been received. You have an appointment with Dr. {$doctorInfo['doctor_name']} on {$formattedApptDateTime}. Payment confirmed.",
                "patient/view-medical-certificates.php?view=" . $certificateId);
        }
        
        $pdo->commit();
        
        $generatedCertificate = [
            'number' => $certificateNumber,
            'start_date' => $certificateData['start_date'],
            'end_date' => $certificateData['end_date'],
            'email' => $patientData['email'],
            'bill_id' => $billId,
            'amount' => $amount,
            'status' => 'pending_consultation',
            'appointment_datetime' => $apptDateTime,
            'doctor_name' => $doctorInfo['doctor_name'] ?? 'Assigned Doctor'
        ];
        
        unset($_SESSION['mc_patient_data'], $_SESSION['mc_certificate_data']);
        
        $step = 4;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to generate certificate: " . $e->getMessage();
        error_log("Medical certificate error: " . $e->getMessage());
    }
}

// Get available doctors for dropdown
$doctors = $pdo->query("
    SELECT d.doctorId, u.firstName, u.lastName, d.specialization
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.isAvailable = 1
    ORDER BY u.firstName, u.lastName
")->fetchAll();

// Restore session data if available
if ($step == 2 && isset($_SESSION['mc_patient_data'])) {
    $patientData = $_SESSION['mc_patient_data'];
}
if ($step == 3 && isset($_SESSION['mc_patient_data']) && isset($_SESSION['mc_certificate_data'])) {
    $patientData = $_SESSION['mc_patient_data'];
    $certificateData = $_SESSION['mc_certificate_data'];
}

// Get doctor name if doctor_id is set
$selectedDoctor = null;
if (!empty($certificateData['doctor_id'])) {
    $stmt = $pdo->prepare("
        SELECT u.firstName, u.lastName, d.specialization, d.consultationFee
        FROM doctors d
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        WHERE d.doctorId = ?
    ");
    $stmt->execute([$certificateData['doctor_id']]);
    $selectedDoctor = $stmt->fetch();
}
?>

<div class="root-container">
    <div class="root-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-medical"></i> Medical Certificate</h1>
            <p>Generate an official medical certificate for work, school, or other purposes</p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="root-btn root-btn-outline">
                <i class="fas fa-home"></i> Home
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="root-alert root-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Progress Steps -->
    <div class="mc-progress-container">
        <div class="mc-progress-steps">
            <div class="mc-step <?php echo $step >= 1 ? 'completed' : ''; ?> <?php echo $step == 1 ? 'active' : ''; ?>">
                <div class="mc-step-number">1</div>
                <div class="mc-step-label">Patient Details</div>
            </div>
            <div class="mc-step-line <?php echo $step >= 2 ? 'completed' : ''; ?>"></div>
            <div class="mc-step <?php echo $step >= 2 ? 'completed' : ''; ?> <?php echo $step == 2 ? 'active' : ''; ?>">
                <div class="mc-step-number">2</div>
                <div class="mc-step-label">Details & Book</div>
            </div>
            <div class="mc-step-line <?php echo $step >= 3 ? 'completed' : ''; ?>"></div>
            <div class="mc-step <?php echo $step >= 3 ? 'completed' : ''; ?> <?php echo $step == 3 ? 'active' : ''; ?>">
                <div class="mc-step-number">3</div>
                <div class="mc-step-label">Payment</div>
            </div>
            <div class="mc-step-line <?php echo $step >= 4 ? 'completed' : ''; ?>"></div>
            <div class="mc-step <?php echo $step >= 4 ? 'completed' : ''; ?> <?php echo $step == 4 ? 'active' : ''; ?>">
                <div class="mc-step-number">4</div>
                <div class="mc-step-label">Complete</div>
            </div>
        </div>
    </div>

    <?php if ($step == 1): ?>
        <!-- Step 1: Patient Details (unchanged) -->
        <div class="mc-card">
            <div class="mc-card-header">
                <h3><i class="fas fa-user"></i> Patient Information</h3>
                <?php if (isLoggedIn()): ?>
                    <span class="mc-badge mc-badge-success"><i class="fas fa-check-circle"></i> Logged in as <?php echo htmlspecialchars($userData['firstName'] ?? ''); ?></span>
                <?php endif; ?>
            </div>
            <div class="mc-card-body">
                <form method="POST" id="mc-step1-form">
                    <input type="hidden" name="step1" value="1">
                    
                    <div class="root-form-row">
                        <div class="root-form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($patientData['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="root-form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($patientData['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="root-form-row">
                        <div class="root-form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($patientData['email'] ?? ''); ?>" required>
                            <small>The certificate will be sent to this email</small>
                        </div>
                        <div class="root-form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($patientData['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="root-form-row">
                        <div class="root-form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($patientData['date_of_birth'] ?? ''); ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="root-form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($patientData['address'] ?? ''); ?>" 
                                   placeholder="Your current address">
                        </div>
                    </div>
                    
                    <div class="mc-form-actions">
                        <button type="submit" class="root-btn root-btn-primary">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($step == 2): ?>
        <!-- Step 2: Certificate Details + Book Appointment with Doctor -->
        <div class="mc-card">
            <div class="mc-card-header">
                <h3><i class="fas fa-file-medical"></i> Certificate Details & Book Consultation</h3>
                <span class="mc-badge">Patient: <?php echo htmlspecialchars($patientData['first_name'] . ' ' . $patientData['last_name']); ?></span>
            </div>
            <div class="mc-card-body">
                <form method="POST" id="mc-step2-form">
                    <input type="hidden" name="step2" value="1">
                    
                    <div class="root-form-group">
                        <label for="certificate_type">Certificate Type <span class="required">*</span></label>
                        <select id="certificate_type" name="certificate_type" class="root-form-control" required>
                            <?php foreach ($certificateTypes as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($certificateData['certificate_type'] ?? '') == $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="root-form-group">
                        <label for="medical_condition">Medical Condition / Reason <span class="required">*</span></label>
                        <textarea id="medical_condition" name="medical_condition" rows="3" class="root-form-control" 
                                  placeholder="e.g., Upper respiratory infection, requiring rest from..." required><?php echo htmlspecialchars($certificateData['medical_condition'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="root-form-row">
                        <div class="root-form-group">
                            <label for="start_date">Certificate Start Date <span class="required">*</span></label>
                            <input type="date" id="start_date" name="start_date" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($certificateData['start_date'] ?? ''); ?>"
                                   min="<?php echo date('Y-m-d', strtotime('-7 days')); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                        </div>
                        <div class="root-form-group">
                            <label for="end_date">Certificate End Date <span class="required">*</span></label>
                            <input type="date" id="end_date" name="end_date" class="root-form-control" 
                                   value="<?php echo htmlspecialchars($certificateData['end_date'] ?? ''); ?>"
                                   min="<?php echo date('Y-m-d', strtotime('-7 days')); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Doctor Selection & Appointment Booking -->
                    <div class="mc-section-divider">
                        <h4><i class="fas fa-calendar-check"></i> Book Doctor Consultation (Required)</h4>
                        <p class="mc-section-note">You must attend an in-person consultation with the doctor to get your certificate approved.</p>
                    </div>
                    
                    <div class="root-form-group">
                        <label for="doctor_id">Select Doctor <span class="required">*</span></label>
                        <select id="doctor_id" name="doctor_id" class="root-form-control" required onchange="loadDoctorSlots()">
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['doctorId']; ?>" <?php echo ($certificateData['doctor_id'] ?? '') == $doctor['doctorId'] ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?> 
                                    (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="root-form-row">
                        <div class="root-form-group">
                            <label for="appointment_date">Consultation Date <span class="required">*</span></label>
                            <input type="date" id="appointment_date" name="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                                   class="root-form-control" required onchange="loadDoctorSlots()"
                                   value="<?php echo htmlspecialchars($certificateData['appointment_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="root-form-group">
                        <label>Select Time <span class="required">*</span></label>
                        <div id="time-slots-container" class="mc-time-slots-container">
                            <p class="mc-text-muted">Please select a doctor and date first</p>
                        </div>
                        <input type="hidden" id="appointment_time" name="appointment_time" value="<?php echo htmlspecialchars($certificateData['appointment_time'] ?? ''); ?>">
                    </div>
                    
                    <div class="root-form-group">
                        <label for="additional_notes">Additional Notes (Optional)</label>
                        <textarea id="additional_notes" name="additional_notes" rows="2" class="root-form-control" 
                                  placeholder="Any additional information..."><?php echo htmlspecialchars($certificateData['additional_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mc-date-summary">
                        <p><i class="fas fa-calendar"></i> Certificate Period: 
                            <span id="date-range-display">
                                <?php 
                                if (!empty($certificateData['start_date']) && !empty($certificateData['end_date'])) {
                                    $days = (strtotime($certificateData['end_date']) - strtotime($certificateData['start_date'])) / 86400 + 1;
                                    echo date('M j, Y', strtotime($certificateData['start_date'])) . ' - ' . 
                                         date('M j, Y', strtotime($certificateData['end_date'])) . 
                                         ' (' . $days . ' day' . ($days > 1 ? 's' : '') . ')';
                                } else {
                                    echo 'Select dates above';
                                }
                                ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="mc-form-actions">
                        <a href="medical-certificate.php?step=1" class="root-btn root-btn-outline">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <button type="submit" class="root-btn root-btn-primary">
                            Continue to Payment <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($step == 3): ?>
        <!-- Step 3: Payment -->
        <div class="mc-card">
            <div class="mc-card-header">
                <h3><i class="fas fa-credit-card"></i> Payment</h3>
            </div>
            <div class="mc-card-body">
                <div class="mc-order-summary">
                    <h4>Order Summary</h4>
                    <div class="mc-summary-item">
                        <span>Patient:</span>
                        <strong><?php echo htmlspecialchars($patientData['first_name'] . ' ' . $patientData['last_name']); ?></strong>
                    </div>
                    <div class="mc-summary-item">
                        <span>Certificate Type:</span>
                        <strong><?php echo $certificateTypes[$certificateData['certificate_type']] ?? $certificateData['certificate_type']; ?></strong>
                    </div>
                    <div class="mc-summary-item">
                        <span>Certificate Period:</span>
                        <strong>
                            <?php 
                            $days = (strtotime($certificateData['end_date']) - strtotime($certificateData['start_date'])) / 86400 + 1;
                            echo date('M j, Y', strtotime($certificateData['start_date'])) . ' - ' . 
                                 date('M j, Y', strtotime($certificateData['end_date'])) . 
                                 ' (' . $days . ' day' . ($days > 1 ? 's' : '') . ')';
                            ?>
                        </strong>
                    </div>
                    <div class="mc-summary-item">
                        <span>Doctor:</span>
                        <strong>
                            <?php if ($selectedDoctor): ?>
                                Dr. <?php echo htmlspecialchars($selectedDoctor['firstName'] . ' ' . $selectedDoctor['lastName']); ?>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <div class="mc-summary-item">
                        <span>Consultation:</span>
                        <strong>
                            <?php 
                            if (!empty($certificateData['appointment_date']) && !empty($certificateData['appointment_time'])) {
                                $apptTime = $certificateData['appointment_time'];
                                if (strlen($apptTime) === 8) {
                                    echo date('M j, Y g:i A', strtotime($certificateData['appointment_date'] . ' ' . $apptTime));
                                } else {
                                    echo $certificateData['appointment_date'] . ' at ' . $apptTime;
                                }
                            }
                            ?>
                        </strong>
                    </div>
                    <div class="mc-summary-divider"></div>
                    <div class="mc-summary-item mc-summary-total">
                        <span>Total Amount:</span>
                        <strong>$<?php echo number_format(MEDICAL_CERTIFICATE_FEE, 2); ?></strong>
                    </div>
                </div>
                
                <form method="POST" id="mc-step3-form">
                    <input type="hidden" name="step3" value="1">
                    
                    <div class="root-form-group">
                        <label>Payment Method <span class="required">*</span></label>
                        <div class="mc-payment-methods">
                            <label class="mc-payment-option">
                                <input type="radio" name="payment_method" value="card" checked>
                                <i class="far fa-credit-card"></i> Credit/Debit Card
                            </label>
                            <label class="mc-payment-option">
                                <input type="radio" name="payment_method" value="cash">
                                <i class="fas fa-money-bill-wave"></i> Cash at Counter
                            </label>
                            <label class="mc-payment-option">
                                <input type="radio" name="payment_method" value="paypal">
                                <i class="fab fa-paypal"></i> PayPal
                            </label>
                        </div>
                    </div>
                    
                    <div id="card-payment-form" class="mc-card-payment-form">
                        <div class="root-form-group">
                            <label>Card Number</label>
                            <input type="text" class="root-form-control" placeholder="4111 1111 1111 1111" value="4111111111111111" readonly disabled>
                            <small class="mc-test-mode">Test Mode - Use demo card</small>
                        </div>
                        <div class="root-form-row">
                            <div class="root-form-group">
                                <label>Expiry Date</label>
                                <input type="text" class="root-form-control" placeholder="MM/YY" value="12/28" readonly disabled>
                            </div>
                            <div class="root-form-group">
                                <label>CVV</label>
                                <input type="text" class="root-form-control" placeholder="123" value="123" readonly disabled>
                            </div>
                        </div>
                        <div class="root-form-group">
                            <label>Cardholder Name</label>
                            <input type="text" class="root-form-control" value="<?php echo htmlspecialchars($patientData['first_name'] . ' ' . $patientData['last_name']); ?>" readonly disabled>
                        </div>
                    </div>
                    
                    <div class="mc-terms">
                        <label>
                            <input type="checkbox" required> I confirm that the information provided is accurate and I understand I must attend the in-person consultation.
                        </label>
                    </div>
                    
                    <div class="mc-form-actions">
                        <a href="medical-certificate.php?step=2" class="root-btn root-btn-outline">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <button type="submit" class="root-btn root-btn-success">
                            <i class="fas fa-lock"></i> Pay $<?php echo number_format(MEDICAL_CERTIFICATE_FEE, 2); ?> & Book Consultation
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($step == 4): ?>
        <!-- Step 4: Complete -->
        <div class="mc-success-card">
            <div class="mc-success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Appointment Booked & Payment Confirmed!</h2>
            <p>Your medical certificate request and doctor consultation have been scheduled.</p>
            
            <?php if ($generatedCertificate): ?>
            <div class="mc-certificate-details">
                <div class="mc-detail-row">
                    <span>Certificate Number:</span>
                    <strong><?php echo $generatedCertificate['number']; ?></strong>
                </div>
                <div class="mc-detail-row">
                    <span>Amount Paid:</span>
                    <strong class="mc-text-success">$<?php echo number_format($generatedCertificate['amount'], 2); ?></strong>
                </div>
                <div class="mc-detail-row">
                    <span>Payment Status:</span>
                    <strong><span class="mc-status-paid"><i class="fas fa-check-circle"></i> PAID</span></strong>
                </div>
                <div class="mc-detail-row">
                    <span>Doctor:</span>
                    <strong>Dr. <?php echo htmlspecialchars($generatedCertificate['doctor_name']); ?></strong>
                </div>
                <div class="mc-detail-row">
                    <span>Consultation:</span>
                    <strong><?php echo date('F j, Y g:i A', strtotime($generatedCertificate['appointment_datetime'])); ?></strong>
                </div>
                <div class="mc-detail-row">
                    <span>Certificate Period:</span>
                    <strong><?php echo date('M j, Y', strtotime($generatedCertificate['start_date'])) . ' - ' . date('M j, Y', strtotime($generatedCertificate['end_date'])); ?></strong>
                </div>
                <div class="mc-detail-row">
                    <span>Confirmation sent to:</span>
                    <strong><?php echo htmlspecialchars($generatedCertificate['email']); ?></strong>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mc-approval-note">
                <i class="fas fa-calendar-check"></i>
                <p><strong>What's Next:</strong> Attend your scheduled consultation with the doctor. The doctor will review your condition and approve the certificate during the consultation. You will receive the approved certificate via email after the consultation.</p>
            </div>
            
            <div class="mc-success-actions">
                <a href="index.php" class="root-btn root-btn-primary">
                    <i class="fas fa-home"></i> Return to Home
                </a>
                <?php if (isLoggedIn()): ?>
                    <a href="patient/view-medical-certificates.php" class="root-btn root-btn-outline">
                        <i class="fas fa-file-medical"></i> View My Certificates
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date range display update
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const dateRangeDisplay = document.getElementById('date-range-display');
    
    if (startDate && endDate && dateRangeDisplay) {
        function updateDateRange() {
            if (startDate.value && endDate.value) {
                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                
                const options = { month: 'short', day: 'numeric', year: 'numeric' };
                const startStr = start.toLocaleDateString('en-US', options);
                const endStr = end.toLocaleDateString('en-US', options);
                
                dateRangeDisplay.textContent = `${startStr} - ${endStr} (${diffDays} day${diffDays > 1 ? 's' : ''})`;
            }
        }
        
        startDate.addEventListener('change', updateDateRange);
        endDate.addEventListener('change', updateDateRange);
    }
    
    // Validate end date is not before start date
    if (endDate && startDate) {
        endDate.addEventListener('change', function() {
            if (startDate.value && endDate.value < startDate.value) {
                alert('End date cannot be before start date.');
                endDate.value = startDate.value;
            }
        });
        
        startDate.addEventListener('change', function() {
            if (endDate.value && endDate.value < startDate.value) {
                endDate.value = startDate.value;
            }
        });
    }
    
    // Toggle payment method forms
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const cardForm = document.getElementById('card-payment-form');
    
    if (paymentRadios.length > 0) {
        function togglePaymentForm() {
            const selected = document.querySelector('input[name="payment_method"]:checked').value;
            if (cardForm) cardForm.style.display = selected === 'card' ? 'block' : 'none';
        }
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', togglePaymentForm);
        });
        
        togglePaymentForm();
    }
    
    // Step 2 form validation
    const step2Form = document.getElementById('mc-step2-form');
    if (step2Form) {
        step2Form.addEventListener('submit', function(e) {
            const apptTime = document.getElementById('appointment_time').value;
            if (!apptTime) {
                e.preventDefault();
                alert('Please select a consultation time slot.');
                return false;
            }
            return true;
        });
    }
});

// Load time slots for doctor availability
async function loadDoctorSlots() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput = document.getElementById('appointment_time');
    
    if (!doctorSelect || !dateInput || !timeSlotsContainer) return;
    
    const doctorId = doctorSelect.value;
    const date = dateInput.value;
    
    if (!doctorId || !date) {
        timeSlotsContainer.innerHTML = '<p class="mc-text-muted">Please select a doctor and date first</p>';
        return;
    }
    
    timeSlotsContainer.innerHTML = '<p class="mc-text-muted"><i class="fas fa-spinner fa-spin"></i> Loading available times...</p>';
    
    try {
        const response = await fetch(`ajax/get-time-slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`);
        const data = await response.json();
        
        if (data.success && data.slots && data.slots.length > 0) {
            let html = '<div class="mc-time-slots">';
            data.slots.forEach(slot => {
                html += `<div class="mc-time-slot" data-time="${slot.value}">${slot.display}</div>`;
            });
            html += '</div>';
            timeSlotsContainer.innerHTML = html;
            
            document.querySelectorAll('.mc-time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.mc-time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    if (timeInput) timeInput.value = this.getAttribute('data-time');
                });
            });
            
            // Restore previously selected time
            if (timeInput && timeInput.value) {
                document.querySelectorAll('.mc-time-slot').forEach(slot => {
                    if (slot.getAttribute('data-time') === timeInput.value) {
                        slot.classList.add('selected');
                    }
                });
            }
        } else {
            timeSlotsContainer.innerHTML = '<p class="mc-text-muted">No available time slots for this date. Please try another date.</p>';
            if (timeInput) timeInput.value = '';
        }
    } catch (error) {
        timeSlotsContainer.innerHTML = '<p class="mc-text-muted">Error loading time slots. Please try again.</p>';
    }
}
</script>

<style>

.mc-approval-note {
    margin: 25px 0;
    padding: 20px;
    background: #fef3c7;
    border-radius: 12px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    text-align: left;
}
.mc-approval-note i {
    font-size: 24px;
    color: #f59e0b;
}
.mc-approval-note p {
    margin: 0;
    color: #92400e;
}
.mc-cash-payment-info {
    margin-top: 20px;
}
.mc-info-box {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}
.mc-info-box i {
    font-size: 24px;
    color: #f59e0b;
}
.mc-info-box p {
    margin: 0 0 10px 0;
    color: #92400e;
}
.mc-info-box p:last-child {
    margin-bottom: 0;
}
.mc-status-paid {
    color: #10b981;
    font-weight: 600;
}
.text-success {
    color: #10b981 !important;
}
.mc-payment-methods {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
}
.mc-payment-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    flex: 1;
    min-width: 150px;
}
.mc-payment-option:hover {
    border-color: #10b981;
    background: #f0fdf4;
}
.mc-payment-option input[type="radio"] {
    accent-color: #10b981;
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.mc-payment-option i {
    font-size: 22px;
    color: #10b981;
}

.mc-section-divider {
    margin: 30px 0 20px;
    padding: 20px;
    background: #eff6ff;
    border-radius: 12px;
    border-left: 4px solid #3b82f6;
}

.mc-section-divider h4 {
    color: #1e40af;
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mc-section-note {
    color: #475569;
    margin: 0;
    font-size: 14px;
}

.mc-time-slots-container {
    min-height: 100px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 16px;
    border: 2px dashed #cbd5e1;
    margin-top: 10px;
}

.mc-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
}

.mc-time-slot {
    padding: 12px 8px;
    background: white;
    border: 2px solid #cbd5e1;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 15px;
    font-weight: 600;
    color: #334155;
}

.mc-time-slot:hover {
    background: #1e3a5f;
    color: white;
    border-color: #1e3a5f;
    transform: scale(1.05);
}

.mc-time-slot.selected {
    background: #1e3a5f;
    color: white;
    border-color: #1e3a5f;
    box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
}

.mc-text-muted {
    color: #64748b;
    text-align: center;
    padding: 20px;
}

.mc-text-success {
    color: #10b981 !important;
}
</style>

<?php include 'includes/footer.php'; ?>