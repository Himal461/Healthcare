<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Reports - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
include '../includes/header.php';

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'appointments';

$reportData = [];

if ($reportType === 'appointments') {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE DATE(dateTime) BETWEEN ? AND ? GROUP BY status");
    $stmt->execute([$dateFrom, $dateTo]);
    $appointmentStatus = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT DATE(dateTime) as date, COUNT(*) as count FROM appointments WHERE DATE(dateTime) BETWEEN ? AND ? GROUP BY DATE(dateTime) ORDER BY date");
    $stmt->execute([$dateFrom, $dateTo]);
    $dailyAppointments = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.firstName, ' ', u.lastName) as doctorName, COUNT(*) as count 
        FROM appointments a JOIN doctors d ON a.doctorId = d.doctorId 
        JOIN staff s ON d.staffId = s.staffId JOIN users u ON s.userId = u.userId
        WHERE DATE(a.dateTime) BETWEEN ? AND ? GROUP BY a.doctorId ORDER BY count DESC LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $doctorAppointments = $stmt->fetchAll();
    
    $reportData = ['status' => $appointmentStatus, 'daily' => $dailyAppointments, 'doctors' => $doctorAppointments];
} elseif ($reportType === 'revenue') {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(generatedAt, '%Y-%m') as month, SUM(totalAmount) as total FROM bills WHERE status = 'paid' AND DATE(generatedAt) BETWEEN ? AND ? GROUP BY DATE_FORMAT(generatedAt, '%Y-%m') ORDER BY month");
    $stmt->execute([$dateFrom, $dateTo]);
    $monthlyRevenue = $stmt->fetchAll();
    $reportData = ['monthly' => $monthlyRevenue];
} elseif ($reportType === 'patients') {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(dateCreated, '%Y-%m') as month, COUNT(*) as count FROM users WHERE role = 'patient' AND DATE(dateCreated) BETWEEN ? AND ? GROUP BY DATE_FORMAT(dateCreated, '%Y-%m') ORDER BY month");
    $stmt->execute([$dateFrom, $dateTo]);
    $newPatients = $stmt->fetchAll();
    $reportData = ['new' => $newPatients];
}
?>

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-flag"></i> Reports & Analytics</h1>
            <p>Generate and view detailed reports</p>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-filter"></i> Report Filters</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="admin-filter-form" id="reportForm">
                <div class="admin-filter-row">
                    <div class="admin-filter-group">
                        <select name="report_type" class="admin-form-control" required>
                            <option value="appointments" <?php echo $reportType=='appointments'?'selected':''; ?>>Appointments</option>
                            <option value="revenue" <?php echo $reportType=='revenue'?'selected':''; ?>>Revenue</option>
                            <option value="patients" <?php echo $reportType=='patients'?'selected':''; ?>>Patients</option>
                        </select>
                    </div>
                    <div class="admin-filter-group">
                        <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" class="admin-form-control">
                    </div>
                    <div class="admin-filter-group">
                        <input type="date" name="date_to" value="<?php echo $dateTo; ?>" class="admin-form-control">
                    </div>
                    <div class="admin-filter-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">Generate</button>
                        <button type="button" class="admin-btn admin-btn-success" onclick="exportReport()"><i class="fas fa-download"></i> Export CSV</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($reportType === 'appointments'): ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-calendar-alt"></i> Appointments Summary</h3>
        </div>
        <div class="admin-card-body">
            <?php
            $total = array_sum(array_column($reportData['status'], 'count'));
            $completed = 0; $scheduled = 0; $cancelled = 0;
            foreach ($reportData['status'] as $s) {
                if ($s['status'] == 'completed') $completed = $s['count'];
                elseif ($s['status'] == 'scheduled') $scheduled = $s['count'];
                elseif ($s['status'] == 'cancelled') $cancelled = $s['count'];
            }
            ?>
            <div style="display: flex; gap: 30px; margin-bottom: 30px;">
                <div><h4>Total</h4><p style="font-size: 28px; font-weight: 700; color: #dc2626;"><?php echo $total; ?></p></div>
                <div><h4>Completed</h4><p style="font-size: 28px; font-weight: 700; color: #10b981;"><?php echo $completed; ?></p></div>
                <div><h4>Scheduled</h4><p style="font-size: 28px; font-weight: 700; color: #3b82f6;"><?php echo $scheduled; ?></p></div>
                <div><h4>Cancelled</h4><p style="font-size: 28px; font-weight: 700; color: #ef4444;"><?php echo $cancelled; ?></p></div>
            </div>
            
            <h3>Top Doctors</h3>
            <table class="admin-data-table">
                <thead><tr><th>Doctor</th><th>Appointments</th></tr></thead>
                <tbody>
                    <?php foreach ($reportData['doctors'] as $d): ?>
                        <tr><td>Dr. <?php echo $d['doctorName']; ?></td><td><?php echo $d['count']; ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($reportType === 'revenue'): ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-dollar-sign"></i> Monthly Revenue</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead><tr><th>Month</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php foreach ($reportData['monthly'] as $m): ?>
                        <tr><td><?php echo $m['month']; ?></td><td>$<?php echo number_format($m['total'], 2); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($reportType === 'patients'): ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-user-plus"></i> New Patients by Month</h3>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead><tr><th>Month</th><th>New Patients</th></tr></thead>
                <tbody>
                    <?php foreach ($reportData['new'] as $p): ?>
                        <tr><td><?php echo $p['month']; ?></td><td><?php echo $p['count']; ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>