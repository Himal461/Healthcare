<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Bills - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
if (!$patient) { 
    $_SESSION['error'] = "Profile not found."; 
    header("Location: dashboard.php"); 
    exit(); 
}
$patientId = $patient['patientId'];

$stmt = $pdo->prepare("
    SELECT b.*, CONCAT(u.firstName, ' ', u.lastName) as doctorName, mr.diagnosis
    FROM bills b 
    LEFT JOIN medical_records mr ON b.recordId = mr.recordId 
    LEFT JOIN doctors d ON mr.doctorId = d.doctorId
    LEFT JOIN staff s ON d.staffId = s.staffId 
    LEFT JOIN users u ON s.userId = u.userId 
    WHERE b.patientId = ? 
    ORDER BY b.generatedAt DESC
");
$stmt->execute([$patientId]);
$bills = $stmt->fetchAll();

$unpaidTotal = 0;
$paidTotal = 0;
foreach ($bills as $b) {
    if ($b['status'] == 'unpaid') $unpaidTotal += $b['totalAmount'];
    if ($b['status'] == 'paid') $paidTotal += $b['totalAmount'];
}
?>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-invoice-dollar"></i> My Bills</h1>
            <p>View and manage your billing history</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Payment Notice Banner for Unpaid Bills -->
    <?php if ($unpaidTotal > 0): ?>
        <div class="patient-payment-banner">
            <div class="patient-payment-banner-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="patient-payment-banner-content">
                <h3>Payment Information</h3>
                <p>You have <strong>$<?php echo number_format($unpaidTotal, 2); ?></strong> in outstanding bills. Please visit our reception desk to complete your payment.</p>
                <div class="patient-payment-banner-contact">
                    <span><i class="fas fa-map-marker-alt"></i> Fussel Lane, Gungahlin, ACT 2912</span>
                    <span><i class="fas fa-clock"></i> Mon-Fri: 9AM-5PM | Sat: 9AM-1PM</span>
                    <span><i class="fas fa-phone"></i> +61 438 347 3483</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="patient-summary-cards">
        <div class="patient-summary-card">
            <span>Outstanding Balance</span>
            <span>$<?php echo number_format($unpaidTotal, 2); ?></span>
        </div>
        <div class="patient-summary-card">
            <span>Total Paid</span>
            <span>$<?php echo number_format($paidTotal, 2); ?></span>
        </div>
        <div class="patient-summary-card">
            <span>Total Bills</span>
            <span><?php echo count($bills); ?></span>
        </div>
    </div>

    <div class="patient-card">
        <div class="patient-table-responsive">
            <?php if (empty($bills)): ?>
                <div class="patient-empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3>No Bills Found</h3>
                    <p>You don't have any bills yet.</p>
                </div>
            <?php else: ?>
                <table class="patient-data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td data-label="Bill #">#<?php echo str_pad($b['billId'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($b['generatedAt'])); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($b['doctorName']); ?></td>
                                <td data-label="Amount">$<?php echo number_format($b['totalAmount'], 2); ?></td>
                                <td data-label="Status">
                                    <span class="patient-status-badge patient-status-<?php echo $b['status']; ?>">
                                        <?php echo ucfirst($b['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a href="view-bill.php?bill_id=<?php echo $b['billId']; ?>" class="patient-btn patient-btn-info patient-btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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