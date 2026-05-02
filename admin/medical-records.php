<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Medical Records - HealthManagement";
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record'])) {
    $patientId = $_POST['patient_id'];
    $doctorId = $_POST['doctor_id'];
    $appointmentId = $_POST['appointment_id'] ?: null;
    $diagnosis = sanitizeInput($_POST['diagnosis']);
    $treatmentNotes = sanitizeInput($_POST['treatment_notes']);
    $prescriptions = sanitizeInput($_POST['prescriptions']);
    $followUpDate = $_POST['follow_up_date'] ?: null;
    
    $stmt = $pdo->prepare("INSERT INTO medical_records (patientId, doctorId, appointmentId, diagnosis, treatmentNotes, prescriptions, followUpDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patientId, $doctorId, $appointmentId, $diagnosis, $treatmentNotes, $prescriptions, $followUpDate]);
    $_SESSION['success'] = "Medical record added!";
    logAction($_SESSION['user_id'], 'ADD_MEDICAL_RECORD', "Added record for patient $patientId");
    header("Location: medical-records.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vitals'])) {
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
    
    $stmt = $pdo->prepare("INSERT INTO vitals (recordId, height, weight, bodyTemperature, bloodPressureSystolic, bloodPressureDiastolic, heartRate, respiratoryRate, oxygenSaturation, notes, recordedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$recordId, $height, $weight, $bodyTemperature, $bloodPressureSystolic, $bloodPressureDiastolic, $heartRate, $respiratoryRate, $oxygenSaturation, $notes, $_SESSION['user_id']]);
    $_SESSION['success'] = "Vitals added!";
    header("Location: medical-records.php");
    exit();
}

$records = $pdo->query("
    SELECT mr.*, CONCAT(pu.firstName, ' ', pu.lastName) as patientName, CONCAT(du.firstName, ' ', du.lastName) as doctorName
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    ORDER BY mr.creationDate DESC
")->fetchAll();

$patients = $pdo->query("SELECT p.patientId, CONCAT(u.firstName, ' ', u.lastName) as name FROM patients p JOIN users u ON p.userId = u.userId")->fetchAll();
$doctors = $pdo->query("SELECT d.doctorId, CONCAT(u.firstName, ' ', u.lastName) as name, d.specialization FROM doctors d JOIN staff s ON d.staffId = s.staffId JOIN users u ON s.userId = u.userId WHERE d.isAvailable = 1")->fetchAll();
$appointments = $pdo->query("SELECT a.appointmentId, CONCAT(u.firstName, ' ', u.lastName) as patientName, a.dateTime FROM appointments a JOIN patients p ON a.patientId = p.patientId JOIN users u ON p.userId = u.userId WHERE a.status = 'completed' ORDER BY a.dateTime DESC")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Medical Records</h1>
        <p>Manage patient medical records and vitals</p>
    </div>

    <div class="card">
        <div class="card-header"><h3>Add Medical Record</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Patient *</label>
                        <select name="patient_id" required>
                            <option value="">Select</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['patientId']; ?>"><?php echo $p['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Doctor *</label>
                        <select name="doctor_id" required>
                            <option value="">Select</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?php echo $d['doctorId']; ?>">Dr. <?php echo $d['name']; ?> (<?php echo $d['specialization']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Appointment</label>
                        <select name="appointment_id">
                            <option value="">Select</option>
                            <?php foreach ($appointments as $a): ?>
                                <option value="<?php echo $a['appointmentId']; ?>"><?php echo $a['patientName']; ?> - <?php echo date('M j, Y', strtotime($a['dateTime'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Follow-up Date</label><input type="date" name="follow_up_date"></div>
                </div>
                <div class="form-group"><label>Diagnosis</label><textarea name="diagnosis" rows="3"></textarea></div>
                <div class="form-group"><label>Treatment Notes</label><textarea name="treatment_notes" rows="3"></textarea></div>
                <div class="form-group"><label>Prescriptions</label><textarea name="prescriptions" rows="3" placeholder="List medications with dosage"></textarea></div>
                <button type="submit" name="add_record" class="btn btn-primary">Add Record</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>All Medical Records</h3></div>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Diagnosis</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($r['creationDate'])); ?></td>
                            <td><?php echo $r['patientName']; ?></td>
                            <td>Dr. <?php echo $r['doctorName']; ?></td>
                            <td><?php echo substr($r['diagnosis'], 0, 50); ?>...</td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="viewRecord(<?php echo $r['recordId']; ?>)">View</button>
                                <button class="btn btn-primary btn-sm" onclick="openModal('vitalsModal'); document.getElementById('vitals_record_id').value=<?php echo $r['recordId']; ?>;">Add Vitals</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="vitalsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Add Vitals</h3><span class="close" onclick="closeModal('vitalsModal')">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="record_id" id="vitals_record_id">
                <div class="form-row">
                    <div class="form-group"><label>Height (cm)</label><input type="number" name="height" step="0.1"></div>
                    <div class="form-group"><label>Weight (kg)</label><input type="number" name="weight" step="0.1"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Temperature (°C)</label><input type="number" name="body_temperature" step="0.1"></div>
                    <div class="form-group"><label>Heart Rate (bpm)</label><input type="number" name="heart_rate"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>BP Systolic</label><input type="number" name="bp_systolic"></div>
                    <div class="form-group"><label>BP Diastolic</label><input type="number" name="bp_diastolic"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Respiratory Rate</label><input type="number" name="respiratory_rate"></div>
                    <div class="form-group"><label>O2 Saturation (%)</label><input type="number" name="oxygen_saturation"></div>
                </div>
                <div class="form-group"><label>Notes</label><textarea name="vitals_notes" rows="3"></textarea></div>
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
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `<div class="modal-content modal-large"><div class="modal-header"><h3>Loading...</h3><span class="close" onclick="this.closest('.modal').remove()">&times;</span></div><div class="modal-body">Loading...</div></div>`;
    document.body.appendChild(modal);
    
    try {
        const res = await fetch(`../ajax/get-medical-record.php?id=${recordId}`);
        const data = await res.json();
        if (data.success) {
            modal.querySelector('.modal-header h3').textContent = `Record: ${data.record.patientName}`;
            modal.querySelector('.modal-body').innerHTML = `
                <p><strong>Doctor:</strong> Dr. ${data.record.doctorName}</p>
                <p><strong>Date:</strong> ${new Date(data.record.creationDate).toLocaleDateString()}</p>
                <h4>Diagnosis</h4><p>${data.record.diagnosis || 'None'}</p>
                <h4>Treatment</h4><p>${data.record.treatmentNotes || 'None'}</p>
                <h4>Prescriptions</h4><p>${data.record.prescriptions || 'None'}</p>
            `;
        }
    } catch (e) {
        modal.querySelector('.modal-body').innerHTML = 'Error loading record';
    }
}
</script>

<?php include '../includes/footer.php'; ?>