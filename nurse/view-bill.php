<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkAnyRole(['nurse', 'admin', 'staff']);

$billId = (int)($_GET['bill_id'] ?? 0);

if (!$billId) {
    $_SESSION['error'] = "Bill ID is required.";
    header("Location: medical-records.php");
    exit();
}

$pageTitle = "View Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

// Get bill details
$stmt = $pdo->prepare("
    SELECT b.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           p.dateOfBirth,
           p.bloodType,
           mr.diagnosis,
           mr.creationDate as consultationDate,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           d.specialization
    FROM bills b
    JOIN patients p ON b.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN medical_records mr ON b.recordId = mr.recordId
    LEFT JOIN doctors d ON mr.doctorId = d.doctorId
    LEFT JOIN staff s ON d.staffId = s.staffId
    LEFT JOIN users du ON s.userId = du.userId
    WHERE b.billId = ?
");
$stmt->execute([$billId]);
$bill = $stmt->fetch();

if (!$bill) {
    $_SESSION['error'] = "Bill not found.";
    header("Location: medical-records.php");
    exit();
}

// Get additional charges
$stmt = $pdo->prepare("SELECT * FROM bill_charges WHERE billId = ? ORDER BY id ASC");
$stmt->execute([$billId]);
$additionalCharges = $stmt->fetchAll();

$isNurse = $_SESSION['user_role'] === 'nurse';
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-invoice-dollar"></i> Bill Details</h1>
            <p>Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="header-actions">
            <a href="javascript:history.back()" class="nurse-btn nurse-btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="nurse-btn nurse-btn-primary">
                <i class="fas fa-print"></i> Print Bill
            </button>
        </div>
    </div>

    <?php if ($isNurse): ?>
        <div class="nurse-alert nurse-alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Read-Only View:</strong> As a nurse, you can view bill details but cannot modify payments.
        </div>
    <?php endif; ?>

    <div class="nurse-card">
        <div class="nurse-card-header" style="display: block;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h2 style="color: #6f42c1; margin-bottom: 10px;">HealthManagement System</h2>
                    <p style="color: #64748b; margin: 5px 0;">Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                    <p style="color: #64748b; margin: 5px 0;">Phone: +61 438 347 3483 | Emergency: +61 455 2627</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 18px; margin-bottom: 5px;"><strong>Bill #:</strong> <?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($bill['generatedAt'])); ?></p>
                    <p>
                        <strong>Status:</strong> 
                        <span class="nurse-status-badge nurse-status-<?php echo $bill['status']; ?>">
                            <?php echo ucfirst($bill['status']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        <div class="nurse-card-body">
            <h3 style="color: #1e293b; margin-bottom: 20px;">Patient Information</h3>
            <div class="nurse-patient-info-grid">
                <div>
                    <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($bill['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($bill['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($bill['patientPhone']); ?></p>
                </div>
                <div>
                    <p><strong>Date of Birth:</strong> <?php echo $bill['dateOfBirth'] ? date('M j, Y', strtotime($bill['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($bill['dateOfBirth']); ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $bill['bloodType'] ?: 'N/A'; ?></p>
                </div>
            </div>

            <?php if ($bill['doctorName']): ?>
            <h3 style="color: #1e293b; margin: 25px 0 20px;">Consultation Details</h3>
            <div class="nurse-info-group">
                <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($bill['doctorName']); ?> (<?php echo htmlspecialchars($bill['specialization']); ?>)</p>
                <p><strong>Consultation Date:</strong> <?php echo date('F j, Y', strtotime($bill['consultationDate'])); ?></p>
                <?php if ($bill['diagnosis']): ?>
                    <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($bill['diagnosis']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <h3 style="color: #1e293b; margin: 25px 0 20px;">Bill Summary</h3>
            <table style="width: 100%; max-width: 500px; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 10px; text-align: left;">Description</th>
                        <th style="padding: 10px; text-align: right;">Amount ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 10px;">Consultation Fee</td>
                        <td style="padding: 10px; text-align: right;">$<?php echo number_format($bill['consultationFee'], 2); ?></td>
                    </tr>
                    <?php foreach ($additionalCharges as $charge): ?>
                        <tr>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($charge['chargeName']); ?></td>
                            <td style="padding: 10px; text-align: right;">$<?php echo number_format($charge['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td style="padding: 10px;"><strong>Subtotal</strong></td>
                        <td style="padding: 10px; text-align: right;"><strong>$<?php echo number_format($bill['consultationFee'] + $bill['additionalCharges'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;">Service Charge (3%)</td>
                        <td style="padding: 10px; text-align: right;">$<?php echo number_format($bill['serviceCharge'], 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;">GST (13%)</td>
                        <td style="padding: 10px; text-align: right;">$<?php echo number_format($bill['gst'], 2); ?></td>
                    </tr>
                    <tr style="border-top: 2px solid #6f42c1;">
                        <td style="padding: 10px;"><strong>Total Amount</strong></td>
                        <td style="padding: 10px; text-align: right;"><strong style="font-size: 18px; color: #6f42c1;">$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <h3 style="color: #1e293b; margin: 25px 0 20px;">Payment Information</h3>
            <?php if ($bill['status'] == 'paid' && $bill['paidAt']): ?>
                <div style="background: #dcfce7; padding: 20px; border-radius: 12px;">
                    <p><strong>Payment Status:</strong> <span class="nurse-status-badge nurse-status-paid">Paid</span></p>
                    <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                </div>
            <?php elseif ($bill['status'] == 'cancelled'): ?>
                <div style="background: #fee2e2; padding: 20px; border-radius: 12px;">
                    <p><strong>Payment Status:</strong> <span class="nurse-status-badge nurse-status-cancelled">Cancelled</span></p>
                </div>
            <?php else: ?>
                <div style="background: #fef3c7; padding: 20px; border-radius: 12px;">
                    <p><strong>Payment Status:</strong> <span class="nurse-status-badge nurse-status-unpaid">Unpaid</span></p>
                    <?php if ($isNurse): ?>
                        <p style="margin-top: 10px;"><i class="fas fa-info-circle"></i> Payment processing requires staff or admin privileges.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b;">
                <p>Thank you for choosing HealthManagement System.</p>
                <p style="margin-top: 30px;">Authorized Signature: _________________________</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>