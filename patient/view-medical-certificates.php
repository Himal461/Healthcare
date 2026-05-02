<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$pageTitle = "My Medical Certificates - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/patient.css">';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get patient ID
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient profile not found.";
    header("Location: dashboard.php");
    exit();
}

$patientId = $patient['patientId'];

// Get all medical certificates for this patient
$stmt = $pdo->prepare("
    SELECT mc.*, 
           CONCAT(u.firstName, ' ', u.lastName) as doctor_name,
           d.specialization,
           b.billId as bill_id,
           b.totalAmount as bill_amount,
           b.status as bill_status,
           a.dateTime as appointment_datetime,
           a.status as appointment_status
    FROM medical_certificates mc
    LEFT JOIN doctors d ON mc.doctor_id = d.doctorId
    LEFT JOIN staff s ON d.staffId = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    LEFT JOIN bills b ON mc.bill_id = b.billId
    LEFT JOIN appointments a ON mc.appointment_id = a.appointmentId
    WHERE mc.patient_id = ?
    ORDER BY mc.created_at DESC
");
$stmt->execute([$patientId]);
$certificates = $stmt->fetchAll();

// Statistics
$totalCertificates = count($certificates);
$pendingConsultationCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$totalSpent = 0;

foreach ($certificates as $cert) {
    $status = $cert['approval_status'] ?? 'pending';
    if ($status == 'pending_consultation' || $status == 'pending' || $status === null) {
        $pendingConsultationCount++;
    } elseif ($status == 'approved') {
        $approvedCount++;
    } elseif ($status == 'rejected') {
        $rejectedCount++;
    }
    $totalSpent += $cert['amount_paid'] ?? 0;
}

// Get single certificate view if ID provided
$viewCertificateId = (int)($_GET['view'] ?? 0);
$viewCertificate = null;

if ($viewCertificateId) {
    $stmt = $pdo->prepare("
        SELECT mc.*, 
               CONCAT(u.firstName, ' ', u.lastName) as doctor_name,
               d.specialization,
               s.licenseNumber,
               b.billId as bill_id,
               b.totalAmount as bill_amount,
               b.status as bill_status,
               b.paidAt as bill_paid_at,
               a.dateTime as appointment_datetime,
               a.status as appointment_status
        FROM medical_certificates mc
        LEFT JOIN doctors d ON mc.doctor_id = d.doctorId
        LEFT JOIN staff s ON d.staffId = s.staffId
        LEFT JOIN users u ON s.userId = u.userId
        LEFT JOIN bills b ON mc.bill_id = b.billId
        LEFT JOIN appointments a ON mc.appointment_id = a.appointmentId
        WHERE mc.certificate_id = ? AND mc.patient_id = ?
    ");
    $stmt->execute([$viewCertificateId, $patientId]);
    $viewCertificate = $stmt->fetch();
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$certificateTypes = [
    'work' => 'Work Leave',
    'school' => 'School/University Leave',
    'travel' => 'Travel Cancellation',
    'insurance' => 'Insurance Claim',
    'other' => 'Other'
];
?>

<style>
.certificate-status-badge {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}
.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #dcfce7; color: #166534; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.certificate-preview-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}
.certificate-preview-card:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    border-color: #0d9488;
}
.certificate-detail-view {
    background: white;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
}
.certificate-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 15px;
}
.certificate-number {
    font-size: 18px;
    font-weight: 700;
    color: #0d9488;
}
.certificate-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}
.info-section {
    background: #f8fafc;
    padding: 18px 20px;
    border-radius: 14px;
    border: 1px solid #eef2f6;
}
.info-section h4 {
    color: #1e293b;
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.info-section h4 i { color: #0d9488; }
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}
.info-row:last-child { border-bottom: none; }
.info-label { font-weight: 500; color: #64748b; }
.info-value { font-weight: 600; color: #1e293b; }
.certificate-condition-box {
    background: #fef3c7;
    padding: 20px;
    border-radius: 12px;
    margin: 20px 0;
    border-left: 4px solid #f59e0b;
}
.certificate-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
    flex-wrap: wrap;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}
.empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
.empty-state h3 { color: #1e293b; margin-bottom: 10px; }
.empty-state p { color: #64748b; margin-bottom: 25px; }

/* Consultation Info Box */
.mc-consultation-info-box {
    margin: 20px 0;
    padding: 20px;
    background: #eff6ff;
    border-radius: 12px;
    border: 1px solid #3b82f6;
}
.mc-consultation-info-box h4 {
    color: #1e40af;
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.mc-consultation-info-box h4 i {
    color: #3b82f6;
}
.mc-consultation-info-box p {
    color: #1e3a5f;
    margin: 8px 0;
    font-size: 14px;
}
.mc-consultation-notice {
    margin-top: 15px;
    padding: 12px 15px;
    background: #fef3c7;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #92400e;
    font-size: 14px;
    font-weight: 500;
    border-left: 3px solid #f59e0b;
}
.mc-consultation-notice i {
    color: #f59e0b;
    font-size: 18px;
}

/* Additional Notes Box */
.mc-notes-box {
    margin: 20px 0;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
}
.mc-notes-box h4 {
    color: #1e293b;
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.mc-notes-box h4 i {
    color: #0d9488;
}

/* Approval Info */
.mc-approval-info {
    margin-top: 25px;
    padding: 15px 20px;
    border-radius: 12px;
}
.mc-approval-info.approved {
    background: #dcfce7;
    border: 1px solid #86efac;
}
.mc-approval-info.rejected {
    background: #fee2e2;
    border: 1px solid #fca5a5;
}
.mc-approval-info i {
    margin-right: 8px;
}
.mc-approval-info.approved i { color: #16a34a; }
.mc-approval-info.rejected i { color: #dc2626; }

@media print {
    .patient-page-header, .header-actions, .patient-btn, .certificate-actions { display: none !important; }
}
</style>

<div class="patient-container">
    <div class="patient-page-header">
        <div class="header-title">
            <h1><i class="fas fa-file-medical"></i> My Medical Certificates</h1>
            <p>View and manage your medical certificate history</p>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="patient-btn patient-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="../medical-certificate.php" class="patient-btn patient-btn-primary">
                <i class="fas fa-plus"></i> Request New Certificate
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="patient-alert patient-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="patient-alert patient-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($viewCertificate): ?>
        <!-- Single Certificate Detail View -->
        <div class="certificate-detail-view">
            <div class="certificate-header">
                <div>
                    <span class="certificate-number">
                        <i class="fas fa-certificate"></i> 
                        Certificate #<?php echo htmlspecialchars($viewCertificate['certificate_number']); ?>
                    </span>
                    <p style="color: #64748b; margin-top: 5px;">
                        Requested on <?php echo date('F j, Y', strtotime($viewCertificate['created_at'])); ?>
                    </p>
                </div>
                <div>
                    <?php
                    $status = $viewCertificate['approval_status'] ?? 'pending';
                    if ($status == 'approved') {
                        $statusClass = 'approved';
                        $statusText = '✓ Approved';
                    } elseif ($status == 'rejected') {
                        $statusClass = 'rejected';
                        $statusText = '✗ Rejected';
                    } elseif ($status == 'pending_consultation') {
                        $statusClass = 'pending';
                        $statusText = '⏳ Pending Consultation';
                    } else {
                        $statusClass = 'pending';
                        $statusText = '⏳ Pending';
                    }
                    ?>
                    <span class="certificate-status-badge status-<?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </span>
                </div>
            </div>
            
            <div class="certificate-info-grid">
                <div class="info-section">
                    <h4><i class="fas fa-user"></i> Patient Information</h4>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4><i class="fas fa-user-md"></i> Doctor Information</h4>
                    <div class="info-row">
                        <span class="info-label">Doctor:</span>
                        <span class="info-value">
                            <?php echo $viewCertificate['doctor_name'] ? 'Dr. ' . htmlspecialchars($viewCertificate['doctor_name']) : 'Not assigned'; ?>
                        </span>
                    </div>
                    <?php if ($viewCertificate['specialization']): ?>
                    <div class="info-row">
                        <span class="info-label">Specialization:</span>
                        <span class="info-value"><?php echo htmlspecialchars($viewCertificate['specialization']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-section">
                    <h4><i class="fas fa-calendar"></i> Certificate Details</h4>
                    <div class="info-row">
                        <span class="info-label">Type:</span>
                        <span class="info-value"><?php echo $certificateTypes[$viewCertificate['certificate_type']] ?? $viewCertificate['certificate_type']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Period:</span>
                        <span class="info-value">
                            <?php echo date('M j, Y', strtotime($viewCertificate['start_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($viewCertificate['end_date'])); ?>
                        </span>
                    </div>
                    <?php
                    $days = (strtotime($viewCertificate['end_date']) - strtotime($viewCertificate['start_date'])) / 86400 + 1;
                    ?>
                    <div class="info-row">
                        <span class="info-label">Duration:</span>
                        <span class="info-value"><?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?></span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4><i class="fas fa-credit-card"></i> Payment Information</h4>
                    <div class="info-row">
                        <span class="info-label">Amount Paid:</span>
                        <span class="info-value" style="color: #10b981;">$<?php echo number_format($viewCertificate['amount_paid'], 2); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value"><?php echo ucfirst($viewCertificate['payment_method'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Bill #:</span>
                        <span class="info-value">#<?php echo str_pad($viewCertificate['bill_id'] ?? 0, 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Status:</span>
                        <span class="info-value" style="color: #10b981;">✓ Paid</span>
                    </div>
                </div>
            </div>
            
            <!-- Consultation Appointment Info -->
            <?php if ($viewCertificate['appointment_datetime']): ?>
                <div class="mc-consultation-info-box">
                    <h4><i class="fas fa-calendar-check"></i> Consultation Appointment</h4>
                    <p><strong>Date & Time:</strong> <?php echo date('F j, Y g:i A', strtotime($viewCertificate['appointment_datetime'])); ?></p>
                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($viewCertificate['doctor_name'] ?? 'Assigned Doctor'); ?></p>
                    <p><strong>Appointment Status:</strong> <?php echo ucfirst($viewCertificate['appointment_status']); ?></p>
                    <p><strong>Location:</strong> Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                    <?php if ($viewCertificate['approval_status'] == 'pending_consultation' || $viewCertificate['approval_status'] === null): ?>
                        <div class="mc-consultation-notice">
                            <i class="fas fa-info-circle"></i>
                            <span>Please attend this in-person consultation. The doctor will review your condition and approve the certificate during your visit.</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Medical Condition -->
            <h4 style="color: #1e293b; margin-bottom: 15px;">
                <i class="fas fa-notes-medical" style="color: #0d9488;"></i> Medical Condition
            </h4>
            <div class="certificate-condition-box">
                <?php echo nl2br(htmlspecialchars($viewCertificate['medical_condition'])); ?>
            </div>
            
            <?php if (!empty($viewCertificate['additional_notes'])): ?>
                <div class="mc-notes-box">
                    <h4><i class="fas fa-clipboard"></i> Additional Notes</h4>
                    <p style="color: #475569;"><?php echo nl2br(htmlspecialchars($viewCertificate['additional_notes'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Approval Information -->
            <?php if ($viewCertificate['approval_status'] == 'approved' && $viewCertificate['approved_at']): ?>
                <div class="mc-approval-info approved">
                    <p><i class="fas fa-check-circle"></i> <strong>Approved on:</strong> <?php echo date('F j, Y g:i A', strtotime($viewCertificate['approved_at'])); ?></p>
                </div>
            <?php elseif ($viewCertificate['approval_status'] == 'rejected'): ?>
                <div class="mc-approval-info rejected">
                    <p><i class="fas fa-times-circle"></i> <strong>This certificate was not approved.</strong> Please contact our office for more information.</p>
                </div>
            <?php endif; ?>
            
            <div class="certificate-actions">
                <a href="view-medical-certificates.php" class="patient-btn patient-btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <button onclick="window.print()" class="patient-btn patient-btn-primary">
                    <i class="fas fa-print"></i> Print Certificate
                </button>
                <?php if ($viewCertificate['approval_status'] == 'approved'): ?>
                    <a href="../download-certificate.php?file=<?php echo $viewCertificate['certificate_number']; ?>" 
                       class="patient-btn patient-btn-success">
                        <i class="fas fa-download"></i> Download Certificate
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Statistics Cards -->
        <div class="patient-stats-grid">
            <div class="patient-stat-card total">
                <div class="patient-stat-icon"><i class="fas fa-file-medical"></i></div>
                <div class="patient-stat-content">
                    <h3><?php echo $totalCertificates; ?></h3>
                    <p>Total Certificates</p>
                </div>
            </div>
            <div class="patient-stat-card upcoming">
                <div class="patient-stat-icon"><i class="fas fa-clock"></i></div>
                <div class="patient-stat-content">
                    <h3><?php echo $pendingConsultationCount; ?></h3>
                    <p>Pending Consultation</p>
                </div>
            </div>
            <div class="patient-stat-card completed">
                <div class="patient-stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="patient-stat-content">
                    <h3><?php echo $approvedCount; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="patient-stat-card cancelled">
                <div class="patient-stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="patient-stat-content">
                    <h3><?php echo $rejectedCount; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
            <div class="patient-stat-card bills">
                <div class="patient-stat-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="patient-stat-content">
                    <h3>$<?php echo number_format($totalSpent, 2); ?></h3>
                    <p>Total Spent</p>
                </div>
            </div>
        </div>

        <!-- Certificates List -->
        <div class="patient-card">
            <div class="patient-card-header">
                <h3><i class="fas fa-list"></i> Certificate History (<?php echo $totalCertificates; ?>)</h3>
                <a href="../medical-certificate.php" class="patient-btn patient-btn-primary patient-btn-sm">
                    <i class="fas fa-plus"></i> Request New
                </a>
            </div>
            <div class="patient-card-body">
                <?php if (empty($certificates)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical"></i>
                        <h3>No Medical Certificates Found</h3>
                        <p>You haven't requested any medical certificates yet.</p>
                        <a href="../medical-certificate.php" class="patient-btn patient-btn-primary">
                            <i class="fas fa-plus"></i> Request Your First Certificate
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($certificates as $cert): ?>
                        <div class="certificate-preview-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                                        <span class="certificate-number">#<?php echo htmlspecialchars($cert['certificate_number']); ?></span>
                                        <?php
                                        $status = $cert['approval_status'] ?? 'pending';
                                        if ($status == 'approved') {
                                            $statusClass = 'approved';
                                            $statusText = 'Approved';
                                        } elseif ($status == 'rejected') {
                                            $statusClass = 'rejected';
                                            $statusText = 'Rejected';
                                        } elseif ($status == 'pending_consultation') {
                                            $statusClass = 'pending';
                                            $statusText = 'Pending Consultation';
                                        } else {
                                            $statusClass = 'pending';
                                            $statusText = 'Pending';
                                        }
                                        ?>
                                        <span class="certificate-status-badge status-<?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </div>
                                    <p style="color: #64748b; margin-bottom: 10px;">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php echo date('M j, Y', strtotime($cert['start_date'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($cert['end_date'])); ?>
                                    </p>
                                    <p style="color: #64748b; margin-bottom: 10px;">
                                        <i class="fas fa-stethoscope"></i> 
                                        <?php echo htmlspecialchars(substr($cert['medical_condition'], 0, 80)) . (strlen($cert['medical_condition']) > 80 ? '...' : ''); ?>
                                    </p>
                                    <p style="color: #64748b; margin-bottom: 10px;">
                                        <i class="fas fa-user-md"></i> 
                                        <?php echo $cert['doctor_name'] ? 'Dr. ' . htmlspecialchars($cert['doctor_name']) : 'Doctor not assigned'; ?>
                                    </p>
                                    <?php if ($cert['appointment_datetime']): ?>
                                        <p style="color: #3b82f6; margin-bottom: 10px;">
                                            <i class="fas fa-calendar-check"></i> 
                                            Consultation: <?php echo date('M j, Y g:i A', strtotime($cert['appointment_datetime'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <p style="font-weight: 700; color: #0d9488; font-size: 18px;">
                                        $<?php echo number_format($cert['amount_paid'], 2); ?>
                                    </p>
                                    <p style="color: #64748b; font-size: 13px;">
                                        <?php echo date('M j, Y', strtotime($cert['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px;">
                                <a href="?view=<?php echo $cert['certificate_id']; ?>" class="patient-btn patient-btn-outline patient-btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if ($cert['approval_status'] == 'approved'): ?>
                                    <a href="../download-certificate.php?file=<?php echo $cert['certificate_number']; ?>" 
                                       class="patient-btn patient-btn-success patient-btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>