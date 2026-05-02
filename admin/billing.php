<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Billing Management - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

// Handle payment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $billId = (int)$_POST['bill_id'];
    $status = $_POST['status'];
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
    
    if (updateBillStatus($billId, $status)) {
        if ($status === 'paid') {
            addFinanceTransaction('revenue', 'bill_payment', $_POST['amount'] ?? 0, $billId, "Payment received via {$paymentMethod}");
            sendPaymentConfirmationEmail($billId);
        }
        $_SESSION['success'] = "Bill status updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update bill status.";
    }
    header("Location: billing.php");
    exit();
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$searchTerm = $_GET['search'] ?? '';

$filters = [];
if ($statusFilter) $filters['status'] = $statusFilter;
if ($dateFrom) $filters['date_from'] = $dateFrom;
if ($dateTo) $filters['date_to'] = $dateTo;

$bills = getAllBills($filters);

if ($searchTerm) {
    $bills = array_filter($bills, function($bill) use ($searchTerm) {
        return stripos($bill['patientName'], $searchTerm) !== false || 
               stripos($bill['patientEmail'], $searchTerm) !== false;
    });
}

$totalUnpaid = 0;
$totalPaid = 0;
$totalCancelled = 0;
foreach ($bills as $bill) {
    if ($bill['status'] == 'unpaid') $totalUnpaid += $bill['totalAmount'];
    elseif ($bill['status'] == 'paid') $totalPaid += $bill['totalAmount'];
    elseif ($bill['status'] == 'cancelled') $totalCancelled += $bill['totalAmount'];
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-invoice-dollar"></i> Billing Management</h1>
            <p>Manage all patient bills and payments</p>
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

    <div class="admin-stats-grid">
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalUnpaid, 2); ?></h3>
                <p>Pending Payments</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                <p>Total Paid</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalUnpaid + $totalPaid, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-ban"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalCancelled, 2); ?></h3>
                <p>Cancelled</p>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-filter"></i> Filter Bills</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="admin-filter-row">
                <div class="admin-filter-group">
                    <select name="status" class="admin-form-control">
                        <option value="">All Status</option>
                        <option value="unpaid" <?php echo $statusFilter=='unpaid'?'selected':''; ?>>Unpaid</option>
                        <option value="paid" <?php echo $statusFilter=='paid'?'selected':''; ?>>Paid</option>
                        <option value="cancelled" <?php echo $statusFilter=='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="admin-filter-group">
                    <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" class="admin-form-control">
                </div>
                <div class="admin-filter-group">
                    <input type="date" name="date_to" value="<?php echo $dateTo; ?>" class="admin-form-control">
                </div>
                <div class="admin-filter-group">
                    <input type="text" name="search" placeholder="Patient name or email..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="admin-form-control">
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a href="billing.php" class="admin-btn admin-btn-outline">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Bills (<?php echo count($bills); ?>)</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Consultation</th>
                        <th>Additional</th>
                        <th>Tax</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="9" class="admin-empty-message">No bills found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bills as $bill): ?>
                            <tr class="status-<?php echo $bill['status']; ?>">
                                <td data-label="Bill #"><strong>#<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($bill['generatedAt'])); ?></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($bill['patientName']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($bill['patientEmail']); ?></small>
                                </td>
                                <td data-label="Consultation">$<?php echo number_format($bill['consultationFee'], 2); ?></td>
                                <td data-label="Additional">$<?php echo number_format($bill['additionalCharges'], 2); ?></td>
                                <td data-label="Tax">$<?php echo number_format($bill['serviceCharge'] + $bill['gst'], 2); ?></td>
                                <td data-label="Total"><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                                <td data-label="Status">
                                    <span class="admin-status-badge admin-status-<?php echo $bill['status']; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="admin-action-buttons">
                                        <a href="view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-eye"></i> View</a>
                                        <?php if ($bill['status'] == 'unpaid'): ?>
                                            <button class="admin-btn admin-btn-success admin-btn-sm" onclick="openPaymentModal(<?php echo $bill['billId']; ?>, <?php echo $bill['totalAmount']; ?>)"><i class="fas fa-check"></i> Mark Paid</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Record Payment</h3>
            <span class="admin-modal-close" onclick="closeModal('paymentModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="admin-modal-body">
                <input type="hidden" name="bill_id" id="payment_bill_id">
                <input type="hidden" name="status" value="paid">
                <input type="hidden" name="amount" id="payment_amount">
                <div class="admin-form-group">
                    <label>Bill Amount</label>
                    <input type="text" id="bill_amount_display" class="admin-form-control" readonly>
                </div>
                <div class="admin-form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="admin-form-control" required>
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" name="update_payment" class="admin-btn admin-btn-success">Confirm Payment</button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('paymentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>