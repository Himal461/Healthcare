<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "My Salary - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get doctor's salary information
$stmt = $pdo->prepare("
    SELECT u.firstName, u.lastName, u.email, u.role,
           s.position, s.department, s.hireDate, s.salary,
           s.updatedAt as salary_updated,
           d.specialization, d.consultationFee, d.yearsOfExperience
    FROM users u
    JOIN staff s ON u.userId = s.userId
    LEFT JOIN doctors d ON s.staffId = d.staffId
    WHERE u.userId = ? AND u.role = 'doctor'
");
$stmt->execute([$userId]);
$staffData = $stmt->fetch();

if (!$staffData) {
    $_SESSION['error'] = "Doctor profile not found.";
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

$currentSalary = $staffData['salary'] ?? 5000.00;
$totalEarned = array_sum(array_column($salaryHistory, 'amount'));
$paidCount = count($salaryHistory);
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-wallet"></i> My Salary</h1>
            <p>View your salary information and payment history</p>
        </div>
    </div>

    <!-- Current Salary Card -->
    <div class="doctor-current-salary-card">
        <div class="doctor-salary-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="doctor-salary-details">
            <h2>Current Monthly Salary</h2>
            <div class="doctor-salary-amount">$<?php echo number_format($currentSalary, 2); ?></div>
            <p><?php echo htmlspecialchars($staffData['specialization'] ?? 'Doctor'); ?> · 
               <?php echo $staffData['yearsOfExperience'] ?? 0; ?>+ years experience</p>
        </div>
        <div class="doctor-salary-stats">
            <div class="doctor-salary-stat">
                <span class="stat-label">Total Earned</span>
                <span class="stat-value">$<?php echo number_format($totalEarned, 2); ?></span>
            </div>
            <div class="doctor-salary-stat">
                <span class="stat-label">Payments Received</span>
                <span class="stat-value"><?php echo $paidCount; ?></span>
            </div>
        </div>
    </div>

    <!-- Employee Information Card -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-user-md"></i> Employee Information</h3>
        </div>
        <div class="doctor-card-body">
            <div class="doctor-patient-info-grid">
                <div class="doctor-info-group">
                    <h4>Personal Details</h4>
                    <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($staffData['firstName'] . ' ' . $staffData['lastName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($staffData['email']); ?></p>
                    <p><strong>Role:</strong> <span class="doctor-role-badge">Doctor</span></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($staffData['specialization'] ?: 'N/A'); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($staffData['department'] ?: 'N/A'); ?></p>
                    <p><strong>Hire Date:</strong> <?php echo $staffData['hireDate'] ? date('F j, Y', strtotime($staffData['hireDate'])) : 'N/A'; ?></p>
                </div>
                <div class="doctor-info-group">
                    <h4>Salary Information</h4>
                    <p><strong>Current Monthly Salary:</strong> <span style="color: #2563eb; font-size: 20px; font-weight: 700;">$<?php echo number_format($currentSalary, 2); ?></span></p>
                    <p><strong>Annual Salary:</strong> $<?php echo number_format($currentSalary * 12, 2); ?></p>
                    <p><strong>Consultation Fee:</strong> $<?php echo number_format($staffData['consultationFee'] ?? 0, 2); ?></p>
                    <p><strong>Total Payments Received:</strong> <?php echo $paidCount; ?></p>
                    <p><strong>Total Earned:</strong> $<?php echo number_format($totalEarned, 2); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo $staffData['salary_updated'] ? date('F j, Y', strtotime($staffData['salary_updated'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary History -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-history"></i> Salary Payment History</h3>
        </div>
        <div class="doctor-card-body">
            <?php if ($paidCount === 0): ?>
                <div class="doctor-empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No salary payments recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="doctor-table-responsive">
                    <table class="doctor-data-table">
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
                                        <a href="../view-salary-details.php?id=<?php echo $payment['salaryId']; ?>" class="doctor-btn doctor-btn-primary doctor-btn-sm">
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
.doctor-role-badge {
    display: inline-block;
    background: #2563eb;
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
</style>

<?php include '../includes/footer.php'; ?>