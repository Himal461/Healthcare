<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Process Payment - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$billId = $_GET['bill_id'] ?? 0;
$searchTerm = $_GET['search'] ?? '';
$selectedPatient = null;
$patientBills = [];
$bill = null;

if ($billId) {
    $stmt = $pdo->prepare("SELECT b.*, CONCAT(u.firstName, ' ', u.lastName) as patientName, u.email, u.phoneNumber FROM bills b JOIN patients p ON b.patientId = p.patientId JOIN users u ON p.userId = u.userId WHERE b.billId = ?");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();
    if (!$bill) { 
        $_SESSION['error'] = "Bill not found."; 
        header("Location: process-payment.php"); 
        exit(); 
    }
}

if ($searchTerm) {
    $stmt = $pdo->prepare("SELECT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber FROM patients p JOIN users u ON p.userId = u.userId WHERE u.role = 'patient' AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?) LIMIT 20");
    $stmt->execute(["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
    $searchResults = $stmt->fetchAll();
}

if (isset($_GET['patient_id'])) {
    $patientId = $_GET['patient_id'];
    $stmt = $pdo->prepare("SELECT b.* FROM bills b WHERE b.patientId = ? AND b.status = 'unpaid' ORDER BY b.generatedAt");
    $stmt->execute([$patientId]);
    $patientBills = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT CONCAT(firstName, ' ', lastName) as name FROM users u JOIN patients p ON u.userId = p.userId WHERE p.patientId = ?");
    $stmt->execute([$patientId]);
    $selectedPatient = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $billId = $_POST['bill_id'];
    $paymentMethod = sanitizeInput($_POST['payment_method']);
    $amountPaid = floatval($_POST['amount_paid']);
    
    $stmt = $pdo->prepare("UPDATE bills SET status = 'paid', paidAt = NOW() WHERE billId = ? AND status = 'unpaid'");
    $stmt->execute([$billId]);
    
    if ($stmt->rowCount() > 0) {
        addFinanceTransaction('revenue', 'bill_payment', $amountPaid, $billId, "Payment processed by staff via {$paymentMethod}");
        sendPaymentConfirmationEmail($billId);
        
        $patientStmt = $pdo->prepare("SELECT p.userId FROM bills b JOIN patients p ON b.patientId = p.patientId WHERE b.billId = ?");
        $patientStmt->execute([$billId]);
        $patient = $patientStmt->fetch();
        
        if ($patient) {
            createNotification($patient['userId'], 'billing', 'Payment Received', "Your payment of $" . number_format($amountPaid, 2) . " has been received. Thank you!");
        }
        
        $_SESSION['success'] = "Payment processed successfully!";
        logAction($_SESSION['user_id'], 'PROCESS_PAYMENT', "Processed payment for bill #$billId");
    } else {
        $_SESSION['error'] = "Failed to process payment. Bill may already be paid.";
    }
    
    header("Location: dashboard.php");
    exit();
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-credit-card"></i> Process Payment</h1>
            <p>Process patient bill payments</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="staff-alert staff-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="staff-alert staff-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!$billId && !isset($_GET['patient_id'])): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-search"></i> Find Patient</h3>
            </div>
            <div class="staff-card-body">
                <form method="GET" class="staff-search-group">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="staff-search-input">
                    <button type="submit" class="staff-btn staff-btn-primary">Search</button>
                </form>
                
                <?php if ($searchTerm && isset($searchResults)): ?>
                    <div class="staff-search-results">
                        <h4>Search Results</h4>
                        <?php if (empty($searchResults)): ?>
                            <p class="staff-text-muted">No patients found.</p>
                        <?php else: ?>
                            <div class="staff-patient-list">
                                <?php foreach ($searchResults as $p): ?>
                                    <div class="staff-patient-item">
                                        <div class="staff-patient-info">
                                            <strong><?php echo htmlspecialchars($p['firstName'].' '.$p['lastName']); ?></strong>
                                            <small><?php echo $p['email']; ?></small>
                                        </div>
                                        <a href="?patient_id=<?php echo $p['patientId']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">View Bills</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['patient_id']) && !$billId): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-file-invoice"></i> Pending Bills - <?php echo htmlspecialchars($selectedPatient['name']); ?></h3>
            </div>
            <div class="staff-card-body">
                <?php if (empty($patientBills)): ?>
                    <p class="staff-text-muted">No pending bills for this patient.</p>
                <?php else: ?>
                    <?php foreach ($patientBills as $b): ?>
                        <div class="staff-bill-item">
                            <div>
                                <strong>Bill #<?php echo $b['billId']; ?></strong><br>
                                Amount: $<?php echo number_format($b['totalAmount'], 2); ?><br>
                                Date: <?php echo date('M j, Y', strtotime($b['generatedAt'])); ?>
                            </div>
                            <a href="?bill_id=<?php echo $b['billId']; ?>" class="staff-btn staff-btn-primary">Process</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="process-payment.php" class="staff-btn staff-btn-outline" style="margin-top: 20px;">Search Another</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($billId && $bill): ?>
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-credit-card"></i> Process Payment - Bill #<?php echo $bill['billId']; ?></h3>
            </div>
            <div class="staff-card-body">
                <div class="staff-info-group" style="margin-bottom: 25px;">
                    <p><strong>Patient:</strong> <?php echo htmlspecialchars($bill['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($bill['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($bill['phoneNumber']); ?></p>
                    <p><strong>Amount Due:</strong> <span style="font-size: 20px; color: #f59e0b;">$<?php echo number_format($bill['totalAmount'], 2); ?></span></p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="bill_id" value="<?php echo $bill['billId']; ?>">
                    
                    <div class="staff-form-group">
                        <label>Amount to Pay</label>
                        <input type="number" name="amount_paid" step="0.01" value="<?php echo $bill['totalAmount']; ?>" class="staff-form-control" required>
                    </div>
                    
                    <div class="staff-form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="staff-form-control" required>
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" name="process_payment" class="staff-btn staff-btn-success">
                            <i class="fas fa-check"></i> Process Payment
                        </button>
                        <a href="process-payment.php" class="staff-btn staff-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>