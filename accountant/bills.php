<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "Bills Management - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

// Filters
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$searchTerm = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT b.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone
    FROM bills b
    JOIN patients p ON b.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE 1=1
";
$params = [];

if ($statusFilter) {
    $query .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $query .= " AND DATE(b.generatedAt) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(b.generatedAt) <= ?";
    $params[] = $dateTo;
}

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$query .= " ORDER BY b.generatedAt DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Summary statistics
$summaryStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bills,
        SUM(totalAmount) as total_amount,
        SUM(CASE WHEN status = 'paid' THEN totalAmount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN status = 'unpaid' THEN totalAmount ELSE 0 END) as unpaid_amount,
        COUNT(CASE WHEN status = 'unpaid' THEN 1 END) as unpaid_count
    FROM bills
    WHERE DATE(generatedAt) BETWEEN ? AND ?
");
$summaryStmt->execute([$dateFrom, $dateTo]);
$summary = $summaryStmt->fetch();
?>

<div class="accountant-container">
    <div class="accountant-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-invoice-dollar"></i> Bills Management</h1>
            <p>View and manage all patient bills</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="accountant-stats-grid">
        <div class="accountant-stat-card revenue">
            <div class="accountant-stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="accountant-stat-content">
                <h3><?php echo $summary['total_bills'] ?? 0; ?></h3>
                <p>Total Bills</p>
            </div>
        </div>
        <div class="accountant-stat-card revenue">
            <div class="accountant-stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h3>
                <p>Total Amount</p>
            </div>
        </div>
        <div class="accountant-stat-card paid">
            <div class="accountant-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($summary['paid_amount'] ?? 0, 2); ?></h3>
                <p>Paid Amount</p>
            </div>
        </div>
        <div class="accountant-stat-card unpaid">
            <div class="accountant-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($summary['unpaid_amount'] ?? 0, 2); ?></h3>
                <p>Outstanding</p>
                <small><?php echo $summary['unpaid_count'] ?? 0; ?> unpaid bills</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-filter"></i> Filter Bills</h3>
        </div>
        <div class="accountant-card-body">
            <form method="GET" class="accountant-filter-form">
                <div class="accountant-filter-row">
                    <div class="accountant-filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="paid" <?php echo $statusFilter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="unpaid" <?php echo $statusFilter == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="accountant-filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="accountant-filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="accountant-filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Patient name or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="accountant-filter-actions">
                        <button type="submit" class="accountant-btn accountant-btn-primary">Apply Filters</button>
                        <a href="bills.php" class="accountant-btn accountant-btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-list"></i> Bills (<?php echo count($bills); ?>)</h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-table-responsive">
                <table class="accountant-data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Consultation</th>
                            <th>Additional</th>
                            <th>Tax</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Paid Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr><td colspan="10" class="accountant-empty-message">No bills found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bills as $bill): ?>
                                <tr class="status-<?php echo $bill['status']; ?>">
                                    <td data-label="Bill #"><strong>#<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($bill['generatedAt'])); ?></td>
                                    <td data-label="Patient">
                                        <strong><?php echo htmlspecialchars($bill['patientName']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($bill['patientEmail']); ?></small>
                                    </td>
                                    <td data-label="Contact"><?php echo htmlspecialchars($bill['patientPhone']); ?></td>
                                    <td data-label="Consultation">$<?php echo number_format($bill['consultationFee'], 2); ?></td>
                                    <td data-label="Additional">$<?php echo number_format($bill['additionalCharges'], 2); ?></td>
                                    <td data-label="Tax">$<?php echo number_format($bill['serviceCharge'] + $bill['gst'], 2); ?></td>
                                    <td data-label="Total"><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                                    <td data-label="Status">
                                        <span class="accountant-status-badge accountant-status-<?php echo $bill['status']; ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Paid Date"><?php echo $bill['paidAt'] ? date('M j, Y', strtotime($bill['paidAt'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>