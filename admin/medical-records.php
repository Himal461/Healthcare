<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Medical Records - HealthManagement";
include '../includes/header.php';

// Handle medical record actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_record'])) {
        $patientId = $_POST['patient_id'];
        $doctorId = $_POST['doctor_id'];
        $appointmentId = $_POST['appointment_id'] ?: null;
        $diagnosis = sanitizeInput($_POST['diagnosis']);
        $treatmentNotes = sanitizeInput($_POST['treatment_notes']);
        $prescriptions = sanitizeInput($_POST['prescriptions']);
        $followUpDate = $_POST['follow_up_date'] ?: null;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO medical_records (patientId, doctorId, appointmentId, diagnosis, treatmentNotes, prescriptions, followUpDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patientId, $doctorId, $appointmentId, $diagnosis, $treatmentNotes, $prescriptions, $followUpDate]);
            $_SESSION['success'] = "Medical record added successfully!";
            logAction($_SESSION['user_id'], 'ADD_MEDICAL_RECORD', "Added record for patient ID: $patientId");
            header("Location: medical-records.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to add record: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_vitals'])) {
        $recordId = $_POST['record_id'];
        $height = $_POST['height'] ?: null;
        $weight = $_POST['weight'] ?: null;
        $bodyTemperature = $_POST['body_temperature'] ?: null;
        $bloodPressureSystolic = $_POST['bp_systolic'] ?: null;
        $bloodPressureDiastolic = $_POST['bp_diastolic'] ?: null;
        $heartRate = $_POST['heart_rate'] ?: null;
        $respiratoryRate = $_POST['respiratory_rate'] ?: null;
        $oxygenSaturation = $_POST['oxygen_saturation'] ?: null;
        $notes = sanitizeInput($_POST['vitals_notes']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO vitals (recordId, height, weight, bodyTemperature, bloodPressureSystolic, bloodPressureDiastolic, heartRate, respiratoryRate, oxygenSaturation, notes, recordedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$recordId, $height, $weight, $bodyTemperature, $bloodPressureSystolic, $bloodPressureDiastolic, $heartRate, $respiratoryRate, $oxygenSaturation, $notes, $_SESSION['user_id']]);
            $_SESSION['success'] = "Vitals added successfully!";
            header("Location: medical-records.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to add vitals: " . $e->getMessage();
        }
    }
}

// Get all medical records
$records = $pdo->query("
    SELECT mr.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           a.dateTime as appointmentDate
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    LEFT JOIN appointments a ON mr.appointmentId = a.appointmentId
    ORDER BY mr.creationDate DESC
")->fetchAll();

// Get patients for dropdown
$patients = $pdo->query("
    SELECT p.patientId, CONCAT(u.firstName, ' ', u.lastName) as name
    FROM patients p
    JOIN users u ON p.userId = u.userId
    ORDER BY u.firstName
")->fetchAll();

// Get doctors for dropdown
$doctors = $pdo->query("
    SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name, d.specialization
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.isAvailable = 1
")->fetchAll();

// Get appointments for dropdown
$appointments = $pdo->query("
    SELECT a.appointmentId, CONCAT(u.firstName, ' ', u.lastName) as patientName, a.dateTime
    FROM appointments a
    JOIN patients p ON a.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    WHERE a.status = 'completed'
    ORDER BY a.dateTime DESC
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Medical Records</h1>
        <p>Manage patient medical records and vitals</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Add Medical Record Form -->
    <div class="card">
        <div class="card-header">
            <h3>Add New Medical Record</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="form">
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
                        <label for="doctor_id">Doctor *</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">Select doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['doctorId']; ?>">Dr. <?php echo $doctor['name']; ?> (<?php echo $doctor['specialization']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment_id">Associated Appointment (Optional)</label>
                        <select id="appointment_id" name="appointment_id">
                            <option value="">Select appointment</option>
                            <?php foreach ($appointments as $appointment): ?>
                                <option value="<?php echo $appointment['appointmentId']; ?>">
                                    <?php echo $appointment['patientName']; ?> - <?php echo date('M j, Y', strtotime($appointment['dateTime'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="follow_up_date">Follow-up Date</label>
                        <input type="date" id="follow_up_date" name="follow_up_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="diagnosis">Diagnosis</label>
                    <textarea id="diagnosis" name="diagnosis" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="treatment_notes">Treatment Notes</label>
                    <textarea id="treatment_notes" name="treatment_notes" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="prescriptions">Prescriptions</label>
                    <textarea id="prescriptions" name="prescriptions" rows="3" placeholder="List medications with dosage and frequency"></textarea>
                </div>
                
                <button type="submit" name="add_record" class="btn btn-primary">Add Medical Record</button>
            </form>
        </div>
    </div>

    <!-- Medical Records List -->
    <div class="card">
        <div class="card-header">
            <h3>All Medical Records</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Follow-up</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td data-label="Date"><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></td>
                        <td data-label="Patient"><?php echo $record['patientName']; ?></td>
                        <td data-label="Doctor">Dr. <?php echo $record['doctorName']; ?></td>
                        <td data-label="Diagnosis"><?php echo substr($record['diagnosis'], 0, 50) . (strlen($record['diagnosis']) > 50 ? '...' : ''); ?></td>
                        <td data-label="Follow-up"><?php echo $record['followUpDate'] ? date('M j, Y', strtotime($record['followUpDate'])) : '-'; ?></td>
                        <td data-label="Actions">
                            <button class="btn btn-primary btn-sm" onclick="viewRecord(<?php echo $record['recordId']; ?>)">View</button>
                            <button class="btn btn-primary btn-sm" onclick="openModal('vitalsModal'); document.getElementById('vitals_record_id').value = <?php echo $record['recordId']; ?>;">Add Vitals</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Record Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Medical Record Details</h3>
            <span class="close" onclick="closeModal('viewModal')">&times;</span>
        </div>
        <div class="modal-body" id="record-details">
            <div class="loading">Loading record details...</div>
        </div>
    </div>
</div>

<!-- Add Vitals Modal -->
<div id="vitalsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Patient Vitals</h3>
            <span class="close" onclick="closeModal('vitalsModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="record_id" id="vitals_record_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" step="0.1">
                    </div>
                    
                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" step="0.1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="body_temperature">Body Temperature (°C)</label>
                        <input type="number" id="body_temperature" name="body_temperature" step="0.1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bp_systolic">Blood Pressure (Systolic)</label>
                        <input type="number" id="bp_systolic" name="bp_systolic">
                    </div>
                    
                    <div class="form-group">
                        <label for="bp_diastolic">Blood Pressure (Diastolic)</label>
                        <input type="number" id="bp_diastolic" name="bp_diastolic">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="heart_rate">Heart Rate (bpm)</label>
                        <input type="number" id="heart_rate" name="heart_rate">
                    </div>
                    
                    <div class="form-group">
                        <label for="respiratory_rate">Respiratory Rate</label>
                        <input type="number" id="respiratory_rate" name="respiratory_rate">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="oxygen_saturation">Oxygen Saturation (%)</label>
                        <input type="number" id="oxygen_saturation" name="oxygen_saturation">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="vitals_notes">Notes</label>
                    <textarea id="vitals_notes" name="vitals_notes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_vitals" class="btn btn-primary">Save Vitals</button>
                <button type="button" class="btn" onclick="closeModal('vitalsModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
async function viewRecord(recordId) {
    const modal = document.getElementById('viewModal');
    const detailsDiv = document.getElementById('record-details');
    
    detailsDiv.innerHTML = '<div class="loading">Loading record details...</div>';
    openModal('viewModal');
    
    try {
        const response = await fetch(`../ajax/get-medical-record.php?id=${recordId}`);
        const data = await response.json();
        
        if (data.success) {
            let vitalsHtml = '';
            if (data.vitals && data.vitals.length > 0) {
                vitalsHtml = '<div class="record-section"><h4>Vitals History</h4><div class="vitals-grid">';
                data.vitals.forEach(vital => {
                    vitalsHtml += `
                        <div class="vital-item">
                            <strong>${new Date(vital.recordedDate).toLocaleDateString()}</strong><br>
                            ${vital.height ? `Height: ${vital.height} cm<br>` : ''}
                            ${vital.weight ? `Weight: ${vital.weight} kg<br>` : ''}
                            ${vital.bodyTemperature ? `Temp: ${vital.bodyTemperature}°C<br>` : ''}
                            ${vital.bloodPressureSystolic ? `BP: ${vital.bloodPressureSystolic}/${vital.bloodPressureDiastolic}<br>` : ''}
                            ${vital.heartRate ? `HR: ${vital.heartRate} bpm<br>` : ''}
                            ${vital.oxygenSaturation ? `SpO2: ${vital.oxygenSaturation}%<br>` : ''}
                        </div>
                    `;
                });
                vitalsHtml += '</div></div>';
            }
            
            detailsDiv.innerHTML = `
                <div class="record-section">
                    <h4>Patient Information</h4>
                    <p><strong>Name:</strong> ${data.record.patientName}</p>
                    <p><strong>Doctor:</strong> Dr. ${data.record.doctorName}</p>
                    <p><strong>Date:</strong> ${new Date(data.record.creationDate).toLocaleDateString()}</p>
                </div>
                <div class="record-section">
                    <h4>Diagnosis</h4>
                    <p>${data.record.diagnosis || 'No diagnosis recorded'}</p>
                </div>
                <div class="record-section">
                    <h4>Treatment Notes</h4>
                    <p>${data.record.treatmentNotes || 'No treatment notes'}</p>
                </div>
                <div class="record-section">
                    <h4>Prescriptions</h4>
                    <p>${data.record.prescriptions || 'No prescriptions'}</p>
                </div>
                ${vitalsHtml}
                ${data.record.followUpDate ? `
                <div class="record-section">
                    <h4>Follow-up Date</h4>
                    <p>${new Date(data.record.followUpDate).toLocaleDateString()}</p>
                </div>
                ` : ''}
            `;
        } else {
            detailsDiv.innerHTML = '<div class="alert alert-error">Failed to load record details</div>';
        }
    } catch (error) {
        console.error('Error:', error);
        detailsDiv.innerHTML = '<div class="alert alert-error">Error loading record details</div>';
    }
}
</script>

<?php include '../includes/footer.php'; ?>