<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Prescriptions - HealthManagement";
include '../includes/header.php';

// Handle prescription actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $prescriptionId = $_POST['prescription_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE prescriptions SET status = ? WHERE prescriptionId = ?");
            $stmt->execute([$status, $prescriptionId]);
            $_SESSION['success'] = "Prescription status updated successfully!";
            logAction($_SESSION['user_id'], 'UPDATE_PRESCRIPTION', "Updated prescription ID: $prescriptionId to status: $status");
            header("Location: prescriptions.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to update prescription status.";
        }
    }
    
    if (isset($_POST['delete_prescription'])) {
        $prescriptionId = $_POST['prescription_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE prescriptionId = ?");
            $stmt->execute([$prescriptionId]);
            $_SESSION['success'] = "Prescription deleted successfully!";
            logAction($_SESSION['user_id'], 'DELETE_PRESCRIPTION', "Deleted prescription ID: $prescriptionId");
            header("Location: prescriptions.php");
            exit();
        } catch (Exception $e) {
            $error = "Failed to delete prescription.";
        }
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$patientFilter = $_GET['patient'] ?? '';
$medicationFilter = $_GET['medication'] ?? '';

// Build query
$query = "
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           u.email as patientEmail,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           mr.diagnosis,
           mr.creationDate as recordDate
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN patients pt ON mr.patientId = pt.patientId
    JOIN users u ON pt.userId = u.userId
    JOIN doctors d ON p.prescribedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE 1=1
";

$params = [];

if ($statusFilter) {
    $query .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if ($patientFilter) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ?)";
    $params[] = "%$patientFilter%";
    $params[] = "%$patientFilter%";
}

if ($medicationFilter) {
    $query .= " AND p.medicationName LIKE ?";
    $params[] = "%$medicationFilter%";
}

$query .= " ORDER BY p.createdAt DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// Get statistics
$totalPrescriptions = $pdo->query("SELECT COUNT(*) as count FROM prescriptions")->fetch()['count'];
$activePrescriptions = $pdo->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'active'")->fetch()['count'];
$completedPrescriptions = $pdo->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'completed'")->fetch()['count'];
$expiredPrescriptions = $pdo->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'expired'")->fetch()['count'];
$cancelledPrescriptions = $pdo->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'cancelled'")->fetch()['count'];

// Get top medications
$topMedications = $pdo->query("
    SELECT medicationName, COUNT(*) as count 
    FROM prescriptions 
    GROUP BY medicationName 
    ORDER BY count DESC 
    LIMIT 10
")->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Manage Prescriptions</h1>
        <p>View and manage all patient prescriptions</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card admin">
            <h3><?php echo $totalPrescriptions; ?></h3>
            <p>Total Prescriptions</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $activePrescriptions; ?></h3>
            <p>Active</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $completedPrescriptions; ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $expiredPrescriptions; ?></h3>
            <p>Expired</p>
        </div>
        <div class="stat-card admin">
            <h3><?php echo $cancelledPrescriptions; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>Filter Prescriptions</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="expired" <?php echo $statusFilter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="patient">Patient Name</label>
                        <input type="text" id="patient" name="patient" value="<?php echo htmlspecialchars($patientFilter); ?>" placeholder="Search patient...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="medication">Medication Name</label>
                        <input type="text" id="medication" name="medication" value="<?php echo htmlspecialchars($medicationFilter); ?>" placeholder="Search medication...">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="prescriptions.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Top Medications -->
    <div class="card">
        <div class="card-header">
            <h3>Top 10 Most Prescribed Medications</h3>
        </div>
        <div class="card-body">
            <div class="medications-list">
                <?php foreach ($topMedications as $med): ?>
                    <div class="medication-tag">
                        <span class="med-name"><?php echo htmlspecialchars($med['medicationName']); ?></span>
                        <span class="med-count"><?php echo $med['count']; ?> prescriptions</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Prescriptions Table -->
    <div class="card">
        <div class="card-header">
            <h3>All Prescriptions</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No prescriptions found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $prescription): ?>
                        <tr class="status-<?php echo $prescription['status']; ?>">
                            <td data-label="Date">
                                <?php echo date('M j, Y', strtotime($prescription['createdAt'])); ?><br>
                                <small><?php echo date('g:i A', strtotime($prescription['createdAt'])); ?></small>
                            </td>
                            <td data-label="Patient">
                                <strong><?php echo htmlspecialchars($prescription['patientName']); ?></strong><br>
                                <small><?php echo $prescription['patientEmail']; ?></small>
                            </td>
                            <td data-label="Doctor">
                                Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?>
                            </td>
                            <td data-label="Medication">
                                <strong><?php echo htmlspecialchars($prescription['medicationName']); ?></strong>
                            </td>
                            <td data-label="Dosage"><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                            <td data-label="Frequency"><?php echo htmlspecialchars($prescription['frequency']); ?></td>
                            <td data-label="Duration">
                                <?php echo date('M j, Y', strtotime($prescription['startDate'])); ?> - 
                                <?php echo $prescription['endDate'] ? date('M j, Y', strtotime($prescription['endDate'])) : 'Ongoing'; ?>
                            </td>
                            <td data-label="Status">
                                <span class="status-badge status-<?php echo $prescription['status']; ?>">
                                    <?php echo ucfirst($prescription['status']); ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="viewPrescription(<?php echo $prescription['prescriptionId']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="openModal('statusModal'); document.getElementById('status_prescription_id').value = <?php echo $prescription['prescriptionId']; ?>; document.getElementById('status_select').value = '<?php echo $prescription['status']; ?>';">
                                        <i class="fas fa-edit"></i> Status
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="openModal('deleteModal'); document.getElementById('delete_prescription_id').value = <?php echo $prescription['prescriptionId']; ?>;">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                             </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
             </table>
        </div>
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
            <span class="close" onclick="closeModal('statusModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="prescription_id" id="status_prescription_id">
                <div class="form-group">
                    <label for="status_select">Status</label>
                    <select id="status_select" name="status" required>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                <button type="button" class="btn" onclick="closeModal('statusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Prescription</h3>
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="prescription_id" id="delete_prescription_id">
                <p>Are you sure you want to delete this prescription? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="submit" name="delete_prescription" class="btn btn-danger">Delete Permanently</button>
                <button type="button" class="btn" onclick="closeModal('deleteModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
async function viewPrescription(prescriptionId) {
    const detailsDiv = document.getElementById('prescription-details');
    
    detailsDiv.innerHTML = '<div class="loading">Loading prescription details...</div>';
    openModal('viewModal');
    
    try {
        const response = await fetch(`../ajax/get-prescription.php?id=${prescriptionId}`);
        const data = await response.json();
        
        if (data.success) {
            detailsDiv.innerHTML = `
                <div class="prescription-detail-section">
                    <h4><i class="fas fa-user"></i> Patient Information</h4>
                    <p><strong>Name:</strong> ${data.prescription.patientName}</p>
                    <p><strong>Email:</strong> ${data.prescription.patientEmail}</p>
                    <p><strong>Diagnosis:</strong> ${data.prescription.diagnosis || 'N/A'}</p>
                </div>
                
                <div class="prescription-detail-section">
                    <h4><i class="fas fa-user-md"></i> Prescribing Doctor</h4>
                    <p><strong>Doctor:</strong> Dr. ${data.prescription.doctorName}</p>
                    <p><strong>Prescribed on:</strong> ${new Date(data.prescription.createdAt).toLocaleDateString()}</p>
                </div>
                
                <div class="prescription-detail-section">
                    <h4><i class="fas fa-prescription"></i> Medication Details</h4>
                    <p><strong>Medication:</strong> ${data.prescription.medicationName}</p>
                    <p><strong>Dosage:</strong> ${data.prescription.dosage}</p>
                    <p><strong>Frequency:</strong> ${data.prescription.frequency}</p>
                    <p><strong>Start Date:</strong> ${new Date(data.prescription.startDate).toLocaleDateString()}</p>
                    <p><strong>End Date:</strong> ${data.prescription.endDate ? new Date(data.prescription.endDate).toLocaleDateString() : 'Ongoing'}</p>
                    <p><strong>Refills:</strong> ${data.prescription.refills || 0}</p>
                    <p><strong>Instructions:</strong> ${data.prescription.instructions || 'No additional instructions'}</p>
                </div>
                
                <div class="prescription-detail-section">
                    <h4><i class="fas fa-chart-line"></i> Status</h4>
                    <p><strong>Status:</strong> <span class="status-badge status-${data.prescription.status}">${data.prescription.status.toUpperCase()}</span></p>
                </div>
            `;
        } else {
            detailsDiv.innerHTML = '<div class="alert alert-error">Failed to load prescription details</div>';
        }
    } catch (error) {
        console.error('Error:', error);
        detailsDiv.innerHTML = '<div class="alert alert-error">Error loading prescription details</div>';
    }
}
</script>

<?php include '../includes/footer.php'; ?>