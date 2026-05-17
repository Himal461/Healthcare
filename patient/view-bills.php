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

// FIXED: Get bills with doctor names from BOTH medical_records AND medical_certificates
$stmt = $pdo->prepare("
    SELECT b.*, 
           COALESCE(
               CONCAT(mr_doctor.firstName, ' ', mr_doctor.lastName),
               CONCAT(cert_doctor.firstName, ' ', cert_doctor.lastName),
               'Not Assigned'
           ) as doctorName,
           COALESCE(mr_doctor_spec.specialization, cert_doctor_spec.specialization, '') as specialization,
           mr.diagnosis
    FROM bills b
    LEFT JOIN medical_records mr ON b.recordId = mr.recordId
    LEFT JOIN doctors mr_doc ON mr.doctorId = mr_doc.doctorId
    LEFT JOIN staff mr_staff ON mr_doc.staffId = mr_staff.staffId
    LEFT JOIN users mr_doctor ON mr_staff.userId = mr_doctor.userId
    LEFT JOIN doctors mr_doctor_spec ON mr.doctorId = mr_doctor_spec.doctorId
    LEFT JOIN medical_certificates mc ON b.billId = mc.bill_id
    LEFT JOIN doctors cert_doc ON mc.doctor_id = cert_doc.doctorId
    LEFT JOIN staff cert_staff ON cert_doc.staffId = cert_staff.staffId
    LEFT JOIN users cert_doctor ON cert_staff.userId = cert_doctor.userId
    LEFT JOIN doctors cert_doctor_spec ON mc.doctor_id = cert_doctor_spec.doctorId
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
                                <td data-label="Doctor">
                                    <?php if ($b['doctorName'] && $b['doctorName'] !== 'Not Assigned'): ?>
                                        <strong>Dr. <?php echo htmlspecialchars($b['doctorName']); ?></strong>
                                        <?php if ($b['specialization']): ?>
                                            <br><small style="color:#64748b;"><?php echo htmlspecialchars($b['specialization']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
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
}
</style>

<?php include '../includes/footer.php'; ?>