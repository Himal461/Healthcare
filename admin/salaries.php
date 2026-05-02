<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Salary Management - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

$selectedMonth = $_GET['month'] ?? date('Y-m');
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$months = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[$month] = date('F Y', strtotime($month));
}

// Get all staff with salary info
$stmt = $pdo->prepare("
    SELECT s.staffId, s.userId, u.firstName, u.lastName, u.email, u.role, s.department, s.position,
           COALESCE(ssc.baseSalary, 
               CASE 
                   WHEN u.role = 'doctor' THEN 5000.00
                   WHEN u.role = 'nurse' THEN 3500.00
                   WHEN u.role = 'staff' THEN 2500.00
                   WHEN u.role = 'accountant' THEN 4000.00
                   WHEN u.role = 'admin' THEN 6000.00
                   ELSE 3000.00
               END
           ) as baseSalary,
           (SELECT amount FROM salary_payments WHERE userId = u.userId AND salaryMonth = ?) as paidAmount,
           (SELECT paymentDate FROM salary_payments WHERE userId = u.userId AND salaryMonth = ?) as paidDate,
           (SELECT status FROM salary_payments WHERE userId = u.userId AND salaryMonth = ?) as paymentStatus
    FROM staff s
    JOIN users u ON s.userId = u.userId
    LEFT JOIN staff_salary_config ssc ON s.staffId = ssc.staffId 
        AND ssc.effectiveFrom <= CURDATE() 
        AND (ssc.effectiveTo IS NULL OR ssc.effectiveTo >= CURDATE())
    WHERE u.role IN ('doctor', 'nurse', 'staff', 'accountant', 'admin')
    ORDER BY u.role, u.firstName
");
$stmt->execute([$selectedMonth, $selectedMonth, $selectedMonth]);
$staffList = $stmt->fetchAll();

// Get salary history
$historyStmt = $pdo->prepare("
    SELECT sp.*, CONCAT(u.firstName, ' ', u.lastName) as employeeName,
           CONCAT(pu.firstName, ' ', pu.lastName) as paidByName
    FROM salary_payments sp
    JOIN users u ON sp.userId = u.userId
    LEFT JOIN users pu ON sp.paidBy = pu.userId
    WHERE sp.salaryMonth = ?
    ORDER BY sp.paymentDate DESC
");
$historyStmt->execute([$selectedMonth]);
$salaryHistory = $historyStmt->fetchAll();

$totalPending = 0;
$totalPaid = 0;
foreach ($staffList as $staff) {
    if ($staff['paidAmount']) {
        $totalPaid += $staff['baseSalary'];
    } else {
        $totalPending += $staff['baseSalary'];
    }
}
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-money-bill-wave"></i> Salary Management</h1>
            <p>Process and manage staff salaries</p>
        </div>
        <div class="header-actions">
            <form method="GET" class="admin-month-selector">
                <select name="month" onchange="this.form.submit()">
                    <?php foreach ($months as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $selectedMonth == $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="revenue.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-chart-bar"></i> View Revenue
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="admin-stats-grid">
        <div class="admin-stat-card users">
            <div class="admin-stat-icon"><i class="fas fa-users"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo count($staffList); ?></h3>
                <p>Total Staff</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalPending, 2); ?></h3>
                <p>Pending Salaries</p>
            </div>
        </div>
        <div class="admin-stat-card revenue">
            <div class="admin-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="admin-stat-content">
                <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                <p>Paid This Month</p>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> Staff Salaries - <?php echo date('F Y', strtotime($selectedMonth)); ?></h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Base Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $staff): ?>
                        <tr>
                            <td data-label="Employee">
                                <strong><?php echo htmlspecialchars($staff['firstName'] . ' ' . $staff['lastName']); ?></strong><br>
                                <small><?php echo htmlspecialchars($staff['email']); ?></small>
                            </td>
                            <td data-label="Role">
                                <span class="admin-role-badge admin-role-<?php echo $staff['role']; ?>">
                                    <?php echo ucfirst($staff['role']); ?>
                                </span>
                            </td>
                            <td data-label="Department"><?php echo htmlspecialchars($staff['department'] ?: '-'); ?></td>
                            <td data-label="Position"><?php echo htmlspecialchars($staff['position'] ?: '-'); ?></td>
                            <td data-label="Base Salary"><strong>$<?php echo number_format($staff['baseSalary'], 2); ?></strong></td>
                            <td data-label="Status">
                                <?php if ($staff['paidAmount']): ?>
                                    <span class="admin-status-badge admin-status-paid">Paid</span><br>
                                    <small><?php echo date('M j, Y', strtotime($staff['paidDate'])); ?></small>
                                <?php else: ?>
                                    <span class="admin-status-badge admin-status-unpaid">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <div class="admin-action-buttons">
                                    <?php if (!$staff['paidAmount']): ?>
                                        <button class="admin-btn admin-btn-success admin-btn-sm" onclick="openProcessModal(<?php echo htmlspecialchars(json_encode($staff)); ?>)">
                                            <i class="fas fa-check"></i> Process
                                        </button>
                                    <?php endif; ?>
                                    <button class="admin-btn admin-btn-outline admin-btn-sm" onclick="openConfigModal(<?php echo htmlspecialchars(json_encode($staff)); ?>)">
                                        <i class="fas fa-cog"></i> Configure
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-history"></i> Recent Salary Payments</h3>
        </div>
        <div class="admin-table-responsive">
            <?php if (empty($salaryHistory)): ?>
                <p class="admin-empty-message">No salary payments recorded for this month.</p>
            <?php else: ?>
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Amount</th>
                            <th>Month</th>
                            <th>Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salaryHistory as $payment): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y g:i A', strtotime($payment['paymentDate'])); ?></td>
                                <td data-label="Employee"><?php echo htmlspecialchars($payment['employeeName']); ?></td>
                                <td data-label="Role"><?php echo ucfirst($payment['role']); ?></td>
                                <td data-label="Amount"><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td data-label="Month"><?php echo date('F Y', strtotime($payment['salaryMonth'])); ?></td>
                                <td data-label="Processed By"><?php echo htmlspecialchars($payment['paidByName'] ?? 'System'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Process Payment Modal -->
<div id="processModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Process Salary Payment</h3>
            <span class="admin-modal-close" onclick="closeModal('processModal')">&times;</span>
        </div>
        <form method="POST" action="../accountant/process-salary.php">
            <div class="admin-modal-body">
                <input type="hidden" name="userId" id="process_userId">
                <input type="hidden" name="staffId" id="process_staffId">
                <input type="hidden" name="role" id="process_role">
                <input type="hidden" name="salaryMonth" id="process_salaryMonth" value="<?php echo $selectedMonth; ?>">
                <div class="admin-form-group">
                    <label>Employee</label>
                    <input type="text" id="process_employeeName" class="admin-form-control" readonly>
                </div>
                <div class="admin-form-group">
                    <label>Amount ($)</label>
                    <input type="number" name="amount" id="process_amount" class="admin-form-control" step="0.01" required>
                </div>
                <div class="admin-form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="admin-form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" class="admin-btn admin-btn-success">Process Payment</button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('processModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Configure Salary Modal -->
<div id="configModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Configure Salary</h3>
            <span class="admin-modal-close" onclick="closeModal('configModal')">&times;</span>
        </div>
        <form method="POST" action="../accountant/update-salary-config.php">
            <div class="admin-modal-body">
                <input type="hidden" name="staffId" id="config_staffId">
                <div class="admin-form-group">
                    <label>Employee</label>
                    <input type="text" id="config_employeeName" class="admin-form-control" readonly>
                </div>
                <div class="admin-form-group">
                    <label>Base Salary ($)</label>
                    <input type="number" name="baseSalary" id="config_baseSalary" class="admin-form-control" step="0.01" required>
                </div>
                <div class="admin-form-group">
                    <label>Effective From</label>
                    <input type="date" name="effectiveFrom" id="config_effectiveFrom" class="admin-form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="admin-form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="admin-form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" class="admin-btn admin-btn-primary">Save</button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('configModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openProcessModal(staff) {
    document.getElementById('process_userId').value = staff.userId;
    document.getElementById('process_staffId').value = staff.staffId;
    document.getElementById('process_role').value = staff.role;
    document.getElementById('process_employeeName').value = staff.firstName + ' ' + staff.lastName;
    document.getElementById('process_amount').value = staff.baseSalary;
    openModal('processModal');
}

function openConfigModal(staff) {
    document.getElementById('config_staffId').value = staff.staffId;
    document.getElementById('config_employeeName').value = staff.firstName + ' ' + staff.lastName;
    document.getElementById('config_baseSalary').value = staff.baseSalary;
    openModal('configModal');
}
</script>

<?php include '../includes/footer.php'; ?>