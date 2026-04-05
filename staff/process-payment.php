<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Process Payment - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$billId = $_GET['bill_id'] ?? 0;

// Get bill details
if ($billId) {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.email as patientEmail,
               u.phoneNumber as patientPhone,
               a.dateTime as appointmentDate
        FROM billing b
        JOIN patients p ON b.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
        WHERE b.billId = ?
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        $_SESSION['error'] = "Bill not found.";
        header("Location: dashboard.php");
        exit();
    }
}

// Search for patient if no bill selected
$searchTerm = $_GET['search'] ?? '';
$selectedPatient = null;
$patientBills = [];
$searchResults = [];

if ($searchTerm) {
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.firstName, u.lastName, u.email, u.phoneNumber
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?
        LIMIT 20
    ");
    $searchLike = "%$searchTerm%";
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike]);
    $searchResults = $stmt->fetchAll();
}

// Get patient bills if patient selected
if (isset($_GET['patient_id'])) {
    $patientId = $_GET['patient_id'];
    $stmt = $pdo->prepare("
        SELECT b.*, a.dateTime as appointmentDate
        FROM billing b
        LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
        WHERE b.patientId = ? AND b.status = 'pending'
        ORDER BY b.dueDate
    ");
    $stmt->execute([$patientId]);
    $patientBills = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT CONCAT(firstName, ' ', lastName) as name 
        FROM users u 
        JOIN patients p ON u.userId = p.userId 
        WHERE p.patientId = ?
    ");
    $stmt->execute([$patientId]);
    $selectedPatient = $stmt->fetch();
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $billId = $_POST['bill_id'];
    $paymentMethod = sanitizeInput($_POST['payment_method']);
    $amountPaid = floatval($_POST['amount_paid']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET status = 'paid', 
                paymentMethod = ?, 
                paymentDate = NOW(),
                notes = CONCAT(IFNULL(notes, ''), ' Payment received: $', ?, ' on ', NOW())
            WHERE billId = ? AND status = 'pending'
        ");
        $stmt->execute([$paymentMethod, $amountPaid, $billId]);
        
        // Get patient details for notification
        $stmt = $pdo->prepare("
            SELECT p.patientId, u.userId, u.firstName, u.lastName, u.email
            FROM billing b
            JOIN patients p ON b.patientId = p.patientId
            JOIN users u ON p.userId = u.userId
            WHERE b.billId = ?
        ");
        $stmt->execute([$billId]);
        $patient = $stmt->fetch();
        
        createNotification(
            $patient['userId'],
            'billing',
            'Payment Received',
            "Payment of $" . number_format($amountPaid, 2) . " has been received. Thank you!"
        );
        
        $_SESSION['success'] = "Payment processed successfully!";
        logAction($userId, 'PROCESS_PAYMENT', "Processed payment for bill ID: $billId");
        
        header("Location: process-payment.php");
        exit();
        
    } catch (Exception $e) {
        $error = "Failed to process payment: " . $e->getMessage();
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Process Payment</h1>
        <p>Process patient payments and manage billing</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (!$billId && !isset($_GET['patient_id'])): ?>
        <!-- Search Patient -->
        <div class="card">
            <div class="card-header">
                <h3>Find Patient</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="search-form">
                    <div class="search-group">
                        <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>

                <?php if ($searchTerm): ?>
                    <div class="search-results">
                        <h4>Search Results (<?php echo count($searchResults); ?> found)</h4>
                        <?php if (empty($searchResults)): ?>
                            <p class="text-muted">No patients found.</p>
                        <?php else: ?>
                            <div class="patient-list">
                                <?php foreach ($searchResults as $patient): ?>
                                    <div class="patient-item">
                                        <div class="patient-info">
                                            <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong><br>
                                            <small><?php echo $patient['email']; ?> | <?php echo $patient['phoneNumber']; ?></small>
                                        </div>
                                        <a href="?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-primary btn-sm">
                                            View Bills
                                        </a>
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
        <!-- Patient Bills -->
        <div class="card">
            <div class="card-header">
                <h3>Pending Bills for <?php echo htmlspecialchars($selectedPatient['name']); ?></h3>
            </div>
            <div class="card-body">
                <?php if (empty($patientBills)): ?>
                    <p class="text-muted">No pending bills for this patient.</p>
                    <a href="process-payment.php" class="btn btn-outline">Back to Search</a>
                <?php else: ?>
                    <div class="bill-list">
                        <?php foreach ($patientBills as $bill): ?>
                            <div class="bill-item">
                                <div class="bill-info">
                                    <strong>Bill #<?php echo $bill['billId']; ?></strong><br>
                                    Date: <?php echo date('M j, Y', strtotime($bill['createdAt'])); ?><br>
                                    <?php if ($bill['appointmentDate']): ?>
                                        Appointment: <?php echo date('M j, Y', strtotime($bill['appointmentDate'])); ?><br>
                                    <?php endif; ?>
                                    Amount: <strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong><br>
                                    Due Date: <?php echo date('M j, Y', strtotime($bill['dueDate'])); ?><br>
                                    <?php if ($bill['description']): ?>
                                        Description: <?php echo nl2br(htmlspecialchars($bill['description'])); ?>
                                    <?php endif; ?>
                                </div>
                                <a href="?bill_id=<?php echo $bill['billId']; ?>" class="btn btn-primary">
                                    Process Payment
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="action-buttons" style="margin-top: 20px;">
                        <a href="process-payment.php" class="btn btn-outline">Search Another Patient</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($billId && $bill): ?>
        <!-- Payment Form -->
        <div class="card">
            <div class="card-header">
                <h3>Process Payment - Bill #<?php echo $bill['billId']; ?></h3>
            </div>
            <div class="card-body">
                <div class="bill-details">
                    <p><strong>Patient:</strong> <?php echo htmlspecialchars($bill['patientName']); ?></p>
                    <p><strong>Email:</strong> <?php echo $bill['patientEmail']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $bill['patientPhone']; ?></p>
                    <p><strong>Amount Due:</strong> <strong style="color: #dc3545;">$<?php echo number_format($bill['totalAmount'], 2); ?></strong></p>
                    <p><strong>Due Date:</strong> <?php echo date('M j, Y', strtotime($bill['dueDate'])); ?></p>
                    <?php if ($bill['description']): ?>
                        <p><strong>Description:</strong></p>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 5px;">
                            <?php echo nl2br(htmlspecialchars($bill['description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="" class="payment-form" id="payment-form">
                    <input type="hidden" name="bill_id" value="<?php echo $bill['billId']; ?>">
                    
                    <div class="form-group">
                        <label for="amount_paid">Amount to Pay *</label>
                        <input type="number" id="amount_paid" name="amount_paid" step="0.01" 
                               value="<?php echo $bill['totalAmount']; ?>" required>
                        <small>Full amount: $<?php echo number_format($bill['totalAmount'], 2); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Insurance">Insurance</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Additional notes about the payment..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="process_payment" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Process Payment
                        </button>
                        <a href="process-payment.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>