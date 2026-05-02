<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "View Consultation - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$appointmentId = (int)($_GET['appointment_id'] ?? 0);

if (!$appointmentId) { 
    $_SESSION['error'] = "Appointment ID required."; 
    header("Location: patients.php"); 
    exit(); 
}

$stmt = $pdo->prepare("SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as doctorName FROM doctors d JOIN staff s ON d.staffId = s.staffId JOIN users u ON s.userId = u.userId WHERE s.userId = ?");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();
$doctorId = $doctor['doctorId'];

$stmt = $pdo->prepare("
    SELECT mr.*, CONCAT(u.firstName, ' ', u.lastName) as patientName, u.email as patientEmail, u.phoneNumber as patientPhone,
           p.dateOfBirth, p.bloodType, p.knownAllergies, a.dateTime as appointmentDate, a.status as appointmentStatus
    FROM medical_records mr JOIN patients p ON mr.patientId = p.patientId JOIN users u ON p.userId = u.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    WHERE mr.appointmentId = ? AND mr.doctorId = ? ORDER BY mr.creationDate DESC LIMIT 1
");
$stmt->execute([$appointmentId, $doctorId]);
$consultation = $stmt->fetch();

if (!$consultation) { 
    $_SESSION['error'] = "Consultation not found."; 
    header("Location: patients.php"); 
    exit(); 
}

$patientId = $consultation['patientId'];
$recordId = $consultation['recordId'];

$stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE recordId = ?");
$stmt->execute([$recordId]);
$prescriptions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM bills WHERE recordId = ? LIMIT 1");
$stmt->execute([$recordId]);
$bill = $stmt->fetch();
$additionalCharges = [];
if ($bill) { 
    $stmt = $pdo->prepare("SELECT * FROM bill_charges WHERE billId = ?"); 
    $stmt->execute([$bill['billId']]); 
    $additionalCharges = $stmt->fetchAll(); 
}
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> Consultation Details</h1>
            <p><?php echo date('F j, Y', strtotime($consultation['creationDate'])); ?></p>
        </div>
        <div class="header-actions">
            <a href="patients.php?view=<?php echo $patientId; ?>" class="doctor-btn doctor-btn-outline">Back</a>
            <?php if ($bill): ?>
                <a href="view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="doctor-btn doctor-btn-primary">View Bill</a>
            <?php endif; ?>
            <button onclick="window.print()" class="doctor-btn doctor-btn-info">Print</button>
        </div>
    </div>

    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
        </div>
        <div class="doctor-card-body">
            <div class="doctor-patient-info-grid">
                <div>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($consultation['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo $consultation['patientEmail']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $consultation['patientPhone']; ?></p>
                </div>
                <div>
                    <p><strong>Allergies:</strong> <?php echo $consultation['knownAllergies'] ?: 'None'; ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $consultation['bloodType'] ?: 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-stethoscope"></i> Consultation Notes</h3>
        </div>
        <div class="doctor-card-body">
            <h4>Diagnosis</h4>
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                <?php echo nl2br(htmlspecialchars($consultation['diagnosis'])); ?>
            </div>
            <?php if ($consultation['treatmentNotes']): ?>
                <h4>Treatment Plan</h4>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                    <?php echo nl2br(htmlspecialchars($consultation['treatmentNotes'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($prescriptions)): ?>
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
            </div>
            <div class="doctor-card-body">
                <?php foreach ($prescriptions as $p): ?>
                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #2563eb;">
                        <strong><?php echo htmlspecialchars($p['medicationName']); ?></strong> - <?php echo $p['dosage']; ?>, <?php echo $p['frequency']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($bill): ?>
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-receipt"></i> Bill Summary</h3>
            </div>
            <div class="doctor-card-body">
                <table class="doctor-bill-table">
                    <tr><td>Consultation Fee:</td><td>$<?php echo number_format($bill['consultationFee'], 2); ?></td></tr>
                    <?php foreach ($additionalCharges as $c): ?>
                        <tr><td><?php echo $c['chargeName']; ?>:</td><td>$<?php echo number_format($c['amount'], 2); ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td>Service Charge (3%):</td><td>$<?php echo number_format($bill['serviceCharge'], 2); ?></td></tr>
                    <tr><td>GST (13%):</td><td>$<?php echo number_format($bill['gst'], 2); ?></td></tr>
                    <tr class="total-row"><td><strong>Total:</strong></td><td><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td></tr>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>