<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

$pageTitle = "Patients - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/accountant.css">';
include '../includes/header.php';

$searchTerm = $_GET['search'] ?? '';

$query = "
    SELECT p.patientId, p.dateOfBirth, p.bloodType,
           u.firstName, u.lastName, u.email, u.phoneNumber,
           (SELECT COUNT(*) FROM bills WHERE patientId = p.patientId) as total_bills,
           (SELECT SUM(totalAmount) FROM bills WHERE patientId = p.patientId) as total_billed,
           (SELECT SUM(totalAmount) FROM bills WHERE patientId = p.patientId AND status = 'paid') as total_paid,
           (SELECT SUM(totalAmount) FROM bills WHERE patientId = p.patientId AND status = 'unpaid') as total_unpaid
    FROM patients p
    JOIN users u ON p.userId = u.userId
    WHERE 1=1
";
$params = [];

if ($searchTerm) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR u.phoneNumber LIKE ?)";
    $searchLike = "%$searchTerm%";
    $params = [$searchLike, $searchLike, $searchLike, $searchLike];
}

$query .= " ORDER BY u.firstName, u.lastName";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();
?>

<div class="accountant-container">
    <div class="accountant-page-header">
        <div class="header-title">
            <h1><i class="fas fa-users"></i> Patients</h1>
            <p>View patient billing summaries</p>
        </div>
    </div>

    <div class="accountant-card">
        <div class="accountant-card-body">
            <form method="GET" class="accountant-search-group">
                <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit" class="accountant-btn accountant-btn-primary"><i class="fas fa-search"></i> Search</button>
                <?php if ($searchTerm): ?>
                    <a href="patients.php" class="accountant-btn accountant-btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="accountant-card">
        <div class="accountant-card-header">
            <h3><i class="fas fa-list"></i> Patients (<?php echo count($patients); ?>)</h3>
        </div>
        <div class="accountant-card-body">
            <div class="accountant-table-responsive">
                <table class="accountant-data-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Blood Type</th>
                            <th>Total Bills</th>
                            <th>Total Billed</th>
                            <th>Paid</th>
                            <th>Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr><td colspan="7" class="accountant-empty-message">No patients found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td data-label="Patient">
                                        <strong><?php echo htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($patient['email']); ?></small>
                                    </td>
                                    <td data-label="Contact"><?php echo htmlspecialchars($patient['phoneNumber']); ?></td>
                                    <td data-label="Blood Type"><?php echo $patient['bloodType'] ?: 'N/A'; ?></td>
                                    <td data-label="Total Bills"><?php echo $patient['total_bills']; ?></td>
                                    <td data-label="Total Billed">$<?php echo number_format($patient['total_billed'] ?? 0, 2); ?></td>
                                    <td data-label="Paid" class="accountant-text-success">$<?php echo number_format($patient['total_paid'] ?? 0, 2); ?></td>
                                    <td data-label="Outstanding" class="accountant-text-warning">$<?php echo number_format($patient['total_unpaid'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>