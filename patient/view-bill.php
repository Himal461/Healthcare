<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "View Bill - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$billId = (int)($_GET['bill_id'] ?? 0);

if (!$billId) {
    $_SESSION['error'] = "Bill ID is required.";
    header("Location: dashboard.php");
    exit();
}

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: dashboard.php");
    exit();
}

$patientId = $patient['patientId'];

// Get bill details
$stmt = $pdo->prepare("
    SELECT b.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           d.specialization,
           mr.diagnosis,
           mr.creationDate as consultationDate,
           a.dateTime as appointmentDate
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
    header("Location: dashboard.php");
    exit();
}

// Get additional charges
$stmt = $pdo->prepare("SELECT * FROM bill_charges WHERE billId = ?");
$stmt->execute([$billId]);
$additionalCharges = $stmt->fetchAll();

// Process payment - FIXED VERSION
$paymentSuccess = false;
$paymentError = null;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if pay_bill button was clicked
    if (isset($_POST['pay_bill'])) {
        $paymentMethod = $_POST['payment_method'] ?? '';
        
        if (empty($paymentMethod)) {
            $paymentError = "Please select a payment method.";
        } else {
            try {
                // Update the bill status
                $updateStmt = $pdo->prepare("
                    UPDATE bills 
                    SET status = 'paid', paidAt = NOW() 
                    WHERE billId = ? AND status = 'unpaid'
                ");
                $updateStmt->execute([$billId]);
                
                $rowsAffected = $updateStmt->rowCount();
                
                if ($rowsAffected > 0) {
                    $paymentSuccess = true;
                    // Update the local bill array
                    $bill['status'] = 'paid';
                    $bill['paidAt'] = date('Y-m-d H:i:s');
                    
                    // Optional: Create notification
                    try {
                        $notifyStmt = $pdo->prepare("
                            INSERT INTO notifications (userId, message, type, created_at) 
                            VALUES (?, ?, 'payment', NOW())
                        ");
                        $notifyStmt->execute([
                            $userId,
                            "Payment successful for Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " of $" . number_format($bill['totalAmount'], 2)
                        ]);
                    } catch (Exception $e) {
                        // Ignore notification errors
                    }
                } else {
                    $paymentError = "Bill could not be updated. It may already be paid.";
                }
            } catch (Exception $e) {
                $paymentError = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>Bill Details</h1>
            <p>Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Bill
            </button>
        </div>
    </div>

    <?php if ($paymentSuccess): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <strong>Payment Successful!</strong>
            <p>Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?> has been marked as paid.</p>
            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        </div>
    <?php endif; ?>

    <?php if ($paymentError): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Payment Failed</strong>
            <p><?php echo $paymentError; ?></p>
        </div>
    <?php endif; ?>

    <!-- Hospital Information -->
    <div class="hospital-info-card">
        <h3>HealthManagement System</h3>
        <p><i class="fas fa-map-marker-alt"></i> Fussel Lane, Gungahlin, ACT 2912, Australia</p>
        <p><i class="fas fa-phone"></i> Main: <a href="tel:+614383473483">+61 438 347 3483</a></p>
        <p><i class="fas fa-ambulance"></i> Emergency: <a href="tel:+614552627">+61 455 2627</a></p>
        <p><i class="fas fa-envelope"></i> Email: <a href="mailto:himalkumarkari@gmail.com">himalkumarkari@gmail.com</a></p>
    </div>

    <div class="bill-container">
        <div class="card bill-card">
            <div class="bill-header">
                <div class="hospital-info">
                    <h2>HealthManagement System</h2>
                    <p>Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                    <p>Phone: <a href="tel:+614383473483">+61 438 347 3483</a> | Emergency: <a href="tel:+614552627">+61 455 2627</a></p>
                    <p>Email: <a href="mailto:himalkumarkari@gmail.com">himalkumarkari@gmail.com</a></p>
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
                <h3>Consultation Details</h3>
                <div class="patient-info-grid">
                    <div class="info-row">
                        <span class="label">Doctor Name:</span>
                        <span class="value">Dr. <?php echo htmlspecialchars($bill['doctorName']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Specialization:</span>
                        <span class="value"><?php echo htmlspecialchars($bill['specialization']); ?></span>
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
            </div>

            <?php if ($bill['diagnosis']): ?>
            <div class="consultation-details">
                <h3>Diagnosis</h3>
                <div class="diagnosis-box">
                    <?php echo nl2br(htmlspecialchars($bill['diagnosis'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bill-details">
                <h3>Bill Summary</h3>
                <table class="bill-table">
                    <thead>
                        32
                            <th>Description</th>
                            <th class="text-right">Amount ($)</th>
                        </thead>
                    <tbody>
                        32
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
            </div>

            <!-- Payment Section - Only show if bill is unpaid -->
            <?php if ($bill['status'] == 'unpaid'): ?>
            <div class="payment-section">
                <h3>Make Payment</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Select Payment Method:</label>
                        <select name="payment_method" id="payment_method" required class="form-control">
                            <option value="">-- Select Payment Method --</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="upi">UPI (Google Pay, PhonePe, etc.)</option>
                            <option value="net_banking">Net Banking</option>
                            <option value="cash">Cash (Pay at Hospital Counter)</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="pay_bill" class="btn btn-success btn-large" id="payButton">
                            <i class="fas fa-credit-card"></i> Confirm Payment - $<?php echo number_format($bill['totalAmount'], 2); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="payment-section paid-section">
                <h3>Payment Information</h3>
                <div class="payment-details paid">
                    <p><strong>Payment Status:</strong> <span class="status-badge status-paid">Paid</span></p>
                    <p><strong>Paid On:</strong> <?php echo date('F j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                    <p><strong>Thank you for your payment!</strong></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="bill-footer">
                <p>Thank you for choosing HealthManagement System. We wish you good health!</p>
                <p class="signature">HealthManagement Team</p>
            </div>
        </div>
    </div>
</div>

<script>
// Add JavaScript to handle form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const method = document.getElementById('payment_method').value;
            
            if (!method) {
                e.preventDefault();
                alert('Please select a payment method.');
                return false;
            }
            
            if (method === 'cash') {
                if (!confirm('You have selected cash payment. Please visit the hospital counter to complete your payment. Click OK to confirm.')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Disable button to prevent double submission
            const btn = document.getElementById('payButton');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
            }
            
            return true;
        });
    }
});
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

.hospital-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.hospital-info-card h3 {
    margin: 0 0 15px 0;
    font-size: 24px;
}

.hospital-info-card p {
    margin: 8px 0;
    font-size: 14px;
}

.hospital-info-card i {
    margin-right: 8px;
}

.hospital-info-card a {
    color: white;
    text-decoration: none;
    border-bottom: 1px dotted rgba(255,255,255,0.5);
}

.hospital-info-card a:hover {
    border-bottom-color: white;
}

.bill-container {
    margin-top: 20px;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

.hospital-info a {
    color: #1a75bc;
    text-decoration: none;
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
    font-weight: bold;
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

.diagnosis-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
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

.tax-row {
    color: #666;
}

.payment-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.payment-details {
    margin-top: 15px;
    padding: 15px;
    background: white;
    border-radius: 8px;
}

.payment-details.paid {
    background: #d4edda;
    text-align: center;
}

.paid-section {
    background: #e8f5e9;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.form-actions {
    margin-top: 20px;
    text-align: right;
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
    padding: 4px 10px;
    border-radius: 20px;
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

.btn-primary {
    background: #1a75bc;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
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
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-success:hover {
    background: #218838;
}

.btn-success:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert i {
    margin-right: 10px;
}

@media print {
    .dashboard-header,
    .header-actions,
    .payment-section,
    .btn,
    .alert,
    .hospital-info-card {
        display: none;
    }
    
    .bill-card {
        padding: 0;
        box-shadow: none;
    }
    
    .bill-header {
        border-bottom: 1px solid #000;
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
    
    .info-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .patient-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../includes/footer.php'; ?>