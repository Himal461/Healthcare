<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "My Salary - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get admin's salary information
$stmt = $pdo->prepare("
    SELECT u.firstName, u.lastName, u.email, u.role,
           s.position, s.department, s.hireDate, s.salary,
           s.updatedAt as salary_updated
    FROM users u
    JOIN staff s ON u.userId = s.userId
    WHERE u.userId = ? AND u.role = 'admin'
");
$stmt->execute([$userId]);
$staffData = $stmt->fetch();

if (!$staffData) {
    $_SESSION['error'] = "Admin profile not found.";
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

$currentSalary = $staffData['salary'] ?? 6000.00;
$totalEarned = array_sum(array_column($salaryHistory, 'amount'));
$paidCount = count($salaryHistory);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-wallet"></i> My Salary</h1>
            <p>View your salary information and payment history</p>
        </div>
    </div>

    <!-- Current Salary Card -->
    <div class="admin-current-salary-card" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); border-radius: 20px; padding: 35px; display: flex; align-items: center; gap: 30px; margin-bottom: 30px; color: white; box-shadow: 0 8px 25px rgba(220, 38, 38, 0.25);">
        <div style="width: 90px; height: 90px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 42px;">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div>
            <h2 style="color: white; margin: 0 0 5px 0; font-size: 20px; font-weight: 500;">Current Monthly Salary</h2>
            <div style="font-size: 48px; font-weight: 700; color: white;">$<?php echo number_format($currentSalary, 2); ?></div>
            <p style="color: rgba(255,255,255,0.85); margin: 5px 0 0;"><?php echo htmlspecialchars($staffData['position'] ?? 'Administrator'); ?></p>
        </div>
        <div style="margin-left: auto; display: flex; gap: 40px;">
            <div style="text-align: center;">
                <span style="display: block; font-size: 13px; color: rgba(255,255,255,0.85);">Total Earned</span>
                <span style="display: block; font-size: 24px; font-weight: 700; color: white;">$<?php echo number_format($totalEarned, 2); ?></span>
            </div>
            <div style="text-align: center;">
                <span style="display: block; font-size: 13px; color: rgba(255,255,255,0.85);">Payments Received</span>
                <span style="display: block; font-size: 24px; font-weight: 700; color: white;"><?php echo $paidCount; ?></span>
            </div>
        </div>
    </div>

    <!-- Employee Information Card -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-user-tie"></i> Employee Information</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-patient-info-grid">
                <div class="admin-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($staffData['firstName'] . ' ' . $staffData['lastName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($staffData['email']); ?></p>
                    <p><strong>Role:</strong> <span class="admin-role-badge admin-role-admin">Administrator</span></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($staffData['position'] ?: 'System Administrator'); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($staffData['department'] ?: 'Administration'); ?></p>
                    <p><strong>Hire Date:</strong> <?php echo $staffData['hireDate'] ? date('F j, Y', strtotime($staffData['hireDate'])) : 'N/A'; ?></p>
                </div>
                <div class="admin-info-group">
                    <h4>Salary Information</h4>
                    <p><strong>Current Monthly Salary:</strong> <span style="color: #dc2626; font-size: 20px; font-weight: 700;">$<?php echo number_format($currentSalary, 2); ?></span></p>
                    <p><strong>Annual Salary:</strong> $<?php echo number_format($currentSalary * 12, 2); ?></p>
                    <p><strong>Total Payments Received:</strong> <?php echo $paidCount; ?></p>
                    <p><strong>Total Earned:</strong> $<?php echo number_format($totalEarned, 2); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo $staffData['salary_updated'] ? date('F j, Y', strtotime($staffData['salary_updated'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary History -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-history"></i> Salary Payment History</h3>
        </div>
        <div class="admin-card-body">
            <?php if ($paidCount === 0): ?>
                <div class="admin-empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3>No Salary Payments Recorded</h3>
                    <p>Your salary payments will appear here once processed.</p>
                </div>
            <?php else: ?>
                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Salary Month</th>
                                <th>Amount</th>
                                <th>Processed By</th>
                                <th>Notes</th>
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
                                    <td data-label="Notes"><?php echo htmlspecialchars($payment['notes'] ?: '-'); ?></td>
                                    <td data-label="Actions">
                                        <a href="../view-salary-details.php?id=<?php echo $payment['salaryId']; ?>" class="admin-btn admin-btn-primary admin-btn-sm">
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

<?php include '../includes/footer.php'; ?>