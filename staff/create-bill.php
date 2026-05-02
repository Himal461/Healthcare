<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Create Bill - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
// Include billing JS
$extraJS = '<script src="../js/billing.js"></script>';
include '../includes/header.php';

$appointmentId = $_GET['appointment_id'] ?? 0;
$patientId = $_GET['patient_id'] ?? 0;
$appointment = null;

if ($appointmentId) {
    $stmt = $pdo->prepare("SELECT a.*, CONCAT(u.firstName, ' ', u.lastName) as patientName, d.consultationFee FROM appointments a JOIN patients p ON a.patientId = p.patientId JOIN users u ON p.userId = u.userId JOIN doctors d ON a.doctorId = d.doctorId WHERE a.appointmentId = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    $patientId = $appointment['patientId'];
}

if ($patientId) {
    $stmt = $pdo->prepare("SELECT CONCAT(firstName, ' ', lastName) as name FROM users u JOIN patients p ON u.userId = p.userId WHERE p.patientId = ? AND u.role = 'patient'");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
}

if (!$patientId || !isset($patient)) {
    $_SESSION['error'] = "Patient not found.";
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_bill'])) {
    $consultationFee = floatval($_POST['consultation_fee']);
    $additionalTotal = floatval($_POST['additional_charges']);
    $subtotal = $consultationFee + $additionalTotal;
    $serviceCharge = round($subtotal * 0.03, 2);
    $gst = round($subtotal * 0.13, 2);
    $totalAmount = round($subtotal + $serviceCharge + $gst, 2);
    
    // Handle additional charges breakdown
    $additionalChargesArray = [];
    if (!empty($_POST['charge_name']) && !empty($_POST['charge_amount'])) {
        foreach ($_POST['charge_name'] as $i => $name) {
            $name = trim($name);
            $amt = floatval($_POST['charge_amount'][$i] ?? 0);
            if ($amt > 0 && $name !== '') {
                $additionalChargesArray[$name] = $amt;
            }
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO bills (patientId, appointmentId, consultationFee, additionalCharges, serviceCharge, gst, totalAmount, status, generatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid', NOW())");
        $stmt->execute([$patientId, $appointmentId ?: null, $consultationFee, $additionalTotal, $serviceCharge, $gst, $totalAmount]);
        $billId = $pdo->lastInsertId();
        
        // Insert additional charges breakdown
        if (!empty($additionalChargesArray)) {
            $chargeStmt = $pdo->prepare("INSERT INTO bill_charges (billId, chargeName, amount) VALUES (?, ?, ?)");
            foreach ($additionalChargesArray as $name => $amount) {
                $chargeStmt->execute([$billId, $name, $amount]);
            }
        }
        
        $pdo->commit();
        
        // Send bill generated email
        sendBillGeneratedEmail($billId);
        
        // Create notification for patient
        $patientStmt = $pdo->prepare("SELECT u.userId FROM patients p JOIN users u ON p.userId = u.userId WHERE p.patientId = ?");
        $patientStmt->execute([$patientId]);
        $patientUser = $patientStmt->fetch();
        
        if ($patientUser) {
            createNotification(
                $patientUser['userId'],
                'billing',
                'New Bill Generated',
                "A new bill of $" . number_format($totalAmount, 2) . " has been generated for your consultation.",
                "../patient/view-bill.php?bill_id=" . $billId
            );
        }
        
        // Add to finance ledger
        addFinanceTransaction('revenue', 'bill_generated', $totalAmount, $billId, "Bill generated for patient ID: $patientId");
        
        $_SESSION['success'] = "Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " created successfully! Total: $" . number_format($totalAmount, 2);
        logAction($_SESSION['user_id'], 'CREATE_BILL', "Created bill #$billId for patient $patientId, Amount: $totalAmount");
        header("Location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to create bill: " . $e->getMessage();
    }
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$defaultFee = $appointment['consultationFee'] ?? 0;
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-receipt"></i> Create Bill</h1>
            <p><?php echo htmlspecialchars($patient['name'] ?? 'Patient'); ?></p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="staff-alert staff-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="staff-alert staff-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="staff-card">
        <div class="staff-card-header">
            <h3><i class="fas fa-calculator"></i> Bill Details</h3>
        </div>
        <div class="staff-card-body">
            <form method="POST" id="bill-form">
                <div class="staff-form-row">
                    <div class="staff-form-group">
                        <label for="consultation_fee">Consultation Fee ($) <span class="required">*</span></label>
                        <input type="number" 
                               name="consultation_fee" 
                               id="consultation_fee" 
                               step="0.01" 
                               min="0"
                               value="<?php echo $defaultFee; ?>" 
                               class="staff-form-control" 
                               required>
                    </div>
                    
                    <div class="staff-form-group">
                        <label for="additional_charges">Additional Charges Total ($)</label>
                        <input type="number" 
                               name="additional_charges" 
                               id="additional_charges" 
                               step="0.01" 
                               min="0"
                               value="0" 
                               class="staff-form-control">
                    </div>
                </div>
                
                <!-- Additional Charges Breakdown -->
                <div style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="color: #1e293b; margin: 0;">Additional Charges Breakdown</h4>
                        <button type="button" class="staff-btn staff-btn-outline staff-btn-sm" onclick="addChargeRow()">
                            <i class="fas fa-plus"></i> Add Charge
                        </button>
                    </div>
                    <div id="charges-container">
                        <div class="charge-item" style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="text" name="charge_name[]" placeholder="Charge name (e.g., Lab Test)" class="staff-form-control" style="flex: 2;">
                            <input type="number" name="charge_amount[]" placeholder="Amount ($)" step="0.01" min="0" class="staff-form-control" style="flex: 1;" oninput="updateAdditionalTotal()">
                            <button type="button" class="staff-btn staff-btn-danger staff-btn-sm" onclick="removeChargeRow(this)">✕</button>
                        </div>
                    </div>
                </div>
                
                <!-- Bill Summary -->
                <div style="background: #f8fafc; padding: 25px; border-radius: 14px; margin-top: 25px;">
                    <h4 style="color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-invoice" style="color: #f59e0b;"></i> Bill Summary
                    </h4>
                    
                    <table style="width: 100%; max-width: 450px; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; color: #64748b;">Consultation Fee:</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: 500;">
                                $<span id="display_consultation">0.00</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #64748b;">Additional Charges:</td>
                            <td style="padding: 8px 0; text-align: right; font-weight: 500;">
                                $<span id="display_additional">0.00</span>
                            </td>
                        </tr>
                        <tr style="border-top: 1px solid #e2e8f0;">
                            <td style="padding: 12px 0; font-weight: 600; color: #1e293b;">Subtotal:</td>
                            <td style="padding: 12px 0; text-align: right; font-weight: 600;">
                                $<span id="display_subtotal">0.00</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #64748b;">Service Charge (3%):</td>
                            <td style="padding: 8px 0; text-align: right;">
                                $<span id="display_service">0.00</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #64748b;">GST (13%):</td>
                            <td style="padding: 8px 0; text-align: right;">
                                $<span id="display_gst">0.00</span>
                            </td>
                        </tr>
                        <tr style="border-top: 2px solid #f59e0b;">
                            <td style="padding: 15px 0; font-size: 18px; font-weight: 700; color: #f59e0b;">Total Amount:</td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; font-weight: 700; color: #f59e0b;">
                                $<span id="display_total">0.00</span>
                            </td>
                        </tr>
                    </table>
                    
                    <small style="display: block; margin-top: 15px; color: #64748b;">
                        <i class="fas fa-info-circle"></i> Service charge (3%) and GST (13%) are automatically calculated.
                    </small>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" name="create_bill" class="staff-btn staff-btn-primary">
                        <i class="fas fa-save"></i> Create Bill
                    </button>
                    <a href="dashboard.php" class="staff-btn staff-btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Additional functions for charge rows
function addChargeRow() {
    const container = document.getElementById('charges-container');
    const div = document.createElement('div');
    div.className = 'charge-item';
    div.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
    div.innerHTML = `
        <input type="text" name="charge_name[]" placeholder="Charge name (e.g., Lab Test)" class="staff-form-control" style="flex: 2;">
        <input type="number" name="charge_amount[]" placeholder="Amount ($)" step="0.01" min="0" class="staff-form-control" style="flex: 1;" oninput="updateAdditionalTotal()">
        <button type="button" class="staff-btn staff-btn-danger staff-btn-sm" onclick="removeChargeRow(this)">✕</button>
    `;
    container.appendChild(div);
}

function removeChargeRow(btn) {
    const row = btn.closest('.charge-item');
    if (document.querySelectorAll('.charge-item').length > 1) {
        row.remove();
        updateAdditionalTotal();
    } else {
        // Clear the inputs instead of removing the last row
        const inputs = row.querySelectorAll('input');
        inputs.forEach(input => input.value = '');
        updateAdditionalTotal();
    }
}

function updateAdditionalTotal() {
    let total = 0;
    document.querySelectorAll('input[name="charge_amount[]"]').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('additional_charges').value = total.toFixed(2);
    
    // Trigger the main calculation
    if (typeof window.calculateBillTotal === 'function') {
        window.calculateBillTotal();
    }
}

// Override the calculate function to include additional charges
document.addEventListener('DOMContentLoaded', function() {
    // Wait for billing.js to load
    setTimeout(function() {
        if (typeof window.calculateBillTotal === 'function') {
            const originalCalculate = window.calculateBillTotal;
            window.calculateBillTotal = function() {
                // Update additional charges total from breakdown
                updateAdditionalTotal();
                return originalCalculate();
            };
            window.calculateBillTotal();
        }
    }, 100);
});
</script>

<?php 
// Add extra JS if not already added
if (!isset($extraJS)) {
    echo '<script src="../js/billing.js"></script>';
}
include '../includes/footer.php'; 
?>