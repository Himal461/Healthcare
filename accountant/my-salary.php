<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "My Salary - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get accountant's salary information
$stmt = $pdo->prepare("
    SELECT u.firstName, u.lastName, u.email, u.role,
           s.position, s.department, s.hireDate, s.salary,
           s.updatedAt as salary_updated,
           a.qualification, a.certification, a.specialization, a.yearsOfExperience
    FROM users u
    JOIN staff s ON u.userId = s.userId
    LEFT JOIN accountants a ON s.staffId = a.staffId
    WHERE u.userId = ? AND u.role = 'accountant'
");
$stmt->execute([$userId]);
$staffData = $stmt->fetch();

if (!$staffData) {
    $_SESSION['error'] = "Accountant profile not found.";
    header("Location: ../dashboard.php");
    exit();
}

// Get salary history from salary_payments
$stmt = $pdo->prepare("
    SELECT sp.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as paidByName
    FROM salary_payments sp
    LEFT JOIN users pu ON sp.paidBy = pu.userId
    WHERE sp.userId = ?
    ORDER BY sp.paymentDate DESC
");
$stmt->execute([$userId]);
$salaryHistory = $stmt->fetchAll();

$currentSalary = $staffData['salary'] ?? 4000.00;
$totalEarned = array_sum(array_column($salaryHistory, 'amount'));
$paidCount = count($salaryHistory);
?>

<div class="accountant-container">
    <div class="accountant-page-header">
        <div class="header-title">
            <h1><i class="fas fa-wallet"></i> My Salary</h1>
            <p>View your salary information and payment history</p>
        </div>
    </div>

    <!-- Current Salary Card -->
    <div class="accountant-current-salary-card">
        <div class="accountant-salary-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="accountant-salary-details">
            <h2>Current Monthly Salary</h2>
            <div class="accountant-salary-amount">$<?php echo number_format($currentSalary, 2); ?></div>
            <p><?php echo htmlspecialchars($staffData['position'] ?? 'Accountant'); ?></p>
        </div>
        <div class="accountant-salary-stats">
            <div class="accountant-salary-stat">
                <span class="stat-label">Total Earned</span>
                <span class="stat-value">$<?php echo number_format($totalEarned, 2); ?></span>
            </div>
            <div class="accountant-salary-stat">
                <span class="stat-label">Payments Received</span>
                <span class="stat-value"><?php echo $paidCount; ?></span>
            </div>
        </div>
    </div>

    <!-- Employee Information Card -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-calculator"></i> Employee Information</h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-patient-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <div class="accountant-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($staffData['firstName'] . ' ' . $staffData['lastName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($staffData['email']); ?></p>
                    <p><strong>Role:</strong> <span class="accountant-role-badge">Accountant</span></p>
                    <p><strong>Qualification:</strong> <?php echo htmlspecialchars($staffData['qualification'] ?: 'N/A'); ?></p>
                    <p><strong>Certification:</strong> <?php echo htmlspecialchars($staffData['certification'] ?: 'N/A'); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($staffData['specialization'] ?: 'General Accounting'); ?></p>
                    <p><strong>Experience:</strong> <?php echo $staffData['yearsOfExperience'] ?? 0; ?>+ years</p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($staffData['department'] ?: 'Finance'); ?></p>
                    <p><strong>Hire Date:</strong> <?php echo $staffData['hireDate'] ? date('F j, Y', strtotime($staffData['hireDate'])) : 'N/A'; ?></p>
                </div>
                <div class="accountant-info-group">
                    <h4>Salary Information</h4>
                    <p><strong>Current Monthly Salary:</strong> <span style="color: #10b981; font-size: 20px; font-weight: 700;">$<?php echo number_format($currentSalary, 2); ?></span></p>
                    <p><strong>Annual Salary:</strong> $<?php echo number_format($currentSalary * 12, 2); ?></p>
                    <p><strong>Total Payments Received:</strong> <?php echo $paidCount; ?></p>
                    <p><strong>Total Earned:</strong> $<?php echo number_format($totalEarned, 2); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo $staffData['salary_updated'] ? date('F j, Y', strtotime($staffData['salary_updated'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary History -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-history"></i> Salary Payment History</h3>
        </div>
        <div class="accountant-card-body">
            <?php if ($paidCount === 0): ?>
                <div class="accountant-empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No salary payments recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="accountant-table-responsive">
                    <table class="accountant-data-table">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Salary Month</th>
                                <th>Amount</th>
                                <th>Processed By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salaryHistory as $payment): ?>
                                <tr>
                                    <td data-label="Payment Date">
                                        <?php echo date('M j, Y', strtotime($payment['paymentDate'])); ?><br>
                                        <small><?php echo date('g:i A', strtotime($payment['paymentDate'])); ?></small>
                                    </td>
                                    <td data-label="Salary Month"><?php echo date('F Y', strtotime($payment['salaryMonth'])); ?></td>
                                    <td data-label="Amount"><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td data-label="Processed By"><?php echo htmlspecialchars($payment['paidByName'] ?? 'System'); ?></td>
                                    <td data-label="Actions">
                                        <a href="../view-salary-details.php?id=<?php echo $payment['salaryId']; ?>" class="accountant-btn accountant-btn-view">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.accountant-role-badge {
    display: inline-block;
    background: #10b981;
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
.accountant-patient-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}
.accountant-info-group {
    background: #f8fafc;
    padding: 18px 20px;
    border-radius: 14px;
    border: 1px solid #eef2f6;
}
.accountant-info-group h4 {
    color: #1e293b;
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}
.accountant-info-group p {
    margin: 8px 0;
    display: flex;
    justify-content: space-between;
    font-size: 14px;
}
.accountant-info-group p strong {
    color: #475569;
    font-weight: 600;
}
@media (max-width: 768px) {
    .accountant-patient-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../includes/footer.php'; ?>