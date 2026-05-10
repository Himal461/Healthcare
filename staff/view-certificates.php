<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$pageTitle = "View Medical Certificates - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/staff.css">';
include '../includes/header.php';

$searchTerm = $_GET['search'] ?? '';
$searchResults = [];
$selectedCertificate = null;

// Handle search
if ($searchTerm) {
    $stmt = $pdo->prepare("
        SELECT mc.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patient_name,
               u.email as patient_email,
               u.phoneNumber as patient_phone,
               p.patientId,
               CONCAT(du.firstName, ' ', du.lastName) as doctor_name,
               d.specialization,
               b.billId as bill_id,
               b.totalAmount as bill_amount,
               b.status as bill_status,
               a.dateTime as appointment_datetime,
               a.status as appointment_status
        FROM medical_certificates mc
        JOIN patients p ON mc.patient_id = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN doctors d ON mc.doctor_id = d.doctorId
        LEFT JOIN staff s ON d.staffId = s.staffId
        LEFT JOIN users du ON s.userId = du.userId
        LEFT JOIN bills b ON mc.bill_id = b.billId
        LEFT JOIN appointments a ON mc.appointment_id = a.appointmentId
        WHERE mc.certificate_number LIKE ? 
           OR u.firstName LIKE ? 
           OR u.lastName LIKE ? 
           OR u.email LIKE ? 
           OR u.phoneNumber LIKE ?
           OR CONCAT(u.firstName, ' ', u.lastName) LIKE ?
        ORDER BY mc.created_at DESC
        LIMIT 50
    ");
    $searchLike = "%$searchTerm%";
    $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike]);
    $searchResults = $stmt->fetchAll();
}

// View single certificate
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId) {
    $stmt = $pdo->prepare("
        SELECT mc.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patient_name,
               u.email as patient_email,
               u.phoneNumber as patient_phone,
               p.patientId, p.dateOfBirth,
               CONCAT(du.firstName, ' ', du.lastName) as doctor_name,
               d.specialization,
               b.billId as bill_id, b.totalAmount as bill_amount,
               b.status as bill_status, b.paidAt as bill_paid_at,
               a.dateTime as appointment_datetime, a.status as appointment_status
        FROM medical_certificates mc
        JOIN patients p ON mc.patient_id = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN doctors d ON mc.doctor_id = d.doctorId
        LEFT JOIN staff s ON d.staffId = s.staffId
        LEFT JOIN users du ON s.userId = du.userId
        LEFT JOIN bills b ON mc.bill_id = b.billId
        LEFT JOIN appointments a ON mc.appointment_id = a.appointmentId
        WHERE mc.certificate_id = ?
    ");
    $stmt->execute([$viewId]);
    $selectedCertificate = $stmt->fetch();
}

$certificateTypes = [
    'work' => 'Work Leave',
    'school' => 'School/University Leave',
    'travel' => 'Travel Cancellation',
    'insurance' => 'Insurance Claim',
    'other' => 'Other'
];

// Recent certificates
$recentCerts = $pdo->query("
    SELECT mc.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patient_name,
           u.email as patient_email
    FROM medical_certificates mc
    JOIN patients p ON mc.patient_id = p.patientId
    JOIN users u ON p.userId = u.userId
    ORDER BY mc.created_at DESC
    LIMIT 10
")->fetchAll();
?>

<div class="staff-container">
    <div class="staff-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-medical"></i> Medical Certificates</h1>
            <p>Search and view patient medical certificates</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="staff-btn staff-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($selectedCertificate): ?>
        <!-- Single Certificate Detail View -->
        <div class="staff-card">
            <div class="staff-card-header">
                <h3>
                    <i class="fas fa-file-medical"></i> 
                    Certificate #<?php echo htmlspecialchars($selectedCertificate['certificate_number']); ?>
                </h3>
                <div>
                    <a href="view-certificates.php" class="staff-btn staff-btn-outline staff-btn-sm">
                        <i class="fas fa-search"></i> New Search
                    </a>
                    <a href="view-certificates.php?search=<?php echo urlencode($selectedCertificate['patient_name']); ?>" class="staff-btn staff-btn-outline staff-btn-sm">
                        <i class="fas fa-list"></i> Back to Results
                    </a>
                </div>
            </div>
            <div class="staff-card-body">
                <!-- Status -->
                <div style="margin-bottom: 25px;">
                    <?php
                    $status = $selectedCertificate['approval_status'] ?? 'pending';
                    $statusText = match($status) {
                        'approved' => '✓ Approved',
                        'rejected' => '✗ Rejected',
                        'pending_consultation' => '⏳ Pending Consultation',
                        default => '⏳ ' . ucfirst($status)
                    };
                    $statusBg = match($status) {
                        'approved' => '#dcfce7',
                        'rejected' => '#fee2e2',
                        default => '#fef3c7'
                    };
                    $statusColor = match($status) {
                        'approved' => '#166534',
                        'rejected' => '#991b1b',
                        default => '#92400e'
                    };
                    ?>
                    <span style="display: inline-block; padding: 8px 20px; border-radius: 40px; font-size: 14px; font-weight: 700; background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>;">
                        <?php echo $statusText; ?>
                    </span>
                </div>

                <div class="staff-patient-info-grid">
                    <div class="staff-info-group">
                        <h4>Patient</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($selectedCertificate['patient_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selectedCertificate['patient_email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($selectedCertificate['patient_phone']); ?></p>
                        <?php if ($selectedCertificate['dateOfBirth']): ?>
                            <p><strong>DOB:</strong> <?php echo date('M j, Y', strtotime($selectedCertificate['dateOfBirth'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="staff-info-group">
                        <h4>Doctor</h4>
                        <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($selectedCertificate['doctor_name'] ?? 'Not Assigned'); ?></p>
                        <p><strong>Specialization:</strong> <?php echo htmlspecialchars($selectedCertificate['specialization'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="staff-info-group">
                        <h4>Certificate</h4>
                        <p><strong>Type:</strong> <?php echo $certificateTypes[$selectedCertificate['certificate_type']] ?? $selectedCertificate['certificate_type']; ?></p>
                        <p><strong>Period:</strong> <?php echo date('M j, Y', strtotime($selectedCertificate['start_date'])) . ' - ' . date('M j, Y', strtotime($selectedCertificate['end_date'])); ?></p>
                        <p><strong>Condition:</strong> <?php echo htmlspecialchars($selectedCertificate['medical_condition']); ?></p>
                    </div>
                    <div class="staff-info-group">
                        <h4>Payment</h4>
                        <p><strong>Amount:</strong> $<?php echo number_format($selectedCertificate['amount_paid'], 2); ?></p>
                        <p><strong>Bill #:</strong> #<?php echo str_pad($selectedCertificate['bill_id'] ?? 0, 6, '0', STR_PAD_LEFT); ?></p>
                        <p><strong>Bill Status:</strong> <?php echo ucfirst($selectedCertificate['bill_status'] ?? 'N/A'); ?></p>
                    </div>
                </div>

                <?php if ($selectedCertificate['appointment_datetime']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fffbeb; border-radius: 12px;">
                        <p><strong><i class="fas fa-calendar-check"></i> Consultation:</strong> 
                            <?php echo date('F j, Y g:i A', strtotime($selectedCertificate['appointment_datetime'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <a href="../download-certificate.php?file=<?php echo $selectedCertificate['certificate_number']; ?>" class="staff-btn staff-btn-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                    <a href="patient-records.php?patient_id=<?php echo $selectedCertificate['patientId']; ?>" class="staff-btn staff-btn-outline">
                        <i class="fas fa-user"></i> View Patient
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Search -->
        <div class="staff-card">
            <div class="staff-card-header">
                <h3><i class="fas fa-search"></i> Search Certificates</h3>
            </div>
            <div class="staff-card-body">
                <form method="GET" class="staff-search-group">
                    <input type="text" name="search" 
                           placeholder="Search by certificate number, patient name, email, or phone..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           class="staff-form-control" style="flex: 1;">
                    <button type="submit" class="staff-btn staff-btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($searchTerm): ?>
                        <a href="view-certificates.php" class="staff-btn staff-btn-outline">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($searchTerm): ?>
            <div class="staff-card">
                <div class="staff-card-header">
                    <h3><i class="fas fa-list"></i> Results (<?php echo count($searchResults); ?>)</h3>
                </div>
                <div class="staff-table-responsive">
                    <?php if (empty($searchResults)): ?>
                        <p class="staff-empty-message">No certificates found.</p>
                    <?php else: ?>
                        <table class="staff-data-table">
                            <thead>
                                <tr>
                                    <th>Certificate #</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $cert): ?>
                                    <tr>
                                        <td data-label="Certificate #"><?php echo htmlspecialchars($cert['certificate_number']); ?></td>
                                        <td data-label="Patient">
                                            <?php echo htmlspecialchars($cert['patient_name']); ?><br>
                                            <small><?php echo htmlspecialchars($cert['patient_email']); ?></small>
                                        </td>
                                        <td data-label="Doctor"><?php echo $cert['doctor_name'] ? 'Dr. ' . htmlspecialchars($cert['doctor_name']) : '—'; ?></td>
                                        <td data-label="Period"><?php echo date('M j', strtotime($cert['start_date'])) . ' - ' . date('M j', strtotime($cert['end_date'])); ?></td>
                                        <td data-label="Status">
                                            <?php
                                            $s = $cert['approval_status'] ?? 'pending';
                                            $st = $s === 'approved' ? 'Approved' : ($s === 'rejected' ? 'Rejected' : 'Pending');
                                            ?>
                                            <span class="staff-status-badge staff-status-<?php echo $s === 'approved' ? 'paid' : ($s === 'rejected' ? 'cancelled' : 'unpaid'); ?>">
                                                <?php echo $st; ?>
                                            </span>
                                        </td>
                                        <td data-label="Actions">
                                            <a href="?view=<?php echo $cert['certificate_id']; ?>&search=<?php echo urlencode($searchTerm); ?>" class="staff-btn staff-btn-primary staff-btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="staff-card">
                <div class="staff-card-header">
                    <h3><i class="fas fa-clock"></i> Recent Certificates</h3>
                </div>
                <div class="staff-table-responsive">
                    <table class="staff-data-table">
                        <thead>
                            <tr>
                                <th>Certificate #</th>
                                <th>Patient</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCerts as $cert): ?>
                                <tr>
                                    <td data-label="Certificate #"><?php echo htmlspecialchars($cert['certificate_number']); ?></td>
                                    <td data-label="Patient"><?php echo htmlspecialchars($cert['patient_name']); ?></td>
                                    <td data-label="Type"><?php echo $certificateTypes[$cert['certificate_type']] ?? $cert['certificate_type']; ?></td>
                                    <td data-label="Amount">$<?php echo number_format($cert['amount_paid'], 2); ?></td>
                                    <td data-label="Status">
                                        <?php
                                        $s = $cert['approval_status'] ?? 'pending';
                                        $st = $s === 'approved' ? 'Approved' : ($s === 'rejected' ? 'Rejected' : 'Pending');
                                        ?>
                                        <span class="staff-status-badge staff-status-<?php echo $s === 'approved' ? 'paid' : ($s === 'rejected' ? 'cancelled' : 'unpaid'); ?>">
                                            <?php echo $st; ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="?view=<?php echo $cert['certificate_id']; ?>" class="staff-btn staff-btn-primary staff-btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>