<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "Finance Overview - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

// Get date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Revenue from bills
$stmt = $pdo->prepare("
    SELECT 
        SUM(totalAmount) as total_billed,
        SUM(CASE WHEN status = 'paid' THEN totalAmount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'unpaid' THEN totalAmount ELSE 0 END) as total_unpaid,
        COUNT(*) as bill_count,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN status = 'unpaid' THEN 1 END) as unpaid_count
    FROM bills
    WHERE DATE(generatedAt) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$billSummary = $stmt->fetch();

// Salary expenses
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total_salaries, COUNT(*) as salary_count
    FROM salary_payments
    WHERE DATE(paymentDate) BETWEEN ? AND ? AND status = 'paid'
");
$stmt->execute([$dateFrom, $dateTo]);
$salarySummary = $stmt->fetch();

// Total revenue from finance ledger
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN transactionType = 'revenue' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN transactionType = 'expense' THEN amount ELSE 0 END) as total_expenses
    FROM hospital_finance
    WHERE DATE(transactionDate) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$financeSummary = $stmt->fetch();

$netBalance = ($financeSummary['total_revenue'] ?? 0) - ($financeSummary['total_expenses'] ?? 0);

// Recent transactions
$transactions = $pdo->prepare("
    SELECT * FROM hospital_finance 
    WHERE DATE(transactionDate) BETWEEN ? AND ?
    ORDER BY transactionDate DESC 
    LIMIT 20
");
$transactions->execute([$dateFrom, $dateTo]);
$recentTransactions = $transactions->fetchAll();

// Monthly chart data
$monthlyStmt = $pdo->query("
    SELECT 
        DATE_FORMAT(transactionDate, '%Y-%m') as month,
        SUM(CASE WHEN transactionType = 'revenue' THEN amount ELSE 0 END) as revenue,
        SUM(CASE WHEN transactionType = 'expense' THEN amount ELSE 0 END) as expenses
    FROM hospital_finance
    WHERE transactionDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(transactionDate, '%Y-%m')
    ORDER BY month ASC
");
$monthlyData = $monthlyStmt->fetchAll();
?>

<div class="accountant-container">
    <div class="accountant-page-header">
        <div class="header-title">
            <h1><i class="fas fa-chart-pie"></i> Finance Overview</h1>
            <p>Complete financial summary and analytics</p>
        </div>
        <form method="GET" class="accountant-date-filter">
            <input type="date" name="date_from" value="<?php echo $dateFrom; ?>">
            <span>to</span>
            <input type="date" name="date_to" value="<?php echo $dateTo; ?>">
            <button type="submit" class="accountant-btn accountant-btn-primary">Filter</button>
            <a href="finance.php" class="accountant-btn accountant-btn-outline">Reset</a>
        </form>
    </div>

    <div class="accountant-stats-grid">
        <div class="accountant-stat-card revenue">
            <div class="accountant-stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($financeSummary['total_revenue'] ?? 0, 2); ?></h3>
                <p>Total Revenue</p>
                <small>All income</small>
            </div>
        </div>
        <div class="accountant-stat-card expenses">
            <div class="accountant-stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($financeSummary['total_expenses'] ?? 0, 2); ?></h3>
                <p>Total Expenses</p>
                <small>All costs</small>
            </div>
        </div>
        <div class="accountant-stat-card salaries">
            <div class="accountant-stat-icon"><i class="fas fa-users"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($salarySummary['total_salaries'] ?? 0, 2); ?></h3>
                <p>Salary Expenses</p>
                <small><?php echo $salarySummary['salary_count'] ?? 0; ?> payments</small>
            </div>
        </div>
        <div class="accountant-stat-card net">
            <div class="accountant-stat-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format(abs($netBalance), 2); ?></h3>
                <p>Net Balance</p>
                <small><?php echo $netBalance >= 0 ? 'Surplus' : 'Deficit'; ?></small>
            </div>
        </div>
    </div>

    <div class="accountant-bills-grid">
        <div class="accountant-bill-card">
            <div class="accountant-bill-icon billed"><i class="fas fa-file-invoice"></i></div>
            <div class="accountant-bill-info">
                <h3>$<?php echo number_format($billSummary['total_billed'] ?? 0, 2); ?></h3>
                <p>Total Billed</p>
                <small><?php echo $billSummary['bill_count'] ?? 0; ?> bills</small>
            </div>
        </div>
        <div class="accountant-bill-card">
            <div class="accountant-bill-icon paid"><i class="fas fa-check-circle"></i></div>
            <div class="accountant-bill-info">
                <h3>$<?php echo number_format($billSummary['total_paid'] ?? 0, 2); ?></h3>
                <p>Total Paid</p>
                <small><?php echo $billSummary['paid_count'] ?? 0; ?> paid bills</small>
            </div>
        </div>
        <div class="accountant-bill-card">
            <div class="accountant-bill-icon unpaid"><i class="fas fa-clock"></i></div>
            <div class="accountant-bill-info">
                <h3>$<?php echo number_format($billSummary['total_unpaid'] ?? 0, 2); ?></h3>
                <p>Outstanding</p>
                <small><?php echo $billSummary['unpaid_count'] ?? 0; ?> unpaid bills</small>
            </div>
        </div>
    </div>

    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-history"></i> Recent Transactions</h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-table-responsive">
                <table class="accountant-data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTransactions)): ?>
                            <tr><td colspan="5" class="accountant-empty-message">No transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentTransactions as $t): ?>
                                <tr>
                                    <td data-label="Date"><?php echo date('M j, Y g:i A', strtotime($t['transactionDate'])); ?></td>
                                    <td data-label="Type">
                                        <span class="<?php echo $t['transactionType'] == 'revenue' ? 'accountant-text-success' : 'accountant-text-danger'; ?>">
                                            <?php echo ucfirst($t['transactionType']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Category"><?php echo ucfirst(str_replace('_', ' ', $t['category'])); ?></td>
                                    <td data-label="Description"><?php echo htmlspecialchars($t['description'] ?: '-'); ?></td>
                                    <td data-label="Amount" class="<?php echo $t['transactionType'] == 'revenue' ? 'accountant-text-success' : 'accountant-text-danger'; ?>">
                                        $<?php echo number_format($t['amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-chart-line"></i> Monthly Trend</h3>
        </div>
        <div class="accountant-card-body">
            <canvas id="financeChart" style="max-height: 300px; width: 100%;"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('financeChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php foreach($monthlyData as $d): ?>'<?php echo date('M Y', strtotime($d['month'].'-01')); ?>',<?php endforeach; ?>],
        datasets: [
            {
                label: 'Revenue',
                data: [<?php foreach($monthlyData as $d): ?><?php echo $d['revenue'] ?? 0; ?>,<?php endforeach; ?>],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true
            },
            {
                label: 'Expenses',
                data: [<?php foreach($monthlyData as $d): ?><?php echo $d['expenses'] ?? 0; ?>,<?php endforeach; ?>],
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            tooltip: { callbacks: { label: function(ctx) { return ctx.dataset.label + ': $' + ctx.raw.toFixed(2); } } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return '$' + value; } } } }
    }
});
</script>

<?php include '../includes/footer.php'; ?>