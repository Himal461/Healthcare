<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: billing.php");
    exit();
}

$billId = (int)($_POST['bill_id'] ?? 0);
$status = $_POST['status'] ?? '';
$paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');

$validStatuses = ['paid', 'cancelled', 'unpaid'];
if (!$billId || !in_array($status, $validStatuses)) {
    $_SESSION['error'] = "Invalid bill ID or status.";
    header("Location: billing.php");
    exit();
}

try {
    $pdo->beginTransaction();
    
    if ($status === 'paid') {
        $stmt = $pdo->prepare("UPDATE bills SET status = ?, paidAt = NOW() WHERE billId = ? AND status = 'unpaid'");
        $stmt->execute([$status, $billId]);
        
        if ($stmt->rowCount() > 0) {
            $billStmt = $pdo->prepare("
                SELECT b.*, p.userId as patientUserId, u.firstName, u.lastName 
                FROM bills b 
                JOIN patients p ON b.patientId = p.patientId 
                JOIN users u ON p.userId = u.userId 
                WHERE b.billId = ?
            ");
            $billStmt->execute([$billId]);
            $bill = $billStmt->fetch();
            
            if ($bill) {
                createNotification(
                    $bill['patientUserId'],
                    'billing',
                    'Payment Confirmed',
                    "Your payment of $" . number_format($bill['totalAmount'], 2) . " for Bill #" . 
                    str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been confirmed. Thank you!",
                    "../patient/view-bill.php?bill_id=" . $billId
                );
            }
            
            logAction($_SESSION['user_id'], 'MARK_BILL_PAID', "Marked bill #$billId as paid via $paymentMethod");
            $_SESSION['success'] = "Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " marked as PAID successfully!";
        } else {
            $_SESSION['error'] = "Bill is already paid or does not exist.";
        }
    } elseif ($status === 'cancelled') {
        $stmt = $pdo->prepare("UPDATE bills SET status = ? WHERE billId = ? AND status = 'unpaid'");
        $stmt->execute([$status, $billId]);
        
        if ($stmt->rowCount() > 0) {
            $billStmt = $pdo->prepare("
                SELECT b.*, p.userId as patientUserId FROM bills b 
                JOIN patients p ON b.patientId = p.patientId WHERE b.billId = ?
            ");
            $billStmt->execute([$billId]);
            $bill = $billStmt->fetch();
            
            if ($bill) {
                createNotification(
                    $bill['patientUserId'],
                    'billing',
                    'Bill Cancelled',
                    "Your bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been cancelled.",
                    "../patient/view-bill.php?bill_id=" . $billId
                );
            }
            
            logAction($_SESSION['user_id'], 'CANCEL_BILL', "Cancelled bill #$billId");
            $_SESSION['success'] = "Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " cancelled successfully!";
        } else {
            $_SESSION['error'] = "Bill is already processed or does not exist.";
        }
    }
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to update bill: " . $e->getMessage();
    error_log("Update bill status error: " . $e->getMessage());
}

header("Location: billing.php");
exit();