<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$pageTitle = "Medical Records - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/nurse.css">';
include '../includes/header.php';

$searchTerm = $_GET['search'] ?? '';
$patientId = $_GET['patient_id'] ?? 0;

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
    $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
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

<div class="nurse-container">
    <div class="nurse-page-header">
        <div class="header-title">
            <h1><i class="fas fa-notes-medical"></i> Medical Records</h1>
            <p>View patient medical records</p>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-search"></i> Search Records</h3>
        </div>
        <div class="nurse-card-body">
            <form method="GET" class="nurse-search-group">
                <input type="text" name="search" placeholder="Search by patient name or diagnosis..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit" class="nurse-btn nurse-btn-primary"><i class="fas fa-search"></i> Search</button>
                <?php if ($searchTerm || $patientId): ?>
                    <a href="medical-records.php" class="nurse-btn nurse-btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="nurse-card">
        <div class="nurse-card-header">
            <h3><i class="fas fa-list"></i> Medical Records (<?php echo count($records); ?>)</h3>
        </div>
        <div class="nurse-table-responsive">
            <table class="nurse-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td data-label="Date"><?php echo date('M j, Y', strtotime($r['creationDate'])); ?></td>
                            <td data-label="Patient">
                                <strong><?php echo htmlspecialchars($r['patientName']); ?></strong><br>
                                <small><?php echo $r['patientEmail']; ?></small>
                            </td>
                            <td data-label="Doctor">Dr. <?php echo htmlspecialchars($r['doctorName']); ?></td>
                            <td data-label="Diagnosis"><?php echo htmlspecialchars(substr($r['diagnosis'], 0, 60)); ?>...</td>
                            <td data-label="Actions">
                                <button class="nurse-btn nurse-btn-primary nurse-btn-sm" onclick="viewRecord(<?php echo $r['recordId']; ?>)">
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
function viewRecord(id) {
    window.location.href = `medical-records-view.php?id=${id}`;
}
</script>

<?php include '../includes/footer.php'; ?>