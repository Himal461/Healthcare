<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "View Bill - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$billId = (int)($_GET['bill_id'] ?? 0);

if (!$billId) { 
    $_SESSION['error'] = "Bill ID required."; 
    header("Location: dashboard.php"); 
    exit(); 
}

$stmt = $pdo->prepare("SELECT d.doctorId FROM doctors d JOIN staff s ON d.staffId = s.staffId WHERE s.userId = ?");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT b.*, CONCAT(u.firstName, ' ', u.lastName) as patientName, u.email, u.phoneNumber,
           p.dateOfBirth, p.bloodType, p.knownAllergies,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName, d.specialization,
           mr.diagnosis, mr.creationDate as consultationDate
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
    header("Location: dashboard.php"); 
    exit(); 
}

$stmt = $pdo->prepare("SELECT * FROM bill_charges WHERE billId = ?");
$stmt->execute([$billId]);
$additionalCharges = $stmt->fetchAll();
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-invoice-dollar"></i> Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></h1>
            <p><?php echo htmlspecialchars($bill['patientName']); ?></p>
        </div>
        <div class="header-actions">
            <a href="javascript:history.back()" class="doctor-btn doctor-btn-outline">Back</a>
            <button onclick="window.print()" class="doctor-btn doctor-btn-primary">Print</button>
        </div>
    </div>

    <div class="doctor-card">
        <div class="doctor-card-header" style="display: block;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h2 style="color: #2563eb;">HealthManagement System</h2>
                    <p style="color: #64748b;">Fussel Lane, Gungahlin, ACT 2912</p>
                </div>
                <div style="text-align: right;">
                    <p><strong>Bill #:</strong> <?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($bill['generatedAt'])); ?></p>
                    <p>
                        <span class="doctor-status-badge doctor-status-<?php echo $bill['status']; ?>">
                            <?php echo ucfirst($bill['status']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        <div class="doctor-card-body">
            <h3>Patient Information</h3>
            <div class="doctor-patient-info-grid" style="margin-bottom: 25px;">
                <div>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($bill['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($bill['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($bill['phoneNumber']); ?></p>
                </div>
                <div>
                    <p><strong>DOB:</strong> <?php echo $bill['dateOfBirth'] ? date('M j, Y', strtotime($bill['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Blood Type:</strong> <?php echo $bill['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo htmlspecialchars($bill['knownAllergies'] ?: 'None'); ?></p>
                </div>
            </div>

            <?php if ($bill['doctorName']): ?>
                <h3>Consultation Details</h3>
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($bill['doctorName']); ?> (<?php echo htmlspecialchars($bill['specialization']); ?>)</p>
                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($bill['consultationDate'])); ?></p>
                    <?php if ($bill['diagnosis']): ?>
                        <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($bill['diagnosis']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h3>Bill Summary</h3>
            <table class="doctor-bill-table">
                <tr><td>Consultation Fee:</td><td>$<?php echo number_format($bill['consultationFee'], 2); ?></td></tr>
                <?php foreach ($additionalCharges as $c): ?>
                    <tr><td><?php echo htmlspecialchars($c['chargeName']); ?>:</td><td>$<?php echo number_format($c['amount'], 2); ?></td></tr>
                <?php endforeach; ?>
                <tr><td>Service Charge (3%):</td><td>$<?php echo number_format($bill['serviceCharge'], 2); ?></td></tr>
                <tr><td>GST (13%):</td><td>$<?php echo number_format($bill['gst'], 2); ?></td></tr>
                <tr class="total-row"><td><strong>Total:</strong></td><td><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td></tr>
            </table>

            <h3 style="margin-top: 25px;">Payment Information</h3>
            <?php if ($bill['status'] == 'paid'): ?>
                <div style="background: #dcfce7; padding: 20px; border-radius: 12px;">
                    <p><span class="doctor-status-badge doctor-status-paid">Paid</span></p>
                    <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                </div>
            <?php elseif ($bill['status'] == 'cancelled'): ?>
                <div style="background: #fee2e2; padding: 20px; border-radius: 12px;">
                    <p><span class="doctor-status-badge doctor-status-cancelled">Cancelled</span></p>
                </div>
            <?php else: ?>
                <div style="background: #fef3c7; padding: 20px; border-radius: 12px;">
                    <p><span class="doctor-status-badge doctor-status-unpaid">Unpaid</span></p>
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <a href="update-bill-status.php?bill_id=<?php echo $billId; ?>&status=paid" class="doctor-btn doctor-btn-success doctor-btn-sm" onclick="return confirm('Mark as paid?')">
                            <i class="fas fa-check"></i> Mark as Paid
                        </a>
                        <a href="update-bill-status.php?bill_id=<?php echo $billId; ?>&status=cancelled" class="doctor-btn doctor-btn-danger doctor-btn-sm" onclick="return confirm('Cancel this bill?')">
                            <i class="fas fa-times"></i> Cancel Bill
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>