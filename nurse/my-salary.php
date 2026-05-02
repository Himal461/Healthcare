<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "My Salary - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get nurse's salary information
$stmt = $pdo->prepare("
    SELECT u.firstName, u.lastName, u.email, u.role,
           s.position, s.department, s.hireDate, s.salary,
           s.updatedAt as salary_updated,
           n.nursingSpecialty, n.certification
    FROM users u
    JOIN staff s ON u.userId = s.userId
    LEFT JOIN nurses n ON s.staffId = n.staffId
    WHERE u.userId = ? AND u.role = 'nurse'
");
$stmt->execute([$userId]);
$staffData = $stmt->fetch();

if (!$staffData) {
    $_SESSION['error'] = "Nurse profile not found.";
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

$currentSalary = $staffData['salary'] ?? 3500.00;
$totalEarned = array_sum(array_column($salaryHistory, 'amount'));
$paidCount = count($salaryHistory);
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-wallet"></i> My Salary</h1>
            <p>View your salary information and payment history</p>
        </div>
    </div>

    <!-- Current Salary Card -->
    <div class="nurse-card" style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white;">
        <div class="nurse-card-body" style="display: flex; align-items: center; gap: 30px; padding: 35px;">
            <div style="width: 90px; height: 90px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 42px;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <h2 style="color: white; margin: 0 0 5px 0; font-size: 20px; font-weight: 500;">Current Monthly Salary</h2>
                <div style="font-size: 48px; font-weight: 700; color: white;">$<?php echo number_format($currentSalary, 2); ?></div>
                <p style="color: rgba(255,255,255,0.85); margin: 5px 0 0;">
                    <?php echo htmlspecialchars($staffData['nursingSpecialty'] ?? 'Registered Nurse'); ?>
                </p>
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
    </div>

    <!-- Employee Information Card -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-user-nurse"></i> Employee Information</h3>
        </div>
        <div class="nurse-card-body">
            <div class="nurse-patient-info-grid">
                <div class="nurse-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($staffData['firstName'] . ' ' . $staffData['lastName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($staffData['email']); ?></p>
                    <p><strong>Role:</strong> <span class="nurse-role-badge">Nurse</span></p>
                    <p><strong>Specialty:</strong> <?php echo htmlspecialchars($staffData['nursingSpecialty'] ?: 'General Nursing'); ?></p>
                    <p><strong>Certification:</strong> <?php echo htmlspecialchars($staffData['certification'] ?: 'N/A'); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($staffData['department'] ?: 'N/A'); ?></p>
                    <p><strong>Hire Date:</strong> <?php echo $staffData['hireDate'] ? date('F j, Y', strtotime($staffData['hireDate'])) : 'N/A'; ?></p>
                </div>
                <div class="nurse-info-group">
                    <h4>Salary Information</h4>
                    <p><strong>Current Monthly Salary:</strong> <span style="color: #6f42c1; font-size: 20px; font-weight: 700;">$<?php echo number_format($currentSalary, 2); ?></span></p>
                    <p><strong>Annual Salary:</strong> $<?php echo number_format($currentSalary * 12, 2); ?></p>
                    <p><strong>Total Payments Received:</strong> <?php echo $paidCount; ?></p>
                    <p><strong>Total Earned:</strong> $<?php echo number_format($totalEarned, 2); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo $staffData['salary_updated'] ? date('F j, Y', strtotime($staffData['salary_updated'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary History -->
    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-history"></i> Salary Payment History</h3>
        </div>
        <div class="nurse-card-body">
            <?php if ($paidCount === 0): ?>
                <div class="nurse-empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No salary payments recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="nurse-table-responsive">
                    <table class="nurse-data-table">
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
                                        <a href="../view-salary-details.php?id=<?php echo $payment['salaryId']; ?>" class="nurse-btn nurse-btn-primary nurse-btn-sm">
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
.nurse-role-badge {
    display: inline-block;
    background: #6f42c1;
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
</style>

<?php include '../includes/footer.php'; ?>