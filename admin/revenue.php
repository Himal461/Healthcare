<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Revenue Report - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Revenue summary
$stmt = $pdo->prepare("
    SELECT 
        SUM(totalAmount) as total_billed,
        SUM(CASE WHEN status = 'paid' THEN totalAmount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'unpaid' THEN totalAmount ELSE 0 END) as total_unpaid,
        COUNT(*) as bill_count
    FROM bills
    WHERE DATE(generatedAt) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$summary = $stmt->fetch();

// Salary expenses
$totalSalaries = getTotalSalariesPaid($dateFrom, $dateTo);
$netProfit = ($summary['total_paid'] ?? 0) - $totalSalaries;

// Daily revenue
$stmt = $pdo->prepare("
    SELECT 
        DATE(generatedAt) as date,
        COUNT(*) as bill_count,
        SUM(consultationFee) as consultation_total,
        SUM(additionalCharges) as additional_total,
        SUM(totalAmount) as total_amount
    FROM bills
    WHERE DATE(generatedAt) BETWEEN ? AND ?
    GROUP BY DATE(generatedAt)
    ORDER BY date DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$dailyRevenue = $stmt->fetchAll();

// Monthly data for chart
$monthlyStmt = $pdo->query("
    SELECT 
        DATE_FORMAT(generatedAt, '%Y-%m') as month,
        SUM(CASE WHEN status = 'paid' THEN totalAmount ELSE 0 END) as paid
    FROM bills
    WHERE generatedAt >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(generatedAt, '%Y-%m')
    ORDER BY month ASC
");
$monthlyData = $monthlyStmt->fetchAll();
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-chart-line"></i> Revenue Report</h1>
            <p>Financial overview and analysis</p>
        </div>
        <form method="GET" class="admin-date-filter">
            <input type="date" name="date_from" value="<?php echo $dateFrom; ?>">
            <span>to</span>
            <input type="date" name="date_to" value="<?php echo $dateTo; ?>">
            <button type="submit" class="admin-btn admin-btn-primary">Filter</button>
            <a href="revenue.php" class="admin-btn admin-btn-outline">Reset</a>
        </form>
    </div>

    <div class="admin-stats-grid">
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($summary['total_billed'] ?? 0, 2); ?></h3>
                <p>Total Billed</p>
                <small><?php echo $summary['bill_count'] ?? 0; ?> bills</small>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($summary['total_paid'] ?? 0, 2); ?></h3>
                <p>Total Paid</p>
                <small>Collected revenue</small>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($summary['total_unpaid'] ?? 0, 2); ?></h3>
                <p>Outstanding</p>
                <small>Pending payment</small>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalSalaries, 2); ?></h3>
                <p>Salary Expenses</p>
                <small>Period total</small>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format(abs($netProfit), 2); ?></h3>
                <p>Net <?php echo $netProfit >= 0 ? 'Profit' : 'Loss'; ?></p>
                <small><?php echo $dateFrom; ?> to <?php echo $dateTo; ?></small>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-calendar-alt"></i> Daily Revenue</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr><th>Date</th><th>Bills</th><th>Consultation</th><th>Additional</th><th>Total</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyRevenue)): ?>
                        <tr><td colspan="5" class="admin-empty-message">No revenue data for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dailyRevenue as $day): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($day['date'])); ?></td>
                                <td data-label="Bills"><?php echo $day['bill_count']; ?></td>
                                <td data-label="Consultation">$<?php echo number_format($day['consultation_total'] ?? 0, 2); ?></td>
                                <td data-label="Additional">$<?php echo number_format($day['additional_total'] ?? 0, 2); ?></td>
                                <td data-label="Total"><strong>$<?php echo number_format($day['total_amount'] ?? 0, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($dailyRevenue)): ?>
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>Total</strong></td>
                            <td><?php echo $summary['bill_count'] ?? 0; ?></td>
                            <td>$<?php echo number_format(array_sum(array_column($dailyRevenue, 'consultation_total')), 2); ?></td>
                            <td>$<?php echo number_format(array_sum(array_column($dailyRevenue, 'additional_total')), 2); ?></td>
                            <td><strong>$<?php echo number_format($summary['total_billed'] ?? 0, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-chart-bar"></i> Monthly Revenue Trend</h3>
        </div>
        <div class="admin-card-body">
            <canvas id="revenueChart" style="max-height: 300px; width: 100%;"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php foreach($monthlyData as $d): ?>'<?php echo date('M Y', strtotime($d['month'].'-01')); ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Revenue ($)',
            data: [<?php foreach($monthlyData as $d): ?><?php echo $d['paid'] ?? 0; ?>,<?php endforeach; ?>],
            borderColor: '#dc2626',
            backgroundColor: 'rgba(220, 38, 38, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function(ctx) { return '$' + ctx.raw.toFixed(2); } } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return '$' + value; } } } }
    }
});
</script>

<?php include '../includes/footer.php'; ?>