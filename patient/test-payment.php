<?php
require_once '../includes/config.php';
session_start();

$billId = $_GET['bill_id'] ?? 8;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Test - HealthManagement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/patient.css">
</head>
<body>
    <div class="patient-container">
        <div class="patient-page-header">
            <div class="header-title">
                <h1><i class="fas fa-flask"></i> Payment Test</h1>
                <p>Test payment functionality</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="patient-btn patient-btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="patient-card">
            <div class="patient-card-header">
                <h3><i class="fas fa-receipt"></i> Test Bill Payment</h3>
            </div>
            <div class="patient-card-body">
                <?php
                $stmt = $pdo->prepare("SELECT * FROM bills WHERE billId = ?");
                $stmt->execute([$billId]);
                $bill = $stmt->fetch();
                
                if ($bill): ?>
                    <div class="patient-info-group">
                        <h4>Bill Information</h4>
                        <p><strong>Bill ID:</strong> #<?php echo str_pad($bill['billId'], 6, '0', STR_PAD_LEFT); ?></p>
                        <p><strong>Amount:</strong> $<?php echo number_format($bill['totalAmount'], 2); ?></p>
                        <p><strong>Current Status:</strong> 
                            <span class="patient-status-badge patient-status-<?php echo $bill['status']; ?>">
                                <?php echo ucfirst($bill['status']); ?>
                            </span>
                        </p>
                        <p><strong>Generated:</strong> <?php echo date('M j, Y g:i A', strtotime($bill['generatedAt'])); ?></p>
                        <?php if ($bill['paidAt']): ?>
                            <p><strong>Paid At:</strong> <?php echo date('M j, Y g:i A', strtotime($bill['paidAt'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="patient-alert patient-alert-error">
                        <i class="fas fa-exclamation-circle"></i> Bill #<?php echo $billId; ?> not found.
                    </div>
                <?php endif; ?>

                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $stmt = $pdo->prepare("UPDATE bills SET status = 'paid', paidAt = NOW() WHERE billId = ? AND status = 'unpaid'");
                    $stmt->execute([$billId]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo '<div class="patient-alert patient-alert-success" style="margin-top: 20px;">
                            <i class="fas fa-check-circle"></i> Payment processed successfully!
                        </div>';
                    } else {
                        echo '<div class="patient-alert patient-alert-error" style="margin-top: 20px;">
                            <i class="fas fa-exclamation-circle"></i> Failed to process payment. Bill may already be paid.
                        </div>';
                    }
                }
                ?>

                <?php if ($bill && $bill['status'] == 'unpaid'): ?>
                    <form method="POST" style="margin-top: 25px;">
                        <div class="patient-form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" class="patient-form-control" style="max-width: 300px;">
                                <option value="test">Test Payment</option>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        <button type="submit" class="patient-btn patient-btn-success">
                            <i class="fas fa-credit-card"></i> Test Pay Bill #<?php echo $billId; ?>
                        </button>
                    </form>
                <?php elseif ($bill && $bill['status'] == 'paid'): ?>
                    <div class="patient-alert patient-alert-info" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i> This bill is already marked as paid.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>