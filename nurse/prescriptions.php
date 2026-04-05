<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Prescriptions - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Get all prescriptions (nurses can view all)
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
    $searchLike = "%$searchTerm%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
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

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Prescriptions</h1>
        <p>View and manage patient prescriptions</p>
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
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Patient name or medication..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="prescriptions.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Prescriptions List -->
    <div class="card">
        <div class="card-header">
            <h3>Prescriptions (<?php echo count($prescriptions); ?> found)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        
                            <td colspan="8" style="text-align: center;">No prescriptions found<\/td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $prescription): ?>
                            
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($prescription['createdAt'])); ?></td>
                                <td data-label="Patient"><?php echo htmlspecialchars($prescription['patientName']); ?></td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?></td>
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
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewPrescription(prescriptionId) {
    window.location.href = `prescription-details.php?id=${prescriptionId}`;
}
</script>

<?php include '..\/includes\/footer.php'; ?>