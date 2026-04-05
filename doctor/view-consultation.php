<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "View Consultation - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$appointmentId = (int)($_GET['appointment_id'] ?? 0);

if (!$appointmentId) {
    $_SESSION['error'] = "Appointment ID is required.";
    header("Location: patients.php");
    exit();
}

// Get doctor details
$stmt = $pdo->prepare("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: patients.php");
    exit();
}

$doctorId = $doctor['doctorId'];

// Get consultation details
$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           p.dateOfBirth,
           p.bloodType,
           p.knownAllergies,
           p.insuranceProvider,
           a.dateTime as appointmentDate,
           a.status as appointmentStatus
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    WHERE mr.appointmentId = ? AND mr.doctorId = ?
    ORDER BY mr.creationDate DESC
    LIMIT 1
");
$stmt->execute([$appointmentId, $doctorId]);
$consultation = $stmt->fetch();

if (!$consultation) {
    $_SESSION['error'] = "Consultation not found for this appointment.";
    header("Location: patients.php");
    exit();
}

$patientId = $consultation['patientId'];
$recordId = $consultation['recordId'];

// Get prescriptions for this consultation
$stmt = $pdo->prepare("
    SELECT * FROM prescriptions 
    WHERE recordId = ?
    ORDER BY createdAt DESC
");
$stmt->execute([$recordId]);
$prescriptions = $stmt->fetchAll();

// Get bill for this consultation
$stmt = $pdo->prepare("
    SELECT * FROM bills 
    WHERE recordId = ?
    LIMIT 1
");
$stmt->execute([$recordId]);
$bill = $stmt->fetch();

// Get additional charges if bill exists
$additionalCharges = [];
if ($bill) {
    $stmt = $pdo->prepare("
        SELECT * FROM bill_charges 
        WHERE billId = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$bill['billId']]);
    $additionalCharges = $stmt->fetchAll();
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>Consultation Details</h1>
            <p>Viewing consultation from <strong><?php echo date('F j, Y', strtotime($consultation['creationDate'])); ?></strong></p>
        </div>
        <div class="header-actions">
            <a href="patients.php?view=<?php echo $patientId; ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Patient
            </a>
            <?php if ($bill): ?>
            <a href="view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="btn btn-primary">
                <i class="fas fa-receipt"></i> View Bill
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-info">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
        </div>
        <div class="card-body">
            <div class="patient-info-grid">
                <div class="info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($consultation['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($consultation['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($consultation['patientPhone']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $consultation['dateOfBirth'] ?: 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($consultation['dateOfBirth']); ?></p>
                </div>
                <div class="info-group">
                    <h4>Medical Information</h4>
                    <p><strong>Allergies:</strong> <?php echo $consultation['knownAllergies'] ?: 'None'; ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $consultation['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Insurance:</strong> <?php echo $consultation['insuranceProvider'] ?: 'N/A'; ?></p>
                </div>
                <div class="info-group">
                    <h4>Appointment Details</h4>
                    <p><strong>Date & Time:</strong> <?php echo date('F j, Y g:i A', strtotime($consultation['appointmentDate'])); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $consultation['appointmentStatus']; ?>">
                            <?php echo ucfirst($consultation['appointmentStatus']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Notes -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-notes-medical"></i> Consultation Notes</h3>
        </div>
        <div class="card-body">
            <div class="consultation-section">
                <h4>Diagnosis</h4>
                <div class="diagnosis-box">
                    <?php echo nl2br(htmlspecialchars($consultation['diagnosis'])); ?>
                </div>
            </div>

            <?php if ($consultation['treatmentNotes']): ?>
            <div class="consultation-section">
                <h4>Treatment Plan</h4>
                <div class="treatment-box">
                    <?php echo nl2br(htmlspecialchars($consultation['treatmentNotes'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($consultation['followUpDate']): ?>
            <div class="consultation-section">
                <h4>Follow-up Date</h4>
                <div class="followup-box">
                    <i class="fas fa-calendar-check"></i>
                    <strong><?php echo date('F j, Y', strtotime($consultation['followUpDate'])); ?></strong>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prescriptions -->
    <?php if (!empty($prescriptions)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
        </div>
        <div class="card-body">
            <div class="prescriptions-list">
                <?php foreach ($prescriptions as $prescription): ?>
                <div class="prescription-item">
                    <div class="prescription-header">
                        <div class="medication-name">
                            <i class="fas fa-capsules"></i>
                            <strong><?php echo htmlspecialchars($prescription['medicationName']); ?></strong>
                        </div>
                        <div class="prescription-status">
                            <span class="status-badge status-<?php echo $prescription['status']; ?>">
                                <?php echo ucfirst($prescription['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="prescription-details">
                        <div class="detail-row">
                            <span class="label">Dosage:</span>
                            <span><?php echo htmlspecialchars($prescription['dosage']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Frequency:</span>
                            <span><?php echo htmlspecialchars($prescription['frequency']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Duration:</span>
                            <span>
                                <?php echo date('M j, Y', strtotime($prescription['startDate'])); ?>
                                <?php if ($prescription['endDate']): ?>
                                    - <?php echo date('M j, Y', strtotime($prescription['endDate'])); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($prescription['instructions']): ?>
                        <div class="detail-row">
                            <span class="label">Instructions:</span>
                            <span><?php echo htmlspecialchars($prescription['instructions']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bill Summary -->
    <?php if ($bill): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-receipt"></i> Bill Summary</h3>
        </div>
        <div class="card-body">
            <div class="bill-summary">
                <table class="bill-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-right">Amount ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Consultation Fee</td>
                            <td class="text-right">$<?php echo number_format($bill['consultationFee'], 2); ?></td>
                        </tr>
                        <?php foreach ($additionalCharges as $charge): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($charge['chargeName']); ?></td>
                            <td class="text-right">$<?php echo number_format($charge['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="subtotal-row">
                            <td><strong>Subtotal</strong></td>
                            <td class="text-right"><strong>$<?php echo number_format($bill['consultationFee'] + $bill['additionalCharges'], 2); ?></strong></td>
                        </tr>
                        <tr class="tax-row">
                            <td>Service Charge (3%)</td>
                            <td class="text-right">$<?php echo number_format($bill['serviceCharge'], 2); ?></td>
                        </tr>
                        <tr class="tax-row">
                            <td>GST (13%)</td>
                            <td class="text-right">$<?php echo number_format($bill['gst'], 2); ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total Amount</strong></td>
                            <td class="text-right"><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                <div class="payment-info">
                    <p><strong>Bill Status:</strong> 
                        <span class="status-badge status-<?php echo $bill['status']; ?>">
                            <?php echo ucfirst($bill['status']); ?>
                        </span>
                    </p>
                    <p><strong>Generated On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['generatedAt'])); ?></p>
                    <?php if ($bill['paidAt']): ?>
                    <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.dashboard {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-header h1 {
    margin: 0;
    color: #333;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: #666;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    color: #495057;
    font-size: 18px;
}

.card-header i {
    margin-right: 8px;
    color: #1a75bc;
}

.card-body {
    padding: 20px;
}

.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.info-group h4 {
    color: #1a75bc;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e9ecef;
    font-size: 16px;
}

.info-group p {
    margin: 8px 0;
    line-height: 1.5;
}

.consultation-section {
    margin-bottom: 25px;
}

.consultation-section h4 {
    color: #495057;
    margin-bottom: 10px;
    font-size: 16px;
}

.diagnosis-box,
.treatment-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
}

.followup-box {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 8px;
    color: #004085;
}

.followup-box i {
    margin-right: 8px;
}

.prescriptions-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 15px;
}

.prescription-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid #1a75bc;
}

.prescription-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
}

.medication-name {
    font-size: 16px;
}

.medication-name i {
    margin-right: 8px;
    color: #1a75bc;
}

.prescription-details {
    margin-top: 10px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 14px;
}

.detail-row .label {
    font-weight: 600;
    color: #495057;
}

.bill-summary {
    margin-top: 10px;
}

.bill-table {
    width: 100%;
    max-width: 500px;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.bill-table th,
.bill-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.bill-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.text-right {
    text-align: right;
}

.subtotal-row {
    border-top: 1px solid #dee2e6;
}

.total-row {
    border-top: 2px solid #1a75bc;
    font-size: 16px;
}

.tax-row {
    color: #666;
}

.payment-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.payment-info p {
    margin: 8px 0;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-scheduled,
.status-confirmed {
    background: #e3f2fd;
    color: #1976d2;
}

.status-in-progress {
    background: #fff3e0;
    color: #f57c00;
}

.status-completed {
    background: #e0f2fe;
    color: #0284c7;
}

.status-cancelled {
    background: #ffebee;
    color: #d32f2f;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-paid {
    background: #d4edda;
    color: #155724;
}

.status-unpaid {
    background: #fff3cd;
    color: #856404;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: #1a75bc;
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary:hover {
    background: #0e5a92;
}

.btn-info {
    background: #17a2b8;
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-info:hover {
    background: #138496;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

@media print {
    .dashboard-header,
    .header-actions,
    .btn,
    .card-header .btn {
        display: none;
    }
    
    .card {
        box-shadow: none;
        page-break-inside: avoid;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .patient-info-grid {
        grid-template-columns: 1fr;
    }
    
    .prescriptions-list {
        grid-template-columns: 1fr;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .bill-table {
        width: 100%;
    }
}
</style>

<?php include '../includes/footer.php'; ?>