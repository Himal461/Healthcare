<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Billing Management - HealthManagement";
include '../includes/header.php';

// Handle billing actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_bill'])) {
        $patientId = $_POST['patient_id'];
        $appointmentId = $_POST['appointment_id'] ?: null;
        $amount = floatval($_POST['amount']);
        $tax = floatval($_POST['tax']);
        $discount = floatval($_POST['discount']);
        $totalAmount = $amount + $tax - $discount;
        $description = sanitizeInput($_POST['description']);
        $dueDate = $_POST['due_date'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO billing (patientId, appointmentId, amount, tax, discount, totalAmount, description, dueDate, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$patientId, $appointmentId, $amount, $tax, $discount, $totalAmount, $description, $dueDate]);
            $_SESSION['success'] = "Bill created successfully!";
            logAction($_SESSION['user_id'], 'CREATE_BILL', "Created bill for patient ID: $patientId");
            header("Location: billing.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to create bill: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_payment'])) {
        $billId = $_POST['bill_id'];
        $status = $_POST['status'];
        $paymentMethod = sanitizeInput($_POST['payment_method']);
        
        try {
            $stmt = $pdo->prepare("UPDATE billing SET status = ?, paymentMethod = ?, paymentDate = NOW() WHERE billId = ?");
            $stmt->execute([$status, $paymentMethod, $billId]);
            $_SESSION['success'] = "Payment updated successfully!";
            logAction($_SESSION['user_id'], 'UPDATE_PAYMENT', "Updated payment for bill ID: $billId");
            header("Location: billing.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to update payment: " . $e->getMessage();
        }
    }
}

// Get all bills
$bills = $pdo->query("
    SELECT b.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           a.dateTime as appointmentDate
    FROM billing b
    JOIN patients p ON b.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
    ORDER BY b.createdAt DESC
")->fetchAll();

// Get patients for dropdown
$patients = $pdo->query("
    SELECT p.patientId, CONCAT(u.firstName, ' ', u.lastName) as name
    FROM patients p
    JOIN users u ON p.userId = u.userId
")->fetchAll();

// Get appointments for dropdown
$appointments = $pdo->query("
    SELECT a.appointmentId, CONCAT(u.firstName, ' ', u.lastName) as patientName, a.dateTime
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.status = 'completed'
")->fetchAll();

// Calculate totals
$totalPending = $pdo->query("SELECT SUM(totalAmount) as total FROM billing WHERE status = 'pending'")->fetch()['total'] ?? 0;
$totalPaid = $pdo->query("SELECT SUM(totalAmount) as total FROM billing WHERE status = 'paid'")->fetch()['total'] ?? 0;
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Billing Management</h1>
        <p>Manage patient bills and payments</p>
    </div>

    <!-- Financial Summary -->
    <div class="stats-grid">
        <div class="stat-card admin">
            <h3>$<?php echo number_format($totalPending, 2); ?></h3>
            <p>Pending Payments</p>
        </div>
        <div class="stat-card admin">
            <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
            <p>Total Paid</p>
        </div>
        <div class="stat-card admin">
            <h3>$<?php echo number_format($totalPending + $totalPaid, 2); ?></h3>
            <p>Total Revenue</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Create Bill Form -->
    <div class="card">
        <div class="card-header">
            <h3>Create New Bill</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form" id="billForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patientId']; ?>"><?php echo $patient['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_id">Associated Appointment</label>
                        <select id="appointment_id" name="appointment_id">
                            <option value="">Select appointment</option>
                            <?php foreach ($appointments as $appointment): ?>
                                <option value="<?php echo $appointment['appointmentId']; ?>">
                                    <?php echo $appointment['patientName']; ?> - <?php echo date('M j, Y', strtotime($appointment['dateTime'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount ($) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" required onchange="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label for="tax">Tax ($)</label>
                        <input type="number" id="tax" name="tax" step="0.01" value="0" onchange="calculateTotal()">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="discount">Discount ($)</label>
                        <input type="number" id="discount" name="discount" step="0.01" value="0" onchange="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date *</label>
                        <input type="date" id="due_date" name="due_date" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Services rendered..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Total Amount</label>
                    <input type="text" id="total_amount" readonly style="background: #f8f9fa; font-weight: bold;">
                </div>
                
                <button type="submit" name="create_bill" class="btn btn-primary">Create Bill</button>
            </form>
        </div>
    </div>

    <!-- Bills List -->
    <div class="card">
        <div class="card-header">
            <h3>All Bills</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Amount</th>
                        <th>Tax</th>
                        <th>Discount</th>
                        <th>Total</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php foreach ($bills as $bill): ?>
                        <tr>
                            <td data-label="Bill ID">#<?php echo $bill['billId']; ?></td>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($bill['createdAt'])); ?></td>
                            <td data-label="Patient"><?php echo $bill['patientName']; ?></td>
                            <td data-label="Amount">$<?php echo number_format($bill['amount'], 2); ?></td>
                            <td data-label="Tax">$<?php echo number_format($bill['tax'], 2); ?></td>
                            <td data-label="Discount">$<?php echo number_format($bill['discount'], 2); ?></td>
                            <td data-label="Total"><strong>$<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                            <td data-label="Due Date"><?php echo date('M j, Y', strtotime($bill['dueDate'])); ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?php echo $bill['status']; ?>">
                                    <?php echo ucfirst($bill['status']); ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <?php if ($bill['status'] === 'pending'): ?>
                                    <button class="btn btn-primary btn-sm" onclick="openModal('paymentModal'); document.getElementById('payment_bill_id').value = <?php echo $bill['billId']; ?>;">
                                        Record Payment
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Record Payment</h3>
            <span class="close" onclick="closeModal('paymentModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="bill_id" id="payment_bill_id">
                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="Cash">Cash</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit Card">Debit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Insurance">Insurance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment_status">Status *</label>
                    <select id="payment_status" name="status" required>
                        <option value="paid">Paid</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_payment" class="btn btn-primary">Record Payment</button>
                <button type="button" class="btn" onclick="closeModal('paymentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('billForm')?.addEventListener('load', function() {
    calculateTotal();
});
</script>

<?php include '../includes/footer.php'; ?>