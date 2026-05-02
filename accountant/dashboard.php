<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "Accountant Dashboard - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get accountant details
$stmt = $pdo->prepare("
    SELECT s.*, CONCAT(u.firstName, ' ', u.lastName) as accountantName
    FROM staff s
    JOIN users u ON s.userId = u.userId
    WHERE u.userId = ? AND u.role = 'accountant'
");
$stmt->execute([$userId]);
$accountant = $stmt->fetch();

// Financial statistics
$totalRevenue = getTotalRevenue();
$totalExpenses = getTotalExpenses();
$totalSalaries = getTotalSalariesPaid();
$netBalance = getNetBalance();

// Bill statistics
$totalBilled = $pdo->query("SELECT SUM(totalAmount) FROM bills")->fetchColumn() ?? 0;
$totalPaid = $pdo->query("SELECT SUM(totalAmount) FROM bills WHERE status = 'paid'")->fetchColumn() ?? 0;
$totalUnpaid = $pdo->query("SELECT SUM(totalAmount) FROM bills WHERE status = 'unpaid'")->fetchColumn() ?? 0;
$pendingBillsCount = $pdo->query("SELECT COUNT(*) FROM bills WHERE status = 'unpaid'")->fetchColumn();

// Staff counts
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM doctors WHERE isAvailable = 1")->fetchColumn();
$totalNurses = $pdo->query("SELECT COUNT(*) FROM nurses")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();

// Recent salary payments
$recentSalaries = $pdo->query("
    SELECT sp.*, CONCAT(u.firstName, ' ', u.lastName) as employeeName,
           CONCAT(pu.firstName, ' ', pu.lastName) as paidByName
    FROM salary_payments sp
    JOIN users u ON sp.userId = u.userId
    LEFT JOIN users pu ON sp.paidBy = pu.userId
    ORDER BY sp.paymentDate DESC
    LIMIT 10
")->fetchAll();

// Recent bills
$recentBills = $pdo->query("
    SELECT b.*, CONCAT(u.firstName, ' ', u.lastName) as patientName
    FROM bills b
    JOIN patients p ON b.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    ORDER BY b.generatedAt DESC
    LIMIT 10
")->fetchAll();

// Pending salaries this month
$currentMonth = date('Y-m');
$pendingSalaries = $pdo->prepare("
    SELECT s.*, u.firstName, u.lastName, u.role,
           COALESCE(ssc.baseSalary, 
               CASE 
                   WHEN u.role = 'doctor' THEN 5000.00
                   WHEN u.role = 'nurse' THEN 3500.00
                   WHEN u.role = 'staff' THEN 2500.00
                   WHEN u.role = 'accountant' THEN 4000.00
                   ELSE 3000.00
               END
           ) as baseSalary
    FROM staff s
    JOIN users u ON s.userId = u.userId
    LEFT JOIN staff_salary_config ssc ON s.staffId = ssc.staffId 
        AND ssc.effectiveFrom <= CURDATE() 
        AND (ssc.effectiveTo IS NULL OR ssc.effectiveTo >= CURDATE())
    WHERE u.role IN ('doctor', 'nurse', 'staff', 'accountant')
    AND NOT EXISTS (
        SELECT 1 FROM salary_payments sp 
        WHERE sp.userId = u.userId AND sp.salaryMonth = ?
    )
");
$pendingSalaries->execute([$currentMonth]);
$pendingSalariesList = $pendingSalaries->fetchAll();
$pendingSalariesCount = count($pendingSalariesList);
$pendingSalariesTotal = array_sum(array_column($pendingSalariesList, 'baseSalary'));

// Display messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="accountant-container">
    <!-- Welcome Section -->
    <div class="accountant-welcome-section">
        <div class="accountant-welcome-text">
            <h1>Welcome, <?php echo htmlspecialchars($accountant['accountantName'] ?? 'Accountant'); ?>!</h1>
            <p>Financial Overview & Management Dashboard</p>
        </div>
        <div class="accountant-date-display">
            <i class="fas fa-calendar-alt"></i>
            <span><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="accountant-alert accountant-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="accountant-alert accountant-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Financial Summary Cards -->
    <div class="accountant-stats-grid">
        <div class="accountant-stat-card revenue">
            <div class="accountant-stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($totalRevenue, 2); ?></h3>
                <p>Total Revenue</p>
                <small>All time</small>
            </div>
        </div>
        <div class="accountant-stat-card expenses">
            <div class="accountant-stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($totalExpenses, 2); ?></h3>
                <p>Total Expenses</p>
                <small>All time</small>
            </div>
        </div>
        <div class="accountant-stat-card salaries">
            <div class="accountant-stat-icon"><i class="fas fa-users"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($totalSalaries, 2); ?></h3>
                <p>Salaries Paid</p>
                <small>All time</small>
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

    <!-- Billing Summary -->
    <div class="accountant-bills-grid">
        <div class="accountant-bill-card">
            <div class="accountant-bill-icon billed"><i class="fas fa-file-invoice"></i></div>
            <div class="accountant-bill-info">
                <h3>$<?php echo number_format($totalBilled, 2); ?></h3>
                <p>Total Billed</p>
            </div>
        </div>
        <div class="accountant-bill-card">
            <div class="accountant-bill-icon paid"><i class="fas fa-check-circle"></i></div>
            <div class="accountant-bill-info">
                <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                <p>Total Paid</p>
            </div>
        </div>
        <div class="accountant-bill-card">
            <div class="accountant-bill-icon unpaid"><i class="fas fa-clock"></i></div>
            <div class="accountant-bill-info">
                <h3>$<?php echo number_format($totalUnpaid, 2); ?></h3>
                <p>Outstanding</p>
                <small><?php echo $pendingBillsCount; ?> pending bills</small>
            </div>
        </div>
    </div>

    <!-- Staff Summary -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-users"></i> Staff Overview</h3>
            <a href="staff.php" class="accountant-view-all">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-stats-grid" style="margin-bottom: 0;">
                <div class="accountant-stat-card staff">
                    <div class="accountant-stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="accountant-stat-content">
                        <h3><?php echo $totalDoctors; ?></h3>
                        <p>Doctors</p>
                    </div>
                </div>
                <div class="accountant-stat-card staff">
                    <div class="accountant-stat-icon"><i class="fas fa-user-nurse"></i></div>
                    <div class="accountant-stat-content">
                        <h3><?php echo $totalNurses; ?></h3>
                        <p>Nurses</p>
                    </div>
                </div>
                <div class="accountant-stat-card staff">
                    <div class="accountant-stat-icon"><i class="fas fa-user-tie"></i></div>
                    <div class="accountant-stat-content">
                        <h3><?php echo $totalStaff; ?></h3>
                        <p>Staff</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-quick-actions-grid">
                <a href="salaries.php" class="accountant-action-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Process Salaries</span>
                    <?php if ($pendingSalariesCount > 0): ?>
                        <span class="badge"><?php echo $pendingSalariesCount; ?> pending</span>
                    <?php endif; ?>
                </a>
                <a href="revenue.php" class="accountant-action-card">
                    <i class="fas fa-chart-pie"></i>
                    <span>Revenue Report</span>
                </a>
                <a href="bills.php" class="accountant-action-card">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>View Bills</span>
                </a>
                <a href="my-salary.php" class="accountant-action-card">
                    <i class="fas fa-wallet"></i>
                    <span>My Salary</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Pending Salaries Alert -->
    <?php if ($pendingSalariesCount > 0): ?>
        <div class="accountant-alert accountant-alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?php echo $pendingSalariesCount; ?> salary payment(s) pending</strong> for <?php echo date('F Y'); ?> totaling $<?php echo number_format($pendingSalariesTotal, 2); ?>
            <a href="salaries.php?month=<?php echo $currentMonth; ?>" class="accountant-btn accountant-btn-warning accountant-btn-sm" style="margin-left: auto;">Process Now</a>
        </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <div class="accountant-activity-grid">
        <div class="accountant-activity-card">
            <div class="accountant-card-header" style="padding: 0 0 15px 0; background: transparent; border-bottom: 1px solid #eef2f6;">
                <h3><i class="fas fa-history"></i> Recent Salary Payments</h3>
                <a href="salaries.php" class="accountant-view-all">View All</a>
            </div>
            <div class="accountant-activity-list">
                <?php if (empty($recentSalaries)): ?>
                    <p class="accountant-empty-message">No recent salary payments.</p>
                <?php else: ?>
                    <?php foreach (array_slice($recentSalaries, 0, 5) as $salary): ?>
                        <div class="accountant-activity-item">
                            <div class="accountant-activity-icon salary">
                                <i class="fas fa-money-bill"></i>
                            </div>
                            <div class="accountant-activity-content">
                                <p><strong><?php echo htmlspecialchars($salary['employeeName']); ?></strong> (<?php echo ucfirst($salary['role']); ?>)</p>
                                <small>$<?php echo number_format($salary['amount'], 2); ?> - <?php echo date('M j, Y', strtotime($salary['paymentDate'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="accountant-activity-card">
            <div class="accountant-card-header" style="padding: 0 0 15px 0; background: transparent; border-bottom: 1px solid #eef2f6;">
                <h3><i class="fas fa-receipt"></i> Recent Bills</h3>
                <a href="bills.php" class="accountant-view-all">View All</a>
            </div>
            <div class="accountant-activity-list">
                <?php if (empty($recentBills)): ?>
                    <p class="accountant-empty-message">No recent bills.</p>
                <?php else: ?>
                    <?php foreach (array_slice($recentBills, 0, 5) as $bill): ?>
                        <div class="accountant-activity-item">
                            <div class="accountant-activity-icon <?php echo $bill['status']; ?>">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="accountant-activity-content">
                                <p><strong><?php echo htmlspecialchars($bill['patientName']); ?></strong></p>
                                <small>Bill #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?> - $<?php echo number_format($bill['totalAmount'], 2); ?> (<?php echo ucfirst($bill['status']); ?>)</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>