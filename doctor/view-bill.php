<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "View Bill - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$billId = (int)($_GET['bill_id'] ?? 0);
$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);

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

// Get bill details
if ($billId > 0) {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.email as patientEmail,
               u.phoneNumber as patientPhone,
               p.dateOfBirth,
               p.bloodType,
               p.knownAllergies,
               p.insuranceProvider,
               mr.diagnosis,
               mr.creationDate as consultationDate,
               a.dateTime as appointmentDate
        FROM bills b
        JOIN patients p ON b.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN medical_records mr ON b.recordId = mr.recordId
        LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
        WHERE b.billId = ?
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        $_SESSION['error'] = "Bill not found.";
        header("Location: patients.php");
        exit();
    }
    
    $patientId = $bill['patientId'];
    
    // Get additional charges
    $stmt = $pdo->prepare("
        SELECT * FROM bill_charges 
        WHERE billId = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$billId]);
    $additionalCharges = $stmt->fetchAll();
    
    // Get all bills for this patient (previous bills)
    $stmt = $pdo->prepare("
        SELECT billId, totalAmount, status, generatedAt 
        FROM bills 
        WHERE patientId = ? 
        ORDER BY generatedAt DESC
        LIMIT 10
    ");
    $stmt->execute([$patientId]);
    $previousBills = $stmt->fetchAll();
    
} elseif ($appointmentId > 0) {
    // Get bill by appointment
    $stmt = $pdo->prepare("
        SELECT b.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.email as patientEmail,
               u.phoneNumber as patientPhone,
               p.dateOfBirth,
               p.bloodType,
               p.knownAllergies,
               p.insuranceProvider,
               mr.diagnosis,
               mr.creationDate as consultationDate,
               a.dateTime as appointmentDate
        FROM bills b
        JOIN patients p ON b.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN medical_records mr ON b.recordId = mr.recordId
        LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
        WHERE b.appointmentId = ?
    ");
    $stmt->execute([$appointmentId]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        $_SESSION['error'] = "Bill not found for this appointment.";
        header("Location: patients.php");
        exit();
    }
    
    $billId = $bill['billId'];
    $patientId = $bill['patientId'];
    
    // Get additional charges
    $stmt = $pdo->prepare("
        SELECT * FROM bill_charges 
        WHERE billId = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$billId]);
    $additionalCharges = $stmt->fetchAll();
    
    // Get all bills for this patient
    $stmt = $pdo->prepare("
        SELECT billId, totalAmount, status, generatedAt 
        FROM bills 
        WHERE patientId = ? 
        ORDER BY generatedAt DESC
        LIMIT 10
    ");
    $stmt->execute([$patientId]);
    $previousBills = $stmt->fetchAll();
    
} elseif ($patientId > 0) {
    // Get latest bill for patient
    $stmt = $pdo->prepare("
        SELECT b.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.email as patientEmail,
               u.phoneNumber as patientPhone,
               p.dateOfBirth,
               p.bloodType,
               p.knownAllergies,
               p.insuranceProvider,
               mr.diagnosis,
               mr.creationDate as consultationDate,
               a.dateTime as appointmentDate
        FROM bills b
        JOIN patients p ON b.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN medical_records mr ON b.recordId = mr.recordId
        LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
        WHERE b.patientId = ?
        ORDER BY b.generatedAt DESC
        LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        $_SESSION['error'] = "No bills found for this patient.";
        header("Location: patients.php");
        exit();
    }
    
    $billId = $bill['billId'];
    
    // Get additional charges
    $stmt = $pdo->prepare("
        SELECT * FROM bill_charges 
        WHERE billId = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$billId]);
    $additionalCharges = $stmt->fetchAll();
    
    // Get all bills for this patient
    $stmt = $pdo->prepare("
        SELECT billId, totalAmount, status, generatedAt 
        FROM bills 
        WHERE patientId = ? 
        ORDER BY generatedAt DESC
        LIMIT 10
    ");
    $stmt->execute([$patientId]);
    $previousBills = $stmt->fetchAll();
    
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: patients.php");
    exit();
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>Bill Details</h1>
            <p>Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="header-actions">
            <a href="patients.php?view=<?php echo $patientId; ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Patient
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Bill
            </button>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Consultation saved successfully! Bill generated.
        </div>
    <?php endif; ?>

    <div class="bill-container">
        <!-- Bill Card -->
        <div class="card bill-card">
            <div class="bill-header">
                <<div class="hospital-info">
    <h2>HealthManagement System</h2>
    <p>Fussel Lane, Gungahlin, ACT 2912, Australia</p>
    <p>Phone: <a href="tel:+614383473483">+61 438 347 3483</a> | Emergency: <a href="tel:+614552627">+61 455 2627</a></p>
    <p>Email: <a href="mailto:himalkumarkari@gmail.com">himalkumarkari@gmail.com</a> | <a href="mailto:abinashcarkee@gmail.com">abinashcarkee@gmail.com</a></p>
</div>
                <div class="bill-info">
                    <div class="bill-number">
                        <strong>Bill Number:</strong> #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?>
                    </div>
                    <div class="bill-date">
                        <strong>Date:</strong> <?php echo date('F j, Y', strtotime($bill['generatedAt'])); ?>
                    </div>
                    <div class="bill-status">
                        <strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $bill['status']; ?>">
                            <?php echo ucfirst($bill['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="patient-details">
                <h3>Patient Information</h3>
                <div class="patient-info-grid">
                    <div class="info-row">
                        <span class="label">Patient Name:</span>
                        <span class="value"><?php echo htmlspecialchars($bill['patientName']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($bill['patientEmail']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone:</span>
                        <span class="value"><?php echo htmlspecialchars($bill['patientPhone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date of Birth:</span>
                        <span class="value"><?php echo $bill['dateOfBirth'] ?: 'N/A'; ?> (Age: <?php echo calculateAge($bill['dateOfBirth']); ?>)</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Blood Type:</span>
                        <span class="value"><?php echo $bill['bloodType'] ?: 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Insurance:</span>
                        <span class="value"><?php echo $bill['insuranceProvider'] ?: 'N/A'; ?></span>
                    </div>
                </div>
            </div>

            <?php if ($bill['diagnosis']): ?>
            <div class="consultation-details">
                <h3>Consultation Details</h3>
                <div class="info-row">
                    <span class="label">Diagnosis:</span>
                    <span class="value"><?php echo htmlspecialchars($bill['diagnosis']); ?></span>
                </div>
                <?php if ($bill['appointmentDate']): ?>
                <div class="info-row">
                    <span class="label">Appointment Date:</span>
                    <span class="value"><?php echo date('F j, Y g:i A', strtotime($bill['appointmentDate'])); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label">Consultation Date:</span>
                    <span class="value"><?php echo date('F j, Y', strtotime($bill['consultationDate'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="bill-details">
                <h3>Bill Summary</h3>
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
                        <tr>
                            <td>Service Charge (3%)</td>
                            <td class="text-right">$<?php echo number_format($bill['serviceCharge'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>GST (13%)</td>
                            <td class="text-right">$<?php echo number_format($bill['gst'], 2); ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total Amount</strong></td>
                            <td class="text-right"><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="payment-section">
                <h3>Payment Information</h3>
                <?php if ($bill['status'] == 'paid' && $bill['paidAt']): ?>
                    <div class="payment-details">
                        <p><strong>Payment Status:</strong> <span class="status-badge status-paid">Paid</span></p>
                        <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                    </div>
                <?php else: ?>
                    <div class="payment-details">
                        <p><strong>Payment Status:</strong> <span class="status-badge status-unpaid">Unpaid</span></p>
                        <p>Please make payment at the hospital cash counter or via online payment portal.</p>
                        <?php if ($doctorId): ?>
                        <button onclick="markAsPaid(<?php echo $bill['billId']; ?>)" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Mark as Paid
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bill-footer">
                <p>Thank you for choosing HealthManagement System. We wish you good health!</p>
                <p class="signature">Authorized Signature</p>
                <p class="signature">Dr. <?php echo htmlspecialchars($doctor['doctorName']); ?></p>
            </div>
        </div>

        <!-- Previous Bills Section -->
        <?php if (!empty($previousBills) && count($previousBills) > 1): ?>
        <div class="card previous-bills">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Previous Bills</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previousBills as $prevBill): ?>
                                <?php if ($prevBill['billId'] != $billId): ?>
                                <tr>
                                    <td>#<?php echo str_pad($prevBill['billId'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($prevBill['generatedAt'])); ?></td>
                                    <td>$<?php echo number_format($prevBill['totalAmount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $prevBill['status']; ?>">
                                            <?php echo ucfirst($prevBill['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-bill.php?bill_id=<?php echo $prevBill['billId']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function markAsPaid(billId) {
    if (confirm('Are you sure you want to mark this bill as paid?')) {
        window.location.href = 'update-bill-status.php?bill_id=' + billId + '&status=paid';
    }
}

function markAsPaid(billId) {
    if (confirm('Are you sure you want to mark this bill as paid? This action will notify the patient.')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;
        window.location.href = 'update-bill-status.php?bill_id=' + billId + '&status=paid';
    }
}

</script>

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

.bill-container {
    margin-top: 20px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.bill-card {
    padding: 30px;
}

.bill-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e9ecef;
    flex-wrap: wrap;
    gap: 20px;
}

.hospital-info h2 {
    margin: 0 0 10px 0;
    color: #1a75bc;
}

.hospital-info p {
    margin: 5px 0;
    color: #666;
    font-size: 14px;
}

.bill-info {
    text-align: right;
}

.bill-info div {
    margin-bottom: 8px;
    font-size: 14px;
}

.bill-number {
    font-size: 18px;
}

.patient-details,
.consultation-details,
.bill-details,
.payment-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.patient-details h3,
.consultation-details h3,
.bill-details h3,
.payment-section h3 {
    color: #495057;
    margin-bottom: 15px;
    font-size: 18px;
}

.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 10px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row .label {
    font-weight: 600;
    color: #495057;
}

.info-row .value {
    color: #212529;
}

.bill-table {
    width: 100%;
    max-width: 500px;
    margin-top: 15px;
    border-collapse: collapse;
}

.bill-table th,
.bill-table td {
    padding: 12px;
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
    font-size: 18px;
}

.payment-details {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.payment-details p {
    margin: 10px 0;
}

.bill-footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
    color: #666;
    font-size: 14px;
}

.signature {
    margin-top: 30px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-paid {
    background: #d4edda;
    color: #155724;
}

.status-unpaid {
    background: #fff3cd;
    color: #856404;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.status-overdue {
    background: #f8d7da;
    color: #721c24;
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

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.data-table tr:hover {
    background: #f8f9fa;
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

.btn-success {
    background: #28a745;
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 8px 15px;
    border-radius: 5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-success:hover {
    background: #218838;
}

.btn-info {
    background: #17a2b8;
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 5px 10px;
    border-radius: 4px;
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

.alert {
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert i {
    margin-right: 10px;
}

@media print {
    .dashboard-header,
    .header-actions,
    .previous-bills,
    .btn,
    .alert {
        display: none;
    }
    
    .bill-card {
        box-shadow: none;
        padding: 0;
    }
    
    .bill-header {
        border-bottom: 1px solid #000;
    }
    
    .status-badge {
        border: 1px solid #000;
        background: none;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .bill-header {
        flex-direction: column;
        text-align: center;
    }
    
    .bill-info {
        text-align: center;
    }
    
    .patient-info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .bill-table {
        width: 100%;
    }
}
</style>

<?php include '../includes/footer.php'; ?>