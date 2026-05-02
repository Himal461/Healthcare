<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
checkAuth();

$salaryId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if (!$salaryId) {
    $_SESSION['error'] = "Invalid salary record ID.";
    header("Location: " . getMySalaryLink($userRole));
    exit();
}

// Get salary payment details with verification that it belongs to the user
$stmt = $pdo->prepare("
    SELECT sp.*, 
           CONCAT(u.firstName, ' ', u.lastName) as employeeName,
           u.email as employeeEmail,
           u.phoneNumber as employeePhone,
           s.department,
           s.position,
           s.hireDate,
           CONCAT(pu.firstName, ' ', pu.lastName) as paidByName,
           pu.email as paidByEmail
    FROM salary_payments sp
    JOIN users u ON sp.userId = u.userId
    LEFT JOIN staff s ON sp.staffId = s.staffId
    LEFT JOIN users pu ON sp.paidBy = pu.userId
    WHERE sp.salaryId = ? AND sp.userId = ?
");
$stmt->execute([$salaryId, $userId]);
$salary = $stmt->fetch();

if (!$salary) {
    $_SESSION['error'] = "Salary record not found or you don't have permission to view it.";
    header("Location: " . getMySalaryLink($userRole));
    exit();
}

// Get current salary config for comparison
$configStmt = $pdo->prepare("
    SELECT baseSalary 
    FROM staff_salary_config 
    WHERE staffId = ? 
        AND effectiveFrom <= ? 
        AND (effectiveTo IS NULL OR effectiveTo >= ?)
    ORDER BY effectiveFrom DESC 
    LIMIT 1
");
$configStmt->execute([$salary['staffId'], $salary['paymentDate'], $salary['paymentDate']]);
$salaryConfig = $configStmt->fetch();

$baseAmount = $salary['amount'];
$estimatedTax = round($baseAmount * 0.15, 2); // 15% estimated tax
$netAmount = $baseAmount - $estimatedTax;

// Helper function to get my-salary link based on role
function getMySalaryLink($role) {
    $links = [
        'doctor' => 'doctor/my-salary.php',
        'nurse' => 'nurse/my-salary.php',
        'staff' => 'staff/my-salary.php',
        'accountant' => 'accountant/my-salary.php',
        'admin' => 'admin/salaries.php'
    ];
    return $links[$role] ?? 'dashboard.php';
}

$pageTitle = "Salary Details - " . date('F Y', strtotime($salary['salaryMonth'])) . " - HealthManagement";
include 'includes/header.php';
?>

<div class="salary-details-container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-invoice-dollar"></i> Salary Details</h1>
            <p><?php echo date('F Y', strtotime($salary['salaryMonth'])); ?> Payment</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo getMySalaryLink($userRole); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Salary History
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Payslip
            </button>
        </div>
    </div>

    <!-- Payslip Card -->
    <div class="payslip-card">
        <div class="payslip-header">
            <div class="company-info">
                <h2>HealthManagement System</h2>
                <p>Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                <p>Phone: +61 438 347 3483 | Email: himalkumarkari@gmail.com</p>
                <p>ABN: 12 345 678 901</p>
            </div>
            <div class="payslip-title">
                <h1>PAYSLIP</h1>
                <div class="payslip-badge status-paid">
                    <i class="fas fa-check-circle"></i> PAID
                </div>
            </div>
        </div>

        <div class="payslip-details">
            <div class="detail-section">
                <h3><i class="fas fa-user"></i> Employee Information</h3>
                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="label">Employee Name:</span>
                        <span class="value"><?php echo htmlspecialchars($salary['employeeName']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Employee ID:</span>
                        <span class="value">EMP-<?php echo str_pad($salary['userId'], 4, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($salary['employeeEmail']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Phone:</span>
                        <span class="value"><?php echo htmlspecialchars($salary['employeePhone'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Department:</span>
                        <span class="value"><?php echo htmlspecialchars($salary['department'] ?: ucfirst($salary['role'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Position:</span>
                        <span class="value"><?php echo htmlspecialchars($salary['position'] ?: ucfirst($salary['role'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Role:</span>
                        <span class="value"><span class="role-badge role-<?php echo $salary['role']; ?>"><?php echo ucfirst($salary['role']); ?></span></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Hire Date:</span>
                        <span class="value"><?php echo $salary['hireDate'] ? date('F j, Y', strtotime($salary['hireDate'])) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h3><i class="fas fa-calendar-alt"></i> Payment Information</h3>
                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="label">Payslip ID:</span>
                        <span class="value">#PAY-<?php echo str_pad($salary['salaryId'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Salary Month:</span>
                        <span class="value"><?php echo date('F Y', strtotime($salary['salaryMonth'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Payment Date:</span>
                        <span class="value"><?php echo date('F j, Y', strtotime($salary['paymentDate'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Payment Time:</span>
                        <span class="value"><?php echo date('g:i A', strtotime($salary['paymentDate'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Payment Method:</span>
                        <span class="value">Bank Transfer</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Processed By:</span>
                        <span class="value"><?php echo htmlspecialchars($salary['paidByName'] ?? 'System'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status:</span>
                        <span class="value"><span class="status-badge status-paid">Paid</span></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Reference Number:</span>
                        <span class="value">SAL-<?php echo date('Ym', strtotime($salary['salaryMonth'])); ?>-<?php echo str_pad($salary['userId'], 4, '0', STR_PAD_LEFT); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="salary-breakdown">
            <h3><i class="fas fa-calculator"></i> Salary Breakdown</h3>
            <table class="breakdown-table">
                <thead>
                    <tr>
                        <th>Earnings</th>
                        <th class="text-right">Amount ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Base Salary</td>
                        <td class="text-right">$<?php echo number_format($baseAmount, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Gross Pay</strong></td>
                        <td class="text-right"><strong>$<?php echo number_format($baseAmount, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <table class="breakdown-table deductions">
                <thead>
                    <tr>
                        <th>Deductions</th>
                        <th class="text-right">Amount ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Income Tax (Estimated 15%)</td>
                        <td class="text-right text-danger">- $<?php echo number_format($estimatedTax, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Total Deductions</strong></td>
                        <td class="text-right text-danger"><strong>- $<?php echo number_format($estimatedTax, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <div class="net-pay">
                <span class="net-label">Net Pay:</span>
                <span class="net-amount">$<?php echo number_format($netAmount, 2); ?></span>
            </div>
            <p class="net-pay-note">* Net pay is estimated after standard deductions. Actual take-home pay may vary.</p>
        </div>

        <?php if ($salary['notes']): ?>
        <div class="notes-section">
            <h3><i class="fas fa-sticky-note"></i> Notes</h3>
            <div class="notes-content">
                <?php echo nl2br(htmlspecialchars($salary['notes'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="payslip-footer">
            <div class="footer-note">
                <p>This is a computer-generated payslip and does not require a signature.</p>
                <p>For any queries, please contact the Finance Department.</p>
            </div>
            <div class="footer-signature">
                <div class="signature-line">
                    <span>Authorized Signature</span>
                    <div class="line"></div>
                </div>
                <div class="signature-line">
                    <span>Date</span>
                    <div class="line"></div>
                </div>
            </div>
            <div class="generated-info">
                Generated on: <?php echo date('F j, Y g:i A'); ?>
            </div>
        </div>
    </div>
</div>

<style>
.salary-details-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 30px 25px;
    background: #f5f7fa;
    min-height: 100vh;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 5px;
}

.page-header h1 i {
    color: #1a75bc;
    background: white;
    padding: 14px;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(26, 117, 188, 0.15);
}

.page-header p {
    color: #475569;
    margin-left: 60px;
    font-size: 16px;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Payslip Card */
.payslip-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.payslip-header {
    padding: 30px 35px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 20px;
}

.company-info h2 {
    color: #1a75bc;
    font-size: 24px;
    margin-bottom: 8px;
}

.company-info p {
    color: #475569;
    margin: 3px 0;
    font-size: 14px;
}

.payslip-title {
    text-align: right;
}

.payslip-title h1 {
    font-size: 36px;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: 3px;
    margin-bottom: 10px;
}

.payslip-badge {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 40px;
    font-size: 16px;
    font-weight: 700;
}

.status-paid {
    background: #dcfce7;
    color: #166534;
}

/* Details Section */
.payslip-details {
    padding: 30px 35px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    border-bottom: 1px solid #e2e8f0;
}

.detail-section h3 {
    color: #1e293b;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
}

.detail-section h3 i {
    color: #1a75bc;
}

.detail-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-row .label {
    font-weight: 600;
    color: #64748b;
    min-width: 130px;
}

.detail-row .value {
    color: #1e293b;
    font-weight: 500;
    text-align: right;
}

/* Role Badge */
.role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.role-doctor { background: #dbeafe; color: #1e40af; }
.role-nurse { background: #e0e7ff; color: #3730a3; }
.role-staff { background: #fef3c7; color: #92400e; }
.role-accountant { background: #dcfce7; color: #166534; }
.role-admin { background: #fee2e2; color: #991b1b; }

/* Salary Breakdown */
.salary-breakdown {
    padding: 30px 35px;
    border-bottom: 1px solid #e2e8f0;
}

.salary-breakdown h3 {
    color: #1e293b;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.breakdown-table {
    width: 100%;
    max-width: 400px;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.breakdown-table th {
    text-align: left;
    padding: 10px 0;
    color: #64748b;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.breakdown-table td {
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}

.breakdown-table .total-row {
    border-top: 2px solid #cbd5e1;
    font-weight: 700;
}

.breakdown-table .total-row td {
    border-bottom: none;
    padding-top: 15px;
    color: #1e293b;
}

.text-right {
    text-align: right;
}

.text-danger {
    color: #dc2626 !important;
}

.deductions {
    margin-top: 10px;
}

.net-pay {
    background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%);
    padding: 20px 25px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    max-width: 400px;
}

.net-label {
    color: white;
    font-size: 18px;
    font-weight: 600;
    opacity: 0.95;
}

.net-amount {
    color: white;
    font-size: 36px;
    font-weight: 800;
}

.net-pay-note {
    margin-top: 15px;
    color: #64748b;
    font-size: 13px;
    font-style: italic;
}

/* Notes Section */
.notes-section {
    padding: 25px 35px;
    border-bottom: 1px solid #e2e8f0;
    background: #fefce8;
}

.notes-section h3 {
    color: #854d0e;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.notes-content {
    color: #713f12;
    line-height: 1.6;
}

/* Footer */
.payslip-footer {
    padding: 25px 35px;
    background: #f8fafc;
}

.footer-note {
    text-align: center;
    color: #64748b;
    font-size: 13px;
    margin-bottom: 25px;
}

.footer-note p {
    margin: 3px 0;
}

.footer-signature {
    display: flex;
    justify-content: space-around;
    gap: 40px;
    margin-bottom: 20px;
}

.signature-line {
    flex: 1;
    text-align: center;
}

.signature-line span {
    color: #64748b;
    font-size: 13px;
    display: block;
    margin-bottom: 8px;
}

.signature-line .line {
    height: 1px;
    background: #cbd5e1;
    width: 100%;
}

.generated-info {
    text-align: center;
    color: #94a3b8;
    font-size: 12px;
    margin-top: 20px;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #1a75bc;
    color: white;
    box-shadow: 0 4px 10px rgba(26, 117, 188, 0.2);
}

.btn-primary:hover {
    background: #0a5a9a;
    transform: translateY(-2px);
}

.btn-outline {
    background: white;
    border: 2px solid #cbd5e1;
    color: #475569;
}

.btn-outline:hover {
    border-color: #1a75bc;
    color: #1a75bc;
}

/* Print Styles */
@media print {
    body {
        background: white;
    }
    
    .salary-details-container {
        padding: 20px;
        background: white;
    }
    
    .page-header,
    .header-actions,
    .btn {
        display: none !important;
    }
    
    .payslip-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .payslip-header {
        background: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .net-pay {
        background: #1a75bc !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .salary-details-container {
        padding: 20px 15px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header p {
        margin-left: 0;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .header-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    .payslip-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    
    .payslip-title {
        text-align: center;
    }
    
    .payslip-details {
        grid-template-columns: 1fr;
        padding: 20px;
    }
    
    .salary-breakdown {
        padding: 20px;
    }
    
    .breakdown-table {
        max-width: 100%;
    }
    
    .net-pay {
        max-width: 100%;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .detail-row .value {
        text-align: left;
    }
    
    .footer-signature {
        flex-direction: column;
        gap: 20px;
    }
    
    .notes-section {
        padding: 20px;
    }
    
    .payslip-footer {
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .page-header h1 {
        font-size: 24px;
    }
    
    .payslip-title h1 {
        font-size: 28px;
    }
    
    .net-amount {
        font-size: 28px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>