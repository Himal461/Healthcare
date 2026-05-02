<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Prescriptions - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$query = "
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName, 
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           u.email as patientEmail, 
           u.phoneNumber as patientPhone, 
           mr.diagnosis
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

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR p.medicationName LIKE ?)";
    $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
}
if ($statusFilter) {
    $query .= " AND p.status = ?";
    $params[] = $statusFilter;
}
$query .= " ORDER BY p.createdAt DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();
?>

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-prescription"></i> Prescriptions</h1>
            <p>View patient prescriptions</p>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-filter"></i> Filter Prescriptions</h3>
        </div>
        <div class="nurse-card-body">
            <form method="GET" class="nurse-filter-form">
                <div class="nurse-filter-row">
                    <div class="nurse-filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="nurse-filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Patient name or medication..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="nurse-filter-actions">
                        <button type="submit" class="nurse-btn nurse-btn-primary">Filter</button>
                        <a href="prescriptions.php" class="nurse-btn nurse-btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-list"></i> Prescriptions (<?php echo count($prescriptions); ?>)</h3>
        </div>
        <div class="nurse-table-responsive">
            <table class="nurse-data-table">
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
                    <?php foreach ($prescriptions as $p): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($p['createdAt'])); ?></td>
                            <td data-label="Patient"><?php echo htmlspecialchars($p['patientName']); ?></td>
                            <td data-label="Doctor">Dr. <?php echo htmlspecialchars($p['doctorName']); ?></td>
                            <td data-label="Medication"><?php echo htmlspecialchars($p['medicationName']); ?></td>
                            <td data-label="Dosage"><?php echo htmlspecialchars($p['dosage']); ?></td>
                            <td data-label="Status">
                                <span class="nurse-status-badge nurse-status-<?php echo $p['status']; ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <button class="nurse-btn nurse-btn-primary nurse-btn-sm" onclick="viewPrescription(<?php echo $p['prescriptionId']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewPrescription(id) {
    window.location.href = `prescription-details.php?id=${id}`;
}
</script>

<?php include '../includes/footer.php'; ?>