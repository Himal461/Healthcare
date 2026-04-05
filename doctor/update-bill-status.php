<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$billId = (int)($_GET['bill_id'] ?? 0);
$status = $_GET['status'] ?? '';

if (!$billId || !in_array($status, ['paid', 'cancelled'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: patients.php");
    exit();
}

try {
    // Get bill details first
    $stmt = $pdo->prepare("
        SELECT b.*, p.userId as patientUserId, u.email as patientEmail, u.firstName, u.lastName
        FROM bills b
        JOIN patients p ON b.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        WHERE b.billId = ?
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        $_SESSION['error'] = "Bill not found.";
        header("Location: patients.php");
        exit();
    }

    // Update bill status
    if ($status == 'paid') {
        $updateStmt = $pdo->prepare("
            UPDATE bills 
            SET status = 'paid', paidAt = NOW() 
            WHERE billId = ?
        ");
        $updateStmt->execute([$billId]);
        
        // Create notification for patient
        try {
            $notificationMsg = "Your bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been marked as paid. Thank you!";
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (userId, type, title, message, link, createdAt) 
                VALUES (?, 'billing', 'Payment Confirmed', ?, ?, NOW())
            ");
            $notifyStmt->execute([
                $bill['patientUserId'],
                $notificationMsg,
                "../patient/view-bill.php?bill_id={$billId}"
            ]);
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
        }
        
        // Log the action
        logAction($_SESSION['user_id'], "Marked bill #{$billId} as paid", "Bill amount: {$bill['totalAmount']}");
        
        $_SESSION['success'] = "Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been marked as paid successfully.";
        
    } elseif ($status == 'cancelled') {
        $updateStmt = $pdo->prepare("
            UPDATE bills 
            SET status = 'cancelled' 
            WHERE billId = ?
        ");
        $updateStmt->execute([$billId]);
        
        // Create notification for patient
        try {
            $notificationMsg = "Your bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been cancelled. Please contact the hospital for details.";
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (userId, type, title, message, link, createdAt) 
                VALUES (?, 'billing', 'Bill Cancelled', ?, ?, NOW())
            ");
            $notifyStmt->execute([
                $bill['patientUserId'],
                $notificationMsg,
                "../patient/view-bill.php?bill_id={$billId}"
            ]);
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
        }
        
        // Log the action
        logAction($_SESSION['user_id'], "Cancelled bill #{$billId}", "Bill amount: {$bill['totalAmount']}");
        
        $_SESSION['success'] = "Bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been cancelled.";
    }
    
    // Redirect back to the bill view page
    header("Location: view-bill.php?bill_id={$billId}&status_updated=1");
    exit();
    
} catch (Exception $e) {
    error_log("Error updating bill status: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update bill status: " . $e->getMessage();
    header("Location: view-bill.php?bill_id={$billId}");
    exit();
}
?>