<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "Revenue Report - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

// Get date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Revenue from bills
$stmt = $pdo->prepare("
    SELECT 
        DATE(generatedAt) as date,
        COUNT(*) as bill_count,
        SUM(consultationFee) as consultation_total,
        SUM(additionalCharges) as additional_total,
        SUM(serviceCharge) as service_total,
        SUM(gst) as gst_total,
        SUM(totalAmount) as total_amount
    FROM bills
    WHERE DATE(generatedAt) BETWEEN ? AND ?
    GROUP BY DATE(generatedAt)
    ORDER BY date DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$dailyRevenue = $stmt->fetchAll();

// Revenue summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bills,
        SUM(totalAmount) as total_revenue,
        SUM(CASE WHEN status = 'paid' THEN totalAmount ELSE 0 END) as paid_revenue,
        SUM(CASE WHEN status = 'unpaid' THEN totalAmount ELSE 0 END) as unpaid_revenue,
        SUM(CASE WHEN status = 'cancelled' THEN totalAmount ELSE 0 END) as cancelled_revenue
    FROM bills
    WHERE DATE(generatedAt) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$summary = $stmt->fetch();

// Expenses (salaries)
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total_salaries
    FROM salary_payments
    WHERE DATE(paymentDate) BETWEEN ? AND ? AND status = 'paid'
");
$stmt->execute([$dateFrom, $dateTo]);
$totalSalaries = $stmt->fetchColumn() ?? 0;

// Net profit
$netProfit = ($summary['paid_revenue'] ?? 0) - $totalSalaries;

// Monthly comparison
$monthlyStmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(generatedAt, '%Y-%m') as month,
        SUM(totalAmount) as revenue,
        SUM(CASE WHEN status = 'paid' THEN totalAmount ELSE 0 END) as paid
    FROM bills
    WHERE generatedAt >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(generatedAt, '%Y-%m')
    ORDER BY month DESC
");
$monthlyStmt->execute();
$monthlyData = $monthlyStmt->fetchAll();
?>

<div class="accountant-container">
    <div class="accountant-page-header">
        <div class="header-title">
            <h1><i class="fas fa-chart-line"></i> Revenue Report</h1>
            <p>Financial overview and analysis</p>
        </div>
        <form method="GET" class="accountant-date-filter">
            <input type="date" name="date_from" value="<?php echo $dateFrom; ?>">
            <span>to</span>
            <input type="date" name="date_to" value="<?php echo $dateTo; ?>">
            <button type="submit" class="accountant-btn accountant-btn-primary">Filter</button>
            <a href="revenue.php" class="accountant-btn accountant-btn-outline">Reset</a>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="accountant-stats-grid">
        <div class="accountant-stat-card revenue">
            <div class="accountant-stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></h3>
                <p>Total Billed</p>
                <small><?php echo $summary['total_bills'] ?? 0; ?> bills</small>
            </div>
        </div>
        <div class="accountant-stat-card paid">
            <div class="accountant-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($summary['paid_revenue'] ?? 0, 2); ?></h3>
                <p>Paid Revenue</p>
                <small>Collected</small>
            </div>
        </div>
        <div class="accountant-stat-card unpaid">
            <div class="accountant-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($summary['unpaid_revenue'] ?? 0, 2); ?></h3>
                <p>Outstanding</p>
                <small>Pending payment</small>
            </div>
        </div>
        <div class="accountant-stat-card expenses">
            <div class="accountant-stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($totalSalaries, 2); ?></h3>
                <p>Salary Expenses</p>
                <small>Period total</small>
            </div>
        </div>
        <div class="accountant-stat-card net">
            <div class="accountant-stat-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format(abs($netProfit), 2); ?></h3>
                <p>Net <?php echo $netProfit >= 0 ? 'Profit' : 'Loss'; ?></p>
                <small><?php echo $dateFrom; ?> to <?php echo $dateTo; ?></small>
            </div>
        </div>
    </div>

    <!-- Daily Revenue Table -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-calendar-alt"></i> Daily Revenue</h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-table-responsive">
                <table class="accountant-data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bills</th>
                            <th>Consultation</th>
                            <th>Additional</th>
                            <th>Service Charge</th>
                            <th>GST</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyRevenue)): ?>
                            <tr><td colspan="7" class="accountant-empty-message">No revenue data for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dailyRevenue as $day): ?>
                                <tr>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($day['date'])); ?></td>
                                    <td data-label="Bills"><?php echo $day['bill_count']; ?></td>
                                    <td data-label="Consultation">$<?php echo number_format($day['consultation_total'] ?? 0, 2); ?></td>
                                    <td data-label="Additional">$<?php echo number_format($day['additional_total'] ?? 0, 2); ?></td>
                                    <td data-label="Service Charge">$<?php echo number_format($day['service_total'] ?? 0, 2); ?></td>
                                    <td data-label="GST">$<?php echo number_format($day['gst_total'] ?? 0, 2); ?></td>
                                    <td data-label="Total"><strong>$<?php echo number_format($day['total_amount'] ?? 0, 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($dailyRevenue)): ?>
                        <tfoot>
                            <tr class="total-row">
                                <td><strong>Total</strong></td>
                                <td><?php echo $summary['total_bills'] ?? 0; ?></td>
                                <td>$<?php echo number_format(array_sum(array_column($dailyRevenue, 'consultation_total')), 2); ?></td>
                                <td>$<?php echo number_format(array_sum(array_column($dailyRevenue, 'additional_total')), 2); ?></td>
                                <td>$<?php echo number_format(array_sum(array_column($dailyRevenue, 'service_total')), 2); ?></td>
                                <td>$<?php echo number_format(array_sum(array_column($dailyRevenue, 'gst_total')), 2); ?></td>
                                <td><strong>$<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Monthly Comparison -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-chart-bar"></i> Monthly Revenue Comparison</h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-table-responsive">
                <table class="accountant-data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Revenue</th>
                            <th>Paid Amount</th>
                            <th>Collection Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyData as $month): 
                            $collectionRate = $month['revenue'] > 0 ? round(($month['paid'] / $month['revenue']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td data-label="Month"><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                <td data-label="Total Revenue">$<?php echo number_format($month['revenue'] ?? 0, 2); ?></td>
                                <td data-label="Paid Amount">$<?php echo number_format($month['paid'] ?? 0, 2); ?></td>
                                <td data-label="Collection Rate">
                                    <div class="accountant-progress-bar">
                                        <div class="accountant-progress-fill" style="width: <?php echo $collectionRate; ?>%"></div>
                                        <span><?php echo $collectionRate; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>