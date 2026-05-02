<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "Staff Directory - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

// Get all staff
$staff = $pdo->query("
    SELECT s.*, u.userId, u.firstName, u.lastName, u.email, u.phoneNumber, u.role,
           d.specialization, d.consultationFee,
           n.nursingSpecialty,
           COALESCE(ssc.baseSalary, 
               CASE 
                   WHEN u.role = 'doctor' THEN 5000.00
                   WHEN u.role = 'nurse' THEN 3500.00
                   WHEN u.role = 'staff' THEN 2500.00
                   WHEN u.role = 'accountant' THEN 4000.00
                   WHEN u.role = 'admin' THEN 6000.00
                   ELSE 3000.00
               END
           ) as baseSalary
    FROM staff s
    JOIN users u ON s.userId = u.userId
    LEFT JOIN doctors d ON s.staffId = d.staffId
    LEFT JOIN nurses n ON s.staffId = n.staffId
    LEFT JOIN staff_salary_config ssc ON s.staffId = ssc.staffId 
        AND ssc.effectiveFrom <= CURDATE() 
        AND (ssc.effectiveTo IS NULL OR ssc.effectiveTo >= CURDATE())
    WHERE u.role IN ('doctor', 'nurse', 'staff', 'accountant', 'admin')
    ORDER BY u.role, u.firstName
")->fetchAll();

// Group by role
$staffByRole = [];
foreach ($staff as $member) {
    $staffByRole[$member['role']][] = $member;
}

$roleOrder = ['admin', 'doctor', 'nurse', 'accountant', 'staff'];
$roleIcons = [
    'admin' => 'fa-crown',
    'doctor' => 'fa-user-md',
    'nurse' => 'fa-user-nurse',
    'accountant' => 'fa-calculator',
    'staff' => 'fa-user-tie'
];
?>

<div class="accountant-container">
    <div class="accountant-page-header">
        <div class="header-title">
            <h1><i class="fas fa-users"></i> Staff Directory</h1>
            <p>View all hospital staff members and their salary information</p>
        </div>
    </div>

    <?php foreach ($roleOrder as $role): ?>
        <?php if (!empty($staffByRole[$role])): ?>
            <div class="accountant-role-section">
                <div class="accountant-role-header">
                    <h2>
                        <span class="accountant-role-icon role-<?php echo $role; ?>">
                            <i class="fas <?php echo $roleIcons[$role]; ?>"></i>
                        </span>
                        <?php echo ucfirst($role); ?>s
                        <span class="accountant-count-badge"><?php echo count($staffByRole[$role]); ?></span>
                    </h2>
                </div>
                <div class="accountant-staff-grid">
                    <?php foreach ($staffByRole[$role] as $member): ?>
                        <div class="accountant-staff-card">
                            <div class="accountant-staff-card-header">
                                <div class="accountant-staff-avatar">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div class="accountant-staff-info">
                                    <h3><?php echo htmlspecialchars($member['firstName'] . ' ' . $member['lastName']); ?></h3>
                                    <span class="accountant-staff-role accountant-role-<?php echo $member['role']; ?>">
                                        <?php echo ucfirst($member['role']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="accountant-staff-card-body">
                                <div class="accountant-info-row">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($member['email']); ?></span>
                                </div>
                                <div class="accountant-info-row">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($member['phoneNumber'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="accountant-info-row">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($member['department'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="accountant-info-row">
                                    <i class="fas fa-briefcase"></i>
                                    <span><?php echo htmlspecialchars($member['position'] ?: 'N/A'); ?></span>
                                </div>
                                <?php if ($member['role'] == 'doctor' && $member['specialization']): ?>
                                    <div class="accountant-info-row">
                                        <i class="fas fa-stethoscope"></i>
                                        <span><?php echo htmlspecialchars($member['specialization']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($member['role'] == 'nurse' && $member['nursingSpecialty']): ?>
                                    <div class="accountant-info-row">
                                        <i class="fas fa-heartbeat"></i>
                                        <span><?php echo htmlspecialchars($member['nursingSpecialty']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="accountant-staff-card-footer">
                                <div class="accountant-salary-info">
                                    <span class="accountant-salary-label">Base Salary:</span>
                                    <span class="accountant-salary-amount">$<?php echo number_format($member['baseSalary'], 2); ?></span>
                                </div>
                                <a href="salaries.php?staff_id=<?php echo $member['staffId']; ?>" class="accountant-btn accountant-btn-outline accountant-btn-sm">
                                    <i class="fas fa-money-bill"></i> View Salary
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>