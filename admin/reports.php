<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "Reports - HealthManagement";
include '../includes/header.php';

// Get date range for reports
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'appointments';

// Fetch report data based on type
$reportData = [];

if ($reportType === 'appointments') {
    // Appointments by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM appointments 
        WHERE DATE(dateTime) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $appointmentStatus = $stmt->fetchAll();
    
    // Daily appointments
    $stmt = $pdo->prepare("
        SELECT DATE(dateTime) as date, COUNT(*) as count 
        FROM appointments 
        WHERE DATE(dateTime) BETWEEN ? AND ?
        GROUP BY DATE(dateTime)
        ORDER BY date
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $dailyAppointments = $stmt->fetchAll();
    
    // Appointments by doctor
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.firstName, ' ', u.lastName) as doctorName, COUNT(*) as count 
        FROM appointments a
        JOIN doctors d ON a.doctorId = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        WHERE DATE(a.dateTime) BETWEEN ? AND ?
        GROUP BY a.doctorId
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $doctorAppointments = $stmt->fetchAll();
    
    $reportData = [
        'status' => $appointmentStatus,
        'daily' => $dailyAppointments,
        'doctors' => $doctorAppointments
    ];
    
} elseif ($reportType === 'revenue') {
    // Revenue by status
    $stmt = $pdo->prepare("
        SELECT status, SUM(totalAmount) as total 
        FROM billing 
        WHERE DATE(createdAt) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $revenueByStatus = $stmt->fetchAll();
    
    // Monthly revenue
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(createdAt, '%Y-%m') as month, SUM(totalAmount) as total 
        FROM billing 
        WHERE status = 'paid' AND DATE(createdAt) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(createdAt, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $monthlyRevenue = $stmt->fetchAll();
    
    // Revenue by payment method
    $stmt = $pdo->prepare("
        SELECT paymentMethod, SUM(totalAmount) as total 
        FROM billing 
        WHERE status = 'paid' AND DATE(createdAt) BETWEEN ? AND ?
        GROUP BY paymentMethod
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $paymentMethods = $stmt->fetchAll();
    
    $reportData = [
        'status' => $revenueByStatus,
        'monthly' => $monthlyRevenue,
        'methods' => $paymentMethods
    ];
    
} elseif ($reportType === 'patients') {
    // New patients by month
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(dateCreated, '%Y-%m') as month, COUNT(*) as count 
        FROM users 
        WHERE role = 'patient' AND DATE(dateCreated) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(dateCreated, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $newPatients = $stmt->fetchAll();
    
    // Patients by age group
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, dateOfBirth, CURDATE()) < 18 THEN '0-17'
                WHEN TIMESTAMPDIFF(YEAR, dateOfBirth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                WHEN TIMESTAMPDIFF(YEAR, dateOfBirth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
                WHEN TIMESTAMPDIFF(YEAR, dateOfBirth, CURDATE()) BETWEEN 51 AND 70 THEN '51-70'
                ELSE '70+'
            END as age_group,
            COUNT(*) as count
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE p.dateOfBirth IS NOT NULL
        GROUP BY age_group
        ORDER BY age_group
    ");
    $stmt->execute();
    $ageGroups = $stmt->fetchAll();
    
    // Patients by blood type
    $stmt = $pdo->prepare("
        SELECT bloodType, COUNT(*) as count 
        FROM patients 
        WHERE bloodType IS NOT NULL
        GROUP BY bloodType
        ORDER BY bloodType
    ");
    $stmt->execute();
    $bloodTypes = $stmt->fetchAll();
    
    $reportData = [
        'new' => $newPatients,
        'age' => $ageGroups,
        'blood' => $bloodTypes
    ];
    
} elseif ($reportType === 'doctors') {
    // Doctors performance
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(u.firstName, ' ', u.lastName) as doctorName,
            d.specialization,
            COUNT(a.appointmentId) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            ROUND(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) / COUNT(a.appointmentId) * 100, 2) as completion_rate
        FROM doctors d
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        LEFT JOIN appointments a ON d.doctorId = a.doctorId AND DATE(a.dateTime) BETWEEN ? AND ?
        GROUP BY d.doctorId
        ORDER BY completion_rate DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $doctorPerformance = $stmt->fetchAll();
    
    $reportData = ['performance' => $doctorPerformance];
    
} elseif ($reportType === 'lab-tests') {
    // Lab tests by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM lab_tests 
        WHERE DATE(orderedDate) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $testStatus = $stmt->fetchAll();
    
    // Most common tests
    $stmt = $pdo->prepare("
        SELECT testName, COUNT(*) as count 
        FROM lab_tests 
        WHERE DATE(orderedDate) BETWEEN ? AND ?
        GROUP BY testName
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $commonTests = $stmt->fetchAll();
    
    $reportData = [
        'status' => $testStatus,
        'common' => $commonTests
    ];
    
} elseif ($reportType === 'prescriptions') {
    // Most prescribed medications
    $stmt = $pdo->prepare("
        SELECT medicationName, COUNT(*) as count 
        FROM prescriptions 
        WHERE DATE(createdAt) BETWEEN ? AND ?
        GROUP BY medicationName
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $topMedications = $stmt->fetchAll();
    
    // Prescriptions by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM prescriptions 
        WHERE DATE(createdAt) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $prescriptionStatus = $stmt->fetchAll();
    
    $reportData = [
        'medications' => $topMedications,
        'status' => $prescriptionStatus
    ];
}
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Reports & Analytics</h1>
        <p>Generate and view detailed reports about your healthcare facility</p>
    </div>

    <!-- Report Filters -->
    <div class="card">
        <div class="card-header">
            <h3>Report Filters</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form" id="reportForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="report_type">Report Type</label>
                        <select id="report_type" name="report_type" required>
                            <option value="appointments" <?php echo $reportType == 'appointments' ? 'selected' : ''; ?>>Appointments Report</option>
                            <option value="revenue" <?php echo $reportType == 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                            <option value="patients" <?php echo $reportType == 'patients' ? 'selected' : ''; ?>>Patients Report</option>
                            <option value="doctors" <?php echo $reportType == 'doctors' ? 'selected' : ''; ?>>Doctors Performance</option>
                            <option value="lab-tests" <?php echo $reportType == 'lab-tests' ? 'selected' : ''; ?>>Lab Tests Report</option>
                            <option value="prescriptions" <?php echo $reportType == 'prescriptions' ? 'selected' : ''; ?>>Prescriptions Report</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                        <button type="button" class="btn btn-success" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export to CSV
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Results -->
    <?php if ($reportType === 'appointments'): ?>
    <div class="report-section">
        <div class="card">
            <div class="card-header">
                <h3>Appointments Summary (<?php echo date('M d, Y', strtotime($dateFrom)); ?> - <?php echo date('M d, Y', strtotime($dateTo)); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="stats-summary">
                    <?php
                    $total = array_sum(array_column($reportData['status'], 'count'));
                    $completed = 0;
                    $scheduled = 0;
                    $cancelled = 0;
                    foreach ($reportData['status'] as $status) {
                        if ($status['status'] == 'completed') $completed = $status['count'];
                        elseif ($status['status'] == 'scheduled') $scheduled = $status['count'];
                        elseif ($status['status'] == 'cancelled') $cancelled = $status['count'];
                    }
                    ?>
                    <div class="stat-box">
                        <h4>Total Appointments</h4>
                        <p class="stat-number"><?php echo $total; ?></p>
                    </div>
                    <div class="stat-box">
                        <h4>Completed</h4>
                        <p class="stat-number"><?php echo $completed; ?></p>
                        <small><?php echo $total > 0 ? round(($completed / $total) * 100, 1) : 0; ?>%</small>
                    </div>
                    <div class="stat-box">
                        <h4>Scheduled</h4>
                        <p class="stat-number"><?php echo $scheduled; ?></p>
                    </div>
                    <div class="stat-box">
                        <h4>Cancelled</h4>
                        <p class="stat-number"><?php echo $cancelled; ?></p>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="appointmentChart"></canvas>
                </div>
                
                <h3>Daily Appointments</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr><th>Date</th><th>Appointments</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['daily'] as $day): ?>
                            <tr><td data-label="Date"><?php echo date('M j, Y', strtotime($day['date'])); ?></td><td data-label="Appointments"><?php echo $day['count']; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <h3>Top Doctors by Appointments</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Doctor</th><th>Appointments</th></tr></thead>
                        <tbody>
                            <?php foreach ($reportData['doctors'] as $doctor): ?>
                            <tr><td data-label="Doctor">Dr. <?php echo $doctor['doctorName']; ?></td><td data-label="Appointments"><?php echo $doctor['count']; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Similar structure for other report types... -->
    
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Generate charts based on report type
<?php if ($reportType === 'appointments'): ?>
const appointmentCtx = document.getElementById('appointmentChart')?.getContext('2d');
if (appointmentCtx) {
    new Chart(appointmentCtx, {
        type: 'pie',
        data: {
            labels: [<?php foreach ($reportData['status'] as $status) { echo "'" . ucfirst($status['status']) . "', "; } ?>],
            datasets: [{
                data: [<?php foreach ($reportData['status'] as $status) { echo $status['count'] . ", "; } ?>],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Appointments by Status' } } }
    });
}
<?php endif; ?>

function exportReport() {
    const reportType = document.getElementById('report_type').value;
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    
    if (!dateFrom || !dateTo) {
        alert('Please select date range before exporting.');
        return;
    }
    
    window.location.href = `export-report.php?type=${reportType}&date_from=${dateFrom}&date_to=${dateTo}`;
}
</script>

<?php include '../includes/footer.php'; ?>