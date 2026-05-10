<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$pageTitle = "View Medical Certificates - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/admin.css">';
$extraJS = '<script src="../js/admin.js"></script>';
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
               s.licenseNumber,
               b.billId as bill_id,
               b.totalAmount as bill_amount,
               b.status as bill_status,
               b.paidAt as bill_paid_at,
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
               p.patientId, p.dateOfBirth, p.address, p.bloodType,
               CONCAT(du.firstName, ' ', du.lastName) as doctor_name,
               d.specialization, s.licenseNumber,
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

<div class="admin-container">
    <div class="admin-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-medical"></i> Medical Certificates</h1>
            <p>Search and view all medical certificates</p>
        </div>
    </div>

    <?php if ($selectedCertificate): ?>
        <!-- Single Certificate Detail View -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>
                    <i class="fas fa-file-medical"></i> 
                    Certificate #<?php echo htmlspecialchars($selectedCertificate['certificate_number']); ?>
                </h3>
                <div>
                    <a href="view-certificates.php" class="admin-btn admin-btn-outline admin-btn-sm">
                        <i class="fas fa-search"></i> New Search
                    </a>
                    <a href="view-certificates.php?search=<?php echo urlencode($selectedCertificate['patient_name']); ?>" class="admin-btn admin-btn-outline admin-btn-sm">
                        <i class="fas fa-list"></i> Back to Results
                    </a>
                </div>
            </div>
            <div class="admin-card-body">
                <!-- Status Badge -->
                <div style="margin-bottom: 25px;">
                    <?php
                    $status = $selectedCertificate['approval_status'] ?? 'pending';
                    $statusClass = match($status) {
                        'approved' => 'admin-status-completed',
                        'rejected' => 'admin-status-cancelled',
                        'pending_consultation' => 'admin-status-pending',
                        default => 'admin-status-pending'
                    };
                    $statusText = match($status) {
                        'approved' => '✓ Approved',
                        'rejected' => '✗ Rejected',
                        'pending_consultation' => '⏳ Pending Consultation',
                        default => '⏳ ' . ucfirst($status)
                    };
                    ?>
                    <span class="admin-status-badge <?php echo $statusClass; ?>" style="font-size: 14px; padding: 8px 20px;">
                        <?php echo $statusText; ?>
                    </span>
                </div>

                <div class="admin-patient-info-grid">
                    <div class="admin-info-group">
                        <h4><i class="fas fa-user"></i> Patient Information</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($selectedCertificate['patient_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selectedCertificate['patient_email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($selectedCertificate['patient_phone']); ?></p>
                        <?php if ($selectedCertificate['dateOfBirth']): ?>
                            <p><strong>DOB:</strong> <?php echo date('M j, Y', strtotime($selectedCertificate['dateOfBirth'])); ?></p>
                        <?php endif; ?>
                        <?php if ($selectedCertificate['bloodType']): ?>
                            <p><strong>Blood Type:</strong> <?php echo $selectedCertificate['bloodType']; ?></p>
                        <?php endif; ?>
                        <?php if ($selectedCertificate['address']): ?>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($selectedCertificate['address']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="admin-info-group">
                        <h4><i class="fas fa-user-md"></i> Doctor Information</h4>
                        <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($selectedCertificate['doctor_name'] ?? 'Not Assigned'); ?></p>
                        <p><strong>Specialization:</strong> <?php echo htmlspecialchars($selectedCertificate['specialization'] ?? 'N/A'); ?></p>
                        <p><strong>License:</strong> <?php echo htmlspecialchars($selectedCertificate['licenseNumber'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="admin-info-group">
                        <h4><i class="fas fa-file-medical"></i> Certificate Details</h4>
                        <p><strong>Type:</strong> <?php echo $certificateTypes[$selectedCertificate['certificate_type']] ?? $selectedCertificate['certificate_type']; ?></p>
                        <p><strong>Condition:</strong> <?php echo htmlspecialchars($selectedCertificate['medical_condition']); ?></p>
                        <p><strong>Period:</strong> <?php echo date('M j, Y', strtotime($selectedCertificate['start_date'])) . ' - ' . date('M j, Y', strtotime($selectedCertificate['end_date'])); ?></p>
                        <p><strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($selectedCertificate['created_at'])); ?></p>
                        <?php if ($selectedCertificate['approved_at']): ?>
                            <p><strong>Processed:</strong> <?php echo date('M j, Y g:i A', strtotime($selectedCertificate['approved_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="admin-info-group">
                        <h4><i class="fas fa-credit-card"></i> Payment</h4>
                        <p><strong>Amount:</strong> $<?php echo number_format($selectedCertificate['amount_paid'], 2); ?></p>
                        <p><strong>Method:</strong> <?php echo ucfirst($selectedCertificate['payment_method'] ?? 'N/A'); ?></p>
                        <p><strong>Bill #:</strong> #<?php echo str_pad($selectedCertificate['bill_id'] ?? 0, 6, '0', STR_PAD_LEFT); ?></p>
                        <p><strong>Bill Status:</strong> 
                            <span class="admin-status-badge admin-status-<?php echo $selectedCertificate['bill_status'] ?? 'unpaid'; ?>">
                                <?php echo ucfirst($selectedCertificate['bill_status'] ?? 'N/A'); ?>
                            </span>
                        </p>
                        <?php if ($selectedCertificate['bill_paid_at']): ?>
                            <p><strong>Paid On:</strong> <?php echo date('M j, Y g:i A', strtotime($selectedCertificate['bill_paid_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selectedCertificate['appointment_datetime']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #eff6ff; border-radius: 12px; border: 1px solid #bfdbfe;">
                        <p><strong><i class="fas fa-calendar-check"></i> Consultation Appointment:</strong> 
                            <?php echo date('F j, Y g:i A', strtotime($selectedCertificate['appointment_datetime'])); ?>
                            (<span class="admin-status-badge admin-status-<?php echo $selectedCertificate['appointment_status']; ?>"><?php echo ucfirst($selectedCertificate['appointment_status']); ?></span>)
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($selectedCertificate['additional_notes']): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fefce8; border-radius: 12px; border-left: 4px solid #f59e0b;">
                        <strong>Additional Notes:</strong>
                        <p style="margin-top: 5px;"><?php echo nl2br(htmlspecialchars($selectedCertificate['additional_notes'])); ?></p>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <a href="../download-certificate.php?file=<?php echo $selectedCertificate['certificate_number']; ?>" class="admin-btn admin-btn-primary">
                        <i class="fas fa-download"></i> Download Certificate
                    </a>
                    <a href="view-patient.php?id=<?php echo $selectedCertificate['patientId']; ?>" class="admin-btn admin-btn-outline">
                        <i class="fas fa-user"></i> View Patient
                    </a>
                    <?php if ($selectedCertificate['bill_id']): ?>
                        <a href="view-bill.php?bill_id=<?php echo $selectedCertificate['bill_id']; ?>" class="admin-btn admin-btn-outline">
                            <i class="fas fa-file-invoice"></i> View Bill
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Search Section -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-search"></i> Search Certificates</h3>
            </div>
            <div class="admin-card-body">
                <form method="GET" class="admin-search-group">
                    <input type="text" name="search" 
                           placeholder="Search by certificate number, patient name, email, or phone..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           class="admin-form-control" style="flex: 1;">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($searchTerm): ?>
                        <a href="view-certificates.php" class="admin-btn admin-btn-outline">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($searchTerm): ?>
            <!-- Search Results -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fas fa-list"></i> Search Results (<?php echo count($searchResults); ?> found)</h3>
                </div>
                <div class="admin-table-responsive">
                    <?php if (empty($searchResults)): ?>
                        <p class="admin-empty-message">No certificates found matching "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                    <?php else: ?>
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>Certificate #</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Type</th>
                                    <th>Period</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $cert): ?>
                                    <tr>
                                        <td data-label="Certificate #">
                                            <strong><?php echo htmlspecialchars($cert['certificate_number']); ?></strong>
                                        </td>
                                        <td data-label="Patient">
                                            <?php echo htmlspecialchars($cert['patient_name']); ?><br>
                                            <small><?php echo htmlspecialchars($cert['patient_email']); ?></small>
                                        </td>
                                        <td data-label="Doctor">
                                            <?php echo $cert['doctor_name'] ? 'Dr. ' . htmlspecialchars($cert['doctor_name']) : '—'; ?>
                                        </td>
                                        <td data-label="Type"><?php echo $certificateTypes[$cert['certificate_type']] ?? $cert['certificate_type']; ?></td>
                                        <td data-label="Period">
                                            <?php echo date('M j', strtotime($cert['start_date'])) . ' - ' . date('M j, Y', strtotime($cert['end_date'])); ?>
                                        </td>
                                        <td data-label="Amount">$<?php echo number_format($cert['amount_paid'], 2); ?></td>
                                        <td data-label="Status">
                                            <?php
                                            $s = $cert['approval_status'] ?? 'pending';
                                            $sc = match($s) {
                                                'approved' => 'admin-status-completed',
                                                'rejected' => 'admin-status-cancelled',
                                                default => 'admin-status-pending'
                                            };
                                            $st = match($s) {
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                                'pending_consultation' => 'Pending Consultation',
                                                default => ucfirst($s)
                                            };
                                            ?>
                                            <span class="admin-status-badge <?php echo $sc; ?>"><?php echo $st; ?></span>
                                        </td>
                                        <td data-label="Actions">
                                            <a href="?view=<?php echo $cert['certificate_id']; ?>&search=<?php echo urlencode($searchTerm); ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($cert['approval_status'] == 'approved'): ?>
                                                <a href="../download-certificate.php?file=<?php echo $cert['certificate_number']; ?>" class="admin-btn admin-btn-success admin-btn-sm">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Recent Certificates -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fas fa-clock"></i> Recent Certificates</h3>
                </div>
                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>Certificate #</th>
                                <th>Patient</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCerts as $cert): ?>
                                <tr>
                                    <td data-label="Certificate #"><strong><?php echo htmlspecialchars($cert['certificate_number']); ?></strong></td>
                                    <td data-label="Patient">
                                        <?php echo htmlspecialchars($cert['patient_name']); ?><br>
                                        <small><?php echo htmlspecialchars($cert['patient_email']); ?></small>
                                    </td>
                                    <td data-label="Type"><?php echo $certificateTypes[$cert['certificate_type']] ?? $cert['certificate_type']; ?></td>
                                    <td data-label="Amount">$<?php echo number_format($cert['amount_paid'], 2); ?></td>
                                    <td data-label="Status">
                                        <?php
                                        $s = $cert['approval_status'] ?? 'pending';
                                        $sc = $s === 'approved' ? 'admin-status-completed' : ($s === 'rejected' ? 'admin-status-cancelled' : 'admin-status-pending');
                                        $st = $s === 'approved' ? 'Approved' : ($s === 'rejected' ? 'Rejected' : ucfirst(str_replace('_', ' ', $s)));
                                        ?>
                                        <span class="admin-status-badge <?php echo $sc; ?>"><?php echo $st; ?></span>
                                    </td>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($cert['created_at'])); ?></td>
                                    <td data-label="Actions">
                                        <a href="?view=<?php echo $cert['certificate_id']; ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
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