<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "View Bill - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$billId = (int)($_GET['bill_id'] ?? 0);

if (!$billId) { 
    $_SESSION['error'] = "Bill ID required."; 
    header("Location: view-bills.php"); 
    exit(); 
}

$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$patientId = $patient['patientId'];

$stmt = $pdo->prepare("
    SELECT b.*, CONCAT(u.firstName, ' ', u.lastName) as doctorName, d.specialization, 
           mr.diagnosis, mr.creationDate as consultationDate, a.dateTime as appointmentDate
    FROM bills b 
    LEFT JOIN medical_records mr ON b.recordId = mr.recordId 
    LEFT JOIN doctors d ON mr.doctorId = d.doctorId
    LEFT JOIN staff s ON d.staffId = s.staffId 
    LEFT JOIN users u ON s.userId = u.userId 
    LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
    WHERE b.billId = ? AND b.patientId = ?
");
$stmt->execute([$billId, $patientId]);
$bill = $stmt->fetch();

if (!$bill) { 
    $_SESSION['error'] = "Bill not found."; 
    header("Location: view-bills.php"); 
    exit(); 
}

$stmt = $pdo->prepare("SELECT * FROM bill_charges WHERE billId = ?");
$stmt->execute([$billId]);
$additionalCharges = $stmt->fetchAll();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-receipt"></i> Bill Details</h1>
            <p>Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="header-actions">
            <a href="view-bills.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="patient-btn patient-btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="patient-alert patient-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="patient-alert patient-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="patient-bill-card">
        <div class="patient-bill-header">
            <div>
                <h2>HealthManagement System</h2>
                <p>Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                <p>Phone: +61 438 347 3483</p>
            </div>
            <div class="patient-bill-info">
                <div class="patient-bill-number"><strong>Bill #:</strong> <?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></div>
                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($bill['generatedAt'])); ?></p>
                <p>
                    <span class="patient-status-badge patient-status-<?php echo $bill['status']; ?>">
                        <?php echo ucfirst($bill['status']); ?>
                    </span>
                </p>
            </div>
        </div>

        <h3 style="color: #1e293b; margin-bottom: 20px;">Consultation Details</h3>
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
            <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($bill['doctorName']); ?> (<?php echo $bill['specialization']; ?>)</p>
            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($bill['consultationDate'])); ?></p>
            <?php if ($bill['diagnosis']): ?>
                <p><strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($bill['diagnosis'])); ?></p>
            <?php endif; ?>
        </div>

        <h3 style="color: #1e293b; margin-bottom: 20px;">Bill Summary</h3>
        <table class="patient-bill-table">
            <tr><td>Consultation Fee:</td><td>$<?php echo number_format($bill['consultationFee'], 2); ?></td></tr>
            <?php foreach ($additionalCharges as $c): ?>
                <tr><td><?php echo htmlspecialchars($c['chargeName']); ?>:</td><td>$<?php echo number_format($c['amount'], 2); ?></td></tr>
            <?php endforeach; ?>
            <tr><td>Service Charge (3%):</td><td>$<?php echo number_format($bill['serviceCharge'], 2); ?></td></tr>
            <tr><td>GST (13%):</td><td>$<?php echo number_format($bill['gst'], 2); ?></td></tr>
            <tr class="total-row"><td><strong>Total:</strong></td><td><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td></tr>
        </table>

        <!-- Payment Information Section -->
        <h3 style="color: #1e293b; margin: 25px 0 20px;">Payment Information</h3>
        <?php if ($bill['status'] == 'paid' && $bill['paidAt']): ?>
            <div class="patient-payment-paid-section">
                <div class="patient-payment-paid-header">
                    <i class="fas fa-check-circle"></i>
                    <span>Payment Completed</span>
                </div>
                <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
            </div>
        <?php elseif ($bill['status'] == 'cancelled'): ?>
            <div class="patient-payment-cancelled-section">
                <div class="patient-payment-cancelled-header">
                    <i class="fas fa-times-circle"></i>
                    <span>Bill Cancelled</span>
                </div>
            </div>
        <?php else: ?>
            <div class="patient-payment-notice-section">
                <div class="patient-payment-notice-header">
                    <i class="fas fa-info-circle"></i>
                    <span>Payment Required</span>
                </div>
                <p>Please visit our reception desk to complete your payment. Our staff will process your payment and update your bill status.</p>
                <div class="patient-payment-notice-details">
                    <div class="patient-notice-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><strong>Location:</strong> Fussel Lane, Gungahlin, ACT 2912</span>
                    </div>
                    <div class="patient-notice-item">
                        <i class="fas fa-clock"></i>
                        <span><strong>Hours:</strong> Mon-Fri 9:00 AM - 5:00 PM, Sat 9:00 AM - 1:00 PM</span>
                    </div>
                    <div class="patient-notice-item">
                        <i class="fas fa-phone"></i>
                        <span><strong>Phone:</strong> +61 438 347 3483</span>
                    </div>
                    <div class="patient-notice-item">
                        <i class="fas fa-money-bill-wave"></i>
                        <span><strong>Amount Due:</strong> $<?php echo number_format($bill['totalAmount'], 2); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="patient-bill-footer">
            <p>Thank you for choosing HealthManagement System.</p>
        </div>
    </div>
</div>

<style>
    /* Payment Notice Banner */
.patient-payment-banner {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border: 2px solid #3b82f6;
    border-radius: 16px;
    padding: 25px 30px;
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 20px;
}

.patient-payment-banner-icon {
    width: 50px;
    height: 50px;
    background: #3b82f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.patient-payment-banner-icon i {
    font-size: 24px;
    color: white;
}

.patient-payment-banner-content h3 {
    color: #1e40af;
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 700;
}

.patient-payment-banner-content p {
    color: #1e3a5f;
    margin: 0 0 15px 0;
    font-size: 15px;
    line-height: 1.5;
}

.patient-payment-banner-contact {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.patient-payment-banner-contact span {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #475569;
    font-size: 14px;
    font-weight: 500;
}

.patient-payment-banner-contact span i {
    color: #3b82f6;
    width: 18px;
}

/* Payment Notice Section (on bill detail page) */
.patient-payment-notice-section {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 25px 30px;
    margin-top: 25px;
}

.patient-payment-notice-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
}

.patient-payment-notice-header i {
    font-size: 24px;
    color: #3b82f6;
}

.patient-payment-notice-header span {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.patient-payment-notice-section > p {
    color: #475569;
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.patient-payment-notice-details {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}

.patient-notice-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.patient-notice-item:last-child {
    border-bottom: none;
}

.patient-notice-item i {
    width: 20px;
    color: #3b82f6;
    font-size: 16px;
    text-align: center;
}

.patient-notice-item span {
    color: #334155;
    font-size: 14px;
}

/* Paid Section */
.patient-payment-paid-section {
    background: #f0fdf4;
    border: 2px solid #22c55e;
    border-radius: 16px;
    padding: 25px 30px;
    margin-top: 25px;
}

.patient-payment-paid-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.patient-payment-paid-header i {
    font-size: 28px;
    color: #16a34a;
}

.patient-payment-paid-header span {
    font-size: 18px;
    font-weight: 700;
    color: #166534;
}

.patient-payment-paid-section p {
    color: #166534;
    font-size: 15px;
    margin: 5px 0 0 40px;
}

/* Cancelled Section */
.patient-payment-cancelled-section {
    background: #fef2f2;
    border: 2px solid #ef4444;
    border-radius: 16px;
    padding: 25px 30px;
    margin-top: 25px;
}

.patient-payment-cancelled-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.patient-payment-cancelled-header i {
    font-size: 28px;
    color: #dc2626;
}

.patient-payment-cancelled-header span {
    font-size: 18px;
    font-weight: 700;
    color: #991b1b;
}

/* Responsive */
@media (max-width: 768px) {
    .patient-payment-banner {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    
    .patient-payment-banner-icon {
        margin: 0 auto;
    }
    
    .patient-payment-banner-contact {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
    
    .patient-payment-notice-section {
        padding: 20px;
    }
    
    .patient-payment-notice-details {
        padding: 15px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>