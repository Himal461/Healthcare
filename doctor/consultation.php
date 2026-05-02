<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Patient Consultation";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);

if (!$patientId) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: patients.php");
    exit();
}

// Get doctor details
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.consultationFee,
           CONCAT(u.firstName,' ',u.lastName) AS doctorName,
           u.email as doctorEmail
    FROM doctors d
    JOIN staff s ON d.staffId=s.staffId
    JOIN users u ON s.userId=u.userId
    WHERE s.userId=?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: dashboard.php");
    exit();
}

$doctorId = $doctor['doctorId'];
$consultationFee = (float)$doctor['consultationFee'];

// Get patient details
$stmt = $pdo->prepare("
    SELECT p.*, u.userId AS patientUserId, u.firstName, u.lastName, u.email, u.phoneNumber
    FROM patients p
    JOIN users u ON p.userId=u.userId
    WHERE p.patientId=? AND u.role='patient'
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient not found.";
    header("Location: patients.php");
    exit();
}

// Get appointment details if appointmentId provided
$appointment = null;
if ($appointmentId) {
    $stmt = $pdo->prepare("
        SELECT * FROM appointments 
        WHERE appointmentId = ? AND patientId = ? AND doctorId = ?
    ");
    $stmt->execute([$appointmentId, $patientId, $doctorId]);
    $appointment = $stmt->fetch();
}

$errorMessage = null;
$successMessage = null;
$newBillId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $diagnosis = trim($_POST['diagnosis']);
        if ($diagnosis === '') throw new Exception("Diagnosis required");

        // Insert medical record
        $stmt = $pdo->prepare("
            INSERT INTO medical_records
            (patientId, doctorId, appointmentId, diagnosis, treatmentNotes, followUpDate, creationDate)
            VALUES (?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([
            $patientId,
            $doctorId,
            $appointmentId ?: null,
            $diagnosis,
            $_POST['treatment_notes'] ?? '',
            $_POST['follow_up_date'] ?: null
        ]);
        $recordId = $pdo->lastInsertId();

        // Insert prescriptions
        if (!empty($_POST['medication_name'])) {
            $stmtPres = $pdo->prepare("
                INSERT INTO prescriptions
                (recordId, medicationName, dosage, frequency, startDate, instructions, prescribedBy, status, createdAt)
                VALUES (?,?,?,?,?,?,?, 'active', NOW())
            ");
            foreach ($_POST['medication_name'] as $i => $name) {
                $name = trim($name);
                if ($name === '') continue;
                $stmtPres->execute([
                    $recordId,
                    $name,
                    $_POST['dosage'][$i] ?? '',
                    $_POST['frequency'][$i] ?? '',
                    $_POST['start_date'][$i] ?? date('Y-m-d'),
                    $_POST['instructions'][$i] ?? '',
                    $doctorId
                ]);
            }
        }

        // Calculate bill
        $additionalTotal = 0;
        $charges = [];
        if (!empty($_POST['charge_name']) && !empty($_POST['charge_amount'])) {
            foreach ($_POST['charge_name'] as $i => $name) {
                $name = trim($name);
                $amt = (float)($_POST['charge_amount'][$i] ?? 0);
                if ($amt > 0 && $name !== '') {
                    $additionalTotal += $amt;
                    $charges[] = [$name, $amt];
                }
            }
        }

        $subtotal = $consultationFee + $additionalTotal;
        $service = round($subtotal * 0.03, 2);
        $gst = round($subtotal * 0.13, 2);
        $total = round($subtotal + $service + $gst, 2);

        // Insert bill
        $stmtBill = $pdo->prepare("
            INSERT INTO bills
            (patientId, appointmentId, recordId, consultationFee, additionalCharges, serviceCharge, gst, totalAmount, status, generatedAt)
            VALUES (?,?,?,?,?,?,?,?, 'unpaid', NOW())
        ");
        $stmtBill->execute([
            $patientId, $appointmentId ?: null, $recordId,
            $consultationFee, $additionalTotal, $service, $gst, $total
        ]);
        $newBillId = $pdo->lastInsertId();

        // Insert additional charges
        if (!empty($charges)) {
            $stmtC = $pdo->prepare("INSERT INTO bill_charges (billId, chargeName, amount) VALUES (?,?,?)");
            foreach ($charges as $c) {
                $stmtC->execute([$newBillId, $c[0], $c[1]]);
            }
        }

        // Mark appointment as COMPLETED
        if ($appointmentId) {
            $stmtUpdate = $pdo->prepare("
                UPDATE appointments 
                SET status = 'completed', 
                    updatedAt = NOW() 
                WHERE appointmentId = ?
            ");
            $stmtUpdate->execute([$appointmentId]);
        }

        $pdo->commit();
        
        $formattedDate = date('F j, Y');
        $appointmentDateTime = $appointment ? date('F j, Y g:i A', strtotime($appointment['dateTime'])) : $formattedDate;
        
        // ========== SEND EMAIL TO PATIENT ==========
        $patientSubject = "Consultation Completed - " . SITE_NAME;
        $patientMessage = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #0d9488; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .info-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .button { display: inline-block; background: #0d9488; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 10px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Consultation Completed</h2>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>{$patient['firstName']} {$patient['lastName']}</strong>,</p>
                        <p>Your consultation with Dr. {$doctor['doctorName']} has been completed.</p>
                        <div class='info-box'>
                            <p><strong>Date:</strong> {$appointmentDateTime}</p>
                            <p><strong>Diagnosis:</strong> " . substr($diagnosis, 0, 100) . (strlen($diagnosis) > 100 ? '...' : '') . "</p>
                            <p><strong>Bill Amount:</strong> $" . number_format($total, 2) . "</p>
                        </div>
                        <p>You can view your medical records and pay your bill online.</p>
                        <a href='" . SITE_URL . "/patient/my-medical-records.php' class='button'>View Medical Records</a>
                        <a href='" . SITE_URL . "/patient/view-bill.php?bill_id={$newBillId}' class='button'>View & Pay Bill</a>
                        <p style='margin-top: 30px;'>Thank you for choosing " . SITE_NAME . ".</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        sendEmail($patient['email'], $patientSubject, $patientMessage);
        
        // ========== CREATE IN-APP NOTIFICATION FOR PATIENT ==========
        createNotification(
            $patient['patientUserId'],
            'appointment',
            'Consultation Completed',
            "Your consultation with Dr. {$doctor['doctorName']} has been completed. View your medical record and bill.",
            "../patient/my-medical-records.php"
        );
        
        // Create bill notification for patient
        createNotification(
            $patient['patientUserId'],
            'billing',
            'New Bill Generated',
            "A new bill of $" . number_format($total, 2) . " has been generated for your consultation.",
            "../patient/view-bill.php?bill_id=" . $newBillId
        );
        
        $successMessage = "Consultation saved! Bill #$newBillId generated. Patient notified.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMessage = $e->getMessage();
        error_log("Consultation error: " . $e->getMessage());
    }
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-stethoscope"></i> Patient Consultation</h1>
            <p>Recording consultation for <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong></p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
            <?php if ($newBillId): ?>
                <div style="margin-top: 10px; display: flex; gap: 10px;">
                    <a href="view-bill.php?bill_id=<?php echo $newBillId; ?>" class="doctor-btn doctor-btn-primary doctor-btn-sm">
                        <i class="fas fa-receipt"></i> View Bill
                    </a>
                    <a href="patients.php?view=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                        <i class="fas fa-user"></i> Back to Patient
                    </a>
                    <a href="appointments.php" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                        <i class="fas fa-calendar-alt"></i> View Schedule
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <?php if (!$successMessage): ?>
        <!-- Patient Information Card -->
        <div class="doctor-patient-info-card">
            <div class="doctor-patient-info-header">
                <i class="fas fa-user-circle"></i>
                <h3>Patient Information</h3>
            </div>
            <div class="doctor-patient-info-grid">
                <div class="doctor-info-group">
                    <h4><i class="fas fa-user"></i> Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ?: 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
                </div>
                <div class="doctor-info-group">
                    <h4><i class="fas fa-notes-medical"></i> Medical Information</h4>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['knownAllergies'] ?: 'None'); ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                </div>
                <div class="doctor-info-group">
                    <h4><i class="fas fa-history"></i> Medical History</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="medical-records.php?patient_id=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                            <i class="fas fa-notes-medical"></i> View Medical Records
                        </a>
                        <a href="prescriptions.php?patient_id=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-outline doctor-btn-sm">
                            <i class="fas fa-prescription"></i> View Prescriptions
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultation Form Card -->
        <div class="doctor-consultation-form-card">
            <form method="POST" id="consultationForm">
                <div class="doctor-form-section">
                    <div class="doctor-form-group">
                        <label>Diagnosis <span class="required">*</span></label>
                        <textarea name="diagnosis" class="doctor-form-control" rows="4" placeholder="Enter primary diagnosis..." required></textarea>
                    </div>
                    
                    <div class="doctor-form-group">
                        <label>Treatment Notes / Plan</label>
                        <textarea name="treatment_notes" class="doctor-form-control" rows="4" placeholder="Treatment plan, recommendations, lifestyle advice..."></textarea>
                    </div>

                    <div class="doctor-form-group">
                        <label>Follow-up Date (Optional)</label>
                        <input type="date" name="follow_up_date" class="doctor-form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <!-- Prescriptions Section -->
                <div class="doctor-section-divider">
                    <h4><i class="fas fa-prescription"></i> Prescription Details</h4>
                    <button type="button" class="doctor-btn doctor-btn-outline doctor-btn-sm" onclick="addMedication()">
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                </div>

                <div id="medications">
                    <div class="doctor-med-row">
                        <div class="doctor-med-fields">
                            <input name="medication_name[]" class="doctor-form-control" placeholder="Medication Name">
                            <input name="dosage[]" class="doctor-form-control" placeholder="Dosage">
                            <input name="frequency[]" class="doctor-form-control" placeholder="Frequency">
                            <input name="start_date[]" type="date" class="doctor-form-control" value="<?php echo date('Y-m-d'); ?>">
                            <input name="instructions[]" class="doctor-form-control" placeholder="Instructions">
                        </div>
                        <button type="button" class="doctor-btn-delete" onclick="removeRow(this)" title="Remove medication">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Additional Charges Section -->
                <div class="doctor-section-divider">
                    <h4><i class="fas fa-dollar-sign"></i> Additional Charges</h4>
                    <button type="button" class="doctor-btn doctor-btn-outline doctor-btn-sm" onclick="addCharge()">
                        <i class="fas fa-plus"></i> Add Charge
                    </button>
                </div>

                <div id="charges">
                    <div class="doctor-charge-row">
                        <div class="doctor-charge-fields">
                            <input name="charge_name[]" class="doctor-form-control" placeholder="Charge Name" oninput="updateBillSummary()">
                            <input name="charge_amount[]" type="number" step="0.01" class="doctor-form-control" placeholder="Amount ($)" oninput="updateBillSummary()">
                        </div>
                        <button type="button" class="doctor-btn-delete" onclick="removeRow(this)" title="Remove charge">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Bill Summary -->
                <div class="doctor-bill-summary">
                    <h4><i class="fas fa-receipt"></i> Bill Summary</h4>
                    <table class="doctor-bill-table">
                        <tr>
                            <td>Consultation Fee:</td>
                            <td>$<?php echo number_format($consultationFee,2); ?></td>
                        </tr>
                        <tbody id="additional-charges-list"></tbody>
                        <tr>
                            <td><strong>Subtotal:</strong></td>
                            <td><strong>$<span id="subtotal"><?php echo number_format($consultationFee,2); ?></span></strong></td>
                        </tr>
                        <tr class="tax-row">
                            <td>Service Charge (3%):</td>
                            <td>$<span id="service">0.00</span></td>
                        </tr>
                        <tr class="tax-row">
                            <td>GST (13%):</td>
                            <td>$<span id="gst">0.00</span></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total Amount:</strong></td>
                            <td><strong>$<span id="total"><?php echo number_format($consultationFee,2); ?></span></strong></td>
                        </tr>
                    </table>
                    <small class="doctor-text-muted">Bill will be automatically generated when you save.</small>
                    <input type="hidden" id="consultation_fee_hidden" value="<?php echo $consultationFee; ?>">
                </div>

                <div class="doctor-form-actions" style="display: flex; gap: 18px; margin-top: 30px; justify-content: center;">
                    <button type="submit" name="save_consultation" class="doctor-btn doctor-btn-primary" style="min-width: 220px;">
                        <i class="fas fa-save"></i> Complete Consultation & Generate Bill
                    </button>
                    <a href="patients.php?view=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>