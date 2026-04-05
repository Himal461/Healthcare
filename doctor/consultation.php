<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Patient Consultation";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);

// ================= DOCTOR =================
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.consultationFee,
           CONCAT(u.firstName,' ',u.lastName) AS doctorName
    FROM doctors d
    JOIN staff s ON d.staffId=s.staffId
    JOIN users u ON s.userId=u.userId
    WHERE s.userId=?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) die("Doctor not found");

$doctorId = $doctor['doctorId'];
$consultationFee = (float)$doctor['consultationFee'];

// ================= PATIENT =================
$stmt = $pdo->prepare("
    SELECT p.*, u.userId AS patientUserId, u.firstName, u.lastName, u.email, u.phoneNumber
    FROM patients p
    JOIN users u ON p.userId=u.userId
    WHERE p.patientId=?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) die("Patient not found");

// ================= SAVE =================
$errorMessage = null;
$successMessage = null;
$newBillId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $diagnosis = trim($_POST['diagnosis']);
        if ($diagnosis === '') throw new Exception("Diagnosis required");

        // ===== MEDICAL RECORD =====
        $stmt = $pdo->prepare("
            INSERT INTO medical_records
            (patientId, doctorId, appointmentId, diagnosis, treatmentNotes, followUpDate, creationDate)
            VALUES (?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([
            $patientId,
            $doctorId,
            $appointmentId ?: null,
            $diagnosis,
            $_POST['treatment_notes'] ?? '',
            $_POST['follow_up_date'] ?: null
        ]);

        $recordId = $pdo->lastInsertId();

        // ===== PRESCRIPTIONS (without refills) =====
        if (!empty($_POST['medication_name'])) {
            $stmtPres = $pdo->prepare("
                INSERT INTO prescriptions
                (recordId, medicationName, dosage, frequency, startDate, instructions, prescribedBy, status, createdAt)
                VALUES (?,?,?,?,?,?,?, 'active', NOW())
            ");

            foreach ($_POST['medication_name'] as $i => $name) {
                $name = trim($name);
                if ($name === '') continue;

                $stmtPres->execute([
                    $recordId,
                    $name,
                    $_POST['dosage'][$i] ?? '',
                    $_POST['frequency'][$i] ?? '',
                    $_POST['start_date'][$i] ?? date('Y-m-d'),
                    $_POST['instructions'][$i] ?? '',
                    $doctorId
                ]);
            }
        }

        // ===== CHARGES =====
        $additionalTotal = 0;
        $charges = [];

        if (!empty($_POST['charge_name']) && !empty($_POST['charge_amount'])) {
            foreach ($_POST['charge_name'] as $i => $name) {
                $name = trim($name);
                $amt = (float)($_POST['charge_amount'][$i] ?? 0);
                
                if ($amt > 0 && $name !== '') {
                    $additionalTotal += $amt;
                    $charges[] = [$name, $amt];
                }
            }
        }

        $subtotal = $consultationFee + $additionalTotal;
        $service = round($subtotal * 0.03, 2);
        $gst = round($subtotal * 0.13, 2);
        $total = round($subtotal + $service + $gst, 2);

        // ===== BILL =====
        $stmtBill = $pdo->prepare("
            INSERT INTO bills
            (patientId, appointmentId, recordId, consultationFee, additionalCharges, serviceCharge, gst, totalAmount, status, generatedAt)
            VALUES (?,?,?,?,?,?,?,?, 'unpaid', NOW())
        ");
        $stmtBill->execute([
            $patientId, $appointmentId ?: null, $recordId,
            $consultationFee, $additionalTotal, $service, $gst, $total
        ]);

        $newBillId = $pdo->lastInsertId();

        // ===== BILL CHARGES =====
        if (!empty($charges)) {
            $stmtC = $pdo->prepare("INSERT INTO bill_charges (billId, chargeName, amount) VALUES (?,?,?)");
            foreach ($charges as $c) {
                $stmtC->execute([$newBillId, $c[0], $c[1]]);
            }
        }

        $pdo->commit();
        $successMessage = "Consultation saved! Bill #$newBillId generated.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="dashboard">

<div class="dashboard-header">
    <h1>Patient Consultation</h1>
    <p>Recording consultation for <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong></p>
</div>

<!-- SUCCESS / ERROR -->
<?php if ($successMessage): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
    <?php if ($newBillId): ?>
    <div style="margin-top: 10px;">
        <a href="view-bill.php?bill_id=<?php echo $newBillId; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-receipt"></i> View Bill
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
</div>
<?php endif; ?>

<!-- ================= PATIENT INFO WITH LINKS ================= -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
    </div>
    <div class="card-body">
        <div class="patient-info-grid">
            <div class="info-group">
                <h4>Personal Details</h4>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phoneNumber']); ?></p>
                <p><strong>Date of Birth:</strong> <?php echo $patient['dateOfBirth'] ?: 'N/A'; ?></p>
                <p><strong>Age:</strong> <?php echo calculateAge($patient['dateOfBirth']); ?></p>
            </div>
            <div class="info-group">
                <h4>Medical Information</h4>
                <p><strong>Allergies:</strong> <?php echo $patient['knownAllergies'] ?: 'None'; ?></p>
                <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                <p><strong>Insurance:</strong> <?php echo $patient['insuranceProvider'] ?: 'N/A'; ?></p>
            </div>
            <div class="info-group">
                <h4>Medical History</h4>
                <div class="history-links">
                    <a href="../patient/medical-records.php?patient_id=<?php echo $patientId; ?>" class="btn btn-sm btn-outline" target="_blank">
                        <i class="fas fa-notes-medical"></i> View Medical Records
                    </a>
                    <a href="../patient/prescriptions.php?patient_id=<?php echo $patientId; ?>" class="btn btn-sm btn-outline" target="_blank">
                        <i class="fas fa-prescription"></i> View Prescriptions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= FORM ================= -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-notes-medical"></i> Consultation Notes</h3>
    </div>
    <div class="card-body">
        <form method="POST" id="consultationForm">
            
            <div class="form-group">
                <label>Diagnosis <span class="required">*</span></label>
                <textarea name="diagnosis" class="form-control" rows="4" placeholder="Enter primary diagnosis..." required></textarea>
            </div>
            
            <div class="form-group">
                <label>Treatment Notes / Plan</label>
                <textarea name="treatment_notes" class="form-control" rows="4" placeholder="Treatment plan, recommendations, lifestyle advice..."></textarea>
            </div>

            <div class="form-group">
                <label>Follow-up Date (Optional)</label>
                <input type="date" name="follow_up_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
            </div>

            <!-- ================= PRESCRIPTIONS SECTION ================= -->
            <div class="section-divider">
                <h4><i class="fas fa-prescription"></i> Prescription Details</h4>
                <button type="button" class="btn btn-sm btn-outline" onclick="addMedication()">
                    <i class="fas fa-plus"></i> Add Medication
                </button>
            </div>

            <div id="medications">
                <div class="med-row">
                    <div class="med-fields">
                        <input name="medication_name[]" class="form-control" placeholder="Medication Name">
                        <input name="dosage[]" class="form-control" placeholder="Dosage">
                        <input name="frequency[]" class="form-control" placeholder="Frequency">
                        <input name="start_date[]" type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        <input name="instructions[]" class="form-control" placeholder="Instructions">
                    </div>
                    <button type="button" class="btn-delete" onclick="removeRow(this)" title="Remove medication">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <!-- ================= ADDITIONAL CHARGES SECTION ================= -->
            <div class="section-divider">
                <h4><i class="fas fa-dollar-sign"></i> Additional Charges</h4>
                <button type="button" class="btn btn-sm btn-outline" onclick="addCharge()">
                    <i class="fas fa-plus"></i> Add Charge
                </button>
            </div>

            <div id="charges">
                <div class="charge-row">
                    <div class="charge-fields">
                        <input name="charge_name[]" class="form-control" placeholder="Charge Name (e.g., Blood Test, ECG)" oninput="updateBillSummary()">
                        <input name="charge_amount[]" type="number" step="0.01" class="form-control" placeholder="Amount ($)" oninput="updateBillSummary()">
                    </div>
                    <button type="button" class="btn-delete" onclick="removeRow(this)" title="Remove charge">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <!-- ================= BILL SUMMARY ================= -->
            <div class="bill-summary">
                <h4><i class="fas fa-receipt"></i> Bill Summary</h4>
                <table class="bill-table">
                    <tr>
                        <td>Consultation Fee:</td>
                        <td class="text-right">$<?php echo number_format($consultationFee,2); ?></td>
                    </tr>
                    <tbody id="additional-charges-list"></tbody>
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-right"><strong>$<span id="subtotal"><?php echo number_format($consultationFee,2); ?></span></strong></td>
                    </tr>
                    <tr class="tax-row">
                        <td>Service Charge (3%):</td>
                        <td class="text-right">$<span id="service">0.00</span></td>
                    </tr>
                    <tr class="tax-row">
                        <td>GST (13%):</td>
                        <td class="text-right">$<span id="gst">0.00</span></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Total Amount:</strong></td>
                        <td class="text-right"><strong>$<span id="total"><?php echo number_format($consultationFee,2); ?></span></strong></td>
                    </tr>
                </table>
                <small class="text-muted">Bill will be automatically generated when you save.</small>
            </div>

            <div class="form-actions">
                <button type="submit" name="save_consultation" class="btn btn-primary btn-large">
                    <i class="fas fa-save"></i> Save Consultation & Generate Bill
                </button>
                <a href="patients.php?view=<?php echo $patientId; ?>" class="btn btn-outline">Cancel</a>
            </div>

        </form>
    </div>
</div>

</div>

<script>
let consultationFee = <?php echo $consultationFee; ?>;

function addMedication(){
    let div=document.createElement('div');
    div.className='med-row';
    div.innerHTML=`
        <div class="med-fields">
            <input name="medication_name[]" class="form-control" placeholder="Medication Name">
            <input name="dosage[]" class="form-control" placeholder="Dosage">
            <input name="frequency[]" class="form-control" placeholder="Frequency">
            <input name="start_date[]" type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            <input name="instructions[]" class="form-control" placeholder="Instructions">
        </div>
        <button type="button" class="btn-delete" onclick="removeRow(this)" title="Remove medication">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    document.getElementById('medications').appendChild(div);
}

function addCharge(){
    let div=document.createElement('div');
    div.className='charge-row';
    div.innerHTML=`
        <div class="charge-fields">
            <input name="charge_name[]" class="form-control" placeholder="Charge Name (e.g., Blood Test, ECG)" oninput="updateBillSummary()">
            <input name="charge_amount[]" type="number" step="0.01" class="form-control" placeholder="Amount ($)" oninput="updateBillSummary()">
        </div>
        <button type="button" class="btn-delete" onclick="removeRow(this)" title="Remove charge">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    document.getElementById('charges').appendChild(div);
    updateBillSummary();
}

function removeRow(btn){
    btn.parentElement.remove();
    updateBillSummary();
}

function updateBillSummary() {
    let additionalTotal = 0;
    let chargesList = [];
    
    // Get all charge rows
    const chargeRows = document.querySelectorAll('.charge-row');
    const additionalChargesList = document.getElementById('additional-charges-list');
    additionalChargesList.innerHTML = '';
    
    chargeRows.forEach(row => {
        const chargeName = row.querySelector('input[name="charge_name[]"]')?.value || '';
        const chargeAmount = parseFloat(row.querySelector('input[name="charge_amount[]"]')?.value) || 0;
        
        if (chargeAmount > 0 && chargeName) {
            additionalTotal += chargeAmount;
            chargesList.push({name: chargeName, amount: chargeAmount});
            
            // Add to bill summary
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(chargeName)}:</td>
                <td class="text-right">$${chargeAmount.toFixed(2)}</td>
            `;
            additionalChargesList.appendChild(tr);
        }
    });
    
    let subtotal = consultationFee + additionalTotal;
    let service = subtotal * 0.03;
    let gst = subtotal * 0.13;
    let total = subtotal + service + gst;
    
    document.getElementById('subtotal').innerText = subtotal.toFixed(2);
    document.getElementById('service').innerText = service.toFixed(2);
    document.getElementById('gst').innerText = gst.toFixed(2);
    document.getElementById('total').innerText = total.toFixed(2);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateBillSummary();
});
</script>

<style>
.dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    margin-bottom: 20px;
}

.dashboard-header h1 {
    color: #333;
    margin-bottom: 5px;
}

.dashboard-header p {
    color: #666;
    font-size: 14px;
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

.card-body {
    padding: 20px;
}

.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.info-group h4 {
    color: #1a75bc;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e9ecef;
    font-size: 16px;
}

.info-group p {
    margin: 8px 0;
    line-height: 1.5;
    font-size: 14px;
}

.history-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
}

.section-divider {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 25px 0 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.section-divider h4 {
    color: #1a75bc;
    margin: 0;
    font-size: 16px;
}

/* Prescription Row Styling */
.med-row {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 12px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    border-left: 4px solid #1a75bc;
}

.med-fields {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

/* Charge Row Styling */
.charge-row {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 12px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    border-left: 4px solid #28a745;
}

.charge-fields {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

/* Delete Button Styling */
.btn-delete {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
}

.btn-delete:hover {
    background: #c82333;
    transform: scale(1.05);
}

.btn-delete i {
    font-size: 14px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #1a75bc;
    box-shadow: 0 0 0 2px rgba(26,117,188,0.1);
}

textarea.form-control {
    resize: vertical;
}

.required {
    color: #dc3545;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-large {
    padding: 12px 24px;
    font-size: 16px;
    border-radius: 6px;
}

.btn-primary {
    background-color: #1a75bc;
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: #0e5a92;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 4px;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
    border-color: #1a75bc;
}

.bill-summary {
    background: #e9ecef;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.bill-table {
    width: 100%;
    max-width: 400px;
    margin-top: 10px;
}

.bill-table td {
    padding: 8px 0;
}

.text-right {
    text-align: right;
}

.tax-row {
    color: #666;
}

.total-row {
    border-top: 2px solid #1a75bc;
    font-size: 16px;
}

.text-muted {
    color: #6c757d;
    font-size: 12px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
    justify-content: center;
}

.form-actions .btn-primary {
    min-width: 250px;
}

.alert {
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert i {
    margin-right: 10px;
}

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn-primary {
        width: 100%;
    }
    
    .med-fields,
    .charge-fields {
        grid-template-columns: 1fr;
    }
    
    .med-row,
    .charge-row {
        flex-direction: column;
    }
    
    .btn-delete {
        align-self: flex-end;
    }
    
    .patient-info-grid {
        grid-template-columns: 1fr;
    }
    
    .history-links {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .section-divider {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
}
</style>

<?php include '../includes/footer.php'; ?>