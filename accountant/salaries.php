<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "Salary Management - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Get selected month filter
$selectedMonth = $_GET['month'] ?? date('Y-m');
$months = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[$month] = date('F Y', strtotime($month));
}

// Get all staff with their salary info for selected month
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

// Calculate totals
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

<div class="accountant-container">
    <div class="accountant-page-header">
        <div class="header-title">
            <h1><i class="fas fa-money-bill-wave"></i> Salary Management</h1>
            <p>Process and manage staff salaries</p>
        </div>
        <div class="header-actions">
            <form method="GET" class="accountant-month-selector">
                <select name="month" onchange="this.form.submit()">
                    <?php foreach ($months as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $selectedMonth == $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="revenue.php" class="accountant-btn accountant-btn-outline">
                <i class="fas fa-chart-bar"></i> View Revenue
            </a>
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

    <!-- Summary Cards -->
    <div class="accountant-stats-grid">
        <div class="accountant-stat-card staff">
            <div class="accountant-stat-icon"><i class="fas fa-users"></i></div>
            <div class="accountant-stat-content">
                <h3><?php echo count($staffList); ?></h3>
                <p>Total Staff</p>
            </div>
        </div>
        <div class="accountant-stat-card pending">
            <div class="accountant-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($totalPending, 2); ?></h3>
                <p>Pending Salaries</p>
            </div>
        </div>
        <div class="accountant-stat-card paid">
            <div class="accountant-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="accountant-stat-content">
                <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                <p>Paid This Month</p>
            </div>
        </div>
    </div>

    <!-- Staff Salary Table -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-list"></i> Staff Salaries - <?php echo date('F Y', strtotime($selectedMonth)); ?></h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-table-responsive">
                <table class="accountant-data-table">
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
                                    <span class="accountant-role-badge accountant-role-<?php echo $staff['role']; ?>">
                                        <?php echo ucfirst($staff['role']); ?>
                                    </span>
                                </td>
                                <td data-label="Department"><?php echo htmlspecialchars($staff['department'] ?: '-'); ?></td>
                                <td data-label="Position"><?php echo htmlspecialchars($staff['position'] ?: '-'); ?></td>
                                <td data-label="Base Salary"><strong>$<?php echo number_format($staff['baseSalary'], 2); ?></strong></td>
                                <td data-label="Status">
                                    <?php if ($staff['paidAmount']): ?>
                                        <span class="accountant-status-badge accountant-status-paid">Paid</span>
                                        <br><small><?php echo date('M j, Y', strtotime($staff['paidDate'])); ?></small>
                                    <?php else: ?>
                                        <span class="accountant-status-badge accountant-status-unpaid">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <?php if (!$staff['paidAmount']): ?>
                                        <button class="accountant-btn accountant-btn-success accountant-btn-sm" onclick="openProcessModal(<?php echo htmlspecialchars(json_encode($staff)); ?>)">
                                            <i class="fas fa-check"></i> Process
                                        </button>
                                    <?php endif; ?>
                                    <button class="accountant-btn accountant-btn-outline accountant-btn-sm" onclick="openConfigModal(<?php echo htmlspecialchars(json_encode($staff)); ?>)">
                                        <i class="fas fa-cog"></i> Configure
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Salary History -->
    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-history"></i> Recent Salary Payments</h3>
        </div>
        <div class="accountant-card-body">
            <?php if (empty($salaryHistory)): ?>
                <p class="accountant-empty-message">No salary payments recorded for this month.</p>
            <?php else: ?>
                <div class="accountant-table-responsive">
                    <table class="accountant-data-table">
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Process Payment Modal -->
<div id="processModal" class="accountant-modal">
    <div class="accountant-modal-content">
        <div class="accountant-modal-header">
            <h3>Process Salary Payment</h3>
            <span class="accountant-modal-close" onclick="closeModal('processModal')">&times;</span>
        </div>
        <form method="POST" action="process-salary.php">
            <div class="accountant-modal-body">
                <input type="hidden" name="userId" id="process_userId">
                <input type="hidden" name="staffId" id="process_staffId">
                <input type="hidden" name="role" id="process_role">
                <input type="hidden" name="salaryMonth" id="process_salaryMonth" value="<?php echo $selectedMonth; ?>">
                
                <div class="accountant-form-group">
                    <label>Employee</label>
                    <input type="text" id="process_employeeName" class="accountant-form-control" readonly>
                </div>
                
                <div class="accountant-form-group">
                    <label>Amount ($)</label>
                    <input type="number" name="amount" id="process_amount" class="accountant-form-control" step="0.01" required>
                </div>
                
                <div class="accountant-form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" class="accountant-form-control" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
            </div>
            <div class="accountant-modal-footer">
                <button type="submit" class="accountant-btn accountant-btn-success">Process Payment</button>
                <button type="button" class="accountant-btn accountant-btn-outline" onclick="closeModal('processModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Configure Salary Modal -->
<div id="configModal" class="accountant-modal">
    <div class="accountant-modal-content">
        <div class="accountant-modal-header">
            <h3>Configure Salary</h3>
            <span class="accountant-modal-close" onclick="closeModal('configModal')">&times;</span>
        </div>
        <form method="POST" action="update-salary-config.php">
            <div class="accountant-modal-body">
                <input type="hidden" name="staffId" id="config_staffId">
                
                <div class="accountant-form-group">
                    <label>Employee</label>
                    <input type="text" id="config_employeeName" class="accountant-form-control" readonly>
                </div>
                
                <div class="accountant-form-group">
                    <label>Base Salary ($)</label>
                    <input type="number" name="baseSalary" id="config_baseSalary" class="accountant-form-control" step="0.01" required>
                </div>
                
                <div class="accountant-form-group">
                    <label>Effective From</label>
                    <input type="date" name="effectiveFrom" id="config_effectiveFrom" class="accountant-form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="accountant-form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" class="accountant-form-control" rows="2" placeholder="Reason for change..."></textarea>
                </div>
            </div>
            <div class="accountant-modal-footer">
                <button type="submit" class="accountant-btn accountant-btn-primary">Save Configuration</button>
                <button type="button" class="accountant-btn accountant-btn-outline" onclick="closeModal('configModal')">Cancel</button>
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

function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(e) { if (e.target.classList.contains('accountant-modal')) e.target.style.display = 'none'; }
</script>

<?php include '../includes/footer.php'; ?>