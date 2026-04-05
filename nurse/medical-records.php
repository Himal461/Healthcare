<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Medical Records - HealthManagement";
include '../includes/header.php';

$userId = $_SESSION['user_id'];
$searchTerm = $_GET['search'] ?? '';
$patientId = $_GET['patient_id'] ?? 0;

// Get medical records
$query = "
    SELECT mr.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           p.dateOfBirth,
           p.bloodType
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users u ON p.userId = u.userId
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE 1=1
";

$params = [];

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR mr.diagnosis LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($patientId) {
    $query .= " AND mr.patientId = ?";
    $params[] = $patientId;
}

$query .= " ORDER BY mr.creationDate DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Medical Records</h1>
        <p>View patient medical records and history</p>
    </div>

    <!-- Search Form -->
    <div class="card">
        <div class="card-header">
            <h3>Search Medical Records</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="search-form">
                <div class="search-group">
                    <input type="text" name="search" placeholder="Search by patient name or diagnosis..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($searchTerm || $patientId): ?>
                        <a href="medical-records.php" class="btn btn-outline">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Medical Records List -->
    <div class="card">
        <div class="card-header">
            <h3>Medical Records (<?php echo count($records); ?> found)</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Treatment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No medical records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($record['creationDate'])); ?></td>
                                <td data-label="Patient">
                                    <strong><?php echo htmlspecialchars($record['patientName']); ?></strong><br>
                                    <small><?php echo $record['patientEmail']; ?></small>
                                </td>
                                <td data-label="Doctor">Dr. <?php echo htmlspecialchars($record['doctorName']); ?></td>
                                <td data-label="Diagnosis"><?php echo substr($record['diagnosis'], 0, 60) . (strlen($record['diagnosis']) > 60 ? '...' : ''); ?></td>
                                <td data-label="Treatment"><?php echo substr($record['treatmentNotes'], 0, 60) . (strlen($record['treatmentNotes']) > 60 ? '...' : ''); ?></td>
                                <td data-label="Actions">
                                    <button class="btn btn-primary btn-sm" onclick="viewRecord(<?php echo $record['recordId']; ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="viewVitals(<?php echo $record['recordId']; ?>)">
                                        <i class="fas fa-heartbeat"></i> Vitals
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
function viewRecord(recordId) {
    window.location.href = `..\/admin\/medical-records.php?view=${recordId}`;
}

function viewVitals(recordId) {
    window.location.href = `vitals.php?record_id=${recordId}`;
}
<\/script>

<?php include '..\/includes\/footer.php'; ?>