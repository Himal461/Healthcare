<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Bills - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: dashboard.php");
    exit();
}

$patientId = $patient['patientId'];

// Get all bills
$stmt = $pdo->prepare("
    SELECT b.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctorName,
           mr.diagnosis
    FROM bills b
    LEFT JOIN medical_records mr ON b.recordId = mr.recordId
    LEFT JOIN doctors d ON mr.doctorId = d.doctorId
    LEFT JOIN staff s ON d.staffId = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    WHERE b.patientId = ?
    ORDER BY b.generatedAt DESC
");
$stmt->execute([$patientId]);
$bills = $stmt->fetchAll();

$unpaidTotal = 0;
foreach ($bills as $bill) {
    if ($bill['status'] == 'unpaid') {
        $unpaidTotal += $bill['totalAmount'];
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <div>
            <h1>My Bills</h1>
            <p>View and manage your billing history</p>
        </div>
        <div class="summary-cards">
            <div class="summary-card">
                <span class="summary-label">Total Outstanding</span>
                <span class="summary-value">$<?php echo number_format($unpaidTotal, 2); ?></span>
            </div>
            <div class="summary-card">
                <span class="summary-label">Total Bills</span>
                <span class="summary-value"><?php echo count($bills); ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-receipt"></i> All Bills</h3>
        </div>
        <div class="card-body">
            <?php if (empty($bills)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Bills Found</h3>
                    <p>Your bills will appear here after your consultations.</p>
                    <a href="book-appointment.php" class="btn btn-primary">Book Appointment</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            32
                                <th>Bill #</th>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </thead>
                        <tbody>
                            <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td>#<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo date('M j, Y', strtotime($bill['generatedAt'])); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($bill['doctorName']); ?></td>
                                <td>
                                    <?php if ($bill['diagnosis']): ?>
                                        <?php echo substr(htmlspecialchars($bill['diagnosis']), 0, 50); ?>...
                                    <?php else: ?>
                                        Consultation Fee
                                    <?php endif; ?>
                                </td>
                                <td><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $bill['status']; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($bill['status'] == 'unpaid'): ?>
                                        <a href="view-bill.php?bill_id=<?php echo $bill['billId']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </a>
                                    <?php endif; ?>
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
.dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-header h1 {
    margin: 0;
    color: #333;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: #666;
}

.summary-cards {
    display: flex;
    gap: 15px;
}

.summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 15px 25px;
    border-radius: 8px;
    text-align: center;
    color: white;
}

.summary-label {
    display: block;
    font-size: 12px;
    opacity: 0.9;
    margin-bottom: 5px;
}

.summary-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    color: #495057;
    font-size: 18px;
}

.card-header i {
    margin-right: 8px;
    color: #1a75bc;
}

.card-body {
    padding: 20px;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.data-table tr:hover {
    background: #f8f9fa;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}

.btn-primary {
    background: #1a75bc;
    color: white;
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #0e5a92;
}

.btn-info {
    background: #17a2b8;
    color: white;
    border: none;
    transition: all 0.3s ease;
}

.btn-info:hover {
    background: #138496;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-paid {
    background: #d4edda;
    color: #155724;
}

.status-unpaid {
    background: #fff3cd;
    color: #856404;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 10px;
    color: #dee2e6;
}

.empty-state h3 {
    margin: 10px 0;
    color: #495057;
}

.empty-state p {
    margin: 0;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .summary-cards {
        width: 100%;
    }
    
    .summary-card {
        flex: 1;
    }
    
    .data-table {
        font-size: 12px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>