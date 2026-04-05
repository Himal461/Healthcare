<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "Patient Records - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$patientId = $_GET['patient_id'] ?? 0;

// Get patient details
if ($patientId) {
    $stmt = $pdo->prepare("
        SELECT u.userId, u.firstName, u.lastName, u.email, u.phoneNumber, u.dateCreated,
               p.patientId, p.dateOfBirth, p.bloodType, p.address, p.knownAllergies,
               p.emergencyContactName, p.emergencyContactPhone, p.insuranceProvider, p.insuranceNumber
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE p.patientId = ?
    ");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    
    // Get patient appointments
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(du.firstName, ' ', du.lastName) as doctorName,
               d.specialization
        FROM appointments a
        JOIN doctors d ON a.doctorId = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE a.patientId = ?
        ORDER BY a.dateTime DESC
        LIMIT 20
    ");
    $stmt->execute([$patientId]);
    $appointments = $stmt->fetchAll();
    
    // Get patient bills
    $stmt = $pdo->prepare("
        SELECT * FROM billing 
        WHERE patientId = ?
        ORDER BY createdAt DESC
    ");
    $stmt->execute([$patientId]);
    $bills = $stmt->fetchAll();
    
    // Get patient medical records
    $stmt = $pdo->prepare("
        SELECT mr.*, 
               CONCAT(du.firstName, ' ', du.lastName) as doctorName,
               d.specialization
        FROM medical_records mr
        JOIN doctors d ON mr.doctorId = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE mr.patientId = ?
        ORDER BY mr.creationDate DESC
        LIMIT 10
    ");
    $stmt->execute([$patientId]);
    $medicalRecords = $stmt->fetchAll();
    
    // Get patient prescriptions
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(du.firstName, ' ', du.lastName) as doctorName,
               mr.diagnosis
        FROM prescriptions p
        JOIN medical_records mr ON p.recordId = mr.recordId
        JOIN doctors d ON p.prescribedBy = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE mr.patientId = ?
        ORDER BY p.createdAt DESC
    ");
    $stmt->execute([$patientId]);
    $prescriptions = $stmt->fetchAll();
    
    // Get patient vitals
    $stmt = $pdo->prepare("
        SELECT v.*, 
               CONCAT(du.firstName, ' ', du.lastName) as recordedByName
        FROM vitals v
        JOIN medical_records mr ON v.recordId = mr.recordId
        LEFT JOIN staff s ON v.recordedBy = s.staffId
        LEFT JOIN users du ON s.userId = du.userId
        WHERE mr.patientId = ?
        ORDER BY v.recordedDate DESC
        LIMIT 10
    ");
    $stmt->execute([$patientId]);
    $vitals = $stmt->fetchAll();
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Patient Records</h1>
        <p>View patient information, appointments, medical records, prescriptions, and billing history</p>
    </div>

    <?php if ($patient): ?>
        <!-- Patient Information -->
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
                        <p><strong>Blood Type:</strong> <?php echo $patient['bloodType'] ?: 'N/A'; ?></p>
                    </div>
                    <div class="info-group">
                        <h4>Medical Information</h4>
                        <p><strong>Allergies:</strong> <?php echo $patient['knownAllergies'] ?: 'None'; ?></p>
                        <p><strong>Address:</strong> <?php echo $patient['address'] ?: 'N/A'; ?></p>
                    </div>
                    <div class="info-group">
                        <h4>Emergency Contact</h4>
                        <p><strong>Name:</strong> <?php echo $patient['emergencyContactName'] ?: 'N/A'; ?></p>
                        <p><strong>Phone:</strong> <?php echo $patient['emergencyContactPhone'] ?: 'N/A'; ?></p>
                    </div>
                    <div class="info-group">
                        <h4>Insurance Information</h4>
                        <p><strong>Provider:</strong> <?php echo $patient['insuranceProvider'] ?: 'N/A'; ?></p>
                        <p><strong>Number:</strong> <?php echo $patient['insuranceNumber'] ?: 'N/A'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Records -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-notes-medical"></i> Medical Records</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        汽
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Diagnosis</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if (empty($medicalRecords)): ?>
                            汽
                                <td colspan="5" style="text-align: center;">No medical records found<\/td>
                            <\/tr>
                        <?php else: ?>
                            <?php foreach ($medicalRecords as $record): ?>
                                汽
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($record['creationDate'])); ?><\/td>
                                    <td data-label="Doctor">Dr. <?php echo htmlspecialchars($record['doctorName']); ?><\/td>
                                    <td data-label="Specialization"><?php echo htmlspecialchars($record['specialization']); ?><\/td>
                                    <td data-label="Diagnosis"><?php echo substr($record['diagnosis'], 0, 50) . (strlen($record['diagnosis']) > 50 ? '...' : ''); ?><\/td>
                                    <td data-label="Actions">
                                        <a href="../admin/medical-records.php?view=<?php echo $record['recordId']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"><\/i> View Details
                                        <\/a>
                                    <\/td>
                                <\/tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <\/tbody>
                <\/table>
            <\/div>
        <\/div>

        <!-- Prescriptions Section -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-prescription"></i> Prescriptions</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        汽
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if (empty($prescriptions)): ?>
                            汽
                                <td colspan="8" style="text-align: center;">No prescriptions found<\/td>
                            <\/tr>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $prescription): ?>
                                汽
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($prescription['createdAt'])); ?><\/td>
                                    <td data-label="Doctor">Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?><\/td>
                                    <td data-label="Medication"><strong><?php echo htmlspecialchars($prescription['medicationName']); ?><\/strong><\/td>
                                    <td data-label="Dosage"><?php echo htmlspecialchars($prescription['dosage']); ?><\/td>
                                    <td data-label="Frequency"><?php echo htmlspecialchars($prescription['frequency']); ?><\/td>
                                    <td data-label="Duration">
                                        <?php echo date('M j, Y', strtotime($prescription['startDate'])); ?> - 
                                        <?php echo $prescription['endDate'] ? date('M j, Y', strtotime($prescription['endDate'])) : 'Ongoing'; ?>
                                    <\/td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?php echo $prescription['status']; ?>">
                                            <?php echo ucfirst($prescription['status']); ?>
                                        <\/span>
                                    <\/td>
                                    <td data-label="Actions">
                                        <button class="btn btn-primary btn-sm" onclick="viewPrescription(<?php echo $prescription['prescriptionId']; ?>)">
                                            <i class="fas fa-eye"><\/i> View Details
                                        <\/button>
                                    <\/td>
                                <\/tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <\/tbody>
                <\/table>
            <\/div>
        <\/div>

        <!-- Vitals History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-heartbeat"></i> Vitals History</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        汽
                            <th>Date</th>
                            <th>Recorded By</th>
                            <th>BP</th>
                            <th>Heart Rate</th>
                            <th>Temperature</th>
                            <th>Weight</th>
                            <th>Height</th>
                            <th>SpO2</th>
                        </thead>
                    <tbody>
                        <?php if (empty($vitals)): ?>
                            汽
                                <td colspan="8" style="text-align: center;">No vitals recorded<\/td>
                            <\/tr>
                        <?php else: ?>
                            <?php foreach ($vitals as $vital): ?>
                                汽
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($vital['recordedDate'])); ?><\/td>
                                    <td data-label="Recorded By"><?php echo $vital['recordedByName'] ?: 'Nurse'; ?><\/td>
                                    <td data-label="BP"><?php echo $vital['bloodPressureSystolic'] ? $vital['bloodPressureSystolic'] . '/' . $vital['bloodPressureDiastolic'] : '-'; ?><\/td>
                                    <td data-label="Heart Rate"><?php echo $vital['heartRate'] ?: '-'; ?><\/td>
                                    <td data-label="Temperature"><?php echo $vital['bodyTemperature'] ?: '-'; ?>°C<\/td>
                                    <td data-label="Weight"><?php echo $vital['weight'] ?: '-'; ?> kg<\/td>
                                    <td data-label="Height"><?php echo $vital['height'] ?: '-'; ?> cm<\/td>
                                    <td data-label="SpO2"><?php echo $vital['oxygenSaturation'] ?: '-'; ?>%<\/td>
                                <\/tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <\/tbody>
                <\/table>
            <\/div>
        <\/div>

        <!-- Appointments History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Appointment History</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        汽
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Reason</th>
                        </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            汽
                                <td colspan="5" style="text-align: center;">No appointments found<\/td>
                            <\/tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appointment): ?>
                                汽
                                    <td data-label="Date & Time"><?php echo date('M j, Y g:i A', strtotime($appointment['dateTime'])); ?><\/td>
                                    <td data-label="Doctor">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?><\/td>
                                    <td data-label="Specialization"><?php echo htmlspecialchars($appointment['specialization']); ?><\/td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        <\/span>
                                    <\/td>
                                    <td data-label="Reason"><?php echo $appointment['reason'] ?: '-'; ?><\/td>
                                <\/tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <\/tbody>
                <\/table>
            <\/div>
        <\/div>

        <!-- Billing History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-dollar-sign"></i> Billing History</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        汽
                            <th>Date</th>
                            <th>Bill ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                            <th>Due Date</th>
                        </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            汽
                                <td colspan="6" style="text-align: center;">No billing records found<\/td>
                            <\/tr>
                        <?php else: ?>
                            <?php foreach ($bills as $bill): ?>
                                汽
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($bill['createdAt'])); ?><\/td>
                                    <td data-label="Bill ID">#<?php echo $bill['billId']; ?><\/td>
                                    <td data-label="Amount">$<?php echo number_format($bill['totalAmount'], 2); ?><\/td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?php echo $bill['status']; ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        <\/span>
                                    <\/td>
                                    <td data-label="Payment Method"><?php echo $bill['paymentMethod'] ?: '-'; ?><\/td>
                                    <td data-label="Due Date"><?php echo date('M j, Y', strtotime($bill['dueDate'])); ?><\/td>
                                <\/tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <\/tbody>
                <\/table>
            <\/div>
        <\/div>

        <div class="action-buttons">
            <a href="book-appointment.php?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </a>
            <a href="create-bill.php?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-primary">
                <i class="fas fa-receipt"></i> Create Bill
            </a>
            <a href="process-payment.php?patient_id=<?php echo $patient['patientId']; ?>" class="btn btn-outline">
                <i class="fas fa-credit-card"></i> Process Payment
            </a>
            <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <p class="text-muted">Please select a patient to view records.</p>
                <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function viewPrescription(prescriptionId) {
    window.location.href = 'prescription-details.php?id=' + prescriptionId;
}
</script>

<?php include '../includes/footer.php'; ?>