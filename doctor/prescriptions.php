<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Prescriptions Management - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();
$doctorId = $doctor['doctorId'];
$doctorName = $doctor['doctorName'];

// Handle prescription creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prescription'])) {
    $patientId = $_POST['patient_id'];
    $medicationName = sanitizeInput($_POST['medication_name']);
    $dosage = sanitizeInput($_POST['dosage']);
    $frequency = sanitizeInput($_POST['frequency']);
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'] ?: null;
    $refills = intval($_POST['refills']);
    $instructions = sanitizeInput($_POST['instructions']);
    
    try {
        // Get the latest medical record for this patient
        $recordStmt = $pdo->prepare("
            SELECT recordId FROM medical_records 
            WHERE patientId = ? 
            ORDER BY creationDate DESC 
            LIMIT 1
        ");
        $recordStmt->execute([$patientId]);
        $record = $recordStmt->fetch();
        
        if (!$record) {
            // Create a basic medical record if none exists
            $recordStmt = $pdo->prepare("
                INSERT INTO medical_records (patientId, doctorId, diagnosis, treatmentNotes) 
                VALUES (?, ?, 'Initial consultation', 'New prescription issued')
            ");
            $recordStmt->execute([$patientId, $doctorId]);
            $recordId = $pdo->lastInsertId();
        } else {
            $recordId = $record['recordId'];
        }
        
        // Insert prescription
        $stmt = $pdo->prepare("
            INSERT INTO prescriptions (recordId, prescribedBy, medicationName, dosage, frequency, startDate, endDate, refills, instructions, status, createdAt) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$recordId, $doctorId, $medicationName, $dosage, $frequency, $startDate, $endDate, $refills, $instructions]);
        
        // Get patient details for notification
        $patientStmt = $pdo->prepare("
            SELECT u.userId, u.firstName, u.lastName, u.email
            FROM patients p
            JOIN users u ON p.userId = u.userId
            WHERE p.patientId = ?
        ");
        $patientStmt->execute([$patientId]);
        $patient = $patientStmt->fetch();
        
        // Create notification for patient
        createNotification(
            $patient['userId'],
            'prescription',
            'New Prescription Issued',
            "Dr. " . $doctorName . " has issued a new prescription for " . $medicationName
        );
        
        $_SESSION['success'] = "Prescription added successfully!";
        logAction($userId, 'ADD_PRESCRIPTION', "Added prescription for patient ID: $patientId");
        
        header("Location: prescriptions.php");
        exit();
        
    } catch (Exception $e) {
        $error = "Failed to add prescription: " . $e->getMessage();
    }
}

// Handle prescription status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prescription_status'])) {
    $prescriptionId = $_POST['prescription_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE prescriptions SET status = ? WHERE prescriptionId = ?");
        $stmt->execute([$status, $prescriptionId]);
        
        $_SESSION['success'] = "Prescription status updated successfully!";
        logAction($userId, 'UPDATE_PRESCRIPTION_STATUS', "Updated prescription ID: $prescriptionId to $status");
        
        header("Location: prescriptions.php");
        exit();
        
    } catch (Exception $e) {
        $error = "Failed to update prescription status.";
    }
}

// Get all prescriptions for this doctor's patients
$prescriptionsStmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           mr.diagnosis
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN patients pt ON mr.patientId = pt.patientId
    JOIN users u ON pt.userId = u.userId
    WHERE p.prescribedBy = ?
    ORDER BY p.createdAt DESC
");
$prescriptionsStmt->execute([$doctorId]);
$prescriptions = $prescriptionsStmt->fetchAll();

// Get all patients for dropdown
$patientsStmt = $pdo->query("
    SELECT p.patientId, CONCAT(u.firstName, ' ', u.lastName) as patientName
    FROM patients p
    JOIN users u ON p.userId = u.userId
    ORDER BY u.firstName
");
$patients = $patientsStmt->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Prescriptions Management</h1>
        <p>Create and manage prescriptions for your patients</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <!-- Add Prescription Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-prescription"></i> Write New Prescription</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="prescription-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patientId']; ?>"><?php echo htmlspecialchars($patient['patientName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="medication_name">Medication Name *</label>
                        <input type="text" id="medication_name" name="medication_name" required placeholder="e.g., Amoxicillin 500mg">
                    </div>
                    
                    <div class="form-group">
                        <label for="dosage">Dosage *</label>
                        <input type="text" id="dosage" name="dosage" required placeholder="e.g., 1 tablet">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="frequency">Frequency *</label>
                        <input type="text" id="frequency" name="frequency" required placeholder="e.g., Twice daily">
                    </div>
                    
                    <div class="form-group">
                        <label for="refills">Number of Refills</label>
                        <input type="number" id="refills" name="refills" value="0" min="0" max="10">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date (Optional)</label>
                        <input type="date" id="end_date" name="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" rows="3" placeholder="Additional instructions for the patient..."></textarea>
                </div>
                
                <button type="submit" name="add_prescription" class="btn btn-primary">
                    <i class="fas fa-save"></i> Issue Prescription
                </button>
            </form>
        </div>
    </div>

    <!-- Prescriptions List -->
    <div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> My Prescriptions</h3>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prescriptions)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No prescriptions issued yet</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($prescription['createdAt'])); ?></td>
                            <td data-label="Patient"><?php echo htmlspecialchars($prescription['patientName']); ?></td>
                            <td data-label="Medication"><strong><?php echo htmlspecialchars($prescription['medicationName']); ?></strong></td>
                            <td data-label="Dosage"><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                            <td data-label="Frequency"><?php echo htmlspecialchars($prescription['frequency']); ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?php echo $prescription['status']; ?>">
                                    <?php echo ucfirst($prescription['status']); ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <button class="btn btn-primary btn-sm" onclick="viewPrescription(<?php echo $prescription['prescriptionId']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-info btn-sm" onclick="updateStatus(<?php echo $prescription['prescriptionId']; ?>, '<?php echo $prescription['status']; ?>')">
                                    <i class="fas fa-edit"></i> Status
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Prescription Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Prescription Details</h3>
            <span class="close" onclick="closeModal('viewModal')">&times;</span>
        </div>
        <div class="modal-body" id="prescription-details">
            <div class="loading">Loading prescription details...</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="printPrescription()">
                <i class="fas fa-print"></i> Print Prescription
            </button>
            <button class="btn" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Prescription Status</h3>
            <span class="close" onclick="closeModal('statusModal')">&times;<\/span>
        <\/div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="prescription_id" id="status_prescription_id">
                <div class="form-group">
                    <label for="status">Status<\/label>
                    <select id="status" name="status" required>
                        <option value="active">Active<\/option>
                        <option value="completed">Completed<\/option>
                        <option value="expired">Expired<\/option>
                        <option value="cancelled">Cancelled<\/option>
                    <\/select>
                <\/div>
            <\/div>
            <div class="modal-footer">
                <button type="submit" name="update_prescription_status" class="btn btn-primary">Update Status<\/button>
                <button type="button" class="btn" onclick="closeModal('statusModal')">Cancel<\/button>
            <\/div>
        <\/form>
    <\/div>
<\/div>

<script>
function viewPrescription(prescriptionId) {
    const modal = document.getElementById('viewModal');
    const detailsDiv = document.getElementById('prescription-details');
    
    detailsDiv.innerHTML = '<div class="loading">Loading prescription details...<\/div>';
    modal.style.display = 'flex';
    
    fetch(`..\/ajax\/get-prescription.php?id=${prescriptionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                detailsDiv.innerHTML = `
                    <div class="prescription-detail-section">
                        <h4><i class="fas fa-user"><\/i> Patient Information</h4>
                        <p><strong>Name:<\/strong> ${data.prescription.patientName}<\/p>
                        <p><strong>Email:<\/strong> ${data.prescription.patientEmail}<\/p>
                        <p><strong>Phone:<\/strong> ${data.prescription.patientPhone}<\/p>
                        <p><strong>Diagnosis:<\/strong> ${data.prescription.diagnosis || 'N/A'}<\/p>
                    <\/div>
                    <div class="prescription-detail-section">
                        <h4><i class="fas fa-user-md"><\/i> Prescribing Doctor</h4>
                        <p><strong>Doctor:<\/strong> Dr. ${data.prescription.doctorName}<\/p>
                        <p><strong>Prescribed on:<\/strong> ${new Date(data.prescription.createdAt).toLocaleDateString()}<\/p>
                    <\/div>
                    <div class="prescription-detail-section">
                        <h4><i class="fas fa-prescription"><\/i> Medication Details</h4>
                        <p><strong>Medication:<\/strong> ${data.prescription.medicationName}<\/p>
                        <p><strong>Dosage:<\/strong> ${data.prescription.dosage}<\/p>
                        <p><strong>Frequency:<\/strong> ${data.prescription.frequency}<\/p>
                        <p><strong>Start Date:<\/strong> ${new Date(data.prescription.startDate).toLocaleDateString()}<\/p>
                        <p><strong>End Date:<\/strong> ${data.prescription.endDate ? new Date(data.prescription.endDate).toLocaleDateString() : 'Ongoing'}<\/p>
                        <p><strong>Refills:<\/strong> ${data.prescription.refills || 0}<\/p>
                        <p><strong>Instructions:<\/strong> ${data.prescription.instructions || 'No additional instructions'}<\/p>
                    <\/div>
                    <div class="prescription-detail-section">
                        <h4><i class="fas fa-chart-line"><\/i> Status</h4>
                        <p><strong>Status:<\/strong> <span class="status-badge status-${data.prescription.status}">${data.prescription.status.toUpperCase()}<\/span><\/p>
                    <\/div>
                `;
            } else {
                detailsDiv.innerHTML = '<div class="alert alert-error">Failed to load prescription details<\/div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            detailsDiv.innerHTML = '<div class="alert alert-error">Error loading prescription details<\/div>';
        });
}

function updateStatus(prescriptionId, currentStatus) {
    document.getElementById('status_prescription_id').value = prescriptionId;
    document.getElementById('status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'flex';
}

function printPrescription() {
    const printContent = document.getElementById('prescription-details').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Prescription Details</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .prescription-detail-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
                h4 { color: #1a75bc; margin-bottom: 10px; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
                .status-active { background: #d4edda; color: #155724; }
                @media print {
                    body { margin: 0; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <h2>Prescription Details</h2>
            ${printContent}
            <p style="margin-top: 30px; font-size: 12px; color: #666; text-align: center;">
                Generated on ${new Date().toLocaleString()}
            </p>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>


<?php include '../includes/footer.php'; ?>