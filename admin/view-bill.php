<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$billId = (int)($_GET['bill_id'] ?? 0);

if (!$billId) {
    $_SESSION['error'] = "Bill ID is required.";
    header("Location: billing.php");
    exit();
}

$bill = getBillById($billId);

if (!$bill) {
    $_SESSION['error'] = "Bill not found.";
    header("Location: billing.php");
    exit();
}

$additionalCharges = getBillCharges($billId);
$pageTitle = "View Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-invoice-dollar"></i> Bill Details</h1>
            <p>Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="header-actions">
            <a href="billing.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Billing
            </a>
            <button onclick="window.print()" class="admin-btn admin-btn-primary">
                <i class="fas fa-print"></i> Print Bill
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <div class="admin-card-header" style="display: block;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h2 style="color: #dc2626; margin-bottom: 10px;">HealthManagement System</h2>
                    <p style="color: #64748b; margin: 5px 0;">Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                    <p style="color: #64748b; margin: 5px 0;">Phone: +61 438 347 3483 | Emergency: +61 455 2627</p>
                    <p style="color: #64748b; margin: 5px 0;">Email: himalkumarkari@gmail.com | abinashcarkee@gmail.com</p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 18px; margin-bottom: 5px;"><strong>Bill Number:</strong> #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></div>
                    <div style="margin-bottom: 5px;"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($bill['generatedAt'])); ?></div>
                    <div>
                        <strong>Status:</strong> 
                        <span class="admin-status-badge admin-status-<?php echo $bill['status']; ?>">
                            <?php echo ucfirst($bill['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="admin-card-body">
            <!-- Patient Information -->
            <h3 style="color: #1e293b; margin-bottom: 20px;">Patient Information</h3>
            <div class="admin-patient-info-grid" style="margin-bottom: 25px;">
                <div class="admin-info-group">
                    <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($bill['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($bill['patientEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($bill['patientPhone']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $bill['dateOfBirth'] ? date('M j, Y', strtotime($bill['dateOfBirth'])) : 'N/A'; ?></p>
                    <p><strong>Age:</strong> <?php echo calculateAge($bill['dateOfBirth']); ?></p>
                </div>
                <div class="admin-info-group">
                    <p><strong>Blood Type:</strong> <?php echo $bill['bloodType'] ?: 'N/A'; ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($bill['address'] ?: 'N/A'); ?></p>
                </div>
            </div>

            <!-- Consultation Details -->
            <?php if ($bill['doctorName']): ?>
            <h3 style="color: #1e293b; margin-bottom: 20px;">Consultation Details</h3>
            <div class="admin-info-group" style="margin-bottom: 25px;">
                <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($bill['doctorName']); ?> (<?php echo htmlspecialchars($bill['specialization']); ?>)</p>
                <?php if ($bill['appointmentDate']): ?>
                    <p><strong>Appointment Date:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['appointmentDate'])); ?></p>
                <?php endif; ?>
                <p><strong>Consultation Date:</strong> <?php echo date('F j, Y', strtotime($bill['consultationDate'])); ?></p>
                <?php if ($bill['diagnosis']): ?>
                    <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($bill['diagnosis']); ?></p>
                <?php endif; ?>
                <?php if ($bill['treatmentNotes']): ?>
                    <p><strong>Treatment Notes:</strong> <?php echo nl2br(htmlspecialchars($bill['treatmentNotes'])); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Bill Summary -->
            <h3 style="color: #1e293b; margin-bottom: 20px;">Bill Summary</h3>
            <table style="width: 100%; max-width: 500px; border-collapse: collapse; margin-bottom: 25px;">
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
                    <tr style="border-top: 2px solid #dc2626;">
                        <td style="padding: 15px 10px;"><strong style="font-size: 18px;">Total Amount</strong></td>
                        <td style="padding: 15px 10px; text-align: right;"><strong style="font-size: 20px; color: #dc2626;">$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <!-- Payment Information -->
            <h3 style="color: #1e293b; margin-bottom: 20px;">Payment Information</h3>
            <?php if ($bill['status'] == 'paid' && $bill['paidAt']): ?>
                <div style="background: #dcfce7; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <p><strong>Payment Status:</strong> <span class="admin-status-badge admin-status-paid">Paid</span></p>
                    <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                </div>
            <?php elseif ($bill['status'] == 'cancelled'): ?>
                <div style="background: #fee2e2; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <p><strong>Payment Status:</strong> <span class="admin-status-badge admin-status-cancelled">Cancelled</span></p>
                </div>
            <?php else: ?>
                <div style="background: #fef3c7; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <p><strong>Payment Status:</strong> <span class="admin-status-badge admin-status-unpaid">Unpaid</span></p>
                    <form method="POST" action="update-bill-status.php" style="margin-top: 15px;">
                        <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                        <input type="hidden" name="status" value="paid">
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="payment_method" class="admin-form-control" style="max-width: 200px;" required>
                                <option value="">Select Payment Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Insurance">Insurance</option>
                            </select>
                            <button type="submit" class="admin-btn admin-btn-success" onclick="return confirm('Mark this bill as paid?')">
                                <i class="fas fa-check-circle"></i> Mark as Paid
                            </button>
                        </div>
                    </form>
                    <form method="POST" action="update-bill-status.php" style="margin-top: 10px;">
                        <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="admin-btn admin-btn-danger" onclick="return confirm('Cancel this bill?')">
                            <i class="fas fa-times"></i> Cancel Bill
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Footer -->
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b;">
                <p>Thank you for choosing HealthManagement System.</p>
                <p style="margin-top: 30px; font-family: 'Courier New', monospace;">Authorized Signature: _________________________</p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .admin-page-header,
    .header-actions,
    .admin-btn,
    form {
        display: none !important;
    }
    .admin-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<?php include '../includes/footer.php'; ?>