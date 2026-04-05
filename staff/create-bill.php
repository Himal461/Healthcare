<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Create Bill - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$appointmentId = $_GET['appointment_id'] ?? 0;
$patientId = $_GET['patient_id'] ?? 0;

// Get appointment details
if ($appointmentId) {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               d.consultationFee
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        JOIN doctors d ON a.doctorId = d.doctorId
        WHERE a.appointmentId = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    $patientId = $appointment['patientId'];
}

// Get patient details
$stmt = $pdo->prepare("
    SELECT CONCAT(firstName, ' ', lastName) as name 
    FROM users u 
    JOIN patients p ON u.userId = p.userId 
    WHERE p.patientId = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_bill'])) {
    $amount = floatval($_POST['amount']);
    $tax = floatval($_POST['tax']);
    $discount = floatval($_POST['discount']);
    $totalAmount = $amount + $tax - $discount;
    $description = sanitizeInput($_POST['description']);
    $dueDate = $_POST['due_date'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO billing (patientId, appointmentId, amount, tax, discount, totalAmount, description, dueDate, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$patientId, $appointmentId ?: null, $amount, $tax, $discount, $totalAmount, $description, $dueDate]);
        
        $_SESSION['success'] = "Bill created successfully!";
        logAction($userId, 'CREATE_BILL', "Created bill for patient ID: $patientId");
        
        header("Location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $error = "Failed to create bill: " . $e->getMessage();
    }
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Create Bill</h1>
        <p>Create a new bill for <?php echo htmlspecialchars($patient['name']); ?></p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="" class="bill-form" id="bill-form">
                <div class="form-group">
                    <label for="amount">Amount ($) *</label>
                    <input type="number" id="amount" name="amount" step="0.01" 
                           value="<?php echo $appointment['consultationFee'] ?? 0; ?>" required onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label for="tax">Tax ($)</label>
                    <input type="number" id="tax" name="tax" step="0.01" value="0" onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label for="discount">Discount ($)</label>
                    <input type="number" id="discount" name="discount" step="0.01" value="0" onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label>Total Amount</label>
                    <input type="text" id="total_amount" readonly style="background: #f8f9fa; font-weight: bold;">
                </div>
                
                <div class="form-group">
                    <label for="due_date">Due Date *</label>
                    <input type="date" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Consultation fee, lab tests, etc."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="create_bill" class="btn btn-primary">Create Bill</button>
                    <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calculateTotal() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const tax = parseFloat(document.getElementById('tax').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const total = amount + tax - discount;
    document.getElementById('total_amount').value = '$' + total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php include '../includes/footer.php'; ?>