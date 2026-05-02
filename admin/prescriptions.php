<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Manage Prescriptions - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $prescriptionId = $_POST['prescription_id'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE prescriptions SET status = ? WHERE prescriptionId = ?");
    $stmt->execute([$status, $prescriptionId]);
    $_SESSION['success'] = "Prescription updated!";
    logAction($_SESSION['user_id'], 'UPDATE_PRESCRIPTION', "Updated prescription $prescriptionId to $status");
    header("Location: prescriptions.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_prescription'])) {
    $prescriptionId = $_POST['prescription_id'];
    $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE prescriptionId = ?");
    $stmt->execute([$prescriptionId]);
    $_SESSION['success'] = "Prescription deleted!";
    header("Location: prescriptions.php");
    exit();
}

$statusFilter = $_GET['status'] ?? '';
$patientFilter = $_GET['patient'] ?? '';
$medicationFilter = $_GET['medication'] ?? '';

$query = "
    SELECT p.*, CONCAT(u.firstName, ' ', u.lastName) as patientName, u.email as patientEmail,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName, mr.diagnosis
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN patients pt ON mr.patientId = pt.patientId
    JOIN users u ON pt.userId = u.userId
    JOIN doctors d ON p.prescribedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE u.role = 'patient'
";
$params = [];

if ($statusFilter) { $query .= " AND p.status = ?"; $params[] = $statusFilter; }
if ($patientFilter) { $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ?)"; $params[] = "%$patientFilter%"; $params[] = "%$patientFilter%"; }
if ($medicationFilter) { $query .= " AND p.medicationName LIKE ?"; $params[] = "%$medicationFilter%"; }

$query .= " ORDER BY p.createdAt DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

$totalPrescriptions = $pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();
$activePrescriptions = $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE status = 'active'")->fetchColumn();
$completedPrescriptions = $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE status = 'completed'")->fetchColumn();

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-prescription"></i> Manage Prescriptions</h1>
            <p>View and manage all prescriptions</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="admin-stats-grid">
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $totalPrescriptions; ?></h3>
                <p>Total</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-play-circle"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $activePrescriptions; ?></h3>
                <p>Active</p>
            </div>
        </div>
        <div class="admin-stat-card patients">
            <div class="admin-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="admin-stat-content">
                <h3><?php echo $completedPrescriptions; ?></h3>
                <p>Completed</p>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-filter"></i> Filter Prescriptions</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="admin-filter-row">
                <div class="admin-filter-group">
                    <select name="status" class="admin-form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter=='active'?'selected':''; ?>>Active</option>
                        <option value="completed" <?php echo $statusFilter=='completed'?'selected':''; ?>>Completed</option>
                        <option value="expired" <?php echo $statusFilter=='expired'?'selected':''; ?>>Expired</option>
                        <option value="cancelled" <?php echo $statusFilter=='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="admin-filter-group">
                    <input type="text" name="patient" placeholder="Patient name" value="<?php echo htmlspecialchars($patientFilter); ?>" class="admin-form-control">
                </div>
                <div class="admin-filter-group">
                    <input type="text" name="medication" placeholder="Medication" value="<?php echo htmlspecialchars($medicationFilter); ?>" class="admin-form-control">
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">Filter</button>
                    <a href="prescriptions.php" class="admin-btn admin-btn-outline">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-list"></i> All Prescriptions</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr><td colspan="7" class="admin-empty-message">No prescriptions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $p): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($p['createdAt'])); ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($p['patientName']); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($p['doctorName']); ?></td>
                                <td data-label="Medication"><?php echo htmlspecialchars($p['medicationName']); ?></td>
                                <td data-label="Dosage"><?php echo htmlspecialchars($p['dosage']); ?></td>
                                <td data-label="Status">
                                    <span class="admin-status-badge admin-status-<?php echo $p['status']; ?>">
                                        <?php echo ucfirst($p['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="admin-action-buttons">
                                        <button class="admin-btn admin-btn-primary admin-btn-sm" onclick="viewPrescription(<?php echo $p['prescriptionId']; ?>)">View</button>
                                        <button class="admin-btn admin-btn-warning admin-btn-sm" onclick="openModal('statusModal'); document.getElementById('status_prescription_id').value=<?php echo $p['prescriptionId']; ?>;">Status</button>
                                        <button class="admin-btn admin-btn-danger admin-btn-sm" onclick="openModal('deleteModal'); document.getElementById('delete_prescription_id').value=<?php echo $p['prescriptionId']; ?>;">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Update Status</h3>
            <span class="admin-modal-close" onclick="closeModal('statusModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="admin-modal-body">
                <input type="hidden" name="prescription_id" id="status_prescription_id">
                <div class="admin-form-group">
                    <label>Status</label>
                    <select name="status" class="admin-form-control" required>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" name="update_status" class="admin-btn admin-btn-primary">Update</button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('statusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Delete Prescription</h3>
            <span class="admin-modal-close" onclick="closeModal('deleteModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="admin-modal-body">
                <input type="hidden" name="prescription_id" id="delete_prescription_id">
                <p>Are you sure you want to delete this prescription?</p>
            </div>
            <div class="admin-modal-footer">
                <button type="submit" name="delete_prescription" class="admin-btn admin-btn-danger">Delete</button>
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
async function viewPrescription(id) {
    try {
        const res = await fetch(`../ajax/get-prescription.php?id=${id}`);
        const data = await res.json();
        if (data.success) {
            alert(`Prescription: ${data.prescription.medicationName}\nPatient: ${data.prescription.patientName}\nDoctor: ${data.prescription.doctorName}\nDosage: ${data.prescription.dosage}`);
        }
    } catch (e) {
        alert('Error loading prescription');
    }
}
</script>

<?php include '../includes/footer.php'; ?>